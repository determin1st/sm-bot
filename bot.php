<?php
declare(strict_types=1);
namespace SM;
class Bot # {{{
{
  # {{{
  const
    NS               = '\\'.__NAMESPACE__.'\\',
    PROCESS_TICK     = 200000,# 1/5 sec
    PROCESS_CHUNK    = 8000,# bytes
    MESSAGE_LIFETIME = 48*60*60,
    USERNAME_EXP     = '/^[a-z]\w{4,32}$/i',
    BOTNAME_EXP      = '/^[a-z]\w{1,29}bot$/i',
    TOKEN_EXP        = '/^\d{8,10}:[a-z0-9_-]{35}$/i';
  public
    $dir,$cfg,$log,$file,$api,$tp,$text,$cmd,$proc,$user;
  # }}}
  static function start(int $id = 0): never # {{{
  {
    # check requirements
    if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 80000) {
      exit(4);
    }
    # configure environment
    ini_set('html_errors', '0');
    ini_set('implicit_flush', '1');
    set_time_limit(0);
    set_error_handler(function(int $no, string $msg, string $file, int $line) {
      # all errors, except supressed (@) must be handled,
      # unhandled are thrown as an exception
      if (error_reporting() !== 0) {
        throw new \Exception($msg, $no);
      }
      return false;
    });
    # create bot instance
    if (!($bot = self::construct($id, true))) {
      exit(3);# failed to construct
    }
    # guard against non-recoverable errors
    register_shutdown_function(function() use ($bot) {
      error_get_last() && $bot->destruct();
    });
    # enforce graceful termination
    if (function_exists($e = 'sapi_windows_set_ctrl_handler'))
    {
      # WinOS: console breaks should stop masterbot,
      # slavebot must register this handler to terminate properly (by master command)
      $e(function (int $e) use ($bot)
      {
        if ($bot->isMaster)
        {
          $bot->destruct();
          ($e === PHP_WINDOWS_EVENT_CTRL_C)
            ? exit(1) # restart
            : exit(2);# stop
        }
      });
    }
    # operate
    try
    {
      # report startup
      $bot->log->info('started');
      $bot->log->commands();
      # loop
      while (($u = $bot->api->getUpdates()) !== null)
      {
        foreach ($u as $o)
        {
          if (!$bot->update($o)) {
            break 2;
          }
        }
      }
    }
    catch (\Throwable $e)
    {
      $bot->log->exception($e);
      $bot->status = 2;
    }
    # terminate
    $bot->destruct();
    exit($bot->status);
  }
  # }}}
  static function construct(int $id, bool $isConsole): ?self # {{{
  {
    try
    {
      # create instance
      $bot = new self($id, ($id === 0), $isConsole);
      # initialize
      if (!$bot->api->init() ||
          !$bot->cfg->init() ||
          !$bot->log->init() ||
          !$bot->dir->init() ||
          !$bot->file->init())
      {
        throw BotError::skip();
      }
      # set masterbot identifier or
      # make sure identifiers match
      if ($bot->isMaster) {
        $bot->id = $bot->cfg->id;
      }
      elseif ($id !== $bot->cfg->id) {
        throw BotError::text('identifiers mismatch: '.$id.'/'.$bot->cfg->id);
      }
      # load dependencies
      require_once $bot->dir->inc.'sm-mustache'.DIRECTORY_SEPARATOR.'mustache.php';
      require_once $bot->dir->src.'handlers.php';
      # create template parser
      $o = [
        'logger'  => \Closure::fromCallable([
          $bot->log->new('mustache'), 'errorOnly'
        ]),
        'helpers' => [
          'BR'    => "\n",
          'NBSP'  => "\xC2\xA0",
          'ZWSP'  => "\xE2\x80\x8B",# zero-width space
          'BEG'   => "\xC2\xAD\n",
          'END'   => "\xC2\xAD",# SOFT HYPHEN U+00AD
        ]
      ];
      if (!($bot->tp = Mustache::construct($o))) {
        throw BotError::skip();
      }
      # set texts and commands
      if (!($bot->text = BotText::construct($bot)) ||
          !($bot->cmd  = BotCommands::construct($bot)))
      {
        throw BotError::skip();
      }
      # set process controller
      if ($isConsole && !($bot->proc = $bot->isMaster
        ? BotMaster::construct($bot)
        : BotSlave::construct($bot)))
      {
        throw BotError::skip();
      }
    }
    catch (\Throwable $e)
    {
      if (isset($bot))
      {
        $bot->log->exception($e);
        $bot->destruct();
      }
      return null;
    }
    return $bot;
  }
  # }}}
  function __construct(# {{{
    public int  $id,
    public bool $isMaster,
    public bool $isConsole,
    public int  $status = 1,
  )
  {
    $this->dir  = new BotDir($this);
    $this->cfg  = new BotConfig($this);
    $this->log  = new BotLog($this, "$id");
    $this->file = new BotFile($this);
    $this->api  = new BotApi($this);
  }
  # }}}
  function destruct(): void # {{{
  {
    if ($this->log)
    {
      $this->user?->destruct();
      $this->proc?->destruct();
      $this->api?->finit();
      $this->cfg?->destruct();
      $this->log->info('stopped');
      $this->log = null;
    }
  }
  # }}}
  function check(): bool # {{{
  {
    return $this->proc->check();
  }
  # }}}
  function update(object $o): bool # {{{
  {
    # parse update and
    # construct specific request
    $q = $u = $c = null;
    if (isset($o->callback_query))
    {
      # {{{
      if (!isset(($q = $o->callback_query)->from))
      {
        $this->log->warn('incorrect callback');
        $this->log->dump($o);
        return true;
      }
      $u = $q->from;
      if (isset($q->game_short_name))
      {
        #$x = new BotRequestGame(this, $q);
        return true;
      }
      elseif (isset($q->data))
      {
        if (!isset($q->message) ||
            !isset($q->message->chat))
        {
          $this->log->warn('incorrect data callback');
          $this->log->dump($o);
          return true;
        }
        $c = $q->message->chat;
        $q = new BotRequestCallback($this, $q);
      }
      else {
        return true;
      }
      # }}}
    }
    elseif (isset($o->inline_query))
    {
      # {{{
      if (!isset(($q = $o->inline_query)->from))
      {
        $this->log->warn('incorrect inline query');
        $this->log->dump($o);
        return true;
      }
      $u = $q->from;
      #$q = new BotRequestInline(this, $q);
      return true;
      # }}}
    }
    elseif (isset($o->message))
    {
      # {{{
      if (!isset(($q = $o->message)->from) ||
          !isset($q->chat))
      {
        $this->log->warn('incorrect message');
        $this->log->dump($o);
        return true;
      }
      $u = $q->from;
      $c = $q->chat;
      $q = (isset($q->text) && ($q->text[0] === '/'))
        ? new BotRequestCommand($this, $q)
        : new BotRequestInput($this, $q);
      # }}}
    }
    elseif (isset($o->my_chat_member))
    {
      # {{{
      if (!isset(($q = $o->my_chat_member)->from) ||
          !isset($q->chat) || !isset($q->date) ||
          !isset($q->old_chat_member) ||
          !isset($q->new_chat_member))
      {
        $this->log->warn('incorrect member update');
        $this->log->dump($o);
        return true;
      }
      $u = $q->from;
      $c = $q->chat;
      $q = new BotRequestChat($this, $q);
      # }}}
    }
    elseif (isset($o->edited_message))
    {
      return true;
    }
    else
    {
      $this->log->warn('unknown update type');
      $this->log->dump($o);
      return true;
    }
    # construct and attach user
    if (!($this->user = BotUser::construct($this, $u, $c))) {
      return true;
    }
    # get current status
    $status = $this->status;
    # reply and detach
    $this->user->destruct($q->response());
    $this->user = null;
    # complete negative when status changed
    return $status === $this->status;
  }
  # }}}
}
# }}}
class BotError extends \Error # {{{
{
  static function text(string $msg): self {
    return new self($msg);
  }
  static function skip(): self {
    return new self('');
  }
  static function from(object $e): object
  {
    return ($e instanceof BotError)
      ? ($e->origin ?: $e)
      : new self('', $e);
  }
  function __construct(
    string $msg,
    public ?object $origin = null
  )
  {
    parent::__construct($msg, -1);
  }
}
# }}}
class BotDir # {{{
{
  public $home,$inc,$dataRoot,$data,$usr,$grp,$img,$font,$srcRoot,$src;
  function __construct(public object $bot) # {{{
  {
    # determine proper data sub-directory
    $a = $bot->isMaster ? 'master' : $bot->id;
    # determine base paths
    $this->home = __DIR__.DIRECTORY_SEPARATOR;
    $this->inc  = $this->home.'inc'.DIRECTORY_SEPARATOR;
    $this->dataRoot = $this->home.'data'.DIRECTORY_SEPARATOR;
    $this->srcRoot  = $this->home.'bots'.DIRECTORY_SEPARATOR;
    $this->data = $this->dataRoot.$a.DIRECTORY_SEPARATOR;
    $this->usr  = $this->data.'usr'.DIRECTORY_SEPARATOR;
    $this->grp  = $this->data.'grp'.DIRECTORY_SEPARATOR;
    $this->img  = [];
    $this->font = [];
    file_exists($a = $this->data.'img'.DIRECTORY_SEPARATOR) && ($this->img[] = $a);
    file_exists($a = $this->data.'font'.DIRECTORY_SEPARATOR) && ($this->font[] = $a);
  }
  # }}}
  function init(): bool # {{{
  {
    # prepare
    $bot = $this->bot;
    $log = $bot->log->new('dir');
    # check everything
    if (!file_exists($this->inc))
    {
      $log->error('not found: '.$this->inc);
      return false;
    }
    if (!file_exists($this->data))
    {
      $log->error('not found: '.$this->data);
      return false;
    }
    if (!file_exists($this->usr) && !@mkdir($this->usr))
    {
      $log->error("mkdir($this->usr) failed");
      return false;
    }
    if (!file_exists($this->grp) && !@mkdir($this->grp))
    {
      $log->error("mkdir($this->grp) failed");
      return false;
    }
    if (!file_exists($this->srcRoot))
    {
      $log->error('not found: '.$this->srcRoot);
      return false;
    }
    # determine bot source directory
    $this->src = $this->srcRoot.$bot->cfg->source.DIRECTORY_SEPARATOR;
    if (!file_exists($this->src))
    {
      $log->error('not found: '.$this->src);
      return false;
    }
    # add bot image and font directories
    $a = 'img'.DIRECTORY_SEPARATOR;
    file_exists($b = $this->src.$a) && ($this->img[] = $b);
    $this->img[] = $this->inc.$a;
    $a = 'font'.DIRECTORY_SEPARATOR;
    file_exists($b = $this->src.$a) && ($this->font[] = $b);
    $this->font[] = $this->inc.$a;
    # done
    return true;
  }
  # }}}
}
# }}}
class BotConfig implements \JsonSerializable # {{{
{
  const FILE_INC  = 'config.inc';
  const FILE_JSON = 'config.json';
  const PROPS = [
    # {{{
    'source' => '',
    'token'  => '',
    'admins' => [],
    'id'     => 0,
    'name'   => '',
    'lang'   => '',
    'useFileIds' => false,
    'BotLog'      => [
      'debug'     => true,# display debug output?
      'infoFile'  => '',
      'errorFile' => '',
    ],
    'BotMaster' => [
      'slaves'  => [],# previously started
    ],
    'BotRequestInput' => [
      'wipeInput'     => true,
    ],
    'BotRequestCommand' => [
      'wipeInput'       => true,
      #'replyFailed' => false,
    ],
    'BotRequestCallback' => [
      'replyBad'         => true,# incorrect data
      'replyUnknown'     => true,# item not found
      'replyFast'        => true,# reply before rendered
    ],
    'BotImgMessage' => [
      'placeholder' => [
        'size'      => [640,160],
        'color'     => [0,0,0],# black
      ],
    ],
    'BotImgItem'     => [
      'image'        => [
        'file'       => '',
        'lookup'     => false,
      ],
      'title'        => [
        'file'       => '',
        'size'       => [640,160],
        'color'      => [0,0,32],
        'header'     => [
          'font'     => 'Days.ttf',
          'size'     => [6,64],# [min,max] font size
          'color'    => [255,255,255],# white
          'rect'     => [140,360,0,160],# rect [x,w,y,h]
        ],
        'breadcrumb' => [
          'font'     => 'Bender-Italic.ttf',
          'size'     => 16,
          'color'    => [135,206,235],# skyblue
          'pos'      => [140,32],# coordinates
        ],
      ],
    ],
    'BotListItem' => [
      'cols'      => 1,# columns in markup
      'rows'      => 8,# rows in markup
      'flexy'     => true,# hide empty rows
      'order'     => 'id',# order tag name
      'desc'      => false,# descending order
      'timeout'   => 0,# data refresh timeout (sec), 0=always
      'item'      => '',
      'func'      => '',
      'markup'    => [
        'head'    => [],
        'foot'    => [['!prev','!next'],['!up']],
        'empty'   => [['!up']],
      ],
    ],
    'BotFormItem'      => [
      'cols'           => 3,# number of options in a row
      'colsFlex'       => false,# allow variable cols in the last row
      'isPersistent'   => false,# change instead of repeat
      'resetFailed'    => true,# allow to reset from negative state
      'resetCompleted' => true,# allow to reset from positive state
      'resetAll'       => false,# do a full reset (all steps)
      'moveAround'     => false,# allow to cycle back/forward
      'backDisable'    => false,# disable at start position
      'backStep'       => true,# allow to return to the previous step
      'backStepReset'  => true,# reset current step before returning
      'forwardDisable' => false,# disable at last position
      'forwardSkip'    => false,# allow to skip required field
      'forwardToOk'    => true,# last forward becomes ok
      'okIsForward'    => false,# ok acts as forward until last field
      'okWhenReady'    => true,# ok only when all required fields filled
      'okDisable'      => true,# disable ok when missing required
      'okConfirm'      => true,# enable confirmation step
      'retryFailed'    => true,# allow to retry failed submission
      'retryDisable'   => true,# disable retry rather than hide
      'clearSolid'     => true,# show empty bar when clearing is not feasible
      'selectDeselect' => true,# allow to de-select selected option
      'hiddenValue'    => '✶✶✶',
      'markup'         => [
        'failure'      => [['!back','!retry'],['!up']],
        'inputHead'    => [],
        'inputFoot'    => [['!back','!ok','!forward'],['!up']],
        'confirm'      => [['!back','!submit'],['!up']],
        'success'      => [['!repeat','!change'],['!up']],
      ],
    ],
    # }}}
  ];
  function __construct(# {{{
    public ?object  $bot,
    public string   $file    = '',
    public bool     $changed = false
  )
  {
    # create properties dynamically
    foreach (self::PROPS as $k => $v) {
      $this->$k = $v;
    }
  }
  # }}}
  function init(): bool # {{{
  {
    # prepare
    $bot      = $this->bot;
    $dir      = $bot->dir->data;
    $fileInc  = $dir.self::FILE_INC;
    $fileJson = $dir.self::FILE_JSON;
    $lock     = "$fileJson.lock";
    # setup
    if (!file_exists($fileJson) && !self::setup($dir, $bot)) {
      return false;
    }
    # load
    if (!($o = $bot->file->getJSON($fileJson))) {
      return false;
    }
    foreach ($o as $k => $v) {
      isset($this->$k) && ($this->$k = $v);
    }
    # set or check lock
    if ($bot->isConsole)
    {
      if (file_exists($lock))
      {
        $bot->log->warn('configuration is already locked');
        return false;
      }
      if (!BotFile::lock($fileJson))
      {
        $bot->log->warn("failed to lock: $fileJson");
        return false;
      }
    }
    elseif (!file_exists($lock))
    {
      $bot->log->warn('configuration is not locked');
      return false;
    }
    # complete
    $this->file = $fileJson;
    return true;
  }
  # }}}
  function destruct(): void # {{{
  {
    if ($this->bot)
    {
      if ($this->file)
      {
        # save
        if ($this->changed) {
          $this->bot->file->setJSON($this->file, $this);
        }
        # unlock
        $this->bot->isConsole &&
        BotFile::unlock($this->file);
      }
      # cleanup
      $this->bot = null;
    }
  }
  # }}}
  function jsonSerialize(): array # {{{
  {
    $a = [];
    foreach (array_keys(self::PROPS) as $k) {
      $a[$k] = $this->$k;
    }
    return $a;
  }
  # }}}
  static function setup(string $dir, object $bot): bool # {{{
  {
    # prepare
    $log = $bot->log->new('setup');
    $fileInc  = $dir.self::FILE_INC;
    $fileJson = $dir.self::FILE_JSON;
    # check files
    if (file_exists($fileJson))
    {
      $log->error("configuration already exists: $fileJson");
      return false;
    }
    if (!file_exists($fileInc))
    {
      $log->error("initial configuration not found: $fileInc");
      return false;
    }
    # load source
    if (!($o = require $fileInc)  || !is_array($o) ||
        !isset($o[$k = 'source']) || !$o[$k] ||
        !isset($o[$k = 'token'])  || !$o[$k] ||
        !isset($o[$k = 'admins']))
    {
      $log->error("incorrect data: $fileInc");
      return false;
    }
    # check source
    if (!file_exists($k = $bot->dir->srcRoot.DIRECTORY_SEPARATOR.$o['source']))
    {
      $log->error("source directory not found: $k");
      return false;
    }
    # check token
    if (!preg_match(Bot::TOKEN_EXP, $v = $o['token']))
    {
      $log->error("incorrect token: $token");
      return false;
    }
    # extract and set identifier
    $k = substr($v, 0, strpos($v, ':'));
    $o['id'] = intval($k);
    # request bot information
    if (!($v = $bot->api->send('getMe', null, null, $o['token'])) ||
        !isset($v->username))
    {
      $log->error("failed to get bot information: ".$bot->api->error);
      return false;
    }
    # set name
    $o['name'] = $v->username;
    # save new configuration
    if (!$bot->file->setJSON($fileJson, $o))
    {
      $log->error("failed to store: $fileJson");
      return false;
    }
    # complete
    $log->info($k.'@'.$v->username);
    return true;
  }
  # }}}
}
# }}}
abstract class BotConfigAccess implements \ArrayAccess # {{{
{
  function offsetExists(mixed $k): bool {
    return false;
  }
  function offsetGet(mixed $k): mixed
  {
    static $name;
    if (!$name)
    {
      ($i = strrpos($name = $this::class, '\\')) &&
      ($name = substr($name, $i + 1));
    }
    return $this->bot->cfg->$name[$k];
  }
  function offsetSet(mixed $k, mixed $v): void
  {
    static $name;
    if (!$name)
    {
      ($i = strrpos($name = $this::class, '\\')) &&
      ($name = substr($name, $i + 1));
    }
    $this->bot->cfg->$name[$k] = $v;
    $this->bot->cfg->changed = true;
  }
  function offsetUnset(mixed $k): void
  {}
}
# }}}
class BotLog extends BotConfigAccess # {{{
{
  function __construct(# {{{
    public object  $bot,
    public string  $name,
    public ?object $parent = null,
  ) {}
  # }}}
  function init(): bool # {{{
  {
    $this->name = $this->bot->cfg->name;
    return true;
  }
  # }}}
  function new(string $name): self # {{{
  {
    return new self($this->bot, $name, $this);
  }
  # }}}
  function newObject(object $o): self # {{{
  {
    # determine object class
    ($i = strrpos($name = $o::class, '\\')) &&
    ($name = substr($name, $i + 1));
    # construct
    return new self($this->bot, $name, $this);
  }
  # }}}
  function debug(string ...$msg): void # {{{
  {
    $this['debug'] && $this->out(0, 0, ...$msg);
  }
  # }}}
  function info(string ...$msg): void # {{{
  {
    $this->out(0, 0, ...$msg);
  }
  # }}}
  function infoInput(string $msg): void # {{{
  {
    $this->out(0, 1, $msg);
  }
  # }}}
  function error(string ...$msg): void # {{{
  {
    $this->out(1, 0, ...$msg);
  }
  # }}}
  function errorOnly(string $msg, int $level): void # {{{
  {
    $level && $this->out(1, 0, $msg);
  }
  # }}}
  function warn(string ...$msg): void # {{{
  {
    $this->out(2, 0, ...$msg);
  }
  # }}}
  function warnInput(string $msg): void # {{{
  {
    $this->out(2, 1, $msg);
  }
  # }}}
  function exception(object $e): void # {{{
  {
    # check object type
    if ($e instanceof BotError)
    {
      if (!$e->origin)
      {
        ($msg = $e->getMessage()) && $this->error($msg);
        return;
      }
      $e = $e->origin;
    }
    $msg = $e->getMessage();
    # determine trace
    $a = $e->getTraceAsString();
    if ($b = strpos($a, "\n"))
    {
      $a = str_replace("\n", "\n  ", substr($a, $b + 1));
      $a = str_replace(__DIR__, '', $a);
    }
    $b = $e->getTrace()[0];
    $b = isset($b['file'])
      ? str_replace(__DIR__, '', $b['file']).'('.$b['line'].')'
      : '---';
    ###
    $this->out(1, 0, get_class($e), "$msg\n  #0 $b\n  $a");
  }
  # }}}
  function commands(): void # {{{
  {
    ($proc = $this->bot->proc) &&
    $proc->out(self::parseTree($this->bot->cmd->tree, 0, 'cyan')."\n");
  }
  # }}}
  function dump(mixed $var): void # {{{
  {
    if ($proc = $this->bot->proc) {
      $proc->out(var_export($var, true)."\n");
    }
  }
  # }}}
  # helpers
  function out(int $level, int $sep, string ...$msg): void # {{{
  {
    # prepare
    static $COLOR = ['green','red','yellow'];# [info,error,warn]
    static $SEP = ['>','<'];# [output,input]
    static $PROMPT = ['@', 'cyan'];# [prefix,color]
    # file output
    if (0)
    {
      #$a = date(DATE_ATOM).': ';
      #$b = $name ? implode(' '.$PREFIX, $name) : '';
      #file_put_contents($f, $a.$b.$msg."\n", FILE_APPEND);
    }
    # console output
    if ($this->bot->isConsole)
    {
      # compose name chain
      $c = $COLOR[$level];
      $s = self::fgColor($SEP[$sep], $c, 1);
      $x = '';
      $p = $this;
      while ($p->parent)
      {
        $n = $level
          ? self::bgColor($p->name, $c)
          : self::fgColor($p->name, $c);
        $x = "$n $s $x";
        $p = $p->parent;
      }
      # compose msg chain
      for ($i = 0, $j = count($msg) - 1; $i < $j; ++$i) {
        $x = $x.self::bgColor($msg[$i], $c)." $s ";
      }
      # compose all
      $x = (
        self::fgColor($PROMPT[0], $PROMPT[1], 1).
        self::fgColor($p->name, $PROMPT[1], 0).
        " $s $x".$msg[$j]."\n"
      );
      # output
      ($f = $this->bot->proc)
        ? $f->out($x)
        : fwrite(STDOUT, $x);
    }
  }
  # }}}
  static function bgColor(string $str, string $name, int $strong=0): string # {{{
  {
    static $COLOR = [
      'black'   => [40,100],
      'red'     => [41,101],
      'green'   => [42,102],
      'yellow'  => [43,103],
      'blue'    => [44,104],
      'magenta' => [45,105],
      'cyan'    => [46,106],
      'white'   => [47,107],
    ];
    $x = $COLOR[$name][$strong];
    return (strpos($str, "\n") === false)
      ? "[{$x}m{$str}[0m"
      : "[{$x}m".str_replace("\n", "[0m\n[{$x}m", $str).'[0m';
  }
  # }}}
  static function fgColor(string $str, string $name, int $strong=0): string # {{{
  {
    static $COLOR = [
      'black'   => [30,90],
      'red'     => [31,91],
      'green'   => [32,92],
      'yellow'  => [33,93],
      'blue'    => [34,94],
      'magenta' => [35,95],
      'cyan'    => [36,96],
      'white'   => [37,97],
    ];
    $x = $COLOR[$name][$strong];
    return "[{$x}m{$str}[0m";
  }
  # }}}
  static function parseTree(# {{{
    ?array &$tree,
    int    $pad,
    string $color,
    array  &$indent = [],
    int    $level   = 0
  ):string
  {
    # prepare
    $x = '';
    $i = 0;
    $j = count($tree);
    # iterate
    foreach ($tree as &$a)
    {
      # compose indent
      $pad && ($x .= str_repeat(' ', $pad));
      foreach ($indent as $b) {
        $x .= $b ? self::fgColor('│ ', $color, 1) : '  ';
      }
      # compose item line
      $b  = (++$i === $j);
      $c  = self::fgColor(($b ? '└─' : '├─'), $color, 1);
      $x .= $c.$a->skel['name']."\n";
      # recurse
      if ($a->items)
      {
        $indent[] = !$b;
        $x .= self::parseTree($a->items, $pad, $color, $indent, $level + 1);
        array_pop($indent);
      }
    }
    return $x;
  }
  # }}}
}
# }}}
class BotFile # {{{
{
  const FILE_JSON = ['fids.json','fonts.json'];
  public $log,$fids = [],$font;
  function __construct(public object $bot) # {{{
  {
    $this->log = $bot->log->new('file');
  }
  # }}}
  function init(): bool # {{{
  {
    # prepare
    $bot = $this->bot;
    $dir = $bot->dir->data;
    # load file identifiers map
    if ($bot->cfg->useFileIds)
    {
      $file = $dir.self::FILE_JSON[0];
      if (($this->fids = $this->getJSON($file)) === null) {
        return false;
      }
    }
    # load fonts map
    if (file_exists($file = $dir.self::FILE_JSON[1]))
    {
      if (($this->font = $this->getJSON($file)) === null) {
        return false;
      }
    }
    else
    {
      $this->font = [];
      foreach ($bot->dir->font as $dir)
      {
        $i = strlen($dir);
        foreach (glob($dir.'*.ttf', GLOB_NOESCAPE) as $font) {
          $this->font[substr($font, $i)] = $font;
        }
      }
      $this->setJSON($file, $this->font);
    }
    # complete
    return true;
  }
  # }}}
  function getJSON(string $file): ?array # {{{
  {
    if (!file_exists($file)) {
      return [];
    }
    if (($data = file_get_contents($file)) === false)
    {
      $this->log->error(__FUNCTION__, "file_get_contents($file)");
      return null;
    }
    if (($data = json_decode($data, true)) === null &&
        json_last_error() !== JSON_ERROR_NONE)
    {
      $this->log->error(__FUNCTION__, "json_decode($file): ".json_last_error_msg());
      return null;
    }
    if (!is_array($data))
    {
      $this->log->error(__FUNCTION__, "$file: incorrect data type");
      return null;
    }
    return $data;
  }
  # }}}
  function setJSON(string $file, array|object $data): bool # {{{
  {
    if ($data)
    {
      if (($data = json_encode($data, JSON_UNESCAPED_UNICODE)) === false)
      {
        $this->log->error(__FUNCTION__, "json_encode($file): ".json_last_error_msg());
        return false;
      }
      if (file_put_contents($file, $data) === false)
      {
        $this->log->error(__FUNCTION__, "file_put_contents($file)");
        return false;
      }
    }
    elseif (file_exists($file))
    {
      if (@unlink($file) === false)
      {
        $this->log->error(__FUNCTION__, "unlink($file)");
        return false;
      }
    }
    $this->log->debug(__FUNCTION__, $file);
    return true;
  }
  # }}}
  function getImage(string $file): string # {{{
  {
    # check user directory
    if (($a = $this->bot->user?->dir) &&
        file_exists($b = $a.$file))
    {
      return $b;
    }
    # search in image directories
    foreach ($this->bot->dir->img as $a)
    {
      if (file_exists($b = $a.$file)) {
        return $b;
      }
    }
    $this->log->error("image not found: $file");
    return '';
  }
  # }}}
  function mapImage(string &$file): bool # {{{
  {
    if (!($path = $this->getImage($file))) {
      return false;
    }
    $file = $path;
    return true;
  }
  # }}}
  function mapFont(string &$file): bool # {{{
  {
    if (isset($this->font[$file]))
    {
      $file = $this->font[$file];
      return true;
    }
    $this->log->error("font not found: $file");
    return false;
  }
  # }}}
  function getId(string $file): string # {{{
  {
    return ($file && $this->fids !== null && isset($this->fids[$file]))
      ? $this->fids[$file]
      : '';
  }
  # }}}
  function setId(string $file, string $id): void # {{{
  {
    if ($this->bot->cfg->useFileIds && $file)
    {
      $this->fids[$file] = $id;
      if (self::lock($file = $this->bot->dir->data.self::FILE_JSON[0]))
      {
        $this->log->debug(__FUNCTION__."($file)");
        $this->setJSON($file, $this->fids);
        self::unlock($file);
      }
    }
  }
  # }}}
  static function lock(# {{{
    string  $file,
    string  $forceId = '',
    int     $tries = 5
  ):string
  {
    # prepare
    static $sleep   = 100000;# 100ms
    static $timeout = 20;
    $lock  = "$file.lock";
    $time  = time();
    $count = 20;
    # check
    if (!$tries) {
      return '';
    }
    # wait released
    while (file_exists($lock) && --$count) {
      usleep($sleep);
    }
    # check exhausted
    if (!$count)
    {
      # get last modification timestamp
      if (!($x = filemtime($lock))) {
        return '';
      }
      # check fresh
      if (($time - $x) <= $timeout)
      {
        return $forceId
          ? self::lock($file, $forceId, $tries - 1)
          : '';
      }
      # remove staled lockfile
      if (!@unlink($lock)) {
        return '';
      }
    }
    # create lockfile
    if ($forceId)
    {
      # store identifier
      if (file_put_contents($lock, $forceId) === false) {
        return '';
      }
      # make user no collisions
      if (file_get_contents($lock) !== $forceId) {
        return self::lock($file, $forceId, $tries - 1);
      }
    }
    else
    {
      if (!touch($lock, $time)) {
        return '';
      }
    }
    # complete
    return $lock;
  }
  # }}}
  static function unlock(string $file): string # {{{
  {
    return (!file_exists($lock = "$file.lock") || @unlink($lock))
      ? '' : $lock;
  }
  # }}}
}
# }}}
class BotApi # {{{
{
  const URL = 'https://api.telegram.org/bot';
  const POLLING_TIMEOUT = 120;# current max=50?
  const CONFIG = [
    # {{{
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,# default(0): 300
    CURLOPT_TIMEOUT        => 0,# default(0): never
    CURLOPT_FORBID_REUSE   => false,
    CURLOPT_FRESH_CONNECT  => false,
    ###
    CURLOPT_TCP_NODELAY    => true,
    CURLOPT_TCP_KEEPALIVE  => 1,
    CURLOPT_TCP_KEEPIDLE   => 300,
    CURLOPT_TCP_KEEPINTVL  => 300,
    #CURLOPT_TCP_FASTOPEN   => true,
    ###
    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
    CURLMOPT_PIPELINING    => 0,
    CURLOPT_PIPEWAIT       => false,
    CURLOPT_FOLLOWLOCATION => false,
    #CURLOPT_NOSIGNAL       => true,
    #CURLOPT_VERBOSE        => true,
    CURLOPT_SSL_VERIFYSTATUS => false,# require OCSP during the TLS handshake?
    CURLOPT_SSL_VERIFYHOST   => 0,# are you afraid of MITM?
    CURLOPT_SSL_VERIFYPEER   => false,# diallow self-signed certs?
    # }}}
  ];
  public $log,$curl,$murl,$error = '';
  function __construct(public object $bot)# {{{
  {
    $this->log = $bot->log->new('api');
  }
  # }}}
  function init(): bool # {{{
  {
    $curl = $murl = null;
    try
    {
      # create curl instance
      if (!($curl = curl_init())) {
        throw BotError::text('curl_init() failed');
      }
      # configure
      if (!curl_setopt_array($curl, self::CONFIG)) {
        throw BotError::text('failed to configure');
      }
      # create multi-curl instance
      if (!($murl = curl_multi_init())) {
        throw BotError::text('curl_multi_init() failed');
      }
      # complete
      $this->curl = $curl;
      $this->murl = $murl;
    }
    catch (\Throwable $e)
    {
      $this->log->exception($e);
      $curl && curl_close($curl);
      $murl && curl_multi_close($murl);
      return false;
    }
    return true;
  }
  # }}}
  function finit(): void # {{{
  {
    if ($this->murl)
    {
      $this->getUpdates(true);
      curl_multi_close($this->murl);
      $this->murl = null;
    }
    if ($this->curl)
    {
      curl_close($this->curl);
      $this->curl = null;
    }
  }
  # }}}
  function getUpdates(bool $end = false): ?array # {{{
  {
    # prepare
    static $log, $o, $q;
    # initialize
    if (!$log)
    {
      if ($end) {# ignore, never called
        return null;
      }
      $log = $this->log->new('getUpdates');
      $o = [
        'offset'  => 0,
        'limit'   => 100,
        'timeout' => self::POLLING_TIMEOUT
      ];
      $q = self::URL.$this->bot->cfg->token;
      $q = [
        CURLOPT_URL  => $q.'/getUpdates',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => &$o
      ];
    }
    # set final parameters
    if ($end)
    {
      if (!$o['offset']) {
        return null;# offset wasn't shifted
      }
      $o['limit']   = 1;
      $o['timeout'] = 0;
    }
    # configure request
    if (!curl_setopt_array($this->curl, $q))
    {
      $log->error('failed to configure');
      return null;
    }
    # to save remote offset,
    # perform short polling routine
    if ($end)
    {
      @curl_exec($this->curl);
      return $log = null;
    }
    # to get bot updates,
    # perform long polling routine
    # {{{
    if ($a = curl_multi_add_handle($this->murl, $this->curl))
    {
      $log->error(curl_multi_strerror($a));
      return null;
    }
    try
    {
      $a = 1;
      while (1)
      {
        if ($b = curl_multi_exec($this->murl, $a))
        {
          $log->error(curl_multi_strerror($b));
          return null;
        }
        elseif ($a === 0) {
          break;
        }
        while (($b = curl_multi_select($this->murl, 0.5)) === 0)
        {
          usleep(Bot::PROCESS_TICK);
          if (!$this->bot->proc->check()) {
            return null;
          }
        }
        if ($b === -1)
        {
          $log->error(curl_multi_strerror(curl_multi_errno($this->murl)));
          return null;
        }
      }
      # check connection status
      if (($a = curl_multi_info_read($this->murl)) &&
          ($a = $a['result']))
      {
        $log->error(curl_strerror($a));
        return null;
      }
      if (!($a = curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE)))
      {
        $log->error('connection failed');
        return null;
      }
      if ($a !== 200)
      {
        $log->error("HTTP status $a");
        return null;
      }
      # get response text
      if (!($a = curl_multi_getcontent($this->curl))) {
        return [];
      }
    }
    finally
    {
      # cleanup
      if ($b = curl_multi_remove_handle($this->murl, $this->curl))
      {
        $log->error(curl_multi_strerror($b));
        return null;
      }
    }
    # }}}
    # decode result
    if (!($b = json_decode($a, false)) || !is_object($b))
    {
      $log->error("incorrect response\n█{$a}█\n");
      return null;
    }
    # check response flag
    if (!$b->ok)
    {
      $log->error(isset($b->description)
        ? $b->description
        : "incorrect response\n█{$a}█\n"
      );
      return null;
    }
    # shift offset
    if ($a = count($b->result)) {
      $o['offset'] = 1 + $b->result[$a - 1]->update_id;
    }
    # complete
    return $b->result;
  }
  # }}}
  function send(# {{{
    string  $method,
    ?array  $req,
    ?object $file  = null,
    string  $token = ''
  ):object|bool
  {
    static $FILE_METHOD = [
      'sendPhoto'     => 'photo',
      'sendAudio'     => 'audio',
      'sendDocument'  => 'document',
      'sendVideo'     => 'video',
      'sendAnimation' => 'animation',
      'sendVoice'     => 'voice',
      'sendVideoNote' => 'video_note',
    ];
    try
    {
      # determine file attachment
      if ($file !== null) {
        $req[$file->postname] = $file;
      }
      elseif (isset($FILE_METHOD[$method]) &&
              isset($req[$a = $FILE_METHOD[$method]]) &&
              $req[$a] instanceof BotApiFile)
      {
        $file = $req[$a];
      }
      # determine token
      $token || ($token = $this->bot->cfg->token);
      # set request parameters
      $req = [
        CURLOPT_URL => self::URL.$token.'/'.$method,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $req,
      ];
      if (!curl_setopt_array($this->curl, $req)) {
        throw BotError::text('failed to setup the request');
      }
      # send
      if (($x = curl_exec($this->curl)) === false) {
        throw BotError::text('curl failed('.curl_errno($this->curl).'): '.curl_error($this->curl));
      }
      # decode response
      if (!($res = json_decode($x, false))) {
        throw BotError::text("incorrect response JSON: █$x█");
      }
      # check response result
      if (!($res->ok ?? false) || !isset($res->result))
      {
        # compose error message
        if (isset($res->description))
        {
          $x = isset($res->error_code)
            ? '('.$res->error_code.') '.$res->description
            : $res->description;
        }
        else {
          $x = "incorrect response: █$x█";
        }
        throw BotError::text($x);
      }
      # success
      $res = $res->result;
    }
    catch (\Throwable $e)
    {
      # report error
      $this->log->exception($e);
      $this->error = $e->getMessage();
      $res = false;
    }
    # remove temporary file (if any)
    if ($file !== null) {
      $file->destruct();
    }
    # complete
    return $res;
  }
  # }}}
  function deleteMessage(object $msg): bool # {{{
  {
    return !!$this->send('deleteMessage', [
      'chat_id'    => $msg->chat->id,
      'message_id' => $msg->message_id,
    ]);
  }
  # }}}
}
# }}}
class BotApiFile extends \CURLFile # {{{
{
  public $isTemp;
  static function construct(string $file, bool $isTemp): self
  {
    $o = new static($file);
    $o->postname = basename($file);
    $o->isTemp   = $isTemp;
    return $o;
  }
  function destruct(): void
  {
    # remove temporary file
    if ($this->isTemp && $this->name && file_exists($this->name))
    {
      @unlink($this->name);
      $this->name = '';
    }
  }
  function __destruct() {
    $this->destruct();
  }
}
# }}}
class BotText implements \ArrayAccess # {{{
{
  const FILE_INC  = ['texts.inc','captions.inc'];
  const FILE_JSON = ['texts.json','captions.json'];
  const EMOJI = [
    # {{{
    'arrow_up'         => "\xE2\xAC\x86",
    'arrow_down'       => "\xE2\xAC\x87",
    'arrow_up_small'   => "\xF0\x9F\x94\xBC",
    'arrow_down_small' => "\xF0\x9F\x94\xBD",
    'arrow_left'       => "\xE2\xAC\x85",
    'arrow_right'      => "\xE2\x9E\xA1",
    'arrow_forward'    => "\xE2\x96\xB6",
    'arrow_backward'   => "\xE2\x97\x80",
    'fast_forward'     => "\xE2\x8F\xA9",
    'rewind'           => "\xE2\x8F\xAA",
    'eject_symbol'     => "\xE2\x8F\x8F",
    'scroll'           => "\xF0\x9F\x93\x9C",
    'game_die'         => "\xF0\x9F\x8E\xB2",
    'video_game'       => "\xF0\x9F\x8E\xAE",
    'exclamation'      => "\xE2\x9D\x97",
    'question'         => "\xE2\x9D\x93",
    'slot_machine'     => "\xF0\x9F\x8E\xB0",
    'no_entry_sign'    => "\xF0\x9F\x9A\xAB",
    'star'             => "\xE2\xAD\x90",
    'star2'            => "\xF0\x9F\x8C\x9F",
    'stop_button'      => "\xE2\x8F\xB9",
    'previous_track'   => "\xE2\x8F\xAE",
    'next_track'       => "\xE2\x8F\xAD",
    'heavy_multiplication_x' => "\xE2\x9C\x96",
    'heavy_minus_sign' => "\xE2\x9E\x96",
    'x'                => "\xE2\x9D\x8C",
    'record_button'    => "\xE2\x8F\xBA",
    'heavy_check_mark' => "\xE2\x9C\x94",
    'white_small_square' => "\xE2\x96\xAB",
    'black_small_square' => "\xE2\x96\xAA",
    'black_medium_small_square' => "\xE2\x97\xBC",
    'ballot_box_with_check' => "\xE2\x98\x91",
    'moneybag'         => "\xF0\x9F\x92\xB0",
    'sparkles'         => "\xE2\x9C\xA8",
    'watermelon'       => "\xF0\x9F\x8D\x89",
    'grapes'           => "\xF0\x9F\x8D\x87",
    'cherries'         => "\xF0\x9F\x8D\x92",
    'flame'            => "\xF0\x9F\x94\xA5",
    'boom'             => "\xF0\x9F\x92\xA5",
    'anger'            => "\xF0\x9F\x92\xA2",
    'red_circle'       => "\xF0\x9F\x94\xB4",
    'green_circle'     => "\xF0\x9F\x9F\xA2",
    'blue_circle'      => "\xF0\x9F\x94\xB5",
    'arrows_counterclockwise' => "\xF0\x9F\x94\x84",
    'yellow_circle'    => "\xF0\x9F\x9F\xA1",
    'orange_circle'    => "\xF0\x9F\x9F\xA0",
    'white_circle'     => "\xE2\x9A\xAA",
    'black_circle'     => "\xE2\x9A\xAB",
    'purple_circle'    => "\xF0\x9F\x9F\xA3",
    'arrow_right_hook' => "\xE2\x86\xAA",
    'leftwards_arrow_with_hook' => "\xE2\x86\xA9",
    'tada'             => "\xF0\x9F\x8E\x89",
    'double_vertical_bar' => "\xE2\x8F\xB8",
    'new'              => "\xF0\x9F\x86\x95",
    'ok'               => "\xF0\x9F\x86\x97",
    'up'               => "\xF0\x9F\x86\x99",
    'vs'               => "\xF0\x9F\x86\x9A",
    'zap'              => "\xE2\x9A\xA1",
    'small_blue_diamond'   => "\xF0\x9F\x94\xB9",
    'small_orange_diamond' => "\xF0\x9F\x94\xB8",
    'large_blue_diamond'   => "\xF0\x9F\x94\xB7",
    'large_orange_diamond' => "\xF0\x9F\x94\xB6",
    'small_red_triangle'   => "\xF0\x9F\x94\xBA",
    'small_red_triangle_down' => "\xF0\x9F\x94\xBB",
    'information_source'    => "\xE2\x84\xB9",
    'eight_spoked_asterisk' => "\xE2\x9C\xB3",
    'repeat'           => "\xF0\x9F\x94\x81",
    'keycap'           => "\xEF\xB8\x8F\xE2\x83\xA3",
    'keycap_asterisk'  => "*\xEF\xB8\x8F\xE2\x83\xA3",
    'stopwatch'        => "\xE2\x8F\xB1",
    # }}}
  ];
  static function construct(object $bot): ?self # {{{
  {
    # prepare
    $dir = $bot->dir;
    # load texts
    if (file_exists($fileJson = $dir->data.self::FILE_JSON[0]) &&
        !$bot->cfg->BotLog['debug'])
    {
      # precompiled
      if (($texts = $bot->file->getJSON($fileJson)) === null) {
        return null;
      }
    }
    else
    {
      # load defaults
      $texts = require $dir->inc.self::FILE_INC[0];
      # merge bot source
      if (file_exists($fileInc = $dir->inc.self::FILE_INC[0])) {
        $texts = array_merge($texts, (require $fileInc));
      }
      # refine and render emojis for each language
      foreach ($texts as &$a)
      {
        foreach ($a as &$b)
        {
          $b = BotText::refineTemplate($b);
          $b = $bot->tp->render($b, '{: :}', self::EMOJI);
        }
      }
      unset($a, $b);
      # store
      if (!$bot->file->setJSON($fileJson, $texts)) {
        return null;
      }
    }
    # load captions
    if (file_exists($fileJson = $dir->data.self::FILE_JSON[1]) &&
        !$bot->cfg->BotLog['debug'])
    {
      # precompiled
      if (($caps = $bot->file->getJSON($fileJson)) === null) {
        return null;
      }
    }
    else
    {
      # load defaults
      $caps = require $dir->inc.self::FILE_INC[1];
      # merge bot source
      if (file_exists($fileInc = $dir->inc.self::FILE_INC[1])) {
        $caps = array_merge($caps, (require $fileInc));
      }
      # render emojis
      foreach ($caps as &$a) {
        $a = $bot->tp->render($a, '{: :}', self::EMOJI);
      }
      unset($a);
      # store
      if (!$bot->file->setJSON($fileJson, $caps)) {
        return null;
      }
    }
    # construct
    return new self($bot, $texts, $caps);
  }
  # }}}
  function __construct(# {{{
    public object $bot,
    public array  &$texts,
    public array  &$caps
  ) {}
  # }}}
  # [texts] access {{{
  function offsetExists(mixed $k): bool {
    return true;
  }
  function offsetGet(mixed $k): mixed
  {
    if (($a = $this->bot->user?->lang) && isset($this->texts[$a][$k])) {
      return $this->texts[$a][$k];
    }
    return $this->texts['en'][$k] ?? '';
  }
  function offsetSet(mixed $k, mixed $v): void
  {}
  function offsetUnset(mixed $k): void
  {}
  # }}}
  static function refineTemplate(string $text): string # {{{
  {
    $t = trim($text);
    if (strpos($t, "\n") !== false) {
      $t = preg_replace('/\n\s+/', '', str_replace("\r", '', $t));
    }
    return $t;
  }
  # }}}
}
# }}}
class BotCommands implements \ArrayAccess # {{{
{
  const FILE_INC  = 'commands.inc';
  const FILE_JSON = 'commands.json';
  static function construct(object $bot): ?self # {{{
  {
    # prepare
    $log = $bot->log->new('cmd');
    $fileJson = $bot->dir->data.self::FILE_JSON;
    $fileInc  = $bot->dir->src.self::FILE_INC;
    # load commands
    if (file_exists($fileJson))
    {
      # precompiled
      if (!($tree = $bot->file->getJSON($fileJson)))
      {
        $log->error("failed to load: $fileJson");
        return null;
      }
    }
    else
    {
      # bot source
      if (!file_exists($fileInc))
      {
        $log->error("source not found: $fileInc");
        return null;
      }
      if (!($tree = include $fileInc) || !is_array($tree))
      {
        $log->error("incorrect source: $fileInc");
        return null;
      }
      # merge with data source
      if (file_exists($fileInc = $bot->dir->data.self::FILE_INC) &&
          is_array($a = include $fileInc))
      {
        $tree = array_merge($tree, $a);
      }
      # refine
      if (!self::refineTree($bot, $tree)) {
        return null;
      }
    }
    # construct items and map
    foreach ($tree as &$item)
    {
      $type = Bot::NS.$item['type'];
      $item = new $type($bot, $item, null);
    }
    # construct self
    return new self($bot, $log, $tree, self::createMap($tree));
  }
  # }}}
  static function refineTree(# {{{
    object  $bot,
    array   &$tree,
    ?array  &$parent = null
  ):bool
  {
    foreach ($tree as $name => &$skel)
    {
      # set base
      $skel['name'] = $name;
      $skel[$a = 'id'] = $id = $parent
        ? $parent[$a].$name
        : $name;
      $skel[$a = 'path'] = $parent
        ? $parent[$a].'/'.$name
        : '/'.$name;
      # determine item type (class)
      $skel[$a = 'type'] = isset($skel[$a])
        ? 'Bot'.ucfirst($skel[$a]).'Item'
        : 'BotImgItem';
      if (!class_exists($class = Bot::NS.$skel[$a], false))
      {
        $bot->log->error($id, "unknown class: $class");
        return false;
      }
      # determine item handler
      $skel['handler'] = function_exists($a = Bot::NS.'BotItem\\'.$id)
        ? $a : '';
      # determine input flag
      if (!isset($skel[$a = 'input'])) {
        $skel[$a] = method_exists($class, 'acceptInput');
      }
      # apply class specific
      if (method_exists($class, 'refine') && !$class::refine($skel))
      {
        $bot->log->error($id, "$class failed to refine");
        return false;
      }
      # refine captions
      # set entry
      if (!isset($skel[$a = 'caps'])) {
        $skel[$a] = [];
      }
      # set content
      foreach ($skel[$a] as &$b)
      {
        $b = BotText::refineTemplate($b);
        $b = $bot->tp->render($b, '{: :}', BotText::EMOJI);
      }
      unset($b);
      # refine texts
      # set primary language
      if (!isset($skel[$a = 'text'])) {
        $skel[$a] = ['en'=>[]];
      }
      elseif (!isset($skel[$a]['en'])) {
        $skel[$a] = ['en'=>$skel[$a]];
      }
      # render contents
      foreach ($skel[$a] as &$b)
      {
        foreach ($b as &$c)
        {
          $c = BotText::refineTemplate($c);
          $c = $bot->tp->render($c, '{: :}', BotText::EMOJI);
          $c = $bot->tp->render($c, '{! !}', $bot->text->caps);
        }
      }
      unset($b, $c);
      # set secondary languages
      $a = &$skel[$a];
      foreach (array_keys($bot->text->texts) as $b)
      {
        if (!isset($a[$b])) {
          $a[$b] = $a['en'];# copy all
        }
        elseif ($b !== 'en' && count($a[$b]) < count($a['en']))
        {
          # fill the gaps
          foreach ($a['en'] as $c => &$d)
          {
            if (!isset($a[$b][$c])) {
              $a[$b][$c] = $d;
            }
          }
          unset($d);
        }
      }
      unset($a);
      # recurse
      if (isset($skel[$a = 'items']) &&
          !self::refineTree($bot, $skel[$a], $skel))
      {
        return false;
      }
    }
    return true;
  }
  # }}}
  static function createMap(array &$tree): array # {{{
  {
    $map = [];
    foreach ($tree as $a)
    {
      $map[$a->id] = $a;
      if ($a->items)
      {
        foreach (self::createMap($a->items) as $b) {
          $map[$b->id] = $b;
        }
      }
    }
    return $map;
  }
  # }}}
  function __construct(# {{{
    public ?object  $bot,
    public ?object  $log,
    public array    $tree,
    public array    $map
  ) {}
  # }}}
  # [map] access {{{
  function offsetExists(mixed $id): bool {
    return isset($this->map[$id]);
  }
  function offsetGet(mixed $id): mixed
  {
    if (!isset($this->map[$id]))
    {
      $this->log->warn("not found: $id");
      return null;
    }
    return $this->map[$id];
  }
  function offsetSet(mixed $id, mixed $item): void
  {}
  function offsetUnset(mixed $id): void
  {}
  # }}}
}
# }}}
# process (console)
class BotMaster extends BotConfigAccess # {{{
{
  static function construct(object $bot): ?self # {{{
  {
    # determine starter command
    if (!file_exists($a = $bot->dir->home.'start.php'))
    {
      $bot->log->error("file not found: $a");
      return null;
    }
    # construct
    $proc = new self(
      $bot, $bot->log->new('proc'),
      '"'.PHP_BINARY.'" -f "'.$a.'" '
    );
    # start slaves
    foreach ($proc['slaves'] as $a)
    {
      if (!$proc->start("$a")) {
        return null;
      }
    }
    # done
    return $proc;
  }
  # }}}
  function __construct(# {{{
    public object $bot,
    public object $log,
    public string $cmd,
    public array  $map = []
  ) {}
  # }}}
  function destruct(): void # {{{
  {
    if ($this->map)
    {
      # store started slaves into master's configuration
      $this['slaves'] = array_keys($this->map);
      # terminate them
      foreach ($this->map as $id => $slave) {
        $slave->destruct();
      }
    }
  }
  # }}}
  function start(string $id): bool # {{{
  {
    if ($this->get($id))
    {
      $this->log->warn(__FUNCTION__."($id): is already started");
      return false;
    }
    if (!($slave = BotMasterSlave::construct($this, $id))) {
      return false;
    }
    $this->map[$id] = $slave;
    return true;
  }
  # }}}
  function stop(string $id): bool # {{{
  {
    if (!($slave = $this->get($id)))
    {
      $this->log->warn(__FUNCTION__."($id): is not started");
      return false;
    }
    $slave->destruct();
    unset($this->map[$id]);
    return true;
  }
  # }}}
  function get(string $id): ?object # {{{
  {
    if (!isset($this->map[$id])) {
      return null;
    }
    if (!($slave = $this->map[$id])->check())
    {
      $slave->destruct();
      unset($this->map[$id]);
      return null;
    }
    return $slave;
  }
  # }}}
  function check(): bool # {{{
  {
    foreach ($this->map as $id => $slave)
    {
      if (!$slave->check())
      {
        $slave->destruct();
        unset($this->map[$id]);
      }
    }
    return true;
  }
  # }}}
  function out(string $s): void # {{{
  {
    fwrite(STDOUT, $s);
  }
  # }}}
}
# }}}
class BotMasterSlave # {{{
{
  static function construct(object $master, string $id): ?self # {{{
  {
    # prepare
    static $DESC = [
      ['pipe','r'],   # 0:input
      ['pipe','w']    # 1:output
    ];
    static $OPTS = [# windows OS only
      'suppress_errors' => false,
      'bypass_shell'    => true,
      'blocking_pipes'  => true,
      'create_process_group' => true,# allow child to handle CTRL events?
      'create_new_console'   => false,
    ];
    $bot  = $master->bot;
    $log  = $master->log->new($id);
    $cmd  = $master->cmd.$id;
    $pipe = null;
    $home = $bot->dir->home;
    $in   = $bot->dir->data."$id.out";
    $out  = $bot->dir->data."$id.in";
    # operate
    try
    {
      # execute
      if (($proc = proc_open($cmd, $DESC, $pipe, $home, null, $OPTS)) === false ||
          !is_resource($proc))
      {
        throw BotError::text("proc_open($cmd) failed");
      }
      # initiate sync protocol
      # create output lockfile
      if (!touch($out)) {
        throw BotError::text("touch($out) failed");
      }
      # pass paths to the process input
      if (fwrite($pipe[0], "$out,$in") === false) {
        throw BotError::text('fwrite() failed');
      }
      # wait output lock removed and input created by the process
      while (file_exists($out) && !file_exists($in))
      {
        usleep(Bot::PROCESS_TICK);
        if (!($a = proc_get_status($proc))['running']) {
          throw BotError::text('process escaped, exitcode='.$a['exitcode']);
        }
      }
      # report success
      $log->info('start');
    }
    catch (\Throwable $e)
    {
      # report failure
      $log->exception($e);
      # remove output lockfile
      file_exists($out) && @unlink($out);
      # close pipes
      if ($pipe)
      {
        is_resource($pipe[0]) && fclose($pipe[0]);
        if (is_resource($pipe[1]))
        {
          # read any output left
          ($a = fread($pipe[1], Bot::PROCESS_CHUNK)) && fwrite(STDOUT, $a);
          fclose($pipe[1]);
        }
      }
      return null;
    }
    # construct
    return new self($proc, $log, [$in, $out], $pipe);
  }
  # }}}
  function __construct(# {{{
    public $proc,
    public object $log,
    public array  $lock,
    public array  $pipe
  ) {}
  # }}}
  function destruct(): void # {{{
  {
    # prepare
    $pipe = $this->pipe;
    # check running
    if ($this->check())
    {
      # process is still running,
      # send termination command
      $this->log->info('stop');
      fwrite($pipe[0], 'stop');
      touch($out = $this->lock[1]);
      # wait
      while (file_exists($out)) {
        usleep(Bot::PROCESS_TICK);
      }
      while (proc_get_status($this->proc)['running']) {
        usleep(Bot::PROCESS_TICK);
      }
      # read final output
      $this->readPipe(true);
    }
    # cleanup
    fclose($pipe[0]);
    fclose($pipe[1]);
  }
  # }}}
  function check(): bool # {{{
  {
    $this->readPipe();
    return $this->checkProc();
  }
  # }}}
  function readPipe(bool $force = false): bool # {{{
  {
    try
    {
      if ((file_exists($this->lock[0]) && unlink($this->lock[0])) ||
          $force)
      {
        fwrite(STDOUT, fread($this->pipe[1], Bot::PROCESS_CHUNK));
      }
    }
    catch (\Throwable $e)
    {
      $this->log->exception($e);
      return false;
    }
    return true;
  }
  # }}}
  function checkProc(): bool # {{{
  {
    if (!($a = proc_get_status($this->proc))['running'])
    {
      $this->log->info('process escaped, exitcode='.$a['exitcode']);
      return false;
    }
    return true;
  }
  # }}}
}
# }}}
class BotSlave # {{{
{
  static function construct(object $bot): ?self # {{{
  {
    # prepare
    $log = $bot->log->new('proc');
    # sync with the master
    try
    {
      # do a blocking read of master input
      stream_set_blocking(STDIN, true);
      if (($a = fread(STDIN, Bot::PROCESS_CHUNK)) === false) {
        throw BotError::text('fread(STDIN) failed');
      }
      # parse and check it
      if (!($lock = explode(',', $a)) || count($lock) !== 2) {
        throw BotError::text("incorrect master input: $a");
      }
      # check and remove master lock
      if (!file_exists($lock[0]) || !unlink($lock[0])) {
        throw BotError::text('failed to remove: '.$lock[0]);
      }
      # create slave lock
      if (!touch($lock[1]) || !file_exists($lock[1])) {
        throw BotError::text('failed to touch: '.$lock[1]);
      }
      # wait unlocked
      for ($a = 0, $b = 10; $a < $b && file_exists($lock[1]); ++$a) {
        usleep(Bot::PROCESS_TICK);
      }
      if ($a === $b)
      {
        unlink($lock[1]);
        throw BotError::text('master timed out');
      }
    }
    catch (\Throwable $e)
    {
      $log->exception($e);
      return null;
    }
    # complete
    return new self($log, $lock);
  }
  # }}}
  function __construct(# {{{
    public object $log,
    public array  $lock
  ) {}
  # }}}
  function check(): bool # {{{
  {
    # check master command arrived
    if (!file_exists($in = $this->lock[0])) {
      return true;
    }
    # read input
    $a = fread(STDIN, 100);
    @unlink($in);
    # operate
    switch ($a) {
    case 'stop':
      return false;
    }
    return true;
  }
  # }}}
  function out(string $s): void # {{{
  {
    fwrite(STDOUT, $s);
    touch($this->lock[1]);
  }
  # }}}
  function destruct(): void # {{{
  {}
  # }}}
}
# }}}
# request (response)
abstract class BotRequest extends BotConfigAccess # {{{
{
  const PARSE_FIRST = true;
  final function __construct(
    public ?object  $bot,
    public ?object  $data,
    public ?object  $log  = null,
    public ?object  $item = null,
    public string   $func = '',
    public string   $args = ''
  ) {}
  final function response(): bool
  {
    return $this->finit($this->init($this->bot->user));
  }
  final function init(object $user): bool
  {
    $this->log = $user->log->newObject($this);
    return (static::PARSE_FIRST
            ? ($this->parse() && $user->init())
            : ($user->init() && $this->parse())) &&
           $this->reply($this->attach($user));
  }
  final function attach(object $user): ?object
  {
    return
      (($item = $this->item) === null ||
       ($this->item = $item->attach($user, $this->func, $this->args)))
      ? $user
      : null;
  }
  final function finit(bool $ok): bool
  {
    $this->item?->detach($ok);
    $this->bot = $this->data =
    $this->log = $this->item = null;
    return $ok;
  }
  abstract function parse(): bool;
  abstract function reply(?object $user): bool;
}
# }}}
class BotRequestInput extends BotRequest # {{{
{
  const PARSE_FIRST = false;
  function parse(): bool # {{{
  {
    # prepare
    $bot  = $this->bot;
    $user = $bot->user;
    $msg  = $this->data;
    # determine recieving item
    if (isset($msg->reply_to_message) &&
        ($a = $msg->reply_to_message)->from->is_bot &&
        $a->from->id === $bot->id)
    {
      # replied item
      $item = $user->cfg->queueItem($a->message_id);
    }
    else
    {
      # active item or global handler
      $item = $user->cfg->queueFirstItem() ??
              $bot->cmd->map['input'] ?? null;
    }
    # check
    if (!$item || !$item->skel['input'])
    {
      # wipe in private chat or when item was found
      $this['wipeInput'] && (!$user->isGroup || $item) &&
      $bot->api->deleteMessage($msg);
      # not replied
      return false;
    }
    # complete
    $item->input = $msg;
    $this->item  = $item;
    $this->func  = 'input';
    return true;
  }
  # }}}
  function reply(?object $user): bool # {{{
  {
    # wipe when attached or in private chat
    $this['wipeInput'] &&
    ($user || !$this->bot->user->isGroup) &&
    $this->bot->api->deleteMessage($this->data);
    # complete
    return $user
      ? $user->update($this->item)
      : false;
  }
  # }}}
}
# }}}
class BotRequestCommand extends BotRequest # {{{
{
  const MAX_LENGTH = 200;
  #const SYNTAX_EXP = '|^\/(([a-z][a-z0-9]+)([:/-]([a-z][a-z0-9]+)){0,8})( ([^@]{1,})){0,1}(@[a-z_]+bot){0,1}$|i';
  const SYNTAX_EXP = '|^\/([a-z][a-z0-9:/-]+){1}(!([a-z]+)){0,1}( ([^@]+)){0,1}(@([a-z_]+bot)){0,1}$|i';
  const GLOBAL_LST = [
    # {{{
    'stop'    => 1,
    'restart' => 1,
    'reset'   => 1,
    # }}}
  ];
  function parse(): bool # {{{
  {
    # prepare
    $msg  = $this->data;
    $bot  = $this->bot;
    $user = $bot->user;
    # parse command
    # syntax: /<item>[!<func>][ <args>][@<botname>]
    if (($a = strlen($msg->text)) < 2 || $a > self::MAX_LENGTH ||
        !preg_match_all(self::SYNTAX_EXP, $msg->text, $a))
    {
      # report
      $this->log->warnInput(substr($msg->text, 0, 200));
      # wipe incorrect
      $this['wipeInput'] && !$user->isGroup &&
      $bot->api->deleteMessage($msg);
      return false;
    }
    # extract
    $item = strtolower($a[1][0]);
    $func = $a[3][0];
    $args = $a[5][0];
    $name = $a[7][0];
    # check bot name specified in group and
    # addressed to this bot
    if ($user->isGroup && $name && $name !== $bot->cfg->name) {
      return false;# ignore
    }
    # check deep linking (tg://<BOT_NAME>?start=<item>)
    if (!$user->isGroup && $item === 'start' && !$func && $args)
    {
      $item = strtolower($args);
      $args = '';
    }
    # check global command
    if (isset(self::GLOBAL_LST[$item]))
    {
      # attach
      $this->func = $item;
      $this->args = $args;
      $this->log->infoInput($msg->text);
      return true;
    }
    # remove identifier separators [:],[-],[/]
    if (strpos($item, ':')) {
      $item = str_replace(':', '', $item);
    }
    elseif (strpos($item, '-')) {
      $item = str_replace('-', '', $item);
    }
    elseif (strpos($item, '/')) {
      $item = str_replace('/', '', $item);
    }
    # check item exists
    if (!isset($bot->cmd[$item]))
    {
      # report and wipe incorrect
      $this->log->warnInput($msg->text);
      $this['wipeInput'] && !$user->isGroup && $bot->api->deleteMessage($msg);
      return false;
    }
    # report valid
    $this->log->infoInput($msg->text);
    # attach and complete
    $this->item = $bot->cmd[$item];
    $this->func = $func;
    $this->args = $args;
    return true;
  }
  # }}}
  function reply(?object $user): bool # {{{
  {
    # prepare
    $bot = $this->bot;
    # wipe when parsed/attached or in private chat
    $this['wipeInput'] && ($user || !$bot->user->isGroup) &&
    $bot->api->deleteMessage($this->data);
    # complete
    return $user
      ? ($this->item
        ? $user->create($this->item)
        : $this->replyGlobal())
      : false;
  }
  # }}}
  function replyGlobal(): bool # {{{
  {
    return false;
    /***
    switch ($item) {
    case 'restart':
      #$user->isAdmin && ($res = -1);
      return true;
    case 'stop':
      #$user->isAdmin && ($res =  1);
      return true;
    case 'reset':
      return true;
      # {{{
      # check allowed
      if (!$user->isAdmin)
      {
        $this->log("access denied: $text");
        return 1;
      }
      # check replied
      if (!isset($this->msg->reply_to_message))
      {
        $this->log("$text: replied not found");
        return 1;
      }
      # get message and item ids
      $a = $this->msg->reply_to_message->message_id;
      if (!($b = $conf->getMessageItemId($a)))
      {
        $this->log("$text: message $a is not rooted");
        return 1;
      }
      $this->log("$text: $a => $b");
      # get and remove item
      ($item = $this->itemGet($b)) && $this->itemDetach($item, true);
      # compose navigation command
      $text = '/'.$b;
      # }}}
    }
    /***/
  }
  # }}}
}
# }}}
class BotRequestCallback extends BotRequest # {{{
{
  function parse(): bool # {{{
  {
    # get and check CallbackQuery->data
    if (!($x = $this->data->data) || $x[0] === '!')
    {
      $this->replyNop();
      return false;
    }
    if ($x[0] !== '/')
    {
      $this->log->warnInput($x);
      $this['replyBad'] && $this->replyNop();
      return false;
    }
    # syntax: /<item>[!<func>][ <args>]
    # parse callback text
    if ($i = strpos($x, ' '))
    {
      $this->args = substr($x, $i + 1);
      $x = substr($x, 0, $i);
    }
    if ($i = strpos($x, '!'))
    {
      $this->func = substr($x, $i + 1);
      $x = substr($x, 0, $i);
    }
    $x = substr($x, 1);
    # check item exists
    if (!isset($this->bot->cmd[$x]))
    {
      $this->log->warnInput($this->data->data);
      $this['replyUnknown'] && $this->replyUnknown($x);
      return false;
    }
    # complete
    $this->log->infoInput($this->data->data);
    $this->item = $this->bot->cmd[$x];
    $this['replyFast'] && $this->replyNop();
    return true;
  }
  # }}}
  function reply(?object $user): bool # {{{
  {
    # operate
    $ok = $user
      ? $user->update($this->item)
      : false;
    # check not replied
    if (!$this['replyFast'])
    {
      if ($ok) {
        $this->replyNop();
      }
      else
      {
        # TODO: report failure to the user
      }
    }
    return $ok;
  }
  # }}}
  function replyNop(): void # {{{
  {
    $this->bot->api->send('answerCallbackQuery', [
      'callback_query_id' => $this->data->id
    ]);
  }
  # }}}
  function replyUnknown(string $id): void # {{{
  {
    $text = $this->bot->text;
    $text = $text['op-fail'].': *'.$id.'* '.$text['not-found'];
    $this->bot->api->send('answerCallbackQuery', [
      'callback_query_id' => $this->data->id,
      'text' => $text
    ]);
  }
  # }}}
  function complete(bool $ok): bool # {{{
  {
  }
  # }}}
}
# }}}
class BotRequestGame extends BotRequest # {{{
{
  function parse(): bool # {{{
  {
    return false;
  }
  # }}}
  function reply(?object $user): bool # {{{
  {
    return false;
    /***
    if (isset($q->game_short_name) &&
        ($a = $q->game_short_name))
    {
      $this->log('game callback: '.$a);
      if ($a = $this->getGameUrl($a, $msg))
      {
        return [
          'url' => $a,
          'cache_time' => 0,
        ];
      }
      else
      {
        return [
          'text' => $this->messages[$lang][0],
          'show_alert' => true,
        ];
      }
    }
    /***/
  }
  # }}}
}
# }}}
class BotRequestInline extends BotRequest # {{{
{
  function parse(): bool # {{{
  {
    return false;
  }
  # }}}
  function reply(?object $user): bool # {{{
  {
    return false;
    /***
    $out = (string)rand();
    $results[] = [
      "type"  => "article",
      "id"    => $out,
      "title" => $out,
      "input_message_content" => [
        "message_text"             => $out,
        "disable_web_page_preview" => true
      ],
    ];
    $client->answerInlineQuery($u->inline_query->id, $results, 1, false);
    unset($results);
    /***/
  }
  # }}}
}
# }}}
class BotRequestChat extends BotRequest # {{{
{
  function parse(): bool # {{{
  {
    # prepare
    $data = $this->data->new_chat_member;
    # check self
    if (($user = $data->user)->id === $this->bot->id)
    {
      $this->log->infoInput($data->status);
      return true;
    }
    # another user changed (in group),
    # determine its name
    $this->args = $user->first_name.(isset($user->username)
      ? '@'.$user->username.'·'.$user->id
      : '·'.$user->id);
    # report details
    $this->log->new($data->status)->infoInput($this->args);
    return true;
  }
  # }}}
  function reply(?object $user): bool # {{{
  {
    # prepare
    $data = $this->data;
    $chat = $user->chat;
    # update another user
    if ($this->args) {
      return $chat->update($data);
    }
    # update self
    switch ($data->new_chat_member->status) {
    case 'kicked':
      return $chat->kicked($data);
    }
    # not handled
    $this->log->dump($data);
    return false;
  }
  # }}}
}
# }}}
# user (subject)
class BotUser # {{{
{
  static function construct(# {{{
    object  $bot,
    object  $from,
    object  $chat
  ):?self
  {
    # determine identifier and name
    $id    = $from->id;
    $name  = $from->first_name;
    $uname = isset($from->username)
      ? $from->username
      : '';
    $fname = $uname
      ? $name.'@'.$uname.'·'.$id
      : $name.'·'.$id;
    # determine group flag
    if (($chat->type === 'group' ||
         $chat->type === 'supergroup'))
    {
      $isGroup = true;
      $gname = (
        (isset($chat->title) ? $chat->title : '').
        (isset($chat->username) ? '@'.$chat->username : '')
      );
    }
    else
    {
      $isGroup = false;
      $gname = '';
    }
    # create logger
    $log = $isGroup
      ? $bot->log->new($gname)->new($fname)
      : $bot->log->new($fname);
    # determine admin privilege flag and
    # check masterbot access
    if (!($isAdmin = in_array($id, $bot->cfg->admins)) &&
        $bot->isMaster)
    {
      $log->warn('access denied');
      return null;
    }
    # determine storage directory
    $dir = $isGroup
      ? $bot->dir->grp.$chat->id.DIRECTORY_SEPARATOR
      : $bot->dir->usr.$id.DIRECTORY_SEPARATOR;
    if (!file_exists($dir) && !@mkdir($dir))
    {
      $log->error("failed to create: $dir");
      return null;
    }
    # determine language
    if (!($lang = $bot->cfg->lang) &&
        (!isset($from->language_code) ||
         !($lang = $from->language_code) ||
         !isset($bot->text->texts[$lang])))
    {
      $lang = 'en';
    }
    # construct
    return new self(
      $bot, $id, $name, $uname, $fname, $log,
      $isAdmin, $isGroup, $dir, $lang, $chat
    );
  }
  # }}}
  function __construct(# {{{
    public ?object  $bot,
    public int      $id,
    public string   $name,
    public string   $username,
    public string   $fullname,
    public ?object  $log,
    public bool     $isAdmin,
    public bool     $isGroup,
    public string   $dir,
    public string   $lang,
    public ?object  $chat,
    public ?object  $cfg = null
  )
  {
    $this->chat = new BotUserChat($this, $chat);
    $this->cfg  = new BotUserConfig($this);
  }
  # }}}
  function init(): bool # {{{
  {
    try
    {
      if (!$this->cfg->init() ||
          !$this->chat->init())
      {
        throw BotError::skip();
      }
    }
    catch (\Throwable $e)
    {
      $this->log->exception($e);
      return $this->finit(false);
    }
    return true;
  }
  # }}}
  function finit(bool $ok): bool # {{{
  {
    $this->cfg->finit($ok);
    return $ok;
  }
  # }}}
  function destruct(bool $ok = false): void # {{{
  {
    if ($this->bot)
    {
      $this->finit($ok);
      $this->bot  = $this->log =
      $this->chat = $this->cfg = null;
    }
  }
  # }}}
###
  function create(object $item): bool # {{{
  {
    # check item has no messages rendered
    if (!$item->msgs) {
      return $this->delete($item);
    }
    try
    {
      # create and send new messages
      if (!($new = BotUserMessages::send($this, $item)))
      {
        $item->log->warn(__FUNCTION__.": failed to send new messages");
        throw BotError::skip();
      }
      # remove previous messages of the same root
      if (($old = $this->cfg->queuePop($item)) && !$old->delete()) {
        $item->log->warn(__FUNCTION__.": failed to delete previous messages");
      }
      # store new
      $this->cfg->queuePush($new);
    }
    catch (\Throwable $e)
    {
      $item->log->exception($e);
      return false;
    }
    $item->log->info($old ? 'refreshed' : 'created');
    return true;
  }
  # }}}
  function update(object $item): bool # {{{
  {
    # check item has no messages rendered
    if (!$item->msgs) {
      return $this->delete($item);
    }
    try
    {
      # get messages of the same root
      if (!($old = $this->cfg->queueGet($item)))
      {
        # create and send new
        if (!($new = BotUserMessages::send($this, $item)))
        {
          $item->log->warn(__FUNCTION__.": failed to send new messages");
          throw BotError::skip();
        }
        # complete
        $this->cfg->queuePush($new);
        $item->log->info('created');
      }
      elseif ($old->compatible($item))
      {
        # edit compatible
        if (($i = $old->edit($item)) === 0) {
          throw BotError::skip();
        }
        # complete
        $this->cfg->changed = true;
        $item->log->info("updated($i)");
      }
      else
      {
        # not compatible, create and send new
        if (!($new = BotUserMessages::send($this, $item)))
        {
          $item->log->warn(__FUNCTION__.": failed to send new messages");
          throw BotError::skip();
        }
        # remove previous messages
        if (!$this->cfg->queueRemove($item) || !$old->delete()) {
          $item->log->warn(__FUNCTION__.": failed to delete previous messages");
        }
        # complete
        $this->cfg->queuePush($new);
        $item->log->info('refreshed');
      }
    }
    catch (\Throwable $e)
    {
      $item->log->exception($e);
      return false;
    }
    return true;
  }
  # }}}
  function delete(object $item): bool # {{{
  {
    try
    {
      # eject and delete messages of the same root
      if (!($msgs = $this->cfg->queuePop($item)))
      {
        $item->log->warn(__FUNCTION__.": no messages found");
        throw BotError::skip();
      }
      if (!$msgs->delete())
      {
        $item->log->warn(__FUNCTION__.": failed to delete");
        throw BotError::skip();
      }
    }
    catch (\Throwable $e)
    {
      $item->log->exception($e);
      return false;
    }
    # complete
    $item->log->info('deleted');
    return true;
  }
  # }}}
}
# }}}
class BotUserConfig # {{{
{
  const FILE_JSON = 'config.json';
  function __construct(# {{{
    public ?object  $user,
    public string   $lock    = '',
    public array    $queue   = [],# BotUserMessages
    public array    $items   = [],# item => configuration
    public bool     $changed = false
  ) {}
  # }}}
  function init(): bool # {{{
  {
    # prepare
    $user  = $this->user;
    $bot   = $user->bot;
    $file  = $user->dir.self::FILE_JSON;
    $force = $user->isGroup
      ? strval($user->id)
      : '';
    # aquire a lock
    if (!($this->lock = BotFile::lock($file, $force)))
    {
      $user->log->error("failed to lock: $file");
      return false;
    }
    # load configuration
    if ($data = $bot->file->getJSON($file))
    {
      # initialize messages queue
      foreach ($data[0] as &$v)
      {
        if ($k = BotUserMessages::load($user, $v)) {
          $this->queue[] = $k;
        }
        else {
          $this->changed = true;
        }
      }
      # initialize item configurations
      foreach ($data[1] as $k => &$v)
      {
        if (isset($bot->cmd[$k])) {
          $this->items[$k] = $v;
        }
        else {
          $this->changed = true;
        }
      }
    }
    # done
    return true;
  }
  # }}}
  function finit(bool $ok): bool # {{{
  {
    if ($this->lock)
    {
      # save and unlock
      $user = $this->user;
      $file = $user->dir.self::FILE_JSON;
      if ($ok && $this->changed)
      {
        $user->bot->file->setJSON($file, [
          $this->queue,
          $this->items
        ]);
      }
      $this->lock = BotFile::unlock($file);
      # cleanup
      $this->queue = [];
      $this->items = [];
      $this->changed = false;
    }
    return $ok;
  }
  # }}}
###
  function queueItem(int $messageId): ?object # {{{
  {
    foreach ($this->queue as $m)
    {
      foreach ($m->list as $msg)
      {
        if ($msg->id === $messageId) {
          return $m->item;
        }
      }
    }
    return null;
  }
  # }}}
  function queueSearch(object $item, bool $strict = false): int # {{{
  {
    # search by item and item's root
    foreach ($this->queue as $i => $m)
    {
      if ($m->item === $item || (!$strict && $m->item->root === $item->root)) {
        return $i;
      }
    }
    # not found
    return -1;
  }
  # }}}
  function queueGet(object $item): ?object # {{{
  {
    return ~($i = $this->queueSearch($item))
      ? $this->queue[$i]
      : null;
  }
  # }}}
  function queueRemove(object $item): bool # {{{
  {
    if (~($i = $this->queueSearch($item)))
    {
      array_splice($this->queue, $i, 1);
      return $this->changed = true;
    }
    return false;
  }
  # }}}
  function queuePop(object $item): ?object # {{{
  {
    if (~($i = $this->queueSearch($item)))
    {
      $m = $this->queue[$i];
      array_splice($this->queue, $i, 1);
      $this->changed = true;
      return $m;
    }
    return null;
  }
  # }}}
  function queuePush(object $msgs): void # {{{
  {
    array_unshift($this->queue, $msgs);
    $this->changed = true;
  }
  # }}}
  function queueFirstItem(): ?object # {{{
  {
    foreach ($this->queue as $m)
    {
      if ($this->user->id === $m->owner) {
        return $m->item;
      }
    }
    return null;
  }
  # }}}
}
# }}}
class BotUserChat # {{{
{
  const FILE_CHAT = 'chat.json';
  const FILE_KICK = 'kicked.json';
  function __construct(# {{{
    public ?object  $user,
    public ?object  $data,
    public int      $id = 0
  ) {}
  # }}}
  function init(): bool # {{{
  {
    # prepare
    $user = $this->user;
    $bot  = $user->bot;
    $data = $this->data;
    # check chatfile
    if (file_exists($file = $user->dir.self::FILE_CHAT))
    {
      # check no data specified or group chat
      if (!$data || $user->isGroup)
      {
        # load stored
        if (($data = $user->bot->file->getJSON($file)) === null) {
          return false;
        }
        # cast array
        $data = (object)$data;
      }
    }
    elseif (!$data || !isset($data->id))
    {
      $user->log->error('no chat data specified');
      return false;
    }
    else
    {
      # initialize,
      # request chat details
      $data = $user->bot->api->send('getChat', [
        'chat_id' => $data->id
      ]);
      # check
      if (!$data) {
        return false;
      }
      # store
      if (!$user->bot->file->setJSON($file, $data)) {
        return false;
      }
      # report
      $user->log->info('chat created');
    }
    # set props
    $this->data = $data;
    $this->id   = $data->id;
    # done
    return true;
  }
  # }}}
  function update(object $u): bool # {{{
  {
    return false;
  }
  # }}}
  function kicked(object $u): bool # {{{
  {
    # prepare
    $user = $this->user;
    # remove chatfile
    if (!@unlink($file = $user->dir.self::FILE_CHAT)) {
      $user->log->error("unlink($file) failed");
    }
    # store details
    $user->bot->file->setJSON($user->dir.self::FILE_KICK, $u);
    $user->log->info('chat removed');
    return true;
  }
  # }}}
}
# }}}
class BotUserMessages implements \JsonSerializable # {{{
{
  static function load(object $user, array &$data): ?self # {{{
  {
    # get command item
    if (!($item = $user->bot->cmd[$data[0]])) {
      return null;
    }
    # construct messages
    foreach ($data[2] as &$msg) {
      $msg = new $msg[0]($user->bot, $msg[1], $msg[2]);
    }
    # complete
    return new self(
      $user, $item, $data[1], $data[2], $data[3]
    );
  }
  # }}}
  static function send(object $user, object $item): ?self # {{{
  {
    # prepare
    $time = time();
    # send item messages
    foreach ($item->msgs as $m)
    {
      if (!$m->send()) {
        return null;
      }
    }
    # construct
    return new self(
      $user, $item, $time, $item->msgs, $user->id
    );
  }
  # }}}
  function __construct(# {{{
    public object  $user,
    public object  $item,
    public int     $time,
    public array   $list,
    public int     $owner
  ) {}
  # }}}
  function jsonSerialize(): array # {{{
  {
    return [
      $this->item->id, $this->time,
      $this->list, $this->user->id
    ];
  }
  # }}}
  function compatible(object $item): bool # {{{
  {
    # check current messages are fresh enough
    if ((time() - $this->time) > 0.8 * Bot::MESSAGE_LIFETIME) {
      return false;
    }
    # get message counts
    $a = count($this->list);
    $b = count($item->msgs);
    # new message block must be smaller or
    # equal to the current, check otherwise
    if ($a < $b) {
      return false;
    }
    # check message types
    for ($i = 0; $i < $b; ++$i)
    {
      if ($this->list[$i]::class !== $item->msgs[$i]::class) {
        return false;
      }
    }
    return true;
  }
  # }}}
  function edit(object $item): int # {{{
  {
    # prepare
    $a = count($this->list);
    $b = count($item->msgs);
    $c = 0;
    # delete first messages which type doesnt match
    if ($a > $b)
    {
      for ($i = 0; $i < $a; ++$i)
      {
        if ($this->list[$i]::class === $item->msgs[$i]::class) {
          break;
        }
        $this->list[$i]->delete() && $c++;
      }
      if ($i)
      {
        $a = $a - $i;
        array_splice($this->list, 0, $i);
      }
    }
    # edit messages which hashes differ
    for ($i = 0; $i < $b; ++$i)
    {
      if ($this->list[$i]->hash === $item->msgs[$i]->hash) {
        continue;
      }
      $this->list[$i]->edit($item->msgs[$i]) && $c++;
    }
    # delete any extra messages left
    if ($a > $b)
    {
      for ($i = $b; $i < $a; ++$i) {
        $this->list[$i]->delete() && $c++;
      }
      array_splice($this->list, $b);
    }
    # replace item
    $this->item = $item;
    # complete
    return $c;
  }
  # }}}
  function delete(): bool # {{{
  {
    # telegram allows to delete only "fresh" messages, so,
    # check item's creation timestamp
    if (($a = time() - $this->time) >= 0 &&
        ($a < Bot::MESSAGE_LIFETIME))
    {
      # iterate and delete messages
      foreach ($this->list as $a => $msg)
      {
        if (!$msg->delete() && $a === 0) {
          return false;# interrupt at first failed attempt
        }
      }
    }
    else
    {
      # iterate and zap messages
      foreach ($this->list as $msg) {
        $msg->zap();
      }
    }
    # done
    return true;
  }
  # }}}
}
# }}}
# message (content)
abstract class BotMessage extends BotConfigAccess implements \JsonSerializable # {{{
{
  static function construct(object $bot, array &$data): self # {{{
  {
    $m = new static($bot, 0, '', $data);
    $m->init();
    return $m;
  }
  # }}}
  function __construct(# {{{
    public object   $bot,
    public int      $id,
    public string   $hash,
    public ?array   &$data = null
  ) {}
  # }}}
  function jsonSerialize(): array # {{{
  {
    return [$this::class, $this->id, $this->hash];
  }
  # }}}
  function delete(): bool # {{{
  {
    # common operation
    return $this->bot->api->send('deleteMessage', [
      'chat_id'    => $this->bot->user->chat->id,
      'message_id' => $this->id,
    ]);
  }
  # }}}
  abstract function init(): void;
  abstract function send(): bool;
  abstract function edit(object $msg): bool;
  abstract function zap(): bool;
}
# }}}
class BotImgMessage extends BotMessage # {{{
{
  function init(): void # {{{
  {
    $this->hash = hash('md4',
      ($this->data['file'] ?: $this->data['id']).
      $this->data['text'].$this->data['markup']
    );
  }
  # }}}
  function send(): bool # {{{
  {
    # prepare
    $bot = $this->bot;
    $res = [
      'chat_id' => $bot->user->chat->id,
      'photo'   => $this->data['image'],
      'disable_notification' => true,
    ];
    if ($a = $this->data['text'])
    {
      $res['caption']    = $a;
      $res['parse_mode'] = 'HTML';
    }
    if ($a = $this->data['markup']) {
      $res['reply_markup'] = $a;
    }
    # send
    if (!($res = $bot->api->send('sendPhoto', $res))) {
      return false;
    }
    # store file identifier
    if (is_object($this->data['image']))
    {
      $a = end($res->photo);# last element is the original
      $bot->file->setId($this->data['file'], $a->file_id);
    }
    # set message identifier and complete
    $this->id = $res->message_id;
    return true;
  }
  # }}}
  function edit(object $msg): bool # {{{
  {
    # prepare
    $bot  = $this->bot;
    $file = is_object($img = $msg->data['image'])
      ? 'attach://'.$img->postname
      : '';
    $res = [# InputMediaPhoto
      'type'       => 'photo',
      'media'      => ($file ?: $img),
      'caption'    => $msg->data['text'],
      'parse_mode' => 'HTML',
    ];
    $res = [
      'chat_id'      => $bot->user->chat->id,
      'message_id'   => $this->id,
      'media'        => json_encode($res),
      'reply_markup' => $msg->data['markup'],
    ];
    # operate
    $res = $file
      ? $bot->api->send('editMessageMedia', $res, $img)
      : $bot->api->send('editMessageMedia', $res);
    # check result
    if (!$res || $res === true) {
      return false;
    }
    # store file identifier
    if ($file)
    {
      $a = end($res->photo);# last element is the original
      $bot->file->setId($msg->data['file'], $a->file_id);
    }
    # update hash and complete
    $this->hash = $msg->hash;
    return true;
  }
  # }}}
  function zap(): bool # {{{
  {
    # prepare
    $bot  = $this->bot;
    $file = '';
    # get placeholder image
    if (!($img = $bot->file->getId(__METHOD__)))
    {
      $cfg = $this['placeholder'];
      $img = self::getPlaceholderFile($cfg['size'], $cfg['color']);
      if ($img instanceof BotError)
      {
        $bot->user->log->exception($img);
        return false;
      }
      $file = 'attach://'.$img->postname;
    }
    # compose parameters
    $res = [
      'type'  => 'photo',
      'media' => ($file ?: $img),
    ];
    $res = [
      'chat_id'    => $bot->user->chat->id,
      'message_id' => $this->id,
      'media'      => json_encode($res),
    ];
    # operate
    $res = $file
      ? $bot->api->send('editMessageMedia', $res, $img)
      : $bot->api->send('editMessageMedia', $res);
    # check result
    if (!$res || $res === true) {
      return false;
    }
    # store file identifier
    if ($file)
    {
      $a = end($res->photo);# last element is the original
      $bot->file->setId(__METHOD__, $a->file_id);
    }
    # complete
    return true;
  }
  # }}}
  # helpers
  static function imgNew(array &$size, array &$color): object # {{{
  {
    $img = null;
    try
    {
      # create image
      if (($img = imagecreatetruecolor($size[0], $size[1])) === false) {
        throw BotError::text('imagecreatetruecolor() failed');
      }
      # allocate color
      if (($c = imagecolorallocate($img, $color[0], $color[1], $color[2])) === false) {
        throw BotError::text('imagecolorallocate() failed');
      }
      # fill the background
      if (!imagefill($img, 0, 0, $c)) {
        throw BotError::text('imagefill() failed');
      }
    }
    catch (\Throwable $e)
    {
      $img && imagedestroy($img);
      throw $e;
    }
    return $img;
  }
  # }}}
  static function imgDrawHeader(# {{{
    object  $img,
    string  $text,
    array   &$color,
    string  $font,
    int     $minSize,
    int     $maxSize,
    array   &$rect
  ):void
  {
    # header should fit into given rect, so
    # determine optimal font size (in points not pixels? -seems not)
    while ($maxSize > $minSize)
    {
      # determine bounding box
      if (!($a = imageftbbox($maxSize, 0, $font, $text))) {
        throw BotError::text('imageftbbox() failed');
      }
      # check it fits width and height
      if ($a[2] - $a[0] <= $rect[1] &&
          $a[1] - $a[7] <= $rect[3])
      {
        break;
      }
      # reduce and retry
      $maxSize -= 2;
    }
    # determine start coordinates (center align)
    $x = $a[2] - $a[0];
    $x = ($rect[0] + ($rect[1] - $x) / 2) | 0;
    $y = $a[1] - $a[7];
    $y = ($rect[2] + ($rect[3] - $y) / 2 + $y) | 0;
    # allocate color
    if (($c = imagecolorallocate($img, $color[0], $color[1], $color[2])) === false) {
      throw BotError::text('imagecolorallocate() failed');
    }
    # draw
    if (!imagefttext($img, $maxSize, 0, $x, $y, $c, $font, $text)) {
      throw BotError::text('imagefttext() failed');
    }
  }
  # }}}
  static function imgDrawText(# {{{
    object  $img,
    string  $text,
    array   $color,
    string  $font,
    int     $fontSize,
    array   $point
  ):void
  {
    # allocate color
    if (($color = imagecolorallocate($img, $color[0], $color[1], $color[2])) === false) {
      throw BotError::text('imagecolorallocate() failed');
    }
    # draw
    if (!imagefttext($img, $fontSize, 0, $point[0], $point[1], $color, $font, $text)) {
      throw BotError::text('imagefttext() failed');
    }
  }
  # }}}
  static function imgFile(object $img): object # {{{
  {
    if (!($file = tempnam(sys_get_temp_dir(), 'img')) ||
        !imagejpeg($img, $file) || !file_exists($file))
    {
      throw BotError::text("imagejpeg($file) failed");
    }
    return BotApiFile::construct($file, true);
  }
  # }}}
  static function getPlaceholderFile(# {{{
    array &$size,
    array &$color
  ):object
  {
    $img = null;
    try
    {
      # create image
      $img = self::imgNew($size, $color);
      # ...
      # create temporary file
      $res = self::imgFile($img);
    }
    catch (\Throwable $e) {
      $res = BotError::from($e);
    }
    # cleanup
    $img && imagedestroy($img);
    # complete
    return $res;
  }
  # }}}
  static function getTitleFile(# {{{
    array   &$cfg,
    string  $header,
    string  $breadcrumb
  ):object
  {
    $img = null;
    try
    {
      # create image
      if ($a = $cfg['file'])
      {
        if (($img = imagecreatefromjpeg($a)) === false) {
          throw BotError::text("imagecreatefromjpeg($a) failed");
        }
      }
      else {
        $img = self::imgNew($cfg['size'], $cfg['color']);
      }
      # draw texts
      $header && ($a = &$cfg['header']) &&
      self::imgDrawHeader(
        $img, $header, $a['color'], $a['font'],
        $a['size'][0], $a['size'][1], $a['rect']
      );
      $breadcrumb && ($a = &$cfg['breadcrumb']) &&
      self::imgDrawText(
        $img, $breadcrumb, $a['color'], $a['font'],
        $a['size'], $a['pos']
      );
      unset($a);
      # create file
      $res = self::imgFile($img);
    }
    catch (\Throwable $e) {
      $res = BotError::from($e);
    }
    # cleanup
    $img && imagedestroy($img);
    # complete
    return $res;
  }
  # }}}
}
# }}}
class BotTxtMessage extends BotMessage # {{{
{
  function init(): void # {{{
  {
    $this->hash = hash('md4',
      $this->data['text'].$this->data['markup']
    );
  }
  # }}}
  function send(): bool # {{{
  {
    # prepare
    $bot = $this->bot;
    $res = [
      'chat_id'    => $bot->user->chat->id,
      'text'       => $this->data['text'],
      'parse_mode' => 'HTML',
      'disable_notification' => true,
    ];
    if ($this->data['markup']) {
      $res['reply_markup'] = $this->data['markup'];
    }
    # send
    if (!($res = $bot->api->send('sendMessage', $res))) {
      return false;
    }
    # set message identifier and complete
    $this->id = $res->message_id;
    return true;
  }
  # }}}
  function edit(object $msg): bool # {{{
  {
    # prepare
    $bot = $this->bot;
    $res = [
      'chat_id'      => $bot->user->chat->id,
      'message_id'   => $this->id,
      'text'         => $msg->data['text'],
      'parse_mode'   => 'HTML',
      'reply_markup' => $msg->data['markup'],
    ];
    # operate
    if (!($res = $bot->api->send('editMessageText', $res)) || $res === true) {
      return false;
    }
    # update hash and complete
    $this->hash = $msg->hash;
    return true;
  }
  # }}}
  function zap(): bool # {{{
  {
    # prepare
    $bot = $this->bot;
    $res = [
      'chat_id'      => $bot->user->chat->id,
      'message_id'   => $this->id,
      'text'         => $bot->tp->render('{{END}}', ''),
      'parse_mode'   => 'HTML',
    ];
    # operate
    if (!($res = $bot->api->send('editMessageText', $res)) || $res === true) {
      return false;
    }
    # complete
    return true;
  }
  # }}}
}
# }}}
# item (rendering)
abstract class BotItem implements \ArrayAccess, \JsonSerializable # {{{
{
  public $root,$id,$text,$caps,$items;
  public $user,$log,$cfg,$data,$input,$msgs;
  function __construct(# {{{
    public object   $bot,
    public array    $skel,
    public ?object  $parent
  )
  {
    # set base props
    $this->root = $parent ? $parent->root : $this;
    $this->id   = $skel['id'];
    $this->text = new BotItemText($this);
    $this->caps = new BotItemCaptions($this);
    # set children (recurse)
    if (isset($skel[$a = 'items']))
    {
      foreach ($skel[$a] as &$item)
      {
        $type = Bot::NS.$item['type'];
        $item = new $type($bot, $item, $this);
      }
      $this->items = &$skel[$a];
    }
  }
  # }}}
  function jsonSerialize(): array # {{{
  {
    return $this->skel;
  }
  # }}}
  function markup(array &$mkup, ?array $flags = null): string # {{{
  {
    # prepare
    static $NOP = ['text'=>' ','callback_data'=>'!'];
    $id  = $this->id;
    $bot = $this->bot;
    $res = [];
    # iterate
    foreach ($mkup as &$a)
    {
      $row = [];
      foreach ($a as $b)
      {
        # prepare
        # {{{
        # check ready
        if (!is_string($b))
        {
          is_array($b) && ($row[] = $b);
          continue;
        }
        # check empty
        if (!($c = strlen($b))) {
          continue;
        }
        # check nop
        if ($c === 1)
        {
          $row[] = $NOP;
          continue;
        }
        # }}}
        # parse inner (current item)
        if ($b[0] === '!') {
          # {{{
          # get function name
          $d = ($d = strpos($b, ' '))
            ? substr($b, 1, $d)
            : substr($b, 1);
          # check control flags
          if ($flags && isset($flags[$d]))
          {
            if (!($c = $flags[$d])) {
              continue;
            }
            if ($c === -1)
            {
              $row[] = $NOP;
              continue;
            }
          }
          # get caption template
          $c = $this->caps[$d];
          # check specific
          if ($d === 'play')
          {
            # game button
            $d = $this->text[$d] ?: $bot->text[$d];
            $row[] = [
              'text' => $bot->tp->render($c, $d),
              'callback_game' => null
            ];
            continue;
          }
          if ($d === 'up')
          {
            # tree navigation
            if ($e = $this->parent)
            {
              # upward
              $e = $e->text['@'] ?: $e->skel['name'];
            }
            else
            {
              # close
              $c = $this->caps[$d = 'close'];
              $e = $bot->text[$d];
            }
            $row[] = [
              'text' => $bot->tp->render($c, $e),
              'callback_data' => "/$id!$d"
            ];
            continue;
          }
          # determine caption
          $e = $this->text[$d] ?: ($bot->text[$d] ?? '');
          # compose
          $row[] = [
            'text' => $c ? $bot->tp->render($c, $e) : $e,
            'callback_data' => "/$id$b"
          ];
          # }}}
          continue;
        }
        # parse outer (another item)
        # {{{
        # extract identifier and function
        if ($c = strpos($b, '!'))
        {
          $d = substr($b, 0, $c);
          $b = substr($b, $c);
        }
        else
        {
          $d = $b;
          $b = '';
        }
        # correct
        $d = ($d[0] === '/')
          ? substr($d, 1) # exact
          : "$id$d";      # child
        # get item
        if (!($item = $bot->cmd[$d])) {
          continue;
        }
        # determine caption
        ($c = $item->text['@']) ||
        ($c = $this->text[$d])  ||
        ($c = $item->skel['name']);
        # compose
        $row[] = [
          'text' => $bot->tp->render($this->caps['open'], $c),
          'callback_data' => "/$d$b"
        ];
        # }}}
      }
      $row && ($res[] = $row);
    }
    # complete
    return $res
      ? json_encode(['inline_keyboard'=>$res], JSON_UNESCAPED_UNICODE)
      : '';
  }
  # }}}
  function &config(string $method = ''): array # {{{
  {
    # get defaults
    if ($method && ($k = strpos($method, ':')))
    {
      # __METHOD__
      $type   = ($i = strrpos($method, '\\'))
        ? substr($method, $i + 1, $k - $i - 1)
        : substr($method, 0, $k);
      $method = substr($method, $k + 2);
      $conf   = $this->bot->cfg->$type[$method];
    }
    else
    {
      $type   = $this->skel['type'];
      $method = 'config';
      $conf   = $this->bot->cfg->$type;
    }
    # merge with custom
    if (isset($this->skel[$method]))
    {
      $custom = &$this->skel[$method];
      foreach ($conf as $k => &$v)
      {
        if (array_key_exists($k, $custom)) {
          $v = $custom[$k];
        }
      }
      unset($custom, $v);
    }
    # complete
    return $conf;
  }
  # }}}
###
  function attach(# {{{
    object $user,
    string $func = '',
    string $args = '',
    ?array $data = null
  ):?object
  {
    # prepare
    $this->user = $user;
    $this->text->lang = $user->lang;
    $this->log  = $user->log->new($this->skel['path']);
    $this->data = null;
    $this->msgs = [];
    # check common operations
    switch ($func) {
    case 'up':
      # climb up the tree,
      # attach parent instead
      if ($this->parent)
      {
        $this->detach(false);
        return $this->parent->attach($user, '', '');
      }
      # fallthrough otherwise..
    case 'close':
      # no messages rendered,
      # item should be deleted from the view
      return $this;
    }
    # attach configuration
    if (!isset($user->cfg->items[$this->id])) {
      $user->cfg->items[$this->id] = [];
    }
    $this->cfg = &$user->cfg->items[$this->id];
    # attach data
    if (($this->data = BotItemData::construct($this, $data)) === null)
    {
      $this->log->error('failed to attach data');
      $this->detach(false);
      return null;
    }
    # operate
    try
    {
      # render and check redirected or failed
      if (($item = $this->render($func, $args)) !== $this) {
        $this->detach($item !== null);
      }
    }
    catch (\Throwable $e)
    {
      # report
      $this->log->exception($e);
      # prevent data from being saved
      $this->data && ($this->data->changed = false);
      # cleanup
      $this->detach(false);
      $item = null;
    }
    return $item;
  }
  # }}}
  function detach(bool $ok): bool # {{{
  {
    static $NULL = null;
    # detach data
    $this->data?->destruct();
    # detach configuration
    if ($ok && !$this->cfg && $this->cfg !== null) {
      unset($this->user->cfg->items[$this->id]);
    }
    $this->cfg = &$NULL;
    # cleanup
    $this->user = $this->log  =
    $this->data = $this->msgs = null;
    # complete
    return $ok;
  }
  # }}}
  # [BotUserConfig] access  {{{
  function offsetExists(mixed $k): bool {
    return true;
  }
  function offsetGet(mixed $k): mixed {
    return $this->cfg[$k] ?? null;
  }
  function offsetSet(mixed $k, mixed $v): void
  {
    if ($v !== ($this->cfg[$k] ?? null))
    {
      if ($v === null) {
        unset($this->cfg[$k]);
      }
      else {
        $this->cfg[$k] = $v;
      }
      $this->user->cfg->changed = true;
    }
  }
  function offsetUnset(mixed $k): void
  {}
  # }}}
  abstract function render(string $func, string $args): ?object;
}
# }}}
class BotItemText implements \ArrayAccess # {{{
{
  public $text,$lang = 'en';
  function __construct(public object $item) {
    $this->text = &$item->skel['text'];
  }
  function offsetExists(mixed $k): bool {
    return isset($this->text[$this->lang][$k]);
  }
  function offsetGet(mixed $k): mixed {
    return $this->text[$this->lang][$k] ?? '';
  }
  function offsetSet(mixed $k, mixed $v): void
  {}
  function offsetUnset(mixed $k): void
  {}
  function render(array $o): string
  {
    # prepare
    $item = $this->item;
    $bot  = $item->bot;
    # check local
    if ($t = $this->text[$this->lang]['#'] ?? '') {
      return $bot->tp->render($t, $o);
    }
    # check global
    if ($t = $bot->text[$item->skel['type']]) {
      return $bot->tp->render($t, $o);
    }
    # nothing
    return '';
  }
}
# }}}
class BotItemCaptions implements \ArrayAccess # {{{
{
  public $caps,$botCaps;
  function __construct(public object $item)
  {
    $this->caps = &$item->skel['caps'];
    $this->botCaps = &$item->bot->text->caps;
  }
  function offsetExists(mixed $k): bool {
    return true;
  }
  function offsetGet(mixed $k): mixed {
    return $this->caps[$k] ?? $this->botCaps[$k] ?? '';
  }
  function offsetSet(mixed $k, mixed $v): void
  {}
  function offsetUnset(mixed $k): void
  {}
}
# }}}
class BotItemData implements \ArrayAccess # {{{
{
  static function construct(object $item, ?array &$data): ?self # {{{
  {
    # determine storage source
    if ($file = $item->skel['datafile'] ?? 0)
    {
      $file = ($file === 1)
        ? $item->user->dir # private
        : $item->bot->dir->data; # public
      $file = $file.'_'.$item->id.'.json';
    }
    else {
      $file = '';
    }
    # check no data specified
    if ($data === null)
    {
      # load from storage or create blank
      if ($file)
      {
        if (($data = $item->bot->file->getJSON($file)) === null) {
          return null;
        }
      }
      else {
        $data = [];
      }
    }
    # complete
    return new self($item, $file, $data, count($data));
  }
  # }}}
  function __construct(# {{{
    public object   $item,
    public string   $file,
    public array    &$data,
    public int      $count,
    public bool     $changed = false
  ) {}
  # }}}
  function destruct(): void # {{{
  {
    if ($this->file && $this->changed)
    {
      $this->item->bot->file->setJSON($this->file, $this->data);
      $this->changed = false;
    }
  }
  # }}}
  function set(array &$data): bool # {{{
  {
    if ($this->data === $data) {
      return false;
    }
    $this->count = count($this->data = $data);
    return $this->changed = true;
  }
  # }}}
  function merge(array &$data): bool # {{{
  {
    reset($data);
    for ($i = 0, $j = count($data); $i < $j; ++$i)
    {
      $k = key($data);
      if ($data[$k] === null)
      {
        if (isset($this->data[$k]))
        {
          unset($this->data[$k]);
          $this->changed = true;
        }
      }
      elseif (!isset($this->data[$k]) ||
              $this->data[$k] !== $data[$k])
      {
        $this->data[$k] = $data[$k];
        $this->changed  = true;
      }
    }
    return $this->changed;
  }
  # }}}
  function indexOf(string $k, string|int $v): int # {{{
  {
    # search index
    for ($i = 0, $j = $this->count; $i < $j; ++$i)
    {
      if ($this->data[$i][$k] === $v) {
        return $i;
      }
    }
    # not found
    return -1;
  }
  # }}}
  function slice(int $from, int $to): ?array # {{{
  {
    return array_slice($this->data, $from, $to);
  }
  # }}}
  function arrayPush(string $k, ...$v): void # {{{
  {
    if (isset($this->data[$k]))
    {
      array_push($this->data[$k], ...$v);
      $this->changed = true;
    }
  }
  # }}}
  # data access {{{
  function offsetExists(mixed $k): bool {
    return isset($this->data[$k]);
  }
  function offsetGet(mixed $k): mixed {
    return $this->data[$k] ?? null;
  }
  function offsetSet(mixed $k, mixed $v): void
  {
    if (!isset($this->data[$k]))
    {
      $this->data[$k] = $v;
      $this->count++;
      $this->changed = true;
    }
    elseif ($v === null)
    {
      unset($this->data[$k]);
      $this->count--;
      $this->changed = true;
    }
    elseif ($v !== $this->data[$k])
    {
      $this->data[$k] = $v;
      $this->changed = true;
    }
  }
  function offsetUnset(mixed $k): void
  {
    if (isset($this->data[$k]))
    {
      unset($this->data[$k]);
      $this->count--;
      $this->changed = true;
    }
  }
  # }}}
}
# }}}
###
class BotImgItem extends BotItem # {{{
{
  function render(string $func, string $args): ?object # {{{
  {
    # invoke custom handler
    if ($f = $this->skel['handler'])
    {
      if (($msg = $f($this, $func, $args)) === null) {
        return null;
      }
    }
    else {
      $msg = [];
    }
    # create image message
    if (!($m = $this->image($msg))) {
      return null;
    }
    # complete
    $this->msgs[] = $m;
    return $this;
  }
  # }}}
  function image(array &$msg): ?object # {{{
  {
    # prepare
    $bot = $this->bot;
    $cfg = $this->config(__METHOD__);
    # determine identifier and filename
    if (!isset($msg[$a = 'id'])) {
      $msg[$a] = $this->user->lang.'-'.$this->id;
    }
    if (!isset($msg[$b = 'file'])) {
      $msg[$b] = $cfg[$b] ?: $msg[$a].'.jpg';
    }
    $file = $msg[$b];
    # determine image content
    if (!isset($msg[$a = 'image']))
    {
      # check dynamic or non-cached static
      if (!$file || !($b = $bot->file->getId($file)))
      {
        if ($cfg['file'] || $cfg['lookup'])
        {
          # file
          if (!($b = $bot->file->getImage($file))) {
            return null;
          }
          $b = BotApiFile::construct($b, false);
        }
        else
        {
          # title
          $b = $msg['title'] ?? $this->text['@'] ?: $this->skel['name'];
          if (!($b = $this->title($b))) {
            return null;
          }
        }
      }
      $msg[$a] = $b;
    }
    # determine text content
    if (!isset($msg[$a = 'text'])) {
      $msg[$a] = $this->text['#'];
    }
    # determine markup
    if (!isset($msg[$a = 'markup']))
    {
      $msg[$a] = isset($this->skel[$a])
        ? $this->markup($this->skel[$a])
        : '';
    }
    # complete
    return BotImgMessage::construct($bot, $msg);
  }
  # }}}
  function title(string $text): ?object # {{{
  {
    # prepare
    $cfg  = $this->config(__METHOD__);
    $file = $this->bot->file;
    # locate and set files
    if (($cfg['file'] && !$file->mapImage($cfg['file'])) ||
        (($a = &$cfg['header']) && !$file->mapFont($a['font'])) ||
        (($a = &$cfg['breadcrumb']) && !$file->mapFont($a['font'])))
    {
      return null;
    }
    # create image
    $file = BotImgMessage::getTitleFile(
      $cfg, $text, $this->skel['path']
    );
    # check
    if ($file instanceof BotError)
    {
      $this->log->exception($file);
      return null;
    }
    # complete
    return $file;
  }
  # }}}
}
# }}}
class BotListItem extends BotImgItem # {{{
{
  function render(string $func, string $args): ?object # {{{
  {
    # prepare {{{
    $cfg  = $this->config();
    $data = $this->data;
    $mkup = [];
    $msg  = [];
    # refresh data
    if (($hand = $this->skel['handler']) &&
        (!($a = $cfg['timeout'])    ||
         !($b = $this['time'] ?? 0) ||
         ($c = time()) - $b > $a))
    {
      # fetch with handler
      if (($b = $hand($this)) === null)
      {
        $this->log->warn(__METHOD__.":$hand: failed");
        return null;
      }
      # set order
      self::sort($b, $cfg['order'], $cfg['desc']);
      # set update time
      $a && ($this['time'] = $c);
      # store
      $data->set($b);
    }
    # determine page size
    $size = $cfg['rows'] * $cfg['cols'];
    # determine total page count
    if (!($total = intval(ceil($data->count / $size)))) {
      $total = 1;
    }
    # determine current page
    if (($page = $this['page'] ?? 0) >= $total) {
      $page = $total - 1;
    }
    # determine last/prev/next pages
    $lastPage = $total - 1;
    $nextPage = ($page > 0) ? $page - 1 : $lastPage;
    $prevPage = ($page < $total - 1) ? $page + 1 : 0;
    # }}}
    # operate {{{
    switch ($func) {
    case '':
    case 'refresh':
      break;
    case 'first':
      $page = 0;
      break;
    case 'last':
      $page = $lastPage;
      break;
    case 'prev':
    case 'back':
      $page = $prevPage;
      break;
    case 'next':
    case 'forward':
      $page = $nextPage;
      break;
    case 'add':
    case 'create':
      # redirect to the child
      if ($item = $this->items[$func] ?? null) {
        return $item->attach($this->user);
      }
      break;
    case 'id':
      # check empty
      if ($args === '')
      {
        $this->log->error(__METHOD__.":$func: no argument");
        return null;
      }
      # locate item index
      if (($i = $data->indexOf('id', $args)) === -1)
      {
        $this->log->warn(__METHOD__.":$func:$args: item not found");
        break;# refresh the list
      }
      # invoke handler
      if ($item = $this->items[$cfg['item']] ?? null) {
        return $item->attach($this->user, $cfg['func'], $args, $data[$i]);
      }
      # TODO: select/multiselect
      # fail
      $this->log->error(__METHOD__.":$func: handler not found");
      return null;
    default:
      $this->log->error(__METHOD__.":$func: unknown");
      return null;
    }
    # }}}
    # sync
    $this['page'] = $page;
    # render markup {{{
    if ($data->count)
    {
      # non-empty,
      # add top controls
      $list = (isset($this->skel[$a = 'markup'][$b = 'head']))
        ? ($this->skel[$a][$b] ?? [])
        : $cfg[$a][$b];
      foreach ($list as &$c) {
        $mkup[] = $c;
      }
      # extract items from the data set and create markup
      $list = $data->slice($page * $size, $size);
      $list = $this->renderListMarkup($list, $cfg);
      foreach ($list as &$c) {
        $mkup[] = $c;
      }
      # add bottom controls
      $list = (isset($this->skel[$a][$b = 'foot']))
        ? ($this->skel[$a][$b] ?? [])
        : $cfg[$a][$b];
      foreach ($list as &$c) {
        $mkup[] = $c;
      }
      unset($c);
      # determine control flags
      $a = ($total === 1) ? -1 : 1;
      $b = ($total === 1) ?  0 : 1;
      $list = [
        'prev'    => $a,
        'back'    => $a,
        'next'    => $a,
        'forward' => $a,
        'first'   => $b,
        'last'    => $b,
      ];
    }
    else
    {
      # empty,
      # add controls
      $list = (isset($this->skel[$a = 'markup'][$b = 'empty']))
        ? ($this->skel[$a][$b] ?? [])
        : $cfg[$a][$b];
      foreach ($list as &$c) {
        $mkup[] = $c;
      }
      unset($c);
      # determine no flags
      $list = null;
    }
    $msg['markup'] = $this->markup($mkup, $list);
    # }}}
    # render text {{{
    $msg['text'] = $this->text->render([
      'cnt'   => $data->count,
      'page'  => 1 + $page,
      'total' => $total,
    ]);
    # }}}
    # create message
    if (!($m = $this->image($msg))) {
      return null;
    }
    $this->msgs[] = $m;
    return $this;
  }
  # }}}
  function renderListMarkup(# {{{
    array &$list,
    array &$cfg
  ):array
  {
    # prepare
    $bot  = $this->bot;
    $mkup = [];
    $size = count($list);
    $rows = $cfg['rows'];
    $cols = $cfg['cols'];
    $tpl  = $this->caps['listItem'];
    $cmd  = '/'.$this->id.'!id ';
    # iterate rows
    for ($i = 0, $a = 0; $a < $rows; ++$a)
    {
      # create row
      $mkup[$a] = [];
      # iterate columns
      for ($b = 0; $b < $cols; ++$b, ++$i)
      {
        # determine caption and data
        if ($i < $size)
        {
          $c = $tpl
            ? $bot->tp->render($tpl, $list[$i])
            : $list[$i]['name'];
          $d = $cfg['item']
            ? $cmd.$list[$i]['id']
            : '!';
        }
        else
        {
          $c = ' ';
          $d = '!';
        }
        # create callback button
        $mkup[$a][$b] = ['text'=>$c,'callback_data'=>$d];
      }
      # check no more items left and
      # stop creating new rows when flexy option set
      if ($i >= $size && $cfg['flexy']) {
        break;
      }
    }
    # complete
    return $mkup;
  }
  # }}}
  static function sort(array &$data, string $tag, bool $desc): bool # {{{
  {
    if (is_int($data[0][$tag]))
    {
      # integers
      $res = usort($data, function($a, $b) use ($tag, $desc)
      {
        # resolve equal by identifier
        if ($a[$tag] === $b[$tag]) {
          return ($a['id'] > $b['id']) ? 1 : -1;
        }
        # compare
        return $desc
          ? (($a[$tag] > $b[$tag]) ? -1 :  1)
          : (($a[$tag] > $b[$tag]) ?  1 : -1);
      });
    }
    else
    {
      # strings
      $res = usort($data, function($a, $b) use ($tag, $desc)
      {
        # resolve equal by identifier
        if (($c = strcmp($a[$tag], $b[$tag])) === 0) {
          return ($a['id'] > $b['id']) ? 1 : -1;
        }
        # compare
        return $desc
          ? (($c > 0) ? -1 :  1)
          : (($c > 0) ?  1 : -1);
      });
    }
    return $res;
  }
  # }}}
}
# }}}
class BotFormItem extends BotImgItem # {{{
{
  # base
  static function refine(array &$skel): bool # {{{
  {
    if (!isset($skel[$a = 'fields'])) {
      return false;
    }
    if (!is_int(key($skel[$a]))) {
      $skel[$a] = [$skel[$a]];
    }
    if (!isset($skel[$a = 'defs'])) {
      $skel[$a] = [];
    }
    return true;
  }
  # }}}
  function render(string $func, string $args): ?object # {{{
  {
    # prepare {{{
    static $STATUS = [
      -3 => 'progress',
      -2 => 'failure',
      -1 => 'miss',
       0 => 'input',
       1 => 'confirmation',
       2 => 'complete',
       3 => 'progress',
    ];
    $cfg    = $this->config();
    $bot    = $this->bot;
    $id     = $this->id;
    $mkup   = [];
    $msg    = [];
    $info   = '';
    if (($data = $this->data) === null)
    {
      $this->log->warn(__CLASS__.' must have data');
      return null;
    }
    # get currents
    $step   = $this['step']   ?? 0;
    $status = $this['status'] ?? 0;
    $field  = $this['field']  ?? 0;
    # non-persistent form with at least one non-persistent field
    # should reset its data, check
    if (!$cfg['isPersistent'] &&
        $this->user->cfg->queueSearch($this, true) === -1 &&
        ~($a = $this->findFirstField($step, -2)) &&
        $status !== 1 && abs($status) !== 3)
    {
      # resetting completed or failed state
      # is optionated but enabled by default
      $b = ($status === -2);
      $c = ($status ===  2);
      if ((!$b || $cfg['resetFailed']) &&
          (!$c || $cfg['resetCompleted']))
      {
        if ($cfg['resetAll'])
        {
          $this->resetData(0, true);
          $step = $status = $field = 0;
        }
        else
        {
          $this->resetData($step, true);
          if ($status)
          {
            $status = 0;
            ($b || $c) && ($field = 0);
          }
          elseif (~($b = $this->findFirstField($step, 1, 1)) && $b < $a) {
            $field = $b;
          }
          else {
            $field = $a;
          }
        }
      }
    }
    $hand   = $this->skel['handler'];
    $fields = &$this->skel['fields'][$step];
    $fieldNames   = array_keys($fields);
    $fieldCount   = count($fields);
    $fieldLast    = $fieldCount - 1;
    $fieldCurrent = ($field >= 0 && $field < $fieldCount)
      ? $fieldNames[$field]
      : '';
    $fieldMissing = $this->getMissingFieldsCnt($step);
    $fieldOpts    = null;# for performance, select cache
    # }}}
    # operate {{{
    switch ($func) {
    case '':
    case 'refresh':
      break;
    case 'back':
    case 'prev':
      # {{{
      if ($status === 0)
      {
        # check input is at the first field
        if ($field === 0)
        {
          # return to the previous step or
          # move to the last field, otherwise, ignore
          if ($step > 0 && $cfg['backStep'])
          {
            $cfg['backStepReset'] && $this->resetData($step);
            --$step;
            $field = count($this->skel['fields'][$step]) - 1;
          }
          elseif ($cfg['moveAround']) {
            $field = $fieldLast;
          }
          break;
        }
        # return to the previous input field
        --$field;
      }
      elseif ($status === 1 || $status === -2)
      {
        # return to the input from confirmation/failure
        $status = 0;
      }
      # }}}
      break;
    case 'forward':
    case 'next':
      # {{{
      # check in input state
      if ($status !== 0) {
        break;
      }
      # check required skip allowed
      if (!isset($data[$fieldCurrent]) &&
          ($fields[$fieldCurrent][0] & 1))
      {
        $info = $bot->text['req-field'];
        break;
      }
      # check last => first jump allowed
      if ($field === $fieldLast && !$cfg['moveAround']) {
        break;
      }
      # increment
      if (++$field > $fieldLast) {
        $field = 0;
      }
      # }}}
      break;
    case 'first':
      # {{{
      if ($status === 0 && $field !== 0) {
        $field = 0;
      }
      # }}}
      break;
    case 'last':
      # {{{
      if ($status === 0 && $field !== ($a = $fieldCount - 1)) {
        $field = $a;
      }
      # }}}
      break;
    case 'select':
      # {{{
      # check correct state and argument
      if ($status !== 0 || !strlen($args))
      {
        $this->log->error($func, 'incorrect state or argument');
        break;
      }
      # get field options and
      # check selected key exists
      if (!($fieldOpts = $this->getFieldOptions($step, $fieldCurrent)) ||
          !isset($fieldOpts[$args]))
      {
        $this->log->error($func, "option not found: $fieldCurrent/$args");
        break;
      }
      # make selection or de-selection
      if (isset($data[$fieldCurrent]))
      {
        # something already selected,
        # check max keys allowed
        if (($a = $fields[$fieldCurrent][2]) > 1)
        {
          # multi,
          # de-select when exists or do LIFO
          if (($b = array_search($args, $data[$fieldCurrent], true)) !== false)
          {
            if (($c = count($data[$fieldCurrent])) > 1 || $cfg['selectDeselect'])
            {
              if ($c === 1)
              {
                unset($data[$fieldCurrent]);
                $this->log->info($func, "[-]$fieldCurrent");
              }
              else
              {
                array_splice($data[$fieldCurrent], $b, 1);
                $this->log->info($func, "[-]$fieldCurrent.$args");
              }
            }
          }
          elseif (count($data[$fieldCurrent]) >= $a)
          {
            $data[$fieldCurrent][] = $args;
            array_splice($data[$fieldCurrent], 0, 1);
            $this->log->info($func, "[~]$fieldCurrent.$args");
          }
          else
          {
            $data[$fieldCurrent][] = $args;
            $this->log->info($func, "[+]$fieldCurrent.$args");
          }
        }
        else
        {
          # single,
          # de-select same key, replace otherwise
          if ($data[$fieldCurrent] === $args)
          {
            if ($cfg['selectDeselect'])
            {
              unset($data[$fieldCurrent]);
              $this->log->info($func, "[-]$fieldCurrent.$args");
            }
          }
          else
          {
            $data[$fieldCurrent] = $args;
            $this->log->info($func, "[~]$fieldCurrent.$args");
          }
        }
      }
      else
      {
        # create first key
        $data[$fieldCurrent] = (($fields[$fieldCurrent][2] ?? 1) > 1)
          ? [$args] : $args;
        $this->log->info($func, "[+]$fieldCurrent.$args");
      }
      # }}}
      break;
    case 'input':
      # {{{
      # check
      if ($status !== 0) {
        return null;
      }
      # accept
      if (!($a = $this->acceptInput($step, $fieldCurrent))) {
        return null;
      }
      # check accepted
      if (~$a[0])
      {
        # report success
        if ($a[0]) {
          $this->log->info($func, "[~]$fieldCurrent");
        }
        # set info
        isset($a[1]) && ($info = $a[1]);
      }
      # }}}
      break;
    case 'clear':
      # {{{
      if ($status === 0 && ($fields[$fieldCurrent][0] & 4) &&
          isset($data[$fieldCurrent]))
      {
        # clear and callback
        unset($data[$fieldCurrent]);
        $hand && $hand($this, 'changed', $fieldCurrent);
        # report
        $this->log->info($func, "[-]$fieldCurrent");
      }
      # }}}
      break;
    case 'ok':
    case 'submit':
    case 'retry':
      # {{{
      # check in proper state
      if (($status !== 0 && $status !== 1 && $status !== -2) ||
          ($status === -2 && !$cfg['retryFailed']))
      {
        $this->log->error($func, "($status)");
        return null;
      }
      # check option and
      # act as forward before last field is reached
      if ($status === 0 && $cfg['okIsForward'] && $field < $fieldLast)
      {
        # warn when required is empty
        if (!isset($data[$fieldCurrent]) &&
            ($fields[$fieldCurrent][0] & 1))
        {
          $info = $bot->text['req-field'];
          break;
        }
        # advance to the next field
        $field++;
        break;
      }
      # check any missing required
      if ($fieldMissing)
      {
        # switch to failure and
        # seek to the first empty required
        $status = -1;
        $field  = $this->findFirstField($step, 1, 1);
        break;
      }
      # determine index of the last step
      $a = count($this->skel['fields']) - 1;
      # determine action variant:
      # next step, confirmation or submission
      $b = (
        ($step < $a) ||
        ($cfg['okConfirm'] && ($status === 0 || $status === -2))
      );
      $func = $b ? 'ok' : 'submit';
      # callback
      if ($hand && ($c = $hand($this, $func, $step)))
      {
        if ($c[0] === -1)
        {
          # TODO: task startup
          $this->log->warn($func, 'task');
          break;
        }
        elseif ($c[0] === 2)
        {
          # instant submission
          $b = false;
          $info = $c[1] ?? '';
        }
        elseif ($c[0] === 0)
        {
          # step failure
          $info   = $c[1] ?? $bot->text['op-fail'];
          $status = -2;
          break;
        }
        elseif (isset($c[1]))
        {
          # step success
          $info = $c[1];
        }
      }
      # proceed to the next step / confirmation
      if ($b)
      {
        if ($step < $a)
        {
          ++$step;
          $status = $field = 0;
        }
        else {
          $status = 1;
        }
        break;
      }
      # proceed to completion
      $status = 2;
      # }}}
      break;
    case 'reset':
      # {{{
      # check allowed
      if (($status < 0 && $status > -3 && !$cfg['resetFailed']) ||
          ($status > 0 && $status <  3 && !$cfg['resetCompleted']))
      {
        $this->log->error($func, "($status)");
        return null;
      }
      # hard reset
      if ($cfg['resetAll'])
      {
        $this->resetData(0);
        $step = $status = $field = 0;
      }
      else
      {
        $this->resetData($step);
        $status = $field = 0;
      }
      # }}}
      break;
    case 'change':
    case 'repeat':
      # {{{
      # check in correct state
      if (abs($status) !== 2)
      {
        $this->log->error($func, $STATUS[$status]);
        return null;
      }
      elseif ($status < 0)
      {
        # back to normal (failure => input)
        $status = 0;
      }
      else
      {
        # changing or repeating (complete => input)
        if ($cfg['resetAll'] || $cfg['isPersistent'])
        {
          # hard repeat/change
          $this->resetData(0, true);
          $step = $status = 0;
        }
        else
        {
          # soft repeat
          $this->resetData($step, true);
          $status = 0;
        }
        $field = $cfg['isPersistent']
          ? 0
          : (~($a = $this->findFirstField(0, -2))
            ? $a
            : 0);
      }
      # }}}
      break;
    default:
      $this->log->error($func);
      return null;
    }
    # }}}
    # sync {{{
    $a = $step   !== $this['step'];
    $b = $status !== $this['status'];
    $c = $field  !== $this['field'];
    if ($a || $b || $c)
    {
      # update
      $a && ($this['step']   = $step);
      $b && ($this['status'] = $status);
      $c && ($this['field']  = $field);
      # re-determine
      if ($a)
      {
        $fields = &$this->skel['fields'][$step];
        $fieldNames = array_keys($fields);
        $fieldCount = count($fields);
      }
      if ($a || $c)
      {
        $fieldCurrent = ($field >= 0 && $field < $fieldCount)
          ? $fieldNames[$field]
          : '';
      }
      # report
      $d = '';
      $a && ($d .= " step:$step");
      $b && ($d .= ' status:'.$STATUS[$status]);
      $c && ($d .= " field:$fieldCurrent");
      $this->log->info($func, ltrim($d));
    }
    if ($a || $data->changed) {
      $fieldMissing = $this->getMissingFieldsCnt($step);
    }
    # }}}
    # render markup {{{
    if ($status)
    {
      # result
      # determine markup type
      $a = ($status < 0)
        ? 'failure'
        : ($status === 1
          ? 'confirm'
          : 'success');
      # get markup
      $a = isset($this->skel[$b = 'markup'][$a])
        ? $this->skel[$b][$a]
        : $cfg[$b][$a];
      # append
      foreach ($a as &$b) {
        $mkup[] = $b;
      }
      unset($b);
      # determine flags
      $a = [
        'retry' => $cfg['retryFailed']
          ? (($status === -2)
            ? 1
            : ($cfg['retryDisable'] ? -1 : 0))
          : 0,
        'repeat' => $cfg['isPersistent']
          ? 0
          : 1,
        'change' => $cfg['isPersistent']
          ? 1
          : 0,
      ];
    }
    else
    {
      # input mode
      # header
      $a = isset($this->skel[$a = 'markup'][$b = 'inputHead'])
        ? $this->skel[$a][$b]
        : $cfg[$a][$b];
      foreach ($a as &$b) {
        $mkup[] = $b;
      }
      unset($b);
      # selector group
      if ($a = $this->getFieldOptionsMkup($step, $fieldCurrent, $cfg, $fieldOpts))
      {
        foreach ($a as &$b) {
          $mkup[] = $b;
        }
        unset($b);
      }
      # clear button
      $mkup[] = ['!clear'];
      # footer
      $a = isset($this->skel[$a = 'markup'][$b = 'inputFoot'])
        ? $this->skel[$a][$b]
        : $cfg[$a][$b];
      foreach ($a as &$b) {
        $mkup[] = $b;
      }
      unset($b);
      # determine control flags
      $a = ($field === 0);
      $b = ($field === $fieldCount - 1);
      $a = [
        'back' => $a
          ? (($cfg['moveAround'] || !$cfg['backDisable']) ? 1 : -1)
          : 1,
        'forward' => $cfg['okIsForward']
          ? 0
          : ($cfg['forwardToOk']
            ? ($b
              ? ($fieldMissing
                ? (($cfg['moveAround'] || !$cfg['forwardDisable']) ? 1 : -1)
                : 0)
              : 1)
            : ($b
              ? (($cfg['moveAround'] || !$cfg['forwardDisable']) ? 1 : -1)
              : 1)),
        'ok' => $cfg['okIsForward']
          ? 1
          : ($cfg['forwardToOk']
            ? ($b
              ? ($fieldMissing ? 0 : 1)
              : 0)
            : ($cfg['okWhenReady']
              ? ($fieldMissing
                ? ($cfg['okDisable'] ? -1 : 0)
                : 1)
              : 1)),
        'clear' => ($fields[$fieldCurrent][0] & 4)
          ? (isset($data[$fieldCurrent])
            ? 1
            : ($cfg['clearSolid'] ? -1 : 0))
          : ($cfg['clearSolid'] ? -1 : 0),
      ];
      # set aliases
      $a['prev']   = $a['back'];
      $a['next']   = $a['forward'];
      $a['submit'] = $a['ok'];
    }
    # compose
    $msg['markup'] = $this->markup($mkup, $a);
    # }}}
    # render text {{{
    # get completed and current fields
    $a = $this->getCompleteFields($step, $status, $cfg);
    if ($status === 2) {
      $b = [];
    }
    else
    {
      # base + extra
      $b = $this->getCurrentFields($step, $status, $field, $cfg);
      $hand && ($c = $hand($this, 'fields', $b)) && ($b = $c);
    }
    # determine status and progress information
    $c = 0;
    if (!$info)
    {
      if ($status === -1) {
        $info = $bot->text['req-miss'];
      }
      elseif ($status < -1 && $hand)
      {
        $info = ($c = $hand($this, 'status', $status))
          ? $c[0]
          : ($status === -3
            ? $bot->text['task-await']
            : $bot->text['op-fail']);
        $c = ($c && isset($c[1]))
          ? $c[1]
          : 0;
      }
    }
    if (!$hand || !($d = $hand($this, 'description', $status))) {
      $d = $this->text['#description'] ?: '';
    }
    # compose
    $msg['text'] = $this->text->render([
      'description'   => $d,
      'completeCount' => count($a),
      'currentCount'  => count($b),
      'complete' => $a,
      'current'  => $b,
      'status'   => $status,
      'info'     => $info,
      'progress' => $c,
    ]);
    # }}}
    # complete {{{
    if (!($a = $this->image($msg))) {
      return null;
    }
    $this->msgs[] = $a;
    if ($status === 0)
    {
      $b = $this->newFieldHint($step, $fieldCurrent);
      if (!($a = BotTxtMessage::construct($bot, $b))) {
        return null;
      }
      $this->msgs[] = $a;
    }
    return $this;
    # }}}
  }
  # }}}
  function acceptInput(int $step, string $fieldName): ?array # {{{
  {
    # prepare
    $bot   = $this->bot;
    $hand  = $this->skel['handler'];
    $field = &$this->skel['fields'][$step][$fieldName];
    $info  = '';
    # try custom handler
    if ($hand && ($a = $hand($this, 'input', $fieldName))) {
      return $a;
    }
    # do standard handling
    # get text
    if (($inp = $this->input->text ?? null) === null) {
      return null;
    }
    # operate
    switch ($field[1]) {
    case 'string':
      # validate string size
      if (isset($field[2]) && $field[2] && strlen($inp) > $field[2])
      {
        # hidden data is considered fragile, so,
        # instead of cutting, discard and report
        if ($field[0] & 8)
        {
          unset($this->data[$fieldName]);
          return [0, $bot->text['oversized']];
        }
        else
        {
          $inp  = substr($inp, 0, $field[2]);
          $info = $bot->text['oversized'];
        }
      }
      # check syntax
      if (isset($field[3]) && !preg_match($field[3], $inp))
      {
        if ($field[0] & 8) {
          unset($this->data[$fieldName]);
        }
        return [0, $bot->text['bad-data']];
      }
      break;
    case 'int':
      # validate integer[-2147483648..2147483648] or long[0..4294967295]
      # extract sign
      $a = ($inp[0] === '-');
      $a && ($inp = substr($inp, 1));
      # check length and digits
      if (!($b = strlen($inp)) || $b > 10 || !ctype_digit($inp)) {
        return [0, $bot->text['bad-data']];
      }
      # cast
      $inp = intval($inp);
      $a && ($inp = -$inp);
      # check minimum and maximum
      if (isset($field[2]) && $inp < $field[2])
      {
        $a = $bot->tp->render($bot->text['min-value'], [
          'x' => $field[2]
        ]);
        return [0, $a];
      }
      elseif (isset($field[3]) && $inp > $field[3])
      {
        $a = $bot->tp->render($bot->text['max-value'], [
          'x' => $field[3]
        ]);
        return [0, $a];
      }
      break;
    default:
      return null;
    }
    # check equal
    if ($inp === $this->data[$fieldName]) {
      return [-1];
    }
    # change and callback
    $this->data[$fieldName] = $inp;
    $hand && $hand($this, 'changed', $fieldName);
    return [1, $info];
  }
  # }}}
  # helpers
  function resetData(# {{{
    int  $step,
    bool $soft = false
  ):void
  {
    if ($step > 0)
    {
      # partial reset,
      # clear step fields (keep persistent)
      $a = &$this->skel['fields'][$step];
      foreach ($a as $b => &$c)
      {
        if (!$soft || !($c[0] & 2)) {
          unset($this->data[$b]);
        }
      }
      unset($c);
    }
    else
    {
      # reset all,
      # get defaults
      $defs = $this->skel['defs'];
      if ($hand = $this->skel['handler']) {
        $hand($this, 'defs', $defs);
      }
      # keep persistent fields
      if ($soft && $this->data->count)
      {
        foreach ($this->skel['fields'] as $a => &$b)
        {
          foreach ($b as $c => &$d)
          {
            if (($d[0] & 2) && isset($this->data[$c])) {
              $defs[$c] = $this->data[$c];
            }
          }
        }
        unset($b, $d);
      }
      # replace
      $this->data->set($defs);
    }
  }
  # }}}
  function findFirstField(# {{{
    int $step,
    int $bit   = 0,# 0=any,1=required,2=persistent,4=nullable
    int $empty = 0 # 0=any,1=yes,-1=nope
  ):int
  {
    # prepare
    $fields = &$this->skel['fields'][$step];
    $count  = count($fields);
    if ($bit < 0)
    {
      $not = true;
      $bit = -$bit;
    }
    else {
      $not = false;
    }
    # search
    reset($fields);
    for ($a = 0; $a < $count; ++$a, next($fields))
    {
      # get field name
      $b = key($fields);
      # check
      if ($bit)
      {
        $c = $fields[$b][0];
        if ($not)
        {
          if (!($c & $bit))
          {
            if ($empty)
            {
              $d = isset($this->data[$b]);
              if ($empty > 0)
              {
                if (!$d) {
                  break;
                }
              }
              elseif ($d) {
                break;
              }
            }
            else {
              break;
            }
          }
        }
        elseif ($c & $bit)
        {
          if ($empty)
          {
            $d = isset($this->data[$b]);
            if ($empty > 0)
            {
              if (!$d) {
                break;
              }
            }
            elseif ($d) {
              break;
            }
          }
          else {
            break;
          }
        }
      }
      elseif ($empty)
      {
        $d = isset($this->data[$b]);
        if ($empty > 0)
        {
          if (!$d) {
            break;
          }
        }
        elseif ($d) {
          break;
        }
      }
      else {
        break;
      }
    }
    # complete
    return ($a < $count) ? $a : -1;
  }
  # }}}
  function getMissingFieldsCnt(int $step): int # {{{
  {
    $c = 0;
    foreach ($this->skel['fields'][$step] as $a => &$b)
    {
      if (($b[0] & 1) && !isset($this->data[$a])) {
        $c++;
      }
    }
    unset($b);
    return $c;
  }
  # }}}
  function &getFieldOptions(int $step, string $name): ?array # {{{
  {
    static $SPEC = '!select', $NULL = null;
    # get field and check it has options
    $field = &$this->skel['fields'][$step][$name];
    if (!strpos($SPEC, $field[1])) {
      return $NULL;
    }
    # get options defined in the command or
    # fetch complete map using handler
    if (is_array($a = end($field)) && $a)
    {
      $opts = [];
      foreach ($a as $b) {
        $opts[$b] = $this->text[".$b"] ?: $b;
      }
    }
    elseif (!($hand = $this->skel['handler']) ||
            !($opts = $hand($this, 'options', $name)))
    {
      return $NULL;
    }
    return $opts;
  }
  # }}}
  function &getFieldOptionsMkup(# {{{
    int    $step,
    string $name,
    array  &$cfg,
    ?array &$opts = null
  ):?array
  {
    static $NULL = null;
    # get field options
    if (!$opts && !($opts = $this->getFieldOptions($step, $name))) {
      return $NULL;
    }
    # get list of selected keys
    if ($this->data && isset($this->data[$name]))
    {
      if (!is_array($keys = $this->data[$name])) {
        $keys = [$keys];
      }
    }
    else {
      $keys = null;
    }
    # prepare
    $id    = $this->id;
    $func  = $this->skel['fields'][$step][$name][1];
    $templ = $this->caps[$func];
    $bot   = $this->bot;
    $cols  = $cfg['cols'];
    $row   = [];
    $mkup  = [];
    # iterate
    foreach ($opts as $a => $b)
    {
      # create inline button
      $b = $bot->tp->render($templ, [
        'v' => ($keys && in_array($a, $keys, true)),
        'x' => $b,
      ]);
      $row[] = [
        'text' => $b,
        'callback_data' => "/$id!$func $a",
      ];
      # accumulate
      if (--$cols <= 0)
      {
        $mkup[] = $row;
        $row    = [];
        $cols   = $cfg['cols'];
      }
    }
    # add last row
    if ($row)
    {
      if (($a = count($row)) < ($cols = $cfg['cols']) &&
          !$cfg['colsFlex'])
      {
        for ($a; $a < $cols; ++$a) {
          $row[] = '!';
        }
      }
      $mkup[] = $row;
    }
    # complete
    return $mkup;
  }
  # }}}
  function &getFields(int $step, array &$cfg): array # {{{
  {
    # prepare
    $res    = [];
    $fields = &$this->skel['fields'][$step];
    $count  = count($fields);
    # iterate
    reset($fields);
    for ($i = 0; $i < $count; ++$i, next($fields))
    {
      # get field
      $name  = key($fields);
      $field = &$fields[$name];
      # get value
      $value = (($field[0] & 8) && isset($this->data[$name]))
        ? $cfg['hiddenValue']
        : null;# default value
      # accumulate
      $res[] = $this->newFieldDescriptor($name, $value, $field[0]);
    }
    return $res;
  }
  # }}}
  function &getCurrentFields(# {{{
    int   $step,
    int   $status,
    int   $field,
    array &$cfg
  ):array
  {
    # get fields
    $res = &$this->getFields($step, $cfg);
    # set flags
    foreach ($res as $a => &$b)
    {
      switch ($status) {
      case 0:
        # current
        $b['flag'] = ($a === $field);
        break;
      case -1:
        # missing required
        $b['flag'] = (($b['type'] & 1) && ($b['value'] === ''));
        break;
      }
    }
    return $res;
  }
  # }}}
  function &getCompleteFields(# {{{
    int   $step,
    int   $status,
    array &$cfg
  ):array
  {
    $res = [];
    if ($status === 2) {
      $step++;
    }
    if ($step > 0)
    {
      if ($status === 2 && $cfg['isPersistent'])
      {
        # exclude non-persistent fields
        for ($i = 0; $i < $step; ++$i)
        {
          foreach ($this->getFields($i, $cfg) as &$a) {
            ($a['type'] & 2) && ($res[] = $a);
          }
        }
      }
      else
      {
        # merge all
        for ($i = 0; $i < $step; ++$i) {
          $res = array_merge($res, $this->getFields($i, $cfg));
        }
      }
    }
    return $res;
  }
  # }}}
  function newFieldDescriptor(# {{{
    string  $id,
    ?string $value = null,
    int     $type  = 0
  ):array
  {
    if ($value === null)
    {
      if (isset($this->data[$id]))
      {
        if (!is_string($value = $this->data[$id]))
        {
          $value = is_array($value)
            ? implode(',', $value)
            : "$value";
        }
      }
      else {
        $value = '';
      }
    }
    return [
      'id'    => $id,
      'name'  => ($this->text[".$id"] ?: $id),
      'value' => $value,
      'type'  => $type,# bitmask
      'flag'  => false,
    ];
  }
  # }}}
  function newFieldHint(int $step, string $name): array # {{{
  {
    # assemble text message
    if (($a = $this->skel['handler']) &&
        ($a = $a($this, 'hint', $name)))
    {
      # dynamic (handler),
      # extract text and markup
      $b = $a[1] ?? null;
      $a = $a[0];
    }
    else
    {
      # static (item or bot),
      # try to extract from the item
      if (!($a = $this->text[">$name"]))
      {
        # reach for common type otherwise
        $a = $this->skel['fields'][$step][$name][1];
        $a = $this->bot->text[">$a"];
      }
      $b = null;
    }
    # complete
    return ['text'=>$a, 'markup'=>$b];
  }
  # }}}
}
# }}}
/***
* TODO {{{
* ╔═╗ ⎛     ⎞ ★★☆☆☆
* ║╬║ ⎜  *  ⎟ ✱✱✱ ✶✶✶ ⨳⨳⨳
* ╚═╝ ⎝     ⎠ ⟶ ➤
*
* check old message callback action does ZAP? or.. PROPER UPDATE?!
* check file_id stores oke
* make handler parse errors more descriptive
* remove unnecessary refresh in private chat (update compatibility)
* }}}
*/
class BotTxtItem extends BotItem # {{{
{
  function render(string $func, string $args): ?object # {{{
  {
    return null;
  }
  # }}}
}
# }}}
?>
