<?php declare(strict_types=1);
namespace SM;
# used globals {{{
use
  JsonSerializable, ArrayAccess,
  SyncEvent, SyncReaderWriter, SyncSharedMemory,
  Generator, Closure, CURLFile,
  Throwable, Error, Exception;
use function
  set_time_limit,ini_set,register_shutdown_function,error_get_last,
  class_exists,function_exists,method_exists,
  explode,implode,count,reset,next,key,array_unshift,array_keys,
  in_array,array_search,array_reverse,
  strpos,strrpos,strlen,trim,rtrim,strval,uniqid,ucfirst,
  file_put_contents,file_get_contents,clearstatcache,file_exists,unlink,
  filemtime,mkdir,scandir,fwrite,fread,fclose,
  curl_init,curl_setopt_array,curl_exec,
  curl_errno,curl_error,curl_strerror,curl_close,
  curl_multi_init,curl_multi_add_handle,curl_multi_exec,curl_multi_select,
  curl_multi_strerror,curl_multi_info_read,
  curl_multi_remove_handle,curl_multi_close,
  proc_open,is_resource,proc_get_status,proc_terminate,getmypid,
  pack,unpack,time,sleep,usleep;
# }}}
# helpers {{{
function array_key(array &$a, int $index): int|string|null # {{{
{
  reset($a);
  while ($index--) {
    next($a);
  }
  return key($a);
}
# }}}
function array_sync(array &$a, array &$data): void # {{{
{
  foreach ($a as $k => &$v)
  {
    if (isset($data[$k]))
    {
      if (is_array($v)) {
        array_sync($v, $data[$k]);
      }
      else {
        $v = $data[$k];
      }
    }
  }
  unset($v);
}
# }}}
function array_sync_merge(array &$a, array &$data): void # {{{
{
  foreach ($data as $k => &$v)
  {
    if (isset($a[$k]) && is_array($v)) {
      array_sync_merge($a[$k], $v);
    }
    else {
      $a[$k] = $v;
    }
  }
  unset($v);
}
# }}}
function array_sync_new(array &$a, array &$data): void # {{{
{
  foreach ($data as $k => &$v)
  {
    if (!isset($a[$k])) {
      $a[$k] = $v;
    }
    elseif (is_array($v)) {
      array_sync_new($a[$k], $v);
    }
  }
  unset($v);
}
# }}}
function file_lock(# {{{
  string  $file,
  int     $tries = 3,     # retry count
  int     $override = 0,  # lockfile lifetime in seconds (0=unlimited)
  int     $delay = 20000, # single check delay (20ms)
  int     $ticks = 50     # total check count (50*20 == 1000ms)
):string
{
  # prepare
  static $id;
  if (!$id)
  {
    $id = ($id = getmypid())
      ? strval($id)
      : uniqid();
  }
  # wait released
  if (file_wait($lock = $file.'.lock', false, $delay, $ticks))
  {
    # create lockfile
    if (file_put_contents($lock, $id, LOCK_EX) === false ||
        ($a = file_get_contents($lock)) === false)
    {
      return '';
    }
    # avoid collisions
    return ($a === $id)
      ? $lock : file_lock($file, $tries, $wait, $ticks);
  }
  # check exhausted
  if (--$tries <= 0)
  {
    # try to override
    return ($override && ($a = filemtime($lock)) &&
            (time() - $a) > $override && unlink($lock))
      ? file_lock($file, 1, $wait, $ticks)
      : '';
  }
  # retry
  return file_lock($file, $tries, $wait, $ticks);
}
# }}}
function file_wait(# {{{
  string  $file,
  bool    $flag  = false, # state to wait (exists or not)
  int     $delay = 20000, # single check delay (20ms)
  int     $ticks = 50     # total check count (50*20 == 1000ms)
):bool
{
  while (file_exists($file) !== $flag && --$ticks)
  {
    usleep($delay);
    !$flag && clearstatcache(true, $file);
  }
  return ($ticks > 0);
}
# }}}
function file_persist(string $file): bool # {{{
{
  clearstatcache(true, $file);
  return file_exists($file);
}
# }}}
function file_unlink(string $file): bool # {{{
{
  return (!file_exists($file) || unlink($file));
}
# }}}
function file_unlock(string $file): string # {{{
{
  return (file_exists($lock = $file.'.lock') && unlink($lock))
    ? $lock : '';
}
# }}}
function file_get_array(string $file): ?array # {{{
{
  try
  {
    if (!file_exists($file) || !is_array($data = include $file)) {
      $data = null;
    }
  }
  catch (Throwable) {
    $data = null;
  }
  return $data;
}
# }}}
function file_get_json(string $file): ?array # {{{
{
  if (!file_exists($file) ||
      ($a = file_get_contents($file)) === false ||
      ($a = json_decode($a, true)) === null ||
      !is_array($a))
  {
    return null;
  }
  return $a;
}
# }}}
function file_set_json(string $file, array|object &$data): bool # {{{
{
  return (($a = json_encode($data, JSON_UNESCAPED_UNICODE)) !== false &&
          file_put_contents($file, $a) !== false);
}
# }}}
function file_time(string $file, bool $creat = false): int # {{{
{
  try
  {
    $a = $creat ? filectime($file) : filemtime($file);
    $a = $a ?: 0;
  }
  catch (Throwable) {
    $a = 0;
  }
  return $a;
}
# }}}
function dir_make(string $dir, int $perms = 0750): bool # {{{
{
  try {
    $a = (file_exists($dir) || mkdir($dir, $perms));
  }
  catch (Throwable) {
    $a = false;
  }
  return $a;
}
# }}}
function class_name(object $o): string # {{{
{
  $a = $o::class;
  return ($b = strrpos($a, '\\'))
    ? substr($a, $b + 1) : $a;
}
# }}}
# }}}
# base {{{
class BotError extends Error # {{{
{
  static function skip(): self {
    return new self(0);
  }
  static function warn(string ...$msg): self {
    return new self(0, $msg);
  }
  static function stop(string ...$msg): self {
    return new self(1, $msg);
  }
  static function fail(string ...$msg): self
  {
    # create new error
    $e = new self(1);
    # prepend message with details
    if (count($a = $e->getTrace()) > 1) {
      array_unshift($msg, $a[1]['function'].'·'.$a[0]['line']);
    }
    $e->msg = $msg;
    return $e;
  }
  static function from(object $e): ?self
  {
    if ($e instanceof BotError)
    {
      # determine total error level
      $a = $e;
      $b = $a->level;
      while ($a->next)
      {
        $a  = $a->next;
        $b += $a->level;
      }
      # result with error or no error
      return $b ? $e : null;
    }
    # wrap
    return new self(1, null, $e);
  }
  function __construct(
    public int      $level  = 0,
    public array    $msg    = [],
    public ?object  $origin = null,
    public ?object  $next   = null
  )
  {
    parent::__construct('', -1);
  }
  function push(object $e): self
  {
    if ($e = self::from($e))
    {
      $n = $this;
      while ($n->next) {
        $n = $n->next;
      }
      $n->next = $e;
    }
    return $this;
  }
}
# }}}
class BotConfig # {{{
{
  # {{{
  const
    DIR_INC       = 'inc',
    DIR_SRC       = 'bots',
    DIR_FONT      = 'font',
    DIR_IMG       = 'img',
    DIR_DATA      = 'data',
    DIR_USER      = 'usr',
    DIR_GROUP     = 'grp',
    FILE_CONFIG   = 'config.inc',
    FILE_BOT_CONFIG = 'config.json',
    FILE_HANDLERS = 'handlers.php',
    EXP_TOKEN     = '/^\d{8,10}:[a-z0-9_-]{35}$/i';
  public
    $data = [
      # {{{
      'Bot'             => [
        'source'        => 'master',
        'token'         => '',
        'id'            => '',
        'name'          => '',
        'canJoinGroups' => false,
        'canReadGroups' => false,
        'isInline'      => false,
        'admins'        => [],
        'lang'          => '',
      ],
      'BotApi'     => [
        'baseUrl'  => 'https://api.telegram.org/bot',
        # getUpdates
        'timeout'  => 60,# polling timeout (telegram's max=50)
        'limit'    => 100,# polling result limit (100=max)
        'maxFails' => 0,# max repeated fails until termination (0=unlimited)
        'pause'    => 10,# pause after repeated failure (seconds)
        # WebHook
        'webhook'  => false,# registered?
        'url'      => '',# HTTPS url of the webhook
        'cert'     => false,# custom certificate file?
        'ip'       => '',# fixed IP instead of DNS resolved IP
        'maxHooks' => 100,# max allowed simultaneous requests (telegram's default=40)
      ],
      'BotLog'      => [
        'debug'     => true,# display debug output?
        'infoFile'  => '',
        'errorFile' => '',
      ],
      'BotProcess' => [
        'list'     => [],# botId:taskId
      ],
      'BotConsoleProcess' => [
        'list'            => [],# botId
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
        'isPersistent'   => false,# form type, change instead of repeat
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
        'inputForward'   => false,# forward after input accepted
        'cols'           => 3,# number of options in a row
        'colsFlex'       => false,# allow variable cols in the last row
        'markup'         => [
          'failure'      => [['!back','!retry'],['!up']],
          'input'        => [['!clear'],['!back','!ok','!forward'],['!up']],
          'confirm'      => [['!back','!submit'],['!up']],
          'success'      => [['!repeat','!change'],['!up']],
        ],
      ],
      # }}}
    ],
    $dirInc,$dirSrcRoot,$dirDataRoot,
    $dirSrc,$dirData,$dirUsr,$dirGrp,
    $dirImg = [],$dirFont = [],
    $isProduction = false,
    $file,$changed = false;
  # }}}
  static function check(): string # {{{
  {
    $a = self::getIncDir().self::FILE_CONFIG;
    if (!file_exists($a)) {
      return "file not found: $a";
    }
    if (!($b = file_get_array($a))) {
      return "file is incorrect: $a";
    }
    if (empty($b['token'])) {
      return "file: $a\ntoken is not specified";
    }
    if (!self::checkToken($c = $b['token'])) {
      return "file: $a\nincorrect token: $c";
    }
    if (!file_exists($c = self::getDataDir($b))) {
      return "directory not found: $c";
    }
    /*
    $a = [
      $this->dirSrcRoot,
      $this->dirInc.self::DIR_FONT.DIRECTORY_SEPARATOR,
      $this->dirInc.self::DIR_IMG.DIRECTORY_SEPARATOR,
    ];
    foreach ($a as $b)
    {
      if (!file_exists($b))
      {
        $log->error($b, 'not found');
        return false;
      }
      $log->info($b, 'ok');
      usleep(100000);
    }
    */
    return '';
  }
  # }}}
  static function checkToken(string $token): string # {{{
  {
    return preg_match(self::EXP_TOKEN, $token)
      ? self::getId($token) : '';
  }
  # }}}
  static function getId(string $token): string # {{{
  {
    return substr($token, 0, strpos($token, ':'));
  }
  # }}}
  static function getSrcDir(): string # {{{
  {
    return __DIR__.DIRECTORY_SEPARATOR.self::DIR_SRC.DIRECTORY_SEPARATOR;
  }
  # }}}
  static function getIncDir(): string # {{{
  {
    return __DIR__.DIRECTORY_SEPARATOR.self::DIR_INC.DIRECTORY_SEPARATOR;
  }
  # }}}
  static function getDataDir(array &$o): string # {{{
  {
    return isset($o['dataRoot'])
      ? rtrim($o['dataRoot'], '\\/').DIRECTORY_SEPARATOR
      : __DIR__.DIRECTORY_SEPARATOR.self::DIR_DATA.DIRECTORY_SEPARATOR;
  }
  # }}}
  static function isInstalled(): bool # {{{
  {
    $a = new self(null);
    $a = $a->dirDataRoot.$a->data['Bot']['id'];
    return file_exists($a.DIRECTORY_SEPARATOR.self::FILE_BOT_CONFIG);
  }
  # }}}
  function __construct(public ?object $bot, string $id = '') # {{{
  {
    # set base directories
    $this->dirSrcRoot = self::getSrcDir();
    $this->dirInc     = $a = self::getIncDir();
    # load master config
    $a = file_get_array($a.self::FILE_CONFIG);
    $this->dirDataRoot = self::getDataDir($a);
    isset($a[$b = 'isProduction']) && ($this->isProduction = $a[$b]);
    isset($a[$b = 'baseUrl']) && ($this->data['BotApi'][$b] = $a[$b]);
    isset($a[$b = 'source'])  && ($this->data['Bot'][$b] = $a[$b]);
    $this->data['Bot']['token'] = $a = $a['token'];
    $this->data['Bot']['id']    = $id ?: self::getId($a);
  }
  # }}}
  function init(): bool # {{{
  {
    # prepare
    $cfg = &$this->data['Bot'];
    # determine data directories
    $this->dirData = $a = $this->dirDataRoot.$cfg['id'].DIRECTORY_SEPARATOR;
    $this->dirUsr  = $a.self::DIR_USER.DIRECTORY_SEPARATOR;
    $this->dirGrp  = $a.self::DIR_GROUP.DIRECTORY_SEPARATOR;
    # load configuration
    if (!($a = file_get_json($file = $a.self::FILE_BOT_CONFIG))) {
      throw BotError::fail($file);
    }
    array_sync($this->data, $a);
    # determine source directory and load handlers
    $this->dirSrc = $this->dirSrcRoot.$cfg['source'].DIRECTORY_SEPARATOR;
    require_once $this->dirSrc.self::FILE_HANDLERS;
    # determine media directories
    $a = self::DIR_IMG.DIRECTORY_SEPARATOR;
    file_exists($b = $this->dirData.$a) && ($this->dirImg[] = $b);
    file_exists($b = $this->dirSrc.$a)  && ($this->dirImg[] = $b);
    $this->dirImg[] = $this->dirInc.$a;
    $a = self::DIR_FONT.DIRECTORY_SEPARATOR;
    file_exists($b = $this->dirData.$a) && ($this->dirFont[] = $b);
    file_exists($b = $this->dirSrc.$a)  && ($this->dirFont[] = $b);
    $this->dirFont[] = $this->dirInc.$a;
    # complete
    $this->file = $file;
    return true;
  }
  # }}}
  function install(array &$o): bool # {{{
  {
    # prepare
    $bot = $this->bot;
    $log = $bot->log->new(__FUNCTION__);
    $src = $o['source'];
    # check source
    $a = $this->dirSrcRoot.$src.DIRECTORY_SEPARATOR;
    $a = [$a.BotCommands::FILE, $a.self::FILE_HANDLERS];
    foreach ($a as $b)
    {
      if (!file_exists($b))
      {
        $log->error("failed\nfile not found: $b");
        return false;
      }
    }
    # check id
    if (!($id = self::checkToken($a = $o['token'])))
    {
      $log->error("failed\nincorrect token: $a");
      return false;
    }
    # request bot information
    if (!($a = $bot->api->send('getMe', null, null, $o['token']))) {
      return false;
    }
    # compose configuration
    $o['id']   = $id;
    $o['name'] = $a->username;
    $o['canJoinGroups'] = $a->can_join_groups ?? false;
    $o['canReadGroups'] = $a->can_read_all_group_messages ?? false;
    $o['isInline']      = $a->supports_inline_queries ?? false;
    # create data directories
    if (!dir_make($a = $this->dirDataRoot.$id) ||
        !dir_make($a.DIRECTORY_SEPARATOR.self::DIR_USER) ||
        !dir_make($a.DIRECTORY_SEPARATOR.self::DIR_GROUP))
    {
      $log->error("failed\ndirectory access: $a");
      return false;
    }
    # store configuration
    $a = $a.DIRECTORY_SEPARATOR.self::FILE_BOT_CONFIG;
    $b = ['Bot' => $o];
    if (!file_set_json($a, $b))
    {
      $log->error("failed\nfile access: $a");
      return false;
    }
    # complete
    $log->info("ok\nsource: $src\nid: $id\nname: ".$o['name']);
    return true;
  }
  # }}}
  function sync(): void # {{{
  {
    if ($this->changed)
    {
      file_set_json($this->file, $this->data);
      $this->changed = false;
    }
  }
  # }}}
  function finit(): void # {{{
  {
    if ($this->file)
    {
      $this->sync();
      $this->file = '';
    }
  }
  # }}}
}
# }}}
abstract class BotConfigAccess implements ArrayAccess # {{{
{
  function offsetExists(mixed $k): bool {
    return true;
  }
  function offsetGet(mixed $k): mixed
  {
    static $data;
    if (!$data) {
      $data = &$this->bot->cfg->data[class_name($this)];
    }
    return $data[$k];
  }
  function offsetSet(mixed $k, mixed $v): void
  {
    static $cfg,$data;
    if (!$data)
    {
      $cfg  = $this->bot->cfg;
      $data = &$cfg->data[class_name($this)];
    }
    $data[$k] = $v;
    $cfg->changed = true;
  }
  function offsetUnset(mixed $k): void
  {}
}
# }}}
class BotConsole # {{{
{
  # {{{
  const
    UUID     = 'b0778b0492bb482dad6cde6ef72308f1',
    FILE_PID = 'console.pid',
    TIMEOUT  = 1000,
    BUF_SIZE = 32000; # lines=80*400
  public
    $bot,$file,$active,
    $lock,$membuf,$locked = false;
  # }}}
  static function construct(object $bot, bool $master): object # {{{
  {
    # create specific instance
    $I = $master
      ? new BotMasterConsole()
      : new self();
    # set base props
    $I->bot    = $bot;
    $I->file   = $bot->cfg->dirDataRoot.self::FILE_PID;
    $I->active = new SyncEvent(self::UUID, 1, 0);
    $I->lock   = new SyncReaderWriter(self::UUID);
    $I->membuf = new SyncSharedMemory(self::UUID, self::BUF_SIZE);
    # initialize buffer
    if ($I->membuf->first()) {
      $I->membuf->write(pack('L', 0), 0);
    }
    # done
    return $I;
  }
  # }}}
  function init(): bool # {{{
  {
    return true;
  }
  # }}}
  function write(string $text): void # {{{
  {
    if ($this->active->wait(0) && ($size = strlen($text)))
    {
      # lock
      if (!($this->locked = $this->lock->readlock(self::TIMEOUT))) {
        throw BotError::fail("timed out\nSyncReaderWriter::readlock");
      }
      # determine size and offset
      $a = unpack('L', $this->membuf->read(0, 4))[1];
      $b = 4 + $a;
      # check overflow
      if (($c = self::BUF_SIZE - $b - $size) < 0 &&
          ($size = $size + $c) > 0)
      {
        $text = substr($text, 0, $size);
      }
      # write
      if ($size > 0)
      {
        $this->membuf->write(pack('L', $a + $size), 0);
        $this->membuf->write($text, $b);
      }
      # unlock
      if ($this->lock->readunlock()) {
        $this->locked = false;
      }
    }
  }
  # }}}
  function finit(): void # {{{
  {
    $this->locked && $this->lock->readunlock();
  }
  # }}}
}
# }}}
class BotMasterConsole extends BotConsole # {{{
{
  function write(string $text): void # {{{
  {
    fwrite(STDOUT, $text);
  }
  # }}}
  function read(): void # {{{
  {
    # lock
    if (!($this->locked = $this->lock->writelock(self::TIMEOUT))) {
      throw BotError::fail("timed out\nSyncReaderWriter::writelock");
    }
    # determine and check size
    $a = unpack('L', $this->membuf->read(0, 4))[1];
    $b = self::BUF_SIZE - 4;
    if ($a < 0 || $a > $b) {
      throw BotError::fail("incorrect buffer size: $a");
    }
    # flush
    fwrite(STDOUT, $this->membuf->read(4, $a));
    $this->membuf->write(pack('L', 0), 0);
    # check overflow
    if ($a === $b)
    {
      fwrite(STDOUT, "...\n");
      $this->bot->log->warn('overflow');
    }
    # unlock
    if ($this->lock->writeunlock()) {
      $this->locked = false;
    }
  }
  # }}}
  function finit(): void # {{{
  {
    # display remaining logs
    try {
      $this->read();
    }
    catch (Throwable)
    {}
    # unlock
    $this->locked && $this->lock->writeunlock();
  }
  # }}}
}
# }}}
class BotLog # {{{
{
  # {{{
  # ●◆◎∙ ▶▼ ■▄ ◥◢◤◣  ►◄
  const
    COLOR  = ['green','red','yellow'],  # [info,error,warn]
    SEP    = ['►','◄'],                 # [output,input]
    PROMPT = ['◆','◆','cyan'];          # [linePrefix,blockPrefix,color]
  public
    $errorCount = 0;
  # }}}
  static function fgColor(string $str, string $name, int $strong=0): string # {{{
  {
    static $z = '[0m';
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
    $x = '['.$COLOR[$name][$strong].'m';
    return strpos($str, $z)
      ? $x.str_replace($z, $z.$x, $str).$z
      : $x.$str.$z;
  }
  # }}}
  static function bgColor(string $str, string $name, int $strong=0): string # {{{
  {
    static $z = '[0m';
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
    $x = '['.$COLOR[$name][$strong].'m';
    if (strpos($str, $z)) {
      $str = str_replace($z, $z.$x, $str);
    }
    return (strpos($str, "\n") === false)
      ? $x.$str.$z
      : $x.str_replace("\n", "$z\n$x", $str).$z;
  }
  # }}}
  static function clearColors(string $s): string # {{{
  {
    return (strpos($s, '[') === false)
      ? $s : preg_replace('/\\[\\d+m/', '', $s);
  }
  # }}}
  static function parseCommands(# {{{
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
        $x .= self::parseCommands($a->items, $pad, $color, $indent, $level + 1);
        array_pop($indent);
      }
    }
    return $x;
  }
  # }}}
  static function parseBlock(string $s): string # {{{
  {
    static $x;
    !$x && ($x = [
      self::fgColor('└┬', self::PROMPT[2], 1),
      self::fgColor(' ├', self::PROMPT[2], 1),
      self::fgColor(' └', self::PROMPT[2], 1),
      self::fgColor('└┐', self::PROMPT[2], 1),
      self::fgColor(' │', self::PROMPT[2], 1),
      self::fgColor('└─', self::PROMPT[2], 1),
    ]);
    # split and determine the last line
    $s = explode("\n", trim($s));
    for ($j = count($s) - 1; $j; --$j)
    {
      # clear any colors
      $a = self::clearColors($s[$j]);
      # stop at pad/block character
      if (strlen($a) && strpos(' └', $a[0]) === false) {
        break;
      }
      # pad otherwise
      $s[$j] = '  '.$s[$j];
    }
    # check at the first line
    if (!$j) {
      return $x[5].implode("\n", $s);
    }
    # compose the block
    for ($i = 0; $i < $j; ++$i)
    {
      $k = (strlen($s[$i]) && !ctype_space($s[$i][0])) ? 0 : 3;
      $i && $k++;
      $s[$i] = $x[$k].$s[$i];
    }
    $s[$j] = $x[2].$s[$j];
    return implode("\n", $s);
  }
  # }}}
  function __construct(# {{{
    public object   $bot,
    public string   $name   = '',
    public ?object  $parent = null
  ) {}
  # }}}
  function init(): bool # {{{
  {
    $this->name = $this->name ? $this->bot['name'] : 'console';
    return true;
  }
  # }}}
  function new(string $name): self # {{{
  {
    return new self($this->bot, $name, $this);
  }
  # }}}
  function consoleBanner(): void # {{{
  {
    $a = $this->bot->cfg->isProduction;
    $b = $a ? 'Production' : 'Development';
    $c = $a ? 'close' : 'restart';
    $a = <<<EOD
███████████████████████████████████████ $b
█─▄▄▄▄█▄─▀█▀─▄█▀▀▀▀▀██▄─▄─▀█─▄▄─█─▄─▄─█ [1mCtrl+C[0m ~ $c
█▄▄▄▄─██─█▄█─██████████─▄─▀█─██─███─███ [1mCtrl+Break[0m ~ stop
▀▄▄▄▄▄▀▄▄▄▀▄▄▄▀▀▀▀▀▀▀▀▄▄▄▄▀▀▄▄▄▄▀▀▄▄▄▀▀

EOD;
    $a = self::fgColor($a, 'cyan');
    $this->bot->console->write($a);
  }
  # }}}
  function commands(): void # {{{
  {
    $this->bot->console->write(
      self::fgColor(self::PROMPT[1], self::PROMPT[2], 1)."\n".
      self::parseCommands($this->bot->cmd->tree, 0, self::PROMPT[2])
    );
  }
  # }}}
  function out(int $level, int $sep, string ...$msg): void # {{{
  {
    # file output
    if (0)
    {
      #$a = date(DATE_ATOM).': ';
      #$b = $name ? implode(' '.$PREFIX, $name) : '';
      #file_put_contents($f, $a.$b.$msg."\n", FILE_APPEND);
    }
    # console output
    # compose name chain
    $c = self::COLOR[$level];
    $s = self::fgColor(self::SEP[$sep], $c);
    $x = '';
    $p = $this;
    while ($p->parent)
    {
      #$n = $level
      #  ? self::bgColor($p->name, $c)
      #  : self::fgColor($p->name, $c);
      $n = self::fgColor($p->name, $c);
      $x = "$n $s $x";
      $p = $p->parent;
    }
    # compose msg chain
    for ($i = 0, $j = count($msg) - 1; $i < $j; ++$i) {
      #$x = $x.self::bgColor($msg[$i], $c)." $s ";
      $x = $x.self::fgColor($msg[$i], $c, 1)." $s ";
    }
    # compose block
    $x = $x.rtrim($msg[$j]);
    if (($n = strpos($x, "\n")) > 0)
    {
      $x = substr($x, 0, ++$n).self::parseBlock(substr($x, $n));
      $n = 1;
    }
    else {
      $n = 0;
    }
    # output
    $this->bot->console->write(
      self::fgColor(self::PROMPT[$n], self::PROMPT[2], 1).
      self::fgColor($p->name, self::PROMPT[2], 0).
      " $s $x\n"
    );
  }
  # }}}
  function info(string ...$msg): void # {{{
  {
    $this->out(0, 0, ...$msg);
  }
  # }}}
  function infoInput(string ...$msg): void # {{{
  {
    $this->out(0, 1, ...$msg);
  }
  # }}}
  function error(string ...$msg): void # {{{
  {
    $this->out(1, 0, ...$msg);
    $this->errorCount += 1;
  }
  # }}}
  function errorInput(string ...$msg): void # {{{
  {
    $this->out(1, 1, ...$msg);
    $this->errorCount += 1;
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
  function warnInput(string ...$msg): void # {{{
  {
    $this->out(2, 1, ...$msg);
  }
  # }}}
  function exception(object $e): bool # {{{
  {
    # handle bot error
    if ($e instanceof BotError)
    {
      if ($e->origin) {
        $this->exception($e->origin);
      }
      if ($e->msg)
      {
        if ($e->level) {
          $this->error(...$e->msg);
        }
        else {
          $this->warn(...$e->msg);
        }
      }
      if ($e->next) {
        $this->exception($e->next);
      }
      return ($e->level !== 0);
    }
    # handle standard error/exception,
    # compose first line
    $a = $e->getMessage()."\n";
    $a = $a.$e->getFile().'('.$e->getLine().")\n";
    # compose trace
    if ($trace = $e->getTrace())
    {
      $b = '';
      foreach ($trace as $c)
      {
        $b .= isset($c['file'])
          ? $c['file'].'('.$c['line'].')'
          : 'INTERNAL';
        $b .= isset($c['class'])
          ? ': '.$c['class'].$c['type'].$c['function']
          : ': '.$c['function'];
        $b .= "\n";
      }
      $a .= $b;
    }
    $a = str_replace(__DIR__.DIRECTORY_SEPARATOR, '', $a);
    $this->out(1, 0, '✶', get_class($e), $a);
    $this->errorCount += 1;
    return true;
  }
  # }}}
  function dump(mixed $var): void # {{{
  {
    if ($proc = $this->bot->proc) {
      $proc->out(var_export($var, true)."\n");
    }
  }
  # }}}
  function finit(): void # {{{
  {}
  # }}}
}
# }}}
class BotApi extends BotConfigAccess # {{{
{
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
    CURLOPT_HTTP_VERSION     => CURL_HTTP_VERSION_1_1,
    CURLMOPT_PIPELINING      => 0,
    CURLOPT_PIPEWAIT         => false,
    CURLOPT_FOLLOWLOCATION   => false,
    #CURLOPT_NOSIGNAL         => true,
    #CURLOPT_VERBOSE          => true,
    CURLOPT_SSL_VERIFYSTATUS => false,# require OCSP during the TLS handshake?
    CURLOPT_SSL_VERIFYHOST   => 0,# are you afraid of MITM?
    CURLOPT_SSL_VERIFYPEER   => false,# disallow self-signed certs?
    # }}}
  ];
  public $log,$curl,$murl,$query,$poll;
  static function getError(object $curl): string # {{{
  {
    return ($e = curl_errno($curl))
      ? "($e) ".curl_error($curl) : '';
  }
  # }}}
  function __construct(public object $bot) # {{{
  {
    $this->log = $bot->log->new('api');
  }
  # }}}
  function init(): bool # {{{
  {
    try
    {
      if (!($this->curl = curl_init())) {
        throw BotError::fail('curl_init()');
      }
      if (!curl_setopt_array($this->curl, self::CONFIG)) {
        throw BotError::fail("curl_setopt_array()\n".self::getError($this->curl));
      }
    }
    catch (Throwable $e)
    {
      $this->log->exception($e);
      $this->finit();
      return false;
    }
    return true;
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
      $token || ($token = $this->bot['token']);
      # set request parameters
      $req = [
        CURLOPT_URL  => $this['baseUrl'].$token.'/'.$method,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $req,
      ];
      if (!curl_setopt_array($this->curl, $req)) {
        throw BotError::fail($method, 'failed to setup the request');
      }
      # send
      if (($x = curl_exec($this->curl)) === false) {
        throw BotError::fail($method, 'curl failed('.curl_errno($this->curl).'): '.curl_error($this->curl));
      }
      # decode response
      if (!($res = json_decode($x, false))) {
        throw BotError::fail($method, "incorrect JSON response\n$x");
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
          $x = "incorrect response\n$x";
        }
        throw BotError::fail($method, $x);
      }
      # success
      $res = $res->result;
    }
    catch (Throwable $e)
    {
      # failure
      $this->log->exception($e);
      $res = false;
    }
    # delete temporary file
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
  function getUpdates(): ?object # {{{
  {
    static $PAUSE=0, $FAILS=0;
    try
    {
      # initialize
      if (!$this->murl && !$this->getUpdatesInit()) {
        throw BotError::fail('failed to initialize');
      }
      # check paused
      if ($PAUSE)
      {
        if ($PAUSE > time()) {
          return null;
        }
        $PAUSE = 0;
      }
      # perform long polling
      if ($this->poll && $this->poll->valid()) {
        $this->poll->send(1);# continue
      }
      else
      {
        # start or re-start
        $a = $this->query[CURLOPT_POSTFIELDS]['timeout'];
        $this->poll = $this->getUpdatesPoll($a);
      }
      # check complete
      if ($this->poll->valid()) {
        return null;
      }
      # check failed
      if ($a = $this->poll->getReturn())
      {
        # manage non-critical failure
        if (!$a->level && ++$FAILS > 1)
        {
          if (($b = $this['maxFails']) && $FAILS > $b) {
            $a->push(BotError::fail("too many failures ($b)"));
          }
          else {# set retry delay
            $PAUSE = time() + $this['pause'];
          }
        }
        throw $a;
      }
      # transmission successful,
      # clear repeated fails counter
      if ($FAILS)
      {
        $this->log->info("recovered ($FAILS)");
        $FAILS = 0;
      }
      # check HTTP response code
      if (($a = curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE)) === false) {
        throw BotError::fail('curl_getinfo() failed');
      }
      if ($a !== 200) {
        throw BotError::fail("unsuccessful HTTP status: $a");
      }
      # get response text
      if (!($a = curl_multi_getcontent($this->curl))) {
        return null;
      }
      # decode
      if (($x = json_decode($a, false)) === null &&
          ($x = json_last_error()) !== JSON_ERROR_NONE)
      {
        throw BotError::fail("json_decode[$x]: ".json_last_error_msg()."\n█{$a}█\n");
      }
      # validate response and check its status
      if (!is_object($x) || !isset($x->ok)) {
        throw BotError::fail("incorrect response\n█{$a}█\n");
      }
      if (!$x->ok) {
        throw BotError::fail(($x->description ?? "unsuccessful response"));
      }
      if (!isset($x->result) || !is_array($x->result)) {
        throw BotError::fail("incorrect response\n█{$a}█\n");
      }
      # shift next query offset
      if (~($a = count($x->result) - 1)) {
        $this->query[CURLOPT_POSTFIELDS]['offset'] = 1 + $x->result[$a]->update_id;
      }
    }
    catch (Throwable $e)
    {
      $this->log->exception($e);
      $x = BotError::from($e);
    }
    return $x;
  }
  # }}}
  function getUpdatesInit(): bool # {{{
  {
    try
    {
      # create multi-curl instance
      if (!($this->murl = curl_multi_init())) {
        throw BotError::fail('curl_multi_init() failed');
      }
      # create query
      $this->query = [
        CURLOPT_URL  => $this['baseUrl'].$this->bot['token'].'/getUpdates',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
          'offset'  => 0,
          'limit'   => $this['limit'],
          'timeout' => $this['timeout'],
        ],
      ];
    }
    catch (Throwable $e)
    {
      # report
      $this->log->exception($e);
      # cleanup
      if ($this->murl)
      {
        curl_multi_close($this->murl);
        $this->murl = null;
      }
      return false;
    }
    return true;
  }
  # }}}
  function getUpdatesPoll(int $timeout, float $wait = 0): Generator # {{{
  {
    try
    {
      # prepare
      $error   = null;
      $started = 0;
      # set query
      if (!curl_setopt_array($this->curl, $this->query))
      {
        throw BotError::fail('curl_setopt_array() failed'.
          (curl_errno($this->curl) ? ': '.curl_error($this->curl) : '')
        );
      }
      # attach handles
      if ($a = curl_multi_add_handle($this->murl, $this->curl)) {
        throw BotError::fail('curl_multi_add_handle() failed: '.curl_multi_strerror($a));
      }
      # start polling
      $started = time();
      $running = 1;
      while (1)
      {
        # execute request
        if ($a = curl_multi_exec($this->murl, $running)) {
          throw BotError::fail("curl_multi_exec[$a]: ".curl_multi_strerror($a));
        }
        # check finished
        if (!$running) {
          break;
        }
        # wait for activity
        while (!$a)
        {
          if (($a = curl_multi_select($this->murl, $wait)) < 0)
          {
            throw BotError::fail(
              ($a = curl_multi_errno($this->murl))
                ? "curl_multi_select[$a]: ".curl_multi_strerror($a)
                : 'system select failed'
            );
          }
          # check response timeout
          if ($timeout && (time() - $started > $timeout)) {
            throw BotError::warn("response timed out ($timeout)");
          }
          # ask for continuation
          if (!yield) {
            throw BotError::skip();
          }
        }
      }
      # check transfer status
      if (!($a = curl_multi_info_read($this->murl))) {
        throw BotError::fail('curl_multi_info_read() failed');
      }
      if ($a = $a['result']) {
        throw BotError::warn('transfer failed: '.curl_strerror($a));
      }
    }
    catch (Throwable $error)
    {}
    # detach handles
    if ($started && ($a = curl_multi_remove_handle($this->murl, $this->curl)))
    {
      $a = BotError::fail("curl_multi_remove_handle[$a]: ".curl_multi_strerror($a));
      $error = $error ? $error->push($a) : $a;
    }
    # complete
    return $error;
  }
  # }}}
  function getUpdatesFinit(): void # {{{
  {
    if ($this->murl)
    {
      # gracefully terminate polling
      if ($this->poll)
      {
        # complete poll if running
        $this->poll->valid() &&
        $this->poll->send(0);
        # save the last offset at remote (if it was changed)
        if (($a = &$this->query[CURLOPT_POSTFIELDS])['offset'])
        {
          $a['limit']   = 1;
          $a['timeout'] = 0;
          curl_setopt_array($this->curl, $this->query) &&
          curl_exec($this->curl);
        }
        unset($a);
      }
      # cleanup
      curl_multi_close($this->murl);
      $this->murl = $this->query = $this->poll = null;
    }
  }
  # }}}
  function finit(): void # {{{
  {
    $this->getUpdatesFinit();
    if ($this->curl)
    {
      curl_close($this->curl);
      $this->curl = null;
    }
  }
  # }}}
}
# }}}
class BotApiFile extends CURLFile # {{{
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
class BotText implements ArrayAccess # {{{
{
  # {{{
  const
    DIR_PARSER    = 'sm-mustache',
    FILE_PARSER   = 'mustache.php',
    FILE_TEXTS    = 'texts.inc',
    FILE_CAPS     = 'captions.inc',
    FILE_EMOJIS   = 'emojis.inc',
    LANG_DEF      = 'en',
    DELIMS_REFINE = '{: :}';
  public
    $log,$helpers,
    $tp,$texts,$langs,$caps,$emoji;
  # }}}
  static function check(): string # {{{
  {
    $a = BotConfig::getIncDir();
    $b = [
      $a.self::DIR_PARSER.DIRECTORY_SEPARATOR.self::FILE_PARSER,
      $a.self::FILE_TEXTS,
      $a.self::FILE_CAPS,
      $a.self::FILE_EMOJIS,
    ];
    foreach ($b as $a)
    {
      if (!file_exists($a)) {
        return "file not found: $a";
      }
    }
    return '';
  }
  # }}}
  function __construct(public object $bot) # {{{
  {
    $this->log = $bot->log->new('text');
    $this->helpers = new BotTextHelpers();
  }
  # }}}
  function init(): bool # {{{
  {
    # prepare
    $cfg = $this->bot->cfg;
    $inc = $cfg->dirInc;
    # load and create template parser
    require_once $inc.self::DIR_PARSER.DIRECTORY_SEPARATOR.self::FILE_PARSER;
    $this->tp = Mustache::construct([
      'logger'  => Closure::fromCallable([$this->log, 'errorOnly']),
      'helpers' => &$this->helpers,
    ]);
    if (!$this->tp) {
      return false;
    }
    # load default texts
    $this->texts = file_get_array($inc.self::FILE_TEXTS);
    $this->langs = array_keys($this->texts);
    $this->caps  = file_get_array($inc.self::FILE_CAPS);
    $this->emoji = file_get_array($inc.self::FILE_EMOJIS);
    # add emoji helper
    $this->helpers->push($this->emoji);
    # merge with bot sources
    $a = $cfg->dirSrc;
    if ($b = file_get_array($a.self::FILE_TEXTS)) {
      array_sync_merge($this->texts, $b);
    }
    if ($b = file_get_array($a.self::FILE_CAPS)) {
      array_sync_merge($this->texts, $b);
    }
    # refine
    $this->refine($this->texts);
    $this->refine($this->caps);
    return true;
  }
  # }}}
  function refine(array &$a): void # {{{
  {
    foreach ($a as &$b)
    {
      if (is_array($b)) {
        $this->refine($b);
      }
      else # string
      {
        if (strpos($b, "\n") !== false) {
          $b = preg_replace('/\n\s+/', '', str_replace("\r", '', trim($b)));
        }
        $b = $this->tp->render($b, self::DELIMS_REFINE, []);
      }
    }
  }
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
  function finit(): void # {{{
  {}
  # }}}
}
# }}}
class BotTextHelpers # {{{
{
  public $data = [];
  function __construct() # {{{
  {
    $this->data[] = [
      'NBSP'    => "\xC2\xA0",# non-breakable space
      'ZWSP'    => "\xE2\x80\x8B",# zero-width space
      'ZS'      => "\xE3\x80\x80",# Ideographic Space
      'LINEPAD' => "\xC2\xAD".str_repeat(' ', 120)."\xC2\xAD",
      'BEG'     => "\xC2\xAD\n",
      'END'     => "\xC2\xAD",# SOFT HYPHEN U+00AD
      'BR'      => "\n",
    ];
  }
  # }}}
  function __isset(string $k): bool # {{{
  {
    foreach ($this->data as &$a) {
      if (isset($a[$k])) {return true;}
    }
    return false;
  }
  # }}}
  function __get(string $k): mixed # {{{
  {
    foreach ($this->data as &$a) {
      if (isset($a[$k])) {return $a[$k];}
    }
    return null;
  }
  # }}}
  function push(array &$a): void # {{{
  {
    array_unshift($this->data, null);
    $this->data[0] = &$a;
  }
  # }}}
  function pop(): void # {{{
  {
    array_shift($this->data);
  }
  # }}}
}
# }}}
class BotCommands implements ArrayAccess # {{{
{
  # {{{
  const
    FILE     = 'commands.inc',
    ITEM_DEF = 'BotImgItem',
    NS_PATH  = '\\'.__NAMESPACE__.'\\';
  public
    $log,$tree,$map = [];
  # }}}
  function __construct(public object $bot) # {{{
  {
    $this->log = $bot->log->new('cmd');
  }
  # }}}
  function init(): bool # {{{
  {
    # prepare
    $cfg = $this->bot->cfg;
    if (!file_exists($file = $cfg->dirData.self::FILE)) {
      $file = $cfg->dirSrc.self::FILE;
    }
    # load commands tree
    if (!($this->tree = file_get_array($file)) ||
        !$this->build($this->tree))
    {
      $this->log->error($file);
      return false;
    }
    return true;
  }
  # }}}
  function build(array &$tree, ?object &$parent = null): bool # {{{
  {
    foreach ($tree as $name => &$node)
    {
      # determine base
      $node['name'] = $name;
      if ($parent)
      {
        $node['id']   = $id   = $parent->id.$name;
        $node['path'] = $path = $parent->skel['path'].'/'.$name;
      }
      else
      {
        $node['id']   = $id   = $name;
        $node['path'] = $path = '/'.$name;
      }
      # determine item type (class)
      $node[$a = 'type'] = $type = isset($node[$a])
        ? 'Bot'.ucfirst($node[$a]).'Item'
        : self::ITEM_DEF;
      if (!class_exists($type = self::NS_PATH.$type, false))
      {
        $this->log->error($path, "unknown type: $type");
        return false;
      }
      # determine item handler
      $node['handler'] = function_exists($a = self::NS_PATH.'BotItem\\'.$id)
        ? $a : '';
      # determine input flag
      if (!isset($node[$a = 'input'])) {
        $node[$a] = method_exists($type, 'inputAccept');
      }
      # invoke custom type refiner
      if (method_exists($type, 'refine') && !$type::refine($node))
      {
        $this->log->error($path, "$type::refine", 'failed');
        return false;
      }
      # refine captions
      if (!isset($node[$a = 'caps'])) {
        $node[$a] = [];
      }
      else {
        $this->bot->text->refine($node[$a]);
      }
      # refine texts
      # set primary language
      if (!isset($node[$a = 'text'])) {
        $node[$a] = ['en'=>[]];
      }
      elseif (!isset($node[$a]['en'])) {
        $node[$a] = ['en'=>$node[$a]];
      }
      $this->bot->text->refine($node[$a]);
      # set secondary languages
      $a = &$node[$a];
      foreach ($this->bot->text->langs as $b)
      {
        if ($b === 'en') {
          continue;
        }
        if (!isset($a[$b])) {
          $a[$b] = $a['en'];# copy primary
        }
        elseif (count($a[$b]) < count($a['en'])) {
          array_sync_new($a[$b], $a['en']);# fill the gaps
        }
      }
      unset($a,$b);
      # construct item (command)
      $node = $this->map[$id] = new $type($this->bot, $node, $parent);
      # construct children (recurse)
      if ($node->items && $this->build($node->items, $node)) {
        return false;
      }
    }
    return true;
  }
  # }}}
  # [map] access {{{
  function offsetExists(mixed $id): bool {
    return isset($this->map[$id]);
  }
  function offsetGet(mixed $id): mixed {
    return $this->map[$id] ?? null;
  }
  function offsetSet(mixed $id, mixed $item): void
  {}
  function offsetUnset(mixed $id): void
  {}
  # }}}
  function finit(): void # {{{
  {}
  # }}}
}
# }}}
class BotFile # {{{
{
  # {{{
  const
    FILE_IDS = 'file_id.json';
  public
    $log,$file,$changed = 0,
    $data = [];
  # }}}
  function __construct(public object $bot) # {{{
  {
    $this->log = $bot->log->new('file');
  }
  # }}}
  function init(): bool # {{{
  {
    # load identifiers
    $this->file = $a = $this->bot->cfg->dirData.self::FILE_IDS;
    if (file_exists($a) && ($this->data = file_get_json()) === null)
    {
      $this->log->error($a);
      return false;
    }
    return true;
    /***
    # load fonts
    $this->font = [];
    foreach ($bot->cfg->dirFont as $dir)
    {
      $i = strlen($dir);
      foreach (glob($dir.'*.ttf', GLOB_NOESCAPE) as $font) {
        $this->font[substr($font, $i)] = $font;
      }
    }
    return true;
    /***/
  }
  # }}}
  function get(string $file): string # {{{
  {
    return $this->data[$file] ?? '';
  }
  # }}}
  function set(string $file, string $id): void # {{{
  {
    $this->data[$file] = $id;
    $this->changed++;
  }
  # }}}
  function sync(): void # {{{
  {
    if (($a = $this->changed) &&
        file_set_json($this->file, $this->data))
    {
      $this->log->info("+$a");
      $this->changed = 0;
    }
  }
  # }}}
  function finit(): void # {{{
  {}
  # }}}
  ###
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
      if (!unlink($file))
      {
        $this->log->error(__FUNCTION__, "unlink($file)");
        return false;
      }
    }
    $this->log->infoInput($file, 'ok');
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
    foreach ($this->bot->cfg->dirImg as $a)
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
}
# }}}
# }}}
# process {{{
class BotProcess extends BotConfigAccess # {{{
{
  # {{{
  const
    UUID = '22c4408d490143b5b29f0640755327db',
    TICK = 200000;# 1/5 sec
  public
    $bot,$id,$log,
    $file,$active,$started = false,
    $map = [],$delay = 200000;
  # }}}
  static function construct(object $bot): object # {{{
  {
    # create specific instance
    $I = $bot->id
      ? ($bot->tid
        ? new BotTaskProcess()
        : new self())
      : new BotConsoleProcess();
    # set base props
    $I->bot  = $bot;
    $I->id   = strval(getmypid());
    $I->log  = $bot->log->new('proc');
    $I->file = $bot->id
      ? $bot->cfg->dirDataRoot.'bot'.$bot->id.'.pid'
      : $bot->console->file;
    $I->active = $bot->id
      ? new SyncEvent($bot->id.$bot->tid.self::UUID, 1, 0)
      : $bot->console->active;
    # done
    return $I;
  }
  # }}}
  function init(): bool # {{{
  {
    try
    {
      # check already running
      if ($this->active->wait(0)) {
        throw BotError::stop("is already running");
      }
      # start self
      if (!($this->started = $this->active->fire())) {
        throw BotError::fail('SyncEvent::fire');
      }
      if (file_put_contents($this->file, $this->id) === false) {
        throw BotError::fail($this->file);
      }
      # start children
      foreach ($this->list() as $a)
      {
        if (!$this->childStart($a)) {
          throw BotError::skip();
        }
      }
    }
    catch (Throwable $e)
    {
      $this->log->exception($e);
      $this->finit();
      return false;
    }
    return true;
  }
  # }}}
  function check(): bool # {{{
  {
    # check child processes
    foreach ($this->map as $id => $child)
    {
      if (!$child->active->wait(0)) {
        unset($this->map[$id]);
      }
    }
    # check self
    return $this->active->wait(0) && file_exists($this->file);
  }
  # }}}
  function tick(): void # {{{
  {
    usleep($this->delay);
  }
  # }}}
  function loop(): void # {{{
  {
    try
    {
      # prepare
      $bot = $this->bot;
      $api = $this->bot->api;
      $bot->log->commands();
      # start event loop
      $i = 0;
      while ($this->check())
      {
        $this->log->info('test #'.strval($i++));
        $this->tick();
        #sleep(2);
        /***
        if ($a = $api->getUpdates())
        {
          if ($a instanceof BotError) {
            break;
          }
          foreach ($a->result as $b) {
            $bot->update($b);
          }
        }
        else {
          $this->tick();
        }
        /***/
      }
    }
    catch (Throwable $e) {
      $this->log->exception($e);
    }
  }
  # }}}
  function list(): array # {{{
  {
    # TODO: tasks
    return [];
  }
  # }}}
  function childStart(string $id): bool # {{{
  {
    if (isset($this->map[$id])) {
      return true;
    }
    if (!($a = BotProcessFork::construct($this, $id))) {
      return false;
    }
    $this->map[$id] = $a;
    return true;
  }
  # }}}
  function childGet(string $id): ?object # {{{
  {
    if (!($slave = $this->map[$id] ?? null)) {
      return null;
    }
    if (!$slave->isRunning())
    {
      $slave->stop();
      unset($this->map[$id]);
      return null;
    }
    return $slave;
  }
  # }}}
  function childStop(string $id): bool # {{{
  {
    if (!($slave = $this->map[$id] ?? null))
    {
      $this->log->warn(__FUNCTION__."($id): was not started");
      return false;
    }
    $slave->stop();
    unset($this->map[$id]);
    return true;
  }
  # }}}
  function finit(): void # {{{
  {
    if ($this->started)
    {
      # save current childmap
      #$this['map'] = array_keys($this->map);
      # stop children
      if ($this->map)
      {
        foreach ($this->map as $child) {
          $child->active->reset();
        }
        $this->map = [];
      }
      # stop self
      $this->started = false;
      $this->active->reset();
      file_unlink($this->file);
    }
  }
  # }}}
}
# }}}
class BotConsoleProcess extends BotProcess # {{{
{
  function loop(): void # {{{
  {
    try
    {
      # prepare
      $bot = $this->bot;
      # enforce graceful termination
      if (function_exists($f = 'sapi_windows_set_ctrl_handler'))
      {
        # WinOS
        $f(function (int $e) use ($bot)
        {
          $e = ($e === PHP_WINDOWS_EVENT_CTRL_C);
          $bot->log->info('signal: '.($e ? 'Ctrl+C' : 'Ctrl+Break'));
          if ($bot->cfg->isProduction)
          {
          }
          $bot->exitcode = $e
            ? 1 # restart
            : 0;# stop
          $bot->finit();
        });
      }
      else
      {
        # NixOS
      }
      # start event loop
      $this->log->consoleBanner();
      while ($this->check())
      {
        $bot->console->read();
        $this->tick();
      }
    }
    catch (Throwable $e) {
      $this->log->exception($e);
    }
  }
  # }}}
  function list(): array # {{{
  {
    # get planned and currently running bots
    $a = $this['list'];
    $b = $this->bot->getActiveIds();
    # combine both lists
    foreach ($b as $c)
    {
      if (!in_array($c, $a, true)) {
        $a[] = $c;
      }
    }
    # add masterbot
    if (!in_array($c = $this->bot['id'], $a, true)) {
      array_unshift($a, $c);
    }
    return $a;
  }
  # }}}
}
# }}}
class BotTaskProcess extends BotProcess # {{{
{
  function loop(): void # {{{
  {
    try
    {
    }
    catch (Throwable $e) {
      $this->log->exception($e);
    }
  }
  # }}}
}
# }}}
class BotProcessFork # {{{
{
  # {{{
  const
    FILE_START = 'start.php',
    PROC_WAIT  = [500,8], # ms,ticks
    PROC_DESC  = [
      #0 => ['pipe','r'],# stdin
      1 => ['pipe','w'],# stdout
      2 => ['pipe','w'],# stderr
    ],
    PROC_OPTS = [# WinOS only
      'suppress_errors' => false,
      'bypass_shell'    => true,
      'blocking_pipes'  => true,
      'create_process_group' => false,# allow child to handle CTRL events?
      'create_new_console'   => false,
    ];
  public
    $proc,$log,$active;
  # }}}
  static function closePipes(?array $list, bool $read = false): string # {{{
  {
    # pipes of a closed process will not block,
    # its safe to read any remaining output
    $text = '';
    if ($list)
    {
      foreach ($list as $a)
      {
        if (is_resource($a))
        {
          if ($read && ($b = fread($a, 8000)) !== false) {
            $text .= rtrim($b)."\n";
          }
          fclose($a);
        }
      }
      unset($a, $b);
    }
    return $text;
  }
  # }}}
  static function construct(object $proc, string $id): ?self # {{{
  {
    try
    {
      # create instance
      $I = new self();
      $I->proc   = $proc;
      $I->log    = $proc->log->new($id);
      $I->active = new SyncEvent($id.BotProcess::UUID, 1, 0);
      # check process is already running or try to start
      if ($I->active->wait(0)) {
        $I->log->info('connected');
      }
      elseif (!$I->start($id)) {
        throw BotError::skip();
      }
    }
    catch (Throwable $e)
    {
      $I->log->exception($e);
      $I = null;
    }
    return $I;
  }
  # }}}
  function start(string $id): bool # {{{
  {
    try
    {
      # create process
      $dir  = __DIR__.DIRECTORY_SEPARATOR;
      $cmd  = '"'.PHP_BINARY.'" -f "'.$dir.self::FILE_START.'" '.$id;
      $pipe = null;
      $proc = proc_open(
        $cmd, self::PROC_DESC, $pipe, $dir, null, self::PROC_OPTS
      );
      # check successful
      if (!is_resource($proc)) {
        throw BotError::fail("failed\nproc_open($cmd)");
      }
      # wait started
      $wait = self::PROC_WAIT;
      while (!$this->active->wait($wait[0]) && --$wait[1])
      {
        if (!($a = proc_get_status($proc))) {
          throw BotError::fail("failed\nproc_get_status()");
        }
        if (!$a['running'])
        {
          $a = 'exitcode: '.$a['exitcode'];
          if ($b = self::closePipes($pipe, true)) {
            $a .= "\noutput:\n".str_replace("\n", "\n ", $b);
          }
          throw BotError::fail("failed\n$a");
        }
      }
      # check successful
      if (!$wait[1])
      {
        proc_terminate($proc);# send termination signal
        throw BotError::fail("unresponsive! (timed out)");
      }
      # complete
      self::closePipes($pipe);
      unset($proc, $pipe);
      # from now on, process must not output anything to STDOUT/ERR,
      # otherwise it may broke (because of writing to closed resource)
      $this->log->info('started');
    }
    catch (Throwable $e)
    {
      $this->log->exception($e);
      return false;
    }
    return true;
  }
  # }}}
  function stop(): bool # {{{
  {
    return true;
  }
  # }}}
}
# }}}
# }}}
# request {{{
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
    $this->log = $user->log->new(class_name($this));
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
      $item = $user->cfg->queueMessageItem($a->message_id);
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
# }}}
# user {{{
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
      ? $bot->cfg->dirGrp.$chat->id.DIRECTORY_SEPARATOR
      : $bot->cfg->dirUsr.$id.DIRECTORY_SEPARATOR;
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
    catch (Throwable $e)
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
    catch (Throwable $e)
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
    catch (Throwable $e)
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
    catch (Throwable $e)
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
    $user = $this->user;
    $bot  = $user->bot;
    $file = $user->dir.self::FILE_JSON;
    # aquire a lock
    if (!($this->lock = file_lock($file)))
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
      file_unlock($file);
      # cleanup
      $this->lock = '';
      $this->queue = [];
      $this->items = [];
      $this->changed = false;
    }
    return $ok;
  }
  # }}}
