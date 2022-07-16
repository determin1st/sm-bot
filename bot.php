<?php declare(strict_types=1);
namespace SM;
# globals {{{
use
  JsonSerializable, ArrayAccess, Iterator,
  SyncEvent, SyncReaderWriter, SyncSharedMemory,
  Generator, Closure, CURLFile,
  Throwable, Error, Exception;
use function
  set_time_limit,ini_set,register_shutdown_function,set_error_handler,
  class_exists,function_exists,method_exists,
  explode,implode,count,reset,next,key,array_keys,
  array_push,array_shift,array_unshift,array_splice,array_slice,
  in_array,array_search,array_reverse,
  strpos,strrpos,strlen,trim,rtrim,strval,uniqid,ucfirst,lcfirst,
  strncmp,substr_count,preg_match,preg_match_all,
  hash,
  file_put_contents,file_get_contents,clearstatcache,file_exists,
  unlink,filesize,filemtime,tempnam,sys_get_temp_dir,
  mkdir,scandir,fwrite,fread,fclose,glob,
  curl_init,curl_setopt_array,curl_exec,
  curl_errno,curl_error,curl_strerror,curl_close,
  curl_multi_init,curl_multi_add_handle,curl_multi_exec,curl_multi_select,
  curl_multi_strerror,curl_multi_info_read,
  curl_multi_remove_handle,curl_multi_close,
  proc_open,is_resource,proc_get_status,proc_terminate,getmypid,
  ob_start,ob_get_length,ob_flush,ob_end_clean,
  pack,unpack,time,hrtime,sleep,usleep,
  min,max;
