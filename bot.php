<?php
declare(strict_types=1);
namespace SM;
class Bot # {{{
{
  const NS = '\\'.__NAMESPACE__.'\\';
  const MESSAGE_LIFETIME = 48*60*60;
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
    # enforce graceful termination
    register_shutdown_function(function() use ($bot) {
      # guard against non-recoverable (non-catched) errors
      error_get_last() && $bot->destruct();
    });
    if ($bot->isMaster && function_exists($e = 'sapi_windows_set_ctrl_handler'))
    {
      # WinOS: console breaks must stop masterbot
      $e(function (int $e) use ($bot)
      {
        $bot->destruct();
        ($e === PHP_WINDOWS_EVENT_CTRL_C)
          ? exit(1) # restart
          : exit(2);# stop
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
      if (!$bot->cfg->init() ||
          !$bot->log->init() ||
          !$bot->dir->init() ||
          !$bot->file->init())
      {
        throw BotError::skip();
      }
      # set masterbot identifier or
      # check/match identifiers
      if ($bot->isMaster) {
        $bot->id = $bot->cfg->id;
      }
      elseif ($id !== $bot->cfg->id) {
        throw BotError::text('identifiers mismatch: '.$id.'/'.$bot->cfg->id);
      }
      # load dependencies
      require_once $bot->dir->inc.'mustache.php';
      require_once $bot->dir->src.'handlers.php';
      # set telegram api
      if (!($bot->api = BotApi::construct($bot))) {
        throw BotError::skip();
      }
      # set template parser
      $o = [
        'logger'  => \Closure::fromCallable([
          $bot->log->new('mustache'), 'errorOnly'
        ]),
        'helpers' => [
          'BR'    => "\n",
          'NBSP'  => "\xC2\xA0",# non-breakable space
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
  public $dir,$cfg,$log,$file,$api,$tp,$text,$cmd,$proc,$user;
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
  }
  # }}}
  function destruct(): void # {{{
  {
    if ($this->log)
    {
      $this->user?->destruct();
      $this->proc?->destruct();
      $this->api?->destruct();
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
    $this->user->destruct($q->result());
    $this->user = null;
    # complete negative when status change
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
  public $home,$inc,$data,$usr,$grp,$img,$font,$src;
  function __construct(public object $bot) # {{{
  {
    # determine proper data sub-directory
    $a = $bot->isMaster ? 'master' : $bot->id;
    # determine base paths
    $this->home = __DIR__.DIRECTORY_SEPARATOR;
    $this->inc  = $this->home.'inc'.DIRECTORY_SEPARATOR;
    $this->data = $this->home.'data'.DIRECTORY_SEPARATOR.$a.DIRECTORY_SEPARATOR;
    $this->usr  = $this->data.'usr'.DIRECTORY_SEPARATOR;
    $this->grp  = $this->data.'grp'.DIRECTORY_SEPARATOR;
    $this->img  = [];
    $this->font = [];
    file_exists($a = $this->data.'img'.DIRECTORY_SEPARATOR) && ($this->img[] = $a);
    file_exists($a = $this->data.'font'.DIRECTORY_SEPARATOR) && ($this->font[] = $a);
    $this->src = $this->home.'bots'.DIRECTORY_SEPARATOR;
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
    if (!file_exists($this->src))
    {
      $log->error('not found: '.$this->src);
      return false;
    }
    # determine bot source directory
    $this->src = $this->src.$bot->cfg->source.DIRECTORY_SEPARATOR;
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
class BotConfig # {{{
{
  const FILE_JSON = 'config.json';
  const DEFS = [
    # {{{
    'source' => '',
    'token'  => '',
    'name'   => '',
    'admins' => [],
    'lang'   => '',
    'useFileIds'  => false,
    ###
    'BotLog' => [
      'debug'     => true,# show dumps/debug output
      'infoFile'  => '',
      'errorFile' => '',
    ],
    'BotRequestInput' => [
      'wipeInput' => true,
    ],
    'BotRequestCommand' => [
      'wipeInput' => true,
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
        'color'      => [0,0,0],# black
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
      'cmd'       => '',# nop, display only
      'timeout'   => 0,# data refresh timeout (sec), 0=always
      'markup'    => [
        'head'    => [],
        'foot'    => [['!prev','!next'],['!up']],
        'empty'   => [['!up']],
      ],
    ],
    # }}}
  ];
  public $id,$file;
  function __construct(public ?object $bot) # {{{
  {
    # set defaults
    foreach (self::DEFS as $k => $v) {
      $this->$k = $v;
    }
  }
  # }}}
  function init(): bool # {{{
  {
    # prepare
    $bot = $this->bot;
    # load bot configuration
    if (!file_exists($file = $bot->dir->data.self::FILE_JSON) ||
        !($o = $bot->file->getJSON($file))   ||
        !isset($o[$k = 'source']) || !$o[$k] ||
        !isset($o[$k = 'token'])  || !($token = $o[$k]))
    {
      $bot->log->error("failed to load: $file");
      return false;
    }
    # parse token (extract identifier)
    if (!($k = strpos($token, ':')) ||
        !ctype_digit($id = substr($token, 0, $k)))
    {
      $bot->log->error("incorrect token: $token");
      return false;
    }
    # cast to integer
    $id = intval($id);
    # match against slavebot
    if (!$bot->isMaster && $id !== $bot->id)
    {
      $bot->log->error("identifier mismatch: $id");
      return false;
    }
    # replace defaults
    foreach (self::DEFS as $k => &$v)
    {
      if (isset($o[$k])) {
        $this->$k = $o[$k];
      }
    }
    # check mode
    if ($bot->isConsole)
    {
      # lock
      if (!BotFile::lock($file))
      {
        $bot->log->warn('bot has already started');
        return false;
      }
    }
    elseif (!file_exists("$file.lock"))
    {
      $bot->log->warn('bot is disabled');
      return false;
    }
    # complete
    $this->id   = $id;
    $this->file = $file;
    return true;
  }
  # }}}
  function destruct(): void # {{{
  {
    if ($this->bot)
    {
      # unlock
      $this->bot->isConsole && $this->file &&
      BotFile::unlock($this->file);
      # cleanup
      $this->bot = null;
    }
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
  {}
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
  function debug(string $msg): void # {{{
  {
    $this['debug'] && $this->out(0, 0, $msg);
  }
  # }}}
  function info(string $msg): void # {{{
  {
    $this->out(0, 0, $msg);
  }
  # }}}
  function infoInput(string $msg): void # {{{
  {
    $this->out(0, 1, $msg);
  }
  # }}}
  function error(string $msg): void # {{{
  {
    $this->out(1, 0, $msg);
  }
  # }}}
  function errorOnly(string $msg, int $level): void # {{{
  {
    $level && $this->out(1, 0, $msg);
  }
  # }}}
  function warn(string $msg): void # {{{
  {
    $this->out(2, 0, $msg);
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
  const FILES = ['fids.json','fonts.json'];
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
      $file = $dir.self::FILES[0];
      if (($this->fids = $this->getJSON($file)) === null) {
        return false;
      }
    }
    # load fonts map
    if (file_exists($file = $dir.self::FILES[1]))
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
      $this->log->error(__FUNCTION__."($file):file_get_contents: failed");
      return null;
    }
    if (($data = json_decode($data, true)) === null &&
        json_last_error() !== JSON_ERROR_NONE)
    {
      $this->log->error(__FUNCTION__."($file):json_decode: ".json_last_error_msg());
      return null;
    }
    if (!is_array($data))
    {
      $this->log->error(__FUNCTION__."($file): incorrect data");
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
        $this->log->error(__FUNCTION__."($file):json_encode: ".json_last_error_msg());
        return false;
      }
      if (file_put_contents($file, $data) === false)
      {
        $this->log->error(__FUNCTION__."($file):file_put_contents: failed");
        return false;
      }
    }
    elseif (file_exists($file))
    {
      if (@unlink($file) === false)
      {
        $this->log->error(__FUNCTION__."($file):unlink: failed");
        return false;
      }
    }
    $this->log->debug(__FUNCTION__."($file)");
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
    return ($this->fids !== null && isset($this->fids[$file]))
      ? $this->fids[$file]
      : '';
  }
  # }}}
  function setId(string $file, string $id): void # {{{
  {
    if ($this->bot->cfg->useFileIds)
    {
      $this->fids[$file] = $id;
      if (self::lock($file = $this->bot->dir->data.self::FILES[0]))
      {
        if ($this->setJSON($file, $this->fids)) {
          $this->log->info(__FUNCTION__."($file)");
        }
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
  static function unlock(string $file) # {{{
  {
    return (file_exists($lock = "$file.lock"))
      ? @unlink($lock)
      : false;
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
    #CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2TLS,
    CURLMOPT_PIPELINING    => 0,
    CURLOPT_PIPEWAIT       => false,
    CURLOPT_FOLLOWLOCATION => false,
    #CURLOPT_NOSIGNAL       => true,
    #CURLOPT_VERBOSE        => true,
    #CURLOPT_SSL_VERIFYHOST => 0,
    #CURLOPT_SSL_VERIFYPEER => false,
    # }}}
  ];
  static function construct(object $bot): ?self # {{{
  {
    # prepare
    $log  = $bot->log->new('api');
    $api  = null;
    $curl = $murl = false;
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
      # construct
      $api = new self(
        $bot, $log, self::URL.$bot->cfg->token, $curl, $murl
      );
    }
    catch (\Throwable $e)
    {
      $log->exception($e);
      $curl && curl_close($curl);
      $murl && curl_multi_close($murl);
    }
    return $api;
  }
  # }}}
  function __construct(# {{{
    public ?object  $bot,
    public ?object  $log,
    public string   $url,
    public ?object  $curl,
    public ?object  $murl
  ) {}
  # }}}
  function destruct(): void # {{{
  {
    if ($this->bot)
    {
      if ($this->murl)
      {
        $this->getUpdates(true);
        curl_multi_close($this->murl);
      }
      if ($this->curl) {
        curl_close($this->curl);
      }
      $this->bot = $this->log = $this->curl = $this->murl = null;
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
      $q = [
        CURLOPT_URL  => $this->url.'/getUpdates',
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
          usleep(300000);# 300ms
          if (!$this->bot->check()) {
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
  function send(string $method, array $req, ?object $file = null): object|bool # {{{
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
      # set file attachment or
      # determine already set file
      if ($file !== null) {
        $req[$file->postname] = $file;
      }
      elseif (isset($FILE_METHOD[$method]) &&
              isset($req[$a = $FILE_METHOD[$method]]) &&
              $req[$a] instanceof BotApiFile)
      {
        $file = $req[$a];
      }
      # set request parameters
      $req = [
        CURLOPT_URL  => $this->url.'/'.$method,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $req,
      ];
      if (!curl_setopt_array($this->curl, $req)) {
        throw BotError::text('failed to set request');
      }
      # send
      if (($a = curl_exec($this->curl)) === false) {
        throw BotError::text('curl_exec('.curl_errno($this->curl).'): '.curl_error($this->curl));
      }
      # decode response
      if (!($res = json_decode($a, false))) {
        throw BotError::text("incorrect response\n█{$a}█\n");
      }
      # check result flag
      if (!$res->ok || !isset($res->result))
      {
        throw BotError::text(isset($res->description)
          ? $res->description
          : "incorrect response\n█{$a}█\n"
        );
      }
      # success
      $res = $res->result;
    }
    catch (\Throwable $e)
    {
      # report error
      $this->log->exception($e);
      $res = false;
    }
    finally
    {
      # remove temporary file
      if ($file !== null) {
        $file->destruct();
      }
    }
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
    # }}}
  ];
  static function construct(object $bot): ?self # {{{
  {
    # prepare
    $dir = $bot->dir;
    # load texts
    if (file_exists($fileJson = $dir->data.self::FILE_JSON[0]))
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
    if (file_exists($fileJson = $dir->data.self::FILE_JSON[1]))
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
    $lang = $this->bot->user?->lang ?? 'en';
    return $this->texts[$lang][$k] ?? '';
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
      # set type class
      $skel[$a = 'type'] = isset($skel[$a])
        ? 'Bot'.ucfirst($skel[$a]).'Item'
        : 'BotImgItem';
      if (!class_exists($class = Bot::NS.$skel[$a], false))
      {
        $bot->log->error("class not found: $class");
        return false;
      }
      # determine item handler
      $skel['handler'] = function_exists($a = Bot::NS.'BotItem_'.$id)
        ? $a : '';
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
      # set secondary languages
      foreach (array_keys($bot->text->texts) as $b)
      {
        if (!isset($skel[$a][$b])) {
          $skel[$a][$b] = $skel[$a]['en'];
        }
      }
      # set contents
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
class BotMaster # {{{
{
  static function construct(object $bot): ?self # {{{
  {
    # determine starter command
    if (!file_exists($cmd = $bot->dir->home.'start.php'))
    {
      $bot->log->error("file not found: $cmd");
      return null;
    }
    $cmd = '"'.PHP_BINARY.'" -f "'.$cmd.'" ';
    # construct
    return new self($bot, $bot->log->new('proc'), $cmd);
  }
  # }}}
  function __construct(# {{{
    public object $bot,
    public object $log,
    public string $cmd,
    public array  $map = []
  ) {}
  # }}}
  function start($id): bool # {{{
  {
    # check
    if (isset($this->map[$id])) {
      return true;
    }
    # start new process
    if (!($slave = BotMasterSlave::construct($this, $id))) {
      return false;
    }
    # store
    $this->map[$id] = $slave;
    return true;
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
  function destruct(): void # {{{
  {
    foreach ($this->map as $id => $slave) {
      $slave->destruct();
    }
  }
  # }}}
}
# }}}
class BotMasterSlave # {{{
{
  static function construct(object $master, string $id): ?self # {{{
  {
    # prepare
    static $DESC = [['pipe','r'],['pipe','w']];# STDIN/OUT
    static $OPTS = [
      'suppress_errors' => false,
      'bypass_shell'    => true,
      'blocking_pipes'  => true,
      'create_process_group' => true,
      #'create_process_group' => false,
      'create_new_console'   => false,
    ];
    $bot  = $master->bot;
    $log  = $master->log->new($id);
    $cmd  = $master->cmd.$id;
    $pipe = null;
    $home = $bot->dir->home;
    $out  = $bot->dir->data."$id.proc";
    $log->info('start');
    try
    {
      # execute
      if (($proc = proc_open($cmd, $DESC, $pipe, $home, null, $OPTS)) === false ||
          !is_resource($proc))
      {
        throw BotError::text("proc_open($cmd) failed");
      }
      # initiate sync protocol
      # set master lockfile
      if (!touch($out)) {
        throw BotError::text("touch($out) failed");
      }
      if (fwrite($pipe[0], $out) === false) {
        throw BotError::text('fwrite() failed');
      }
      # wait
      while (file_exists($out))
      {
        usleep(200000);
        if (!($a = proc_get_status($proc)) || !$a['running']) {
          throw BotError::text('exited('.$a['exitcode'].')');
        }
      }
      # get slave lockfile
      stream_set_blocking($pipe[1], true);
      if (($in = fread($pipe[1], 300)) === false) {
        throw BotError::text('fread() failed');
      }
      if (!file_exists($in)) {
        throw BotError::text("file not found: $in");
      }
      # unlock slave
      @unlink($in);
    }
    catch (\Throwable $e)
    {
      # report
      $log->exception($e);
      # cleanup
      if (file_exists($out)) {
        @unlink($out);
      }
      if ($pipe)
      {
        is_resource($pipe[0]) && fclose($pipe[0]);
        if (is_resource($pipe[1]))
        {
          ($a = fread($pipe[1], 2000)) && fwrite(STDOUT, $a);
          fclose($pipe[1]);
        }
      }
      return null;
    }
    # construct
    return new self($log, $proc, [$in, $out], $pipe);
  }
  # }}}
  function __construct(# {{{
    public object $log,
    public object $proc,
    public array  $lock,
    public array  $pipe
  ) {}
  # }}}
  function check(): bool # {{{
  {
    # read slave output
    $in = $this->lock[0];
    while (file_exists($in))
    {
      @unlink($in);
      if ($a = fread($this->pipe[1], 8000)) {
        fwrite(STDOUT, $a);
      }
    }
    # check process state
    if (!($a = proc_get_status($this->proc)) || !$a['running'])
    {
      $this->log->info('exited('.$a['exitcode'].')');
      return false;
    }
    return true;
  }
  # }}}
  function destruct(): void # {{{
  {
    # prepare
    $pipe = $this->pipe;
    # check
    if ($this->check())
    {
      # process is still running,
      # send termination command
      $this->log->info('stop');
      fwrite($pipe[0], 'stop');
      touch($out = $this->lock[1]);
      # wait
      while (file_exists($out)) {
        usleep(200000);
      }
      while (($a = proc_get_status($this->proc)) && $a['running']) {
        usleep(200000);
      }
      # read final output
      if (file_exists($a = $this->lock[0])) {
        @unlink($a);
      }
      if ($a = fread($pipe[1], 8000)) {
        fwrite(STDOUT, $a);
      }
    }
    # cleanup
    fclose($pipe[0]);
    fclose($pipe[1]);
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
    # initiate sync protocol
    # get master lockfile
    stream_set_blocking(STDIN, true);
    if (($in = fread(STDIN, 300)) === false)
    {
      $log->error('fread() failed');
      return null;
    }
    # check
    if (!file_exists($in))
    {
      $log->error("file not found: $in");
      return null;
    }
    # set slave lockfile
    if (!touch($out = $bot->dir->data.'proc.out'))
    {
      $log->error("touch($out) failed");
      return null;
    }
    if (fwrite(STDOUT, $out) === false)
    {
      $log->error("fwrite() failed");
      return null;
    }
    # unlock master
    @unlink($in);
    # wait
    for ($a = 0, $b = 10; $a < $b && file_exists($out); ++$a) {
      usleep(200000);
    }
    if ($a === $b)
    {
      @unlink($out);
      $log->error('master timed out');
      return null;
    }
    # complete
    return new self($log, [$in, $out]);
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
  function __construct(
    public ?object  $bot,
    public ?object  $data
  ) {}
  public $log,$item,$func,$args;
  function result(): bool # {{{
  {
    return $this->finit($this->init($this->bot->user));
  }
  # }}}
  function init(object $user): bool # {{{
  {
    # attach logger
    $this->log = $user->log->newObject($this);
    # parse data and initialize user
    if (!$this->parse() || !$user->init()) {
      return false;
    }
    # check item
    if ($item = $this->item)
    {
      # check common function
      switch ($this->func) {
      case 'up':
        # climb up the tree (replace item)
        if ($item->parent)
        {
          $this->item = $item->parent;
          $this->func = '';
          $this->args = $item->id;
          break;
        }
        # fallthrough..
      case 'close':
        # remove item from the view and complete
        return $this->complete($item->delete());
      }
    }
    # complete
    return $this->reply();
  }
  # }}}
  function finit(bool $ok): bool # {{{
  {
    # cleanup
    $this->bot = $this->data =
    $this->log = $this->item = null;
    # complete
    return $ok;
  }
  # }}}
  abstract function parse(): bool;
  abstract function reply(): bool;
  function complete(bool $ok): bool {
    return $ok;
  }
}
# }}}
class BotRequestInput extends BotRequest # {{{
{
  function parse(): bool # {{{
  {
    return false;
  }
  # }}}
  function reply(): bool # {{{
  {
    # prepare
    $msg  = $this->data;
    $bot  = $this->bot;
    $user = $bot->user;
    # ...
    # wipe user input or group input if it was consumed
    #$this['wipeInput'] && (!$user->isGroup || $this->item) &&
    #$bot->api->deleteMessage($msg);
    # ...
    # done
    return true;
    /***
    $text = $this->msg->text;
    # check current (first) root accepts input
    if (count($root = $user->config->roots) &&)
    {
      # handle input
      if (!($item = $user->command($text, true)) ||
          !$user->send($item))
      {
        $bot->logError('command failed: '.$text);
        $bot->cfg->replyFailedCommand && $bot->api->send('sendMessage', [
          'chat_id' => $this->chat->id,
          'text'    => $user->messages[1].': '.$text,
        ]);
      }
    }
    /***/
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
      # report and wipe incorrect
      $this->log->warnInput(substr($msg->text, 0, 200));
      $this['wipeInput'] && !$user->isGroup && $bot->api->deleteMessage($msg);
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
  function reply(): bool # {{{
  {
    return $this->complete($this->item
      ? $this->item->create($this)
      : $this->replyGlobal()
    );
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
  function complete(bool $ok): bool # {{{
  {
    # prepare
    $bot = $this->bot;
    # wipe at success or in private
    $this['wipeInput'] && ($ok || !$bot->user->isGroup) &&
    $bot->api->deleteMessage($this->data);
    # complete
    return $ok;
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
  function reply(): bool # {{{
  {
    return $this->complete($this->item->update($this));
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
    # check not replied
    if (!$this['replyFast'])
    {
      if ($ok) {
        $this->replyNop();
      }
      else
      {
        # TODO: report failures to the user
      }
    }
    return $ok;
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
  function reply(): bool # {{{
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
  function reply(): bool # {{{
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
  function reply(): bool # {{{
  {
    # prepare
    $data = $this->data;
    $chat = $this->bot->user->chat;
    # update another user
    if ($this->args) {
      return $chat->update($data);
    }
    # update self
    switch ($data->new_chat_member->status) {
    case 'kicked':
      return $chat->kicked($data);
    }
    # skip
    return false;
  }
  # }}}
}
# }}}
# user (subject)
class BotUser implements \ArrayAccess # {{{
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
  }
  # }}}
  function init(): bool # {{{
  {
    return (
      ($this->cfg = BotUserConfig::construct($this)) &&
      ($this->chat->init())
    );
  }
  # }}}
  function destruct(bool $ok = false): void # {{{
  {
    if ($this->bot)
    {
      $this->cfg?->destruct($ok);
      $this->bot  = $this->log = $this->chat =
      $this->text = $this->cfg = null;
    }
  }
  # }}}
  # [BotItemMessages] access  {{{
  function offsetExists(mixed $item): bool {
    return true;
  }
  function offsetGet(mixed $item): mixed
  {
    if (is_object($item))
    {
      # search by item and item's root
      foreach ($this->cfg->queue as $m)
      {
        if ($m->item === $item || $m->item->root === $item->root) {
          return $m;
        }
      }
    }
    else
    {
      # search by message identifier
      foreach ($this->cfg->queue as $m)
      {
        foreach ($m->list as $msg)
        {
          if ($msg->id === $item) {
            return $m;
          }
        }
      }
    }
    return null;
  }
  function offsetSet(mixed $item, mixed $msgs): void
  {
    # search and replace
    foreach ($this->cfg->queue as $i => $m)
    {
      if ($m->item === $item || $m->item->root === $item->root)
      {
        $this->cfg->queue[$i] = $msgs;
        $this->cfg->changed = true;
        return;
      }
    }
    # not found, add new
    array_unshift($this->cfg->queue, $msgs);
    $this->cfg->changed = true;
  }
  function offsetUnset(mixed $item): void
  {
    # search and remove
    foreach ($this->cfg->queue as $i => $m)
    {
      if ($m->item === $item || $m->item->root === $item->root)
      {
        array_splice($this->cfg->queue, $i, 1);
        $this->cfg->changed = true;
        break;
      }
    }
  }
  # }}}
}
# }}}
class BotUserConfig # {{{
{
  const FILE_JSON = 'config.json';
  static function construct(object $user): ?self # {{{
  {
    # determine locking method
    $k = $user->isGroup
      ? strval($user->id) # force retry
      : '';
    # aquire a lock
    if (!BotFile::lock($file = $user->dir.self::FILE_JSON, $k))
    {
      $user->log->error("failed to lock: $file");
      return null;
    }
    # prepare
    $queue   = [];
    $items   = [];
    $changed = false;
    $bot     = $user->bot;
    # load configuration data
    # format: [[queue],[item:data]]
    if ($data = $bot->file->getJSON($file))
    {
      # construct messages queue
      foreach ($data[0] as &$v)
      {
        if ($k = BotItemMessages::load($bot, $v)) {
          $queue[] = $k;
        }
        else {
          $changed = true;
        }
      }
      # refine items data map
      foreach ($data[1] as $k => &$v)
      {
        if (isset($bot->cmd[$k])) {
          $items[$k] = $v;
        }
        else
        {
          $user->log->warn("item not found: $k");
          $changed = true;
        }
      }
    }
    # construct
    return new self($user, $file, $queue, $items, $changed);
  }
  # }}}
  function __construct(# {{{
    public ?object  $user,
    public string   $file,
    public array    &$queue,
    public array    &$items,
    public bool     $changed
  ) {}
  # }}}
  function destruct(bool $save): void # {{{
  {
    if ($this->user)
    {
      if ($save && $this->changed)
      {
        $this->user->bot->file->setJSON($this->file, [
          $this->queue,
          $this->items
        ]);
      }
      BotFile::unlock($this->file);
      $this->user = null;
    }
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
# item (rendering)
abstract class BotItem implements \ArrayAccess, \JsonSerializable # {{{
{
  public $root,$id,$text,$caps,$items,$log,$cfg,$data;
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
  function init(object $user, ?object $q = null): ?array # {{{
  {
    # initialize
    # attach user language
    $this->text->lang = $user->lang;
    # create logger
    $this->log = $user->log->new($this->skel['path']);
    # attach user's item configuration
    $this->cfg = $user->cfg;
    if (!isset($this->cfg->items[$this->id])) {
      $this->cfg->items[$this->id] = [];
    }
    # check no query
    if (!$q) {
      return null;
    }
    # attach data
    if ($a = $this->skel['datafile'] ?? 0)
    {
      # determine storage source
      $a = ($a === 1)
        ? $user->dir
        : $this->bot->dir->data;
      $a = $a.$this->id.'.json';
      # load and attach
      $this->data = $this->bot->file->getJSON($a);
    }
    # render and complete
    return $this->render($q);
  }
  # }}}
  function finit(bool $ok): bool # {{{
  {
    # detach data
    if ($ok && ($a = $this->skel['datafile'] ?? 0) &&
        $this->data !== null)
    {
      # determine destination
      $a = ($a === 1)
        ? $this->bot->user->dir
        : $this->bot->dir->data;
      $a = $a.$this->id.'.json';
      # store
      $this->bot->file->setJSON($a, $this->data);
    }
    # cleanup
    $this->log  = $this->cfg =
    $this->data = null;
    # complete
    return $ok;
  }
  # }}}
  function markup(array &$mkup, ?array &$flags = null): string # {{{
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
            'text' => $bot->tp->render($c, $e),
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
  # [BotUserConfig] access  {{{
  function offsetExists(mixed $k): bool {
    return true;
  }
  function offsetGet(mixed $k): mixed {
    return $this->cfg->items[$this->id][$k] ?? null;
  }
  function offsetSet(mixed $k, mixed $v): void
  {
    $this->cfg->items[$this->id][$k] = $v;
    $this->cfg->changed = true;
  }
  function offsetUnset(mixed $k): void
  {}
  # }}}
  function create(object $q): bool # {{{
  {
    try
    {
      # render and send new messages
      if (!($new = $this->init($user = $this->bot->user, $q)) ||
          !($new = BotItemMessages::send($this, $new)))
      {
        throw BotError::skip();
      }
      # remove previous messages of the same root
      if ($old = $user[$this]?->delete()) {
        unset($user[$this]);
      }
      # store new
      $user[$this] = $new;
    }
    catch (\Throwable $e)
    {
      $this->log?->exception($e);
      return $this->finit(false);
    }
    $this->log->info($old ? 'refreshed' : 'created');
    return $this->finit(true);
  }
  # }}}
  function update(object $q): bool # {{{
  {
    try
    {
      # render new messages
      if (!($new = $this->init($user = $this->bot->user, $q))) {
        throw BotError::skip();
      }
      # get current messages of the same root
      if (!($old = $user[$this]))
      {
        # no current, send new
        if (!($new = BotItemMessages::send($this, $new))) {
          throw BotError::skip();
        }
        # update configuration
        $user[$this] = $new;
        # report
        $this->log->info('created');
      }
      elseif ($old->compatible($new))
      {
        # may be edited, edit
        if (($i = $old->edit($this, $new)) === 0)
        {
          $this->log->warn('not updated');
          throw BotError::skip();
        }
        # update configuration
        $user->cfg->changed = true;
        # report
        $this->log->info("updated($i)");
      }
      else
      {
        # may not be edited, send new
        if (!($new = BotItemMessages::send($this, $new))) {
          throw BotError::skip();
        }
        # remove current
        $old->delete();
        # update configuration
        unset($user[$this]);
        $user[$this] = $new;
        # report
        $this->log->info('refreshed');
      }
    }
    catch (\Throwable $e)
    {
      $this->log?->exception($e);
      return $this->finit(false);
    }
    return $this->finit(true);
  }
  # }}}
  function delete(): bool # {{{
  {
    # prepare
    $user = $this->bot->user;
    $id   = $this->id;
    # get messages of the same root
    if (!($msgs = $user[$this]))
    {
      $user->log->warn(__FUNCTION__."($id): no messages found");
      return false;
    }
    # check item is not the owner and not the root
    if (($item = $msgs->item) !== $this && $this->parent)
    {
      $user->log->warn(__FUNCTION__."($id): messages belong to /".$item->id);
      return false;
    }
    # select and initialize item without rendering
    ($item = $msgs->item)->init($user);
    # delete messages
    if (!$msgs->delete())
    {
      $item->log->warn('not deleted');
      return $item->finit(false);
    }
    # update configuration
    unset($user[$item]);
    # complete
    $item->log->info('deleted');
    return $item->finit(true);
  }
  # }}}
  abstract function render(object $q): ?array;
}
# }}}
class BotItemText implements \ArrayAccess # {{{
{
  public $text,$lang = 'en';
  function __construct(public object $item)
  {
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
    $bot  = $this->item->bot;
    $lang = $this->lang;
    # check local
    if (isset($this->text[$lang]['#'])) {
      return $bot->tp->render($this->text[$lang]['#'], $o);
    }
    # check global
    $t = $this->item->skel['type'];
    if (isset($bot->text->texts[$lang][$t])) {
      return $bot->tp->render($bot->text->texts[$lang][$t], $o);
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
    return $this->caps[$k] ?? $this->botCaps[$k];
  }
  function offsetSet(mixed $k, mixed $v): void
  {}
  function offsetUnset(mixed $k): void
  {}
}
# }}}
class BotItemMessages implements \JsonSerializable # {{{
{
  static function load(object $bot, array &$data): ?self # {{{
  {
    # get command item
    if (!($item = $bot->cmd[$data[0]])) {
      return null;
    }
    # construct messages
    foreach ($data[2] as &$msg) {
      $msg = new $msg[0]($bot, $msg[1], $msg[2]);
    }
    # complete
    return new self($item, $data[1], $data[2]);
  }
  # }}}
  static function send(object $item, array $list): ?self # {{{
  {
    # iterate and send messages
    foreach ($list as $msg)
    {
      if (!$msg->send()) {
        return null;
      }
    }
    # construct and complete
    return new self($item, time(), $list);
  }
  # }}}
  function __construct(# {{{
    public object  $item, # BotItem
    public int     $time, # item's creation timestamp
    public array   $list = []
  ) {}
  # }}}
  function jsonSerialize(): array # {{{
  {
    return [$this->item->id, $this->time, $this->list];
  }
  # }}}
  function compatible(array &$list): bool # {{{
  {
    # check current messages are fresh enough
    if ((time() - $this->time) > 0.8 * Bot::MESSAGE_LIFETIME) {
      return false;
    }
    # check count is equal or more (reduceable)
    if (($a = count($this->list)) < ($b = count($list))) {
      return false;
    }
    # check message types
    $a = $b - $a + 1;
    $i = -1;
    while (++$i < $a)
    {
      for ($j = $i, $k = 0; $k < $b; ++$k)
      {
        if ($this->list[$j]::class !== $list[$k]::class) {
          break;
        }
      }
      if ($k === $b) {
        return true;
      }
    }
    return false;
  }
  # }}}
  function edit(object $item, array &$list): int # {{{
  {
    # prepare
    $a = count($this->list);
    $b = count($list);
    $c = 0;
    # delete first messages which type doesnt match
    if ($a > $b)
    {
      for ($i = 0; $i < $a; ++$i)
      {
        if ($this->list[$i]::class === $list[$i]::class) {
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
      if ($this->list[$i]->hash === $list[$i]->hash) {
        continue;
      }
      $this->list[$i]->edit($list[$i]) && $c++;
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
      $this->data['file'].$this->data['text'].$this->data['markup']
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
# item types
class BotImgItem extends BotItem # {{{
{
  function render(object $q): ?array # {{{
  {
    # prepare
    $bot = $this->bot;
    $msg = [];
    /***
    # invoke custom handler
    if (($msg = $this->skel['handler']) &&
        ($msg = $msg($this)) === null)
    {
      return null;
    }
    else {
      $msg = [];
    }
    /***/
    # complete
    return $this->image($msg);
  }
  # }}}
  function image(array &$msg): ?array # {{{
  {
    # prepare
    $bot = $this->bot;
    $cfg = $this->config(__METHOD__);
    # determine file name
    if (!isset($msg[$a = 'file'])) {
      $msg[$a] = $cfg[$a] ?: $bot->user->lang.'-'.$this->id.'.jpg';
    }
    $file = $msg[$a];
    # determine image content
    if (!isset($msg[$a = 'image']))
    {
      # check cache
      if (!($b = $bot->file->getId($file)))
      {
        if ($cfg['file'] || $cfg['lookup'])
        {
          # lookup file
          if (!($b = $bot->file->getImage($file))) {
            return null;
          }
          $b = BotApiFile::construct($b, false);
        }
        else
        {
          # generate title
          $b = $this->text['@'] ?: $this->skel['name'];
          if (!($b = $this->title($b))) {
            return null;
          }
        }
      }
      $msg[$a] = $b;
    }
    # determine text content
    if (!isset($msg[$a = 'text']))
    {
      $msg[$a] = ($b = $this->text['.'])
        ? $bot->tp->render($b, [])
        : '';
    }
    # determine markup
    if (!isset($msg[$a = 'markup']))
    {
      $msg[$a] = isset($this->skel[$a])
        ? $this->markup($this->skel[$a])
        : '';
    }
    # create single message and complete
    return [BotImgMessage::construct($bot, $msg)];
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
  function render(object $q): ?array # {{{
  {
    # prepare {{{
    $bot  = $this->bot;
    $cfg  = $this->config();
    $mkup = [];
    $msg  = [];
    $data = &$this->data;
    # refresh data
    if (($hand = $this->skel['handler']) &&
        (!($a = $cfg['timeout'])    ||
         !($b = $this['time'] ?? 0) ||
         ($c = time()) - $b > $a))
    {
      # fetch with handler
      if (($data = $hand($this)) === null)
      {
        $this->log->warn("$hand() failed");
        return null;
      }
      # set order
      self::sort($data, $cfg['order'], $cfg['desc']);
      # set update time
      $a && ($this['time'] = $c);
    }
    # determine page size and total records count
    $size = $cfg['rows'] * $cfg['cols'];
    $cnt  = $data ? count($data) : 0;
    # determine total page count
    if (!($total = intval(ceil($cnt / $size)))) {
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
    switch ($func = $q->func) {
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
    #case 'select':
    case 'open':
      # check identifier
      if (!($id = $q->args) || !ctype_alnum($id) || ($id = intval($id)) < 0)
      {
        $this->log->error("$func: incorrect id=$id");
        return null;
      }
      # locate item
      for ($i = 0; $i < $cnt; ++$i)
      {
        if ($data[$i]['id'] === $id) {
          break;
        }
      }
      # check not found
      if ($i === $cnt)
      {
        $this->log->warn("$func: id=$id not found");
        break;# refresh the list
      }
      # TODO
      # ...
      # ...
      break;
    case 'add':
    case 'create':
      break;
    default:
      $this->log->error("$func: unknown");
      return null;
    }
    # }}}
    # render markup {{{
    if ($cnt)
    {
      # non-empty,
      # add top controls
      $list = (isset($this->skel[$a = 'markup'][$b = 'head']))
        ? ($this->skel[$a][$b] ?? [])
        : $cfg[$a][$b];
      foreach ($list as &$c) {
        $mkup[] = $c;
      }
      # extract items from the data set and
      # create list markup
      $list = array_slice($data, $page * $size, $size);
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
      'cnt'   => $cnt,
      'page'  => 1 + $page,
      'total' => $total,
    ]);
    # }}}
    # store {{{
    /***
    if ($page !== $conf['page'])
    {
      $conf['page'] = $page;
      $bot->user->changed = true;
    }
    /***/
    # }}}
    # complete
    return $this->image($msg);
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
    # determine callback command
    if (($cmd = $cfg['cmd']) && $cmd[0] !== '/') {
      $cmd = '/'.$this->id.$cmd;
    }
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
          $d = $cmd
            ? $cmd.' '.$list[$i]['id']
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
#####
# TODO: check old message callback action does ZAP? or.. PROPER UPDATE?!
# TODO: check file_id stores oke
#####
class BotFormItem extends BotItem # {{{
{
  function render(object $q): ?array # {{{
  {
    # prepare {{{
    # get current state and field index
    if (!array_key_exists('index', $conf))
    {
      $conf['index'] = 0;
      $conf['state'] = 0;
      $user->changed = true;
    }
    $index = $conf['index'];
    $state = $conf['state'];
    $stateInfo = array_key_exists('info', $conf)
      ? $conf['info']
      : [0, ''];
    # get language specific options list
    $options = [];
    if (array_key_exists('options', $item))
    {
      # static options
      $options = $item['options'];
      $options = array_key_exists($lang, $options)
        ? $options[$lang]
        : $options['en'];
    }
    elseif (false)
    {
      # dynamic options
      # TODO: ...
    }
    # determine fields count
    $count = count($item['fields']);
    # determine field name
    $indexField = ($state === 0)
      ? array_keys($item['fields'])[$index]
      : '';
    # determine count of empty required fields
    $emptyRequired = 0;
    foreach ($item['fields'] as $a => $b)
    {
      if (($b[0] & 1) && !array_key_exists($a, $data)) {
        ++$emptyRequired;
      }
    }
    # }}}
    # handle input {{{
    if ($inputLen)
    {
      switch ($state) {
      case 0:
        # execute input command {{{
        if ($inputLen === 1)
        {
          switch ($input) {
          case '-':
            # wipe current field value
            if (!array_key_exists($indexField, $data)) {
              $error = 1;return $item;# NOP
            }
            unset($data[$indexField]);
            $item['dataChanged'] = true;
            break 2;
          case '>':
            # advance to the next field but don't cycle around
            if (++$index === $count) {
              $error = 1;return $item;# NOP
            }
            break 2;
          case '<':
            # retreat to the previous field but don't cycle around
            if (--$index < 0) {
              $error = 1;return $item;# NOP
            }
            break 2;
          }
        }
        # }}}
        # set field value {{{
        # refine by type
        $a = $item['fields'][$indexField];
        switch ($a[1]) {
        case 'string':
          $input = substr($input, 0, $a[2]);
          break;
        case 'int':
          $input = intval(substr($input, 0, 16));
          if ($input < $a[2]) {
            $input = $a[2];
          }
          elseif ($input > $a[3]) {
            $input = $a[3];
          }
          break;
        case 'list':
          # check list index specified correctly [1..N]
          if (!ctype_digit($input) ||
              ($a = intval($input)) <= 0 ||
              $a > count($options[$indexField]))
          {
            $error = 1; return $item;# NOP
          }
          # select value from the list
          $input = $options[$indexField][$a];
          break;
        }
        # check not changed
        if (array_key_exists($indexField, $data) &&
            $data[$indexField] === $input)
        {
          $error = 1;return $item;# NOP
        }
        # update
        $data[$indexField] = $input;
        $item['dataChanged'] = true;
        # advance to the next field (if option specified)
        #if (array_key_exists('submitOnInput', $item) &&
        if (array_key_exists('submitOnInput', $item) &&
            $item['submitOnInput'] &&
            ++$index === $count)
        {
          # last field completes input,
          # change form state
          $state = 1;
        }
        # }}}
        break;
      case 2:
        # set final required field {{{
        # check first
        if ($emptyRequired !== 1)
        {
          # NOP, more than one empty required,
          # this is assumed as non-deterministic state,
          # no errors either..
          $error = 1;
          return $item;
        }
        # search field name
        foreach ($item['fields'] as $a => $b)
        {
          if (($b[0] & 1) && !array_key_exists($a, $data)) {
            break;
          }
        }
        # accept input and store data
        $data[$a] = $input;
        $item['dataChanged'] = true;
        # form is now filled (complete),
        # change its state
        $state = 1;
        $index = $count;
        # }}}
        break;
      }
    }
    # }}}
    # handle function {{{
    switch ($func) {
    case 'prev': # input index backward {{{
      if ($state !== 0) {
        $error = 1;return $item;
      }
      if (--$index < 0)
      {
        if (array_key_exists('inputMoveAround', $item) &&
            $item['inputMoveAround'])
        {
          $index = $count - 1;
        }
        else
        {
          $error = 1;
          return $item;
        }
      }
      break;
    # }}}
    case 'next': # input index forward {{{
      if ($state !== 0) {
        $error = 1;return $item;
      }
      if (++$index === $count)
      {
        if (array_key_exists('inputMoveAround', $item) &&
            $item['inputMoveAround'])
        {
          $index = 0;
        }
        else
        {
          $error = 1;
          return $item;
        }
      }
      break;
    # }}}
    case 'refresh': # form refresher {{{
      switch ($state) {
      case 1:
      case 4:
      case 5:
        # reset filled, failed and completed form states,
        # clear all non-persistent fields and
        # set input to the first one
        $a = -1;
        $b = 0;
        foreach ($item['fields'] as $c => $d)
        {
          if (!($d[0] & 2))
          {
            if (!~$a) {
              $a = $b;
            }
            if (array_key_exists($c, $data))
            {
              unset($data[$c]);
              $item['dataChanged'] = true;
            }
          }
          ++$b;
        }
        # set input index to the first non-persistent field and
        # reset completed form state
        if (~$a)
        {
          $index = $a;
          $state = 0;
          break;
        }
        # all the fields are persistent,
        # preserve data and find first required from the end
        $state = 0;
        $index = $count - 1;
        foreach (array_reverse($item['fields']) as $a => $b)
        {
          if ($b & 1) {
            break;
          }
          --$index;
        }
        break;
      case 2:
        # required field missing,
        # locate the index of the first empty required,
        $index = 0;
        foreach ($item['fields'] as $a => $b)
        {
          if (($b[0] & 1) && !array_key_exists($a, $data)) {
            break;
          }
          ++$index;
        }
        # check not found
        if ($index === $count)
        {
          # this is a rare case occurse when command structure changes and
          # don't cataches with previous state,
          # because of all the required are filled,
          # complete form input
          $state = 1;
        }
        else
        {
          # refreshing resets form into input state
          $state = 0;
        }
        break;
      case 3:
        # this is a positive waiting state,
        # refreshing doesn't do anything here
        # because it's the job of the item's task
        $error = 1;return $item;# NOP
      case 6:
        # TODO: errors display state
        $error = 1;return $item;# NOP for now
      }
      break;
    # }}}
    case 'select': # input selector {{{
      if ($state !== 0 || !$args ||
          ($a = intval($args[0])) < 0 ||
          ($a &&
            (!array_key_exists($indexField, $options) ||
            !($b = $options[$indexField]) ||
            $a > count($b))))
      {
        $this->logError("failed to select field value");
        $error = 1;
        return $item;
      }
      # check same value selected
      if ($a && array_key_exists($indexField, $data) &&
          $data[$indexField] === $b[$a])
      {
        # NOP when required
        if ($item['fields'][$indexField][0] & 1)
        {
          $error = 1;
          return $item;
        }
        # de-select (set empty)
        $a = 0;
      }
      # change data
      if ($a)
      {
        # correct integer type
        $data[$indexField] = ($item['fields'][$indexField][1] === 'int')
          ? intval($b[$a])
          : $b[$a];
      }
      elseif (array_key_exists($indexField, $data)) {
        unset($data[$indexField]);
      }
      $item['dataChanged'] = true;
      # advance field index (if option set)
      if (array_key_exists('submitOnSelect', $item) &&
          $item['submitOnSelect'])
      {
        ++$index;
      }
      break;
    # }}}
    case 'last': # submit input {{{
      if ($state !== 0 || $index === $count) {
        $error = 1; return $item;# NOP
      }
      # TODO: autofill
      # set final index value
      $index = $count;
      # check confirmation required
      if (array_key_exists('submitConfirm', $item) &&
          $item['submitConfirm'])
      {
        break;
      }
      # check any required fields miss
      if ($emptyRequired > 0) {
        break;
      }
      # continue to form submittion..
      $state = 1;
    # }}}
    case 'ok': # submit form {{{
      # check mode is correct
      if (!in_array($state, [1,4])) {
        $error = 1;return $item;# NOP guard
      }
      # create task
      if (!$this->taskAttach($item, $data, true))
      {
        $error = 1;
        return $item;
      }
      # change form state
      $state = 3;
      break;
    # }}}
    case 'progress': # form progress {{{
      if ($state !== 3 || !$args) {
        $error = 1;return $item;# NOP guard
      }
      $stateInfo[0] = intval($args[0]);
      break;
    # }}}
    case 'done': # form result {{{
      if (!in_array($state,[1,4,3]) || !$args)
      {
        $error = "incorrect state ($state) or no args";
        return $item;
      }
      $state = intval($args[0]) ? 5 : 4;
      $stateInfo = [1, base64_decode($args[1])];
      break;
    # }}}
    case 'first': # form reset {{{
      # make sure in the correct state
      if ($state < 4 && $state !== 2)
      {
        $error = 'form reset is only possible after completion or failure';
        return $item;
      }
      # wipe data
      $data = [];
      $item['dataChanged'] = true;
      # reset input index and state
      $state = $index = 0;
      break;
    # }}}
    case '':
      # SKIP
      break;
    default:
      # NOP error
      $this->logError("unknown form function: $func");
      $error = $this->messages[$lang][2];
      return $item;
    }
    # }}}
    # sync changes {{{
    if ($index !== $conf['index'] ||
        $state !== $conf['state'])
    {
      # set flag
      $user->changed = true;
      # check input completion state
      if ($state === 0 && $index === $count)
      {
        # resolve to completed (optimistic)
        $state = 1;
        # check all required fields are set
        foreach ($item['fields'] as $a => $b)
        {
          if (($b[0] & 1) &&
              !array_key_exists($a, $data))
          {
            # required missing!
            $state = 2;
            break;
          }
        }
      }
      # reset state info
      if ($state < 4) {
        $stateInfo = [0,''];
      }
    }
    # re-determine current/prev/next field names
    $indexField = '';
    $nextField  = $prevField = '';
    if ($state === 0)
    {
      $a = array_keys($item['fields']);
      $indexField = $a[$index];
      # determine localized captions
      if ($count > 1)
      {
        $nextField  = ($index + 1 < $count)
          ? $a[$index + 1]
          : $a[0];
        $prevField  = ($index - 1 >= 0)
          ? $a[$index - 1]
          : $a[$count - 1];
        $nextField = $text[$nextField];
        $prevField = $text[$prevField];
      }
    }
    # determine last field flag
    $indexIsLast = ($index === $count - 1);
    # re-determine count of empty required fields
    if ($item['dataChanged'])
    {
      $emptyRequired = 0;
      foreach ($item['fields'] as $a => $b)
      {
        if (($b[0] & 1) && !array_key_exists($a, $data)) {
          ++$emptyRequired;
        }
      }
    }
    # }}}
    # render markup {{{
    $mkup = [];
    # compose input selector group {{{
    if ($state === 0 && $indexField &&
        array_key_exists($indexField, $options))
    {
      # get value options and column count
      $b = $options[$indexField];
      $c = $b[0];
      $b = array_slice($b, 1);
      # determine row count
      $d = ceil(count($b) / $c);
      # create select group
      for ($i = 0, $k = 0; $i < $d; ++$i)
      {
        # create row
        for ($j = 0, $e = []; $j < $c; ++$j)
        {
          # create option
          if ($k < count($b))
          {
            # determine current or standard
            $n = (array_key_exists($indexField, $data) &&
                  !strcmp($data[$indexField], $b[$k]))
              ? 'select1'
              : 'select0';
            # parse it and add to row
            $e[] = [
              'text' => $this->tp->render(
                $this->buttons[$n],
                [
                  'index' => ($k + 1),
                  'text'  => $b[$k],
                ]
              ),
              'callback_data' => '/'.$item['id'].'!select '.($k + 1)
            ];
          }
          else
          {
            # empty pad
            $e[] = ['text'=>' ','callback_data'=>'!'];
          }
          ++$k;
        }
        # add row
        $mkup[] = $e;
      }
    }
    # }}}
    # compose input value display {{{
    while ($state === 0 && $indexField)
    {
      # check empty
      if (!array_key_exists($indexField, $data))
      {
        # display empty bar
        $mkup[] = [['text'=>' ','callback_data'=>'!']];
        break;
      }
      # check options list
      $a = $data[$indexField];
      if (array_key_exists($indexField, $options))
      {
        # check value is already shown in the list
        $b = array_slice($options[$indexField], 1);
        if (!is_string($a) && is_string($b[0])) {
          $a = strval($a);# avoid comparison problem
        }
        if (array_search($a, $b, true) !== false)
        {
          # display empty bar
          $mkup[] = [['text'=>' ','callback_data'=>'!']];
          break;
        }
      }
      # display non-empty value
      $b = $this->tp->render($this->buttons['select1'], ['text'=>$a]);
      $a = !($item['fields'][$indexField][0] & 1)
        ? '/'.$item['id'].'!select 0' # with de-selector
        : '!';
      $mkup[] = [['text'=>$b,'callback_data'=>$a]];
      break;
    }
    # }}}
    # compose controls {{{
    # select markup
    $a = $item['markup'];
    $b = 'S'.$state;
    if (array_key_exists($b, $a)) {
      $c = $a[$b];
    }
    elseif ($state === 4 || $state === 6) {
      $c = $a['S1'];
    }
    elseif ($state === 5) {
      $c = $a['S2'];
    }
    else {
      $c = [['_up']];
    }
    # determine controls
    $d = [
      'first' => $this->messages[$lang][15],# reset
    ];
    $d['refresh'] = ($state === 5)
      ? $this->messages[$lang][18] # repeat
      : $this->messages[$lang][14];# refresh
    if ($state === 0 && $indexField)
    {
      $d['prev'] = $index
        ? $this->messages[$lang][16]
        : '';
      if ($indexIsLast)
      {
        if ($emptyRequired &&
            (!array_key_exists('noRequiredSubmit', $item) ||
              $item['noRequiredSubmit']))
        {
          # display empty bar
          $d['last'] = '';
        }
        else
        {
          # standard
          $d['last'] = $this->messages[$lang][12];
        }
      }
      elseif (($item['fields'][$indexField][0] & 1) &&
              !array_key_exists($indexField, $data) &&
              (!array_key_exists('noRequiredSkip', $item) ||
                $item['noRequiredSkip']))
      {
        # non-last, empty but required,
        # display empty bar
        $d['next'] = '';
      }
      else
      {
        # standard
        $d['next'] = $this->messages[$lang][17];
      }
    }
    # render
    $c = $this->itemInlineMarkup($item, $c, $text, $d);
    foreach ($c as $a) {
      $mkup[] = $a;
    }
    # }}}
    $item['inlineMarkup'] = $mkup;
    # }}}
    # render content {{{
    # compose input fields
    $fields = [];
    $i = 0;
    foreach ($item['fields'] as $a => $b)
    {
      # determine type hint
      switch ($b[1]) {
      case 'string':
        $c = $this->messages[$lang][7];
        $c = $this->tp->render($c, ['max'=>$b[2]]);
        break;
      case 'int':
        $c = $this->messages[$lang][8];
        $c = $this->tp->render($c, ['min'=>$b[2],'max'=>$b[3]]);
        break;
      case 'list':
        $c = $this->messages[$lang][13];
        break;
      default:
        $c = '.';
        break;
      }
      # determine value
      $d = array_key_exists($a, $data)
        ? $data[$a]
        : '';
      # add form field descriptor
      $fields[] = [
        'name' => (array_key_exists($a, $text)
          ? $text[$a]
          : $a),
        'value'    => $d,
        'valueLen' => strlen($d),
        'before'   => ($index > $i),
        'current'  => ($index === $i),
        'after'    => ($index < $i),
        'required' => ($b[0] & 1),
        'hint'     => $c,
      ];
      $i++;
    }
    # get custom or common template
    $a = array_key_exists('*', $text)
      ? $text['*']
      : $this->messages[$lang][6];
    # get description
    $b = array_key_exists('.', $text)
      ? preg_replace('/\n\s*/m', ' ', trim($text['.']))
      : '';
    # compose full text
    $item['textContent'] = $this->render_content($a, [
      'desc'   => $b,
      'fields' => $fields,
      'info'   => $stateInfo,
      's0' => ($state === 0),
      's1' => ($state === 1),
      's2' => ($state === 2),
      's3' => ($state === 3),
      's4' => ($state === 4),
      's5' => ($state === 5),
      's6' => ($state === 6),
      's1s3' => ($state === 1 || $state === 3),
      's3s4' => ($state === 3 || $state === 4),
      's3s5' => ($state === 3 || $state === 5),
      's3s4s5' => ($state === 3 || $state === 4 || $state === 5),
    ]);
    # }}}
    # set {{{
    $conf['index']  = $index;
    $conf['state']  = $state;
    $conf['info']   = $stateInfo;
    $item['isInputAccepted'] = (
      ($state === 0) ||
      ($state === 2 && $emptyRequired === 1)
    );
    # }}}
  }
  # }}}
}
# }}}
class BotTxtItem extends BotItem # {{{
{
  function render(object $q): ?array # {{{
  {
    return null;
  }
  # }}}
}
# }}}
?>
