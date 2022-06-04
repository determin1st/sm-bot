<?php declare(strict_types=1);
namespace SM;
# used globals {{{
use
  JsonSerializable, ArrayAccess,
  SyncEvent, SyncReaderWriter, SyncSharedMemory,
  Generator, Closure, CURLFile,
  Throwable, Error, Exception;
use function
  set_time_limit,ini_set,register_shutdown_function,set_error_handler,
  class_exists,function_exists,method_exists,
  explode,implode,count,reset,next,key,array_unshift,array_keys,
  in_array,array_search,array_reverse,
  strpos,strrpos,strlen,trim,rtrim,strval,uniqid,ucfirst,lcfirst,
  file_put_contents,file_get_contents,clearstatcache,file_exists,unlink,
  filemtime,mkdir,scandir,fwrite,fread,fclose,glob,
  curl_init,curl_setopt_array,curl_exec,
  curl_errno,curl_error,curl_strerror,curl_close,
  curl_multi_init,curl_multi_add_handle,curl_multi_exec,curl_multi_select,
  curl_multi_strerror,curl_multi_info_read,
  curl_multi_remove_handle,curl_multi_close,
  proc_open,is_resource,proc_get_status,proc_terminate,getmypid,
  ob_start,ob_get_length,ob_flush,ob_end_clean,
  pack,unpack,time,sleep,usleep;
