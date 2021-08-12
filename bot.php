<?php
#declare(strict_types=1);
namespace SM;
abstract class StasisConstruct # {{{
{
  protected function __construct(array $o) {
    foreach ($o as $k => &$v) {$this->$k = &$v;}
  }
}
# }}}
class Bot extends StasisConstruct # {{{
{
  const MESSAGE_LIFETIME = 48*60*60;
  static function start(string $id = 'master'): never # {{{
  {
    # check requirements
    if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 80000) {
      exit(4);
    }
    # configure environment
    ini_set('html_errors', 0);
    ini_set('implicit_flush', 1);
    set_time_limit(0);
    set_error_handler(function($no, $msg, $file, $line) {
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
    if ($bot->master && function_exists($e = 'sapi_windows_set_ctrl_handler'))
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
          if (!$this->handle($update)) {
            break 2;
          }
        }
      }
    }
    catch (\Exception|\Error $e) {
      $bot->log->exception($e);
    }
    # terminate
    $bot->destruct();
    exit($bot->status);
  }
  # }}}
  static function construct(string $id, bool $proc): ?self # {{{
  {
    try
    {
      # construct
      $log = null;
      $bot = new static([
        'master' => ($id === 'master'),
        'id'     => $id,
        'log'    => null,
        'dir'    => null,
        'cfg'    => null,
        'file'   => null,
        'api'    => null,
        'tp'     => null,
        'text'   => null,
        'cmd'    => null,
        'proc'   => $proc,
        'status' => 1,
      ]);
      # initialize
      # set base objects
      $bot->log = $log = new BotLog($id, new BotLogConfig($bot));
      if (!($bot->dir = $dir = BotDirs::construct($id, $log)) ||
          !($bot->cfg = $cfg = BotConfig::construct($dir, $log)) ||
          !$dir->init($bot) ||
          !$log->init($bot) ||
          !$cfg->init($bot))
      {
        throw new \Exception('', -1);
      }
      # load dependencies
      require_once $dir->inc.'mustache.php';
      require_once $dir->src.'control.php';
      # set files and telegram api
      if (!($bot->file = BotFiles::construct($bot)) ||
          !($bot->api  = BotApi::construct($bot)))
      {
        throw new \Exception('', -1);
      }
      # set template parser
      $o = [$log->new('mustache'), 'errorOnly'];
      $o = [
        'logger'  => \Closure::fromCallable($o),
        'helpers' => [
          'BR'    => "\n",
          'NBSP'  => "\xC2\xA0",# non-breakable space
          'END'   => "\xC2\xAD",# SOFT HYPHEN U+00AD
        ]
      ];
      if (!($bot->tp = Mustache::construct($o)))
      {
        $log->error('failed to construct template parser');
        throw new \Exception('', -1);
      }
      # set texts and commands
      if (!($bot->text = BotTexts::construct($bot)) ||
          !($bot->cmd  = BotCommands::construct($bot)))
      {
        throw new \Exception('', -1);
      }
      # process controller (console mode)
      if ($proc && !($proc = $bot->master
        ? BotMaster::construct($bot)
        : BotSlave::construct($bot)))
      {
        throw new \Exception('', -1);
      }
      $bot->proc = $proc;
    }
    catch (\Exception|\Error $e)
    {
      $log && ~$e->getCode() && $log->exception($e);
      $bot && $bot->destruct();
      $bot = null;
    }
    return $bot;
  }
  # }}}
  function check(): bool # {{{
  {
    return $this->proc->check();
  }
  # }}}
  function handle(object $update): int # {{{
  {
    # construct request
    $x = null;
    if (isset($update->callback_query)) {
      $x = BotRequestCallback::init($update->callback_query);
    }
    elseif (isset($update->inline_query)) {
      #$x = BotRequestInline::init($update->inline_query);
    }
    elseif (isset($update->message)) {
      $x = BotRequestInput::init($update->message);
    }
    # construct user
    if (!$x || !($x = BotUser::construct($this, $x)))
    {
      return true;
    }
    # handle user request
    return ($x = BotUser::init($this, $x)) ? $x->finit() : 0;
  }
  # }}}
  function destruct(): void # {{{
  {
    if ($this->log)
    {
      $this->proc && is_object($this->proc) && $this->proc->destruct();
      $this->api  && $this->api->destruct();
      $this->cfg  && $this->cfg->destruct();
      $this->log->info('stopped');
      $this->log = null;
    }
  }
  # }}}
}
# }}}
class BotLog # {{{
{
  # contructor/initializer {{{
  function __construct(
    public string  $name,
    public ?object $cfg,
    public ?object $parent = null,
  ) {}
  function new(string $name): self {
    return new self($name, $this->cfg, $this);
  }
  function init(object $bot): bool
  {
    # set bot and root name
    $this->cfg->bot = $bot;
    $this->name = $bot->cfg->name;
    # set logfiles
    if ($bot->cfg->saveAccessLog) {
      $this->cfg->files[0] = $bot->dir->data.'ACCESS.log';
    }
    if ($bot->cfg->saveErrorLog) {
      $this->cfg->files[1] = $bot->dir->data.'ERROR.log';
    }
    return true;
  }
  # }}}
  static function str_bg_color(string $str, string $name, int $strong=0): string # {{{
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
  static function str_fg_color(string $str, string $name, int $strong=0): string # {{{
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
  function out(int $level, int $sep, string ...$msg): void # {{{
  {
    # prepare
    $cfg = $this->cfg;
    # file output
    if ($f = $cfg->files[(($level === 1) ? 1 : 0)])
    {
      #$a = date(DATE_ATOM).': ';
      #$b = $name ? implode(' '.$PREFIX, $name) : '';
      #file_put_contents($f, $a.$b.$msg."\n", FILE_APPEND);
    }
    # console output
    if ($f = $cfg->bot->proc)
    {
      # compose name chain
      $c = $cfg->color[$level];
      $s = self::str_fg_color($cfg->sep[$sep], $c, 1);
      $x = '';
      $p = $this;
      while ($p->parent)
      {
        $x = self::str_bg_color($p->name, $c)." $s $x";
        $p = $p->parent;
      }
      # compose msg chain
      for ($i = 0, $j = count($msg) - 1; $i < $j; ++$i) {
        $x = $x.self::str_bg_color($msg[$i], $c)." $s ";
      }
      # compose all
      $c = $cfg->root[1];
      $x = (
        self::str_fg_color($cfg->root[0], $c, 1).
        self::str_fg_color($p->name, $c, 0).
        " $s $x".$msg[$j]."\n"
      );
      # output
      ($f === true) ? fwrite(STDOUT, $x) : $f->out($x);
    }
  }
  # }}}
  function info(string $msg, int $sep = 0): void # {{{
  {
    $this->out(0, $sep, $msg);
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
  function exception(object $e): void # {{{
  {
    static $E = [
      'E_ERROR' => 1,
      'E_RECOVERABLE_ERROR' => 4096,
      'E_WARNING' => 2,
      'E_PARSE' => 4,
      'E_NOTICE' => 8,
      'E_STRICT' => 2048,
      'E_DEPRECATED' => 8192,
      'E_CORE_ERROR' => 16,
      'E_CORE_WARNING' => 32,
      'E_COMPILE_ERROR' => 64,
      'E_COMPILE_WARNING' => 128,
      'E_USER_ERROR' => 256,
      'E_USER_WARNING' => 512,
      'E_USER_NOTICE' => 1024,
      'E_USER_DEPRECATED' => 16384,
      'E_ALL' => 32767,
    ];
    $a = $e->getMessage();
    $b = $e->getTraceAsString();
    if ($c = strpos($b, "\n"))
    {
      $b = str_replace("\n", "\n  ", substr($b, $c + 1));
      $b = str_replace(__DIR__, '', $b);
    }
    $c = ($d = array_search($c = $e->getCode(), $E, true))
      ? substr($d, 2)
      : 'EXCEPTION';
    $d = $e->getTrace()[0];
    $d = isset($d['file'])
      ? str_replace(__DIR__, '', $d['file']).'('.$d['line'].')'
      : '---';
    ###
    $this->out(1, 0, $c, "$a\n  #0 $d\n  $b");
  }
  # }}}
  function commands(): void # {{{
  {
    if (($bot = $this->cfg->bot)->proc)
    {
      $a = self::parseTree($bot->cmd->tree, 0, $this->cfg->root[1]);
      $bot->proc->out("$a\n");
    }
  }
  # }}}
  ###
  static function parseTree( # {{{
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
        $x .= $b ? self::str_fg_color('│ ', $color, 1) : '  ';
      }
      # compose item line
      $b  = (++$i === $j);
      $c  = self::str_fg_color(($b ? '└─' : '├─'), $color, 1);
      $x .= $c.$a->name."\n";
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
class BotLogConfig # {{{
{
  function __construct(
    public ?object $bot,
    public array   $files = ['',''],# level:[!1,1]
    public array   $sep   = ['>','<'],# out,in
    public array   $color = ['green','red','yellow'],# level:[info,error,warn]
    public array   $root  = ['@','cyan']# prefix,color
  ) {}
}
# }}}
class BotDirs extends StasisConstruct # {{{
{
  static function construct(string $id, object $log): ?self
  {
    # determine base paths
    $home = __DIR__.DIRECTORY_SEPARATOR;
    $inc  = $home.'inc'.DIRECTORY_SEPARATOR;
    $data = $home.'data'.DIRECTORY_SEPARATOR.$id.DIRECTORY_SEPARATOR;
    # check
    if (!file_exists($inc))
    {
      $log->error("directory not found: $inc");
      return null;
    }
    if (!file_exists($data))
    {
      $log->error("directory not found: $data");
      return null;
    }
    # determine user and group data storage
    if (!file_exists($user = $data.'u'.DIRECTORY_SEPARATOR) &&
        !@mkdir($user))
    {
      $log->error("failed to create: $user");
      return null;
    }
    if (!file_exists($group = $data.'g'.DIRECTORY_SEPARATOR) &&
        !@mkdir($group))
    {
      $log->error("failed to create: $group");
      return null;
    }
    # image and font directories must be prioritized
    $img  = [];
    $font = [];
    file_exists($a = $data.'img'.DIRECTORY_SEPARATOR) && ($img[] = $a);
    file_exists($a = $data.'font'.DIRECTORY_SEPARATOR) && ($font[] = $a);
    # construct
    return new static([
      'home'  => $home,
      'inc'   => $inc,
      'data'  => $data,
      'user'  => $user,
      'group' => $group,
      'img'   => $img,
      'font'  => $font,
      'src'   => '',
    ]);
  }
  function init(object $bot): bool
  {
    # set bot directory
    $src = $this->home.'bots'.DIRECTORY_SEPARATOR.$bot->cfg->bot.DIRECTORY_SEPARATOR;
    if (!file_exists($src))
    {
      $bot->log->error("directory not found: $src");
      return false;
    }
    $this->src = $src;
    # add image directory
    file_exists($a = $src.'img'.DIRECTORY_SEPARATOR) && ($this->img[] = $a);
    # add font directories
    $a = 'font'.DIRECTORY_SEPARATOR;
    file_exists($b = $src.$a) && ($this->font[] = $b);
    file_exists($b = $this->inc.$a) && ($this->font[] = $b);
    # done
    return true;
  }
}
# }}}
class BotConfig extends StasisConstruct # {{{
{
  static $FILE = 'config.json';
  static $DEFS = [
    # {{{
    'bot'    => '',
    'token'  => '',
    'name'   => '',
    'admins' => [],
    'colors' => [
      'title' => [[240,248,255],[0,0,0]],
    ],
    'fonts' => [
      'title' => ['Cuprum-Bold.ttf','Bender.ttf'],
    ],
    'forceLang'            => '',
    'saveAccessLog'        => false,
    'saveErrorLog'         => false,
    'useFileIds'           => false,
    'showBreadcrumb'       => true,
    'replyFailedCommand'   => false,
    'replyIgnoredCallback' => true,
    'wipeUserInput'        => true,
    'debugTasks'           => false,
    # }}}
  ];
  public string $file;
  static function construct(object $dir, object $log): ?self # {{{
  {
    # check configuration file exists
    if (!file_exists($file = $dir->data.self::$FILE))
    {
      $log->error("file not found: $file");
      return null;
    }
    # load and check data
    if (!($o = BotFiles::get_json($file))   ||
        !isset($o[$k = 'bot'])   || !$o[$k] ||
        !isset($o[$k = 'token']) || !$o[$k] ||
        !isset($o[$k = 'name'])  || !$o[$k])
    {
      $log->error("incorrect file: $file");
      return null;
    }
    # construct
    $o = new static(array_merge(self::$DEFS, $o));
    $o->file = $file;
    return $o;
  }
  # }}}
  function init(object $bot): bool # {{{
  {
    if (!BotFiles::lock($file = $this->file))
    {
      $bot->log->error("failed to lock: $file");
      $this->file = '';
      return false;
    }
    return true;
  }
  # }}}
  function destruct(): void # {{{
  {
    if ($a = $this->file)
    {
      BotFiles::unlock($a);
      $this->file = '';
    }
  }
  # }}}
}
# }}}
class BotFiles extends StasisConstruct # {{{
{
  static $ID_FILE='fids.json';
  static function construct($bot): self # {{{
  {
    # get file identifiers map
    $map = ($file = $bot->cfg->useFileIds ? $bot->dir->data.$ID_FILE : '')
      ? self::get_json($file)
      : null;
    # construct
    return new static([
      'dir'    => $bot->dir,
      'log'    => $bot->log->new('file'),
      'idFile' => $file,
      'idMap'  => $map,
    ]);
  }
  # }}}
  function getId(string $file): string # {{{
  {
    return ($this->idMap && isset($this->idMap[$file]))
      ? $this->idMap[$file]
      : '';
  }
  # }}}
  function setId(string $file, string $id): void # {{{
  {
    if ($this->idFile)
    {
      $this->idMap[$file] = $id;
      if (self::lock($this->idFile))
      {
        self::set_json($this->idFile, $this->idMap);
        self::unlock($this->idFile);
      }
    }
  }
  # }}}
  static function lock(string $file, bool $force = false): string # {{{
  {
    # prepare
    $id    = strval(time());
    $count = 20;
    $lock  = "$file.lock";
    # wait until lock released or count exhausted
    while (file_exists($lock) && --$count) {
      usleep(100000);# 100ms
    }
    # check exhausted
    if (!$count)
    {
      # check not forced or apply force (remove lockfile)
      if (!$force || !@unlink($lock)) {
        return '';
      }
      # clear cache
      clearstatcache(true, $lock);
    }
    # set new lock and make sure no collisions
    return (file_put_contents($lock, $id) && file_get_contents($lock) === $id)
      ? $lock
      : '';
  }
  # }}}
  static function unlock(string $file) # {{{
  {
    return (file_exists($lock = "$file.lock"))
      ? @unlink($lock) : false;
  }
  # }}}
  static function get_json(string $file): array # {{{
  {
    if (!file_exists($file) ||
        !($data = file_get_contents($file)) ||
        !($data = json_decode($data, true)))
    {
      $data = [];
    }
    return $data;
  }
  # }}}
  static function set_json(string $file, ?array $data): bool # {{{
  {
    if ($data)
    {
      if (($data = json_encode($data, JSON_UNESCAPED_UNICODE)) === false) {
        return false;
      }
      if (file_exists($file) && (file_get_contents($file) === $data)) {
        return true;
      }
      if (file_put_contents($file, $data) === false) {
        return false;
      }
    }
    elseif (file_exists($file)) {
      @unlink($file);
    }
    return true;
  }
  # }}}
}
# }}}
class BotApi extends StasisConstruct # {{{
{
  const URL = 'https://api.telegram.org/bot';
  const POLLING_TIMEOUT = 120;# max=50?
  static $OPT = [
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
        throw new \Exception('curl_init() failed');
      }
      # configure
      if (!curl_setopt_array($curl, self::$OPT)) {
        throw new \Exception('failed to configure');
      }
      # create multi-curl instance
      if (!($murl = curl_multi_init())) {
        throw new \Exception('curl_multi_init() failed');
      }
      # construct
      $api = new static([
        'bot'  => $bot,
        'log'  => $log,
        'url'  => self::URL.$bot->cfg->token,
        'curl' => $curl,
        'murl' => $murl,
        'res'  => null,
      ]);
    }
    catch (\Exception $e)
    {
      $curl && curl_close($curl);
      $murl && curl_multi_close($murl);
      $log->error($e->getMessage());
    }
    return $api;
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
        : 'faulty response'
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
  function send(string $method, array $args, ?object $file = null): object|bool # {{{
  {
    static $TEMP = [
      'sendPhoto'     => 'photo',
      'sendAudio'     => 'audio',
      'sendDocument'  => 'document',
      'sendVideo'     => 'video',
      'sendAnimation' => 'animation',
      'sendVoice'     => 'voice',
      'sendVideoNote' => 'video_note',
    ];
    # prepare
    if ($file && ($a = $file->postname)) {
      $args[$a] = $file;
    }
    # send
    curl_setopt_array($this->curl, [
      CURLOPT_URL  => $this->url.'/'.$method,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $args,
    ]);
    $this->res = null;
    $a = curl_exec($this->curl);
    # explicitly remove temporary files
    if ($file && $file instanceof BotApiFile) {
        $file->__destruct();
    }
    if (isset($TEMP[$method]))
    {
      $b = $TEMP[$method];
      if (isset($args[$b]) && $args[$b] instanceof BotApiFile) {
        $args[$b]->__destruct();
      }
    }
    # check result
    if ($a === false)
    {
      $this->log->error('curl_exec('.curl_errno($this->curl).'): '.curl_error($this->curl));
      return false;
    }
    # decode
    if (!($this->res = $a = json_decode($a, false)))
    {
      $this->log->error('json_decode('.json_last_error().'): '.json_last_error_msg());
      return false;
    }
    # check response
    if (!$a->ok)
    {
      $this->log->error($method.(isset($a->description) ? ': '.$a->description : ''));
      return false;
    }
    return $a;
  }
  # }}}
  function destruct(): void # {{{
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
}
class BotApiFile extends \CURLFile
{
  # {{{
  public bool $isTemp;
  function __construct(string $file, bool $temp = true)
  {
    parent::__construct($file);
    $this->postname = basename($file);
    $this->isTemp = $temp;
  }
  function __destruct()
  {
    if ($this->name && $this->isTemp && file_exists($this->name))
    {
      @unlink($this->name);
      $this->name = '';
    }
  }
  # }}}
}
# }}}
class BotTexts extends StasisConstruct # {{{
{
  static $MESSAGES = [
    'en' => [# {{{
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
      9 => 'play',
      10 => 'close',
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
      9 => 'играть',
      10 => 'закрыть',
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
    'up'       => '{:eject_symbol:} {{.}}',
    'close'    => '{:stop_button:} {{.}}',
    'open'     => '{{.}} {:arrow_forward:}',
    'play'     => '{:arrow_forward:} {{.}}',
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
  static $EMOJI = [
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
  static function construct(object $bot): ?self # {{{
  {
    # messages
    if (!($msg = BotFiles::get_json($a = $bot->dir->data.'messages.json')))
    {
      # load from bot directory and merge over defaults
      $msg = file_exists($b = $bot->dir->src.'messages.inc')
        ? array_merge(self::$MESSAGES, (include $b))
        : self::$MESSAGES;
      # render emojis
      foreach ($msg as &$c)
      {
        foreach ($c as &$d) {
          $d = $bot->tp->render($d, '{: :}', self::$EMOJI);
        }
      }
      unset($c, $d);
      # store
      if (!BotFiles::set_json($a, $msg))
      {
        $bot->log->error("set_json($a) failed");
        return null;
      }
    }
    # button captions
    if (!($btn = BotFiles::get_json($a = $bot->dir->data.'buttons.json')))
    {
      # load source and merge over defaults
      $btn = file_exists($b = $bot->dir->src.'buttons.inc')
        ? array_merge(self::$BUTTONS, (include $b))
        : self::$BUTTONS;
      # render emojis
      foreach ($btn as &$c) {
        $c = $bot->tp->render($c, '{: :}', self::$EMOJI);
      }
      unset($c);
      # store
      if (!BotFiles::set_json($a, $btn))
      {
        $bot->log->error("set_json($a) failed");
        return null;
      }
    }
    # construct
    return new static([
      'msg' => $msg,
      'btn' => $btn,
    ]);
  }
  # }}}
}
# }}}
class BotCommands extends StasisConstruct # {{{
{
  static function construct(object $bot): ?self # {{{
  {
    # try to load precompiled
    $isRefined = true;
    if (!($data = BotFiles::get_json($bot->dir->data.'commands.json')))
    {
      # include and merge
      $isRefined = false;
      $a = 'commands.inc';
      if (!file_exists($b = $bot->dir->src.$a))
      {
        $bot->log->error("file not found: $b");
        return null;
      }
      if (!($data = include $b) || !is_array($data))
      {
        $bot->log->error("incorrect file: $b");
        return null;
      }
      if (file_exists($b = $bot->dir->data.$a) &&
          is_array($c = (include $b)))
      {
        $data = array_merge($data, $c);
      }
    }
    # parse and construct root items
    $base = '\\'.__NAMESPACE__.'\\BotItem';
    foreach ($data as $a => &$b)
    {
      # determine item class
      $type = $base.(isset($b['type']) ? ucfirst($b['type']) : 'Img');
      # check exists
      if (!$isRefined && !class_exists($type, false))
      {
        $bot->log->error("class not found: $type");
        return null;
      }
      # construct
      if (($b = $type::construct($bot, $a, $b, null, $isRefined)) === null)
      {
        $bot->log->error("failed to construct $type::$a");
        return null;
      }
    }
    unset($b);
    # construct
    return new static([
      'tree' => $data,
      'map'  => self::createMap($data),
    ]);
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
}
class BotCommand extends StasisConstruct
{
  # {{{
  # command syntax: /<item>!<func> <args>
  const EXPR = '|^\/((\w+)([:/-](\w+)){0,8})(!(\w{1,})){0,1}( (.{1,})){0,1}$|';
  const MAX_SIZE = 200;
  static function parse(string $exp, bool $loose = true): ?self
  {
    # check correct size
    if (($a = strlen($exp)) < 2 || $a > self::MAX_SIZE) {
      return null;
    }
    # determine bot name (specified as postfix in groups)
    $bot = (($a = strrpos($exp, '@')) !== false)
      ? substr($exp, 0, $a)
      : '';
    # check
    if ($loose && $exp[0] === '!')
    {
      # parse simplified expression
      $item = '';
      if ($a = strpos($exp, ' '))
      {
        $func = substr($exp, 1, $a);
        $args = explode(',', substr($exp, $a + 1));
      }
      else
      {
        $func = substr($exp, 1);
        $args = [];
      }
    }
    else
    {
      # parse full-fledged command
      $a = null;
      if (!preg_match_all(self::EXPR, $exp, $a)) {
        return null;
      }
      # extract
      $item = $a[1][0];
      $func = $a[6][0];
      $args = strlen($a[8][0])
        ? explode(',', $a[8][0])
        : null;
      # check deep link (tg://<BOT_NAME>?start=<item>)
      if ($item === 'start' && !$func && $args)
      {
        $item = $args[0];
        $args = null;
      }
      # remove separators [:],[-],[/]
      if (strpos($item, ':')) {
        $item = str_replace(':', '', $item);
      }
      elseif (strpos($item, '-')) {
        $item = str_replace('-', '', $item);
      }
      elseif (strpos($item, '/')) {
        $item = str_replace('/', '', $item);
      }
    }
    # construct
    return new static([
      'id'   => $item,
      'func' => $func,
      'args' => $args,
      'bot'  => $bot,
    ]);
  }
  # }}}
}
# }}}
###
class BotMaster extends StasisConstruct # {{{
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
    return new static([
      'bot' => $bot,
      'log' => $bot->log->new('proc'),
      'cmd' => $cmd,
      'map' => [],
    ]);
  }
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
class BotMasterSlave extends StasisConstruct # {{{
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
        throw new \Exception("proc_open($cmd) failed", -1);
      }
      # initiate sync protocol
      # set master lockfile
      if (!touch($out)) {
        throw new \Exception("touch($out) failed", -1);
      }
      if (fwrite($pipe[0], $out) === false) {
        throw new \Exception('fwrite() failed', -1);
      }
      # wait
      while (file_exists($out))
      {
        usleep(200000);
        if (!($a = proc_get_status($proc)) || !$a['running']) {
          throw new \Exception('exited('.$a['exitcode'].')', -1);
        }
      }
      # get slave lockfile
      stream_set_blocking($pipe[1], true);
      if (($in = fread($pipe[1], 300)) === false) {
        throw new \Exception('fread() failed', -1);
      }
      if (!file_exists($in)) {
        throw new \Exception("file not found: $in", -1);
      }
      # unlock slave
      @unlink($in);
    }
    catch (\Exception $e)
    {
      # report error
      if (~$e->getCode()) {
        $log->exception($e);
      }
      else {
        $log->error($e->getMessage());
      }
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
    return new static([
      'log'  => $log,
      'proc' => $proc,
      'lock' => [$in, $out],
      'pipe' => $pipe,
    ]);
  }
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
class BotSlave extends StasisConstruct # {{{
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
    if (!touch($out = $bot->dir->data.$bot->id.'.proc'))
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
    return new static([
      'log'  => $log,
      'lock' => [$in, $out],
    ]);
  }
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
###
class BotRequestInput extends StasisConstruct # {{{
{
  static function construct(object $msg): ?object # {{{
  {
    if (!isset($msg->from)) {
      return null;
    }
    if (isset($msg->text) && ($msg->text[0] === '/')) {
      return BotRequestCommand::init($msg);
    }
    return new static([
      'msg'  => $msg,
      'from' => $msg->from,
      'chat' => $msg->chat,
    ]);
  }
  # }}}
  function reply(object $user): int # {{{
  {
    # prepare
    $bot = $user->bot;
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
    # cleanup
    if ($bot->cfg->wipeUserInput)
    {
      $bot->api->send('deleteMessage', [
        'chat_id'    => $this->chat->id,
        'message_id' => $this->msg->message_id,
      ]);
    }
    return 0;
  }
  # }}}
  function wipe(object $user): void # {{{
  {
    if ($user->bot->cfg->wipeUserInput)
    {
      $user->bot->api->send('deleteMessage', [
        'chat_id'    => $this->chat->id,
        'message_id' => $this->msg->message_id,
      ]);
    }
  }
  # }}}
}
# }}}
class BotRequestCommand extends BotRequestInput # {{{
{
  static function init(object $msg): self # {{{
  {
    return new static([
      'msg'  => $msg,
      'from' => $msg->from,
      'chat' => $msg->chat,
      'cmd'  => BotCommand::parse($msg->text),
    ]);
  }
  # }}}
  function reply(object $user): int # {{{
  {
    # prepare
    $bot  = $user->bot;
    $opt  = $bot->cfg;
    $msg  = $this->msg;
    $item = null;
    $res  = 0;
    if (!($cmd = $this->cmd))
    {
      $bot->logWarn('incorrect command: '.$msg->text);
      $user->isGroup || $this->wipe($user);
      return 0;
    }
    if ($cmd->bot && $this->isGroup &&
        $cmd->bot !== $opt->name)
    {
      return 0;# addressed to another bot
    }
    # operate
    $bot->log($msg->text);
    switch ($cmd->id) {
    case 'restart':
      $user->isAdmin && ($res = -1);
      break;
    case 'stop':
      $user->isAdmin && ($res =  1);
      break;
    case 'reset':
      /***
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
      /***/
      break;
    default:
      ($item = $user->render($cmd)) && $user->send($item);
      break;
    }
    if (!$user->isGroup || ($item && $item->msg)) {
      $this->wipe($user);
    }
    return $res;
  }
  # }}}
}
# }}}
class BotRequestCallback extends StasisConstruct # {{{
{
  static function init(object $query): ?object # {{{
  {
    if (!isset($query->message)) {
      return null;
    }
    if (isset($query->game_short_name)) {
      return BotRequestGame::init($query);
    }
    if (!isset($query->data)) {
      return null;
    }
    return new static([
      'msg'   => $query->message,
      'from'  => $query->from,
      'chat'  => $query->message->chat,
      'id'    => $query->id,
      'data'  => $query->data,
    ]);
  }
  # }}}
  function reply(object $user): int # {{{
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
  }
  # }}}
  function replyNop(object $api, int $id): void # {{{
  {
    $api->send('answerCallbackQuery', [
      'callback_query_id' => $id
    ]);
  }
  # }}}
}
# }}}
class BotRequestGame extends StasisConstruct # {{{
{
  static function init(object $query): ?self # {{{
  {
    return new static([
    ]);
  }
  # }}}
  function reply(object $user): int # {{{
  {
    return 0;
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
class BotRequestInline extends StasisConstruct # {{{
{
  static function init(object $query): ?self # {{{
  {
    return new static([
    ]);
  }
  # }}}
  function reply(object $user): int # {{{
  {
    $this->log('inline query!!!');
    return 0;
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
class BotUser extends StasisConstruct # {{{
{
  # {{{
  static function init(object $bot, object $u): ?self
  {
    # prepare
    # {{{
    # parse update and create request
    $req = null;
    if (isset($u->callback_query)) {
      $req = BotRequestCallback::init($u->callback_query);
    }
    elseif (isset($u->inline_query)) {
      #$req = BotRequestInline::init($u->inline_query);
    }
    elseif (isset($u->message)) {
      #$req = BotRequestInput::init($u->message);
    }
    if (!$req)
    {
      $bot->logCycle();
      return null;
    }
    # determine user/group name
    $from = $req->from;
    $chat = $req->chat;
    if ($isGroup = ($chat && $chat->type !== 'private'))
    {
      $a = (isset($chat->title) ? $chat->title : $chat->id);
      $b = (isset($chat->username) ? '@'.$chat->username : '');
      $name = $a.$b.'/';
    }
    else {
      $name = '';
    }
    $a = preg_replace('/[[:^print:]]/', '', trim($from->first_name));
    $b = (isset($from->username) ? '@'.$from->username : '');
    $name = $name.$a.$b;
    # determine admin flag
    # TODO: groups
    $isAdmin = in_array($from->id, $bot->cfg->admins);
    # check masterbot access
    if (!$isAdmin && $bot->master)
    {
      $bot->logWarn("access denied: $name");
      return null;
    }
    # determine language
    if (!($lang = $bot->cfg->forceLang) &&
        (!isset($from->language_code) ||
         !($lang = $from->language_code) ||
         !isset($bot->messages[$lang])))
    {
      $lang = 'en';
    }
    # determine directory
    $dir = $isGroup ? '_'.$chat->id : $from->id;
    $dir = $bot->dir->data.$dir.DIRECTORY_SEPARATOR;
    if (!file_exists($dir) && !@mkdir($dir))
    {
      $bot->logError("failed to create directory: $dir");
      return null;
    }
    # }}}
    # construct
    $user = new static([
      'bot'      => $bot,
      'request'  => $req,
      'id'       => $from->id,
      'name'     => $name,
      'isGroup'  => $isGroup,
      'isAdmin'  => $isAdmin,
      'lang'     => $lang,
      'messages' => &$bot->messages[$lang],
      'dir'      => $dir,
      'config'   => null,
      'item'     => null,
      'changed'  => false,
    ]);
    # initialize
    if (!($user->config = BotUserConfig::init($user)))
    {
      $bot->logError("failed to attach user configuration: $name");
      return null;
    }
    # attach and complete
    return $bot->user = $user;
  }
  function finit(): int
  {
    # reply and detach
    $res = $this->request->reply($this);
    $this->config->finit();
    $this->config = $this->bot->user = null;
    return $res;
  }
  function __destruct()
  {
    # guard against exceptions
    if ($this->config)
    {
      BotFiles::unlock($this->config->file);
      $this->config = null;
    }
  }
  # }}}
  function render(object $cmd): ?object # {{{
  {
    # prepare
    $bot  = $this->bot;
    $item = $bot->items->map;
    $id   = $cmd->id;
    $func = $cmd->func;
    $args = $cmd->args;
    # check item exists
    if (!isset($item[$id]))
    {
      $bot->logWarn("item not found: $id");
      return null;
    }
    # initialize
    $item = $item[$id];
    $item->config = $this->config->getItem($item);
    $item->root   = $this->config->getRoot($item);
    /***
    switch ($func) {
    case 'inject':
      # {{{
      # remove current item
      $this->itemDetach($item);
      # check injection origin
      if (!isset($args['root']) ||
          !($a = $args['root']) ||
          !isset($a['config']['_msg']) ||
          !$a['config']['_msg'])
      {
        $this->logError('no injection origin');
        return 0;
      }
      # copy origin configuration
      $b = &$a['config'];
      $c = &$root['config'];
      $c['_from'] = $args['id'];
      $c['_msg']  = $b['_msg'];
      $c['_time'] = $b['_time'];
      $c['_item'] = $c['_hash'] = '';# not the same item
      # clear origin
      $b['_msg']  = 0;
      $b['_time'] = 0;
      $b['_hash'] = '';
      unset($b, $c);
      # replace active root
      $b = &$this->user->config['/'];
      if (($c = array_search($a['id'], $b, true)) !== false) {
        $b[$c] = $item['root']['id'];
      }
      unset($b, $c);
      # set changed
      $this->user->changed = true;
      # cleanup
      $func = '';
      # }}}
      break;
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
    /***/
    # attach language specific texts
    $s = &$item->struct;
    if (isset($s['text'][$this->lang])) {
      $item->text = &$s['text'][$this->lang];
    }
    else {
      $item->text = &$s['text']['en'];
    }
    # attach data
    if ($s['datafile'])
    {
      $file = $item->id.'.json';
      $file = $s['datafile'] === 1
        ? $this->dir.$file
        : $bot->dir->data.$file;
      if (file_exists($file) && ($a = file_get_contents($file))) {
        $item->data = json_decode($a, true);
      }
    }
    else {
      $file = '';
    }
    # render messages
    $item->msg = $item->render($this, $func, $args);
    # detach data
    if ($file && ($item->changed & 1) &&
        !Bot::file_set_json($file, $item->data))
    {
      $bot->logWarn("file_set_json($file) failed");
    }
    # attach and complete
    return $this->item = $item;
  }
  # }}}
  function markup(object $item, array &$rows, ?array &$ext = null): string # {{{
  {
    # prepare
    static $NOP = ['text'=>' ','callback_data'=>'!'];
    $bot  = $this->bot;
    $lang = $this->lang;
    $map  = $bot->items->map;
    $mkup = [];
    # iterate array of arrays (rows)
    foreach ($rows as &$a)
    {
      $row = [];
      foreach ($a as $b)
      {
        # check ready
        if (!is_string($b))
        {
          is_array($b) && ($row[] = $b);
          continue;
        }
        # check skip
        if (!($c = strlen($b))) {
          continue;
        }
        # check nop
        if ($c === 1)
        {
          $row[] = $NOP;
          continue;
        }
        # check operation scope
        if ($b[0] !== '!') {
          # OUTER {{{
          # determine item id and function
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
          $d = ($d[0] === '/')
            ? substr($d, 1)# exact
            : $item->id.$d;# direct child
          # check exists
          if (!isset($map[$d])) {
            continue;
          }
          # get caption template
          $c = isset($item->text[$d])
            ? $item->text[$d]
            : $bot->buttons['open'];
          # get caption
          $e = $map[$d];
          $e = isset($e->text[$lang]['@'])
            ? $e->text[$lang]['@']
            : $e->name;
          # compose
          $row[] = [
            'text' => $bot->tp->render($c, $e),
            'callback_data' => "/$d$b"
          ];
          continue;
          # }}}
        }
        # INNER {{{
        # get function name
        $d = ($d = strpos($b, ' '))
          ? substr($b, 1, $d)
          : substr($b, 1);
        # get caption template
        $c = isset($item->text["!$d"])
          ? $item->text["!$d"]
          : (isset($bot->buttons[$d])
            ? $bot->buttons[$d]
            : $d);
        # check common
        if ($d === 'play')
        {
          # {{{
          $c = $bot->tp->render($c, (isset($item->text[$d])
            ? $item->text[$d]
            : $this->messages[9]
          ));
          $row[] = ['text'=>$c,'callback_game'=>null];
          # }}}
          continue;
        }
        if ($d === 'up')
        {
          # {{{
          if ($e = $item->parent)
          {
            # upward navigation
            $e = isset($e->text[$lang]['@'])
              ? $e->text[$lang]['@']
              : $e->name;
          }
          else
          {
            # closeup
            $e = $this->messages[10];
            $d = 'close';
            $c = isset($item->text[$d])
              ? $item->text[$d]
              : $bot->buttons[$d];
          }
          $row[] = [
            'text' => $bot->tp->render($c, $e),
            'callback_data' => "!$d"
          ];
          # }}}
          continue;
        }
        # check extras
        # {{{
        if ($ext && array_key_exists($d, $ext))
        {
          # check skip
          if (($e = $ext[$d]) === null) {
            continue;
          }
          # check empty/nop
          if ($e === false)
          {
            $row[] = $NOP;
            continue;
          }
        }
        elseif (isset($item->text[$d])) {
          $e = $item->text[$d];
        }
        else {
          $e = '';
        }
        # }}}
        # compose
        $row[] = [
          'text' => $bot->tp->render($c, $e),
          'callback_data' => $b
        ];
        # }}}
      }
      # accumulate
      $row && ($mkup[] = $row);
    }
    # complete
    return $mkup
      ? json_encode(['inline_keyboard'=>$mkup], JSON_UNESCAPED_UNICODE)
      : '';
  }
  # }}}
  function send(object $item): bool # {{{
  {
    # check
    if (!$item->msg) {
      return false;
    }
    # create new root and
    # send rendered messages
    $root = BotUserConfigRoot::init($this, $item);
    foreach ($item->msg as &$a)
    {
      if (($root->msg[] = $item::send($this, $a)) === 0) {
        return false;
      }
      $root->hash[] = hash('md4', json_encode($a));
    }
    # replace previous root
    $item->root && $this->delete($item->root);
    $this->config->setRoot($item->root = $root);
    # complete
    $this->bot->log($item->id, 0, ['send']);
    return true;
  }
  # }}}
  function delete(object $root): bool # {{{
  {
    # prepare
    $bot = $this->bot;
    # telegram allows to delete only "fresh" messages, so,
    # check the root is fresh
    if (($a = time() - $root->time) >= 0 &&
        ($a < Bot::MESSAGE_LIFETIME))
    {
      # delete
      $b = $this->request->chat->id;
      $bot->log($root->item->id, 0, ['delete']);
      foreach ($root->msg as $a)
      {
        if ($a && !$bot->api->send('deleteMessage', [
          'chat_id'    => $b,
          'message_id' => $a,
        ]))
        {
          return false;
        }
      }
    }
    else
    {
      # zap
      $bot->log($root->item->id, 0, ['zap']);
      foreach ($root->msg as $b => $a)
      {
        if ($a && !$root->item::zap($this, $a, $b)) {
          return false;
        }
      }
    }
    # remove root and complete
    $this->config->unsetRoot($root);
    return true;
  }
  # }}}
  function zap(object $msg): bool # {{{
  {
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
      return BotItemImg::zap($this, $id);
    }
    # fail
    return false;
  }
  # }}}
}
class BotUserConfig extends StasisConstruct
{
  # initializer {{{
  static function init(object $user): ?self
  {
    # aquire a forced lock
    if (!BotFiles::lock($file = $user->dir.'config.json', true)) {
      return null;
    }
    # construct
    $conf = new static([
      'user'  => $user,
      'file'  => $file,
      'roots' => [],
      'items' => [],
    ]);
    # initialize
    if ($data = Bot::file_get_json($file))
    {
      foreach ($data['/'] as &$v) {
        $conf->roots[] = BotUserConfigRoot::init($user, $v);
      }
      foreach ($data['*'] as $k => &$v) {
        $conf->items[$k] = BotUserConfigItem::init($user, $v);
      }
      unset($v);
    }
    else {
      $user->changed = true;
    }
    return $conf;
  }
  function finit(bool $unlock = true): void
  {
    if ($this->user->changed)
    {
      $this->user->changed = false;
      Bot::file_set_json($this->file, [
        '/' => $this->roots,
        '*' => $this->items,
      ]);
    }
    $unlock && BotFiles::unlock($this->file);
  }
  # }}}
  # api {{{
  function getRoot(object $item): ?object
  {
    while (($root = $item)->parent) {}
    foreach ($this->roots as $a)
    {
      if ($a->root === $root) {
        return $a;
      }
    }
    return null;
  }
  function getMessageRoot(int $msg): ?object
  {
    foreach ($this->roots as $a)
    {
      if (($b = array_search($msg, $a->msg, true)) !== false) {
        return $a;
      }
    }
    return null;
  }
  function unsetRoot(object $root): void
  {
    if (($a = array_search($root, $this->roots, true)) !== false)
    {
      array_splice($this->roots, $a, 1);
      $this->user->changed = true;
    }
  }
  function setRoot(object $root): void
  {
    array_unshift($this->roots, $root);
    $this->user->changed = true;
  }
  function getItem(object $item): object
  {
    # create item configuration when accessed
    if (!isset($this->items[$k = $item->id]))
    {
      $this->items[$k] = BotUserConfigItem::init($this);
      $this->user->changed = true;
    }
    return $this->items[$k];
  }
  function setItem(object $item, array $v): void
  {
    $this->items[$item->id] = BotUserConfigItem::init($this, $v);
    $this->user->changed = true;
  }
  # }}}
}
class BotUserConfigRoot extends StasisConstruct implements \JsonSerializable {
  # {{{
  static function init(object $user, array|object $data): self
  {
    # prepare
    $map = $user->bot->items->map;
    if (is_array($data))
    {
      # loaded from config
      $root = $map[$data[3]];
      $item = (($a = $data[4]) && isset($map[$a]))
        ? $map[$a]
        : null;
    }
    else
    {
      # created from item
      while (($root = $data)->parent) {}
      $item = $data;
      $data = [[],[],time()];
    }
    # construct
    return new static([
      'user' => $user,
      'msg'  => $data[0],# message identifiers
      'hash' => $data[1],# message hashes
      'time' => $data[2],# creation timestamp (seconds)
      'root' => $root,# root item
      'item' => $item,# current item
    ]);
  }
  function jsonSerialize(): array {
    return [
      $this->msg, $this->hash, $this->time,
      $this->root->id,
      $this->item->id
    ];
  }
  # }}}
}
class BotUserConfigItem extends StasisConstruct implements \ArrayAccess, \JsonSerializable {
  # {{{
  static function init(object $user, ?array &$data = null): self
  {
    return new static([
      'user' => $user,
      'data' => ($data === null) ? [] : $data,
    ]);
  }
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
    # ...
    #$this->user->changed = true;
  }
  function offsetUnset(mixed $k): void
  {
    unset($this->data[$k]);
    $this->user->changed = true;
  }
  # }}}
}
# }}}
###
abstract class BotItem extends StasisConstruct implements \JsonSerializable # {{{
{
  static $DATAFILE = 0;# 0=none, 1=private, 2=public
  static $ERROR = null;# exception
  static function construct(# {{{
    object  $bot,
    string  $name,
    array   &$struct,
    ?object $parent,
    bool    $isRefined
  ):?self
  {
    # prepare
    $id = $parent ? $parent->id.$name : $name;
    $base = '\\'.__NAMESPACE__.'\\BotItem';
    $type = $base.'_'.$id;
    # initialize item structure
    # {{{
    if (!$isRefined)
    {
      # initialize texts
      # correct empty or primary language
      if (!isset($struct['text'])) {
        $struct['text'] = ['en'=>[]];
      }
      elseif (!isset($struct['text']['en'])) {
        $struct['text'] = ['en'=>$struct['text']];
      }
      # correct absent languages (copy primary)
      foreach ($bot->text->msg as $a => &$b)
      {
        if (!isset($struct['text'][$a])) {
          $struct['text'][$a] = $struct['text']['en'];
        }
      }
      unset($b);
      # render emojis and captions
      foreach ($struct['text'] as &$a)
      {
        foreach ($a as &$b)
        {
          if (strpos($b, "\r") !== false) {
            $b = str_replace("\r\n", "\n", $b);
          }
          $b = $bot->tp->render($b, '{: :}', BotTexts::$EMOJI);
          $b = $bot->tp->render($b, '{! !}', $bot->text->btn);
        }
      }
      unset($a, $b);
      # initialize other props
      $struct[$a = 'datafile'] = isset($struct[$a])
        ? $struct[$a]
        : self::$DATAFILE;
      $struct[$a = 'handler'] = $b = isset($struct[$a])
        ? $struct[$a]
        : 0;
      # check handler
      if ($b && !function_exists($type))
      {
        $bot->logWarn("handler function not found: $type");
        return null;
      }
    }
    # }}}
    # construct
    $item = new static([
      'parent'  => $parent,
      'id'      => $id,
      'name'    => $name,
      'struct'  => $struct,
      'handler' => ($struct['handler'] ? $type : ''),
      'items'   => null,
      'root'    => null,
      'config'  => null,
      'text'    => null,
      'data'    => null,
      'msg'     => null,
      'changed' => 0,# bitmask: 1=data,2=struct
    ]);
    # construct children
    if (isset($struct['items']))
    {
      foreach ($struct['items'] as $a => &$b)
      {
        $type = $base.(isset($b['type']) ? ucfirst($b['type']) : 'Img');
        if (!$isRefined && !class_exists($type, false))
        {
          $bot->log->warn("class not found: $type");
          return null;
        }
        if (($b = $type::construct($bot, $a, $b, $item, $isRefined)) === null)
        {
          $bot->log->warn("failed to construct $type::$a");
          return null;
        }
      }
      unset($b);
      $item->items = &$struct['items'];
    }
    return $item;
  }
  # }}}
  function jsonSerialize(): array # {{{
  {
    return $this->struct;
  }
  # }}}
  function breadcrumb(): string # {{{
  {
    $bread = '/'.$this->name;
    while ($crumb = $this->parent) {
      $bread = '/'.$crumb->name.$bread;
    }
    return $bread;
  }
  # }}}
}
# }}}
class BotItemImg extends BotItem # {{{
{
  function render(object $user, string $func, ?array $args): ?array # {{{
  {
    # prepare
    $bot = $user->bot;
    $o   = $bot->cfg;
    $s   = &$this->struct;
    $msg = [];
    # invoke handler
    if (($a = $this->handler) &&
        ($msg = $a($user, $this, $func, $args)) === null)
    {
      $bot->logWarn("handler failed: $a");
      return null;
    }
    # determine image
    if (!($b = isset($msg[$a = 'imageId']))) {
      $msg[$a] = $user->lang.'-'.$this->id;
    }
    while (!isset($msg[$c = 'image']))
    {
      # check cached
      if ($d = $bot->file->getId($a = $msg[$a]))
      {
        $msg[$c] = $d;
        break;
      }
      # check file specified by handler
      if ($b)
      {
        foreach ($bot->dir->img as &$d)
        {
          if (file_exists($e = "$d$a.jpg"))
          {
            $msg[$c] = new BotApiFile($e, false);
            break 2;
          }
        }
      }
      # check title
      if (isset($this->text['@']))
      {
        if (!($msg[$c] = self::title(
          $this->text['@'],
          $o->showBreadcrumb ? $this->breadcrumb() : '',
          $o->colors[$a = 'title'],
          $o->fonts[$a]
        )))
        {
          $bot->logException(self::$ERROR);
          return null;
        }
        break;
      }
      # check file
      if (!$b)
      {
        foreach ($user->bot->dir->img as &$d)
        {
          if (file_exists($e = "$d$a.jpg") ||
              file_exists($e = "$d$b.jpg"))
          {
            $msg[$c] = new BotApiFile($e, false);
            break 2;
          }
        }
      }
      # use item name as title
      if (!($msg[$c] = self::title(
        $this->name,
        $o->showBreadcrumb ? $this->breadcrumb() : '',
        $o->colors[$a = 'title'],
        $o->fonts[$a]
      )))
      {
        $bot->logException(self::$ERROR);
        return null;
      }
      break;
    }
    # determine content and inline markup
    if (!isset($msg[$a = 'text'])) {
      $msg[$a] = isset($this->text['.']) ? $this->text['.'] : '';
    }
    if (!isset($msg[$a = 'markup'])) {
      $msg[$a] = isset($s[$a]) ? $user->markup($this, $s[$a]) : '';
    }
    # complete
    return [$msg];
  }
  # }}}
  static function title( # {{{
    string  $header,
    string  $bread,
    array   $color, # [foreground<RGB>,background<RGB>]
    array   $font,  # [header,breadcrumb]
    int     $asFile = 1 # 0=<image>,1=BotApiFile(temporary),2=BotApiFile(persistent)
  ):?object
  {
    static $MAX_FONTSIZE = 64;#72;
    try
    {
      # prepare {{{
      # create image
      if (($img = @imagecreatetruecolor(640, 160)) === false) {
        throw new \Exception('imagecreatetruecolor() failed');
      }
      # allocate colors
      $fg = $color[0];
      $bg = $color[1];
      if (($fg = imagecolorallocate($img, $fg[0], $fg[1], $fg[2])) === false ||
          ($bg = imagecolorallocate($img, $bg[0], $bg[1], $bg[2])) === false)
      {
        throw new \Exception('imagecolorallocate() failed');
      }
      # fill the background
      if (!imagefill($img, 0, 0, $bg)) {
        throw new \Exception('imagefill() failed');
      }
      # }}}
      # draw header {{{
      if ($header)
      {
        # header should fit into x:140-500,y:0-160 area, so
        # determine optimal font size (in points not pixels? -seems not)
        $size = $MAX_FONTSIZE;
        while ($size > 6)
        {
          # determine bounding box
          if (!($a = imageftbbox($size, 0, $font[0], $header))) {
            throw new \Exception('imageftbbox() failed');
          }
          # check it fits width and height
          if ($a[2] - $a[0] <= 360 &&
              $a[1] - $a[7] <= 160)
          {
            break;
          }
          # reduce and retry
          $size -= 2;
        }
        # determine start coordinates (center align)
        $x = 140 + intval((360 - $a[2]) / 2);
        #$y = 160 / 2 + intval(($a[1] - $a[7]) / 2) - 8;
        $y = 160 / 2 + 24;
        # draw
        if (!imagefttext($img, $size, 0, $x, $y, $fg, $font[0], $header)) {
          throw new \Exception('imagefttext() failed');
        }
      }
      # }}}
      # draw breadcrumb {{{
      if ($bread)
      {
        $a = (count($font) > 1) ? $font[1] : $font[0];
        $x = 140;
        $y = 32;
        if (!imagefttext($img, 16, 0, $x, $y, $fg, $a, $bread)) {
          throw new \Exception('imagefttext() failed');
        }
      }
      # }}}
      # create file {{{
      if ($asFile)
      {
        if (!($a = tempnam(sys_get_temp_dir(), 'img')) ||
            !imagejpeg($img, $a))
        {
          throw new \Exception("imagejpeg($a) failed");
        }
        imagedestroy($img);
        $img = new BotApiFile($a, ($asFile === 1));
      }
      # }}}
    }
    catch (\Exception $e)
    {
      self::$ERROR = $e;
      if ($img)
      {
        imagedestroy($img);
        $img = null;
      }
    }
    return $img;
  }
  # }}}
  static function titleDummy(object $bot): ?object # {{{
  {
    return self::title('', '', $bot->cfg->colors['title'], []);
  }
  # }}}
  static function send(object $user, array &$msg): int # {{{
  {
    # prepare
    $bot = $user->bot;
    $res = [
      'chat_id' => $user->request->chat->id,
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
      return 0;
    }
    # store file identifier
    if ($msg['image'] instanceOf BotApiFile)
    {
      $a = end($res->result->photo);
      $bot->file->setId($msg['imageId'], $a->file_id);
    }
    # complete
    return $res->result->message_id;
  }
  # }}}
  static function zap(object $user, int $msg): bool # {{{
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
      elseif ($img = self::titleDummy($bot)) {
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
  ### TODO
  function itemUpdate($msg, &$item, $reportHashFail = false) # {{{
  {
    # CUSTOM
    if (($a = $item['typeHandler']) && method_exists($a, 'update')) {
      return $a::update($this, $msg, $item);
    }
    # STANDARD
    # prepare
    $root = &$item['root']['config'];
    $a = $item['titleId'];
    $b = $item['textContent'];
    $c = $item['inlineMarkup'];
    $d = hash('md4', $b.$c);
    # check message hash
    if ($a.$d === ($e = $root['_hash']))
    {
      if ($reportHashFail) {
        $this->logError('update skipped: '.$item['id']);
      }
      return -1;# positive
    }
    # determine which API function to use,
    # image changes when title identifier changes
    $func = strlen($e) - strlen($d);
    $func = ($a !== substr($e, 0, $func));
    # determine message parameters
    if ($func)
    {
      # IMAGE and TEXT
      # {{{
      $file = $item['titleImage'];
      $func = 'editMessageMedia';
      if ($file instanceof BotApiFile) {
        $e = 'attach://'.$file->postname;# attachment
      }
      else
      {
        $e = $file;# file_id
        $file = null;# not attachment
      }
      $res = [
        'type'       => 'photo',
        'media'      => $e,
        'caption'    => $b,
        'parse_mode' => 'HTML',
      ];
      $res = [
        'chat_id'    => $this->user->chat->id,
        'message_id' => $msg,
        'media'      => json_encode($res),
      ];
      # }}}
    }
    else
    {
      # TEXT
      # {{{
      $file = null;
      $func = 'editMessageCaption';
      $res  = [
        'chat_id'    => $this->user->chat->id,
        'message_id' => $msg,
        'caption'    => $b,
        'parse_mode' => 'HTML',
      ];
      # }}}
    }
    if ($c) {# add MARKUP
      $res['reply_markup'] = $c;
    }
    # send
    if (!($b = $this->api->send($func, $res, $file)) || $b === true)
    {
      $this->logError($func.'('.$item['id'].') failed: '.$this->api->error);
      $this->logError($res);
      return 0;
    }
    # set message hash
    $item['hash'] = $a.$d;
    # set file_id
    if ($a && $file)
    {
      $c = end($b->result->photo);
      $this->setFileId($a, $c->file_id);
    }
    # complete
    return $this->userUpdate($b->result->message_id);
  }
  # }}}
}
# }}}
class BotItemTxt extends BotItem # {{{
{
  function render(object $user, string $func, ?array $args): ?object # {{{
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
          $item['titleImage'] = new BotApiFile($a, false);
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
  function send() # {{{
  {
    # prepare
    if ($item === null && !($item = &$this->item)) {
      return 0;
    }
    # CUSTOM
    if (($a = $item['typeHandler']) && method_exists($a, 'send')) {
      return $a::send($this, $item);
    }
    # STANDARD
    # check title specified
    if (!$item['titleImage'])
    {
      $this->logError('no titleImage specified');
      return 0;
    }
    # assemble parameters
    $a = $item['titleId'];
    $b = $item['textContent'];
    $c = $item['inlineMarkup'];
    $q = [
      'chat_id' => $this->user->chat->id,
      'photo'   => $item['titleImage'],
      'disable_notification' => true,
    ];
    if ($b)
    {
      $q['caption']    = $b;
      $q['parse_mode'] = 'HTML';
    }
    if ($c) {
      $q['reply_markup'] = $c;
    }
    # send
    if (!($d = $this->api->send('sendPhoto', $q)))
    {
      $this->logError('sendPhoto('.$item['id'].') failed: '.$this->api->error);
      $this->logError($q);
      return 0;
    }
    # set message hash
    $item['hash'] = $a.hash('md4', $b.$c);
    # set file_id
    if ($a && ($item['titleImage'] instanceOf BotApiFile))
    {
      $b = end($d->result->photo);
      $this->setFileId($a, $b->file_id);
    }
    # complete
    return $this->userUpdate($d->result->message_id);
  }
  # }}}
}
# }}}
class BotItemList extends BotItem # {{{
{
  static $DATAFILE = 1;
  # data {{{
  static public $template = [
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
  static function render($bot, &$item, $func, $args) # {{{
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
class BotItemForm extends BotItem # {{{
{
  static $DATAFILE = 1;
  static function render($bot, &$item, $func, $args) # {{{
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
