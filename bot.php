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
      exit(3);
    }
    # enforce graceful termination
    register_shutdown_function(function() use ($bot) {
      # guard against non-recoverable (non-catched) errors
      error_get_last() && $bot->destruct();
    });
    if ($bot->isMaster && function_exists($e = 'sapi_windows_set_ctrl_handler'))
    {
      # WinOS: console breaks must stop masterbot
      $e(function (int $e) use ($bot) {
        $bot->destruct();
        exit(2);
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
        foreach ($u as $update)
        {
          if (!$bot->handle($update)) {
            break 2;
          }
        }
      }
    }
    catch (\Throwable $e) {
      $bot->log->exception($e);
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
      if (!($bot->text = BotTexts::construct($bot)) ||
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
      $this->user && $this->user->destruct();
      $this->proc && is_object($this->proc) && $this->proc->destruct();
      $this->api  && $this->api->destruct();
      $this->cfg  && $this->cfg->destruct();
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
  function handle(object $update): bool # {{{
  {
    # parse update and
    # construct specific request
    $q = $u = $c = null;
    if (isset($update->callback_query))
    {
      # {{{
      if (!isset(($q = $update->callback_query)->from))
      {
        $this->log->warn('incorrect callback');
        $this->log->dump($update);
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
          $this->log->dump($update);
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
    elseif (isset($update->inline_query))
    {
      # {{{
      if (!isset(($q = $update->inline_query)->from))
      {
        $this->log->warn('incorrect inline query');
        $this->log->dump($update);
        return true;
      }
      $u = $q->from;
      #$q = new BotRequestInline(this, $q);
      return true;
      # }}}
    }
    elseif (isset($update->message))
    {
      # {{{
      if (!isset(($q = $update->message)->from) ||
          !isset($q->chat))
      {
        $this->log->warn('incorrect message');
        $this->log->dump($update);
        return true;
      }
      $u = $q->from;
      $c = $q->chat;
      $q = (isset($q->text) && ($q->text[0] === '/'))
        ? new BotRequestCommand($this, $q)
        : new BotRequestInput($this, $q);
      # }}}
    }
    elseif (isset($update->my_chat_member))
    {
      # {{{
      if (!isset(($q = $update->my_chat_member)->from) ||
          !isset($q->chat) || !isset($q->date) ||
          !isset($q->old_chat_member) ||
          !isset($q->new_chat_member))
      {
        $this->log->warn('incorrect member update');
        $this->log->dump($update);
        return true;
      }
      $u = $q->from;
      $c = $q->chat;
      $q = new BotRequestChat($this, $q);
      # }}}
    }
    else
    {
      $this->log->warn('unknown update type');
      $this->log->dump($update);
      return true;
    }
    # construct user
    if (!($this->user = BotUser::construct($this, $u, $c))) {
      return true;
    }
    # get current status
    $status = $this->status;
    # reply and cleanup
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
    file_exists($a = $this->src.'img'.DIRECTORY_SEPARATOR) && ($this->img[] = $a);
    $a = 'font'.DIRECTORY_SEPARATOR;
    file_exists($b = $this->src.$a) && ($this->font[] = $b);
    file_exists($b = $this->inc.$a) && ($this->font[] = $b);
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
    'useFileIds'    => false,
    'useBreadcrumb' => true,
    'wipeUserInput' => true,
    #'replyFailedCommand' => false,
    #'replyIgnoredCallback' => true,
    'BotLog' => [
      'debug'     => true,
      'infoFile'  => '',
      'errorFile' => '',
    ],
    'BotImgItem' => [
      'color'  => [0,0,0],
      'size'   => [640,160],
      'header' => [
        [255,255,255],# white
        'Days.ttf',# font
        64,# maximal font size
        [140,360,0,160],# rect [x,w,y,h]
      ],
      'breadcrumb' => [
        [135,206,235],# skyblue
        'Bender-Italic.ttf',# font
        16,# exact font size
        [140,32],# coordinates
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
  const FILE_JSON = 'fids.json';
  public $log,$fids;
  function __construct(public object $bot) # {{{
  {
    $this->log = $bot->log->new('file');
  }
  # }}}
  function init(): bool # {{{
  {
    # load file identifiers map
    if ($this->bot->cfg->useFileIds)
    {
      $file = $this->bot->dir->data.self::FILE_JSON;
      if (($this->fids = $this->getJSON($file)) === null) {
        return false;
      }
    }
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
  function getFont(string $file): string # {{{
  {
    $bot = $this->bot;
    foreach ($bot->dir->font as $a)
    {
      if (file_exists($a.$file)) {
        return $a.$file;
      }
    }
    $bot->log->error("font not found: $file");
    return '';
  }
  # }}}
  function getImage(string $name): string # {{{
  {
    # prepare
    $file = $name.'.jpg';
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
    return '';
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
    if ($this->fids !== null)
    {
      $this->fids[$file] = $id;
      if (self::lock($file = $this->bot->dir->data.self::FILE_JSON))
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
  static function construct(string $file, bool $isTemp = true): self
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
class BotTexts # {{{
{
  const FILE_JSON = ['messages.json', 'buttons.json'];
  const FILE_INC  = ['messages.inc',  'buttons.inc'];
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
    # }}}
  ];
  static $MESSAGES = [
    'en' => [# {{{
      'play'  => 'play',
      'close' => 'close',
      0 => '{:no_entry_sign:} game not available',
      1 => '{:exclamation:} command failed',
      2 => '{:exclamation:} operation failed',
      3 => '{:exclamation:} not found',
      4 => 'add',
      5 => 'empty',
      6 => # FORM template {{{
      '
{{#desc}}
<i>Description:</i>{{br}}
{{desc}}{{br}}
{{br}}
{{/desc}}
<i>Parameters:</i>{{br}}
{{#fields}}
{{#s0}}
  {{#before}}
    {{#valueLen}}
      {:green_circle:} {{name}}: {{value}}
    {{/valueLen}}
    {{^valueLen}}
      {{#required}}{:yellow_circle:} {{/required}}
      {{^required}}{:green_circle:} {{/required}}
      {{name}} -
    {{/valueLen}}
  {{/before}}
  {{#current}}
    {:white_small_square:} <b>{{name}}: </b>
    {{#valueLen}}<code>&lt;</code>{{value}}<code>&gt;</code>{{/valueLen}}
    {{^valueLen}}<code>&lt;{{hint}}&gt;</code>{{/valueLen}}
  {{/current}}
  {{#after}}
    {:black_small_square:} {{name}}
    {{#valueLen}}: {{value}}{{/valueLen}}
  {{/after}}
  {{br}}
{{/s0}}
{{#s1}}
  {:green_circle:} {{name}}
  {{#valueLen}}: <b>{{value}}</b>{{/valueLen}}
  {{^valueLen}} -{{/valueLen}}
  {{br}}
{{/s1}}
{{#s2}}
  {{#required}}
    {{#valueLen}}{:green_circle:} {{/valueLen}}
    {{^valueLen}}{:yellow_circle:} {{/valueLen}}
  {{/required}}
  {{^required}}
    {:green_circle:} 
  {{/required}}
  {{#valueLen}}{{name}}: {{value}}{{/valueLen}}
  {{^valueLen}}{{name}} -{{/valueLen}}
  {{br}}
{{/s2}}
{{#s3s4s5}}
  {{#valueLen}}
    {:green_circle:} {{name}}: <b>{{value}}</b>
    {{br}}
  {{/valueLen}}
{{/s3s4s5}}
{{/fields}}
{{br}}
{{^s0}}
<i>Status:</i>{{br}}
{{/s0}}
{{#s1}}
{:blue_circle:} confirm operation
{{/s1}}
{{#s2}}
{:yellow_circle:} missing required parameter
{{/s2}}
{{#s3}}
{{^info.0}}{:blue_circle:} {{/info.0}}
{{#info.0}}{:purple_circle:} {{/info.0}}
processing{{#info.0}}..{{/info.0}}
{{/s3}}
{{#s4}}
{:red_circle:} <b>failure</b>{{#info.1}}: {{info.1}}{{/info.1}}
{{/s4}}
{{#s5}}
{:green_circle:} <b>complete</b>{{#info.1}}: {{info.1}}{{/info.1}}
{{/s5}}
{{br}}
      ',# }}}
      7 => 'string ({{max}})',
      8 => 'number [{{min}}..{{max}}]',
      11 => '{:exclamation:} task failed to start',
      12 => 'complete',
      13 => 'select option',
      14 => 'refresh',
      15 => 'reset',
      16 => 'previous',
      17 => 'next',
      18 => 'repeat',
      19 => '',
    ],
    # }}}
    'ru' => [# {{{
      'play'  => 'играть',
      'close' => 'закрыть',
      0 => '{:no_entry_sign:} игра не доступна',
      1 => '{:exclamation:} неверная комманда',
      2 => '{:exclamation:} сбой операции',
      3 => '{:exclamation:} не найдено',
      4 => 'добавить',
      5 => 'пусто',
      6 => # FORM template {{{
      '
{{#desc}}
<i>Описание:</i>{{br}}
{{desc}}{{br}}
{{br}}
{{/desc}}
<i>Параметры:</i>{{br}}
{{#fields}}
{{#s0}}
  {{#before}}
    {{#valueLen}}
      {:green_circle:} {{name}}: {{value}}
    {{/valueLen}}
    {{^valueLen}}
      {{#required}}{:yellow_circle:} {{/required}}
      {{^required}}{:green_circle:} {{/required}}
      {{name}} -
    {{/valueLen}}
  {{/before}}
  {{#current}}
    {:white_small_square:} <b>{{name}}: </b>
    {{#valueLen}}<code>&lt;</code>{{value}}<code>&gt;</code>{{/valueLen}}
    {{^valueLen}}<code>&lt;{{hint}}&gt;</code>{{/valueLen}}
  {{/current}}
  {{#after}}
    {:black_small_square:} {{name}}
    {{#valueLen}}: {{value}}{{/valueLen}}
  {{/after}}
  {{br}}
{{/s0}}
{{#s1}}
  {:green_circle:} {{name}}
  {{#valueLen}}: <b>{{value}}</b>{{/valueLen}}
  {{^valueLen}} -{{/valueLen}}
  {{br}}
{{/s1}}
{{#s2}}
  {{#required}}
    {{#valueLen}}{:green_circle:} {{/valueLen}}
    {{^valueLen}}{:yellow_circle:} {{/valueLen}}
  {{/required}}
  {{^required}}
    {:green_circle:} 
  {{/required}}
  {{#valueLen}}{{name}}: {{value}}{{/valueLen}}
  {{^valueLen}}{{name}} -{{/valueLen}}
  {{br}}
{{/s2}}
{{#s3s4s5}}
  {{#valueLen}}
    {:green_circle:} {{name}}: <b>{{value}}</b>
    {{br}}
  {{/valueLen}}
{{/s3s4s5}}
{{/fields}}
{{br}}
{{^s0}}
<i>Статус:</i>{{br}}
{{/s0}}
{{#s1}}
{:blue_circle:} подтвердите действие
{{/s1}}
{{#s2}}
{:yellow_circle:} не задан обязательный параметр
{{/s2}}
{{#s3}}
{{^info.0}}{:blue_circle:} {{/info.0}}
{{#info.0}}{:purple_circle:} {{/info.0}}
обработка{{#info.0}}..{{/info.0}}
{{/s3}}
{{#s4}}
{:red_circle:} <b>ошибка</b>{{#info.1}}: {{info.1}}{{/info.1}}
{{/s4}}
{{#s5}}
{:green_circle:} <b>выполнено</b>{{#info.1}}: {{info.1}}{{/info.1}}
{{/s5}}
{{br}}
      ',# }}}
      7 => 'строка ({{max}})',
      8 => 'число [{{min}},{{max}}]',
      11 => '{:exclamation:} не удалось запустить задачу',
      12 => 'завершить',
      13 => 'выберите опцию',
      14 => 'обновить',
      15 => 'сброс',
      16 => 'предыдущий',
      17 => 'далее',
      18 => 'повторить',
      19 => '',
    ],
    # }}}
  ];
  static $BUTTONS = [
    # {{{
    'play'     => '{:arrow_forward:} {{.}}',
    'up'       => '{:eject_symbol:} {{.}}',
    'close'    => '{:stop_button:} {{.}}',
    'open'     => '{{.}} {:arrow_forward:}',
    'prev'     => '{:rewind:} {{.}}',
    'next'     => '{{.}} {:fast_forward:}',
    'first'    => '{:previous_track:} {{.}}',
    'last'     => '{{.}} {:next_track:}',
    'refresh'  => '{{.}} {:arrows_counterclockwise:}',
    'reset'    => '{:arrows_counterclockwise:} {{.}}',
    'retry'    => '{:arrow_right_hook:} {{.}}',
    'ok'       => 'OK',
    'add'      => '{{.}} {:new:}',
    ###
    'select0'  => '{{.}}',
    'select1'  => '{:green_circle:} {{.}}',
    'fav_on'   => '{:star:}',
    'fav_off'  => '{:sparkles:}{:star:}{:sparkles:}',
    # }}}
  ];
  static function construct(object $bot): ?self # {{{
  {
    # prepare
    $dirJson = $bot->dir->data;
    $dirInc  = $bot->dir->src;
    # load messages
    if (file_exists($fileJson = $dirJson.self::FILE_JSON[0]))
    {
      # precompiled
      if (($msgs = $bot->file->getJSON($fileJson)) === null) {
        return null;
      }
    }
    else
    {
      # bot source (merge over defaults)
      $msgs = file_exists($fileInc = $dirInc.self::FILE_INC[0])
        ? array_merge(self::$MESSAGES, (include $fileInc))
        : self::$MESSAGES;
      # render emojis for each language
      foreach ($msgs as &$a)
      {
        foreach ($a as &$b) {
          $b = $bot->tp->render($b, '{: :}', self::EMOJI);
        }
      }
      unset($a, $b);
      # store
      if (!$bot->file->setJSON($fileJson, $msgs)) {
        return null;
      }
    }
    # load button captions
    if (file_exists($fileJson = $dirJson.self::FILE_JSON[1]))
    {
      # precompiled
      if (($btns = $bot->file->getJSON($fileJson)) === null) {
        return null;
      }
    }
    else
    {
      # bot source (merge over defaults)
      $btns = file_exists($fileInc = $dirInc.self::FILE_INC[1])
        ? array_merge(self::$BUTTONS, (include $fileInc))
        : self::$BUTTONS;
      # render emojis
      foreach ($btns as &$a) {
        $a = $bot->tp->render($a, '{: :}', self::EMOJI);
      }
      unset($a);
      # store
      if (!$bot->file->setJSON($fileJson, $btns)) {
        return null;
      }
    }
    # construct
    return new self($msgs, $btns);
  }
  # }}}
  function __construct(# {{{
    public array &$msg,
    public array &$btn
  ) {}
  # }}}
}
# }}}
class BotCommands implements \ArrayAccess # {{{
{
  const FILE_JSON = 'commands.json';
  const FILE_INC  = 'commands.inc';
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
      $class = Bot::NS.$item['type'];
      $item  = new $class($bot, $item, null);
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
        : $name;
      # set type class
      $skel[$a = 'type'] = isset($skel[$a])
        ? 'Bot'.ucfirst($skel[$a]).'Item'
        : 'BotImgItem';
      if (!class_exists($class = Bot::NS.$skel[$a], false))
      {
        $bot->log->error("class not found: $class");
        return false;
      }
      # determine datafile option
      $skel[$a = 'datafile'] = isset($skel[$a])
        ? $skel[$a]
        : $class::DATAFILE;
      # determine custom handler
      $skel['handler'] = function_exists($a = Bot::NS.'BotItem_'.$id)
        ? $a : '';
      # refine texts
      # set primary language
      if (!isset($skel['text'])) {
        $skel['text'] = ['en'=>[]];
      }
      elseif (!isset($skel['text']['en'])) {
        $skel['text'] = ['en'=>$skel['text']];
      }
      # set secondary languages
      foreach (array_keys($bot->text->msg) as $a)
      {
        if (!isset($skel['text'][$a])) {
          $skel['text'][$a] = $skel['text']['en'];
        }
      }
      # set contents
      foreach ($skel['text'] as &$a)
      {
        foreach ($a as &$b)
        {
          if (strpos($b, "\r") !== false) {
            $b = str_replace("\r\n", "\n", $b);
          }
          $b = $bot->tp->render($b, '{: :}', BotTexts::EMOJI);
          $b = $bot->tp->render($b, '{! !}', $bot->text->btn);
        }
      }
      unset($a, $b);
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
# request (update)
abstract class BotRequest # {{{
{
  function __construct(
    public ?object  $bot,
    public ?object  $data
  ) {}
  public $log,$item,$func,$args;
  function result(): bool # {{{
  {
    # create logger
    $user = $this->bot->user;
    $this->log = $user->log->newObject($this);
    # initialize
    if (!$this->init() || !$user->init()) {
      return false;
    }
    # attach item
    $this->item && $this->item->attach();
    # reply
    try
    {
      $ok = $this->reply();
    }
    catch (\Throwable $e)
    {
      $this->log->exception($e);
      $ok = false;
    }
    # cleanup
    $this->item && $this->item->detach();
    $this->bot = $this->data =
    $this->log = $this->item = null;
    # complete
    return $ok;
  }
  # }}}
  abstract function init(): bool;
  abstract function reply(): bool;
}
# }}}
class BotRequestInput extends BotRequest # {{{
{
  function init(): bool # {{{
  {
    return true;
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
    $bot->cfg->wipeUserInput && (!$user->isGroup || $this->item) &&
    $bot->api->deleteMessage($msg);
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
  const SYNTAX_EXP = '|^\/(([a-z][a-z0-9]+)([:/-]([a-z][a-z0-9]+)){0,8})( ([^@]{1,})){0,1}(@[a-z_]+bot){0,1}$|i';
  const GLOBAL_LST = [
    # {{{
    'stop'    => 1,
    'restart' => 1,
    'reset'   => 1,
    # }}}
  ];
  function init(): bool # {{{
  {
    # prepare
    $msg  = $this->data;
    $bot  = $this->bot;
    $user = $bot->user;
    # parse command
    # syntax: /<item>[ <args>][@<botname>]
    if (($a = strlen($msg->text)) < 2 || $a > self::MAX_LENGTH ||
        !preg_match_all(self::SYNTAX_EXP, $msg->text, $a))
    {
      $this->log->warnInput(substr($msg->text, 0, 200));
      return true;
    }
    # extract
    $item = strtolower($a[1][0]);
    $args = $a[5][0];
    $name = $a[7][0];
    # check bot name specified in group and
    # addressed to this bot
    if ($user->isGroup && $name && $name !== $bot->cfg->name) {
      return false;# ignore
    }
    # check deep linking (tg://<BOT_NAME>?start=<item>)
    if (!$user->isGroup && $item === 'start' && $args)
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
    # remove item separators [:],[-],[/]
    if (strpos($item, ':')) {
      $item = str_replace(':', '', $item);
    }
    elseif (strpos($item, '-')) {
      $item = str_replace('-', '', $item);
    }
    elseif (strpos($item, '/')) {
      $item = str_replace('/', '', $item);
    }
    # attach item
    if (isset($bot->cmd[$item]))
    {
      $this->item = $bot->cmd[$item];
      $this->args = $args;
      $this->log->infoInput($msg->text);
    }
    else {
      $this->log->warnInput($msg->text);
    }
    return true;
  }
  # }}}
  function reply(): bool # {{{
  {
    # prepare
    $bot  = $this->bot;
    $user = $bot->user;
    # check no item attached
    if (!($item = $this->item))
    {
      # check global command
      if ($this->func) {
        return $this->replyGlobal();
      }
      # wipe incorrect command in private chat
      $bot->cfg->wipeUserInput && !$user->isGroup &&
      $bot->api->deleteMessage($this->data);
      return false;
    }
    # send new item
    $x = $item->send();
    # wipe at success or in private
    $bot->cfg->wipeUserInput && ($x || !$user->isGroup) &&
    $bot->api->deleteMessage($this->data);
    # complete
    return $x;
  }
  # }}}
  function replyGlobal(): bool # {{{
  {
    return true;
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
  function init(): bool # {{{
  {
    # check CallbackQuery->data
    if (!($text = $this->data->data) || $text[0] === '!')
    {
      $this->replyNop();
      return false;
    }
    if ($text[0] !== '/')
    {
      $this->log->warnInput($text);
      return false;
    }
    # syntax: /<item>[!<func>][ <args>]
    # parse callback text
    if ($i = strpos($text, ' '))
    {
      $this->args = substr($text, $i + 1);
      $text = substr($text, 0, $i);
    }
    if ($i = strpos($text, '!'))
    {
      $this->func = substr($text, $i + 1);
      $text = substr($text, 0, $i);
    }
    $text = substr($text, 1);
    # determine item
    return false;
    # report
    $this->log->infoInput($data);
    var_dump($data);
    return false;
    if (empty($data = $query->data)) {
      return false;
    }
    return true;
  }
  # }}}
  function reply(): bool # {{{
  {
    # prepare
    static $FUNC = 'answerCallbackQuery';
    $bot = $user->bot;
    $res = ['callback_query_id' => $this->id];
    if (!($root = $user->config->getMessageRoot($this->msg->message_id)))
    {
      $bot->api->send($FUNC, $res);
      $user->zap($this->msg);
      return 0;
    }
    # handle nop
    if (($cmd = $this->data) === '!')
    {
      $bot->api->send($FUNC, $res);
      return 0;
    }
    # ...
    if ($cmd[0] === '!')
    {
    }
    ###
    $bot->log('aaaaaaaaa');
    $bot->logDebug($root);
    ###
    # complete
    return 0;
    ###
    ###
    /***
    $isRooted = !!$id;
    # attach item
    if (!($a = $this->itemAttach($text)))
    {
      # failed, item message should be removed/nullified
      $isRooted && $this->itemDetach();
      !$isRooted && $this->itemZap($msg);
      return [
        'text' => $this->messages[$lang][2],
        'show_alert' => true
      ];
    }
    elseif ($a === -1) {# nop
      return [];
    }
    # get item and it's root configuration
    $item = &$this->item;
    $root = &$item['root']['config'];
    $msg1 = isset($root['_msg']) ? $root['_msg'] : 0;
    # determine if message is fresh
    $isFresh = ($msg1 &&
                ($a = time() - $root['_time']) >= 0 &&
                ($a < self::MESSAGE_LIFETIME));
    # determine if the item has re-activated input
    if (!($isNew = $item['isNew']))
    {
      if ($root && $item['isInputAccepted'])
      {
        # make sure it's the first from the start
        if (!array_key_exists('/', $userCfg) ||
            $userCfg['/'][0] !== $item['root']['id'])
        {
          $isNew = true;
        }
      }
    }
    # reply
    if (!$isFresh || $isNew)
    {
      # recreate message
      $res = $this->itemSend();
    }
    else
    {
      # update message
      $res = $this->itemUpdate($msg, $item);
    }
    # success
    if ($res) {
      return [];
    }
    # failure
    return [
      'text' => $this->messages[$lang][2],
      'show_alert' => true,
    ];
    /***/
    /***
    # {{{
    switch ($func) {
    case 'up':
      # {{{
      # check
      if ($item['parent'])
      {
        # CLIMB UP THE TREE (replace with parent)
        return $this->itemRender($item = $item['parent']);
      }
      if (isset($root['config']['_from']))
      {
        # EJECT BACK TO THE ORIGIN
        # copy root parameters
        $a = &$root['config'];
        $b = $a['_from'];
        $c = &$this->user->config[$b];
        $c['_msg']  = $a['_msg'];
        $c['_time'] = $a['_time'];
        $c['_item'] = $c['_hash'] = '';# not the same item
        $a['_msg']  = $a['_time'] = 0;
        unset($a['_from']);
        unset($a, $c);
        # rename active root
        $c = &$this->user->config['/'];
        if (($a = array_search($root['id'], $c, true)) !== false) {
          $c[$a] = $b;
        }
        unset($a, $c);
        # set changed
        $this->user->changed = true;
        # replace item and recurse
        return $this->itemRender($item = $this->itemGet($b));
      }
      # }}}
      # fallthrough otherwise..
    case 'close':
      $this->itemDetach($item);
      # fallthrough..
    case 'nop':
      return -1;
    }
    # }}}
    /***/
  }
  # }}}
  function replyNop(): void # {{{
  {
    $this->bot->api->send('answerCallbackQuery', [
      'callback_query_id' => $this->data->id
    ]);
  }
  # }}}
}
# }}}
class BotRequestGame extends BotRequest # {{{
{
  function init(): bool # {{{
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
  function init(): bool # {{{
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
  function init(): bool # {{{
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
         !isset($bot->text->msg[$lang])))
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
    public ?object  $text = null,
    public ?object  $cfg  = null
  )
  {
    $this->chat = new BotUserChat($this, $chat);
    $this->text = new BotUserText($this);
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
  function destruct(bool $ok): void # {{{
  {
    if ($this->bot)
    {
      $this->cfg && $this->cfg->destruct($ok);
      $this->bot  = $this->log = $this->chat =
      $this->text = $this->cfg = null;
    }
  }
  # }}}
  # [BotUserMessages] access  {{{
  function offsetExists(mixed $item): bool {
    return true;
  }
  function offsetGet(mixed $item): mixed
  {
    if (is_object($item))
    {
      # search by item's root
      foreach ($this->cfg->queue as $m)
      {
        if ($m->item->root === $item->root) {
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
      if ($m->item->root === $item->root)
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
      if ($m->item->root === $item->root)
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
class BotUserText implements \ArrayAccess # {{{
{
  function __construct(
    public object $user
  ) {}
  function offsetExists(mixed $k): bool {
    return true;
  }
  function offsetGet(mixed $k): mixed
  {
    $text = $this->user->bot->text;
    $lang = $this->user->lang;
    return isset($text->msg[$lang][$k])
      ? $text->msg[$lang][$k]
      : '';
  }
  function offsetSet(mixed $k, mixed $v): void {}
  function offsetUnset(mixed $k): void {}
}
# }}}
class BotUserConfig implements \ArrayAccess # {{{
{
  const FILE_JSON = 'config.json';
  static function construct(object $user): ?self # {{{
  {
    # try to lock:
    # group is forced to retry, single user is not
    $k = $user->isGroup ? strval($user->id) : '';
    if (!BotFile::lock($file = $user->dir.self::FILE_JSON, $k))
    {
      $user->log->error("failed to lock: $file");
      return null;
    }
    # prepare
    $cmd   = $user->bot->cmd;
    $queue = [];
    $items = [];
    $changed = false;
    # load configuration data
    if ($data = $user->bot->file->getJSON($file))
    {
      # format: [[queue],[item:config]]
      # item messages queue
      foreach ($data[0] as &$v)
      {
        if ($k = BotUserMessages::load($user, $v)) {
          $queue[] = $k;
        }
        else {
          $changed = true;
        }
      }
      # item configurations map
      foreach ($data[1] as $k => &$v)
      {
        if (isset($cmd[$k])) {
          $items[$k] = new BotItemConfig($user, $v);
        }
        else
        {
          $user->log->warn("item not found: $k");
          $changed = true;
        }
      }
    }
    # construct
    return new self(
      $user, $file, $queue, $items, $changed
    );
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
  # [BotItemConfig] access  {{{
  function offsetExists(mixed $item): bool {
    return isset($this->items[$item->id]);
  }
  function offsetGet(mixed $item): mixed
  {
    if (!isset($this->items[$id = $item->id]))
    {
      $this->items[$id] = new BotItemConfig($this);
      $this->changed = true;
    }
    return $this->items[$id];
  }
  function offsetSet(mixed $item, mixed $config): void
  {
    $this->items[$item->id] = $config;
    $this->changed = true;
  }
  function offsetUnset(mixed $item): void
  {
    unset($this->items[$item->id]);
    $this->changed = true;
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
    # data:[item,time,[msg]]
    # get command item
    if (!($item = $user->bot->cmd[$data[0]])) {
      return null;
    }
    # construct message list
    foreach ($data[2] as &$msg)
    {
      $type = $msg[0];
      $msg  = new $type($user, null, $msg[1], $msg[2]);
    }
    # construct
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
    public array   $list = [] # [BotUserMessage]
  ) {}
  # }}}
  function jsonSerialize(): array # {{{
  {
    return [$this->item->id, $this->time, $this->list];
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
      foreach ($this->list as $msg) {
        $msg->delete();
      }
    }
    else
    {
      # zap
      /***
      $bot->log($root->item->id, 0, ['zap']);
      foreach ($root->msg as $b => $a)
      {
        if ($a && !$root->item::zap($this, $a, $b)) {
          return false;
        }
      }
      /***/
    }
    # done
    return true;
  }
  # }}}
}
# }}}
# message (content)
abstract class BotUserMessage implements \JsonSerializable # {{{
{
  function __construct(# {{{
    public ?object  $user,
    public ?array   $data = null,
    public int      $id   = 0,
    public string   $hash = ''
  ) {}
  # }}}
  function jsonSerialize(): array # {{{
  {
    return [$this::class, $this->id, $this->hash];
  }
  # }}}
  abstract function send(): bool;
  function delete(): bool # {{{
  {
    # deletion is a common operation
    return $this->user->bot->api->send('deleteMessage', [
      'chat_id'    => $this->user->chat->id,
      'message_id' => $this->id,
    ]);
  }
  # }}}
}
# }}}
class BotPhotoMessage extends BotUserMessage # {{{
{
  function send(): bool # {{{
  {
    # prepare
    $bot = $this->user->bot;
    $msg = $this->data;
    $res = [
      'chat_id' => $this->user->chat->id,
      'photo'   => $msg['image'],
      'disable_notification' => true,
    ];
    if ($a = $msg['text'])
    {
      $res['caption']    = $a;
      $res['parse_mode'] = 'HTML';
    }
    if ($a = $msg['markup']) {
      $res['reply_markup'] = $a;
    }
    # send
    if (!($res = $bot->api->send('sendPhoto', $res))) {
      return false;
    }
    # store file identifier
    if ($msg['image'] instanceOf BotApiFile)
    {
      $a = end($res->photo);# last element is the original
      $bot->file->setId($msg['name'], $a->file_id);
      unset($msg['image']);# for proper hash calc
    }
    # store message identifier and hash
    $this->id   = $res->message_id;
    $this->hash = hash('md4', json_encode($msg));
    # complete
    return true;
  }
  # }}}
  function zap(): bool # {{{
  {
    # prepare
    static $FUNC = 'editMessageMedia';
    static $FID  = __CLASS__.'::dummy';
    $bot = $user->bot;
    # create dummy image
    if (0)
    {
      # TODO: dynamic
    }
    else
    {
      # static,
      # check cached or generate otherwise
      if ($img = $bot->file->getId($FID)) {
        $file = '';
      }
      elseif ($img = BotImgItem::titleBlank($bot)) {
        $file = 'attach://'.$img->postname;
      }
      else
      {
        self::$ERROR && $bot->logException(self::$ERROR);
        return false;
      }
    }
    # compose request parameters
    $a = [
      'chat_id'    => $user->request->chat->id,
      'message_id' => $msg,
      'media'      => json_encode([
        'type'     => 'photo',
        'media'    => ($file ?: $img),
        'caption'  => '',
      ]),
      'reply_markup' => '',
    ];
    # update
    $a = $file
      ? $bot->api->send($FUNC, $a, $img)
      : $bot->api->send($FUNC, $a);
    # check
    if (!$a || $a === true) {
      return false;
    }
    # store file identifier
    if ($file)
    {
      $b = end($a->result->photo);
      $bot->file->setId($FID, $b->file_id);
    }
    # done
    return true;
  }
  # }}}
}
# }}}
# item (rendering)
abstract class BotItem extends BotConfigAccess implements \JsonSerializable # {{{
{
  const DATAFILE = 0;# 0=none,1=private,2=public
  function __construct(# {{{
    public object   $bot,
    public array    $skel,
    public ?object  $parent,
    public ?object  $root    = null,
    public string   $id      = '',
    public ?object  $text    = null,
    public ?array   $items   = null,
    public ?object  $user    = null,
    public ?object  $log     = null,
    public ?object  $cfg     = null,
    public ?array   $typeCfg = null,
    public ?array   $data    = null,
    public int      $changed = 0 # bitmask:1=data,2=skel
  )
  {
    # set base
    if (!$root) {
      $this->root = $this;
    }
    $this->id   = $skel['id'];
    $this->text = new BotItemText($bot, $skel['text']);
    # set children (recurse)
    if (isset($skel[$a = 'items']))
    {
      foreach ($skel[$a] as &$child)
      {
        $class = Bot::NS.$child['type'];
        $child = new $class($bot, $child, $this, $root);
      }
      $this->items = &$skel[$a];
    }
    # set type configuration
    if (isset($bot->cfg->type[$a = $skel['type']])) {
      $this->typeCfg = &$bot->cfg->type[$a];
    }
  }
  # }}}
  function jsonSerialize(): array # {{{
  {
    return $this->skel;
  }
  # }}}
  function attach(): void # {{{
  {
    $this->user = $user = $this->bot->user;
    $this->log  = $user->log->new($this->id);
    $this->cfg  = $user->cfg[$this];
    /***
    # attach data
    $file = '';
    if ($skel['datafile'])
    {
      $file = $item->id.'.json';
      $file = $skel['datafile'] === 1
        ? $this->dir.$file
        : $this->bot->dir->data.$file;
      ###
      $item->data = $user->bot->file->getJSON($file);
    }
    /***/
  }
  # }}}
  function detach(): void # {{{
  {
    /***
    if ($file && ($item->changed & 1) &&
        !BotFile::setJSON($file, $item->data))
    {
      $this->log->error("failed to save: $file");
    }
    /***/
    $this->user = $this->log = $this->cfg = null;
  }
  # }}}
  abstract function render(): ?array;
  function markup(array &$mkup, ?array &$ext = null): string # {{{
  {
    # prepare
    static $NOP = ['text'=>' ','callback_data'=>'!'];
    $id   = $this->id;
    $bot  = $this->bot;
    $user = $this->user;
    $caps = $bot->text->btn;
    $cmd  = $bot->cmd;
    $res  = [];
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
          # get caption template
          if (!($c = $this->text["!$d"])) {
            $c = isset($caps[$d]) ? $caps[$d] : $d;
          }
          # check specific
          if ($d === 'play')
          {
            # game button
            $d = $this->text[$d] ?: $user->text[$d];
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
              $e = $user->text[$d = 'close'];
              if (!($c = $this->text["!$d"])) {
                $c = isset($caps[$d]) ? $caps[$d] : $d;
              }
            }
            $row[] = [
              'text' => $bot->tp->render($c, $e),
              'callback_data' => "/$id!$d"
            ];
            continue;
          }
          # check extras and determine caption
          if ($ext && array_key_exists($d, $ext))
          {
            # set and check
            if (($e = $ext[$d]) === null) {
              continue;
            }
            elseif ($e === false)
            {
              $row[] = $NOP;
              continue;
            }
          }
          else {
            $e = $this->text[$d];
          }
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
        if (!($e = $cmd[$d])) {
          continue;
        }
        # get template/caption
        $c = $this->text[$d] ?: $caps['open'];
        $e = $e->text['@'] ?: $e->skel['name'];
        # compose
        $row[] = [
          'text' => $bot->tp->render($c, $e),
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
  function send(): bool # {{{
  {
    try
    {
      # render and send new messages
      if (!($msgs = $this->render()) ||
          !($msgs = BotUserMessages::send($this, $msgs)))
      {
        throw BotError::skip();
      }
      # remove previous messages of the same root
      if ($prev = $this->user[$this]?->delete()) {
        unset($this->user[$this]);
      }
      # store new
      $this->user[$this] = $msgs;
    }
    catch (\Throwable $e)
    {
      $this->log->exception($e);
      return false;
    }
    $this->log->info($prev ? 'refreshed' : 'sent');
    return true;
  }
  # }}}
  ####
  ####
  function zap(object $msg): bool # {{{
  {
    return true;
    /***
    # prepare
    $bot = $this->bot;
    $id  = $msg->message_id;
    $bot->log("message #$id", 0, ['zap']);
    # try to delete message
    if ($bot->api->send('deleteMessage', [
      'chat_id'    => $this->request->chat->id,
      'message_id' => $id,
    ]))
    {
      return true;
    }
    # message might be too old for deletion,
    # determine its type and apply specific zap
    if (isset($msg->photo)) {
      return BotImgItem::zap($this, $id);
    }
    # fail
    return false;
    /***/
  }
  # }}}
}
# }}}
class BotItemText implements \ArrayAccess # {{{
{
  function __construct(
    public object $bot,
    public array  &$text
  ) {}
  function offsetExists(mixed $k): bool
  {
    return (($lang = $this->bot->user?->lang) &&
            isset($this->text[$lang][$k]))
      ? true
      : false;
  }
  function offsetGet(mixed $k): mixed
  {
    return (($lang = $this->bot->user?->lang) &&
            isset($this->text[$lang][$k]))
      ? $this->text[$lang][$k]
      : '';
  }
  function offsetSet(mixed $k, mixed $v): void
  {}
  function offsetUnset(mixed $k): void
  {}
}
# }}}
class BotItemConfig implements \ArrayAccess, \JsonSerializable # {{{
{
  function __construct(
    public object $cfg,
    public array  &$data = []
  ) {}
  function jsonSerialize(): array {
    return $this->data;
  }
  function offsetExists(mixed $k): bool {
    return isset($this->data[$k]);
  }
  function offsetGet(mixed $k): mixed {
    return isset($this->data[$k]) ? $this->data[$k] : null;
  }
  function offsetSet(mixed $k, mixed $v): void
  {
    $this->data[$k] = $v;
    $this->cfg->changed = true;
  }
  function offsetUnset(mixed $k): void
  {
    unset($this->data[$k]);
    $this->cfg->changed = true;
  }
}
# }}}
# item types
class BotImgItem extends BotItem # {{{
{
  function render(): ?array # {{{
  {
    # prepare
    $bot = $this->bot;
    # invoke custom handler
    if (($msg = $this->skel['handler']) &&
        ($msg = $msg($this)) === null)
    {
      return null;
    }
    else {
      $msg = [];
    }
    # determine image name
    if (!($b = isset($msg[$a = 'name']))) {
      $msg[$a] = $this->user->lang.'-'.$this->id;
    }
    # determine image content
    while (!isset($msg[$c = 'image']))
    {
      # check cache
      if ($d = $bot->file->getId($a = $msg[$a]))
      {
        $msg[$c] = $d;
        break;
      }
      # check dynamic file
      if ($b && ($d = $bot->file->getImage($a)))
      {
        $msg[$c] = BotApiFile::construct($d, false);
        break;
      }
      # check title
      if ($d = $this->text['@'])
      {
        if (!($msg[$c] = $this->title($d))) {
          return null;
        }
        break;
      }
      # check static file
      if (!$b && ($d = $bot->file->getImage($a)))
      {
        $msg[$c] = BotApiFile::construct($d, false);
        break;
      }
      # set item name as title
      if (!($msg[$c] = $this->title($this->skel['name']))) {
        return null;
      }
      break;
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
    return [new BotPhotoMessage($this->user, $msg)];
  }
  # }}}
  function title(string $text): ?object # {{{
  {
    $img = $file = null;
    try
    {
      # create image
      $img = self::imgNew($this['color'], $this['size']);
      # draw title
      if ($text &&
          ($opt  = $this['header']) &&
          ($font = ($bot = $this->bot)->file->getFont($opt[1])))
      {
        self::imgDrawHeader($img, $text, $opt[0], $font, $opt[2], $opt[3]);
        # draw breadcrumb
        if ($bot->cfg->useBreadcrumb &&
            ($opt  = $this['breadcrumb']) &&
            ($font = $bot->file->getFont($opt[1])))
        {
          $text = '/'.$this->skel['path'];
          self::imgDrawText($img, $text, $opt[0], $font, $opt[2], $opt[3]);
        }
      }
      # create temporary file
      $file = self::imgFile($img);
    }
    catch (\Throwable $e) {
      $this->log->exception($e);
    }
    finally {
      $img && imagedestroy($img);
    }
    # complete
    return $file;
  }
  # }}}
  static function imgNew(array $color, array $size): object # {{{
  {
    $img = null;
    try
    {
      # create image
      if (($img = imagecreatetruecolor($size[0], $size[1])) === false) {
        throw BotError::text('imagecreatetruecolor() failed');
      }
      # allocate color
      if (($color = imagecolorallocate($img, $color[0], $color[1], $color[2])) === false) {
        throw BotError::text('imagecolorallocate() failed');
      }
      # fill the background
      if (!imagefill($img, 0, 0, $color)) {
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
    array   $color,
    string  $font,
    int     $fontSize,
    array   $rect
  ):void
  {
    # header should fit into given rect, so
    # determine optimal font size (in points not pixels? -seems not)
    while ($fontSize > 6)
    {
      # determine bounding box
      if (!($a = imageftbbox($fontSize, 0, $font, $text))) {
        throw BotError::text('imageftbbox() failed');
      }
      # check it fits width and height
      if ($a[2] - $a[0] <= $rect[1] &&
          $a[1] - $a[7] <= $rect[3])
      {
        break;
      }
      # reduce and retry
      $fontSize -= 2;
    }
    # determine start coordinates (center align)
    $x = $a[2] - $a[0];
    $x = ($rect[0] + ($rect[1] - $x) / 2) | 0;
    $y = $a[1] - $a[7];
    $y = ($rect[2] + ($rect[3] - $y) / 2 + $y) | 0;
    # allocate color
    if (($color = imagecolorallocate($img, $color[0], $color[1], $color[2])) === false) {
      throw BotError::text('imagecolorallocate() failed');
    }
    # draw
    if (!imagefttext($img, $fontSize, 0, $x, $y, $color, $font, $text)) {
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
        !imagejpeg($img, $file) ||
        !file_exists($file))
    {
      throw BotError::text("imagejpeg($file) failed");
    }
    return BotApiFile::construct($file, true);
  }
  # }}}
}
# }}}
class BotTxtItem extends BotItem # {{{
{
  function render(): ?array # {{{
  {
    # prepare
    $bot  = $user->bot;
    $id   = $this->id;
    $name = $this->name;
    ###
    ###
    ###
    ###
    $item['root'] = &$root;
    $item['hash'] = '';
    $item['data'] = null;
    $item['dataChanged'] = false;
    $item['isTitleCached'] = true;
    $item['isInputAccepted'] = false;
    $item['titleId'] = $name.'_'.$lang;
    $item['title'] = isset($text['@']) ? $text['@'] : '';
    $item['content'] = isset($text['.']) ? $text['.'] : '';
    ###
    $item['titleImage'] = null;   # file_id or BotApiFile
    $item['textContent'] = null;  # message text
    $item['inlineMarkup'] = null; # null=skip, []=zap, [buttons]=otherwise
    ###
    /***
    # check new item is injected
    $item['isNew'] = $isNew;
    if ($isNew && ($func !== 'up') && isset($item['root']['config']['_from']))
    {
      unset($item['root']['config']['_from']);
      $this->user->changed = true;
    }
    /***/
    ###
    # attach data {{{
    if ($a = $item['dataHandler'])
    {
      # invoke handler
      if (!$a::attach($this, $item)) {
        return 0;
      }
    }
    elseif ($item['type'])
    {
      # load from file
      $file = $item['type'].'_'.$name.'.json';
      $file = $item['isPublicData']
        ? $this->dir->data.$file
        : $this->user->dir.$file;
      if (file_exists($file) && ($a = file_get_contents($file))) {
        $item['data'] = json_decode($a, true);
      }
    }
    # }}}
    # invoke item/type handler {{{
    if ((($a = $item['itemHandler']) && method_exists($a, 'render')) ||
        ($a = $item['typeHandler']))
    {
      if (($b = $a::render($this, $item, $func, $args)) !== 1)
      {
        !$b && $this->logError('failed to render: '.$a);
        return $b;
      }
    }
    elseif ($item['type'])# non-basic
    {
      $this->logError('unknown type: '.$item['type']);
      return 0;
    }
    # }}}
    # set defaults {{{
    if ($item[$a = 'textContent'] === null) {
      $item[$a] = $item['content'];
    }
    $b = ($item[$a = 'inlineMarkup'] === null && $item['markup'])
      ? $this->itemInlineMarkup($item, $item['markup'], $text)
      : $item[$a];
    $item[$a] = $b ? json_encode(['inline_keyboard'=>$b]) : '';
    ###
    if ($item['titleImage'] === null)
    {
      # all standard sm-bot items have a title image,
      # image may be dynamic (generated) or static
      # so first, check file_id cache
      if ($a = $this->getFileId($item['titleId']))
      {
        # CACHED
        $item['titleImage'] = $a;
      }
      elseif (!$item['title'])
      {
        # STATIC IMAGE? (no text specified)
        # determine base image paths
        $b = 'img'.DIRECTORY_SEPARATOR;
        $c = $b.$item['titleId'].'.jpg';# more specific first
        $d = $b.$name.'.jpg';# less specific last
        $a = $this->dir->src;
        $b = $this->dir->inc;
        # determine single source
        $a = (file_exists($a.$c)
          ? $a.$c : (file_exists($a.$d)
            ? $a.$d : (file_exists($b.$c)
              ? $b.$c : (file_exists($b.$d)
                ? $b.$d : ''))));
        # check found
        if ($a)
        {
          # static image file
          $this->logDebug("image request: $a");
          $item['titleImage'] = BotApiFile::construct($a, false);
        }
        else
        {
          # use item's name and raw breadcrumb (no language)
          $a = $this->itemBreadcrumb($item);
          $item['titleImage'] = $this->imageTitle($item['name'], $a);
        }
      }
      else
      {
        # SPECIFIED TEXT
        # generate nice header with language specific breadcrumbs
        $a = $this->itemBreadcrumb($item, $lang);
        $item['titleImage'] = $this->imageTitle($item['title'], $a);
      }
    }
    # }}}
    # detach data {{{
    if ($item['dataChanged'])
    {
      if ($a = $item['dataHandler'])
      {
        # invoke handler
        if (!$a::detach($this, $item)) {
          $this->logError("failed to detach data: $a");
        }
      }
      else
      {
        # store file
        if ($item['data'] && !file_put_contents($file, json_encode($item['data']))) {
          $this->logError('file_put_contents('.$file.') failed');
        }
        elseif (!$item['data'] && !@unlink($file)) {
          $this->logError('unlink('.$file.') failed');
        }
      }
    }
    # }}}
    return 1;
  }
  # }}}
}
# }}}
class BotListItem extends BotItem # {{{
{
  const DATAFILE = 1;
  # data {{{
  static $template = [
    'en' => # {{{
    '
page <b>{{page}}</b> of {{page_count}} ({{item_count}})
    ',
    # }}}
    'ru' => # {{{
    '
бва ­Ёж  <b>{{page}}</b> Ё§ {{page_count}} ({{item_count}})
    ',
    # }}}
  ];
  # }}}
  function render(): ?array # {{{
  {
    # prepare {{{
    $conf = &$item['config'];
    $data = &$item['data'];
    $lang = $bot->user->lang;
    $text = &$item['text'][$lang];
    if (!isset($item['opts']) || !isset($item['markup'])) {
      return 0;
    }
    $opts  = $item['opts'];
    $rows  = isset($opts['rows']) ? $opts['rows'] : 8;
    $cols  = isset($opts['cols']) ? $opts['cols'] : 1;
    $flexy = isset($opts['flexy']) ? $opts['flexy'] : true;
    $size  = $rows * $cols;
    $count = $data ? count($data) : 0;
    # determine total page count
    if (!($total = intval(ceil($count / $size)))) {
      $total = 1;
    }
    # determine current page
    if (!isset($conf['page']))
    {
      $conf['page'] = $page = 0;
      $bot->user->changed = true;
    }
    elseif (($page = $conf['page']) >= $total)
    {
      $conf['page'] = $page = $total - 1;
      $bot->user->changed = true;
    }
    # determine prev/next pages
    $nextPage = ($page > 0) ? $page - 1 : $total - 1;
    $prevPage = ($page < $total - 1) ? $page + 1 : 0;
    # }}}
    # handle list operation {{{
    switch ($func) {
    case 'first':
      $page = 0;
      break;
    case 'last':
      $page = $total - 1;
      break;
    case 'prev':
      $page = $prevPage;
      break;
    case 'next':
      $page = $nextPage;
      break;
    #case 'add':
    case 'item':
      # check identifier
      if (!$args || !isset($args[0]))
      {
        $this->log('no arguments');
        return -1;
      }
      if (!($a = $args[0]) || !ctype_alnum($a) || strlen($a) > 32)
      {
        $this->log('incorrect identifier');
        return -1;
      }
      # get the item for the list
      $b = null;
      foreach ($data as &$c) {
        if ($c['id'] === $a) {$b = $c; break;}
      }
      unset($c);
      if (!$b)
      {
        $this->log('list item "'.$a.'" not found');
        break;# refresh the list
      }
      # check child
      if (isset($b['type']) && ($c = $b['type']) &&
          isset($item['items'][$c]))
      {
        # re-attach to the child
        $item = $item['items'][$c];
        return $bot->itemRender($item, 'list', $b);
      }
      return -1;
    }
    # }}}
    # set markup {{{
    if ($count)
    {
      # NON-EMPTY
      # sort list items
      $a = isset($opts['order']) ? $opts['order'] : 'id';
      $b = isset($opts['desc']) ? $opts['desc'] : false;
      if (!self::sort($data, $a, $b))
      {
        $bot->logError('failed to sort');
        return 0;
      }
      # extract records from the ordered data set
      $a = $page * $size;
      $d = array_slice($data, $a, $size);
      $e = count($d);
      # create list
      $mkup = [];
      for ($a = 0, $c = 0; $a < $rows; ++$a)
      {
        $mkup[$a] = [];
        for ($b = 0; $b < $cols; ++$b)
        {
          if ($c < $e)
          {
            $mkup[$a][$b] = [
              'text'=>$d[$c]['name'],
              'callback_data'=>'/'.$item['id'].'!item '.$d[$c]['id']
            ];
          }
          else {
            $mkup[$a][$b] = ['text'=>' ','callback_data'=>'!'];
          }
          $c++;
        }
        if ($flexy && $c >= $e) {
          break;
        }
      }
      # add controls
      $a = isset($item['markup']['.'])
        ? $item['markup']['.'] : [['_up']];
      foreach ($a as $b) {
        $mkup[] = $b;
      }
    }
    else
    {
      # EMPTY
      $mkup = isset($item['markup']['-'])
        ? $item['markup']['-']
        : [['_up']];
    }
    # determine control extras
    $a = $count <= $size;
    $a = [
      'prev' => ($a ? null : strval($prevPage)),
      'next' => ($a ? null : strval($nextPage)),
      'add'  => $bot->messages[$lang][4],
    ];
    # parse and set
    $item['inlineMarkup'] = $bot->itemInlineMarkup($item, $mkup, $text, $a);
    # }}}
    # set content {{{
    if ($count)
    {
      # NON-EMPTY
      $a = ($item['content'] ?: self::$template[$lang]);
      $item['textContent'] = $bot->tp->render($a, [
        'item_count'   => $count,
        'page'         => 1 + $page,
        'page_count'   => $total,
        'not_one_page' => ($total > 1),
      ]);
    }
    else
    {
      # EMPTY
      $item['textContent'] = isset($text['-'])
        ? $text['-'] : '';
    }
    # }}}
    # complete
    if ($page !== $conf['page'])
    {
      $conf['page'] = $page;
      $bot->user->changed = true;
    }
    return 1;
  }
  # }}}
  static function sort(&$list, $k, $desc = false) # {{{
  {
    if (!isset($list[0][$k])) {
      return false;
    }
    if (is_int($list[0][$k]))
    {
      # sort numbers
      $c = usort($list, function($a, $b) use ($k, $desc) {
        # check equal and resolve by identifier
        if ($a[$k] === $b[$k]) {
          return ($a['id'] > $b['id']) ? 1 : -1;
        }
        # operate
        return $desc
          ? (($a[$k] > $b[$k]) ? -1 :  1)
          : (($a[$k] > $b[$k]) ?  1 : -1);
      });
    }
    else
    {
      # sort strings
      $c = usort($list, function($a, $b) use ($k, $desc) {
        # check equal and resolve by identifier
        if (($c = strcmp($a[$k], $b[$k])) === 0) {
          return ($a['id'] > $b['id']) ? 1 : -1;
        }
        # operate
        return $desc
          ? (($c > 0) ? -1 :  1)
          : (($c > 0) ?  1 : -1);
      });
    }
    return $c;
  }
  # }}}
}
# }}}
class BotFormItem extends BotItem # {{{
{
  const DATAFILE = 1;
  function render(): ?array # {{{
  {
    # prepare {{{
    # get current state and field index
    if (!array_key_exists('index', $conf))
    {
      $conf['index'] = 0;
      $conf['state'] = 0;
      $this->user->changed = true;
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
      $this->user->changed = true;
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
?>