# }}}
# helpers
# functions {{{
# array
function array_key(array &$a, int $index): int|string|null # {{{
{
  reset($a);
  while ($index--) {
    next($a);
  }
  return key($a);
}
# }}}
function array_string_keys(array &$a): array # {{{
{
  for ($keys = [], reset($a); $k = key($a); next($a)) {
    $keys[] = is_string($k) ? $k : strval($k);
  }
  return $keys;
}
# }}}
function array_import(array &$to, array &$from): void # {{{
{
  foreach ($to as $k => &$v)
  {
    if (isset($from[$k]))
    {
      if (is_array($v)) {
        array_import($v, $from[$k]);
      }
      else {
        $v = $from[$k];
      }
    }
  }
}
# }}}
function array_import_all(array &$to, array &$from): void # {{{
{
  foreach ($from as $k => &$v)
  {
    if (isset($to[$k]) && is_array($to[$k]) && is_array($v)) {
      array_import_all($to[$k], $v);
    }
    else {
      $to[$k] = $v;
    }
  }
}
# }}}
function array_import_new(array &$to, array &$from): void # {{{
{
  foreach ($from as $k => &$v)
  {
    if (!isset($to[$k])) {
      $to[$k] = $v;
    }
    elseif (is_array($v) && is_array($to[$k])) {
      array_import_new($to[$k], $v);
    }
  }
}
# }}}
###
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
function &file_get_array(string $file): ?array # {{{
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
function &file_get_json(string $file): ?array # {{{
{
  if (!file_exists($file) || ($a = file_get_contents($file)) === '') {
    $a = [];
  }
  elseif ($a === false || ($a[0] !== '[' && $a[0] !== '{')) {
    $a = null;
  }
  else {
    $a = json_decode($a, true, 128, JSON_INVALID_UTF8_IGNORE);
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
function dir_check_make(string $dir, int $perms = 0750): bool # {{{
{
  if (file_exists($dir)) {
    return true;
  }
  try {
    $res = mkdir($dir, $perms);
  }
  catch (Throwable) {
    $res = false;
  }
  return $res;
}
# }}}
function class_basename(string $name): string # {{{
{
  return ($i = strrpos($name, '\\'))
    ? substr($name, $i + 1) : $name;
}
# }}}
function class_name(object $o): string # {{{
{
  return class_basename($o::class);
}
# }}}
function class_parent_name(object $o): string # {{{
{
  return ($name = get_parent_class($o))
    ? class_basename($name) : '';
}
# }}}
# }}}
# classes {{{
class ErrorEx extends Error # {{{
{
  const TYPE = [# {{{
    E_ERROR             => 'ERROR',
    E_WARNING           => 'WARNING',
    E_PARSE             => 'PARSE',
    E_NOTICE            => 'NOTICE',
    E_CORE_ERROR        => 'CORE_ERROR',
    E_CORE_WARNING      => 'CORE_WARNING',
    E_COMPILE_ERROR     => 'COMPILE_ERROR',
    E_COMPILE_WARNING   => 'COMPILE_WARNING',
    E_USER_ERROR        => 'USER_ERROR',
    E_USER_WARNING      => 'USER_WARNING',
    E_USER_NOTICE       => 'USER_NOTICE',
    E_STRICT            => 'STRICT',
    E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
    E_DEPRECATED        => 'DEPRECATED',
    E_USER_DEPRECATED   => 'USER_DEPRECATED',
  ];
  # }}}
  static function rise(int $no, string $msg): self # {{{
  {
    $a = self::TYPE[$no] ?? "UNKNOWN($no)";
    return new self(2, [$a, $msg]);
  }
  # }}}
  static function skip(string ...$msg): self # {{{
  {
    return new self(0, $msg);
  }
  # }}}
  static function warn(string ...$msg): self # {{{
  {
    return new self(0, $msg);
  }
  # }}}
  static function stop(string ...$msg): self # {{{
  {
    return new self(1, $msg);
  }
  # }}}
  static function fail(string ...$msg): self # {{{
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
  # }}}
  static function from(object $e): ?self # {{{
  {
    if ($e instanceof ErrorEx)
    {
      # determine error level
      $a = $e;
      $b = $a->level;
      while ($a->next)
      {
        $a  = $a->next;
        $b += $a->level;
      }
      # select error or no error
      return $b ? $e : null;
    }
    # wrap
    return new self(1, null, $e);
  }
  # }}}
  function __construct(# {{{
    public int      $level  = 0,
    public array    $msg    = [],
    public ?object  $origin = null,
    public ?object  $next   = null
  )
  {
    parent::__construct('', -1);
  }
  # }}}
  function push(object $e): self # {{{
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
  # }}}
  function getMsg(string $default = '') # {{{
  {
    return $this->msg
      ? implode(' ', $this->msg)
      : $default;
  }
  # }}}
}
# }}}
class ArrayNode implements ArrayAccess, JsonSerializable # {{{
{
  function __construct(# {{{
    public array    $node,
    public ?object  $root   = null,
    public int      $limit  = 0,
    public ?object  $parent = null,
    public int      $depth  = 0
  )
  {
    $this->_restruct();
  }
  # }}}
  function __debugInfo(): array # {{{
  {
    return $this->node;
  }
  # }}}
  function _restruct(): void # {{{
  {
    if (($limit = $this->limit - 1) >= 0)
    {
      $depth = $this->depth + 1;
      foreach ($this->node as $k => &$v)
      {
        if (is_array($v)) {
          $v = new self($v, $this->root, $limit, $this, $depth);
        }
      }
    }
  }
  # }}}
  # [node] access {{{
  function offsetExists(mixed $k): bool {
    return isset($this->node[$k]);
  }
  function offsetGet(mixed $k): mixed {
    return $this->node[$k] ?? null;
  }
  function offsetSet(mixed $k, mixed $v): void
  {
    if ($v === null)
    {
      if (isset($this->node[$k]))
      {
        unset($this->node[$k]);
        $this->root?->change($this, $k);
      }
    }
    elseif (!isset($this->node[$k]) || $v !== $this->node[$k])
    {
      $this->node[$k] = (is_array($v) && ($i = $this->limit))
        ? new self($v, $this->root, $i - 1, $this, $this->depth + 1)
        : $v;
      $this->root?->change($this, $k);
    }
  }
  function offsetUnset(mixed $k): void {
    $this->offsetSet($k, null);
  }
  # }}}
  function jsonSerialize(): array # {{{
  {
    return $this->node;
  }
  # }}}
  function obtain(string $k): ?object # {{{
  {
    if (isset($this->node[$k])) {
      return $this->node[$k];
    }
    if (($limit = $this->limit - 1) >= 0) {
      return null;
    }
    return $this->node[$k] = new self(
      [], $this->root, $limit, $this, $this->depth + 1
    );
  }
  # }}}
  function import(array &$node): void # {{{
  {
    foreach ($this->node as $k => &$v)
    {
      if (isset($node[$k]))
      {
        if (is_object($v)) {
          $v->import($node[$k]);
        }
        else {
          $v = $node[$k];
        }
      }
    }
  }
  # }}}
  function set(array &$node): void # {{{
  {
    $this->node = $node;
    $this->_restruct();
    $this->root?->change($this, $k);
  }
  # }}}
  function keys(): array # {{{
  {
    return array_string_keys($this->node);
  }
  # }}}
}
# }}}
class ArrayUnion implements ArrayAccess # {{{
{
  public $stack = [];
  function __construct(?array $first = null) # {{{
  {
    $first && $this->pushRef($first);
  }
  # }}}
  function __isset(string $k): bool # {{{
  {
    return $this->search($k) >= 0;
  }
  # }}}
  function __get(string $k): mixed # {{{
  {
    return ~($i = $this->search($k))
      ? $this->stack[$i][$k]
      : null;
  }
  # }}}
  # access {{{
  function offsetExists(mixed $k): bool {
    return $this->search($k) >= 0;
  }
  function offsetGet(mixed $k): mixed
  {
    return ~($i = $this->search($k))
      ? $this->stack[$i][$k]
      : null;
  }
  function offsetSet(mixed $k, mixed $v): void
  {}
  function offsetUnset(mixed $k): void
  {}
  # }}}
  function search(string $k): int # {{{
  {
    for ($i = 0, $j = count($this->stack); $i < $j; ++$i)
    {
      if (isset($this->stack[$i][$k])) {
        return $i;
      }
    }
    return -1;
  }
  # }}}
  function push(array $a): void # {{{
  {
    $this->pushRef($a);
  }
  # }}}
  function pushRef(array &$a): void # {{{
  {
    array_unshift($this->stack, null);
    $this->stack[0] = &$a;
  }
  # }}}
  function pop(): void # {{{
  {
    array_shift($this->stack);
  }
  # }}}
}
# }}}
class Membuf # {{{
{
  public $name,$size,$buf,$overflow = false;
  function __construct(string $name, int $size) # {{{
  {
    $this->name = $name;
    $this->size = $size = $size + 4;
    $this->buf  = new SyncSharedMemory($name, $size);
    if ($this->buf->first()) {
      $this->reset();
    }
  }
  # }}}
  function read(bool $noReset = false): string # {{{
  {
    # prepare
    $a = unpack('l', $this->buf->read(0, 4))[1];
    $b = $this->size - 4;
    # check
    if ($a < -1 || $a > $b) {
      throw ErrorEx::fail("incorrect buffer size: $a");
    }
    if ($a === 0) {
      return '';
    }
    if ($this->overflow = ($a === -1)) {
      $a = $b;
    }
    # complete
    $noReset || $this->reset();
    return $this->buf->read(4, $a);
  }
  # }}}
  function write(string $data, bool $append = false): int # {{{
  {
    # check empty
    if (($a = strlen($data)) === 0)
    {
      $append || $this->reset();
      return 0;
    }
    # determine size and offset
    if ($append)
    {
      # read current
      $b = unpack('l', $this->buf->read(0, 4))[1];
      $c = 4 + $b;
      # check
      if ($b < -1 || $b > $this->size - 4) {
        throw ErrorEx::fail("incorrect buffer size: $b");
      }
      if ($b === -1)
      {
        $this->overflow = true;
        return 0;
      }
    }
    else
    {
      # overwrite
      $b = 0;
      $c = 4;
    }
    # check overflow
    if ($this->overflow = (($d = $this->size - $c - $a) < 0))
    {
      # write special size-flag
      $this->buf->write(pack('l', -1), 0);
      # check no space left
      if (($a = $a + $d) <= 0) {
        return 0;
      }
      # cut to fit
      $data = substr($data, 0, $a);
    }
    else
    {
      # write size
      $this->buf->write(pack('l', $a + $b), 0);
    }
    # write content
    return $this->buf->write($data, $c);
  }
  # }}}
  function reset(): bool # {{{
  {
    return ($this->buf->write("\x00\x00\x00\x00", 0) === 4);
  }
  # }}}
}
# }}}
class Syncbuf # {{{
{
  public $membuf,$rEvent,$wEvent,$timeout;
  function __construct(string $name, int $size, int $timeout) # {{{
  {
    $this->membuf  = new Membuf($name, $size);
    $this->rEvent  = new SyncEvent('R'.$name, 1);
    $this->wEvent  = new SyncEvent('W'.$name, 1);
    $this->timeout = $timeout;
  }
  # }}}
  function write(string $data, int $timeout = 0): void # {{{
  {
    if ($this->rEvent->wait(0) && !$this->rEvent->reset()) {
      throw ErrorEx::fail('event failed to reset');
    }
    if (!$this->membuf->write($data)) {
      throw ErrorEx::fail('failed to write into buffer');
    }
    if ($this->membuf->overflow) {
      throw ErrorEx::fail('buffer overflow');
    }
    if (!$this->wEvent->fire()) {
      throw ErrorEx::fail('event failed to fire');
    }
    if (!$this->rEvent->wait($timeout ?: $this->timeout)) {
      throw ErrorEx::fail('response timed out');
    }
  }
  # }}}
  function read(int $wait = 0): string # {{{
  {
    if (!$this->wEvent->wait($wait)) {
      return '';
    }
    $data = $this->membuf->read();
    if (!$this->wEvent->reset()) {
      throw ErrorEx::fail('event failed to reset');
    }
    if ($this->membuf->overflow) {
      throw ErrorEx::fail('buffer overflow');
    }
    if (!$this->rEvent->fire()) {
      throw ErrorEx::fail('event failed to fire');
    }
    return $data;
  }
  # }}}
  function writeRead(string $data, int $wait = 300, int $timeout = 0): string # {{{
  {
    $this->write($data, $timeout);
    return $this->read($wait);
  }
  # }}}
  function reset(): void # {{{
  {
    $this->wEvent->reset();
    $this->rEvent->reset();
    $this->membuf->reset();
  }
  # }}}
}
# }}}
class PromiseOne # {{{
{
  public $queue = [],$index = 0,$status = 0,$result;
  static function construct(?object ...$queue): ?object # {{{
  {
    $I = new static();
    foreach ($queue as $a) {
      $a && ($I->queue[] = $a);
    }
    return count($I->queue) ? $I : null;
  }
  # }}}
  function add(?object $a): bool # {{{
  {
    if (!$a || $this->status) {
      return false;
    }
    $this->queue[] = $a;
    return true;
  }
  # }}}
  function complete(): int # {{{
  {
    if ($this->status) {
      return $this->status;
    }
    $a = $this->queue[$this->index];
    if ($i = $a($this->result))
    {
      $this->result = $a->result;
      if ($i < 0) {
        return $this->status = -1;
      }
      if (++$this->index === count($this->queue)) {
        return $this->status = 1;
      }
    }
    return 0;
  }
  # }}}
}
# }}}
class PromiseAll extends PromiseOne # {{{
{
  function complete(): int # {{{
  {
    if ($this->status) {
      return $this->status;
    }
    $i = 0;
    foreach ($this->queue as $a)
    {
      if ($a->status || $a()) {
        $i++;
      }
    }
    if (count($this->queue) === $i)
    {
      foreach ($this->queue as $a)
      {
        if ($a->status < 0) {
          return $this->status = -1;
        }
      }
      return $this->status = 1;
    }
    return 0;
  }
  # }}}
}
# }}}
# }}}
# core
# console {{{
class BotConsole # {{{
{
  # {{{
  const
    UUID     = 'b0778b0492bb482dad6cde6ef72308f1',
    TIMEOUT  = 1000,
    BUF_SIZE = 32000; # lines=80*400
  public
    $active,$membuf,$lock,$locked = false;
  # }}}
  static function construct(object $bot): object # {{{
  {
    return $bot->id
      ? new self($bot)
      : new BotMasterConsole($bot);
  }
  # }}}
  function __construct(public object $bot) # {{{
  {
    $this->active = new SyncEvent(self::UUID, 1);
    $this->membuf = new Membuf(self::UUID, self::BUF_SIZE);
    $this->lock   = new SyncReaderWriter(self::UUID);
  }
  # }}}
  function init(): bool # {{{
  {
    # redirect STDOUT
    ob_start(Closure::fromCallable([$this, 'write']));
    return true;
  }
  # }}}
  function write(string $text): void # {{{
  {
    if ($this->active->wait(0))
    {
      # lock
      if (!($this->locked = $this->lock->readlock(self::TIMEOUT))) {
        throw ErrorEx::fail("timed out\nSyncReaderWriter::readlock");
      }
      # write
      $this->membuf->write($text, true);
      # unlock
      if ($this->lock->readunlock()) {
        $this->locked = false;
      }
    }
  }
  # }}}
  function flush(): void # {{{
  {
    ob_get_length() && ob_flush();
  }
  # }}}
  function stdout(string $text, int $phase = 0): string # {{{
  {
    $this->write($text);
    return '';
  }
  # }}}
  function finit(): void # {{{
  {
    ob_end_clean();
    $this->locked && $this->lock->readunlock();
  }
  # }}}
}
# }}}
class BotMasterConsole extends BotConsole # {{{
{
  const FILE_CONIO = 'conio.inc';
  public $conio;
  function init(): bool # {{{
  {
    $this->conio = include $this->bot->cfg->dirInc.self::FILE_CONIO;
    return true;
  }
  # }}}
  function write(string $text): void # {{{
  {
    fwrite(STDOUT, $text);
  }
  # }}}
  function flush(): void # {{{
  {
    # lock
    if (!($this->locked = $this->lock->writelock(self::TIMEOUT))) {
      throw ErrorEx::fail("timed out\nSyncReaderWriter::writelock");
    }
    # read and flush
    if ($a = $this->membuf->read())
    {
      fwrite(STDOUT, $a);
      if ($this->membuf->overflow)
      {
        fwrite(STDOUT, "...\n");
        $this->bot->log->warn('overflow');
      }
    }
    # unlock
    if ($this->lock->writeunlock()) {
      $this->locked = false;
    }
  }
  # }}}
  function choice(int $timeout = 0, string $from = 'ny'): string # {{{
  {
    $from = strtolower($from);
    $tick = $i = 0;
    while (1)
    {
      while (!strlen($a = $this->conio->getch()))
      {
        usleep(200000);
        if ($timeout && ++$tick === 5)
        {
          if (--$timeout === 0) {
            return $from[0];
          }
          $tick = 0;
        }
      }
      if (($i = strpos($from, lcfirst($a))) !== false) {
        break;
      }
    }
    return $from[$i];
  }
  # }}}
  function finit(): void # {{{
  {
    # display remaining logs
    try {$this->read();}
    catch (Throwable) {}
    # unlock
    $this->locked && $this->lock->writeunlock();
  }
  # }}}
}
# }}}
# }}}
# logger {{{
class BotLog
{
  # {{{
  # ●◆◎∙ ▶▼ ■▄ ◥◢◤◣  ►◄
  const
    COLOR  = ['green','red','yellow'],# [info,error,warn]
    SEP    = ['►','◄'],# [output,input]
    PROMPT = ['◆','◆','cyan'];# [linePrefix,blockPrefix,color]
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
    if ($x === null)
    {
      $x = [
        self::fgColor('└┬', self::PROMPT[2], 1),
        self::fgColor(' ├', self::PROMPT[2], 1),
        self::fgColor(' └', self::PROMPT[2], 1),
        self::fgColor('└┐', self::PROMPT[2], 1),
        self::fgColor(' │', self::PROMPT[2], 1),
        self::fgColor('└─', self::PROMPT[2], 1),
      ];
    }
    # prepare
    $s = trim($s);
    $a = explode("\n", self::clearColors($s));
    $b = explode("\n", $s);
    # split and determine the last line
    for ($i = 0,$j = count($a) - 1; ~$j; --$j)
    {
      # stop at pad/block character
      if (strlen($a[$j]) && strpos(' └', $a[$j][0]) === false)
      {
        $i = 1;
        break;
      }
      # pad otherwise
      $b[$j] = '  '.$b[$j];
    }
    # check not a block
    if ($i === 0) {
      return $s;
    }
    # check at the first line
    if ($j === 0) {
      return $x[5].implode("\n", $b);
    }
    # compose the block
    for ($i = 0; $i < $j; ++$i)
    {
      $k = (strlen($a[$i]) && strpos(' └', $a[$i][0]) === false)
        ? 0 : 3;
      #$k = (strlen($b[$i]) && !ctype_space($b[$i][0])) ? 0 : 3;
      $i && $k++;
      $b[$i] = $x[$k].$b[$i];
    }
    $b[$j] = $x[2].$b[$j];
    return implode("\n", $b);
  }
  # }}}
  static function parseObject(# {{{
    object  $o,
    int     $depth = 0
  ):string
  {
    $pad = str_repeat('*', $depth);
    $equ = ' = ';
    $a = '';
    $i = 0;
    foreach ($o as $k => &$v)
    {
      if (is_bool($v)) {
        $b = $equ.($v ? 'true' : 'false')."\n";
      }
      elseif (is_null($v)) {
        $b = $equ."null\n";
      }
      elseif (is_string($v))
      {
        $c = (($j = strlen($v)) > 60 || strpos($v, "\n"))
          ? '".." size='.$j
          : '"'.$v.'"';
        $b = $equ.$c."\n";
      }
      elseif (is_scalar($v)) {
        $b = $equ.strval($v)."\n";
      }
      elseif (is_object($v)) {
        $b = "\n".self::parseObject($v, $depth + 1);
      }
      elseif (is_array($v)) {
        $b = $equ.'[..] count='.count($v)."\n";
      }
      else {
        $b = "?\n";
      }
      $a = $a.$k.$b;
      $i++;
    }
    return $a ? self::parseBlock($a)."\n" : '';
  }
  # }}}
  static function parseTrace(object $e, int $from = 0): string # {{{
  {
    # prepare
    $a = '';
    $b = $from
      ? array_slice($e->getTrace(), $from)
      : $e->getTrace();
    # compose
    if ($b)
    {
      foreach ($b as $c)
      {
        $a .= isset($c['file'])
          ? $c['file'].'('.$c['line'].')'
          : 'INTERNAL';
        $a .= isset($c['class'])
          ? ': '.$c['class'].$c['type'].$c['function']
          : ': '.$c['function'];
        $a .= "\n";
      }
    }
    return $a;
  }
  # }}}
  static function separator(int $level, int $sep): string # {{{
  {
    $i = ($level === 2) ? 1 : 0;
    return ' '.self::fgColor(self::SEP[$sep], self::COLOR[$level], $i).' ';
  }
  # }}}
  static function throwableToString(Throwable $e): string # {{{
  {
    return '## '.$e->getFile().'('.$e->getLine().'): '.$e->getMessage()."\n".
           $e->getTraceAsString()."\n";
  }
  # }}}
  function __construct(# {{{
    public object   $bot,
    public string   $name   = '',
    public ?object  $parent = null
  )
  {
    if (!$name)
    {
      $this->name = $bot->id
        ? 'bot:'.$bot->id.($bot->task ? ':'.$bot->task : '')
        : 'console';
    }
  }
  # }}}
  function init(): bool # {{{
  {
    if ($this->bot->id) {
      $this->name = $this->bot['name'];
    }
    return true;
  }
  # }}}
  function new(string $name): self # {{{
  {
    return ($name === $this->name)
      ? $this : new self($this->bot, $name, $this);
  }
  # }}}
  function message(int $level, string $sep, array &$msg): string # {{{
  {
    # prepare
    $text  = '';
    $color = self::COLOR[$level];
    # compose name chain
    $p = $this;
    while ($p->parent)
    {
      $text = $sep.self::fgColor($p->name, $color).$text;
      $p = $p->parent;
    }
    # compose msg chain
    if ($msg)
    {
      for ($i = 0, $j = count($msg) - 1; $i < $j; ++$i) {
        $text = $text.$sep.self::fgColor($msg[$i], $color, 1);
      }
      $text = (~$j && ($a = rtrim($msg[$j])))
        ? (($a[0] === "\n") ? $text.$a : $text.$sep.$a)
        : $text.$sep;
    }
    # check multiline
    if (($n = strpos($text, "\n")) > 0)
    {
      $text = substr($text, 0, ++$n).self::parseBlock(substr($text, $n));
      $n = 1;
    }
    else {# single line
      $n = 0;
    }
    # complete
    return self::fgColor(self::PROMPT[$n], self::PROMPT[2], 1).
           self::fgColor($p->name, self::PROMPT[2], 0).$text;
  }
  # }}}
  function print(int $level, int $sep, string ...$msg): void # {{{
  {
    $s = self::separator($level, $sep);
    $this->bot->console->write($this->message($level, $s, $msg)."\n");
  }
  # }}}
  function printObject(object $o, int $level, array &$msg): void # {{{
  {
    $a = $this->message($level, self::separator($level, 0), $msg);
    $b = self::parseObject($o);
    $this->bot->console->write($a."\n".$b);
  }
  # }}}
  function prompt(string ...$msg): void # {{{
  {
    array_push($msg, '');
    $s = self::separator(0, 1);
    $this->bot->console->write($this->message(0, $s, $msg));
  }
  # }}}
  function bannerConsole(): void # {{{
  {
    $a = <<<EOD
███████████████████████████████████████ Process control:
█─▄▄▄▄█▄─▀█▀─▄█▀▀▀▀▀██▄─▄─▀█─▄▄─█─▄─▄─█ [[1mq[0m][[1mCtrl+C[0m] ~ quit, keep bots running
█▄▄▄▄─██─█▄█─██████████─▄─▀█─██─███─███ [[1mx[0m][[1mCtrl+Break[0m] ~ quit, stop bots
▀▄▄▄▄▄▀▄▄▄▀▄▄▄▀▀▀▀▀▀▀▀▄▄▄▄▀▀▄▄▄▄▀▀▄▄▄▀▀ [[1mr[0m] ~ restart

EOD;
    $a = self::fgColor($a, self::PROMPT[2]);
    $this->bot->console->write($a);
  }
  # }}}
  function bannerCommands(): void # {{{
  {
    $this->info($this->bot['source'],
      "\n".self::parseCommands($this->bot->cmd->tree, 0, self::PROMPT[2])
    );
  }
  # }}}
  function info(string ...$msg): void # {{{
  {
    $this->print(0, 0, ...$msg);
  }
  # }}}
  function infoInput(string ...$msg): void # {{{
  {
    $this->print(0, 1, ...$msg);
  }
  # }}}
  function infoObject(object $o, string ...$msg): void # {{{
  {
    $this->printObject($o, 0, $msg);
  }
  # }}}
  function error(string ...$msg): void # {{{
  {
    $this->print(1, 0, ...$msg);
    $this->errorCount += 1;
  }
  # }}}
  function errorInput(string ...$msg): void # {{{
  {
    $this->print(1, 1, ...$msg);
    $this->errorCount += 1;
  }
  # }}}
  function errorObject(object $o, string ...$msg): void # {{{
  {
    $this->printObject($o, 1, $msg);
  }
  # }}}
  function warn(string ...$msg): void # {{{
  {
    $this->print(2, 0, ...$msg);
  }
  # }}}
  function warnInput(string ...$msg): void # {{{
  {
    $this->print(2, 1, ...$msg);
  }
  # }}}
  function warnObject(object $o, string ...$msg): void # {{{
  {
    $this->printObject($o, 2, $msg);
  }
  # }}}
  function exception(object $e): int # {{{
  {
    # compose custom error
    if ($e instanceof ErrorEx)
    {
      # check rised (with trace) or informational
      if (($i = $e->level) > 1)
      {
        # get reduced trace
        $a = self::parseTrace($e, $i - 1);
        $a = str_replace(__DIR__.DIRECTORY_SEPARATOR, '', $a);
        # prepend with bang and append trace
        array_unshift($e->msg, '✶');
        $e->msg[count($e->msg) - 1] .= "\n".$a;
        $this->error(...$e->msg);
      }
      elseif ($e->msg)
      {
        if ($i) {
          $this->error(...$e->msg);
        }
        else {
          $this->warn(...$e->msg);
        }
      }
      # check derived from standard
      if ($e->origin) {
        $i += $this->exception($e->origin);
      }
      # check chained
      if ($e->next) {
        $i += $this->exception($e->next);
      }
      return $i;
    }
    # compose standard error/exception
    $a = $e->getMessage()."\n".
      $e->getFile().'('.$e->getLine().")\n".
      self::parseTrace($e);
    # remove paths
    $a = str_replace(__DIR__.DIRECTORY_SEPARATOR, '', $a);
    # output
    $this->print(1, 0, '✱', get_class($e), $a);
    $this->errorCount += 1;
    return 2;
  }
  # }}}
  function dump(mixed $var): void # {{{
  {
    if ($proc = $this->bot->proc) {
      $proc->print(var_export($var, true)."\n");
    }
  }
  # }}}
  function finit(): void # {{{
  {}
  # }}}
}
# }}}
# config {{{
class BotConfig # {{{
{
  # {{{
  const
    DIR_INC       = 'inc',
    DIR_SRC       = 'bots',
    DIR_DATA      = 'data',
    DIR_USER      = 'users',
    DIR_GROUP     = 'groups',
    DIR_CHAN      = 'channels',
    FILE_MASTER   = 'config.inc',
    FILE_BOT      = 'config.json',
    FILE_HANDLERS = 'handlers.php',
    EXP_TOKEN     = '/^\d{8,10}:[a-z0-9_-]{35}$/i';
  public
    $dirInc,$dirSrcRoot,$dirDataRoot,
    $dirSrc,$dirData,$dirUsr,$dirGrp,$dirChan,
    $data,$file,$changed = false;
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
  function __construct(public object $bot, string $id = '') # {{{
  {
    # set base directories
    $this->dirSrcRoot = self::getSrcDir();
    $this->dirInc     = self::getIncDir();
    # load master config
    $o = include $this->dirInc.self::FILE_MASTER;
    $this->dirDataRoot = self::getDataDir($o);
    $this->data = new ArrayNode([
      # {{{
      'Bot'             => [
        'source'        => $o['source'] ?? 'master',
        'lang'          => $o['lang'] ?? 'en',
        'token'         => $o['token'],
        'id'            => $id ?: self::getId($o['token']),
        'admins'        => [],
        'name'          => '',
        'canJoinGroups' => false,
        'canReadGroups' => false,
        'isInline'      => false,
      ],
      'BotApi'     => [
        'baseUrl'  => $o['baseUrl'] ?? 'https://api.telegram.org/bot',
        # getUpdates
        'timeout'  => 60,# polling timeout (telegram's max=50)
        'limit'    => 100,# polling results limit (100=max)
        'maxFails' => 0,# max repeated fails until termination (0=unlimited)
        'pause'    => 10,# pause after repeated fails (seconds)
        # WebHook
        'webhook'  => false,
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
      # }}}
    ], $this, 1);
  }
  # }}}
  function init(): bool # {{{
  {
    # prepare
    $bot = $this->bot;
    # set data directories
    $this->dirData = $a = $this->dirDataRoot.$bot['id'].DIRECTORY_SEPARATOR;
    $this->dirUsr  = $a.self::DIR_USER.DIRECTORY_SEPARATOR;
    $this->dirGrp  = $a.self::DIR_GROUP.DIRECTORY_SEPARATOR;
    $this->dirChan = $a.self::DIR_CHAN.DIRECTORY_SEPARATOR;
    # load bot configuration
    if (!($b = file_get_json($file = $a.self::FILE_BOT)))
    {
      $bot->log->error($file);
      return false;
    }
    $this->data->import($b);
    # set source directory and load handlers
    $this->dirSrc = $this->dirSrcRoot.$bot['source'].DIRECTORY_SEPARATOR;
    try {
      require $this->dirSrc.self::FILE_HANDLERS;
    }
    catch (Throwable $e)
    {
      $bot->log->exception($e);
      return false;
    }
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
    if (!dir_check_make($a = $this->dirDataRoot.$id) ||
        !dir_check_make($a.DIRECTORY_SEPARATOR.self::DIR_USER) ||
        !dir_check_make($a.DIRECTORY_SEPARATOR.self::DIR_GROUP))
    {
      $log->error("failed\ndirectory access: $a");
      return false;
    }
    # store configuration
    $a = $a.DIRECTORY_SEPARATOR.self::FILE_BOT;
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
  function sync(): bool # {{{
  {
    if ($this->changed && ($file = $this->file))
    {
      if (!file_set_json($file, $this->data)) {
        return false;
      }
      $this->changed = false;
    }
    return true;
  }
  # }}}
  function change(object $node, string $k): void # {{{
  {
    $this->changed = true;
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
  public $classCfg;
  final function offsetExists(mixed $k): bool
  {
    if (!($o = $this->classCfg)) {
      $this->classCfg = $o = $this->bot->cfg->data[class_name($this)];
    }
    return isset($o[$k]);
  }
  final function offsetGet(mixed $k): mixed
  {
    return $this->offsetExists($k)
      ? $this->classCfg[$k]
      : null;
  }
  final function offsetSet(mixed $k, mixed $v): void
  {
    if ($this->offsetExists($k)) {
      $this->classCfg[$k] = $v;
    }
  }
  final function offsetUnset(mixed $k): void
  {}
}
# }}}
# }}}
# api {{{
class BotApi extends BotConfigAccess # {{{
{
  # {{{
  const
    CONFIG = [
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
    ],
    METHOD_FILE = [
      'sendPhoto'     => 'photo',
      'sendAudio'     => 'audio',
      'sendDocument'  => 'document',
      'sendVideo'     => 'video',
      'sendAnimation' => 'animation',
      'sendVoice'     => 'voice',
      'sendVideoNote' => 'video_note',
    ];
  public
    $log,$curl,$murl,$acts = [],$gen,$pool;
  # }}}
  static function cError(object $curl): string # {{{
  {
    return ($e = curl_errno($curl))
      ? "[$e] ".curl_error($curl)
      : '';
  }
  # }}}
  static function mError(object $murl): string # {{{
  {
    return ($e = curl_multi_errno($murl))
      ? "[$e] ".curl_multi_strerror($e)
      : '';
  }
  # }}}
  static function decode(string &$a): object # {{{
  {
    if (!($b = json_decode($a, false, 16, JSON_THROW_ON_ERROR)) ||
        !is_object($b) || !isset($b->ok))
    {
      throw ErrorEx::stop("incorrect response\n$a");
    }
    if (!$b->ok)
    {
      if (isset($b->description))
      {
        $a = isset($b->error_code)
          ? '('.$b->error_code.') '.$b->description
          : $b->description;
      }
      throw ErrorEx::stop("unsuccessful response\n".$a);
    }
    if (!isset($b->result)) {
      throw ErrorEx::stop("incorrect response\n$a");
    }
    return $b;
  }
  # }}}
  function __construct(public object $bot) # {{{
  {
    # set base
    $this->log = $bot->log->new('api');
    # set curl instances
    if (!($this->curl = curl_init())) {
      throw ErrorEx::fail('curl_init');
    }
    if (!curl_setopt_array($this->curl, self::CONFIG)) {
      throw ErrorEx::fail("curl_setopt_array\n".self::cError($this->curl));
    }
    if (!($this->murl = curl_multi_init())) {
      throw ErrorEx::fail('curl_multi_init');
    }
  }
  # }}}
  function init(): bool # {{{
  {
    $this->pool = $this['webhook']
      ? BotApiHook::construct($this)
      : BotApiPoll::construct($this);
    return ($this->pool !== null);
  }
  # }}}
  function set(# {{{
    array   &$q,
    ?object &$file,
    string  &$token,
    string  &$method
  ):void
  {
    # handle file attachment
    if ($file) {
      $q[$file->postname] = $file;# put into query
    }
    elseif (isset(self::METHOD_FILE[$method]) &&
            isset($q[$a = self::METHOD_FILE[$method]]) &&
            $q[$a] instanceof BotApiFile)
    {
      $file = $q[$a];# set from query
    }
    # determine token
    if (strlen($token) === 0) {
      $token = $this->bot['token'];
    }
    # compose the request
    $q = [
      CURLOPT_URL  => $this['baseUrl'].$token.'/'.$method,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $q,
    ];
  }
  # }}}
  function send(# {{{
    string  $method,
    array   $req,
    ?object $file  = null,
    string  $token = ''
  ):object|bool
  {
    try
    {
      # prepare
      $curl = $this->curl;
      $this->set($req, $file, $token, $method);
      # operate
      if (!curl_setopt_array($curl, $req)) {
        throw ErrorEx::fail("curl_setopt_array\n".self::cError($curl));
      }
      if (!($a = curl_exec($curl))) {
        throw ErrorEx::fail("curl_exec\n".self::cError($curl));
      }
      # decode response
      $res = self::decode($a)->result;
    }
    catch (Throwable $e)
    {
      $this->log->exception($e);
      $res = false;
    }
    # cleanup and complete
    $file && $file->destruct();
    return $res;
  }
  # }}}
  function action(# {{{
    string  $method,
    array   $req,
    ?object $file  = null,
    string  $token = ''
  ):?object
  {
    $this->set($req, $file, $token, $method);
    return ($a = BotApiAction::construct($this, $req, $file))
      ? ($this->acts[] = $a)
      : null;
  }
  # }}}
  function actionLoop(): bool # {{{
  {
    # check generator
    if (($gen = $this->gen) && $gen->valid()) {
      $gen->send(1);# continue
    }
    else {# start or restart
      $this->gen = $gen = $this->actionGen();
    }
    # check non-finished or successfull
    if ($gen->valid() || $gen->getReturn()) {
      return true;
    }
    # failed, cleanup
    foreach ($this->acts as $a) {
      $a->destruct();
    }
    $this->acts = [];
    return false;
  }
  # }}}
  function actionGen(): Generator # {{{
  {
    try
    {
      # prepare
      $murl  = $this->murl;
      $count = $running = count($this->acts);
      # operate
      while (1)
      {
        # execute requests
        if ($a = curl_multi_exec($murl, $running)) {
          throw ErrorEx::fail("curl_multi_exec\n".curl_multi_strerror($a));
        }
        # check any transfers ready
        if ($running !== $count) {
          break;
        }
        # wait for activity
        while (!$a)
        {
          # check response
          if (($a = curl_multi_select($murl, 0)) < 0)
          {
            $b = BotApi::mError($murl) ?: 'system select failed';
            throw ErrorEx::fail("curl_multi_select\n$b");
          }
          # postpone until continuation
          if (!yield) {
            throw ErrorEx::skip();
          }
        }
        # update as the new handles may be added
        $count = count($this->acts);
      }
      # check transfers
      while ($c = curl_multi_info_read($murl))
      {
        # find and complete the action
        foreach ($this->acts as $a => $action)
        {
          if ($action->curl === $c['handle'])
          {
            $running++;# rise back
            array_splice($this->acts, $a, 1);
            $action->complete($c['result']);
            break;
          }
        }
      }
      # the number of transfers must match,
      # otherwise, there is an error
      if ($running !== $count)
      {
        $b = ($b = self::mError($murl))
          ? "curl_multi_info_read\n".$b
          : 'incomplete transfers remained';
        throw ErrorEx::fail($b);
      }
    }
    catch (Throwable $e)
    {
      $this->log->exception($e);
      return false;
    }
    return true;
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
  function &recieve(): array # {{{
  {
    # prepare
    $bot = $this->bot;
    $res = [];
    # get updates
    if (!($a = $this->pool->get())) {
      return $res;
    }
    # create requests
    foreach ($a as $b)
    {
      if ($c = BotRequest::fromUpdate($bot, $b))
      {
        if ($c instanceof ErrorEx)
        {
          if ($c->level) {
            $this->log->errorObject($b, 'update', $c->getMsg('incorrect'));
          }
          else {
            $this->log->warnObject($b, 'update', 'unknown');
          }
        }
        else {
          $res[] = $c;
        }
      }
    }
    return $res;
  }
  # }}}
  function finit(): void # {{{
  {
    $this->pool->destruct();
    $this->pool = null;
  }
  # }}}
  function __destruct() # {{{
  {
    curl_close($this->curl);
    curl_multi_close($this->murl);
  }
  # }}}
}
# }}}
class BotApiAction # {{{
{
  public $api,$req,$file,$curl,$status = 0,$result;
  static function construct(object $api, array &$req, ?object $file) # {{{
  {
    $I = new self();
    $I->api  = $api;
    $I->req  = $req;
    $I->file = $file;
    if ($I->init()) {
      return $I;
    }
    $file && $file->destruct();
    return null;
  }
  # }}}
  function init(): bool # {{{
  {
    try
    {
      # create new instance
      if (!($this->curl = $curl = curl_init())) {
        throw ErrorEx::fail('curl_init');
      }
      # configure
      if (!curl_setopt_array($curl, BotApi::CONFIG) ||
          !curl_setopt_array($curl, $this->req))
      {
        throw ErrorEx::fail("curl_setopt_array\n".BotApi::cError($curl));
      }
      if ($a = curl_multi_add_handle($this->api->murl, $curl)) {
        throw ErrorEx::fail("curl_multi_add_handle\n".curl_multi_strerror($a));
      }
    }
    catch (Throwable $e)
    {
      $this->api->log->exception($e);
      $curl && curl_close($curl);
      return false;
    }
    return true;
  }
  # }}}
  function __invoke(): int # {{{
  {
    # cycle if not complete
    if (!$this->status && !$this->api->actionLoop()) {
      return $this->status = -1;
    }
    # check complete
    if ($a = $this->status)
    {
      if ($a === -2 && $this->init()) {
        return $this->status = 0;# retry
      }
      $this->destruct();
    }
    return $a;
  }
  # }}}
  function complete(int $error): void # {{{
  {
    try
    {
      # check failed
      if ($error)
      {
        $msg = "transfer failed\n[$error] ".curl_strerror($error);
        throw ErrorEx::warn($msg);
      }
      # check HTTP response code
      $curl = $this->curl;
      if (($error = curl_getinfo($curl, CURLINFO_RESPONSE_CODE)) === false) {
        throw ErrorEx::stop("curl_getinfo\n".BotApi::cError($curl));
      }
      if ($error !== 200) {
        throw ErrorEx::stop("unsuccessful HTTP status: $error");
      }
      # get and decode response text
      $msg = curl_multi_getcontent($curl);
      $this->result = ($msg !== null && strlen($msg))
        ? BotApi::decode($msg)->result
        : null;
      # success
      $this->status = 1;
    }
    catch (Throwable $e)
    {
      # failure
      $this->result = null;
      $this->status = $this->api->log->exception($e)
        ? -1 # critical
        : -2;# recoverable
    }
    $this->finit();
  }
  # }}}
  function finit(): void # {{{
  {
    if ($a = curl_multi_remove_handle($this->api->murl, $this->curl)) {
      $this->api->log->error("curl_multi_remove_handle\n".curl_multi_strerror($a));
    }
    curl_close($this->curl);
    $this->curl = null;
  }
  # }}}
  function destruct(): void # {{{
  {
    $this->curl && $this->finit();
    if ($this->file)
    {
      $this->file->destruct();
      $this->file = null;
    }
    if (!$this->status) {
      $this->status = -1;
    }
  }
  # }}}
}
# }}}
class BotApiPoll # {{{
{
  # {{{
  public
    $api,$log,$murl,$qry,$req,$gen,
    $pause = 0,$fails = 0;
  # }}}
  static function construct(object $api) # {{{
  {
    try
    {
      # create instance
      $I = new self();
      $I->api = $api;
      $I->log = $api->log->new('poll');
      # attach multi-curl instance
      if (!($I->murl = curl_multi_init())) {
        throw ErrorEx::fail('curl_multi_init');
      }
      # attach query and request
      $I->qry = [
        'offset'  => 0,
        'limit'   => $api['limit'],
        'timeout' => $api['timeout'],
      ];
      $I->req = [
        CURLOPT_URL  => $api['baseUrl'].$api->bot['token'].'/getUpdates',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => &$I->qry,
      ];
    }
    catch (Throwable $e)
    {
      $api->log->exception($e);
      if (isset($I) && $I->murl) {
        curl_multi_close($I->murl);
      }
      $I = null;
    }
    return $I;
  }
  # }}}
  function &get(): ?array # {{{
  {
    try
    {
      # check paused
      if ($this->pause)
      {
        if ($this->pause > time()) {
          throw ErrorEx::skip();
        }
        $this->pause = 0;
      }
      # check generator
      if ($this->gen && $this->gen->valid()) {
        $this->gen->send(1);# continue
      }
      else {# start or re-start
        $this->gen = $this->pull($this->qry['timeout']);
      }
      # check non-finished
      if ($this->gen->valid()) {
        throw ErrorEx::skip();
      }
      # check failed
      if ($a = $this->gen->getReturn())
      {
        # manage non-critical error
        if (!$a->level && ++$this->fails > 1)
        {
          if (($b = $this->api['maxFails']) && $this->fails > $b) {
            $a->push(ErrorEx::fail("too many failures ($b)"));
          }
          else {# set retry delay
            $this->pause = time() + $this->api['pause'];
          }
        }
        throw $a;
      }
      # transmission successful,
      # clear repeated fails counter
      if ($this->fails)
      {
        $this->log->info('recovered ('.$this->fails.')');
        $this->fails = 0;
      }
      # check HTTP response code
      $curl = $this->api->curl;
      if (($b = curl_getinfo($curl, CURLINFO_RESPONSE_CODE)) === false) {
        throw ErrorEx::stop("curl_getinfo\n".BotApi::cError($curl));
      }
      if ($b !== 200) {
        throw ErrorEx::stop("unsuccessful HTTP status: $b");
      }
      # get response text
      if (!($c = curl_multi_getcontent($curl))) {
        throw ErrorEx::skip();# empty
      }
      # decode
      $res = BotApi::decode($c)->result;
      # shift to the next offset
      if ($b = count($res)) {
        $this->qry['offset'] = 1 + $res[$b - 1]->update_id;
      }
    }
    catch (Throwable $e)
    {
      if ($this->log->exception($e)) {
        throw ErrorEx::skip();# critical
      }
      $res = null;
    }
    return $res;
  }
  # }}}
  function pull(int $timeout): Generator # {{{
  {
    try
    {
      # prepare
      $error   = null;
      $started = 0;
      $curl    = $this->api->curl;
      $murl    = $this->murl;
      # initialize
      if (!curl_setopt_array($curl, $this->req)) {
        throw ErrorEx::stop("curl_setopt_array\n".BotApi::cError($curl));
      }
      if ($a = curl_multi_add_handle($murl, $curl)) {
        throw ErrorEx::stop("curl_multi_add_handle\n".curl_multi_strerror($a));
      }
      # start polling
      $started = time();
      $running = 1;
      while (1)
      {
        # execute request
        if ($a = curl_multi_exec($murl, $running)) {
          throw ErrorEx::stop("curl_multi_exec\n".curl_multi_strerror($a));
        }
        # check finished
        if (!$running) {
          break;
        }
        # wait for activity
        while (!$a)
        {
          # check response
          if (($a = curl_multi_select($murl, 0)) < 0)
          {
            $b = BotApi::mError($murl) ?: 'system select failed';
            throw ErrorEx::stop("curl_multi_select\n$b");
          }
          # check timeout
          if (time() - $started > $timeout) {
            throw ErrorEx::warn("request timed out ($timeout)");
          }
          # postpone until continuation
          if (!yield) {
            throw ErrorEx::skip();
          }
        }
      }
      # check transfer status
      if (!($c = curl_multi_info_read($murl))) {
        throw ErrorEx::stop("curl_multi_info_read\n".BotApi::mError($murl));
      }
      if ($a = $c['result']) {
        throw ErrorEx::warn('transfer failed: '.curl_strerror($a));
      }
    }
    catch (Throwable $error) {
      $error = ErrorEx::from($error);
    }
    # finalize
    if ($started && ($a = curl_multi_remove_handle($murl, $curl)))
    {
      $b = "curl_multi_remove_handle\n".curl_multi_strerror($a);
      $error = $error
        ? $error->push(ErrorEx::stop($b))
        : ErrorEx::stop($b);
    }
    # complete
    return $error;
  }
  # }}}
  function destruct(): void # {{{
  {
    # gracefully terminate polling
    if ($this->gen)
    {
      # stop current pull
      $this->gen->valid() &&
      $this->gen->send(0);
      # check current offset is set
      if (($a = &$this->qry)['offset'])
      {
        # to confirm handled updates
        # set simplified parameters
        $a['limit']   = 1;
        $a['timeout'] = 0;
        # update in the response (if any) is discarded,
        # it will be retrived at the next polling request
        curl_setopt_array($b = $this->api->curl, $this->req) &&
        curl_exec($b);
      }
    }
    # complete
    curl_multi_close($this->murl);
  }
  # }}}
}
# }}}
class BotApiHook # {{{
{
  static function construct(object $api) # {{{
  {
    try
    {
      # create instance
      $I = new self();
      $I->api = $api;
      $I->log = $api->log->new('hook');
    }
    catch (Throwable $e)
    {
      $I = null;
    }
    return $I;
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
    if ($this->isTemp && $this->name)
    {
      file_unlink($this->name);
      $this->name = '';
    }
  }
  function __destruct() {
    $this->destruct();
  }
}
# }}}
# }}}
# text {{{
class BotText implements ArrayAccess
{
  # {{{
  const
    DIR_PARSER  = 'sm-mustache',
    FILE_PARSER = 'mustache.php',
    FILE_TEXTS  = 'texts.inc',
    FILE_CAPS   = 'captions.inc',
    FILE_EMOJIS = 'emojis.inc';
  public
    $log,$hlp,$tp,$lang,$texts,$caps;
  # }}}
  function __construct(public object $bot) # {{{
  {
    $this->log = $bot->log->new('text');
    $this->hlp = new ArrayUnion([
      'NBSP'    => "\xC2\xA0",# non-breakable space
      'ZWSP'    => "\xE2\x80\x8B",# zero-width space
      'ZS'      => "\xE3\x80\x80",# Ideographic Space
      'LINEPAD' => "\xC2\xAD".str_repeat(' ', 120)."\xC2\xAD",
      'BEG'     => "\xC2\xAD\n",
      'END'     => "\xC2\xAD",# SOFT HYPHEN U+00AD
      'BR'      => "\n",
    ]);
  }
  # }}}
  function init(): bool # {{{
  {
    # prepare
    $cfg = $this->bot->cfg;
    $inc = $cfg->dirInc;
    $src = $cfg->dirSrc;
    # add emoji helper
    $this->hlp->pushRef(file_get_array($inc.self::FILE_EMOJIS));
    # load template parser
    require $inc.self::DIR_PARSER.DIRECTORY_SEPARATOR.self::FILE_PARSER;
    $this->tp = Mustache::construct([
      'logger' => Closure::fromCallable([$this, 'error']),
      'helper' => $this->hlp,
    ]);
    if (!$this->tp) {
      return false;
    }
    # load texts
    $this->texts = file_get_array($inc.self::FILE_TEXTS);
    $this->caps  = file_get_array($inc.self::FILE_CAPS);
    if ($a = file_get_array($src.self::FILE_TEXTS)) {
      array_import_all($this->texts, $a);
    }
    if ($a = file_get_array($src.self::FILE_CAPS)) {
      array_import_all($this->caps, $b);
    }
    $this->refine($this->texts);
    $this->refine($this->caps);
    return true;
  }
  # }}}
  function error(string $msg, int $level): void # {{{
  {
    $level && $this->log->error($msg);
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
        $b = $this->tp->render($b, '{: :}', []);
      }
    }
  }
  # }}}
  # [texts] access {{{
  function offsetExists(mixed $k): bool {
    return isset($this->texts[$this->lang][$k]);
  }
  function offsetGet(mixed $k): mixed {
    return $this->texts[$this->lang][$k] ?? '';
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
# commands {{{
class BotCommands implements ArrayAccess
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
      # determine input flag
      if (!isset($node[$a = 'input'])) {
        $node[$a] = method_exists($type, 'inputAccept');
      }
      # invoke type refiner
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
# file {{{
class BotFile
{
  # {{{
  const
    DIR_FONT = 'font',
    DIR_IMG  = 'img',
    EXP_FONT = '{ttf,otf}',
    EXP_IMG  = '{jpg,jpeg,png}',
    FILE_ID  = 'file_id.json';
  public
    $log,$fids,$img = [],$font = [],$data = [];
  # }}}
  function __construct(public object $bot) # {{{
  {
    $this->log = $bot->log->new('file');
  }
  # }}}
  function init(): bool # {{{
  {
    # load file/id data
    $this->fids = new BotFileData(
      $this->log, $this->bot->cfg->dirData.self::FILE_ID
    );
    if (!$this->fids->load()) {
      return false;
    }
    # load name/file maps
    return $this->loadMaps();
  }
  # }}}
  function loadMaps(): bool # {{{
  {
    try
    {
      # prepare
      $this->font = [];
      $this->img  = [];
      $cfg = $this->bot->cfg;
      $flg = GLOB_BRACE|GLOB_NOSORT|GLOB_NOESCAPE;
      # scan images
      $a = self::DIR_IMG.DIRECTORY_SEPARATOR;
      $b = [$cfg->dirInc.$a];
      file_exists($c = $cfg->dirSrc.$a)  && ($b[] = $c);
      file_exists($c = $cfg->dirData.$a) && ($b[] = $c);
      foreach ($b as $a)
      {
        $i = strlen($a);
        $a = $a.'*.'.self::EXP_IMG;
        foreach (glob($a, $flg) as $c)
        {
          $j = strrpos($c, '.') - $i;
          $this->img[substr($c, $i, $j)] = $c;
        }
      }
      # scan fonts
      $a = self::DIR_FONT.DIRECTORY_SEPARATOR;
      $b = [$cfg->dirInc.$a];
      file_exists($c = $cfg->dirSrc.$a)  && ($b[] = $c);
      file_exists($c = $cfg->dirData.$a) && ($b[] = $c);
      foreach ($b as $a)
      {
        $i = strlen($a);
        $a = $a.'*.'.self::EXP_FONT;
        foreach (glob($a, $flg) as $c)
        {
          $j = strrpos($c, '.') - $i;
          $this->font[substr($c, $i, $j)] = $c;
        }
      }
    }
    catch (Throwable $e)
    {
      $this->log->exception($e);
      return false;
    }
    return true;
  }
  # }}}
  function getId(string $k): string # {{{
  {
    return $this->fids[$k] ?? '';
  }
  # }}}
  function getImage(string $name): string # {{{
  {
    if (!isset($this->img[$name]))
    {
      $this->log->error($name, 'image not found');
      return '';
    }
    return $this->img[$name];
  }
  # }}}
  function getData(string $file, int $maxDepth = 0): ?object # {{{
  {
    # check already exists
    if (isset($this->data[$file])) {
      return $this->data[$file];
    }
    # construct and store new instance
    $o = new BotFileData($this->log, $file, $maxDepth);
    return $o->load()
      ? ($this->data[$file] = $o)
      : null;
  }
  # }}}
  function sync(): void # {{{
  {
    $this->fids->sync();
    foreach ($this->data as $o) {
      $o->sync();
    }
  }
  # }}}
  function finit(): void # {{{
  {
    $this->sync();
  }
  # }}}
}
class BotFileData implements ArrayAccess
{
  public $node,$time,$changed;
  function __construct(# {{{
    public object $log,
    public string $file,
    public int    $limit = 0,
  ) {}
  # }}}
  function load(): bool # {{{
  {
    if (($a = file_get_json($this->file)) === null)
    {
      $this->log->error('get', $this->file);
      return false;
    }
    $this->node = new ArrayNode($a, $this, $this->limit);
    $this->time = time();
    $this->changed = 0;
    return true;
  }
  # }}}
  # [node] access {{{
  function offsetExists(mixed $k): bool {
    return isset($this->node[$k]);
  }
  function offsetGet(mixed $k): mixed {
    return $this->node[$k];
  }
  function offsetSet(mixed $k, mixed $v): void {
    $this->node[$k] = $v;
  }
  function offsetUnset(mixed $k): void {
    $this->node[$k] = null;
  }
  # }}}
  function change(): void # {{{
  {
    $this->changed++;
  }
  # }}}
  function sync(): bool # {{{
  {
    if ($this->changed)
    {
      if (!file_set_json($this->file, $this->node))
      {
        $this->log->error('set', $this->file);
        return false;
      }
      $this->time = time();
      $this->changed = 0;
    }
    return true;
  }
  # }}}
}
# }}}
# process {{{
class BotProcess extends BotConfigAccess # {{{
{
  # {{{
  const
    PROC_UUID       = '22c4408d490143b5b29f0640755327db',
    BUF_UUID        = 'c25de777e80d49f69b6b7b57091d70d5',
    BUF_SIZE        = 200,
    TIMEOUT         = 15000,# ms, response timeout
    EXP_PIDFILE     = '/^bot([-0-9]+)\\.pid$/',
    EXIT_CLEAN      = 0,
    EXIT_DIRTY      = 1,
    EXIT_UNEXPECTED = 2,
    EXIT_RESTART    = 100,
    EXIT_SIGINT     = 101,
    EXIT_SIGTERM    = 102;
  public
    $id,$log,$active,$file,$syncbuf,
    $started = false,$map = [],
    $exitcode = self::EXIT_DIRTY;
  # }}}
  static function construct(object $bot): object # {{{
  {
    # create specific instance
    return $bot->id
      ? ($bot->task
        ? new BotTaskProcess($bot)
        : new self($bot))
      : new BotConsoleProcess($bot);
  }
  # }}}
  function __construct(public object $bot) # {{{
  {
    $k = $bot->id.$bot->task;
    $this->id      = strval(getmypid());
    $this->log     = $bot->log->new('proc');
    $this->active  = new SyncEvent($k.self::PROC_UUID, 1);
    $this->file    = $bot->cfg->dirDataRoot.'bot'.$bot->id.'.pid';
    $this->syncbuf = new Syncbuf(
      $k.self::BUF_UUID, self::BUF_SIZE, self::TIMEOUT
    );
  }
  # }}}
  function init(): bool # {{{
  {
    try
    {
      # check already running
      if ($this->active->wait(0)) {
        throw ErrorEx::fail('is already started');
      }
      # to enforce graceful termination,
      # handle termination signals
      if (function_exists($f = 'sapi_windows_set_ctrl_handler'))
      {
        # WinOS
        $self = $this;
        $f(function (int $e) use ($self) {
          $self->signal($e === PHP_WINDOWS_EVENT_CTRL_C);
        });
      }
      else
      {
        # NixOS
        # ...
      }
      # start self
      if (!($this->started = $this->active->fire())) {
        throw ErrorEx::fail('failed to fire');
      }
      # create pidfile
      if (!$this->bot->task &&
          file_put_contents($this->file, $this->id) === false)
      {
        throw ErrorEx::fail($this->file);
      }
      # start children
      $this->startChildren();
      $this->exitcode = self::EXIT_UNEXPECTED;
    }
    catch (Throwable $e)
    {
      $this->log->exception($e);
      $this->started && $this->stopChildren();
      return false;
    }
    return true;
  }
  # }}}
  function signal(bool $interrupt): void # {{{
  {}
  # }}}
  function check(): bool # {{{
  {
    # check children
    foreach ($this->map as $id => $child)
    {
      if (!$child->active->wait(0))
      {
        $child->log->warn('unexpected deactivation');
        unset($this->map[$id]);
      }
    }
    # check self
    if (!$this->active->wait(0) || !file_exists($this->file)) {
      throw ErrorEx::stop('forced deactivation');
    }
    # flush console
    $this->bot->console->flush();
    # handle commands
    return $this->command();
  }
  # }}}
  function command(): bool # {{{
  {
    # read command
    if (!strlen($a = $this->syncbuf->read())) {
      return true;
    }
    # reply
    $this->log->infoInput('command', $a);
    switch ($a) {
    case 'stop':
      return false;
    case 'info':
      # compose bot <pid:name> string
      $bot = $this->bot;
      $a = $bot->task
        ? 'task:'.$bot->task
        : $bot['name'];
      $a = $this->id.':'.$a;
      # send answer
      $this->syncbuf->write($a);
      break;
    }
    return true;
  }
  # }}}
  function busy(): void # {{{
  {
    # reset delay
    # ...
  }
  # }}}
  function wait(): void # {{{
  {
    # TODO: speedup / slowdown
    usleep(200000);
  }
  # }}}
  function loop(): void # {{{
  {
    try
    {
      # prepare
      $bot = $this->bot;
      $this->log->bannerCommands();
      # work
      while ($this->check())
      {
        $bot->spin()
          ? $this->busy()
          : $this->wait();
      }
      # complete
      if ($this->exitcode === self::EXIT_UNEXPECTED) {
        $this->exitcode = self::EXIT_CLEAN;
      }
    }
    catch (Throwable $e)
    {
      $this->log->exception($e);
      $this->syncbuf->reset();
      $this->exitcode = self::EXIT_DIRTY;
    }
  }
  # }}}
  function startChildren(): void # {{{
  {}
  # }}}
  function start(string $id): bool # {{{
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
  function get(string $id): ?object # {{{
  {
    /***
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
    /***/
    return null;
  }
  # }}}
  function &getActive(): array # {{{
  {
    $dir = $this->bot->cfg->dirDataRoot;
    $lst = [];
    if (($a = scandir($dir, SCANDIR_SORT_NONE)) === false) {
      throw ErrorEx::fail('scandir', $dir);
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
  function stop(string $id): bool # {{{
  {
    /***
    if (!($slave = $this->map[$id] ?? null))
    {
      $this->log->warn(__FUNCTION__."($id): was not started");
      return false;
    }
    $slave->stop();
    unset($this->map[$id]);
    /***/
    return true;
  }
  # }}}
  function stopChildren(): void # {{{
  {
    foreach ($this->map as $child) {
      $child->stop();
    }
    $this->map = [];
  }
  # }}}
  function finit(): void # {{{
  {
    $this['list'] = array_string_keys($this->map);
    $this->stopChildren();
  }
  # }}}
  function destruct(): void # {{{
  {
    if ($this->started)
    {
      # report exit state
      switch ($this->exitcode) {
      case self::EXIT_CLEAN:
      case self::EXIT_SIGINT:
      case self::EXIT_SIGTERM:
        $this->log->info('exit', 'clean');
        break;
      case self::EXIT_DIRTY:
        $this->log->error('exit', 'dirty');
        break;
      case self::EXIT_UNEXPECTED:
        $this->log->error('exit', 'unexpected');
        break;
      }
      # terminate
      $this->started = false;
      $this->active->reset();
      if (!$this->bot->task) {
        file_unlink($this->file);
      }
    }
  }
  # }}}
}
# }}}
class BotConsoleProcess extends BotProcess # {{{
{
  function __construct(public object $bot) # {{{
  {
    $this->log    = $bot->log->new('proc');
    $this->active = $bot->console->active;
    $this->file   = $bot->cfg->dirDataRoot.'console.pid';
  }
  # }}}
  function signal(bool $interrupt): void # {{{
  {
    $this->log->infoInput('signal', ($interrupt ? 'Ctrl+C' : 'Ctrl+Break'));
    $this->exitcode = $interrupt
      ? self::EXIT_SIGINT
      : self::EXIT_SIGTERM;
  }
  # }}}
  function command(): bool # {{{
  {
    $io = $this->bot->console->conio;
    while (strlen($k = $io->getch()))
    {
      if (strpos('rqx', $k = lcfirst($k)) === false) {
        continue;
      }
      $this->log->infoInput(__FUNCTION__, $k);
      switch ($k) {
      case 'r':
        $this->exitcode = self::EXIT_RESTART;
        return false;
      case 'q':
        $this->exitcode = self::EXIT_SIGINT;
        return false;
      case 'x':
        $this->exitcode = self::EXIT_CLEAN;
        return false;
      }
    }
    return ($this->exitcode === self::EXIT_UNEXPECTED);
  }
  # }}}
  function loop(): void # {{{
  {
    try
    {
      $this->log->bannerConsole();
      while ($this->check()) {
        usleep(200000);# 200ms
      }
    }
    catch (Throwable $e)
    {
      $this->log->exception($e);
      $this->exitcode = self::EXIT_DIRTY;
    }
  }
  # }}}
  function startChildren(): void # {{{
  {
    # get planned and currently running bots
    $a = $this['list'];
    $b = $this->getActive();
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
    # start
    foreach ($a as $c)
    {
      if (!$this->start($c)) {
        throw ErrorEx::skip();
      }
    }
  }
  # }}}
  function finit(): void # {{{
  {
    # store current list
    $this['list'] = array_string_keys($this->map);
    # keep bots running at interruption, stop otherwise
    if ($this->exitcode !== self::EXIT_SIGINT) {
      $this->stopChildren();
    }
    # flush remaining logs
    $this->bot->console->flush();
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
    PROC_WAIT_STOP  = [200,10],
    PROC_DESC  = [
      #0 => ['pipe','r'],# stdin
      1 => ['pipe','w'],# stdout
      2 => ['pipe','w'],# stderr
    ],
    PROC_OPTS = [# WinOS only
      'suppress_errors' => false,
      'bypass_shell'    => true,
      'blocking_pipes'  => true,
      'create_process_group' => false,
      'create_new_console'   => false,
    ];
  public
    $proc,$pid,$log,$active,$syncbuf;
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
  static function construct(object $proc, string $key): ?self # {{{
  {
    try
    {
      # create instance
      $I = new self();
      $I->proc    = $proc;
      $I->log     = $proc->log->new($key);
      $I->active  = new SyncEvent($key.$proc::PROC_UUID, 1);
      $I->syncbuf = new Syncbuf(
        $key.$proc::BUF_UUID, $proc::BUF_SIZE, $proc::TIMEOUT
      );
      # reset command interface
      $I->syncbuf->reset();
      # check process is already running or try to start
      if ($I->active->wait(0)) {
        $I->log->info('connected');
      }
      else {
        $I->start($key);
      }
      # set bot process information (pid:name)
      if (!($a = $I->syncbuf->writeRead('info')))
      {
        $I->log->error('no response (timed out)');
        $I->stop();
        throw ErrorEx::skip();
      }
      $a = explode(':', $a, 2);
      $I->id = $a[0];
      $I->log->name = $a[1];
    }
    catch (Throwable $e)
    {
      $I->log->exception($e);
      $I = null;
    }
    return $I;
  }
  # }}}
  function start(string $key): void # {{{
  {
    # create process
    $dir  = __DIR__.DIRECTORY_SEPARATOR;
    $cmd  = '"'.PHP_BINARY.'" -f "'.$dir.self::FILE_START.'" '.$key;
    $pipe = null;
    $proc = proc_open(
      $cmd, self::PROC_DESC, $pipe, $dir, null, self::PROC_OPTS
    );
    # check successful
    if (!is_resource($proc)) {
      throw ErrorEx::fail("failed\nproc_open($cmd)");
    }
    # wait started
    $c = 8;
    while (!$this->active->wait(500) && --$c)
    {
      if (!($a = proc_get_status($proc))) {
        throw ErrorEx::fail("failed\nproc_get_status()");
      }
      if (!$a['running'])
      {
        $a = 'exitcode: '.$a['exitcode'];
        if ($b = self::closePipes($pipe, true)) {
          $a .= "\noutput:\n".str_replace("\n", "\n ", $b);
        }
        throw ErrorEx::fail("failed\n$a");
      }
    }
    # check successful
    if (!$c)
    {
      proc_terminate($proc);
      throw ErrorEx::fail('activation timed out');
    }
    # complete
    self::closePipes($pipe);
    unset($proc, $pipe);
    # from now on, process must not output anything to STDOUT/ERR,
    # otherwise it may broke (because of writing to closed resource)
    $this->log->info('started');
  }
  # }}}
  function command(string $cmd): bool # {{{
  {
    try
    {
      $this->syncbuf->write($cmd);
      $this->log->info($cmd);
    }
    catch (Throwable $e)
    {
      $this->log->exception($e);
      $this->syncbuf->reset();
      return false;
    }
    return true;
  }
  # }}}
  function stop(): bool # {{{
  {
    if (!$this->command('stop'))
    {
      $this->active->reset();
      return false;
    }
    $a = 20;
    while ($this->active->wait(0) && $a--) {
      usleep(100000);
    }
    if ($a) {
      return true;
    }
    $this->log->error(__FUNCTION__, 'timed out');
    $this->active->reset();
    return false;
  }
  # }}}
}
# }}}
# }}}
# TODO: bot {{{
class Bot extends BotConfigAccess
{
  # TODO: request attachment routine
  # {{{
  const
    MESSAGE_LIFETIME = 48*60*60,
    EXP_USERNAME = '/^[a-z]\w{4,32}$/i',
    EXP_BOTNAME  = '/^[a-z]\w{1,29}bot$/i',
    INIT = [
      'console','cfg','log','api','text','cmd','file','proc'
    ];
  public
    $bot,$pid,$id,$tid,
    $console,$log,$cfg,$api,$text,$cmd,$file,$proc,$inited = [],
    $chats = [],$users = [],$queue = [],
    $chat,$user;
  # }}}
  static function start(string $args = ''): never # {{{
  {
    try
    {
      # create instance
      $args = explode(':', $args, 2);
      $bot  = new self($args[0], $args[1] ?? '');
      # initialize
      if ($bot->init())
      {
        # operate
        $bot->proc->loop();
        $bot->finit();
      }
      $e = $bot->proc->exitcode;
    }
    catch (Throwable $e)
    {
      $e = "\n".__METHOD__."\n".BotLog::throwableToString($e);
      $args ? error_log($e) : fwrite(STDOUT, $e);
      $e = 2;
    }
    exit($e);
  }
  # }}}
  function __construct(string $id, string $task) # {{{
  {
    $this->bot     = $this;# for BotConfigAccess
    $this->id      = $id;
    $this->task    = $task;
    $this->console = BotConsole::construct($this);
    $this->log     = new BotLog($this);
    $this->cfg     = new BotConfig($this, $id);
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
      # configure environment
      set_time_limit(0);
      error_reporting(E_ALL);
      if ($k = $this->id)
      {
        ini_set('log_errors', '1');
        ini_set('log_errors_max_len', '0');
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        ini_set('error_log', $this->cfg->dirDataRoot."bot$k.error");
      }
      else
      {
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
      }
      # any mild error types (warnings/notices/deprecations),
      # except supressed (@), are rised to exception level
      set_error_handler(function(int $no, string $msg, string $file, int $line) {
        throw ErrorEx::rise($no, $msg);
      });
      # initialize in the right order
      foreach (self::INIT as $k)
      {
        if (!$this->$k->init()) {
          throw ErrorEx::stop($k, 'failed to initialize');
        }
        array_unshift($this->inited, $k);
      }
      # guard against non-recoverable errors
      register_shutdown_function(
        Closure::fromCallable([$this, 'finit'])
      );
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
  function spin(): int # {{{
  {
    # load requests into individual queues
    if (($count = count($this->queue)) < 1000)
    {
      foreach ($this->api->recieve() as $req)
      {
        if (isset($this->queue[$id = $req->chat->id])) {
          $this->queue[$id][] = $req;
        }
        else
        {
          $this->queue[$id] = [$req];
          $count++;
        }
      }
    }
    # work
    $done = 0;
    while ($count && $done < 100)
    {
      foreach ($this->queue as $id => &$q)
      {
        if ($q[0]->complete())
        {
          if (count($q) === 1)
          {
            unset($this->queue[$id]);
            $count--;
          }
          else {
            array_shift($q);
          }
          $done++;
        }
      }
    }
    # cleanup
    # ...
    # done
    return $done;
  }
  # }}}
  function update(object $req): bool # {{{
  {
    $this->user = $req->user;
    $this->chat = $req->chat;
    $this->text->lang = $req->chat->lang;
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
      $this->proc->destruct();
    }
  }
  # }}}
}
# }}}
# processing
# request {{{
abstract class BotRequest extends BotConfigAccess # {{{
{
  # {{{
  public
    $bot,$data,$user,$chat,$log,
    $item,$func = '',$args = '',$resp;
  # }}}
  final static function fromUpdate(object $bot, object $upd): ?object # {{{
  {
    if ($o = $upd->callback_query ?? null)
    {
      # {{{
      if (!($from = $o->from ?? null)) {
        return ErrorEx::stop('missing callback_query.from');
      }
      if (isset($o->data))
      {
        return ($chat = $o->message->chat ?? null)
          ? BotRequestCallback::construct($bot, $o, $from, $chat)
          : ErrorEx::stop('missing callback_query.message.chat');
      }
      #if (isset($o->game_short_name)) {
      #  return new BotRequestGame($bot, $o, $from);
      #}
      return ErrorEx::skip();
      # }}}
    }
    if ($o = $upd->message ?? null)
    {
      # {{{
      if (!($from = $o->from ?? null)) {
        return ErrorEx::stop('missing message.from');
      }
      if (!($chat = $o->chat ?? null)) {
        return ErrorEx::stop('missing message.chat');
      }
      return (isset($o->text) && ($o->text[0] === '/'))
        ? BotRequestCommand::construct($bot, $o, $from, $chat)
        : BotRequestInput::construct($bot, $o, $from, $chat);
      # }}}
    }
    if ($o = $upd->my_chat_member ?? null)
    {
      # {{{
      if (!isset($o->from) || !isset($o->chat) || !isset($o->date)) {
        return ErrorEx::stop('missing my_chat_member.[from/chat/date]');
      }
      if (!isset($o->old_chat_member) || !isset($o->new_chat_member)) {
        return ErrorEx::stop('missing my_chat_member.[old_chat_member/new_chat_member]');
      }
      return BotRequestMember::construct($bot, $o, $o->from, $o->chat);
      # }}}
    }
    #if (isset($upd->inline_query)) {}
    #if (isset($upd->edited_message)) {}
    return ErrorEx::skip();
  }
  # }}}
  final static function construct(# {{{
    object  $bot,
    object  $data,
    object  $user,
    ?object $chat
  ):?object
  {
    # construct user and chat
    if (!($user = BotUser::construct($bot, $user))) {
      return null;
    }
    $chat = $chat
      ? BotChat::construct($bot, $chat)
      : $user->getChat();
    if (!$chat) {
      return null;
    }
    # create and initialize instance
    $I = new static();
    $I->bot  = $bot;
    $I->data = $data;
    $I->user = $user;
    $I->chat = $chat;
    $I->log  = $chat->isGroup()
      ? $chat->log->new($user->fullname)
      : $chat->log;
    # complete
    return $I->parse() ? $I : null;
  }
  # }}}
  final function complete(): bool # {{{
  {
    # attach
    $this->bot->chat = $this->chat;
    $this->bot->user = $this->user;
    # reply
    if ($this->resp) {
      return ($this->resp->complete() !== 0);
    }
    # render response
    return !($this->resp = $this->render());
  }
  # }}}
  abstract function parse(): bool;
  #abstract function render(): ?object;
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
class BotRequestInput extends BotRequest # {{{
{
  function parse(): bool # {{{
  {
    return false;
    /*
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
    */
  }
  # }}}
  function reply(): bool # {{{
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
  # {{{
  const
    # /<ITEM>[!<FUNC>][ <ARGS>][@<BOTNAME>]
    SYNTAX_EXP = '|^\/([a-z][a-z0-9:/-]+){1}(!([a-z]+)){0,1}( ([^@]+)){0,1}(@([a-z_]+bot)){0,1}$|i',
    MAX_LENGTH = 200;
  # }}}
  function parse(): bool # {{{
  {
    # prepare
    $msg  = $this->data;
    $chat = $this->chat;
    $bot  = $chat->bot;
    # check
    if (($a = strlen($msg->text)) < 2 || $a > self::MAX_LENGTH ||
        !preg_match_all(self::SYNTAX_EXP, $msg->text, $a))
    {
      if ($chat->isUser())
      {
        $this->log->warnInput("incorrect", $msg->text);
        return true;
      }
      return false;
    }
    # extract
    $item = strtolower($a[1][0]);
    $func = $a[3][0];
    $args = $a[5][0];
    $name = $a[7][0];
    # check different bot addressed
    if ($name && $name !== $bot['name'] && $chat->isGroup()) {
      return false;# ignore
    }
    # check deep linking (tg://<BOTNAME>?start=<ARGS>)
    if (false && $item === 'start' && !$func && $args)
    {
      $item = strtolower($args);
      $args = '';
    }
    # clear item path separators [:-/]
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
      if ($chat->isUser())
      {
        $this->log->warnInput("unknown", $msg->text);
        return true;
      }
      return false;
    }
    # complete
    $this->log->infoInput('command', $msg->text);
    $this->item = $bot->cmd[$item];
    $this->func = $func;
    $this->args = $args;
    return true;
  }
  # }}}
  function render(): ?object # {{{
  {
    $data = $this->data;
    return PromiseAll::construct(
      $this->chat->action($this),
      $this->bot->api->action('deleteMessage', [
        'chat_id'    => $data->chat->id,
        'message_id' => $data->message_id,
      ])
    );
  }
  # }}}
}
# }}}
class BotRequestMember extends BotRequest # {{{
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
# TODO: chat {{{
class BotChat # {{{
{
  # {{{
  const
    FILE_CHAT = 'chat.json',
    FILE_CONF = 'config.json',
    CHAT_TYPE = [
      'private'=>0,
      'group'=>1,'supergroup'=>1,
      'channel'=>2,
    ],
    CHAT_NAME = ['user','group','channel'];
  public
    $bot,$time,$id,$type,$name,$username,$log,$dir,
    $view,$conf,$opts,$lang,$info,$changed = false;
  # }}}
  static function construct(object $bot, object $chat) # {{{
  {
    # check object already exists
    if (isset($bot->chats[$id = strval($chat->id)])) {
      return $bot->chats[$id];
    }
    try
    {
      # create new instance
      $I = new self();
      $I->bot  = $bot;
      $I->time = time();
      $I->id   = $id;
      $I->type = $type = self::CHAT_TYPE[$chat->type];
      $I->name = ($type === 0)
        ? ($chat->first_name ?? '')
        : ($chat->title ?? '');
      $I->username = $chat->username ?? '';
      # set logger
      $a = $I->username
        ? '@'.$I->username
        : $I->name.'#'.$id;
      $I->log = $bot->log->new(self::CHAT_NAME[$type])->new($a);
      # set working directory
      $a = match ($type) {
        0 => $bot->cfg->dirUsr,
        1 => $bot->cfg->dirGrp,
        2 => $bot->cfg->dirChan,
      };
      if (!dir_check_make($I->dir = $a.$id.DIRECTORY_SEPARATOR)) {
        throw ErrorEx::fail($a);
      }
      # initialize
      if (!$I->init() || !$I->sync()) {
        throw ErrorEx::skip();
      }
    }
    catch (Throwable $e)
    {
      $bot->log->exception($e);
      return null;
    }
    # store and complete
    return $bot->chats[$id] = $I;
  }
  # }}}
  function init(): bool # {{{
  {
    # get chat configuration
    $file = $this->dir.self::FILE_CONF;
    if (($a = file_get_json($file)) === null)
    {
      $this->log->error($file);
      return false;
    }
    # initialize
    if ($a)
    {
      # previous
      $this->view = new BotChatView($this, $a[0]);
      $this->conf = new BotChatConfig($this, $a[1]);
      $this->opts = new BotChatConfig($this, $a[2]);
      $this->lang = $a[3];
      $this->info = $a[4];
    }
    else
    {
      # new
      $a = ['chat_id' => $this->id];
      if (!($a = $this->bot->api->send('getChat', $a)))
      {
        $this->log->error('failed to get chat details');
        return false;
      }
      $this->log->infoObject($a, 'new chat');
      $this->view = new BotChatView($this);
      $this->conf = new BotChatConfig($this);
      $this->opts = new BotChatConfig($this);
      $this->lang = $this->bot['lang'];
      $this->info = (array)$a;
      $this->changed = true;
    }
    return true;
  }
  # }}}
  function reset(): bool # {{{
  {
    $this->changed = false;
    return $this->init();
  }
  # }}}
  function sync(): bool # {{{
  {
    if ($this->changed)
    {
      $a = $this->dir.self::FILE_CONF;
      $b = [
        $this->view, $this->conf, $this->opts,
        $this->lang, $this->info
      ];
      if (!file_set_json($a, $b)) {
        return false;
      }
      $this->changed = false;
    }
    return true;
  }
  # }}}
  function change(): void # {{{
  {
    $this->changed = false;
  }
  # }}}
  function update(object $req): ?object # {{{
  {
    return null;
    /*kicked*
    # prepare
    $user = $this->user;
    # remove chatfile
    if (!@unlink($file = $user->dir.self::FILE_CHAT)) {
      $user->log->error("unlink($file) failed");
    }
    # store details
    $user->bot->file->setJSON($user->dir.self::FILE_KICK, $u);
    $user->log->info('chat removed');
    /***/
  }
  # }}}
  function action(object $req): ?object # {{{
  {
    # get item of interest
    if (!($item1 = $req->item)) {
      return null;
    }
    try
    {
      # get current item
      $item0 = ($node0 = $this->view->nodeOfItem($item1))
        ? $node0->item : null;
      # handle common navigation
      switch ($req->func) {
      case 'up':
        # climbing up the tree, select parent
        if ($item0 !== $item1) {
          throw ErrorEx::skip();
        }
        $item1 = $item1->parent;
        break;
      case 'close':
        # item should be deleted from the view
        if ($item0 !== $item1) {
          throw ErrorEx::skip();
        }
        $item1 = null;
        break;
      }
      # determine kind of action
      $a = $item1
        ? ($item0
          ? ($item0 === $item1
            ? 0
            : 1)
          : 2)
        : 3;
      # trigger item events
      switch ($a) {
      case 0:# update
        if (!$item1->event(3, $req)) {
          throw ErrorEx::skip();
        }
        break;
      case 1:# replace
        if (!$item0->event(2, $req)) {
          throw ErrorEx::skip();
        }
      case 2:# create
        $b = $this->conf[$item1->id]?->node ? 1 : 0;
        if (!$item1->event($b, $req)) {
          throw ErrorEx::skip();
        }
        break;
      case 3:# delete
        if (!$item0->event(2, $req)) {
          throw ErrorEx::skip();
        }
        break;
      }
      # create node
      if ($item1)
      {
        if (($msg = $item1->render($req)) === null) {
          throw ErrorEx::skip();
        }
        $node1 = $msg
          ? new BotChatNode($this, $item1, $msg)
          : null;
      }
      else {
        $node1 = null;
      }
      throw ErrorEx::fail('test stop');
    }
    catch (Throwable $e)
    {
      $req->log->exception($e);
      $this->reset();
      return null;
    }
    return new BotChatAction($this, $node0, $node1);
  }
  # }}}
  function isUser(): bool # {{{
  {
    return $this->type === 0;
  }
  # }}}
  function isGroup(): bool # {{{
  {
    return $this->type === 1;
  }
  # }}}
  function isChannel(): bool # {{{
  {
    return $this->type === 2;
  }
  # }}}
}
# }}}
class BotChatView implements JsonSerializable # {{{
{
  public $queue = [];
  function __construct(public object $chat, array &$queue = []) # {{{
  {
    foreach ($queue as &$a)
    {
      if ($item = $chat->bot->cmd[$a[0]])
      {
        foreach ($a[1] as &$msg) {
          $msg = new $msg[0]($chat->bot, $msg[1], $msg[2]);
        }
        $this->queue[] = new BotChatNode($chat, $item, $a[1], $a[2], $a[3]);
      }
      else {
        $chat->changed = true;
      }
    }
  }
  # }}}
  function jsonSerialize(): array # {{{
  {
    return $this->queue;
  }
  # }}}
  function nodeOfItem(object $item): ?object # {{{
  {
    foreach ($this->queue as $i => $node)
    {
      if ($node->item === $item ||
          $node->item->root === $item->root)
      {
        return $node;
      }
    }
    return null;
  }
  # }}}
  function nodeOfMessage(int $id): ?object # {{{
  {
    foreach ($this->queue as $node)
    {
      foreach ($node->msg as $msg)
      {
        if ($msg->id === $id) {
          return $node;
        }
      }
    }
    return null;
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
class BotChatConfig extends ArrayNode # {{{
{
  function __construct(public object $chat, array &$node = [])
  {
    # check and clear data from non-existent items
    foreach ($node as $id => &$cfg)
    {
      if (!isset($chat->bot->cmd[$id]))
      {
        unset($node[$id]);
        $chat->changed = true;
      }
    }
    parent::__construct($node, $chat, 1);
  }
  function jsonSerialize(): array
  {
    # serializer is called upon saving,
    # remove empty containers
    foreach ($this->node as $id => $cfg)
    {
      if (!$cfg->node) {
        unset($this->node[$id]);
      }
    }
    return $this->node;
  }
}
# }}}
class BotChatNode implements JsonSerializable # {{{
{
  static function load(object $chat, array &$data): ?self # {{{
  {
    if (!($item = $chat->bot->cmd[$data[0]])) {
      return null;
    }
    foreach ($data[1] as &$msg) {
      $msg = new $msg[0]($chat->bot, $msg[1], $msg[2]);
    }
    return new self(
      $chat, $item, $data[1], $data[2], $data[3]
    );
  }
  # }}}
  static function send(object $chat, object $item): ?self # {{{
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
      $chat, $item, $time, $item->msgs, $chat->user->id
    );
  }
  # }}}
  function __construct(# {{{
    public object  $chat,
    public object  $item,
    public array   $msg,
    public string  $owner = '',
    public int     $time  = 0
  ) {}
  # }}}
  function jsonSerialize(): array # {{{
  {
    return [
      $this->item->id, $this->msg,
      $this->owner, $this->time
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
class BotChatAction # {{{
{
  function __construct(# {{{
    public object   $chat,
    public ?object  $node0,
    public ?object  $node1
  ) {}
  # }}}
  function __invoke(): int # {{{
  {
    ###
    return 1;
  }
  # }}}
  ###
  function deleteTT(): bool # {{{
  {
    try
    {
      # prepare
      $log  = $this->chat->log;
      $node = $this->nodeCurrent;
      $path = $node->skel['path'];
      # eject node from the view
      if (($i = array_search($node, $this->view, true)) === false)
      {
        $log->warn($path, 'not found');
        throw ErrorEx::skip();
      }
      array_splice($this->view, $i, 1);
      $this->chat->changed = true;
      # delete item messages
      if (!$node->delete())
      {
        $item->log->warn(__FUNCTION__.": failed to delete");
        throw ErrorEx::skip();
      }
    }
    catch (Throwable $e)
    {
      $log->exception($e);
      return false;
    }
    $log->info($path, 'deleted');
    return true;
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
        throw ErrorEx::skip();
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
          throw ErrorEx::skip();
        }
        # complete
        $this->cfg->queuePush($new);
        $item->log->info('created');
      }
      elseif ($old->compatible($item))
      {
        # edit compatible
        if (($i = $old->edit($item)) === 0) {
          throw ErrorEx::skip();
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
          throw ErrorEx::skip();
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
        throw ErrorEx::skip();
      }
      if (!$msgs->delete())
      {
        $item->log->warn(__FUNCTION__.": failed to delete");
        throw ErrorEx::skip();
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
class BotUser # {{{
{
  public $bot,$id,$name,$username,$lang,$fullname;
  static function construct(object $bot, object $from) # {{{
  {
    # check object already exists
    if (isset($bot->users[$id = strval($from->id)])) {
      return $bot->users[$id];
    }
    # create and initialize instance
    $I = new self();
    $I->id       = strval($from->id);
    $I->name     = trim($from->first_name);
    $I->username = $from->username ?? '';
    $I->lang     = $from->language_code ?? $bot['lang'];
    $I->fullname = $I->username
      ? '@'.$I->username
      : $I->name.'#'.$id;
    # complete
    return $bot->users[$id] = $I;
  }
  # }}}
  function getChat(): ?object # {{{
  {
    return null;
  }
  # }}}
}
# }}}
# }}}
# UI components
# base {{{
abstract class BotItem implements ArrayAccess, JsonSerializable # {{{
{
  # {{{
  public
    $root,$id,$text,$caps,$items,$exEvent,$exRender,
    $log,$cfg,$opts,$data;
  # }}}
  function __construct(# {{{
    public object   $bot,
    public array    $skel,
    public ?object  $parent
  )
  {
    $this->root = $parent ? $parent->root : $this;
    $this->id   = $id = $skel['id'];
    $this->text = new BotItemText($this);
    $this->caps = new BotItemCaptions($this);
    $this->opts = new BotItemOptions($this);
    if ($a = $this->opts['data']) {
      $this->data = new BotItemData($this, $a);
    }
    if (isset($skel[$a = 'items'])) {
      $this->items = &$skel[$a];
    }
    # set extra handlers
    $b = '\\'.__CLASS__;
    if (function_exists($a = $b.'on_'.$id)) {
      $this->exEvent = Closure::bind(Closure::fromCallable($a), $this);
    }
    if (function_exists($a = $b.$id)) {
      $this->exRender = Closure::bind(Closure::fromCallable($a), $this);
    }
  }
  # }}}
  function __debugInfo(): array # {{{
  {
    return [
      $this->skel['path'] => $this::class
    ];
  }
  # }}}
  function jsonSerialize(): array # {{{
  {
    return $this->skel;
  }
  # }}}
  function event(int $evt, object $req): bool # {{{
  {
    static $EVENT = ['init','open','close','change'];
    # attach
    $this->log = $req->log;
    $this->cfg = $req->chat->conf->obtain($this->id);
    $this->opts->node = $req->chat->opts->obtain($this->id);
    if ($this->data && !$this->data->init()) {
      return false;
    }
    # ...
    switch ($evt) {
    }
    # complete
    return ($f = $this->exEvent)
      ? $f($EVENT[$evt], $req->func, $req->args)
      : true;
  }
  # }}}
  # [cfg] access {{{
  function offsetExists(mixed $k): bool {
    return isset($this->cfg[$k]);
  }
  function offsetGet(mixed $k): mixed {
    return $this->cfg[$k];
  }
  function offsetSet(mixed $k, mixed $v): void {
    $this->cfg[$k] = $v;
  }
  function offsetUnset(mixed $k): void {
    $this->cfg[$k] = null;
  }
  # }}}
  function renderMarkup(array &$mkup, ?array $flags = null): string # {{{
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
  abstract function render(object $req): ?array;
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
class BotItemOptions implements ArrayAccess # {{{
{
  public $base,$node;
  function __construct(public object $item) # {{{
  {
    $clas = $item::class;
    $base = [$clas];
    while ($clas = get_parent_class($clas)) {
      $base[] = $clas;
    }
    $this->base = array_slice($base, 0, -1);
  }
  # }}}
  function baseOf(string $k): string # {{{
  {
    foreach ($this->base as $type)
    {
      if (isset($type::OPTION[$k])) {
        return $type;
      }
    }
    return '';
  }
  # }}}
  function grab(string $k): object # {{{
  {
    return new BotItemOptionsGrab($this, $k.'.');
  }
  # }}}
  # access {{{
  function offsetExists(mixed $k): bool {
    return $this->baseOf($k) !== '';
  }
  function offsetGet(mixed $k): mixed
  {
    # get from chat/user
    if (isset($this->node[$k])) {
      return $this->node[$k];
    }
    # get from command config
    if (isset($this->item->skel[$k])) {
      return $this->item->skel[$k];
    }
    # get default
    return ($base = $this->baseOf($k))
      ? $base::OPTION[$k]
      : null;
  }
  function offsetSet(mixed $k, mixed $v): void
  {
    # option must have base
    if ($this->baseOf($k)) {
      $this->node[$k] = $v;
    }
  }
  function offsetUnset(mixed $k): void
  {
    if (isset($this->node[$k])) {
      $this->node[$k] = null;
    }
  }
  # }}}
}
# }}}
class BotItemOptionsGrab implements ArrayAccess # {{{
{
  function __construct(
    public object $opts,
    public string $from
  ) {}
  function offsetExists(mixed $k): bool {
    return isset($this->opts[$this->from.$k]);
  }
  function offsetGet(mixed $k): mixed {
    return $this->opts[$this->from.$k];
  }
  function offsetSet(mixed $k, mixed $v): void {
    $this->opts[$this->from.$k] = $v;
  }
  function offsetUnset(mixed $k): void {
    $this->opts[$this->from.$k] = null;
  }
}
# }}}
class BotItemData implements ArrayAccess # {{{
{
  # {{{
  const
    PREFIX = '@',
    SCOPE  = ['global'=>0,'bot'=>1,'chat'=>2,'user'=>3];
  public
    $scope,$data;
  # }}}
  function __construct(public object $item, string $scope) # {{{
  {
    $this->scope = self::SCOPE[$scope];
  }
  # }}}
  function init(): bool # {{{
  {
    # prepare
    $bot  = $this->item->bot;
    $file = self::PREFIX.$this->item->id;
    # determine file path
    switch ($this->scope) {
    case 0:# any bot
      $file = $bot->cfg->dirDataRoot.$file;
      break;
    case 1:# any chat
      $file = $bot->cfg->dirData.$file;
      break;
    case 2:# any user
      $file = $bot->chat->dir.$file;
      break;
    case 3:# user
      $file = $bot->chat->isUser()
        ? $bot->chat->dir.$file
        : $bot->chat->dir.$file.'-'.$bot->user->id;
      break;
    }
    # load and complete
    return ($this->data = $bot->file->getData($file.'.json'))
      ? true : false;
  }
  # }}}
  # [data] access {{{
  function offsetExists(mixed $k): bool {
    return isset($this->data->node[$k]);
  }
  function offsetGet(mixed $k): mixed {
    return $this->data->node[$k];
  }
  function offsetSet(mixed $k, mixed $v): void {
    $this->data->node[$k] = $v;
  }
  function offsetUnset(mixed $k): void {
    unset($this->data->node[$k]);
  }
  # }}}
  ###
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
}
# }}}
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
# }}}
# img {{{
class BotImgItem extends BotItem # {{{
{
  const OPTION = [# {{{
    'img.variant' => 'title',
    ### static file
    'img.file.name'   => '',# custom name, item id otherwise
    'img.file.select' => true,# append custom id when set
    ### generated title
    # background
    'img.title.file'   => '',# image name
    'img.title.size'   => [640,160],# optimal
    'img.title.color'  => [0,0,32],
    # header (always drawn)
    'img.title.head.font'  => 'Days',
    'img.title.head.size'  => [6,64],# [min,max] font size
    'img.title.head.color' => [255,255,255],# white
    'img.title.head.rect'  => [140,360,0,160],# rect [x,w,y,h]
    # breadcrumb (path)
    'img.title.path'       => true,# draw?
    'img.title.path.font'  => 'Bender-Italic',
    'img.title.path.size'  => 16,
    'img.title.path.color' => [135,206,235],# skyblue
    'img.title.path.pos'   => [140,32],# coordinates
  ];
  # }}}
  function render(object $req): ?array # {{{
  {
    # prepare
    if ($f = $this->exRender)
    {
      if (($msg = $f($req->func, $req->args)) === null) {
        return null;
      }
    }
    else {
      $msg = [];
    }
    # render image
    if (!isset($msg[$a = 'image']))
    {
      $msg[$a] = $this->renderImage($msg);
    }
    # render text content
    if (!isset($msg[$a = 'text'])) {
      $msg[$a] = $this->text['#'];
    }
    # render markup
    if (!isset($msg[$a = 'markup']))
    {
      $msg[$a] = isset($this->skel[$a])
        ? $this->renderMarkup($this->skel[$a])
        : '';
    }
    # complete
    return BotImgMessage::construct($bot, $msg);
  }
  # }}}
  function renderImage(array &$msg): bool # {{{
  {
    # prepare
    $bot = $this->bot;
    $opt = $this->opts;
    # check variant
    switch ($opt['img.variant']) {
    case 'file':
      # determine file name
      $a = $opt['img.file.name'] ?: $this->id;
      if (isset($msg['id']) && $opt['img.file.select']) {
        $a = $a.$msg['id'];
      }
      # get file path
      if (!($a = $bot->file->getImage($a))) {
        return false;
      }
      # get file identifier from cache or
      # create file object for transmission
      if (!($b = $bot->file->getId[$a])) {
        $b = BotApiFile::construct($a, false);
      }
      # complete
      $msg['id'] = $a;
      $msg['file'] = $b;
      break;
    default:
      # prepare
      $opt = $opt->grab('img.title');
      # determine header text
      $a = $msg['title'] ?? $this->text['@'] ?: $this->skel['name'];
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
      if ($file instanceof ErrorEx)
      {
        $this->log->exception($file);
        return null;
      }
      # complete
      return $file;
      break;
    }
    return true;
  }
  # }}}
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
      if ($img instanceof ErrorEx)
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
        throw ErrorEx::fail('imagecreatetruecolor() failed');
      }
      # allocate color
      if (($c = imagecolorallocate($img, $color[0], $color[1], $color[2])) === false) {
        throw ErrorEx::fail('imagecolorallocate() failed');
      }
      # fill the background
      if (!imagefill($img, 0, 0, $c)) {
        throw ErrorEx::fail('imagefill() failed');
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
        throw ErrorEx::fail('imageftbbox() failed');
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
      throw ErrorEx::fail('imagecolorallocate() failed');
    }
    # draw
    if (!imagefttext($img, $maxSize, 0, $x, $y, $c, $font, $text)) {
      throw ErrorEx::fail('imagefttext() failed');
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
      throw ErrorEx::fail('imagecolorallocate() failed');
    }
    # draw
    if (!imagefttext($img, $fontSize, 0, $point[0], $point[1], $color, $font, $text)) {
      throw ErrorEx::fail('imagefttext() failed');
    }
  }
  # }}}
  static function imgFile(object $img): object # {{{
  {
    if (!($file = tempnam(sys_get_temp_dir(), 'img')) ||
        !imagejpeg($img, $file) || !file_exists($file))
    {
      throw ErrorEx::fail("imagejpeg($file) failed");
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
      $res = ErrorEx::from($e);
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
          throw ErrorEx::fail("imagecreatefromjpeg($a) failed");
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
      $res = ErrorEx::from($e);
    }
    # cleanup
    $img && imagedestroy($img);
    # complete
    return $res;
  }
  # }}}
}
# }}}
# }}}
# txt {{{
class BotTxtItem extends BotItem # {{{
{
  const OPTION = [# {{{
  ];
  # }}}
  function render(object $req): ?array # {{{
  {
    return null;
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
 # list {{{
class BotListItem extends BotImgItem
{
  const OPTION = [# {{{
    'cols'    => 1,# columns in markup
    'rows'    => 8,# rows in markup
    'flexy'   => true,# hide empty rows
    'order'   => 'id',# tag name
    'desc'    => false,# descending order
    'timeout' => 0,# data refresh timeout (sec), 0=always
    'item'    => '',
    'func'    => '',
    'markup.head'  => [],
    'markup.foot'  => [['!prev','!next'],['!up']],
    'markup.empty' => [['!up']],
  ];
  # }}}
  function render(object $req): ?array # {{{
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
 # form {{{
class BotFormItem extends BotImgItem
{
  const OPTION = [# {{{
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
    'markup.failure' => [['!back','!retry'],['!up']],
    'markup.input'   => [['!clear'],['!back','!ok','!forward'],['!up']],
    'markup.confirm' => [['!back','!submit'],['!up']],
    'markup.success' => [['!repeat','!change'],['!up']],
  ];
  # }}}
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
  function eventInit(object $req): bool # {{{
  {
    $cfg = &$this->config();
    return $this->init($cfg);
  }
  # }}}
  function render(object $req): ?array # {{{
  {
    # prepare {{{
    # get current state
    $state = [
      ($this['status'] ?? 0),
      ($this['step']   ?? 0),
      ($this['field']  ?? 0),
    ];
    # create vars
    $cfg  = &$this->config();
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
# TODO {{{
/***
* ╔═╗ ⎛     ⎞ ★★☆☆☆
* ║╬║ ⎜  *  ⎟ ✱✱✱ ✶✶✶ ⨳⨳⨳
* ╚═╝ ⎝     ⎠ ⟶ ➤ →
*
* split chat data: separate queue/config/lang from info
* filedata access time and sync/cleanup
* test: handlers source errors
* test: file_id usage
* request filters
* WebHook: operate through fast-cgi (nginx)
* stability: masterbot should erase outdated locks of bots that failed for some reason
* form data separation: user changes its own data until completion
* compatible msg updates: remove unnecessary refresh in private chat
* solve: old message callback action does ZAP? or.. REFRESH?!
*/
# }}}