###
  function queueMessageItem(int $messageId): ?object # {{{
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
  function queueItem(object $item): int # {{{
  {
    foreach ($this->queue as $i => $m)
    {
      if ($m->item === $item) {
        return $i;
      }
    }
    return -1;
  }
  # }}}
  function queueItemRoot(object $item): int # {{{
  {
    # search by item and item's root
    foreach ($this->queue as $i => $m)
    {
      if ($m->item === $item || $m->item->root === $item->root) {
        return $i;
      }
    }
    # not found
    return -1;
  }
  # }}}
  function queueGet(object $item): ?object # {{{
  {
    return ~($i = $this->queueItemRoot($item))
      ? $this->queue[$i]
      : null;
  }
  # }}}
  function queueRemove(object $item): bool # {{{
  {
    if (~($i = $this->queueItemRoot($item)))
    {
      array_splice($this->queue, $i, 1);
      return $this->changed = true;
    }
    return false;
  }
  # }}}
  function queuePop(object $item): ?object # {{{
  {
    if (~($i = $this->queueItemRoot($item)))
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
class BotUserMessages implements JsonSerializable # {{{
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
# }}}
# message {{{
abstract class BotMessage extends BotConfigAccess implements JsonSerializable # {{{
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
        throw BotError::fail('imagecreatetruecolor() failed');
      }
      # allocate color
      if (($c = imagecolorallocate($img, $color[0], $color[1], $color[2])) === false) {
        throw BotError::fail('imagecolorallocate() failed');
      }
      # fill the background
      if (!imagefill($img, 0, 0, $c)) {
        throw BotError::fail('imagefill() failed');
      }
    }
    catch (Throwable $e)
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
        throw BotError::fail('imageftbbox() failed');
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
      throw BotError::fail('imagecolorallocate() failed');
    }
    # draw
    if (!imagefttext($img, $maxSize, 0, $x, $y, $c, $font, $text)) {
      throw BotError::fail('imagefttext() failed');
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
      throw BotError::fail('imagecolorallocate() failed');
    }
    # draw
    if (!imagefttext($img, $fontSize, 0, $point[0], $point[1], $color, $font, $text)) {
      throw BotError::fail('imagefttext() failed');
    }
  }
  # }}}
  static function imgFile(object $img): object # {{{
  {
    if (!($file = tempnam(sys_get_temp_dir(), 'img')) ||
        !imagejpeg($img, $file) || !file_exists($file))
    {
      throw BotError::fail("imagejpeg($file) failed");
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
    catch (Throwable $e) {
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
          throw BotError::fail("imagecreatefromjpeg($a) failed");
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
    catch (Throwable $e) {
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
# }}}
# item base {{{
abstract class BotItem implements ArrayAccess, JsonSerializable # {{{
{
  public
    $root,$id,$text,$caps,$items,
    $user,$log,$cfg,$data,$input,$msgs;
  ###
  function __construct(# {{{
    public object   $bot,
    public array    $skel,
    public ?object  $parent
  )
  {
    $this->root = $parent ? $parent->root : $this;
    $this->id   = $skel['id'];
    $this->text = new BotItemText($this);
    $this->caps = new BotItemCaptions($this);
    if (isset($skel[$a = 'items'])) {
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
    # merge with custom options specified in command tree
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
  function isFirstRender(): bool # {{{
  {
    return ($this->user->cfg->queueItem($this) === -1);
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
    # render guarded
    try
    {
      if (($item = $this->render($func, $args)) !== $this) {
        $this->detach($item !== null);# redirected or failed
      }
    }
    catch (Throwable $e)
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
  # [BotUserConfig] access {{{
  function offsetExists(mixed $k): bool {
    return isset($this->cfg[$k]);
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
  {
    if (isset($this->cfg[$k]))
    {
      unset($this->cfg[$k]);
      $this->user->cfg->changed = true;
    }
  }
  # }}}
  abstract function render(string $func, string $args): ?object;
}
# }}}
class BotItemText implements ArrayAccess # {{{
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
class BotItemCaptions implements ArrayAccess # {{{
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
class BotItemData implements ArrayAccess # {{{
{
  static function construct(object $item, ?array &$data): ?self # {{{
  {
    # determine storage source
    if ($file = $item->skel['datafile'] ?? 0)
    {
      $file = ($file === 1)
        ? $item->user->dir # private
        : $item->bot->cfg->dirData; # public
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
  # access {{{
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
# }}}
# items (components) {{{
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
  const STATUS = [# {{{
    -3 => 'progress',
    -2 => 'failure',
    -1 => 'miss',
     0 => 'input',
     1 => 'confirmation',
     2 => 'complete',
  ];
  # }}}
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
    # initialize on entry
    $cfg = &$this->config();
    if ($this->isFirstRender() && !$this->init($cfg)) {
      return null;
    }
    # get current state
    $state = [
      ($this['status'] ?? 0),
      ($this['step']   ?? 0),
      ($this['field']  ?? 0),
    ];
    # create vars
    $bot  = $this->bot;
    $data = $this->data;
    $hand = $this->skel['handler'];
    $info = '';
    $progress  = 0;
    $fieldOpts = null;# fetched once
    # }}}
    # operate {{{
    switch ($func) {
    case '':
    case 'refresh':
      break;
    case 'back':
    case 'prev':
      $this->fieldBack($cfg, $state);
      break;
    case 'forward':
    case 'next':
      $this->fieldForward($cfg, $state, $info);
      break;
    case 'select':
      $this->fieldSetOption($cfg, $state, $info, $fieldOpts, $args);
      break;
    case 'input':
    case 'clear':
      if (!$this->inputAccept($cfg, $state, $info)) {
        return null;
      }
      break;
    case 'ok':
    case 'submit':
    case 'retry':
      if (!$this->dataSubmit($cfg, $state, $info)) {
        return null;
      }
      break;
    case 'reset':
      if (!$this->dataReset($cfg, $state)) {
        return null;
      }
      break;
    case 'change':
    case 'repeat':
      if (!$this->dataChange($cfg, $state)) {
        return null;
      }
      break;
    default:
      $this->log->error("operation unknown: $func");
      return null;
    }
    # }}}
    # sync {{{
    $a = '';
    if ($state[0] !== ($c = $this[$b = 'status']))
    {
      $a .= self::STATUS[$state[0]].'→'.self::STATUS[$c].' ';
      $state[0] = $c;
    }
    if ($state[1] !== ($c = $this[$b = 'step']))
    {
      $a .= $b.':'.$state[1].'→'.$c.' ';
      $state[1] = $c;
    }
    if ($state[2] !== ($c = $this[$b = 'field']))
    {
      $a .= $b.':'.$state[2].'→'.$c.' ';
      $state[2] = $c;
    }
    # report change
    $a && $this->log->info($func, $a);
    # determine field vars
    $fields         = &$this->skel['fields'][$state[1]];
    $fieldMissCount = $this->fieldMissCount($fields);
    $fieldName      = array_key($fields, $state[2]);
    $field          = &$fields[$fieldName];
    $fieldIsLast    = $state[2] === count($fields) - 1;
    # }}}
    # compose markup {{{
    if ($state[0])
    {
      # results display,
      # determine markup type
      $a = ($state[0] < 0)
        ? 'failure'
        : ($state[0] === 1
          ? 'confirm'
          : 'success');
      # get markup
      $mkup = isset($this->skel[$b = 'markup'][$a])
        ? $this->skel[$b][$a]
        : $cfg[$b][$a];
      # determine flags
      $mkupFlags = [
        'retry' => $cfg['retryFailed']
          ? (($state[0] === -2)
            ? 1
            : ($cfg['retryDisable'] ? -1 : 0))
          : 0,
        'repeat' => $cfg['isPersistent'] ? 0 : 1,
        'change' => $cfg['isPersistent'] ? 1 : 0,
      ];
    }
    else
    {
      # data input,
      # get markup
      $mkup = isset($this->skel[$a = 'markup'][$b = 'input'])
        ? $this->skel[$a][$b]
        : $cfg[$a][$b];
      # determine flags
      $mkupFlags = [
        'back' => ($state[2] === 0)
          ? (($cfg['moveAround'] || !$cfg['backDisable']) ? 1 : -1)
          : 1,
        'forward' => $cfg['okIsForward']
          ? 0
          : ($cfg['forwardToOk']
            ? ($fieldIsLast
              ? ($fieldMissCount
                ? (($cfg['moveAround'] || !$cfg['forwardDisable']) ? 1 : -1)
                : 0)
              : 1)
            : ($fieldIsLast
              ? (($cfg['moveAround'] || !$cfg['forwardDisable']) ? 1 : -1)
              : 1)),
        'ok' => $cfg['okIsForward']
          ? 1
          : ($cfg['forwardToOk']
            ? ($fieldIsLast
              ? ($fieldMissCount ? 0 : 1)
              : 0)
            : ($cfg['okWhenReady']
              ? ($fieldMissCount
                ? ($cfg['okDisable'] ? -1 : 0)
                : 1)
              : 1)),
        'clear' => ($fields[$fieldName][0] & 4)
          ? (isset($data[$fieldName])
            ? 1
            : ($cfg['clearSolid'] ? -1 : 0))
          : ($cfg['clearSolid'] ? -1 : 0),
      ];
      # alias
      $mkupFlags['prev']   = $mkupFlags['back'];
      $mkupFlags['next']   = $mkupFlags['forward'];
      $mkupFlags['submit'] = $mkupFlags['ok'];
    }
    # }}}
    # compose text {{{
    $a = 'description';
    $text = $this->text["#$a"] ?: '';
    $hand && $hand($this, $a, $text, $state);
    if (!$info && $state[0] < 0)
    {
      switch ($state[0]) {
      case -1:
        $info = $bot->text['req-miss'];
        break;
      case -2:
        $info = $bot->text['op-fail'];
        break;
      }
    }
    $text = $this->text->render([
      'description' => $text,
      'complete' => $this->fieldsGetComplete($cfg, $state),
      'current'  => $this->fieldsGetCurrent($cfg, $state),
      'status'   => $state[0],
      'progress' => ($this['progress'] ?? 0),
      'info'     => $info,
    ]);
    # }}}
    # complete {{{
    # create form/control message
    $a = [
      'text'   => $text,
      'markup' => $this->markup($mkup, $mkupFlags),
    ];
    if (!($this->msgs[] = $this->image($a))) {
      return null;
    }
    if ($state[0] !== 0) {
      return $this;
    }
    # create input field/bob message
    if (!($this->msgs[] = $this->renderBob($cfg, $fieldName, $field, $fieldOpts))) {
      return null;
    }
    return $this;
    # }}}
  }
  # }}}
  function renderBob(# {{{
    array  &$cfg,
    string $fieldName,
    array  &$field,
    ?array &$fieldOpts
  ):?object
  {
    # prepare
    $bot  = $this->bot;
    $type = $field[1];
    $hand = $this->skel['handler'];
    $mkup = '';
    # compose text
    $text = $this->text[">$fieldName"] ?: $bot->text[">$type"];
    $hand && $hand($this, 'hint', $text, $fieldName);
    # compose markup
    if ($type === 'select')
    {
      ($fieldOpts === null) &&
      ($fieldOpts = $this->fieldGetOptions($field, $fieldName));
      if ($fieldOpts === null) {
        return null;
      }
      if ($a = $this->fieldGetOptionsMkup($cfg, $fieldOpts, $fieldName)) {
        $mkup = json_encode(['inline_keyboard'=>$a], JSON_UNESCAPED_UNICODE);
      }
    }
    # create message
    $mkup = [
      'text'   => $text,
      'markup' => $mkup,
    ];
    return BotTxtMessage::construct($bot, $mkup);
  }
  # }}}
  function init(array &$cfg): bool # {{{
  {
    # prepare
    $step   = $this['step']   ?? 0;
    $status = $this['status'] ?? 0;
    $field  = $this['field']  ?? 0;
    # check
    if ($cfg['isPersistent']) {
      return true;
    }
    # non-persistent form,
    # check current mode/status is resettable
    if ($status === 1 || abs($status) > 2 ||
        ($status === -2 && !$cfg['resetFailed']) ||
        ($status ===  2 && !$cfg['resetCompleted']))
    {
      return true;
    }
    # reset all steps
    if ($cfg['resetAll'])
    {
      $this->dataResetAll(true);
      $this['step'] = $this['status'] = $this['field'] = 0;
      return true;
    }
    # reset current step
    $this->dataResetStep($step, true);
    if ($status)
    {
      # back to the input
      $this['status'] = 0;
      if ($status === -2 || $status === 2) {
        $this['field'] = 0;
      }
    }
    else
    {
      # seek specific field
      $fields = &$this->skel['fields'][$step];
      $this['fields'] = ~($a = $this->fieldFindFirst($fields, -2))
        ? ((~($b = $this->fieldFindFirst($fields, 1, 1)) && $b < $a)
          ? $b  # empty required
          : $a) # non-persistent
        : 0;    # first
    }
    return true;
  }
  # }}}
  function inputAccept(# {{{
    array   &$cfg,
    array   &$state,
    string  &$info
  ):bool
  {
    # check current mode/status
    if ($state[0] !== 0)
    {
      $this->log->warn(__FUNCTION__, 'incorrect state '.implode(':', $state));
      return false;
    }
    # prepare
    $bot    = $this->bot;
    $fields = &$this->skel['fields'][$state[1]];
    $name   = array_key($fields, $state[2]);
    $field  = &$fields[$name];
    $value  = $this->input;
    $op     = $value ? '✱' : '·';
    # try custom handler
    if (($hand = $this->skel['handler']) &&
        ($a = $hand($this, 'input', $name, $value)))
    {
      # accepted,
      # report result
      if (!$a[0]) {
        $this->log->infoInput(__FUNCTION__, $name, "($op)");
      }
      else {
        $this->log->warnInput(__FUNCTION__, $name, "($op)");
      }
      # set info and complete
      $info = $a[1] ?? '';
      return true;
    }
    # default handler
    if ($value)
    {
      switch ($field[1]) {
      case 'string':
        # {{{
        # get text
        if (($value = $value->text ?? null) === null) {
          return false;
        }
        # validate size
        if (isset($field[2]) && $field[2] && strlen($value) > $field[2])
        {
          # hidden data is considered fragile, so,
          # instead of cutting, discard it
          if ($field[0] & 8)
          {
            unset($this->data[$name]);
            $info = $bot->text['oversized'];
            return true;
          }
          # cut to maximal allowed size
          $value = substr($value, 0, $field[2]);
          $info  = $bot->text['oversized'];
        }
        # validate syntax
        if (isset($field[3]) && !preg_match($field[3], $value))
        {
          # discard fragile/hidden content
          if ($field[0] & 8) {
            unset($this->data[$name]);
          }
          $info = $bot->text['bad-data'];
          return true;
        }
        # }}}
        break;
      case 'int':
        # {{{
        # integer[-2147483648..2147483648] or long[0..4294967295]
        # get text
        if (($value = $value->text ?? null) === null) {
          return false;
        }
        # extract sign and positive value
        $a = ($value[0] === '-');
        $a && ($value = substr($value, 1));
        # check length and digits
        if (!($b = strlen($value)) || $b > 10 || !ctype_digit($value))
        {
          $info = $bot->text['bad-data'];
          return true;
        }
        # cast to integer
        $value = intval($value);
        $a && ($value = -$value);
        # check minimum and maximum
        if (isset($field[2]) && $value < $field[2])
        {
          $info = $bot->tp->render($bot->text['min-value'], [
            'x' => $field[2]
          ]);
          return true;
        }
        elseif (isset($field[3]) && $value > $field[3])
        {
          $info = $bot->tp->render($bot->text['max-value'], [
            'x' => $field[3]
          ]);
          return true;
        }
        # }}}
        break;
      default:
        $this->log->errorInput(__FUNCTION__, $name, 'unknown type: '.$field[1]);
        return false;
      }
    }
    elseif (!($field[0] & 4))
    {
      # clear is not allowed
      $this->log->warnInput(__FUNCTION__, $name, $op);
      return false;
    }
    # skip equal
    if ($value === $this->data[$name]) {
      return false;
    }
    # callback skip
    if ($hand && ($a = $hand($this, 'change', $name, $value)) && !$a[0])
    {
      # check reason specified
      $this->log->warnInput(__FUNCTION__, $name, $op);
      if ($info = $a[1] ?? '') {
        return true;
      }
      return false;
    }
    # change and complete
    $this->data[$name] = $value;
    $this->log->infoInput(__FUNCTION__, $name, $op);
    return true;
  }
  # }}}
###
  function dataSubmit(# {{{
    array   &$cfg,
    array   &$state,
    string  &$info
  ):bool
  {
    # check current mode/status
    $a = $state[0];
    if (($a !== 0 && $a !== 1 && $a !== -2) ||
        ($a === -2 && !$cfg['retryFailed']))
    {
      $this->log->warn(__FUNCTION__, 'incorrect state: '.self::STATUS[$a]);
      return false;
    }
    # prepare
    $fields = &$this->skel['fields'][$state[1]];
    $name   = array_key($fields, $state[2]);
    if ($a === 0)
    {
      # act as forward before last field is reached
      if ($cfg['okIsForward'] && $state[2] < count($fields) - 1) {
        return $this->fieldForward($cfg, $state, $info);
      }
      # switch to the miss state when any empty required found
      if (~($b = $this->fieldFindFirst($fields, 1, 1)))
      {
        $this['status'] = -1;
        $this['field']  = $b;
        return true;
      }
    }
    # determine action variant
    $lastStep = count($this->skel['fields']) - 1;
    $a = (
      ($state[1] < $lastStep) ||
      ($cfg['okConfirm'] && ($a === 0 || $a === -2))
    );
    $b = $a
      ? 'ok'      # next step / last step confirmation
      : 'submit'; # submission
    # callback
    if (($c = $this->skel['handler']) &&
        ($c = $c($this, $b, $state)))
    {
      # handle result
      if ($c[0] === -2)
      {
        $this->log->warn(__FUNCTION__, $b, 'TODO: ✶task');
        return false;
      }
      elseif ($c[0] === -1)
      {
        # back to the input
        $this['status'] = 0;
        if (isset($c[1]))
        {
          # TODO: step back or forward
        }
        return true;
      }
      elseif ($c[0] === 0)
      {
        $this['status'] = -2;
        $info = $c[1] ?? $bot->text['op-fail'];
        return true;
      }
      elseif ($c[0] === 2 && $a) {
        $a = false;
      }
      $info = $c[1] ?? '';
    }
    # confirm
    if ($a)
    {
      if ($state[1] < $lastStep)
      {
        $this['step']  = $state[1] + 1;
        $this['field'] = 0;
      }
      else {
        $this['status'] = 1;
      }
      return true;
    }
    # submit
    #$this->log->info(__FUNCTION__, 'submit', implode('|', array_keys($a)));
    $this['status'] = 2;
    if ($cfg['isPersistent'])
    {
      /***
      # copy persistent fields
      $a = [];
      foreach ($this->skel['fields'] as &$b)
      {
        foreach ($b as $c => &$d)
        {
          if (($d[0] & 2) && isset($this->data[$c]))
          {
            $a[$c] = $this->data[$c];
          }
        }
      }
      $this->data->set($a);
      /***/
    }
    return true;
  }
  # }}}
  function dataReset(# {{{
    array &$cfg,
    array &$state
  ):bool
  {
    # check current mode/status
    $a = $state[0];
    if (($a < 0 && $a > -3 && !$cfg['resetFailed']) ||
        ($a > 0 && $a <  3 && !$cfg['resetCompleted']))
    {
      $this->log->warn(__FUNCTION__, self::STATUS[$a]);
      return false;
    }
    # hard reset
    if ($cfg['resetAll'])
    {
      $this->dataResetAll();
      $this['status'] = $this['step'] = $this['field'] = 0;
    }
    else
    {
      $this->dataResetStep($state[1]);
      $this['status'] = $this['field'] = 0;
    }
    $this->log->info(__FUNCTION__, self::STATUS[$a]);
    return true;
  }
  # }}}
  function dataResetAll(# {{{
    bool $softly = false
  ):bool
  {
    # get defaults
    $defs = $this->skel['defs'];
    if ($hand = $this->skel['handler']) {
      $hand($this, 'defs', $defs);
    }
    # keep persistent fields
    if ($softly && $this->data->count)
    {
      foreach ($this->skel['fields'] as &$a)
      {
        foreach ($a as $b => &$c)
        {
          if (($c[0] & 2) && isset($this->data[$b])) {
            $defs[$b] = $this->data[$b];
          }
        }
      }
    }
    # replace
    return $this->data->set($defs);
  }
  # }}}
  function dataResetStep(# {{{
    int  $step,
    bool $softly = false
  ):bool
  {
    # clear step fields (excluding persistent if softly flag set)
    $a = &$this->skel['fields'][$step];
    foreach ($a as $b => &$c)
    {
      if (!$softly || !($c[0] & 2)) {
        unset($this->data[$b]);
      }
    }
    return $this->data->changed;
  }
  # }}}
  function dataChange(# {{{
    array &$cfg,
    array &$state
  ):bool
  {
    # check current mode/status
    if (($a = $state[0]) !== -2 && $a !== 2)
    {
      $this->log->warn(__FUNCTION__, self::STATUS[$a]);
      return false;
    }
    # back to the input
    $this->log->info(__FUNCTION__, self::STATUS[$a].' → '.self::STATUS[0]);
    $this['status'] = 0;
    # keep failed state data
    if ($a === -2) {
      return true;
    }
    # reset complete data
    if ($cfg['resetAll'] || $cfg['isPersistent'])
    {
      # hard
      $this->dataResetAll(true);
      $this['step'] = $this['fields'] = 0;
    }
    else
    {
      # soft
      $this->dataResetStep($a = $this['step'], true);
      $fields = &$this->skel['fields'][$a];
      $a = $this->fieldFindFirst($fields, -2);
      $b = $this->fieldFindFirst($fields, 1, 1);
      $this['field'] = ~$a
        ? (~$b
          ? (($a < $b) ? $a : $b)
          : $a)
        : (~$b
          ? $b
          : 0);
    }
    return true;
  }
  # }}}
###
  function fieldBack(# {{{
    array &$cfg,
    array &$state
  ):bool
  {
    # check current mode/status
    if ($state[0] === 0)
    {
      # return to the previous input field
      if ($state[2] > 0)
      {
        $this['field'] = $state[2] - 1;
        return true;
      }
      # at the first field,
      # return to the previous step
      if ($state[1] > 0 && $cfg['backStep'])
      {
        $cfg['backStepReset'] && $this->dataResetStep($state[1]);
        $this['step']  = $a = $state[1] - 1;
        $this['field'] = count($this->skel['fields'][$a]) - 1;
        return true;
      }
      # move to the last field
      if ($cfg['moveAround'])
      {
        $this['field'] = count($this->skel['fields'][$state[1]]) - 1;
        return true;
      }
      # no action
      return false;
    }
    if ($state[0] === 1 || $state[0] === -2)
    {
      # return to the input from confirmation/failure
      $this['status'] = 0;
      return true;
    }
    # fail
    $this->log->warn(__FUNCTION__, 'incorrect state '.implode(':', $state));
    return false;
  }
  # }}}
  function fieldForward(# {{{
    array   &$cfg,
    array   &$state,
    string  &$info
  ):bool
  {
    # check current mode/status
    if ($state[0] !== 0)
    {
      $this->log->warn(__FUNCTION__, 'incorrect state '.implode(':', $state));
      return false;
    }
    # preapre
    $fields = &$this->skel['fields'][$state[1]];
    $name   = array_key($fields, $state[2]);
    # check field is required, empty and skipping is prohibited
    if (($fields[$name][0] & 1) &&
        !isset($this->data[$name]) &&
        !$cfg['forwardSkip'])
    {
      $info = $this->bot->text['req-field'];
      return false;
    }
    # jump to the next field
    if ($state[2] < ($a = count($fields) - 1))
    {
      $this['field'] = $state[2] + 1;
      return true;
    }
    # at the last field,
    # jump to the first field
    if ($cfg['moveAround'])
    {
      $this['field'] = 0;
      return true;
    }
    # do nothing..
    return false;
  }
  # }}}
  function fieldSetOption(# {{{
    array   &$cfg,
    array   &$state,
    string  &$info,
    ?array  &$opts,
    string  $key
  ):bool
  {
    # check current mode/status
    if ($state[0] !== 0)
    {
      $this->log->warn(__FUNCTION__, 'incorrect state '.implode(':', $state));
      return false;
    }
    # prepare
    $fields = &$this->skel['fields'][$state[1]];
    $name   = array_key($fields, $state[2]);
    $field  = &$fields[$name];
    $max    = $field[2] ?? 1;
    $data   = $this->data;
    # check field type
    if ($field[1] !== 'select')
    {
      $this->log->warn(__FUNCTION__, $name, 'incorrect field type: '.$field[1]);
      return false;
    }
    # determine options
    if (!$opts && !($opts = $this->fieldGetOptions($field, $name)))
    {
      $this->log->warn(__FUNCTION__, $name, 'failed to get options');
      return false;
    }
    # check key
    if (!strlen($key) || !isset($opts[$key]))
    {
      $this->log->warn(__FUNCTION__, $name, "incorrect option [$key]");
      return false;
    }
    # operate
    # select first
    if (!isset($data[$name]))
    {
      $data[$name] = ($max > 1)
        ? [$key] # multiple
        : $key;  # single
      $this->log->info(__FUNCTION__, $name, "(+)$key");
      return true;
    }
    # select next
    if ($max === 1)
    {
      # SINGLE-SELECT,
      # replace
      if ($key !== ($a = $data[$name]))
      {
        $data[$name] = $key;
        $this->log->info(__FUNCTION__, $name, "$a(=)$key");
        return true;
      }
      # de-select
      if ($cfg['selectDeselect'])
      {
        unset($data[$name]);
        $this->log->info(__FUNCTION__, $name, "(-)$key");
        return true;
      }
      return false;
    }
    # MULTI-SELECT,
    # get array reference
    $opts = &$data->data[$name];
    # de-select selected
    if (($a = array_search($key, $opts, true)) !== false)
    {
      # de-select last remaining
      if (count($opts) === 1)
      {
        if (!$cfg['selectDeselect']) {
          return false;# not allowed
        }
        unset($data[$name]);
        $this->log->info(__FUNCTION__, $name, "(-)$key");
        return true;
      }
      # de-select
      array_splice($opts, $a, 1);
      $data->changed = true;
      $this->log->info(__FUNCTION__, $name, "(-)$key");
      return true;
    }
    # select and de-select (LIFO)
    if (count($opts) >= $max)
    {
      $a = $opts[0];
      array_splice($opts, 0, 1);
      $opts[] = $key;
      $data->changed = true;
      $this->log->info(__FUNCTION__, $name, "$a(~)$key");
      return true;
    }
    # select (LI)
    $opts[] = $key;
    $data->changed = true;
    $this->log->info(__FUNCTION__, $name, "(+)$key");
    return true;
  }
  # }}}
  function &fieldGetOptions(# {{{
    array   &$field,
    string  $name
  ):?array
  {
    static $NULL = null;
    # get options,
    # from field descriptor (keys are mapped to the item's text)
    if (is_array($field[$a = count($field) - 1]))
    {
      $opts = [];
      foreach ($field[$a] as $b) {
        $opts[$b] = $this->text[".$name.$b"] ?: $b;
      }
      return $opts;
    }
    # from item's handler
    if (($hand = $this->skel['handler']) &&
        ($opts = $hand($this, 'options', $name)))
    {
      return $opts;
    }
    return $NULL;
  }
  # }}}
  function &fieldGetOptionsMkup(# {{{
    array   &$cfg,
    array   &$opts,
    string  $name,
  ):array
  {
    # get selected keys
    if (($keys = $this->data[$name]) !== null && !is_array($keys)) {
      $keys = [$keys];
    }
    # prepare
    $id    = $this->id;
    $templ = $this->caps['select'];
    $tp    = $this->bot->tp;
    $cols  = $cfg['cols'];
    $row   = [];
    $mkup  = [];
    # iterate
    foreach ($opts as $a => $b)
    {
      # create inline button
      $row[] = [
        'callback_data' => "/$id!select $a",
        'text' => $tp->render($templ, [
          'v'  => ($keys && in_array($a, $keys, true)),
          'x'  => $b,
        ])
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
      # add placeholders when non-flexible
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
  function fieldFindFirst(# {{{
    array &$fields,
    int   $bit   = 0,# 0=any,1=required,2=persistent,4=nullable,..
    int   $empty = 0 # 0=any,1=yes,-1=nope
  ):int
  {
    # prepare
    $count = count($fields);
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
  function fieldMissCount(array &$fields): int # {{{
  {
    $c = 0;
    foreach ($fields as $a => &$b) {
      ($b[0] & 1) && !isset($this->data[$a]) && $c++;
    }
    return $c;
  }
  # }}}
###
  function &fieldsGet(# {{{
    array &$cfg,
    int   $step
  ):array
  {
    # prepare
    $list   = [];
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
      $list[] = $this->newFieldDescriptor($name, $value, $field[0]);
    }
    return $list;
  }
  # }}}
  function &fieldsGetComplete(# {{{
    array &$cfg,
    array &$state
  ):array
  {
    # prepare
    $list = [];
    $step = ($state[0] === 2)
      ? $state[1] + 1
      : $state[1];
    # check
    if (!$step) {
      return $list;
    }
    # collect
    if ($state[0] === 2 && $cfg['isPersistent'])
    {
      # only persistent fields
      for ($i = 0; $i < $step; ++$i)
      {
        foreach ($this->fieldsGet($cfg, $i) as &$a) {
          ($a['type'] & 2) && ($list[] = $a);
        }
      }
    }
    else
    {
      # all
      for ($i = 0; $i < $step; ++$i) {
        $list = array_merge($list, $this->fieldsGet($cfg, $i));
      }
    }
    return $list;
  }
  # }}}
  function &fieldsGetCurrent(# {{{
    array &$cfg,
    array &$state
  ):?array
  {
    static $NULL = null;
    # check
    if ($state[0] === 2) {
      return $NULL;
    }
    # get fields of the step
    $list = &$this->fieldsGet($cfg, $state[1]);
    # set flags
    switch ($state[0]) {
    case 0:
      # current input
      foreach ($list as $a => &$b) {
        $b['flag'] = $a === $state[2];
      }
      break;
    case -1:
      # missing required
      foreach ($list as $a => &$b) {
        $b['flag'] = (($b['type'] & 1) && ($b['value'] === ''));
      }
      break;
    }
    # callback
    if ($hand = $this->skel['handler']) {
      $hand($this, 'fields', $list, $state);
    }
    # complete
    return $list;
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
}
# }}}
/***
* TODO {{{
* ╔═╗ ⎛     ⎞ ★★☆☆☆
* ║╬║ ⎜  *  ⎟ ✱✱✱ ✶✶✶ ⨳⨳⨳
* ╚═╝ ⎝     ⎠ ⟶ ➤ →
*
* IPC: through files not standard process pipes (STDIN/OUT/ERR)
* IPC: standalone console process
* WebHook: operate through fast-cgi (nginx)
* stability: masterbot should erase outdated locks of bots that failed for some reason
* data separation: each user changes its own data until completion
* test: file_id usage
* handler parse errors: improve, make it more descriptive
* compatible update: remove unnecessary refresh in private chat
* solve: old message callback action does ZAP? or.. REFRESH?!
* getUpdates: own curl handle
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
# }}}
class Bot extends BotConfigAccess {
  # {{{
  const
    PROCESS_TICK     = 200000,# 1/5 sec
    PROCESS_TIMEOUT  = 100,# ticks
    MESSAGE_LIFETIME = 48*60*60,
    USERNAME_EXP     = '/^[a-z]\w{4,32}$/i',
    BOTNAME_EXP      = '/^[a-z]\w{1,29}bot$/i',
    EXP_PIDFILE = '/^bot([-0-9]+)\\.pid$/',
    INIT = [
      # TODO: console+log first!
      'cfg','console','log','api','text','cmd','file','proc'
    ];
  public
    $bot,$id,$tid,
    $cfg,$console,$log,$api,$text,$cmd,$file,$proc,
    $user,$inited = [],$exitcode = 1;
  # }}}
  static function start(string $args = ''): never # {{{
  {
    try
    {
      # configure environment
      set_time_limit(0);
      #ini_set('implicit_flush', '1');
      ini_set('html_errors', '0');
      ini_set('log_errors_max_len', '0');
      set_error_handler(function(int $no, string $msg, string $file, int $line) {
        # all errors, except supressed (@) must be handled,
        # unhandled are thrown as an exception
        if (error_reporting() !== 0) {
          throw new Exception($msg, $no);
        }
        return false;
      });
      # create instance
      $args = explode(':', $args, 2);
      $bot  = new self($args[0], $args[1] ?? '');
      # initialize
      if ($bot->init())
      {
        # guard against non-recoverable errors
        register_shutdown_function(function() use ($bot) {
          $bot->finit();
        });
        # operate
        $bot->proc->loop();
        $bot->finit();
      }
      $e = $bot->exitcode;
    }
    catch (Throwable $e)
    {
      fwrite(STDERR, 'fatal: '.$e->getMessage()."\n".$e->getTraceAsString());
      $e = 2;
    }
    exit($e);
  }
  # }}}
  function __construct(string $id, string $taskId) # {{{
  {
    $this->bot     = $this;# for BotConfigAccess
    $this->id      = $id;
    $this->tid     = $taskId;
    $this->cfg     = new BotConfig($this, $id);
    $this->console = BotConsole::construct($this, $id === '');
    $this->log     = new BotLog($this, $id);
    $this->api     = new BotApi($this);
    $this->text    = new BotText($this);
    $this->cmd     = new BotCommands($this);
    $this->file    = new BotFile($this);
    $this->proc    = BotProcess::construct($this);
  }
  # }}}
  function init(): bool # {{{
  {
    try
    {
      foreach (self::INIT as $k)
      {
        if (!$this->$k->init()) {
          throw BotError::stop($k, 'failed');
        }
        array_unshift($this->inited, $k);
      }
      $this->exitcode = 0;
    }
    catch (Throwable $e)
    {
      $this->log->exception($e);
      $this->finit();
      return false;
    }
    return true;
  }
  # }}}
  function update(object $o): bool # {{{
  {
    # parse update opbject and
    # construct specific request
    $q = $u = $c = null;
    if (isset($o->callback_query))
    {
      # {{{
      if (!isset(($q = $o->callback_query)->from))
      {
        $this->log->warn('incorrect callback');
        $this->log->dump($o);
        return false;
      }
      $u = $q->from;
      if (isset($q->game_short_name))
      {
        #$x = new BotRequestGame(this, $q);
        return false;
      }
      elseif (isset($q->data))
      {
        if (!isset($q->message) ||
            !isset($q->message->chat))
        {
          $this->log->warn('incorrect data callback');
          $this->log->dump($o);
          return false;
        }
        $c = $q->message->chat;
        $q = new BotRequestCallback($this, $q);
      }
      else {
        return false;
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
        return false;
      }
      $u = $q->from;
      #$q = new BotRequestInline(this, $q);
      return false;
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
        return false;
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
        return false;
      }
      $u = $q->from;
      $c = $q->chat;
      $q = new BotRequestChat($this, $q);
      # }}}
    }
    elseif (isset($o->edited_message))
    {
      return false;
    }
    else
    {
      $this->log->warn('unknown update type');
      $this->log->dump($o);
      return false;
    }
    # construct and attach user
    if (!($this->user = BotUser::construct($this, $u, $c))) {
      return false;
    }
    # reply and detach
    $this->user->destruct($q->response());
    $this->user = null;
    # complete
    return true;
  }
  # }}}
  function &getActiveIds(): array # {{{
  {
    $dir = $this->cfg->dirDataRoot;
    $lst = [];
    if (($a = scandir($dir, SCANDIR_SORT_NONE)) === false) {
      throw BotError::fail('scandir', $dir);
    }
    foreach ($a as $b)
    {
      $c = null;
      if (preg_match(self::EXP_PIDFILE, $b, $c)) {
        $lst[] = $c[1];
      }
    }
    return $lst;
  }
  # }}}
  function finit(): void # {{{
  {
    if ($this->inited)
    {
      foreach ($this->inited as $k) {
        $this->$k->finit();
      }
      $this->inited = [];
    }
  }
  # }}}
}
?>