# }}}
# helpers
# functions {{{
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
    if (!file_exists($file) || !is_array($data = require $file)) {
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
function file_set_json(string $file, array|object &$data): int # {{{
{
  if (($a = json_encode($data, JSON_UNESCAPED_UNICODE)) === false ||
      ($b = file_put_contents($file, $a)) === false)
  {
    return 0;# json file cant be empty
  }
  return $b;
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
    return new self(1, [], $e);
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
  public $count = 0,$changed = false,$jsonFilter,$brr;
  function __construct(# {{{
    public array    $arr,
    public int      $limit  = 0,
    public ?object  $parent = null,
    public int      $depth  = 0
  ) {
    $this->restruct($limit);
  }
  # }}}
  function __debugInfo(): array # {{{
  {
    return $this->arr;
  }
  # }}}
  function restruct(int $limit): self # {{{
  {
    # check potential
    if ($limit > $this->limit) {
      return $this;
    }
    # update
    $this->limit = $limit;
    if (($this->count = count($this->arr)) &&
        ($limit = $limit - 1) >= 0)
    {
      $depth = $this->depth + 1;
      foreach ($this->arr as $k => &$v)
      {
        if (is_array($v)) {
          $v = new self($v, $limit, $this, $depth);
        }
      }
    }
    return $this;
  }
  # }}}
  function change(?self $node = null): void # {{{
  {
    if ($this->brr === null && $this->parent) {
      $this->parent->change($node ?? $this);
    }
    else {
      $this->changed = true;
    }
  }
  # }}}
  # transaction {{{
  function transact(): void {
    $this->brr = $this->arr;
  }
  function rollback(): void
  {
    if ($this->brr !== null)
    {
      if ($this->changed)
      {
        $this->arr = $this->brr;
        $this->brr = null;
        $this->restruct($this->limit);
        $this->changed = false;
        $this->change();
      }
      else {
        $this->brr = null;
      }
    }
  }
  function commit(): void
  {
    if ($this->brr !== null)
    {
      $this->brr = null;
      if ($this->changed)
      {
        $this->changed = false;
        $this->change();
      }
    }
  }
  # }}}
  # [node] access {{{
  function offsetExists(mixed $k): bool {
    return isset($this->arr[$k]);
  }
  function offsetGet(mixed $k): mixed {
    return $this->arr[$k] ?? null;
  }
  function offsetSet(mixed $k, mixed $v): void
  {
    $set = isset($this->arr[$k]);
    if ($v === null)
    {
      if ($set)
      {
        unset($this->arr[$k]);
        $this->count--;
        $this->change();
      }
    }
    elseif (!$set || $v !== $this->arr[$k])
    {
      if (is_array($v) && ($limit = $this->limit))
      {
        $this->arr[$k] = new self(
          $v, $limit - 1, $this, $this->depth + 1
        );
      }
      else {
        $this->arr[$k] = $v;
      }
      $set || $this->count++;
      $this->change();
    }
  }
  function offsetUnset(mixed $k): void {
    $this->offsetSet($k, null);
  }
  # }}}
  function jsonSerialize(): array # {{{
  {
    if ($this->changed) {
      $this->changed = false;
    }
    return ($f = $this->jsonFilter)
      ? $f($this->arr)
      : $this->arr;
  }
  # }}}
  function isEmpty(): bool # {{{
  {
    return $this->count === 0;
  }
  # }}}
  function indexOfKey(string|int $k): int # {{{
  {
    # prepare
    if (($c = $this->count) === 0) {
      return -1;
    }
    $a = &$this->arr;
    $i = 0;
    # search key index
    reset($a);
    while ($k !== strval(key($a))) {
      $i++; next($a);
    }
    # restore pointer
    $j = $i;
    while ($j--) {
      prev($a);
    }
    # complete
    return $i;
  }
  # }}}
  function indexOfValue(mixed $value): int # {{{
  {
    $i = $j = -1;
    foreach ($this->arr as &$v)
    {
      $j++;
      if ($v === $value)
      {
        $i = $j;
        break;
      }
    }
    return $i;
  }
  # }}}
  function delete(mixed $value): self # {{{
  {
    if (($i = $this->indexOfValue($value)) >= 0)
    {
      $this->count--;
      array_splice($this->arr, $i, 1);
      $this->change();
    }
    return $this;
  }
  # }}}
  function prepend(mixed $value): self # {{{
  {
    array_unshift($this->arr, $value);
    $this->count++;
    $this->change();
    return $this;
  }
  # }}}
  function obtain(string $k): ?object # {{{
  {
    if (isset($this->arr[$k])) {
      return $this->arr[$k];
    }
    if (($limit = $this->limit - 1) >= 0) {
      return null;
    }
    $this->count++;
    return $this->arr[$k] = new self(
      [], $limit, $this, $this->depth + 1
    );
  }
  # }}}
  function import(array &$a): void # {{{
  {
    foreach ($this->arr as $k => &$v)
    {
      if (isset($a[$k]))
      {
        if (is_object($v)) {
          $v->import($a[$k]);
        }
        else {
          $v = $a[$k];
        }
      }
    }
  }
  # }}}
  function filt(callable $f): bool # {{{
  {
    $i = 0;
    $j = $this->count;
    foreach ($this->arr as $k => &$v)
    {
      if ($f($v, $i, $k)) {
        $i++;
      }
      else
      {
        array_splice($this->arr, $i, 1);
        $j--;
      }
    }
    if ($j < $this->count)
    {
      $this->count = $j;
      $this->change();
      return true;
    }
    return false;
  }
  # }}}
  function each(callable $f): int # {{{
  {
    $i = 0;
    foreach ($this->arr as $k => &$v)
    {
      if (!$f($v, $i++, $k)) {
        break;
      }
    }
    return $i;
  }
  # }}}
  function set(array $a): void # {{{
  {
    $this->setRef($a);
  }
  # }}}
  function setRef(array &$a): void # {{{
  {
    $this->arr = &$a;
    $this->restruct($this->limit)->change();
  }
  # }}}
  function keys(): array # {{{
  {
    return array_string_keys($this->arr);
  }
  # }}}
}
# }}}
class ArrayUnion implements ArrayAccess # {{{
{
  static function construct(# {{{
    mixed $defaultValue = null
  ):self
  {
    return new self($defaultValue);
  }
  # }}}
  function __construct(# {{{
    public mixed $defaultValue,
    public array $stack = []
  ) {}
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
  # [access] {{{
  function offsetExists(mixed $k): bool {
    return $this->search($k) >= 0;
  }
  function offsetGet(mixed $k): mixed
  {
    return ~($i = $this->search($k))
      ? $this->stack[$i][$k]
      : $this->defaultValue;
  }
  function offsetSet(mixed $k, mixed $v): void
  {}
  function offsetUnset(mixed $k): void
  {}
  # }}}
  function push(array $a): self # {{{
  {
    return $this->pushRef($a);
  }
  # }}}
  function pushRef(array &$a): self # {{{
  {
    array_unshift($this->stack, null);
    $this->stack[0] = &$a;
    return $this;
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
class Promise # {{{
{
  public $action,$status = 1,$result,$next;
  # static {{{
  static function Func(object $f): self {
    return new self(new PromiseActionFunc($f));
  }
  static function One(array $a): self {
    return new self(new PromiseActionOne($a, false));
  }
  static function OneBreak(array $a): self {
    return new self(new PromiseActionOne($a, true));
  }
  static function All(array $a): self {
    return new self(new PromiseActionAll($a, false));
  }
  static function AllBreak(array $a): self {
    return new self(new PromiseActionAll($a, true));
  }
  static function from(object $x): self
  {
    return ($x instanceof self)
      ? $x
      : (($x instanceof PromiseAction)
        ? new self($x)
        : new self(new PromiseActionFunc($x)));
  }
  # }}}
  function __construct(object $action) # {{{
  {
    $this->action = $action;
  }
  # }}}
  function complete(): bool # {{{
  {
    # check complete
    if ($this->status < 1) {
      return true;
    }
    # initialize
    if ($this->status < 2)
    {
      $this->action->result = &$this->result;
      if (!$this->action->init())
      {
        $this->status = -1;
        return true;
      }
      $this->status = 2;
    }
    # operate
    if ($this->action->spin()) {
      return false;
    }
    # finalize
    if ($x = $this->action->finit())
    {
      for ($p = $x = self::from($x); $p->next; $p = $p->next)
      {}
      $p->next = $this->next;
      $this->action = $x->action;
      $this->next   = $x->next;
      $this->status = 1;
      return $this->complete();
    }
    # continue
    if ($x = $this->next)
    {
      $this->action = $x->action;
      $this->next   = $x->next;
      $this->status = 1;
      return $this->complete();
    }
    # complete
    $this->status = 0;
    return true;
  }
  # }}}
  function then(?object $x): self # {{{
  {
    if ($x)
    {
      if ($this->status)
      {
        for ($p = $this; $p->next; $p = $p->next)
        {}
        $p->next = self::from($x);
      }
      else
      {
        $x = self::from($x);
        $this->action = $x->action;
        $this->next   = $x->next;
        $this->status = 1;
        $this->result = null;
      }
    }
    return $this;
  }
  # }}}
  function check(object $func): self # {{{
  {
    return $this->then(new PromiseActionCheck($func));
  }
  # }}}
  function isPending(): bool # {{{
  {
    return $this->status > 0;
  }
  # }}}
  function isFailed(): bool # {{{
  {
    return $this->status < 0;
  }
  # }}}
}
class PromiseQueue extends Promise # {{{
{
  function __construct(?object ...$queue) # {{{
  {
    $this->action = [];
    foreach ($queue as $o) {
      $o && ($this->action[] = Promise::from($o));
    }
    $this->status = count($this->action);
  }
  # }}}
  function complete(): bool # {{{
  {
    if (!$this->status) {
      return true;
    }
    if (!$this->action[0]->complete()) {
      return false;
    }
    array_shift($this->action);
    return (--$this->status === 0);
  }
  # }}}
  function then(?object $o): self # {{{
  {
    if ($x) {
      $this->status = array_push($this->action, Promise::from($o));
    }
    return $this;
  }
  # }}}
}
# }}}
abstract class PromiseAction # {{{
{
  public $result;
  function init(): bool {
    return true;
  }
  function spin(): bool {
    return false;
  }
  function finit(): ?object {
    return null;
  }
}
# }}}
class PromiseActionFunc extends PromiseAction # {{{
{
  function __construct(
    private object $func
  ) {}
  function finit(): ?object {
    return $this->func->call($this);
  }
}
# }}}
class PromiseActionCheck extends PromiseAction # {{{
{
  function __construct(
    private object $func
  ) {}
  function init(): bool {
    return $this->func->call($this);
  }
}
# }}}
class PromiseActionOne extends PromiseAction # {{{
{
  public $pending;
  function __construct(
    public array &$queue,
    public bool  $break
  ) {
    # filter promise queue
    for ($i = 0, $j = count($queue); $i < $j; ++$i)
    {
      if ($queue[$i]) {
        $queue[$i] = Promise::from($queue[$i]);
      }
      else {
        array_splice($queue, $i, 1); $i--; $j--;
      }
    }
    $this->pending = $j;
  }
  function init(): bool
  {
    $this->result = true;
    return true;
  }
  function spin(): bool
  {
    # check complete
    if (!($n = &$this->pending)) {
      return false;
    }
    # complete first
    if (($p = $this->queue[0])->complete())
    {
      # check failed
      if ($p->status < 0)
      {
        # set negative and stop spinning (if breakable)
        $this->result = false;
        if ($this->break) {
          return false;
        }
      }
      # eject and proceed to the next
      array_shift($this->queue);
      $n--;
    }
    # complete with check
    return $n > 0;
  }
}
# }}}
class PromiseActionAll extends PromiseActionOne # {{{
{
  function spin(): bool
  {
    # check complete
    if (!($n = &$this->pending)) {
      return false;
    }
    # complete all
    $i = 0;
    while ($i < $n)
    {
      if (($p = $this->queue[$i])->complete())
      {
        # check failed and optionally, stop spinning
        if ($p->status < 0)
        {
          $this->result = false;
          if ($this->break) {
            return false;
          }
        }
        # eject
        array_splice($this->queue, $i, 1);
        $n--;
      }
      else {
        $i++;
      }
    }
    # complete with check
    return $n > 0;
  }
}
# }}}
# }}}
trait TimeTouchable # {{{
{
  public $time = 0;
  function touch(int $time = 0): object
  {
    $this->time = $time ?: time();
    return $this;
  }
}
# }}}
# }}}
## boundary
# console {{{
class BotConsole # {{{
{
  # {{{
  const
    UUID    = 'b0778b0492bb482dad6cde6ef72308f1',
    TIMEOUT = 1000,
    SIZE    = 32000; # lines=80*400
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
    $this->membuf = new Membuf(self::UUID, self::SIZE);
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
    foreach ($tree as $node)
    {
      # compose indent
      $pad && ($x .= str_repeat(' ', $pad));
      foreach ($indent as $a) {
        $x .= $a ? self::fgColor('│ ', $color, 1) : '  ';
      }
      # compose item line
      $a = (++$i === $j);
      $b = self::fgColor(($a ? '└─' : '├─'), $color, 1);
      $x = $x.$b.$node->name."\n";
      # recurse
      if ($node->children)
      {
        $indent[] = !$a;
        $x .= self::parseCommands(
          $node->children, $pad, $color, $indent, $level + 1
        );
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
    public string   $name = '',
    public ?object  $parent = null
  ) {
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
  function setName(string $name): void # {{{
  {
    $this->name = $name;
  }
  # }}}
  function new(string $name): self # {{{
  {
    return ($name && $name !== $this->name)
      ? new self($this->bot, $name, $this)
      : $this;
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
    # filter empty messages
    for ($i = 0, $j = count($msg); $i < $j; ++$i)
    {
      if ($msg[$i] === '') {
        array_splice($msg, $i--, 1); $j--;
      }
    }
    # output
    $s = self::separator($level, $sep);
    $this->bot->console->write(
      $this->message($level, $s, $msg)."\n"
    );
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
    $this->info(
      $this->bot['source'],
      "\n".self::parseCommands(
        $this->bot->cmd->tree, 0, self::PROMPT[2]
      )
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
  function ok(bool $result, string $msg = ''): bool # {{{
  {
    if ($result) {
      $this->info($msg, 'ok');
    }
    else {
      $this->error($msg, 'fail');
    }
    return $result;
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
    DIR_USER      = 'usr',
    DIR_GROUP     = 'grp',
    DIR_CHAN      = 'chan',
    FILE_MASTER   = 'config.inc',
    FILE_BOT      = 'config.json',
    FILE_SERVICE  = 'service.php',
    EXP_TOKEN     = '/^\d{8,10}:[a-z0-9_-]{35}$/i';
  public
    $dirInc,$dirSrcRoot,$dirDataRoot,
    $dirSrc,$dirData,$dirUsr,$dirGrp,$dirChan,
    $data;
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
    return isset($o['dir'])
      ? rtrim($o['dir'], '\\/').DIRECTORY_SEPARATOR
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
        'token'         => $o['token'],
        'id'            => $id ?: self::getId($o['token']),
        'apiUrl'        => $o['api'] ?? 'https://api.telegram.org/bot',
        'apiPull'       => true,# hook otherwise
        'lang'          => 'en',
        'admins'        => [],
        'name'          => '',
        'canJoinGroups' => false,
        'canReadGroups' => false,
        'isInline'      => false,
      ],
      'BotApiPull' => [# getUpdates (long polling)
        'timeout'  => 60,# polling timeout (telegram's max=50)
        'limit'    => 100,# polling results limit (100=max)
        'maxFails' => 0,# max repeated fails until termination (0=unlimited)
        'pause'    => 10,# pause after repeated fails (seconds)
      ],
      'BotFile'   => [
        'timeout' => 5*60,# sec, unload
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
        'blank' => [# placeholder background
          [640,160],# size: width,height
          [0,0,48],# color: R,G,B
        ],
      ],
      # }}}
    ], 1);
  }
  # }}}
  function init(): bool # {{{
  {
    # prepare
    $bot = $this->bot;
    # set and check data directories
    $this->dirData = $a = $this->dirDataRoot.$bot['id'].DIRECTORY_SEPARATOR;
    $b = [
      $this->dirUsr  = $a.self::DIR_USER.DIRECTORY_SEPARATOR,
      $this->dirGrp  = $a.self::DIR_GROUP.DIRECTORY_SEPARATOR,
      $this->dirChan = $a.self::DIR_CHAN.DIRECTORY_SEPARATOR,
    ];
    foreach ($b as $c)
    {
      if (!dir_check_make($c))
      {
        $bot->log->error($c);
        return false;
      }
    }
    # load bot configuration
    if (!($c = file_get_json($b = $a.self::FILE_BOT)))
    {
      $bot->log->error($b);
      return false;
    }
    $this->data->import($c);
    # now, bot source is determined,
    # set source directory and complete
    $this->dirSrc = $this->dirSrcRoot.$bot['source'].DIRECTORY_SEPARATOR;
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
    $a = [$a.BotCommands::FILE, $a.self::FILE_SERVICE];
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
    if ($this->data->changed)
    {
      $file = $this->dirData.self::FILE_BOT;
      if (!file_set_json($file, $this->data)) {
        return false;
      }
    }
    return true;
  }
  # }}}
  function finit(): void # {{{
  {
    $this->sync();
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
# file {{{
class BotFile extends BotConfigAccess
{
  # {{{
  const
    DIR_FONT = 'font',
    DIR_IMG  = 'img',
    EXP_FONT = '{ttf,otf}',
    EXP_IMG  = '{jpg,jpeg,png}',
    FILE_ID  = 'file_id.json';
  public
    $log,$fid,$img = [],$fnt = [],$map = [];
  # }}}
  function __construct(public object $bot) # {{{
  {
    $this->log = $bot->log->new('file');
  }
  # }}}
  function init(): bool # {{{
  {
    # load file identifiers
    $this->fid = new BotFileData(
      $this->log, $this->bot->cfg->dirData.self::FILE_ID
    );
    if (!$this->fid->load()) {
      return false;
    }
    # load file maps
    return $this->loadMaps();
  }
  # }}}
  function loadMaps(): bool # {{{
  {
    try
    {
      # prepare
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
          $this->fnt[substr($c, $i, $j)] = $c;
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
  function id(string $path, string $id = ''): string # {{{
  {
    if ($id === '') {
      return $this->fid[$path] ?? '';
    }
    $this->fid[$path] = $id;
    return '';
  }
  # }}}
  function image(string $name): string # {{{
  {
    if (isset($this->img[$name])) {
      return $this->img[$name];
    }
    $this->log->error(__FUNCTION__.':'.$name, 'not found');
    return '';
  }
  # }}}
  function font(string $name): string # {{{
  {
    if (isset($this->fnt[$name])) {
      return $this->fnt[$name];
    }
    $this->log->error(__FUNCTION__.':'.$name, 'not found');
    return '';
  }
  # }}}
  function data(string $path, int $depth = 0): ?object # {{{
  {
    # check already exists
    if (isset($this->map[$path])) {
      return $this->map[$path];
    }
    # construct and store new instance
    $o = new BotFileData($this->log, $path, $depth);
    return $o->load()
      ? ($this->map[$path] = $o)
      : null;
  }
  # }}}
  function node(string $path, int $depth = 0): object # {{{
  {
    return ($o = $this->data($path, $depth))
      ? $o->node: new ArrayNode([], $depth);
  }
  # }}}
  function dirCheckMake(string $path, int $perms = 0750): bool # {{{
  {
    if (file_exists($path)) {
      return true;
    }
    try
    {
      if (!mkdir($path, $perms, true)) {
        throw ErrorEx::stop('mkdir', $path);
      }
      $this->log->info('mkdir', $path);
    }
    catch (Throwable $e)
    {
      $this->log->exception($e);
      return false;
    }
    return true;
  }
  # }}}
  function sync(): void # {{{
  {
    # synchronize file identifiers
    $this->fid->sync();
    # synchronize data objects
    $t = time();
    $x = $this['timeout'];
    foreach ($this->map as $o)
    {
      # save (always) and unload (if not changed and timed out)
      if (!$o->sync($t) && $t - $o->time > $x) {
        unset($this->map[$o->key]);
      }
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
  public $time,$node;
  function __construct(# {{{
    public object $log,
    public string $path,
    public int    $limit = 0,
  )
  {
    $this->time = time();
  }
  # }}}
  function load(): bool # {{{
  {
    if (($a = file_get_json($this->path)) === null)
    {
      $this->log->error('get', $this->path);
      return false;
    }
    $this->time = time();
    $this->node = new ArrayNode($a, $this->limit);
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
  function isEmpty(): bool # {{{
  {
    return $this->node->count === 0;
  }
  # }}}
  function set(array $a): void # {{{
  {
    $this->node->setRef($a);
  }
  # }}}
  function sync(int $time = 0): bool # {{{
  {
    if (!$this->node->changed) {
      return false;
    }
    if (!($a = file_set_json($this->path, $this->node)))
    {
      $this->log->error('set', $this->path);
      return false;
    }
    $this->time = $time ?: time();
    return true;
  }
  # }}}
}
# }}}
# api {{{
class BotApi # {{{
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
    $log,$curl,$actions,$reciever;
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
  }
  # }}}
  function init(): bool # {{{
  {
    $this->actions  = new BotApiActions($this);
    $this->reciever = $this->bot['apiPull']
      ? new BotApiPull($this) # long polling
      : new BotApiHook($this);# webhook
    return
      $this->actions->init() &&
      $this->reciever->init();
  }
  # }}}
  function setup(# {{{
    string  $method,
    array   &$query,
    ?object &$file = null,
    string  $token = ''
  ):void
  {
    # handle file attachment
    if ($file) {
      $query[$file->postname] = $file;# put into
    }
    elseif (isset(self::METHOD_FILE[$method]) &&
            isset($query[$a = self::METHOD_FILE[$method]]) &&
            $query[$a] instanceof BotApiFile)
    {
      $file = $query[$a];# get from
    }
    # determine token
    if (strlen($token) === 0) {
      $token = $this->bot['token'];
    }
    # compose the request
    $query = [
      CURLOPT_URL => $this->bot['apiUrl'].$token.'/'.$method,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $query,
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
      $this->setup($method, $req, $file, $token);
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
  function promise(# {{{
    string  $method,
    array   $req,
    ?object $file  = null,
    string  $token = ''
  ):?object
  {
    $this->setup($method, $req, $file, $token);
    return new Promise(new BotApiAction(
      $this->actions, $req, $file
    ));
  }
  # }}}
  function &recieve(): array # {{{
  {
    return $this->reciever->get();
  }
  # }}}
  function finit(): void # {{{
  {
    $this->reciever->finit();
    $this->actions->finit();
  }
  # }}}
  function __destruct() # {{{
  {
    curl_close($this->curl);
  }
  # }}}
}
# }}}
abstract class BotApiExt extends BotConfigAccess # {{{
{
  public $bot,$log;
  final function __construct(public object $api)
  {
    $this->bot = $api->bot;
    $this->log = $api->log->new(static::LOGNAME);
  }
  abstract function init(): bool;
  abstract function finit(): void;
}
# }}}
class BotApiPull extends BotApiExt # {{{
{
  const LOGNAME = 'pull';
  public $murl,$qry,$req,$gen,$pause = 0,$fails = 0;
  function init(): bool # {{{
  {
    try
    {
      # attach multi-curl instance
      if (!($this->murl = curl_multi_init())) {
        throw ErrorEx::fail('curl_multi_init');
      }
      # configure query
      $this->req = [
        'offset'  => 0,
        'limit'   => $this['limit'],
        'timeout' => $this['timeout'],
      ];
      $this->api->setup('getUpdates', $this->req);
      $this->qry = &$this->req[CURLOPT_POSTFIELDS];
    }
    catch (Throwable $e)
    {
      $this->api->log->exception($e);
      if ($this->murl) {
        curl_multi_close($this->murl);
      }
      return false;
    }
    return true;
  }
  # }}}
  function &get(): array # {{{
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
        $this->gen = $this->getGen($this->qry['timeout']);
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
      $res = [];
    }
    return $res;
  }
  # }}}
  function getGen(int $timeout): Generator # {{{
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
  function finit(): void # {{{
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
class BotApiHook extends BotApiExt # {{{
{
  const LOGNAME = 'hook';
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
  function init(): bool # {{{
  {
    return true;
  }
  # }}}
  function finit(): void # {{{
  {
  }
  # }}}
}
# }}}
class BotApiActions extends BotApiExt # {{{
{
  const LOGNAME = 'action';
  public $murl,$gen,$acts = [];
  function init(): bool # {{{
  {
    if (!($this->murl = curl_multi_init()))
    {
      $this->log->error('curl_multi_init');
      return false;
    }
    return true;
  }
  # }}}
  function spin(): bool # {{{
  {
    # check generator
    if (($gen = $this->gen) && $gen->valid()) {
      $gen->send(1);# continue
    }
    else {# start or restart
      $this->gen = $gen = $this->getGen();
    }
    # check non-finished or successfull
    if ($gen->valid() || $gen->getReturn()) {
      return true;
    }
    # failed
    $this->stop();
    return false;
  }
  # }}}
  function getGen(): Generator # {{{
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
        # new handles may be added, so,
        # update counter
        $count = count($this->acts);
      }
      # check transfers
      while ($c = curl_multi_info_read($murl))
      {
        # find and complete the action
        foreach ($this->acts as $a => $action)
        {
          if ($action->curl === $c['handle'] &&
              $action->set($c['result']))
          {
            $running++;# rise back
            array_splice($this->acts, $a, 1);
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
  function stop(): void # {{{
  {
    if ($this->gen?->valid()) {
      $this->gen->send(0);# stop
    }
    foreach ($this->acts as $a) {
      $a->finit();
    }
    $this->gen = null;
    $this->acts = [];
  }
  # }}}
  function finit(): void # {{{
  {
    $this->stop();
    curl_multi_close($this->murl);
  }
  # }}}
}
# }}}
class BotApiAction extends PromiseAction # {{{
{
  public $curl,$result,$status = 0;
  function __construct(# {{{
    public object   $base,
    public array    &$req,
    public ?object  $file
  ) {}
  # }}}
  function init(): bool # {{{
  {
    if ($this->attach())
    {
      $this->base->acts[] = $this;
      return true;
    }
    $this->file?->destruct();
    return false;
  }
  # }}}
  function attach(): bool # {{{
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
      if ($a = curl_multi_add_handle($this->base->murl, $curl)) {
        throw ErrorEx::fail("curl_multi_add_handle\n".curl_multi_strerror($a));
      }
    }
    catch (Throwable $e)
    {
      $this->base->log->exception($e);
      $curl && curl_close($curl);
      return false;
    }
    return true;
  }
  # }}}
  function set(int $x): bool # {{{
  {
    try
    {
      # check failed (non-zero)
      if ($x)
      {
        $msg = "transfer failed\n[$x] ".curl_strerror($x);
        throw ErrorEx::warn($msg);
      }
      # check HTTP response code
      $curl = $this->curl;
      if (($x = curl_getinfo($curl, CURLINFO_RESPONSE_CODE)) === false) {
        throw ErrorEx::stop("curl_getinfo\n".BotApi::cError($curl));
      }
      if ($x !== 200) {
        throw ErrorEx::stop("unsuccessful HTTP status: $x");
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
      if ($this->base->log->exception($e)) {
        $this->status = -1;# critical
      }
    }
    # complete
    $this->detach();
    if ($this->status) {
      return true;
    }
    # retry (recoverable failure)
    return !$this->attach();
  }
  # }}}
  function spin(): bool # {{{
  {
    return ($this->status === 0 &&
            $this->base->spin());
  }
  # }}}
  function detach(): void # {{{
  {
    $base = $this->base;
    if ($e = curl_multi_remove_handle($base->murl, $this->curl)) {
      $base->log->error("curl_multi_remove_handle\n".curl_multi_strerror($e));
    }
    curl_close($this->curl);
    $this->curl = null;
  }
  # }}}
  function finit(): ?object # {{{
  {
    $this->curl && $this->detach();
    if ($this->file)
    {
      $this->file->destruct();
      $this->file = null;
    }
    if (!$this->status) {
      $this->status = -1;
    }
    return null;
  }
  # }}}
}
# }}}
class BotApiFile extends CURLFile # {{{
{
  public $isTemp;
  static function construct(
    string $file, bool $isTemp = false
  ):self
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
# updates {{{
class BotUpdates
{
  # {{{
  public
    $log,$queue = [],$count = 0,
    $loadable = true;
  # }}}
  function __construct(public object $bot) # {{{
  {
    $this->log = $bot->log->new('update');
  }
  # }}}
  function load(object $upd): bool # {{{
  {
    # construct the request
    if (!($q = BotRequest::fromUpdate($this->bot, $upd))) {
      return false;
    }
    # check
    if ($q instanceof ErrorEx)
    {
      if ($q->level) {
        $this->log->errorObject($upd, $q->getMsg('incorrect'));
      }
      else {
        $this->log->warnObject($upd, 'unknown');
      }
      return false;
    }
    # construct and stash the response
    if (isset($this->queue[$id = $q->id()])) {
      $this->queue[$id]->then($q->response());
    }
    else
    {
      $this->queue[$id] = new PromiseQueue($q->response());
      $this->count++;
    }
    return true;
  }
  # }}}
  function apply(): int # {{{
  {
    if ($cnt = $this->count)
    {
      foreach ($this->queue as $id => $q)
      {
        if ($q->complete())
        {
          unset($this->queue[$id]);
          $this->count--;
        }
      }
    }
    return $cnt;
  }
  # }}}
}
# }}}
# text {{{
class BotText
{
  # {{{
  const
    DEF_LANG    = 'en',
    DIR_PARSER  = 'sm-mustache',
    FILE_PARSER = 'mustache.php',
    FILE_TEXTS  = 'texts.inc',
    FILE_CAPS   = 'captions.inc',
    FILE_EMOJIS = 'emojis.inc';
  public
    $log,$hlp,$tp,$texts,$caps;
  # }}}
  function __construct(public object $bot) # {{{
  {
    $this->log = $bot->log->new('text');
    $this->hlp = ArrayUnion::construct('')->push([
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
    $this->restruct($this->texts);
    $this->refine($this->caps);
    return true;
  }
  # }}}
  function restruct(array &$a): self # {{{
  {
    # prepare
    $this->refine($a);
    # set primary language
    if (!isset($a[$b = self::DEF_LANG]))
    {
      # store the whole set as primary,
      # assuming other languages not specified
      $a = [$b => $a];
    }
    # set secondary languages
    foreach (array_keys($this->texts) as $lang)
    {
      if ($lang !== $b)
      {
        if (!isset($a[$lang])) {# copy primary
          $a[$lang] = $a[$b];
        }
        else {# fill gaps
          array_import_new($a[$lang], $a[$b]);
        }
      }
    }
    return $this;
  }
  # }}}
  function refine(array &$a): self # {{{
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
    return $this;
  }
  # }}}
  function restructKey(array &$a, string $k): self # {{{
  {
    isset($a[$k]) || ($a[$k] = []);
    return $this->restruct($a[$k]);
  }
  # }}}
  function refineKey(array &$a, string $k): self # {{{
  {
    isset($a[$k]) || ($a[$k] = []);
    return $this->refine($a[$k]);
  }
  # }}}
  function error(string $msg, int $level): void # {{{
  {
    $level && $this->log->error($msg);
  }
  # }}}
  function lang(string $lang = ''): string # {{{
  {
    return ($lang && isset($this->texts[$lang]))
      ? $lang : $this->bot['lang'];
  }
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
    FILE_SCHEMA    = 'commands.inc',
    FILE_HANDLERS  = 'commands.php',
    TYPE_DEFAULT   = 'Img',
    TYPE_NAMESPACE = '\\'.__NAMESPACE__.'\\',
    EXP_PATH       = '|^(\/[a-z0-9_]+){1,8}$|i',
    EXP_COMMAND    = '|^((\/[a-z0-9_]+){1,8})(@([a-z_]+bot)){0,1}$|i',
    EXP_Q_COMMAND  = '|^((\/[a-z0-9_]+){1,8})(!([a-z]+)){0,1}([ \n](.*)){0,1}$|is',
    EXP_Q_SELF     = '|^((\/[a-z0-9_]+){0,8})(!([a-z]+)){1}([ \n](.*)){0,1}$|is',
    EXP_Q_CHILD    = '|^([a-z0-9_]+(\/[a-z0-9_]+){0,7})(!([a-z]+)){0,1}([ \n](.*)){0,1}$|is',
    SEPARATOR_NAME = '-',
    SEPARATOR_PATH = '/';
  public
    $log,$tree,$map;
  # }}}
  function __construct(public object $bot) # {{{
  {
    $this->log = $bot->log->new('commands');
  }
  # }}}
  function init(): bool # {{{
  {
    try
    {
      # prepare
      $bot = $this->bot;
      $dir = $bot->cfg->dirSrc;
      # load service scheme and handlers
      $schema = require $dir.self::FILE_SCHEMA;
      $hands  = require $dir.self::FILE_HANDLERS;
      # create items
      foreach ($schema as $path => &$item)
      {
        # check path
        if (!preg_match(self::EXP_PATH, $path)) {
          throw ErrorEx::stop($path, 'incorrect path');
        }
        # determine base properties of the item
        $depth = substr_count($path, self::SEPARATOR_PATH, 1);
        $name  = substr($path, 1 + strrpos($path, self::SEPARATOR_PATH));
        $id    = hash('xxh3', $path);# 16 bytes
        $type  = ucfirst($item['type'] ?? self::TYPE_DEFAULT);
        $class = self::TYPE_NAMESPACE.'Bot'.$type.'Item';
        # check class
        if (!class_exists($class, false)) {
          throw ErrorEx::stop($path, 'unknown type: '.$type);
        }
        # initialize common fields
        $bot
          ->text
          ->restructKey($item, 'text')
          ->refineKey($item, 'caps');
        if (!isset($item[$a = 'markup'])) {
          $item[$a] = [];
        }
        # construct
        $item = new $class(
          $bot, $path, $depth, $name, $id, $type, $item,
          $hands[$path] ?? null
        );
      }
      # build trees and map
      $tree = [];
      $map  = [];
      foreach ($schema as $item)
      {
        if ($item->depth === 0) {
          $tree[$item->name] = $item->build($schema, null);
        }
        $map[$item->id] = $map[$item->path] = $item;
      }
      # set refs
      $this->tree = &$tree;
      $this->map  = &$map;
    }
    catch (Throwable $e)
    {
      $this->log->exception($e);
      return false;
    }
    return true;
  }
  # }}}
  # [map] access {{{
  function offsetExists(mixed $k): bool {
    return isset($this->map[$k]);
  }
  function offsetGet(mixed $k): mixed {
    return $this->map[$k] ?? null;
  }
  function offsetSet(mixed $k, mixed $item): void
  {}
  function offsetUnset(mixed $k): void
  {}
  # }}}
  function filename(object $item): string # {{{
  {
    return str_replace(
      self::SEPARATOR_PATH,
      self::SEPARATOR_NAME,
      substr($item->path, 1)
    );
  }
  # }}}
  function pathHash(object $item, string &$text): string # {{{
  {
    return
      $item->path.self::SEPARATOR_NAME.
      hash('xxh3', $text);
  }
  # }}}
  function query(# {{{
    string  &$func,
    string  &$args = '',
    ?object $item  = null
  ):string
  {
    return
      ($item ? '/'.$item->id : '').'!'.$func.
      ($args === '' ? ' '.$args : '');
  }
  # }}}
  function parseCommand(string &$text): ?array # {{{
  {
    $a = [];
    if (preg_match(self::EXP_COMMAND, $text, $a))
    {
      # simple command
      return [$a[1],'','',($a[4] ?? '')];
    }
    if (preg_match(self::EXP_Q_COMMAND, $text, $a))
    {
      # command query
      return [$a[1],($a[4] ?? ''),($a[6] ?? ''),''];
    }
    # TODO: deep linking (tg://<BOTNAME>?start=<PATH>)
    return null;
  }
  # }}}
  function parseCallback(# {{{
    string &$text, ?object $item = null
  ):?array
  {
    $a = [];
    if ($item && preg_match(self::EXP_Q_CHILD, $text, $a))
    {
      # relative/child query
      return [
        ($item->path.self::SEPARATOR_PATH.$a[1]),
        ($a[4] ?? ''),($a[6] ?? ''),''
      ];
    }
    if (preg_match(self::EXP_Q_SELF, $text, $a) ||
        preg_match(self::EXP_Q_COMMAND, $text, $a))
    {
      # command query
      return [$a[1],($a[4] ?? ''),($a[6] ?? ''),''];
    }
    return null;
  }
  # }}}
  function finit(): void # {{{
  {}
  # }}}
}
# }}}
# process {{{
class BotProcess extends BotConfigAccess # {{{
{
  # {{{
  const
    PROC_UUID       = '22c4408d490143b5b29f0640755327db',
    PROC_TIMEOUT    = 4000,# ms, response timeout
    PROC_WAIT       = 500,# ms, intervals
    TIMEOUT         = 15000,# ms
    BUF_UUID        = 'c25de777e80d49f69b6b7b57091d70d5',
    BUF_SIZE        = 200,
    REGEX_PIDFILE   = '/^bot([-0-9]+)\.pid$/',
    EXIT_CLEAN      = 0,
    EXIT_DIRTY      = 1,
    EXIT_UNEXPECTED = 2,
    EXIT_RESTART    = 100,
    EXIT_SIGINT     = 101,
    EXIT_SIGTERM    = 102;
  public
    $id,$log,$pidfile,$active,$syncbuf,
    $map = [],$idle = 0,$exitcode = self::EXIT_DIRTY;
  # }}}
  static function construct(object $bot): object # {{{
  {
    # create specific instance
    $I = $bot->id
      ? ($bot->task
        ? new BotTaskProcess($bot)
        : new self($bot))
      : new BotConsoleProcess($bot);
    # set common props
    $I->id  = strval(getmypid());
    $I->log = $bot->log->new('proc');
    return $I;
  }
  # }}}
  function __construct(public object $bot) # {{{
  {
    $this->pidfile = $bot->cfg->dirDataRoot.'bot'.$bot->id.'.pid';
    $this->active  = new SyncEvent($bot->id.self::PROC_UUID, 1);
    $this->syncbuf = new Syncbuf(
      $bot->id.self::BUF_UUID, self::BUF_SIZE, self::PROC_TIMEOUT
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
      # activate
      if (!$this->active->fire()) {
        throw ErrorEx::fail('SyncEvent::fire');
      }
      if (file_put_contents($this->pidfile, $this->id) === false) {
        throw ErrorEx::fail($this->pidfile);
      }
      # start children
      $this->startChildren();
      $this->exitcode = self::EXIT_UNEXPECTED;
    }
    catch (Throwable $e)
    {
      $this->log->exception($e);
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
    if (!$this->active->wait(0) || !file_exists($this->pidfile)) {
      throw ErrorEx::stop('forced deactivation');
    }
    # flush the console
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
      # send bot details <name:pid>
      $this->syncbuf->write(
        $this->bot['name'].':'.$this->id
      );
      break;
    }
    return true;
  }
  # }}}
  function busy(): void # {{{
  {
    $this->idle = 0;
  }
  # }}}
  function wait(): void # {{{
  {
    if (++$this->idle > 100)
    {
      $this->bot->file->sync();
      $this->idle = 0;
    }
    else {
      usleep(200000);
    }
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
        $bot->operate()
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
  function startChild(string $id): bool # {{{
  {
    if (isset($this->map[$id])) {
      return true;
    }
    if (!($a = BotProcessChild::construct($this, $id))) {
      return false;
    }
    $this->map[$id] = $a;
    return true;
  }
  # }}}
  function get(string $id): ?object # {{{
  {
    return null;
  }
  # }}}
  function getChildren(): array # {{{
  {
    $ids = [];
    $dir = $this->bot->cfg->dirDataRoot;
    if (($a = scandir($dir, SCANDIR_SORT_NONE)) === false) {
      throw ErrorEx::fail('scandir', $dir);
    }
    foreach ($a as $b)
    {
      $c = [];
      if (preg_match(self::REGEX_PIDFILE, $b, $c)) {
        $ids[] = $c[1];
      }
    }
    return $ids;
  }
  # }}}
  function stopChild(string $id): bool # {{{
  {
    return false;
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
    # cleanup
    $this->bot->console->flush();
    $this->active->reset();
    file_unlink($this->pidfile);
  }
  # }}}
}
# }}}
class BotConsoleProcess extends BotProcess # {{{
{
  function __construct(public object $bot) # {{{
  {
    $this->pidfile = $bot->cfg->dirDataRoot.'console.pid';
    $this->active  = $bot->console->active;
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
    return $this->exitcode === self::EXIT_UNEXPECTED;
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
    $b = $this->getChildren();
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
      if (!$this->startChild($c)) {
        throw ErrorEx::stop($c, 'failed to attach');
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
class BotProcessChild # {{{
{
  # {{{
  const
    FILE_START = 'start.php',
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
    $parent,$log,$id,$pidfile,$active,$syncbuf;
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
      unset($a,$b);
    }
    return $text;
  }
  # }}}
  static function construct(object $parent, string $args): ?self # {{{
  {
    try
    {
      # create new instance
      $I = new self();
      $I->parent  = $parent;
      $I->log     = $parent->log->new($args);
      $I->active  = new SyncEvent($args.$parent::PROC_UUID, 1);
      $I->syncbuf = new Syncbuf(
        $args.$parent::BUF_UUID,
        $parent::BUF_SIZE,
        $parent::PROC_TIMEOUT
      );
      # reset command interface
      $I->syncbuf->reset();
      # startup
      if (!$I->start($args)) {
        throw ErrorEx::skip();
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
  function start(string $args): bool # {{{
  {
    # check already running
    if ($this->active->wait(0))
    {
      $this->log->info('attached');
      return $this->init();
    }
    # create process
    $time = hrtime(true);
    $dir  = __DIR__.DIRECTORY_SEPARATOR;
    $file = $dir.self::FILE_START;
    $pipe = null;
    $cmd  = '"'.PHP_BINARY.'" -f "'.$file.'" '.$args;
    $proc = proc_open(
      $cmd, self::PROC_DESC, $pipe,
      $dir, null, self::PROC_OPTS
    );
    # check
    if (!is_resource($proc))
    {
      $this->log->error("proc_open($cmd)\n");
      return false;
    }
    # wait activated
    $t0 = $this->parent::PROC_TIMEOUT;
    $t1 = $this->parent::PROC_WAIT;
    while (!$this->active->wait($t1) && $t0 > 0)
    {
      if (!($status = proc_get_status($proc)))
      {
        $this->log->error('proc_get_status()');
        return false;
      }
      if (!$status['running'])
      {
        $a = ($a = 'exitcode').': '.$status[$a];
        if ($b = self::closePipes($pipe, true)) {
          $a = $a."\noutput:\n".str_replace("\n", "\n ", $b);
        }
        $this->log->error("unexpected termination\n$a");
        return false;
      }
      $t0 -= $t1;
    }
    # check
    if ($t0 <= 0)
    {
      proc_terminate($proc);
      $this->log->error('activation timed out');
      return false;
    }
    # cleanup
    self::closePipes($pipe);
    unset($proc, $pipe);
    # from now on, process must not output anything to STDOUT/ERR,
    # otherwise it may broke (because of writing to closed resource)
    $time = intval((hrtime(true) - $time)/1e+6);# nano to milli
    $this->log->info('started ('.$time.'ms)');
    return $this->init();
  }
  # }}}
  function init(): bool # {{{
  {
    try
    {
      # request process details
      if (!($a = $this->syncbuf->writeRead('info'))) {
        throw ErrorEx::fail('info', 'empty response');
      }
      # parse and store
      $b = explode(':', $a, 2);
      $this->log->name = $b[0];
      $this->id = $b[1];
    }
    catch (Throwable $e)
    {
      $this->log->exception($e);
      $this->parent->bot->console->flush();
      $this->stop();
      return false;
    }
    return true;
  }
  # }}}
  function command(string $cmd): bool # {{{
  {
    try
    {
      $this->log->info($cmd);
      $this->syncbuf->write($cmd);
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
    if (!$this->active->wait(0)) {
      return true;
    }
    if (!$this->command('stop'))
    {
      $this->active->reset();
      return false;
    }
    $a = $this->parent::PROC_TIMEOUT;
    $b = $this->parent::PROC_WAIT;
    $c = $b * 1000;
    while ($this->active->wait(0) && $a > 0) {
      usleep($c); $a -= $b;
    }
    if ($a <= 0)
    {
      $this->log->warn('timed out');
      $this->active->reset();
      return false;
    }
    return true;
  }
  # }}}
}
# }}}
# }}}
## mediators
# bot {{{
class Bot extends BotConfigAccess
{
  # {{{
  const
    EXP_USERNAME = '/^[a-z]\w{4,32}$/i',
    EXP_BOTNAME  = '/^[a-z]\w{1,29}bot$/i',
    INIT = [
      'console','cfg','log','file','api','text','cmd','proc'
    ];
  public
    $bot,$id,$task,
    $console,$log,$cfg,$api,$update,$text,$cmd,$file,$proc,$inited = [],
    $users = [],$chats = [];
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
    $this->file    = new BotFile($this);
    $this->api     = new BotApi($this);
    $this->updates = new BotUpdates($this);
    $this->text    = new BotText($this);
    $this->cmd     = new BotCommands($this);
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
  function operate(): int # {{{
  {
    if ($this->updates->loadable)
    {
      foreach ($this->api->recieve() as $update) {
        $this->updates->load($update);
      }
    }
    return $this->updates->apply();
  }
  # }}}
  function getUser(object $user): object # {{{
  {
    # check cache
    if (isset($this->users[$id = strval($user->id)])) {
      $user = $this->users[$id];# take from cache
    }
    else
    {
      # create and store new instance
      $this->users[$id] = $user = new BotUser(
        $this, $id, $user
      );
    }
    # update access timestamp and complete
    return $user->touch();
  }
  # }}}
  function getChat(object $user, ?object $chat): ?object # {{{
  {
    # determine identifier
    $id = $chat
      ? strval($chat->id)
      : '';
    # check cache
    if (isset($this->chats[$id])) {
      return $this->chats[$id]->touch();
    }
    # create specific instance
    $I = match ($chat->type ?? 'none') {
      'private' => new BotUserChat($this, $id),
      'group','supergroup' => new BotGroupChat($this, $id),
      'channel' => new BotChanChat($this, $id),
      'none' => new BotAnonChat($this, $id),
    };
    # initialize
    if (!$I->set($user, $chat)->init()) {
      return null;
    }
    # complete
    return $this->chats[$id] = $I->touch();
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
# request {{{
abstract class BotRequest extends BotConfigAccess # {{{
{
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
    object $bot, object $data, object $user, ?object $chat
  ):?object
  {
    # get user and chat
    if (!($user = $bot->getUser($user)) ||
        !($chat = $bot->getChat($user, $chat)))
    {
      return null;
    }
    # get logger
    $log = $chat->isGroup()
      ? $chat->log->new($user->logname)
      : $chat->log;
    # create new instance
    return new static($data, $user, $chat, $log);
  }
  # }}}
  final function __construct(# {{{
    public object $data,
    public object $user,
    public object $chat,
    public object $log
  ) {}
  # }}}
  function id(): string {
    return 'c'.$this->chat->id;
  }
  abstract function response(): ?object;
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
  function response(): ?object # {{{
  {
    return null;
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
  function response(): ?object # {{{
  {
    return null;
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
  function response(): ?object # {{{
  {
    return null;
  }
  # }}}
}
# }}}
class BotRequestCommand extends BotRequest # {{{
{
  function response(): ?object # {{{
  {
    # prepare
    $msg  = $this->data;
    $chat = $this->chat;
    $bot  = $chat->bot;
    # parse
    if (!($a = $bot->cmd->parseCommand($msg->text))) {
      return $this->warning('incorrect');
    }
    # check bot name
    # name addressing is skipped for private chat,
    # otherwise it must match with the bot
    if ($a[3] && !$chat->isUser() && $bot['name'] !== $a[3]) {
      return null;
    }
    # get item
    if (!($item = $bot->cmd[$a[0]])) {
      return $this->warning('unknown');
    }
    # complete
    $this->log->infoInput('command', $msg->text);
    return Promise::One([
      $chat->command($this, $item, $a[1], $a[2]),
      $chat->deleteMessage($msg->message_id)
    ]);
  }
  # }}}
  function warning(string $msg): ?object # {{{
  {
    if ($this->chat->isUser())
    {
      $this->log->warnInput('command', $msg);
      return $this->chat->deleteMessage(
        $this->data->message_id
      );
    }
    return null;
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
  function response(): ?object # {{{
  {
    return null;
  }
  # }}}
}
# }}}
# }}}
# user {{{
class BotUser
{
  use TimeTouchable;
  public $name,$username,$logname,$lang;
  function __construct(# {{{
    public object $bot,
    public string $id,
    public object $info
  ) {
    # set names
    $this->name     = trim($info->first_name);
    $this->username = $info->username ?? '';
    $this->logname  = $this->username
      ? '@'.$this->username
      : $this->name.'#'.$id;
    # set language
    $this->lang = $bot->text->lang(
      $info->language_code ?? ''
    );
  }
  # }}}
}
# }}}
# chat {{{
abstract class BotChat # {{{
{
  use TimeTouchable;
  # {{{
  const
    FILE_INFO = 'info.json',
    FILE_VIEW = 'view.json';
  public
    $name,$username,$logname,$log,
    $info,$lang,$view,$conf,$opts;
  # }}}
  function __construct(# {{{
    public object $bot,
    public string $id
  ) {}
  # }}}
  function init(): bool # {{{
  {
    # set logger
    $this->log = ($bot = $this->bot)->log->new(
      static::LOGNAME
    )->new($this->logname);
    # check working directory
    if (!($dir = $this->dir())) {
      return true;
    }
    if (!$bot->file->dirCheckMake($dir)) {
      return false;
    }
    # complete
    return
      $this->setInfo($dir) &&
      $this->setView($dir);
  }
  # }}}
  function setInfo(string $path): bool # {{{
  {
    # prepare
    $bot  = $this->bot;
    $path = $path.self::FILE_INFO;
    # load [0:info,1:lang]
    if (!($data = $bot->file->data($path))) {
      return false;
    }
    if ($data->isEmpty())
    {
      # initialize
      $a = $bot->api->send('getChat', [
        'chat_id' => $this->id
      ]);
      if (!$a)
      {
        $this->log->error('failed to get details');
        return false;
      }
      $this->log->infoObject($a, 'new');
      $this->info = $data[0] = (array)$a;
      $this->lang = $data[1] = '';
      $data->sync();
    }
    else
    {
      # restore
      $this->info = $data[0];
      $this->lang = $data[1];
    }
    return true;
  }
  # }}}
  function setView(string $path): bool # {{{
  {
    # prepare
    $bot  = $this->bot;
    $path = $path.self::FILE_VIEW;
    # load [0:view,1:conf,2:opts]
    if (!($data = $bot->file->data($path, 1))) {
      return false;
    }
    # initialize
    if ($data->isEmpty()) {
      $data->set([[], [], []]);
    }
    # set
    $this->view = $data[0];
    $this->conf = $a = $data[1]->restruct(1);
    $this->opts = $b = $data[2]->restruct(1);
    # create nodes
    $data[0]->filt(function (array &$v) use ($bot) {
      return ($v = BotChatNode::reconstruct($bot, $v)) !== null;
    });
    # set filters
    $a->jsonFilter = $b->jsonFilter = (function(array &$in) use ($bot) {
      # skip empty containers and unknown items upon saving
      $out = [];
      foreach ($in as $id => $cfg)
      {
        if ($cfg->count && $bot->cmd[$id]) {
          $out[$id] = $cfg;
        }
      }
      return $out;
    });
    return true;
  }
  # }}}
  function nodeOfItem(object $item): ?object # {{{
  {
    foreach ($this->view->arr as $node)
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
    foreach ($this->view->arr as $node)
    {
      foreach ($node->msgs as $msg)
      {
        if ($msg->id === $id) {
          return $node;
        }
      }
    }
    return null;
  }
  # }}}
  function callback(# {{{
    object $req, object $item, string &$func, string &$args
  ):object
  {
    return new Promise(new BotChatCallback(
      $req, $item, new BotItemQuery($func, $args)
    ));
  }
  # }}}
  function command(# {{{
    object $req, object $item, string &$func, string &$args
  ):object
  {
    return new Promise(new BotChatCommand(
      $req, $item, new BotItemQuery($func, $args)
    ));
  }
  # }}}
  function deleteMessage(int $id): object # {{{
  {
    return $this->bot->api->promise('deleteMessage', [
      'chat_id'    => $this->id,
      'message_id' => $id,
    ]);
  }
  # }}}
  # custom {{{
  function isUser():  bool { return false; }
  function isGroup(): bool { return false; }
  function isChan():  bool { return false; }
  abstract function dir(): string;
  abstract function set(
    object $user, ?object $chat
  ):object;
  # }}}
}
class BotUserChat extends BotChat # {{{
{
  const LOGNAME = 'user';
  function isUser(): bool { return true; }
  function dir(): string
  {
    return $this->bot->cfg->dirUsr.
      $this->id.DIRECTORY_SEPARATOR;
  }
  function set(object $user, ?object $chat): object
  {
    $this->name     = $user->name;
    $this->username = $user->username;
    $this->logname  = $user->logname;
    return $this;
  }
}
# }}}
class BotGroupChat extends BotChat # {{{
{
  const LOGNAME = 'group';
  function isGroup(): bool { return true; }
  function dir(): string
  {
    return $this->bot->cfg->dirGrp.
      $this->id.DIRECTORY_SEPARATOR;
  }
  function set(object $user, ?object $chat): object
  {
    $this->name     = $chat->title ?? '';
    $this->username = $username = $chat->username ?? '';
    $this->logname  = $username
      ? '@'.$username
      : $this->name.'#'.$id;
    return $this;
  }
}
# }}}
class BotChanChat extends BotGroupChat # {{{
{
  const LOGNAME = 'chan';
  function isChan(): bool { return true; }
  function dir(): string
  {
    return $this->bot->cfg->dirChan.
      $this->id.DIRECTORY_SEPARATOR;
  }
}
# }}}
class BotAnonChat extends BotChat # {{{
{
  const LOGNAME = 'anon';
  function dir(): string {
    return '';
  }
  function set(object $user, ?object $chat): object
  {
    $this->name     = '';
    $this->username = '';
    $this->logname  = 'inline';
    return $this;
  }
}
# }}}
# }}}
class BotChatCallback extends PromiseAction # {{{
{
  const COMMON_FUNC = ['up'=>1,'close'=>1];
  function __construct(# {{{
    public object $req,
    public object $item,
    public object $query
  ) {}
  # }}}
  function finit(): ?object # {{{
  {
    # prepare
    $item = $this->item;
    $node = $this->req->chat->nodeOfItem($item);
    # handle common navigation
    if (isset(self::COMMON_FUNC[$a = $this->query->func]))
    {
      if (!$node || $node->item !== $item)
      {
        $this->req->log->warn($item->path,
          "$a\ncommand item is not in the chat"
        );
        return null;
      }
      switch ($a) {
      case 'up':
        # climbing up the tree, select parent
        $item = $item->parent;
        break;
      case 'close':
        # item should be deleted
        $item = null;
        break;
      }
    }
    # complete
    return $item
      ? ($node
        ? (($node->item === $item)
          ? $this->change($node)
          : $this->refresh($node, $item))
        : $this->create($item))
      : $this->delete($node);
  }
  # }}}
  function delete(object $node, ?object $ctx = null): ?object # {{{
  {
    # prepare
    $req  = $this->req;
    $chat = $req->chat;
    # operate
    if (!$ctx && !($ctx = $node->item->ctx('close', $req))) {
      return null;# cancel operation
    }
    # complete
    return $node->delete(
      $chat
    )->then(function() use ($chat,$node,$ctx) {
      $ctx->log->ok($this->result, 'delete') &&
      $chat->view->delete($node);
    });
  }
  # }}}
  function create(object $item): ?object # {{{
  {
    # prepare
    $req  = $this->req;
    $user = $req->user;
    $chat = $req->chat;
    $qry  = $this->query;
    # operate
    if (!($ctx  = $item->ctx('open', $req)) ||
        !($node = BotChatNode::construct($item, $ctx, $qry)))
    {
      return null;# cancel operation
    }
    # complete
    return $node->send(
      $chat, $user
    )->then(function() use ($chat,$node,$ctx) {
      $ctx->log->ok($this->result, 'create') &&
      $chat->view->prepend($node);
    });
  }
  # }}}
  function refresh(object $node0, object $item1): ?object # {{{
  {
    # prepare
    $req = $this->req;
    $qry = $this->query;
    # operate
    if (!($ctx0  = $node0->item->ctx('close', $req)) ||
        !($ctx1  = $item1->ctx('open', $req)) ||
        !($node1 = BotChatNode::construct($item1, $ctx1, $qry)))
    {
      return null;
    }
    # complete
    return $node1->isEmpty()
      ? $this->delete($node0, $ctx0)
      : $this->update($node0, $node1, $ctx0);
  }
  # }}}
  function change(object $node0): ?object # {{{
  {
    # prepare
    $item = $node0->item;
    $req  = $this->req;
    $qry  = $this->query;
    # operate
    if (!($ctx   = $item->ctx('change', $req)) ||
        !($node1 = BotChatNode::construct($item, $ctx, $qry)))
    {
      return null;
    }
    # complete
    return $node1->isEmpty()
      ? $this->delete($node0, $ctx)
      : $this->update($node0, $node1, $ctx);
  }
  # }}}
  function update(# {{{
    object $node0, object $node1, object $ctx
  ):object
  {
    if ($node0->isCompatible($node1))
    {
      return $node0->edit(
        $chat, $node1
      )->then(function() use ($ctx) {
        $ctx->log->ok($this->result, 'update');
      });
    }
    return $this->replace($node0, $node1, $ctx);
  }
  # }}}
  function replace(# {{{
    object $node0, object $node1, object $ctx
  ):object
  {
    $chat = $this->req->chat;
    $view = $chat->view;
    return Promise::OneBreak([
      $node0->delete($chat),
      $node1->send($chat, $this->req->user)
    ])->then(function() use ($view,$node0,$node1,$ctx) {
      $ctx->log->ok($this->result, 'replace') &&
      $view->delete($node0)->prepend($node1);
    });
  }
  # }}}
}
# }}}
class BotChatCommand extends BotChatCallback # {{{
{
  function update(
    object $node0, object $node1, object $ctx
  ):object
  {
    return $this->replace($node0, $node1, $ctx);
  }
}
# }}}
class BotChatNode implements JsonSerializable # {{{
{
  const MESSAGE_LIFETIME = 48*60*60;
  static function reconstruct(# {{{
    object $bot, array $json
  ):?self
  {
    # get item
    if (!($item = $bot->cmd[$json[0]])) {
      return null;
    }
    # create message objects
    foreach ($json[1] as &$msg)
    {
      if (!($msg = BotMessage::reconstruct($bot, $msg))) {
        return null;
      }
    }
    # complete
    return new self(
      $item, $json[1], $json[2], $json[3]
    );
  }
  # }}}
  static function construct(# {{{
    object $item, object $ctx, object $query
  ):?self
  {
    try {
      $msgs = $item->render($ctx, $query);
    }
    catch (Throwable $e)
    {
      $ctx->log->exception($e);
      return null;
    }
    return new self($item, $msgs, '', 0);
  }
  # }}}
  function __construct(# {{{
    public object  $item,
    public array   &$msgs,
    public string  $owner,
    public int     $time
  ) {}
  # }}}
  function jsonSerialize(): array # {{{
  {
    return [
      $this->item->id, $this->msgs,
      $this->owner, $this->time
    ];
  }
  # }}}
  function delete(object $chat): object # {{{
  {
    # prepare
    $a = [];
    # telegram allows to delete only "fresh" messages,
    # check creation timestamp
    if (($i = time() - $this->time) >= 0 &&
        ($i < self::MESSAGE_LIFETIME))
    {
      # construct delete actions
      foreach ($this->msgs as $message) {
        $a[] = $message->delete($chat);
      }
    }
    else
    {
      # construct zaps, zap is a special procedure,
      # which removes message content (makes it neutral)
      foreach ($this->msgs as $message) {
        $a[] = $message->zap($chat);
      }
    }
    # delete altogether
    return Promise::AllBreak($a);
  }
  # }}}
  function send(object $chat, object $user): object # {{{
  {
    # create message promises
    $a = [];
    foreach ($this->msgs as $message) {
      $a[] = $message->send($chat);
    }
    # compose result
    $me = $this;
    return Promise::Func(function() use ($me,$user) {
      # set node owner and creation timestamp
      $me->owner = $user->id;
      $me->time  = time();
    })->then(
      # send one by one
      Promise::OneBreak($a)
    );
  }
  # }}}
  function isEmpty(): bool # {{{
  {
    return count($this->msgs) === 0;
  }
  # }}}
  function isCompatible(object $node): bool # {{{
  {
    # check old messages are fresh enough
    $n0 = time() - $this->time;
    $n1 = self::MESSAGE_LIFETIME * 0.8;# 80%
    if ($n0 > $n1) {
      return false;
    }
    # prepare
    $n0 = count($m0 = &$this->msgs);
    $n1 = count($m1 = &$node->msgs);
    # check types
    # block message types must match at intersection
    for ($i = 0, $j = min($n0, $n1); $i < $j; ++$i)
    {
      if ($m1[$i]::class !== $m0[$i]::class) {
        return false;
      }
    }
    # check counts
    # to fit into old message block (node),
    # new message block should be smaller (or equal),
    # one exception from this - is the active node which
    # has very last message in the chat (TODO)
    return $n1 <= $n0;
  }
  # }}}
  function edit(object $chat, object $node): object # {{{
  {
    # prepare
    $n0 = count($m0 = &$this->msgs);
    $n1 = count($m1 = &$node->msgs);
    $ed = [];
    $rm = [];
    # edit messages (when they differ by hash)
    for ($i = 0; $i < $n1; ++$i)
    {
      if ($m0[$i]->hash !== $m1[$i]->hash) {
        $ed[] = $m0[$i]->edit($chat, $m1[$i]);
      }
    }
    # delete any extra messages
    if ($i < $n0)
    {
      for ($i; $i < $n0; ++$i)
      {
        $ed[] = $m0[$i]->delete($chat);
        $rm[] = $i;
      }
    }
    # compose result
    return Promise::AllBreak(
      $ed
    )->then(function() use (&$m0,$rm) {
      # remove message entries upon success
      if ($this->result)
      {
        foreach ($rm as $i) {
          array_splice($m0, $i);
        }
      }
    });
  }
  # }}}
}
# }}}
# }}}
## components
# base
# item {{{
abstract class BotItem implements ArrayAccess # {{{
{
  # {{{
  const OPTION = [
    'events'     => false,
    'data'       => false,
    'data.scope' => 'chat',
    'data.name'  => '',
    'data.depth' => 0,
  ];
  public $base,$caps,$texts,$parent,$root,$children;
  # }}}
  function __construct(# {{{
    public object   $bot,
    public string   $path,
    public int      $depth,
    public string   $name,
    public string   $id,
    public string   $type,
    public array    $spec,
    public ?object  $hand
  ) {
    # set inheritance chain
    $this->base = [$a = $this::class];
    while ($a = get_parent_class($a)) {
      $this->base[] = $a;
    }
    # set captions
    $this->caps = ArrayUnion::construct('')
      ->pushRef($bot->text->caps)
      ->pushRef($spec['caps']);
    # set texts
    foreach ($spec['text'] as $lang => &$node) {
      $node = new BotItemText($this, $lang, $node);
    }
    $this->texts = &$spec['text'];
  }
  # }}}
  function __debugInfo(): array # {{{
  {
    return [$this->path, $this::class];
  }
  # }}}
  function build(array &$schema, ?object $parent): object # {{{
  {
    # set parent and root
    $this->root = ($this->parent = $parent)
      ? $parent->root
      : $this;
    # set children
    $a = $this->depth + 1;
    $c = strlen($b = $this->path);
    $d = [];
    foreach ($schema as $path => $item)
    {
      if ($item->depth === $a && strncmp($item->path, $b, $c) === 0) {
        $d[$item->name] = $item->build($schema, $this);
      }
    }
    $this->children = &$d;
    return $this;
  }
  # }}}
  # [base/spec] access {{{
  function baseOf(string $k): string
  {
    foreach ($this->base as $type)
    {
      if (isset($type::OPTION[$k])) {
        return $type;
      }
    }
    return '';
  }
  function offsetExists(mixed $k): bool {
    return $this->baseOf($k) !== '';
  }
  function offsetGet(mixed $k): mixed
  {
    if (isset($this->spec[$k])) {
      return $this->spec[$k];
    }
    return ($type = $this->baseOf($k))
      ? $type::OPTION[$k]
      : null;
  }
  function offsetSet(mixed $k, mixed $v): void
  {}
  function offsetUnset(mixed $k): void
  {}
  # }}}
  function ctx(string $event, object $req): ?object # {{{
  {
    # create context
    $ctx = new BotItemCtx($this, $req);
    # check events are disabled for this class/spec
    if (!$this['events']) {
      return $ctx;
    }
    try
    {
      # replace open event at first render
      if ($event === 'open' && $ctx->conf->isEmpty()) {
        $event = 'init';
      }
      # invoke component handler
      if (!$this->event($event, $ctx)) {
        throw ErrorEx::skip($event, 'denied by component');
      }
      # invoke service handler
      if (($hand = $this->hand) &&
          !$hand->call($ctx, new BotItemQuery('event', $event)))
      {
        throw ErrorEx::skip($event, 'denied by service');
      }
    }
    catch (Throwable $e)
    {
      $ctx->log->exception($e);
      return null;
    }
    return $ctx;
  }
  # }}}
  function event(
    object $ctx, string $event
  ):bool { return true; }
  abstract function render(
    object $ctx, object $query
  ):array;
}
# }}}
class BotItemText implements ArrayAccess # {{{
{
  const TEMPLATE_PREFIX = '#';
  private $template = '',$tp,$texts;
  function __construct(# {{{
    object $item, string $lang, array $spec
  ) {
    # prepare
    $text = $item->bot->text;
    $base = &$text->texts[$lang];
    # set template reference
    if (isset($spec[$k = self::TEMPLATE_PREFIX])) {
      $this->template = &$spec[$k];
    }
    elseif (isset($base[$k = $k.$item->type])) {
      $this->template = &$base[$k];
    }
    # set parser and texts
    $this->tp = $text->tp;
    $this->texts = ArrayUnion::construct('')
      ->pushRef($base)
      ->pushRef($spec);
  }
  # }}}
  # [access] {{{
  function offsetExists(mixed $k): bool {
    return isset($this->texts[$k]);
  }
  function offsetGet(mixed $k): mixed {
    return $this->texts[$k];
  }
  function offsetSet(mixed $k, mixed $v): void
  {}
  function offsetUnset(mixed $k): void
  {}
  # }}}
  function render(# {{{
    array $data = [], string $template = ''
  ):string
  {
    return $template
      ? $this->tp->render($template, $data)
      : $this->tp->render($this->template, $data);
  }
  # }}}
}
# }}}
class BotItemQuery # {{{
{
  public $res;
  function __construct(
    public string $func,
    public string &$args
  ) {}
}
# }}}
class BotItemCtx implements ArrayAccess # {{{
{
  # {{{
  const
    CHR_TITLE = '@',
    NOP = ['text'=>' ','callback_data'=>'!'];
  public
    $log,$chat,$user,$req,$lang,
    $caps,$text,$conf,$opts,$data;
  # }}}
  function __construct(private object $item, object $req) # {{{
  {
    $bot = $item->bot;
    $this->log  = $req->log->new($item->path);
    $this->chat = ($chat = $req->chat)->info;
    $this->user = ($user = $req->user)->info;
    $this->req  = $req->data;
    $this->lang = $lang = $chat->lang ?: $user->lang;
    $this->caps = $item->caps;
    $this->text = $item->texts[$lang];
    $this->conf = $chat->conf->obtain($item->id);
    $this->opts = $opts = new BotItemCtxOptions(
      $item, $chat->opts->obtain($item->id)
    );
    if ($opts['data'])
    {
      $a = $opts['data.name']
        ?? self::CHR_TITLE.$bot->cmd->filename($item);
      $a = match ($opts['data.scope']) {
        'global' => $bot->cfg->dirDataRoot.$a,
        'bot'    => $bot->cfg->dirData.$a,
        default  => $chat->dir.$a,
      };
      $this->data = $bot->file->node(
        $a.'.json', $opts['data.depth']
      );
    }
  }
  # }}}
  # [conf] access {{{
  function offsetExists(mixed $k): bool {
    return isset($this->conf[$k]);
  }
  function offsetGet(mixed $k): mixed {
    return $this->conf[$k];
  }
  function offsetSet(mixed $k, mixed $v): void {
    $this->conf[$k] = $v;
  }
  function offsetUnset(mixed $k): void {
    $this->conf[$k] = null;
  }
  # }}}
  function markup(# InlineKeyboardMarkup {{{
    ?array $flag = null,
    ?array $mkup = null
  ):string
  {
    # prepare
    if (!$mkup) {
      $mkup = $this->opts['markup'];
    }
    # operate
    $a = [];
    foreach ($mkup as &$row)
    {
      $b = [];
      foreach ($row as $btn)
      {
        # check rendered
        if (is_array($btn))
        {
          $b[] = $btn;
          continue;
        }
        # skip incorrect type or empty
        if (!is_string($btn) ||
            !($c = strlen($btn)))
        {
          continue;
        }
        # check NOP
        if ($c === 1)
        {
          $b[] = self::NOP;
          continue;
        }
        # render
        if ($btn = $this->markupButton($btn, $flag)) {
          $b[] = $btn;
        }
      }
      $b && ($a[] = $b);
    }
    # check empty
    if (!$a) {
      return '';
    }
    # complete
    return json_encode(
      ['inline_keyboard' => $a],
      JSON_UNESCAPED_UNICODE
    );
  }
  # }}}
  function markupButton(# {{{
    string $text, ?array &$flag
  ):?array
  {
    # parse
    $item = $this->item;
    $bot  = $item->bot;
    if (!($a = $bot->cmd->parseCallback($text, $item))) {
      return null;
    }
    $func = $a[1];
    $args = $a[2];
    # check another item
    if (($a = $a[0]) !== '' && $a !== $item->path)
    {
      # render item navigation
      return ($a = $bot->cmd[$a])
        ? $this->markupButtonItem($a, $func, $args)
        : null;
    }
    # apply flag
    if ($flag && isset($flag[$func]))
    {
      # check NONE
      if (!($a = $flag[$func])) {
        return null;
      }
      # check NOP
      if ($a < 0) {
        return self::NOP;
      }
      # continue
    }
    # check backward navigation
    if ($func === 'up')
    {
      if ($item = $item->parent)
      {
        $a = $this->caps[$func];
        $b = $item->texts[$this->lang][self::CHR_TITLE]
          ?: $item->name;
      }
      else
      {
        $a = $this->caps[$b = 'close'];
        $b = $this->text[$b];
      }
      $a = $bot->text->tp->render($a, $b);
      $b = $bot->cmd->query($func, $args);
      return [
        'text'=>$a,'callback_data'=>$b
      ];
    }
    # render caption text
    $a = $this->texts[$func];
    if ($b = $this->caps[$func]) {
      $a = $bot->text->tp->render($a, $b);
    }
    # check game
    if ($func === 'play')
    {
      return [
        'text'=>$a,'callback_game'=>null
      ];
    }
    # render callback button
    $b = $bot->cmd->query($func, $args);
    return [
      'text'=>$a,'callback_data'=>$b
    ];
  }
  # }}}
  function markupButtonItem(# {{{
    object $item, string &$func, string &$args
  ):?array
  {
    $a = $this->caps['open'];
    $b = $this->text[$item->name]
      ?: $item->texts[$this->lang][self::CHR_TITLE]
      ?: $item->name;
    $c = $item->bot;
    $a = $c->text->tp->render($a, $b);
    $b = $c->cmd->query($func, $args, $item);
    return [
      'text'=>$a,'callback_data'=>$b
    ];
  }
  # }}}
}
# }}}
class BotItemCtxOptions implements ArrayAccess # {{{
{
  function __construct(
    private object $item,
    private object $spec
  ) {}
  function grab(string $k): object {
    return new BotItemCtxOption($this, $k.'.');
  }
  function offsetExists(mixed $k): bool {
    return isset($this->item[$k]);
  }
  function offsetGet(mixed $k): mixed
  {
    return isset($this->spec[$k])
      ? $this->spec[$k]
      : $this->item[$k];
  }
  function offsetSet(mixed $k, mixed $v): void
  {
    if (isset($this->item[$k])) {
      $this->spec[$k] = $v;
    }
  }
  function offsetUnset(mixed $k): void {
    $this->spec[$k] = null;
  }
}
# }}}
class BotItemCtxOption implements ArrayAccess # {{{
{
  function __construct(
    private object $opts,
    private string $from
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
# }}}
# message {{{
abstract class BotMessage extends BotConfigAccess implements JsonSerializable
{
  static function reconstruct(# {{{
    object $bot, array &$json
  ):?self
  {
    return class_exists($json[0], false)
      ? new $json[0]($bot, $json[1], $json[2])
      : null;
  }
  # }}}
  static function construct(# {{{
    object $bot, array &$data
  ):?self
  {
    return new static(
      $bot, 0, static::stew($data), $data
    );
  }
  # }}}
  function __construct(# {{{
    public object $bot,
    public int    $id,
    public string $hash,
    public ?array &$data = null
  ) {}
  # }}}
  function jsonSerialize(): array # {{{
  {
    return [$this::class, $this->id, $this->hash];
  }
  # }}}
  function delete(object $chat): object # {{{
  {
    return $this->bot->api->promise('deleteMessage', [
      'chat_id'    => $chat->id,
      'message_id' => $this->id,
    ])->check(function() {
      return $this->result === true;
    });
  }
  # }}}
  abstract static function stew(array &$data): string;
  abstract function zap(object $chat): object;
  abstract function send(object $chat): object;
  abstract function edit(object $chat, object $msg): object;
}
# }}}
# Img {{{
class BotImgItem extends BotItem # {{{
{
  const OPTION = [# {{{
    'img.variant' => 'title',
    ### static file
    'img.file.name' => '',# name, id otherwise
    'img.file.vary' => true,# append custom when set
    ### generated title
    'img.title.filename' => '',# background image
    'img.title.blank' => [# background blank
      [640,160],# size: width,height
      [0,0,32],# color: R,G,B
    ],
    'img.title.header' => [
      'Days',[6,64],# font name and size range
      [255,255,255],# text color: R,G,B
      [140,0,360,160],# draw rect x,y,w,h
    ],
    'img.title.breadcrumb' => [
      'Bender-Italic',16,# font name and size
      [135,206,235],# text color
      [140,32],# draw coordinates
    ],
  ];
  # }}}
  # helpers {{{
  static function imgFileTexts(# {{{
    string $file, array &$texts
  ):object
  {
    try
    {
      $img = self::imgFromFile($file);
      self::imgDrawTexts($img, $texts);
      $res = self::imgToFile($img);
    }
    catch (Throwable $e) {
      $res = ErrorEx::from($e);
    }
    isset($img) && imagedestroy($img);
    return $res;
  }
  # }}}
  static function imgBlankTexts(# {{{
    array $blank, array &$texts
  ):object
  {
    try
    {
      $img = self::imgFromBlank($blank);
      self::imgDrawTexts($img, $texts);
      $res = self::imgToFile($img);
    }
    catch (Throwable $e) {
      $res = ErrorEx::from($e);
    }
    isset($img) && imagedestroy($img);
    return $res;
  }
  # }}}
  static function imgPlaceholder(array $blank): object # {{{
  {
    try
    {
      $img = self::imgFromBlank($blank);
      $res = self::imgToFile($img);
    }
    catch (Throwable $e) {
      $res = ErrorEx::from($e);
    }
    isset($img) && imagedestroy($img);
    return $res;
  }
  # }}}
  static function imgFromFile(string $file): object # {{{
  {
    if (!($img = imagecreatefromjpeg($file))) {
      throw ErrorEx::stop('imagecreatefromjpeg', $file);
    }
    return $img;
  }
  # }}}
  static function imgFromBlank(array &$blank): object # {{{
  {
    $img   = self::imgNew($blank[0]);
    $color = self::imgNewColor($img, $blank[1]);
    if (!imagefill($img, 0, 0, $color)) {
      throw ErrorEx::fail('imagefill');
    }
    return $img;
  }
  # }}}
  static function imgNew(array &$WH): object # {{{
  {
    if (!($x = imagecreatetruecolor($WH[0], $WH[1]))) {
      throw ErrorEx::fail(implode(':', $WH));
    }
    return $x;
  }
  # }}}
  static function imgNewColor(# {{{
    object $img, array &$RGB
  ):int
  {
    $x = imagecolorallocate($img, $RGB[0], $RGB[1], $RGB[2]);
    if ($x === false) {
      throw ErrorEx::fail(implode(':', $RGB));
    }
    return $x;
  }
  # }}}
  static function imgDrawTexts(# {{{
    object $img, array &$texts
  ):void
  {
    foreach ($texts as &$t)
    {
      if (count($t[3]) === 4)
      {
        self::imgDrawHeader(
          $img, $t[0], $t[1][0], $t[1][1], 0,
          $t[2], $t[3], $t[4]
        );
      }
      else
      {
        self::imgDrawText(
          $img, $t[0], $t[1], 0,
          $t[2], $t[3][0], $t[3][1], $t[4]
        );
      }
    }
  }
  # }}}
  static function imgDrawHeader(# {{{
    object  $img,
    string  $font,
    int     $min,
    int     $max,
    float   $angle,
    array   &$color,
    array   &$rect,
    string  $text
  ):void
  {
    # header should fit into given rect, so
    # determine optimal font size (in points not pixels? -seems not)
    while ($max > $min)
    {
      # determine bounding box
      if (!($a = imageftbbox($max, $angle, $font, $text)))
      {
        throw ErrorEx::fail('imageftbbox',
          "\ntext: ".$text."\nfont: ".$font.
          "\nsize: ".$max
        );
      }
      # check it fits width and height
      if ($a[2] - $a[0] <= $rect[2] &&
          $a[1] - $a[7] <= $rect[3])
      {
        break;
      }
      # reduce and retry
      $max -= 2;
    }
    # determine starting coordinates (center align)
    $x = $a[2] - $a[0];
    $y = $a[1] - $a[7];
    $x = $rect[0] + ($rect[2] - $x) / 2;
    $y = $rect[1] + ($rect[3] - $y) / 2 + $y;
    # draw
    self::imgDrawText(
      $img, $font, $max, $angle, $color,
      (int)round($x), (int)round($y), $text
    );
  }
  # }}}
  static function imgDrawText(# {{{
    object  $img,
    string  $font,
    int     $size,
    float   $angle,
    array   &$color,
    int     $x,
    int     $y,
    string  $text
  ):void
  {
    $x = imagefttext(
      $img, $size, $angle, $x, $y,
      self::imgNewColor($img, $color),
      $font, $text
    );
    if ($x === false)
    {
      throw ErrorEx::fail(
        "imagefttext()\n".
        "text: ".$text."\nfont: ".$font."\n".
        "size: ".$size." angle: ".$angle."\n".
        "point: x=".$x." y=".$y
      );
    }
  }
  # }}}
  static function imgToFile(object $img): object # {{{
  {
    $dir = sys_get_temp_dir();
    if (!($file = tempnam($dir, 'img'))) {
      throw ErrorEx::fail('tempnam', $dir);
    }
    if (!imagejpeg($img, $file)) {
      throw ErrorEx::fail('imagejpeg', $file);
    }
    return BotApiFile::construct($file, true);
  }
  # }}}
  # }}}
  function render(object $ctx, object $q): array # {{{
  {
    # invoke custom renderer
    if (($f = $this->hand) && !$f->call($ctx, $q)) {
      throw ErrorEx::skip();
    }
    $a = is_array($q->res)
      ? $q->res : [];
    # standard render
    if (!isset($a['path']) || !isset($a['image'])) {
      $this->renderImage($ctx, $a);
    }
    if (!isset($a[$b = 'text'])) {
      $a[$b] = $ctx->text->render();
    }
    if (!isset($a[$b = 'markup'])) {
      $a[$b] = $ctx->markup();
    }
    # complete
    return [
      BotImgMessage::construct($this->bot, $a)
    ];
  }
  # }}}
  function renderImage(# {{{
    object $ctx, array &$msg
  ):void
  {
    # prepare
    $bot = $this->bot;
    $opt = $ctx->opts;
    # check variant
    switch ($opt['img.variant']) {
    case 'file':
      # determine path
      if (!($path = $opt['img.file.name'])) {
        $path = $bot->cmd->filename($this);
      }
      if ($opt['img.file.vary'] && isset($msg['name'])) {
        $path = $path.$msg['name'];
      }
      if (!($path = $bot->file->image($path))) {
        throw ErrorEx::skip();
      }
      # get identifier from cache or
      # set persistent file for transmission
      if (!($file = $bot->file->id($path))) {
        $file = BotApiFile::construct($path);
      }
      break;
    default:
      # determine path
      $text = $msg['title'] ?? $ctx->text['@'] ?: $this->name;
      $path = $bot->cmd->pathHash($this, $text);
      # get identifier from cache
      if ($file = $bot->file->id($path)) {
        break;
      }
      # prepare texts
      $a = [];
      if ($b = $opt['img.title.header'])
      {
        if (!($b[0] = $bot->file->font($b[0]))) {
          throw ErrorEx::stop();
        }
        $b[] = $text;
        $a[] = $b;
      }
      if ($b = $opt['img.title.breadcrumb'])
      {
        if (!($b[0] = $bot->file->font($b[0]))) {
          throw ErrorEx::stop();
        }
        $b[] = $this->path;
        $a[] = $b;
      }
      # create file for transmission
      $file = ($b = $opt['img.title.filename'])
        ? self::imgFileTexts($b, $a)
        : self::imgBlankTexts($opt['img.title.blank'], $a);
      # check
      if ($file instanceof ErrorEx) {
        throw $file;
      }
      break;
    }
    # complete
    $msg['path']  = $path;
    $msg['image'] = $file;
  }
  # }}}
}
# }}}
class BotImgMessage extends BotMessage # {{{
{
  const UUID = '325d301047084c46bba3eeb44051a1c2';
  static function stew(array &$a): string {
    return hash('xxh3', $a['path'].$a['text'].$a['markup']);
  }
  function zap(object $chat): object # {{{
  {
    # prepare
    $self = $this;
    $bot  = $this->bot;
    $path = self::UUID;
    $file = null;
    # get placeholder
    if (!($id = $bot->file->id($path)))
    {
      $file = BotImgItem::imgPlaceholder(
        $this['blank']
      );
      if ($file instanceof ErrorEx)
      {
        # ...
      }
    }
    # compose request
    $res = [
      'chat_id'    => $chat->id,
      'message_id' => $this->id,
      'media'      => json_encode([
        'type'     => 'photo',
        'media'    => $id ?: 'attach://'.$file->postname
      ])
    ];
    $res = $file
      ? $bot->api->promise('editMessageMedia', $res, $file)
      : $bot->api->promise('editMessageMedia', $res);
    $res->check(function() use ($bot,$path,$id) {
      # check unsuccessful
      if (!($res = $this->result) || $res === true) {
        return false;
      }
      # store file identifier
      if (!$id) {
        $bot->file->id($path, end($res->photo)->file_id);
      }
      # complete
      return true;
    });
    return $res;
  }
  # }}}
  function send(object $chat): object # {{{
  {
    # prepare
    $self = $this;
    $data = &$this->data;
    $req  = [
      'chat_id' => $chat->id,
      'photo'   => $data['image'],
      'disable_notification' => true,
    ];
    if (isset($data[$a = 'text']))
    {
      $req['caption']    = $data[$a];
      $req['parse_mode'] = 'HTML';
    }
    if (isset($data[$a = 'markup'])) {
      $req['reply_markup'] = $data[$a];
    }
    # complete
    return $this->bot->api->promise(
      'sendPhoto', $req
    )->check(function() use ($self,&$data) {
      # get created message
      if (!($res = $this->result)) {
        return false;
      }
      # store file identifier
      if (is_object($data['image']) &&
          ($path = $data['path'] ?? ''))
      {
        $self->bot->file->id(
          $path, end($res->photo)->file_id
        );
      }
      # store message identifier
      $self->id = $res->message_id;
      return true;
    });
  }
  # }}}
  function edit(object $chat, object $msg): object # {{{
  {
    # prepare
    $self = $this;
    $bot  = $this->bot;
    $data = &$msg->data;
    $path = is_object($file = $data['image'])
      ? 'attach://'.$file->postname
      : '';
    $res = [# InputMediaPhoto
      'type'       => 'photo',
      'media'      => ($path ?: $file),
      'caption'    => $data['text'],
      'parse_mode' => 'HTML',
    ];
    $res = [
      'chat_id'      => $chat->id,
      'message_id'   => $this->id,
      'media'        => json_encode($res),
      'reply_markup' => $data['markup'],
    ];
    # compose request
    $res = $path
      ? $bot->api->promise('editMessageMedia', $res, $file)
      : $bot->api->promise('editMessageMedia', $res);
    $res->check(function() use ($self,$msg,$path) {
      # check successful
      if (!($res = $this->result) || $res === true) {
        return false;
      }
      # store file identifier
      if ($path && ($path = $msg->data['path'] ?? ''))
      {
        $self->bot->file->id(
          $path, end($res->photo)->file_id
        );
      }
      # store message hash
      $self->hash = $msg->hash;
      return true;
    });
    return $res;
  }
  # }}}
}
# }}}
# }}}
# Txt {{{
class BotTxtItem extends BotItem # {{{
{
  const OPTION = [# {{{
  ];
  # }}}
  function render(object $ctx, object $q): array # {{{
  {
    return [];
  }
  # }}}
}
# }}}
class BotTxtMessage extends BotMessage # {{{
{
  static function stew(array &$a): string {
    return hash('xxh3', $a['text'].$a['markup']);
  }
  function send(object $chat): object # {{{
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
  function edit(object $chat, object $msg): object # {{{
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
  function zap(object $chat): object # {{{
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
# compound
 # List {{{
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
  function render(object $ctx, object $q): array # {{{
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
 # Form {{{
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
  function render(object $ctx, object $q): array # {{{
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
  function init_TODO(array &$cfg): bool # {{{
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
* fix: slow api action generator
* feature: file access time, lazy sync/cleanup
* solve: request/response/context
* test: handlers source errors
* test: file_id usage
* perf: mustache parser text reference and prepare()
* feature: request filters
* feature: WebHook, operate through fast-cgi (nginx)
* stability: masterbot should erase outdated locks of bots that failed for unknown reason
* solve: form data separation, user changes its own data until completion
* fix: compatible msg update: unnecessary re-creation in private chat
* solve: old message callback action does ZAP? or.. REFRESH?!
* architect: bot.file -> bot.data
* architect: BotConfig ~ BotDir
*/
# }}}
