<?php declare(strict_types=1);
# globals {{{
namespace SM;
use
  JsonSerializable,ArrayAccess,Iterator,Stringable,
  SyncEvent,SyncReaderWriter,SyncSharedMemory,
  Generator,Closure,CURLFile,
  Throwable,Error,Exception;
use function
  set_time_limit,ini_set,register_shutdown_function,
  set_error_handler,class_exists,function_exists,
  method_exists,func_num_args,
  ### variable handling
  gettype,intval,strval,is_object,is_array,is_bool,is_null,
  is_string,is_scalar,
  ### arrays
  explode,implode,count,reset,next,key,array_keys,
  array_push,array_pop,array_shift,array_unshift,
  array_splice,array_slice,in_array,array_search,
  array_reverse,
  ### strings
  strpos,strrpos,strlen,trim,rtrim,uniqid,ucfirst,
  str_repeat,str_replace,
  lcfirst,strncmp,substr_count,preg_match,preg_match_all,
  hash,http_build_query,
  json_encode,json_decode,json_last_error,
  json_last_error_msg,
  ### filesystem
  file_put_contents,file_get_contents,clearstatcache,
  file_exists,unlink,filesize,filemtime,tempnam,
  sys_get_temp_dir,mkdir,scandir,fwrite,fread,fclose,glob,
  ### CURL
  curl_init,curl_setopt_array,curl_exec,
  curl_errno,curl_error,curl_strerror,curl_close,
  curl_multi_init,curl_multi_add_handle,curl_multi_exec,
  curl_multi_select,curl_multi_strerror,
  curl_multi_info_read,curl_multi_remove_handle,
  curl_multi_close,
  ### misc
  proc_open,is_resource,proc_get_status,proc_terminate,
  getmypid,ob_start,ob_get_length,ob_flush,ob_end_clean,
  pack,unpack,time,hrtime,sleep,usleep,
  min,max,pow;
# }}}
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
  if (file_exists($file) === false ||
      ($a = file_get_contents($file)) === '')
  {
    $a = [];
    return $a;
  }
  if ($a === false ||
      ($a[0] !== '[' &&
       $a[0] !== '{'))
  {
    $a = null;
    return $a;
  }
  $a = json_decode(
    $a, true, 128, JSON_INVALID_UTF8_IGNORE
  );
  if (!is_array($a)) {
    $a = null;
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
function json_error(): string # {{{
{
  return (json_last_error() !== JSON_ERROR_NONE)
    ? json_last_error_msg() : '';
}
# }}}
# }}}
# classes {{{
trait TimeTouchable # {{{
{
  public $time = 0;
  function timeTouch(): object
  {
    $this->time = hrtime(true);
    return $this;
  }
  function timeAdd(int $ms): object
  {
    $this->time += $ms * 1000000; # milli => nano
    return $this;
  }
  function timeDelta(int $base = 0): int
  {
    $base = ($base ?: hrtime(true)) - $this->time;
    return (int)($base / 1000000);# nano => milli
  }
}
# }}}
class ErrorEx extends Error # {{{
{
  # {{{
  const E_NUM = [
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
  const E_LEVEL = [
    'Info','Warning','Error','Fatal'
  ];
  # }}}
  # static constuctors {{{
  static function skip(): self {
    return new self(0);
  }
  static function info(string ...$msg): self {
    return new self(0, $msg);
  }
  static function warn(string ...$msg): self {
    return new self(1, $msg);
  }
  static function fail(string ...$msg): self {
    return new self(2, $msg);
  }
  static function failFn(string ...$msg): self
  {
    # prefix messages with the point of failure
    $a = (count($a = $e->getTrace()) > 1)
      ? $a[1]['function'].'@'.$a[0]['line']
      : $a[0]['function'];
    array_unshift($msg, $a);
    return new self(2, $msg);
  }
  static function num(int $n, string $msg): self
  {
    return new self(3, [
      (self::E_NUM[$n] ?? "($n) UNKNOWN"), $msg
    ]);
  }
  static function from(object $e): self
  {
    return ($e instanceof self)
      ? $e : new self(3, [], $e);
  }
  # }}}
  function __construct(# {{{
    public int     $level = 0,
    public array   $msg   = [],
    public mixed   $value = null,
    public ?object $next  = null
  ) {
    parent::__construct('', -1);
  }
  # }}}
  function __debugInfo(): array # {{{
  {
    return [
      'level' => self::E_LEVEL[$this->level],
      'msg'   => implode('·', $this->msg),
      'next'  => $this->next
    ];
  }
  # }}}
  function levelMax(int $limit = 3): int # {{{
  {
    $a = $this->level;
    $b = $this->next;
    while ($b && $a < $limit)
    {
      if ($b->level > $a) {
        $a = $b->level;
      }
      $b = $b->next;
    }
    return $a;
  }
  # }}}
  function getMsg(string $default = ''): string # {{{
  {
    return $this->msg
      ? implode(' ', $this->msg)
      : $default;
  }
  # }}}
  # is {{{
  static function is(?object $e): bool {
    return ($e && ($e instanceof self));
  }
  function isFatal(): bool {
    return $this->level > 2;
  }
  function isError(): bool {
    return $this->level > 1;
  }
  function isWarning(): bool {
    return $this->level === 1;
  }
  function isInfo(): bool {
    return $this->level < 1;
  }
  # }}}
  # has {{{
  function hasError(): bool {
    return $this->levelMax() > 1;
  }
  function hasIssue(): bool {
    return $this->levelMax() > 0;
  }
  # }}}
  function count(): int # {{{
  {
    for ($x = 1, $e = $this->next; $e; $e = $e->next) {
      $x++;
    }
    return $x;
  }
  # }}}
  function last(): self # {{{
  {
    $a = $this;
    while ($b = $a->next) {
      $a = $b;
    }
    return $a;
  }
  # }}}
  ###
  function lastSet(?self $e = null): self
  {
    $e && ($this->last()->next = $e);
    return $this;
  }
  function nextSet(self $e): self
  {
    $this->next = $e->lastSet($this->next);
    return $this;
  }
  function nextFrom(object $e): self {
    return $this->nextSet(self::from($e));
  }
  function firstSet(self $e): self {
    return $e->lastSet($this);
  }
  function firstMsg(string ...$msg): self
  {
    $e = new self($this->levelMax(2), $msg);
    return $this->firstSet($e);
  }
}
# }}}
class Promise # {{{
{
  public $result,$next;
  function __construct(# {{{
    public ?object $action
  ) {}
  # }}}
  # static {{{
  static function Fn(object $f): self {
    return new self(new PromiseActionFn($f));
  }
  static function Check(object $f): self {
    return new self(new PromiseActionCheck($f));
  }
  static function Stop(?object $e = null): self {
    return new self(new PromiseActionStop($e));
  }
  static function One(array $a): self {
    return new self(new PromiseActionOne($a, false));
  }
  static function OneStop(array $a): self {
    return new self(new PromiseActionOne($a, true));
  }
  static function All(array $a): self {
    return new self(new PromiseActionAll($a, false));
  }
  static function AllStop(array $a): self {
    return new self(new PromiseActionAll($a, true));
  }
  static function from(?object $x): ?self
  {
    return $x
      ? (($x instanceof self)
        ? $x
        : (($x instanceof PromiseAction)
          ? new self($x)
          : self::Fn($x)))
      : null;
  }
  # }}}
  function complete(?object $result = null): ?object # {{{
  {
    # get action and check complete
    if (!($a = $this->action)) {
      return $this->result;
    }
    # get result and initialize
    if (!$a->result && !$a->init($result))
    {
      $this->action = null;
      return $this->result = $a->result;
    }
    # operate
    if ($a->spin()) {
      return null;
    }
    # complete
    if ($p = self::from($a->stop()))
    {
      if ($this->next) {
        $p->last()->next = $this->next;
      }
    }
    # continue
    if ($p || ($p = $this->next))
    {
      $this->action = $p->action;
      $this->next   = $p->next;
      return $this->complete($a->result);
    }
    # complete
    $this->action = null;
    return $this->result = $a->result;
  }
  # }}}
  function cancel(): bool # {{{
  {
    if ($a = $this->action)
    {
      if ($x = $a->result) {# started
        $a->stop();
      }
      else {# pending
        $x = new PromiseResult();
      }
      $this->action = null;
      $this->result =
        $x->failure()->message('cancel');
    }
    return true;
  }
  # }}}
  function then(?object $x): self # {{{
  {
    if ($x = self::from($x))
    {
      if ($this->action) {
        $this->last()->next = $x;
      }
      else
      {
        $this->action = $x->action;
        $this->next   = $x->next;
        $this->result = null;
      }
    }
    return $this;
  }
  # }}}
  function thenOne(array $a): self # {{{
  {
    return $this->then(new PromiseActionOne($a, false));
  }
  # }}}
  function thenOneStop(array $a): self # {{{
  {
    return $this->then(new PromiseActionOne($a, true));
  }
  # }}}
  function thenCheck(object $f): self # {{{
  {
    return $this->then(new PromiseActionCheck($f));
  }
  # }}}
  function last(): self # {{{
  {
    $a = $this;
    while ($b = $a->next) {
      $a = $b;
    }
    return $a;
  }
  # }}}
}
class PromiseResult # {{{
{
  public $track,$ok,$value;
  function __construct(?object $t = null) # {{{
  {
    if ($t === null) {
      $t = new PromiseResultTrack();
    }
    $this->track = [$t];
    $this->ok    = &$t->ok;
    $this->value = &$t->value;
  }
  # }}}
  function __debugInfo(): array # {{{
  {
    return $this->track;
  }
  # }}}
  function current(): object # {{{
  {
    return ($t = $this->track[0])->msg
      ? $this->next()
      : $t;
  }
  # }}}
  function next(): object # {{{
  {
    array_unshift(
      $this->track,
      $t = new PromiseResultTrack()
    );
    $this->ok    = &$t->ok;
    $this->value = &$t->value;
    return $t;
  }
  # }}}
  function success(mixed $value = null): self # {{{
  {
    $this->current();
    $this->value = $value;
    $this->ok = true;
    return $this;
  }
  # }}}
  function failure(?object $e = null): self # {{{
  {
    $this->current()->setError($e);
    $this->ok = false;
    return $this;
  }
  # }}}
  function message(string ...$msg): self # {{{
  {
    $this->track[0]->setError($this->ok
      ? new ErrorEx(0, $msg)
      : new ErrorEx(2, $msg)
    );
    return $this;
  }
  # }}}
  function confirm(string ...$msg): self # {{{
  {
    $this->current()->setMsg($msg);
    return $this;
  }
  # }}}
  function confirms(array &$msg): self # {{{
  {
    $this->current()->setMsg($msg);
    return $this;
  }
  # }}}
  function group(object $result): self # {{{
  {
    $this->current()->setGroup($result);
    return $this;
  }
  # }}}
  function pack(): self # {{{
  {
    # extract, wrap and group confirmed track
    if ($this->track[0]->msg)
    {
      $r = new self(array_shift($this->track));
      $this->next()->setGroup($r);
    }
    return $this;
  }
  # }}}
}
class PromiseResultTrack
{
  public $ok = true,$value,$msg,$error,$group;
  function __debugInfo(): array # {{{
  {
    return [
      'ok'    => $this->ok,
      'msg'   => implode('·', $this->msg ?? []),
      'error' => $this->error,
      'group' => $this->group
    ];
  }
  # }}}
  function setMsg(array &$msg): void # {{{
  {
    $this->msg = &$msg;
  }
  # }}}
  function setError(?object $e): void # {{{
  {
    if ($e)
    {
      $this->error = $this->error
        ? $e->lastSet($this->error)
        : $e;
    }
  }
  # }}}
  function setGroup(object $result): void # {{{
  {
    if ($this->group) {
      array_unshift($this->group, $result);
    }
    else {
      $this->group = [$result];
    }
    if ($this->ok && $result->ok === false) {
      $this->ok = false;
    }
  }
  # }}}
}
# }}}
class PromiseOne # {{{
{
  public $queue = [];
  function __construct(?object $o = null) {
    $this->then($o);
  }
  function then(?object $o): self
  {
    if ($o = Promise::from($o)) {
      $this->queue[] = $o;
    }
    return $this;
  }
  function complete(): bool
  {
    if (!($q = &$this->queue)) {
      return true;
    }
    if (!$q[0]->complete()) {
      return false;
    }
    array_shift($q);
    return $this->complete();
  }
}
# }}}
class PromiseAll extends PromiseOne # {{{
{
  function complete(): bool
  {
    if (($k = count($q = &$this->queue)) === 0) {
      return false;
    }
    for ($i = $k - 1; $i >= 0; --$i)
    {
      if ($q[$i]->complete()) {
        array_splice($q, $i, 1); $k--;
      }
    }
    return ($k > 0);
  }
}
# }}}
abstract class PromiseAction # {{{
{
  public $result;
  final function init(?object $res): bool
  {
    $this->result = $res ?? new PromiseResult();
    return $this->start();
  }
  function start(): bool {
    return true;
  }
  function spin(): bool {
    return false;
  }
  function stop(): ?object {
    return null;
  }
}
# }}}
class PromiseActionFn extends PromiseAction # {{{
{
  function __construct(public object $func)
  {}
  function stop(): ?object {
    return ($this->func)($this->result);
  }
}
# }}}
class PromiseActionCheck extends PromiseAction # {{{
{
  function __construct(public object $func)
  {}
  function start(): bool {
    return ($this->func)($this->result);
  }
}
# }}}
class PromiseActionStop extends PromiseAction # {{{
{
  function __construct(public ?object $error)
  {}
  function start(): bool
  {
    $this
      ->result
      ->failure($this->error)
      ->message('stop');
    return false;
  }
}
# }}}
class PromiseActionOne extends PromiseAction # {{{
{
  function __construct(# {{{
    public array &$queue,
    public bool  $stopFlag
  ) {
    # filter promise queue
    for ($i = count($queue) - 1; $i >= 0; --$i)
    {
      if ($a = Promise::from($queue[$i])) {
        $queue[$i] = $a;
      }
      else {
        array_splice($queue, $i, 1);
      }
    }
  }
  # }}}
  function start(): bool # {{{
  {
    if ($this->stopFlag && !$this->result->ok) {
      $this->queue = [];
    }
    return true;
  }
  # }}}
  function spin(): bool # {{{
  {
    # check complete
    if (!($q = &$this->queue)) {
      return false;
    }
    # complete first
    if (($r = $q[0]->complete()) === null) {
      return true;
    }
    # eject promise and store result
    array_shift($q);
    $this->result->group($r);
    # check have to stop
    if ($this->stopFlag && $r->ok === false) {
      return false;
    }
    # recurse
    return $this->spin();
  }
  # }}}
  function stop(): ?object # {{{
  {
    if ($q = &$this->queue)
    {
      foreach ($q as $p) {
        $p->cancel();
      }
      $q = [];
    }
    return null;
  }
  # }}}
}
# }}}
class PromiseActionAll extends PromiseActionOne # {{{
{
  function spin(): bool
  {
    # check complete
    if (($k = count($q = &$this->queue)) === 0) {
      return false;
    }
    # complete all
    for ($i = $k - 1; $i >= 0; --$i)
    {
      if ($r = $q[$i]->complete())
      {
        # eject promise and store result
        array_splice($q, $i, 1);
        $this->result->group($r);
        # check have to stop
        if ($this->stopFlag && $r->ok === false) {
          return false;
        }
        $k--;
      }
    }
    return ($k > 0);
  }
}
# }}}
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
    return [
      'limit' => $this->limit,
      'array' => $this->arr,
    ];
  }
  # }}}
  function restruct(int $limit): self # {{{
  {
    # check growth possibility
    if ($this->limit > $limit) {
      return $this;
    }
    # grow
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
  function change(?self $node = null): self # {{{
  {
    if ($this->brr === null && $this->parent) {
      $this->parent->change($node ?? $this);
    }
    else {
      $this->changed = true;
    }
    return $this;
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
  function indexOfValue(mixed &$value): int # {{{
  {
    $i = 0;
    foreach ($this->arr as &$v)
    {
      if ($v === $value) {
        return $i;
      }
      $i++;
    }
    return -1;
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
  function delete(mixed $value): self # {{{
  {
    if (($i = $this->indexOfValue($value)) >= 0)
    {
      array_splice($this->arr, $i, 1);
      $this->count--;
      $this->change();
    }
    return $this;
  }
  # }}}
  function replace(mixed $v0, mixed $v1): self # {{{
  {
    if (($i = $this->indexOfValue($v0)) >= 0)
    {
      $this->arr[$i] = $v1;
      $this->change();
    }
    return $this;
  }
  # }}}
  function obtain(string $k): ?object # {{{
  {
    if (isset($this->arr[$k])) {
      return $this->arr[$k];
    }
    if (($limit = $this->limit - 1) < 0) {
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
      if (!$f($v, $i, $k)) {
        return $i;
      }
      $i++;
    }
    return -1;
  }
  # }}}
  function set(array $a): self # {{{
  {
    return $this->setRef($a);
  }
  # }}}
  function setRef(array &$a): self # {{{
  {
    $this->arr = &$a;
    return $this->restruct($this->limit)->change();
  }
  # }}}
  function keys(): array # {{{
  {
    return array_string_keys($this->arr);
  }
  # }}}
  function &slice(# {{{
    int $idx = 0, ?int $len = null
  ):array
  {
    $a = array_slice($this->arr, $idx, $len);
    return $a;
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
      throw ErrorEx::failFn('incorrect buffer size: '.$a);
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
        throw ErrorEx::failFn('incorrect buffer size: '.$b);
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
      throw ErrorEx::failFn('SyncEvent::reset');
    }
    if (!$this->membuf->write($data)) {
      throw ErrorEx::failFn('Membuf::write');
    }
    if ($this->membuf->overflow) {
      throw ErrorEx::failFn('buffer overflow');
    }
    if (!$this->wEvent->fire()) {
      throw ErrorEx::failFn('SyncEvent::fire');
    }
    if (!$this->rEvent->wait($timeout ?: $this->timeout)) {
      throw ErrorEx::failFn('response timed out');
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
      throw ErrorEx::failFn('SyncEvent::reset');
    }
    if ($this->membuf->overflow) {
      throw ErrorEx::failFn('buffer overflow');
    }
    if (!$this->rEvent->fire()) {
      throw ErrorEx::failFn('SyncEvent::fire');
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
# }}}
## boundary
# console {{{
class BotConsole
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
    if (strlen($text) && $this->active->wait(0))
    {
      if (!($this->locked = $this->lock->readlock(self::TIMEOUT))) {
        throw ErrorEx::failFn('SyncReaderWriter::readlock');
      }
      if (!$this->membuf->write($text, true)) {
        throw ErrorEx::failFn('Membuf::write');
      }
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
class BotMasterConsole extends BotConsole
{
  const FILE_CONIO = 'conio.php';
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
      throw ErrorEx::failFn('SyncReaderWriter::writelock');
    }
    # read and flush
    if ($a = $this->membuf->read())
    {
      fwrite(STDOUT, $a);
      if ($this->membuf->overflow)
      {
        fwrite(STDOUT, '.');
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
# logger {{{
class BotLog
{
  # {{{
  const
    COLOR = [
      'green','yellow','red', # 0:info,1:warn,2:error
      'cyan' # 3:prompt
    ],
    SEP = [# ●◆◎∙▪■ ▶▼ ■▄ ◥◢◤◣  ►◄ ∙･·•
      ' ► ',' ◄ ', # 0:output,1:input
      '•','･','·', # 2:result,3:object,4:words
      '◆','◆'      # 5:line,6:block
    ];
  public
    $errorCount = 0;
  # }}}
  # hlp {{{
  static function fgColor(# {{{
    string $str, string $name, int $strong = 0
  ):string
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
  static function block(# {{{
    string $s, string $color = '', int $strong = 1
  ):string
  {
    static $z = [
      '└┬', ' ├', ' └',
      '└┐', ' │', '└─',
    ];
    $x = $z;
    if ($color === '') {
      $color = self::COLOR[3];
    }
    foreach ($x as &$c) {
      $c = self::fgColor($c, $color, $strong);
    }
    # trim and split into non-colored
    # for reading and colored for writing
    $s = trim($s);
    $a = explode("\n", self::clearColors($s));
    $b = explode("\n", $s);
    # determine last line of the block
    for ($i = 0,$j = count($a) - 1; ~$j; --$j)
    {
      # stop at pad or end-of-block
      if (strlen($c = &$a[$j]) &&
          ctype_space($c[0]) === false &&
          strpos($c, '└') !== 0)
      {
        $i = 1;
        break;
      }
      # pad bottom lines
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
    # compose
    for ($i = 0; $i < $j; ++$i)
    {
      # select separator
      if (strlen($c = &$a[$i]) &&
          ctype_space($a[$i][0]) === false &&
          strpos($a[$i], '└') !== 0)
      {
        $k = 0;
      }
      else {
        $k = 3;
      }
      $i && $k++;
      $b[$i] = $x[$k].$b[$i];
    }
    $b[$j] = $x[2].$b[$j];
    return implode("\n", $b);
  }
  # }}}
  static function op(# {{{
    int $level, array &$msg, bool $out = false
  ):string
  {
    # compose element
    $a = '';
    $c = self::COLOR[$level];
    $s = self::SEP[$out ? 0 : 1];
    switch($i = count($msg)) {
    case 0:
      break;
    case 1:
      # single message
      $a .= $msg[0];
      break;
    case 2:
      if ($out)
      {
        # operation and message
        $a .= self::fgColor($msg[0], $c, 1);
        $a .= self::fgColor($s, $c);
        $a .= self::opBlock($msg[1], $level);
      }
      else
      {
        # path and operation
        $a .= self::fgColor($msg[0], $c);
        $a .= self::fgColor(self::SEP[3], $c);
        $a .= self::fgColor($msg[1], $c, 1);
      }
      break;
    default:
      # path, operation and message
      $b = implode(
        self::SEP[3], array_slice($msg, 0, -2)
      );
      $a .= self::fgColor($b, $c);
      if (($b = $msg[$i - 2]) !== '')
      {
        $a .= self::fgColor(self::SEP[3], $c);
        $a .= self::fgColor($b, $c, 1);
      }
      if (($b = $msg[$i - 1]) !== '')
      {
        $a .= self::fgColor($s, $c);
        $a .= self::opBlock($b, $level);
      }
      break;
    }
    return $a;
  }
  # }}}
  static function opBlock(# {{{
    string $s, int $level
  ):string
  {
    # find next break
    if (($i = strpos($s, "\n")) !== false)
    {
      $s = (
        substr($s, 0, $i)."\n".
        self::block(
          substr($s, $i + 1),
          self::COLOR[$level], 0
        )
      );
    }
    return $s;
  }
  # }}}
  static function composeObject(# {{{
    object $o, int $depth = 0
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
        $b = "\n".self::composeObject($v, $depth + 1);
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
    return $a ? self::block($a)."\n" : '';
  }
  # }}}
  static function composeTree(# {{{
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
        $x .= self::composeTree(
          $node->children, $pad, $color, $indent, $level + 1
        );
        array_pop($indent);
      }
    }
    return $x;
  }
  # }}}
  static function trace(# {{{
    object $e, int $from = 0
  ):string
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
  static function &parseException(object $e): array # {{{
  {
    static $PATH = __DIR__.DIRECTORY_SEPARATOR;
    if (ErrorEx::is($e))
    {
      # compose message
      $x = $e->msg;
      array_unshift($x, '✶');
      # add reduced trace
      $a = self::trace($e, 1);
      $a = str_replace($PATH, '', $a);
      $x[count($x) - 1] .= "\n".$a;
    }
    else
    {
      # compose message and trace
      $a = $e->getMessage()."\n".
        $e->getFile().'('.$e->getLine().")\n".
        self::trace($e);
      # truncate paths and complete
      $a = str_replace($PATH, '', $a);
      $x = ['✶', get_class($e), $a];
    }
    return $x;
  }
  # }}}
  static function errors(object $e): string # {{{
  {
    $a = '';
    while ($e)
    {
      $b = trim(self::op(
        $e->level, $e->msg, true
      ));
      if ($b) {
        $a .= $b."\n";
      }
      $e = $e->next;
    }
    return $a;
  }
  # }}}
  static function separator(# {{{
    int $level, int $sep = 0
  ):string
  {
    $i = ($level === 1) ? 1 : 0;
    return self::fgColor(
      self::SEP[$sep], self::COLOR[$level], $i
    );
  }
  # }}}
  static function throwable(Throwable $e): string # {{{
  {
    return
      '## '.$e->getFile().'('.$e->getLine().'): '.
      $e->getMessage()."\n".
      $e->getTraceAsString()."\n";
  }
  # }}}
  static function resultObject(# {{{
    object $r, int $depth
  ):string
  {
    $a = '';
    foreach ($r->track as $b) {
      $a .= self::resultTrack($b, $depth)."\n";
    }
    return $a;
  }
  # }}}
  static function resultTrack(# {{{
    object $t, int $depth
  ):string
  {
    # prepare
    $d = $t->ok ? 0 : 2;
    $c = self::COLOR[$d];
    $a = self::fgColor(self::SEP[2], $c, 1);
    $b = '';
    # compose title
    $a = $t->msg
      ? $a.self::op($d, $t->msg)
      : ($depth
        ? $a.self::fgColor(' ', $c)
        : '');
    # compose errors
    if ($t->error) {
      $b = self::errors($t->error);
    }
    # compose results group
    if ($t->group)
    {
      foreach ($t->group as $e) {
        $b .= self::resultObject($e, 1 + $depth);
      }
    }
    # complete
    return $a
      ? ($b
        ? $a."\n".self::block($b, $c, 0)
        : $a)
      : $b;
  }
  # }}}
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
  function new(string $name): self # {{{
  {
    return ($name && $name !== $this->name)
      ? new self($this->bot, $name, $this)
      : $this;
  }
  # }}}
  function &path(): array # {{{
  {
    $log  = $this;
    $path = [$log->name];
    while ($log->parent)
    {
      $log = $log->parent;
      array_unshift($path, $log->name);
    }
    return $path;
  }
  # }}}
  function print(# {{{
    int $level, int $sep, array &$msg
  ):void
  {
    # filter empty messages
    for ($i = 0, $j = count($msg); $i < $j; ++$i)
    {
      if ($msg[$i] === '') {
        array_splice($msg, $i--, 1); $j--;
      }
    }
    # compose message
    $a = $this->message(
      $level, self::separator($level, $sep), $msg
    );
    # output
    $this->bot->console->write($a."\n");
  }
  # }}}
  function printObject(# {{{
    object $o, int $level, array &$msg
  ):void
  {
    $this->bot->console->write(
      $this->message(
        $level, self::separator($level, 0), $msg
      )."\n".
      self::composeObject($o)
    );
  }
  # }}}
  function message(# {{{
    int $level, string $sep, array &$msg
  ):string
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
      for ($i = 0, $j = count($msg) - 1; $i < $j; ++$i)
      {
        $text = $text.$sep.
          self::fgColor($msg[$i], $color, 1);
      }
      $text = (~$j && ($a = rtrim($msg[$j])))
        ? (($a[0] === "\n") ? $text.$a : $text.$sep.$a)
        : $text.$sep;
    }
    # check multiline
    if (($n = strpos($text, "\n")) > 0)
    {
      $text = (
        substr($text, 0, ++$n).
        self::block(substr($text, $n))
      );
      $prompt = self::SEP[6];# block
    }
    else {
      $prompt = self::SEP[5];# line
    }
    # complete
    return
      self::fgColor($prompt, self::COLOR[3], 1).
      self::fgColor($p->name, self::COLOR[3], 0).$text;
  }
  # }}}
  function prompt(string ...$msg): void # {{{
  {
    array_push($msg, '');
    $s = self::separator(0, 1);
    $this->bot->console->write(
      $this->message(0, $s, $msg)
    );
  }
  # }}}
  function banner(): void # {{{
  {
    $a = <<<EOD
███████████████████████████████████████ Process control:
█─▄▄▄▄█▄─▀█▀─▄█▀▀▀▀▀██▄─▄─▀█─▄▄─█─▄─▄─█ [[1mq[0m][[1mCtrl+C[0m] ~ quit, keep bots running
█▄▄▄▄─██─█▄█─██████████─▄─▀█─██─███─███ [[1mx[0m][[1mCtrl+Break[0m] ~ quit, stop bots
▀▄▄▄▄▄▀▄▄▄▀▄▄▄▀▀▀▀▀▀▀▀▄▄▄▄▀▀▄▄▄▄▀▀▄▄▄▀▀ [[1mr[0m] ~ restart

EOD;
    $a = self::fgColor($a, self::COLOR[3]);
    $this->bot->console->write($a);
  }
  # }}}
  function commands(array &$tree, string $src): void # {{{
  {
    $this->info($src, "\n".self::composeTree(
      $tree, 0, self::COLOR[3]
    ));
  }
  # }}}
  # info/warn/error {{{
  function info(string ...$msg): void {
    $this->print(0, 0, $msg);
  }
  function infoInput(string ...$msg): void {
    $this->print(0, 1, $msg);
  }
  function infoObject(object $o, string ...$msg): void {
    $this->printObject($o, 0, $msg);
  }
  function warn(string ...$msg): void {
    $this->print(1, 0, $msg);
  }
  function warnInput(string ...$msg): void {
    $this->print(1, 1, $msg);
  }
  function warnObject(object $o, string ...$msg): void {
    $this->printObject($o, 2, $msg);
  }
  function error(string ...$msg): void
  {
    $this->errorCount++;
    $this->print(2, 0, $msg);
  }
  function errorInput(string ...$msg): void
  {
    $this->errorCount++;
    $this->print(2, 1, $msg);
  }
  function errorObject(object $o, string ...$msg): void
  {
    $this->errorCount++;
    $this->printObject($o, 1, $msg);
  }
  # }}}
  function exception(object $e): bool # {{{
  {
    if (ErrorEx::is($e)) {
      return $this->exceptionEx($e);
    }
    $this->errorCount++;
    $this->print(2, 0, self::parseException($e));
    return true;
  }
  # }}}
  function exceptionEx(object $e): bool # {{{
  {
    if ($e->isFatal())
    {
      $this->errorCount++;
      $this->print(2, 0, self::parseException(
        $e->value ?? $e
      ));
    }
    else
    {
      $e->isError() && $this->errorCount++;
      $this->print($e->level, 0, $e->msg);
    }
    $e->next && $this->exceptionEx($e->next);
    return $e->hasError();
  }
  # }}}
  function result(object $r, string ...$msg): bool # {{{
  {
    # compose
    $i = $r->ok ? 0 : 2;
    $a = $this->message(
      $i, self::separator($i, 0), $msg
    );
    if ($b = self::resultObject($r, 0)) {
      $a .= "\n".self::block($b);
    }
    # complete
    $r->ok && $this->errorCount++;
    $this->bot->console->write($a."\n");
    return $r->ok;
  }
  # }}}
  function finit(): void # {{{
  {}
  # }}}
}
# }}}
# config {{{
class BotConfig
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
  # static {{{
  static function checkToken(string $token): string
  {
    return preg_match(self::EXP_TOKEN, $token)
      ? self::getId($token) : '';
  }
  static function getId(string $token): string {
    return substr($token, 0, strpos($token, ':'));
  }
  static function getSrcDir(): string {
    return __DIR__.DIRECTORY_SEPARATOR.self::DIR_SRC.DIRECTORY_SEPARATOR;
  }
  static function getIncDir(): string {
    return __DIR__.DIRECTORY_SEPARATOR.self::DIR_INC.DIRECTORY_SEPARATOR;
  }
  static function getDataDir(array &$o): string
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
      'Bot'             => [
        'id'            => $id ?: self::getId($o['token']),
        'source'        => $o['source'] ?? 'master',
        'token'         => $o['token'],
        'url'           => $o['url'] ?? 'https://api.telegram.org/bot',
        'polling'       => true,# hook otherwise
        'admins'        => [],
        'name'          => '',
        'canJoinGroups' => false,
        'canReadGroups' => false,
        'isInline'      => false,
      ],
      'BotFile'   => [
        'timeout' => 5*60*1000,# unload timeout (ms)
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
      'BotEventCallback' => [
        'replyFast'      => true,# reply before rendering
        'replyInvalid'   => true,# incorrect data
      ],
      'BotEventInput' => [
        'wipeInput'   => true,
      ],
      'BotEventCommand' => [
        #'cooldown'      => 2000,
        #'wipe'          => true,
      ],
      'BotImgMessage' => [
        'blank' => [# placeholder background
          [640,160],# size: width,height
          [0,0,48],# color: R,G,B
        ],
      ],
    ], 1);
  }
  # }}}
  function init(): bool # {{{
  {
    # set data directory
    $bot = $this->bot;
    $this->dirData = $a = $this->dirDataRoot.
      $bot['id'].DIRECTORY_SEPARATOR;
    ###
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
    $this->dirSrc = $this->dirSrcRoot.
      $bot['source'].DIRECTORY_SEPARATOR;
    ###
    return true;
  }
  # }}}
  function path(string $id = ''): string # {{{
  {
    if ($id === '') {
      $id = $this->bot->id;
    }
    return $this->dirDataRoot.$id.
      DIRECTORY_SEPARATOR.self::FILE_BOT;
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
abstract class BotConfigAccess
  implements ArrayAccess
{
  public $config;
  final function offsetExists(mixed $k): bool
  {
    if (($o = $this->config) === null)
    {
      $this->config = $o =
        $this->bot->cfg->data[class_name($this)];
    }
    return isset($o[$k]);
  }
  final function offsetGet(mixed $k): mixed
  {
    return $this->offsetExists($k)
      ? $this->config[$k]
      : null;
  }
  final function offsetSet(mixed $k, mixed $v): void
  {
    if ($this->offsetExists($k)) {
      $this->config[$k] = $v;
    }
  }
  final function offsetUnset(mixed $k): void
  {}
}
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
    $log,$fid,$img = [],$fnt = [],$dataMap = [];
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
    return 'file='.$path.' id='.$id;
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
    if (isset($this->dataMap[$path])) {
      return $this->dataMap[$path];
    }
    # construct new instance
    $o = new BotFileData($this->log, $path, $depth);
    return $o->timeTouch()->load()
      ? ($this->dataMap[$path] = $o)
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
        throw ErrorEx::fail('mkdir', $path);
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
  function sync(): self # {{{
  {
    # synchronize file identifiers
    $this->fid->sync();
    # synchronize data objects
    $t = hrtime(true);
    $x = $this['timeout'];
    $s = '';
    $c = 0;
    $d = count($this->dataMap);
    foreach ($this->dataMap as $path => $data)
    {
      # store data
      if (($a = $data->sync()) < 0) {
        continue;
      }
      $b = $a ? '[save]' : '';
      # check timeout
      if ($data->timeDelta($t) >= $x)
      {
        unset($this->dataMap[$path]);
        $b = $b.'[unload]';
      }
      # accumulate logs
      if ($b)
      {
        $s = $s."\n".$b.': '.$path.' ('.$data->size.')';
        $c = $c + 1;
      }
    }
    # report results and complete
    $c && $this->log->info('sync', $c.' of '.$d.$s);
    return $this;
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
  use TimeTouchable;
  public $node,$size = 0;
  function __construct(# {{{
    public object $log,
    public string $path,
    public int    $limit = 0,
  ) {}
  # }}}
  function __debugInfo(): array # {{{
  {
    return $this->node->__debugInfo();
  }
  # }}}
  function load(): bool # {{{
  {
    try
    {
      if (!file_exists($path = $this->path))
      {
        $a = [];
        $b = 0;
      }
      elseif (($a = file_get_contents($path)) === false) {
        throw ErrorEx::fail($path);
      }
      else
      {
        $b = strlen($a);
        $a = json_decode(
          $a, true, 128, JSON_INVALID_UTF8_IGNORE
        );
        if ($a === null) {
          throw ErrorEx::fail($path."\n".json_error());
        }
        if (!is_array($a)) {
          throw ErrorEx::fail($path."\ninvalid content type");
        }
      }
      $this->node = new ArrayNode($a, $this->limit);
      $this->size = $b;
    }
    catch (Throwable $e)
    {
      $this->log->exception($e);
      return false;
    }
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
  function set(array $a): self # {{{
  {
    $this->node->setRef($a);
    return $this;
  }
  # }}}
  function sync(): int # {{{
  {
    if (!$this->node->changed) {
      return 0;
    }
    try
    {
      if (($a = json_encode($this->node, JSON_UNESCAPED_UNICODE)) === false) {
        throw ErrorEx::fail($this->path."\n".json_error());
      }
      if (($b = file_put_contents($this->path, $a)) === false) {
        throw ErrorEx::fail($this->path);
      }
      $this->size = $b;
    }
    catch (Throwable $e)
    {
      $this->log->exception($e);
      return -1;
    }
    return 1;
  }
  # }}}
}
# }}}
# api {{{
class BotApi # {{{
{
  # {{{
  const LOGFILE = 'curl.log';
  public static $CONFIG = [
    CURLOPT_VERBOSE        => false,# for debugging
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,# default(0): 300
    CURLOPT_TIMEOUT        => 0,# default(0): never
    CURLOPT_FORBID_REUSE   => false,# do reuse
    CURLOPT_FRESH_CONNECT  => false,# do reuse
    CURLOPT_FOLLOWLOCATION => false,# no redirects
    CURLOPT_PROTOCOLS      => (# limit protocols
      CURLPROTO_HTTP|CURLPROTO_HTTPS
    ),
    ###
    CURLOPT_TCP_NODELAY    => true,
    CURLOPT_TCP_KEEPALIVE  => 1,
    CURLOPT_TCP_KEEPIDLE   => 300,
    CURLOPT_TCP_KEEPINTVL  => 300,
    #CURLOPT_TCP_FASTOPEN   => true,
    ###
    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2_PRIOR_KNOWLEDGE,
    #CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
    #CURLMOPT_PIPELINING    => 1,
    CURLOPT_PIPEWAIT       => true,# be lazy, multiplexy
    CURLOPT_NOSIGNAL       => false,# allowed, im not multi-threaded
    ###
    CURLOPT_SSL_ENABLE_ALPN  => true,# negotiate to h2
    #CURLOPT_SSL_ENABLE_NPN   => false,# eh.. disabled?
    CURLOPT_SSL_VERIFYSTATUS => false,# require OCSP during the TLS handshake?
    CURLOPT_SSL_VERIFYHOST   => 0,# are you afraid of MITM?
    CURLOPT_SSL_VERIFYPEER   => false,# disallow self-signed certs?
    #CURLOPT_PINNEDPUBLICKEY  => '',
  ];
  public $log,$curl,$logfile,$actions,$reciever;
  # }}}
  static function cError(object $curl): string # {{{
  {
    return ($e = curl_errno($curl))
      ? "($e) ".curl_error($curl)
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
  static function oError(object $o): string # {{{
  {
    $a = strval($o->error_code ?? '');
    $b = strval($o->description ?? '');
    return ($a === '')
      ? $b : trim('('.$a.') '.$b);
  }
  # }}}
  static function formenc(array &$q): string # {{{
  {
    return http_build_query(
      $q, '', null, PHP_QUERY_RFC3986
    );
  }
  # }}}
  static function setopt(# {{{
    object $curl, ?array &$req = null
  ):?object
  {
    if (curl_setopt_array($curl, $req ?? self::$CONFIG)) {
      return null;
    }
    return ErrorEx::fail('curl_setopt_array',
      "fail\n".self::cError($curl)
    );
  }
  # }}}
  static function curl(): object # {{{
  {
    if (($curl = curl_init()) === false) {
      return ErrorEx::fail('curl_init', 'fail');
    }
    if ($e = self::setopt($curl))
    {
      curl_close($curl);
      return $e;
    }
    return $curl;
  }
  # }}}
  static function decode(string &$s): object # {{{
  {
    # decode to object
    $o = json_decode(
      $s, false, 16, JSON_INVALID_UTF8_IGNORE
    );
    if ($o === null)
    {
      return ErrorEx::fail(
        "incorrect response\n".
        str_replace("\n", '\n', $s)
      )->addFail('json_decode',
        "fail\n".json_error()
      );
    }
    # check proper type
    if (!is_object($o) || !isset($o->ok))
    {
      return ErrorEx::fail(
        "incorrect response type\n".
        str_replace("\n", '\n', $s)
      );
    }
    # check failure
    if (!$o->ok) {
      return $o;
    }
    # check has a result
    if (!isset($o->result))
    {
      return ErrorEx::fail(
        "incorrect response (no result)\n".
        str_replace("\n", '\n', $s)
      );
    }
    return $o;
  }
  # }}}
  function __construct(public object $bot) # {{{
  {
    # debugging?
    if (self::$CONFIG[CURLOPT_VERBOSE] &&
        !isset(self::$CONFIG[CURLOPT_STDERR]))
    {
      self::$CONFIG[CURLOPT_STDERR] = fopen(
        $bot->cfg->dirDataRoot.self::LOGFILE, 'a'
      );
    }
    # create easy handle
    if (ErrorEx::is($curl = self::curl())) {
      throw $curl;
    }
    # initialize
    $this->curl = $curl;
    $this->log  = $bot->log->new('api');
  }
  # }}}
  function init(): bool # {{{
  {
    $this->actions  = $a = new BotApiActions($this);
    $this->reciever = $b = $this->bot['polling']
      ? new BotApiPolling($this)
      : new BotApiHook($this);
    return $a->init() && $b->init();
  }
  # }}}
  function url(# {{{
    string &$method, string &$token
  ):string
  {
    if ($token === '') {
      $token = $this->bot['token'];
    }
    return $this->bot['url'].$token.'/'.$method;
  }
  # }}}
  function aJson(# {{{
    string &$method, array &$q, string &$token = ''
  ):array
  {
    static $JSONHEAD = [
      'Content-Type: application/json'
    ];
    return [
      CURLOPT_URL  => $this->url($method, $token),
      CURLOPT_POST => true,
      CURLOPT_HTTPHEADER => $JSONHEAD,
      CURLOPT_POSTFIELDS => json_encode(
        $q, JSON_UNESCAPED_UNICODE
      )
    ];
  }
  # }}}
  function aFormData(# {{{
    string &$method, array &$q, string &$token = ''
  ):array
  {
    return [# multipart/form-data
      CURLOPT_URL  => $this->url($method, $token),
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $q
    ];
  }
  # }}}
  function aFormEnc(# {{{
    string &$method, array &$q, string &$token = ''
  ):array
  {
    return [# application/x-www-form-urlencoded
      CURLOPT_URL  => $this->url($method, $token),
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => http_build_query(
        $q, '', null, PHP_QUERY_RFC3986
      )
    ];
  }
  # }}}
  function send(# {{{
    string  $method,
    array   $req,
    ?object $file  = null,
    string  $token = ''
  ):mixed
  {
    static $FILE = [
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
      # check file attachment
      if ($file) {
        $req[$file->postname] = $file;# put
      }
      elseif (isset($FILE[$method]) &&
              isset($req[$a = $FILE[$method]]) &&
              $req[$a] instanceof BotApiFile)
      {
        $file = $req[$a];# get
      }
      # transform query into request
      $req = $file
        ? $this->aFormData($method, $req, $token)
        : $this->aJson($method, $req, $token);
      # setup
      $curl = $this->curl;
      if ($e = self::setopt($curl, $req)) {
        throw $e;
      }
      # execute
      if (($a = curl_exec($curl)) === false)
      {
        throw ErrorEx::fail('curl_exec',
          "fail\n".self::cError($curl)
        );
      }
      # decode and check the response
      if (ErrorEx::is($e = self::decode($a))) {
        throw $e;
      }
      if (!$e->ok)
      {
        throw ErrorEx::fail(
          "unsuccessful response\n".
          (self::oError($e) ?: $a)
        );
      }
      $res = $e->result;
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
    ?object $file,
    array   $req,
    array   $opt = []
  ):object
  {
    return new Promise(new BotApiAction(
      $this->actions, $file, $req, $opt, $method
    ));
  }
  # }}}
  function getUpdates(array $q): object # {{{
  {
    static $m = __FUNCTION__;
    static $o = [
      'offset'  => 0,
      'limit'   => 100,# results limit, max=100
      'timeout' => 50,# experimental max=50
    ];
    array_import_new($q, $o);
    return $this
      ->promise($m, null, $q, [
        'fails'   => 1,
        'retries' => 1,
        'report'  => true,
        'timeout' => (int)(($q['timeout'] + 1) * 1000),
      ])
      ->then(new BotApiConfirm($m));
  }
  # }}}
  function sendPhoto(array $req): object # {{{
  {
    static $m = __FUNCTION__;
    static $k = 'photo';
    return $this
      ->promise($m, BotApiFile::of($req[$k]), $req)
      ->then(new BotApiConfirm($m));
  }
  # }}}
  function editMessageMedia(array $req): object # {{{
  {
    # prepare
    static $m = __FUNCTION__;
    static $k = 'media';
    # set attachment and encode
    if ($file = BotApiFile::of($req[$k][$k]))
    {
      $req[$k][$k] = 'attach://'.$file->postname;
      $req[$file->postname] = $file;
    }
    $req[$k] = json_encode($req[$k]);
    # compose
    return $this
      ->promise($m, $file, $req)
      ->then(new BotApiConfirm($m));
  }
  # }}}
  function deleteMessage(array $req): object # {{{
  {
    static $m = __FUNCTION__;
    return $this
      ->promise($m, null, $req)
      ->then(new BotApiConfirm($m));
  }
  # }}}
  function answerCallbackQuery(array $q): object # {{{
  {
    static $m = __FUNCTION__;
    return $this
      ->promise($m, null, $q)
      ->then(new BotApiConfirm($m));
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
    curl_close($this->curl);
  }
  # }}}
}
# }}}
class BotApiConfirm extends PromiseAction # {{{
{
  const ERR = [
    'incorrect result type (%s)',
    'unsuccessful result (%s)',
  ];
  function __construct(public string $method)
  {}
  function stop(): ?object
  {
    $this->check($r = $this->result);
    $r->confirm('api', $this->method);
    return null;
  }
  function check(object $r): bool # {{{
  {
    return $r->ok
      ? $this->checkTrue($r)
      : false;
  }
  # }}}
  function checkTrue(object $r): bool # {{{
  {
    switch ($this->method) {
    case 'sendPhoto':
    case 'editMessageMedia':
      $this->isType('object');
      break;
    case 'deleteMessage':
    case 'answerCallbackQuery':
      $this->isType('boolean') &&
      $this->isEqual(true);
      break;
    case 'getUpdates':
      $this->isType('array');
      break;
    }
    return $r->ok;
  }
  # }}}
  function isType(string $name): bool # {{{
  {
    $r = $this->result;
    if (($type = gettype($r->value)) === $name) {
      return true;
    }
    $r->failure()->message(sprintf(
      self::ERR[0], $type
    ));
    return false;
  }
  # }}}
  function isEqual(mixed $value): bool # {{{
  {
    if (($r = $this->result)->value === $value) {
      return true;
    }
    $r->failure()->message(sprintf(
      self::ERR[1], $value
    ));
    return false;
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
class BotApiActions extends BotApiExt # {{{
{
  const LOGNAME = 'actions';
  public $murl,$gen,$acts = [],$ms = 0;
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
  function murlAdd(object $curl): ?object # {{{
  {
    try
    {
      if ($a = curl_multi_add_handle($this->murl, $curl))
      {
        throw ErrorEx::fail('curl_multi_add_handle',
          "fail\n".curl_multi_strerror($a)
        );
      }
    }
    catch (Throwable $e) {
      return ErrorEx::from($e);
    }
    return null;
  }
  # }}}
  function murlRemove(object $curl): ?object # {{{
  {
    try
    {
      if ($a = curl_multi_remove_handle($this->murl, $curl))
      {
        throw ErrorEx::fail('curl_multi_remove_handle',
          "fail\n".curl_multi_strerror($a)
        );
      }
    }
    catch (Throwable $e) {
      return ErrorEx::from($e);
    }
    return null;
  }
  # }}}
  function start(): Generator # {{{
  {
    try
    {
      # prepare
      $murl  = $this->murl;
      $acts  = &$this->acts;
      $count = $running = count($acts);
      $api   = $this->api;
      # operate
      while (1)
      {
        # execute requests
        if ($a = curl_multi_exec($murl, $running))
        {
          throw ErrorEx::fail('curl_multi_exec',
            "fail\n".curl_multi_strerror($a)
          );
        }
        # check any transfers ready
        if ($running !== $count) {
          break;
        }
        # wait for activity
        $d = 0;
        while (!$a)
        {
          # probe
          if (($a = curl_multi_select($murl, 0)) < 0)
          {
            throw ErrorEx::fail('curl_multi_select',
              "fail\n".$api::mError($murl)
            );
          }
          # retry
          if (++$d < 4)
          {
            sleep(0);
            continue;
          }
          # postpone until continuation
          if (!yield) {
            throw ErrorEx::skip();
          }
          # new handles may be added after yielding,
          # check and update counter
          if (($c = count($acts)) !== $count)
          {
            $count = $c;
            break;
          }
          $d = 0;
        }
      }
      # check transfers
      while ($c = curl_multi_info_read($murl))
      {
        # find completed
        for ($i = 0; $i < $count; ++$i)
        {
          $a = $acts[$i];
          if ($a->curl === $c['handle'] &&
              $a->set($c['result']))
          {
            array_splice($acts, $i, 1);
            break;
          }
        }
        $count--;
      }
      # the number of transfers must match,
      # otherwise, there is an error
      if ($running !== $count)
      {
        throw ErrorEx::fail('curl_multi_info_read',
          "fail\n".$api::mError($murl)
        );
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
  function spin(): bool # {{{
  {
    # check generator
    if (($gen = $this->gen) && $gen->valid()) {
      $gen->send(1);# continue
    }
    else {# start or restart
      $this->gen = $gen = $this->start();
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
  function stop(): void # {{{
  {
    if ($this->gen?->valid()) {
      $this->gen->send(0);# stop
    }
    foreach ($this->acts as $a) {
      $a->stop();
    }
    $this->gen  = null;
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
  use TimeTouchable;
  public $curl,$log,$state = 1,$fails = 0;
  function __construct(# {{{
    public object  $base,
    public ?object $file,
    public array   &$req,
    public array   &$opt,
    string &$method
  ) {
    static $DEFS = [# defaults
      'fails'   => 2,# fails before retry (-1=disallowed)
      'retries' => -1,# retries before fatal (-1=unlimited)
      'pause'   => 2000,# first retry pause (msec)
      'backoff' => 6,# max retries in pause^retry
      'report'  => false,# output retry warnings
      'timeout' => 0,# transfer timeout
    ];
    # set defaults
    array_import_new($opt, $DEFS);
    # transform query into request
    $api = $base->api;
    $req = $file
      ? $api->aFormData($method, $req)
      : $api->aJson($method, $req);
    # set transfer timeout
    $req[CURLOPT_TIMEOUT_MS] = $opt['timeout'];
    # set logger
    if ($opt['report']) {
      $this->log = $api->log->new($method);
    }
  }
  # }}}
  function start(): bool # {{{
  {
    if ($this->attach())
    {
      $this->base->acts[] = $this;
      return true;
    }
    return false;
  }
  # }}}
  function attach(): bool # {{{
  {
    try
    {
      # prepare
      $curl = null;
      $base = $this->base;
      $api  = $base->api;
      # create cURL instance
      if (ErrorEx::is($curl = $api::curl())) {
        throw $curl;
      }
      # initialize and add to multi-handle
      if (($e = $api::setopt($curl, $this->req)) ||
          ($e = $base->murlAdd($curl)))
      {
        throw $e;
      }
      # success
      $this->curl  = $curl;
      $this->state = 0;
    }
    catch (Throwable $e)
    {
      $curl && curl_close($curl);
      $this->file?->destruct();
      $this->result->failure($e);
      $this->log?->exception($e);
      return false;
    }
    return true;
  }
  # }}}
  function spin(): bool # {{{
  {
    # check current state
    if ($this->state === 0) {# non-finished
      return $this->base->spin();
    }
    if ($this->state === 1) {# finished
      return false;
    }
    # check pending retry
    return ($this->timeDelta() > 0)
      ? $this->start()
      : true;
  }
  # }}}
  function set(int $x): bool # {{{
  {
    try
    {
      # check failed
      if ($x) {
        throw ErrorEx::warn(curl_strerror($x));
      }
      # prepare
      $api  = $this->base->api;
      $curl = $this->curl;
      # get response and HTTP status code
      $v = curl_multi_getcontent($curl);
      $x = curl_getinfo(
        $curl, CURLINFO_RESPONSE_CODE
      );
      # check
      if ($x === false)
      {
        throw ErrorEx::fail('curl_getinfo',
          "fail\n".$api::cError($curl)
        );
      }
      if ($x !== 200)
      {
        $a = '('.$x.') unsuccessful HTTP status';
        if ($v !== null)
        {
          $b = $api::oError($api::decode($v));
          $a = $a."\n".($b ?: $v);
        }
        if ($x >= 500) {# not my fault
          throw ErrorEx::warn($a);
        }
        else {
          throw ErrorEx::fail($a);
        }
      }
      # obtain the result
      if ($v !== null)
      {
        if (ErrorEx::is($v = $api::decode($v))) {
          throw $v;
        }
        $v = $v->result;
      }
    }
    catch (Throwable $e)
    {
      $this->log?->exception($e);
      return $this->setFail($e);
    }
    # success
    $this->result->success($v);
    return $this->detach();
  }
  # }}}
  function setFail(object $e): bool # {{{
  {
    # handle critical
    if (($e = ErrorEx::from($e))->isError() ||
        ($i = $this->opt['fails']) < 0)
    {
      $this->result->failure($e);
      return $this->detach();
    }
    # handle recoverable
    # try restart
    if (++$this->fails <= $i) {
      return $this->restart();
    }
    # try retry
    if (($j = $this->opt['retries']) === 0)
    {
      $this->result->failure($e)->message(
        $a = 'too many failures ('.$i.')'
      );
      $this->log?->error($a);
      return $this->detach();
    }
    if ($j > 0 && $this->fails - $i > $j)
    {
      $this->result->failure($e)->message(
        $a = 'retry limit exceeded ('.$j.')'
      );
      $this->log?->error($a);
      return $this->detach();
    }
    return $this->retry();
  }
  # }}}
  function restart(): bool # {{{
  {
    return ($this->detach() && $this->attach())
      ? false # ok, keep spinning
      : true; # fail, stop
  }
  # }}}
  function retry(): bool # {{{
  {
    $o = &$this->opt;
    $a = $this->fails - $o['fails'];
    $b = min($a, $o['backoff']);
    $c = (int)($o['pause'] / 1000);
    $c = (int)(pow($c, $b) * 1000);
    $this->log?->info(
      'retry in '.$c.'ms'.
      (($d = $o['retries']) > 0
        ? ' ('.($d - $a).' retries left)'
        : ''
      )
    );
    return $this
      ->timeTouch()
      ->timeAdd($c)
      ->detach(-1);
  }
  # }}}
  function detach(int $state = 1): bool # {{{
  {
    if ($curl = $this->curl)
    {
      if ($e = $this->base->murlRemove($curl)) {
        $this->log?->exception($e);
      }
      curl_close($curl);
      $this->curl  = null;
      $this->state = $state;
    }
    return true;
  }
  # }}}
  function stop(): ?object # {{{
  {
    if ($this->state === 0)
    {
      $this->result->failure();
      $this->detach();
    }
    if ($this->file)
    {
      $this->file->destruct();
      $this->file = null;
    }
    return null;
  }
  # }}}
}
# }}}
class BotApiPolling extends BotApiExt # {{{
{
  const LOGNAME = 'polling';
  public $req,$offset = 0;
  function init(): bool # {{{
  {
    $this->req = $this->api->getUpdates([
      'offset' => $this->offset,
    ]);
    return true;
  }
  # }}}
  function &get(): array # {{{
  {
    static $NONE = [];
    # probe
    if (!($x = $this->req->complete())) {
      return $NONE;
    }
    # check
    if (!$x->ok)
    {
      $this->log->result($x, 'getUpdates');
      throw ErrorEx::skip();
    }
    # update the offset
    if (($i = count($a = $x->value) - 1) >= 0) {
      $this->offset = 1 + $a[$i]->update_id;
    }
    # recharge and complete
    $this->init();
    return $a;
  }
  # }}}
  function finit(): void # {{{
  {
    # cancel current request
    $this->req->cancel();
    # gracefully terminate polling
    if ($a = $this->offset)
    {
      # to confirm handled updates
      # set simplified query parameters
      $this->api->send('getUpdates', [
        'offset'  => $a,
        'limit'   => 1,
        'timeout' => 0,
      ]);
    }
  }
  # }}}
}
# }}}
class BotApiHook extends BotApiExt # {{{
{
  const LOGNAME = 'hook';
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
  static function of(&$file): ?self {
    return ($file instanceof self) ? $file : null;
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
# events {{{
class BotEvents
{
  public
    $log,$queue = [];
  function __construct(public object $bot) {
    $this->log = $bot->log->new('event');
  }
  function load(object $update): bool # {{{
  {
    # construct event
    $e = BotEvent::fromUpdate(
      $this->bot, $update
    );
    # check
    if (ErrorEx::is($e))
    {
      $this->log->warnObject(
        $update, $e->getMsg('unknown')
      );
      return false;
    }
    # construct response
    if (($o = $e->response()) === null) {
      return false;
    }
    # stash
    if (isset($this->queue[$k = $e->id])) {
      $this->queue[$k]->then($o);
    }
    elseif ($k === '') {
      $this->queue[$k] = new PromiseAll($o);
    }
    else {
      $this->queue[$k] = new PromiseOne($o);
    }
    return true;
  }
  # }}}
  function dispatch(): int # {{{
  {
    if (($c = count($this->queue)) === 0) {
      return 0;
    }
    foreach ($this->queue as $k => $p)
    {
      if ($p->complete()) {
        unset($this->queue[$k]);
      }
    }
    return $c;
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
      'NNBSP'   => "\xE2\x80\xAF",# narrow nbsp
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
      ? $lang : self::DEF_LANG;
  }
  # }}}
  function get(string $k, string $lang = ''): string # {{{
  {
    return $this->texts[$lang ?: self::DEF_LANG][$k] ?? '';
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
    EXP_PATH       = '|^(\/[a-z0-9_]+){1,16}$|i',
    EXP_COMMAND    = '|^'.
      '((\/[a-z0-9_]+){1,16})'. # path
      '(@([a-z_]+bot)){0,1}'.   # botname
      '$|i',
    EXP_DEEPLINK   = '|^(\/start) ([-a-z0-9_]+){1}$|i',
    EXP_Q_SELF     = '|^'.
      '(())'.
      '(!([a-z]+)){1}'.
      '([ \n](.*)){0,1}'.
      '$|is',
    EXP_Q_CHILD    = '|^'.
      '([a-z0-9_]+(\/[a-z0-9_]+){0,15})'.
      '(!([a-z]+)){0,1}'.
      '([ \n](.*)){0,1}'.
      '$|is',
    EXP_Q_COMMAND  = '|^'.
      '((\/[a-z0-9_]+){1,16})'.
      '(!([a-z]+)){0,1}'.
      '([ \n](.*)){0,1}'.
      '$|is',
    EXP_CALLBACK   = '|^'.
      '([0-9a-f]{16}){1}'.    # item id
      '(:([0-9]{1,2})){1}'.   # tick (state id)
      '(!([a-z]+)){0,1}'.     # func
      '([ \n](.*)){0,1}'.     # argument
      '$|is',
    TYPE_NAMESPACE = '\\'.__NAMESPACE__.'\\',
    TYPE_DEFAULT   = 'Img';
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
      $items = require $dir.self::FILE_SCHEMA;
      $hands = require $dir.self::FILE_HANDLERS;
      # create items
      foreach ($items as $path => &$item)
      {
        # check path
        if (!preg_match(self::EXP_PATH, $path)) {
          throw ErrorEx::fail($path, 'incorrect path');
        }
        # determine base properties of the item
        $depth = substr_count($path, '/', 1);
        $name  = substr($path, 1 + strrpos($path, '/'));
        $id    = hash('xxh3', $path);# 16 bytes
        $type  = ucfirst($item['type'] ?? self::TYPE_DEFAULT);
        $class = self::TYPE_NAMESPACE.'Bot'.$type.'Item';
        # check class
        if (!class_exists($class, false)) {
          throw ErrorEx::fail($path, 'unknown type: '.$type);
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
          $bot, $path, $depth, $name, $id,
          $type, $item, $hands[$path] ?? null
        );
      }
      # assemble tree and map
      $tree = [];
      $map  = [];
      foreach ($items as $path => $item)
      {
        if ($item->depth === 0)
        {
          $tree[$item->name] = self::root(
            $items, $item
          );
        }
        $map[$path] = $map[$item->id] = $item;
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
  static function root(# {{{
    array &$list, object $item, ?object $parent = null
  ):object
  {
    # set parent and root
    $item->root = ($item->parent = $parent)
      ? $parent->root
      : $item;
    # set children
    $a = $item->depth + 1;
    $c = strlen($b = $item->path);
    $d = [];
    foreach ($list as $path => $e)
    {
      if ($e->depth === $a &&
          strncmp($e->path, $b, $c) === 0)
      {
        $d[$e->name] = self::root(
          $list, $e, $item
        );
      }
    }
    $item->children = &$d;
    return $item;
  }
  # }}}
  static function parseCallback(string &$s): ?array # {{{
  {
    $a = [];
    return preg_match(self::EXP_CALLBACK, $s, $a)
      ? [$a[1],intval($a[3]),($a[5] ?? ''),($a[7] ?? '')]
      : null;
  }
  # }}}
  static function parseCommand(string &$text): ?array # {{{
  {
    # - simple command: <PATH>[@<BOTNAME>]
    # - deeplink (https:/t.me://<BOTNAME>?start=<PATH>)
    # - command query: <PATH>[!<FUNC>][ <ARGS>]
    $a = [];
    if (preg_match(self::EXP_COMMAND, $text, $a)) {
      return [$a[1],'','',($a[4] ?? '')];
    }
    if (preg_match(self::EXP_DEEPLINK, $text, $a)) {
      return [$a[1],'deeplink',$a[2],''];
    }
    return null;
  }
  # }}}
  static function parseQuery(string &$s): ?array # {{{
  {
    $a = [];# [path,func,args]
    if (preg_match(self::EXP_Q_SELF, $s, $a) ||
        preg_match(self::EXP_Q_CHILD, $s, $a) ||
        preg_match(self::EXP_Q_COMMAND, $s, $a))
    {
      return [$a[1],($a[4] ?? ''),($a[6] ?? '')];
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
  use TimeTouchable;
  # {{{
  const
    PROC_UUID       = '22c4408d490143b5b29f0640755327db',
    PROC_TIMEOUT    = 4000,# ms, response timeout
    PROC_WAIT       = 500,# ms, intervals
    IDLE_DELAY      = 20000,# usec, minimal delay
    IDLE_FACTOR     = 10,# maximal factor
    IDLE_STEP       = 1000,# delays before idle
    BUSY_TOUCH      = 100,# time touch after
    BUF_UUID        = 'c25de777e80d49f69b6b7b57091d70d5',
    BUF_SIZE        = 200,
    EXIT_CLEAN      = 0,
    EXIT_DIRTY      = 1,
    EXIT_UNEXPECTED = 2,
    EXIT_RESTART    = 100,
    EXIT_SIGINT     = 101,
    EXIT_SIGTERM    = 102;
  public
    $id,$log,$pidfile,$active,$syncbuf,
    $idleTicks,$idleStep,$idleFactor,$idleDelay,
    $busyTicks,
    $map = [],$exitcode = self::EXIT_DIRTY;
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
    $this->pidfile = $this->path();
    $this->active  = new SyncEvent(
      $bot->id.self::PROC_UUID, 1
    );
    $this->syncbuf = new Syncbuf(
      $bot->id.self::BUF_UUID,
      self::BUF_SIZE, self::PROC_TIMEOUT
    );
    $this->busy();
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
  function path(string $id = ''): string # {{{
  {
    if ($id === '') {
      $id = $this->bot->id;
    }
    return $this->bot->cfg->dirDataRoot.
      'bot'.$id.'.pid';
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
      throw ErrorEx::fail('forced deactivation');
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
  function loop(): void # {{{
  {
    try
    {
      # prepare
      $bot = $this->bot;
      $bot->log->commands(
        $bot->cmd->tree, $bot['source']
      );
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
  function busy(): void # {{{
  {
    if ($this->idleFactor === self::IDLE_FACTOR) {
      $this->log->info('woke up');
    }
    $this->idleTicks  = 0;
    $this->idleFactor = 1;
    $this->idleStep   = self::IDLE_STEP;
    $this->idleDelay  = self::IDLE_DELAY;
    if (++$this->busyTicks > self::BUSY_TOUCH)
    {
      $this->timeTouch()->busyTicks = 0;
    }
  }
  # }}}
  function wait(): void # {{{
  {
    if (++$this->idleTicks > $this->idleStep)
    {
      $i = &$this->idleFactor;
      $j = self::IDLE_FACTOR;
      if ($i < $j && ++$i === $j)
      {
        $this->log->info('going to sleep..');
        $this->bot->file->sync();
      }
      $this->idleTicks = 0;
      $this->idleStep  = intval(self::IDLE_STEP / $i);
      $this->idleDelay = intval(self::IDLE_DELAY * $i);
    }
    usleep($this->idleDelay);
    $this->timeTouch();
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
    return $this->bot->listActiveBots();
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
      $this->log->banner();
      while ($this->check()) {
        usleep(100000);# 100ms
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
        throw ErrorEx::fail($c, 'failed to attach');
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
        throw ErrorEx::fail('info', 'no response');
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
    EXP_PIDFILE  = '/^bot(-{0,1}[0-9]+)\.pid$/',
    EXP_BOTID    = '/^-{0,1}[0-9]+$/',
    EXP_BOTNAME  = '/^[a-z]\w{1,29}bot$/i',
    EXP_USERNAME = '/^[a-z]\w{4,32}$/i',
    INIT = [
      'console','cfg','log','file',
      'api','text','cmd','proc'
    ];
  public
    $id,$task,
    $console,$log,$cfg,$api,$events,$text,$cmd,$file,$proc,
    $inited = [],$users = [],$chats = [];
  # }}}
  static function start(string $id = ''): never # {{{
  {
    try
    {
      # create instance
      $bot = new self($id);
      # initialize and operate
      if ($bot->init())
      {
        $bot->proc->loop();
        $bot->finit();
      }
      $e = $bot->proc->exitcode;
    }
    catch (Throwable $e)
    {
      # compose failure report
      $e = "\n".__METHOD__."\n".
        BotLog::throwable($e);
      # output
      if ($id === '') {
        fwrite(STDOUT, $e);
      }
      else {
        error_log($e);
      }
      $e = BotProcess::EXIT_UNEXPECTED;
    }
    exit($e);
  }
  # }}}
  function __construct(string $id) # {{{
  {
    $this->id      = $id;
    $this->task    = '';
    $this->console = BotConsole::construct($this);
    $this->log     = new BotLog($this);
    $this->cfg     = new BotConfig($this, $id);
    $this->config  = $this->cfg->data['Bot'];
    $this->file    = new BotFile($this);
    $this->api     = new BotApi($this);
    $this->events  = new BotEvents($this);
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
      # are and thrown as exception
      set_error_handler(function(
        int $no, string $msg, string $file, int $line
      ) {
        throw ErrorEx::num($no, $msg);
      });
      # initialize in the right order
      foreach (self::INIT as $k)
      {
        if (!$this->$k->init()) {
          throw ErrorEx::fail($k, 'failed to initialize');
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
    foreach ($this->api->recieve() as $o) {
      $this->events->load($o);
    }
    return $this->events->dispatch();
  }
  # }}}
  function &listDataRoot(): array # {{{
  {
    # read data directory
    $a = $this->cfg->dirDataRoot;
    $b = scandir($a, SCANDIR_SORT_NONE);
    if ($b === false) {
      throw ErrorEx::fail('scandir', "fail\n".$a);
    }
    # filter special items
    $c = [];
    foreach ($b as &$a)
    {
      if ($a !== '.' && $a !== '..') {
        $c[] = $a;
      }
    }
    return $c;
  }
  # }}}
  function &listBots(): array # {{{
  {
    $a = []; $b = [];
    foreach ($this->listDataRoot() as &$c)
    {
      if (preg_match(self::EXP_BOTID, $c, $b)) {
        $a[] = $b[0];
      }
    }
    return $a;
  }
  # }}}
  function &listActiveBots(): array # {{{
  {
    $a = []; $b = [];
    foreach ($this->listDataRoot() as &$c)
    {
      if (preg_match(self::EXP_PIDFILE, $c, $b)) {
        $a[] = $b[1];
      }
    }
    return $a;
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
# event {{{
abstract class BotEvent extends BotConfigAccess # {{{
{
  use TimeTouchable;
  final static function fromUpdate(# {{{
    object $bot, object $u
  ):object
  {
    if (isset($u->callback_query)) {
      return BotEventCallback::from($bot, $u->callback_query);
    }
    if (isset($u->message)) {
      return BotEventInput::from($bot, $u->message);
    }
    if (isset($u->my_chat_member)) {
      return BotEventMember::from($bot, $u->my_chat_member);
    }
    if (isset($u->chat_member)) {
      return BotEventMember::from($bot, $u->chat_member);
    }
    return ErrorEx::skip();
  }
  # }}}
  final static function construct(# {{{
    object $bot, object $data, object $user, ?object $chat
  ):?object
  {
    # get user and chat
    if (!($user = BotUser::from($bot, $user)) ||
        !($chat = BotChat::from($bot, $user, $chat)))
    {
      return null;
    }
    # get logger
    $log = $chat->isGroup()
      ? $chat->log->new($user->logname)
      : $chat->log;
    # construct
    return new static(
      $bot, $data, $user, $chat,
      $log->new(static::LOGNAME),
      'c'.$chat->id
    );
  }
  # }}}
  final function __construct(# {{{
    public object $bot,
    public object $data,
    public object $user,
    public object $chat,
    public object $log,
    public string $id
  ) {
    $this->timeTouch();
  }
  # }}}
  final function asap(): self # {{{
  {
    $this->id = '';
    return $this;
  }
  # }}}
  final function result(object $r): void # {{{
  {
    $this->log->result($r,
      ($r->ok ? 'ok' : 'fail'),
      $this->timeDelta().'ms'
    );
  }
  # }}}
  abstract function response(): ?object;
}
# }}}
class BotEventCallback extends BotEvent # {{{
{
  const LOGNAME = 'callback';
  const TIMEOUT = 14500;# max=15000ms
  static function from(# {{{
    object $bot, object $o
  ):object
  {
    static $E = [
      'missing callback_query.from',
      'missing callback_query.message.chat'
    ];
    if (!($from = $o->from ?? null)) {
      return ErrorEx::fail($E[0]);
    }
    if (!($chat = $o->message->chat ?? null)) {
      return ErrorEx::fail($E[1]);
    }
    if (isset($o->data)) {
      return self::construct($bot, $o, $from, $chat);
    }
    #if (isset($o->game_short_name)) {
    #  return BotEventGame::construct($bot, $o, $from, $chat);
    #}
    return ErrorEx::skip();
  }
  # }}}
  function response(): ?object # {{{
  {
    # prepare
    $text = &$this->data->data;
    $bot  = $this->bot;
    $self = $this;
    # check NOP
    if (strlen($text) === 1) {
      return $this->asap()->answer();
    }
    # parse
    if (!($a = $bot->cmd::parseCallback($text))) {
      return $this->asap()->invalid($text);
    }
    # get target item
    if (!($item = $bot->cmd[$a[0]])) {
      return $this->asap()->invalid($text);
    }
    # check no operation
    if ($a[2] === 'nop') {
      return $this->asap()->answer();
    }
    # compose
    $this->log->infoInput($text);
    return Promise::Fn(function($r) use (
      $self,&$a,$item
    ) {
      # prepare
      $chat = $self->chat;
      $qry  = $self->data;
      $node = $chat->nodeOfMessage(
        $id = $qry->message->message_id
      );
      # check valid
      if (!$node)
      {
        $self->log->warn(
          'unbound, no message='.$id
        );
        return null;
      }
      if ($a[1] !== $node->tick)
      {
        $self->log->warn(
          'invalid, tick='.$a[1].'<>'.$node->tick
        );
        return null;
      }
      # operate
      return $chat->callback(
        $self, $node, $item, $a[2], $a[3]
      )
      ->then(function($r) use ($self,$node,$id) {
        # get message
        if (!($msg = $node->message($id))) {
          return null;# message was deleted
        }
        # check timeout
        if ($self->timeDelta() > $self::TIMEOUT) {
          return null;# answering's not possible
        }
        # answer query
        return $self->answer();
      })
      ->then($self->result(...));
    });
  }
  # }}}
  function answer(# {{{
    string $text = '', bool $alert = false
  ):object
  {
    $a = ['callback_query_id' => $this->data->id];
    if ($text !== '')
    {
      $a['text'] = $text;
      $a['show_alert'] = $alert;
    }
    return $this->bot->api
      ->answerCallbackQuery($a);
  }
  # }}}
  function alert(string $k): object # {{{
  {
    return $this->answer(
      $this->bot->text->get($k, $this->chat->lang),
      true
    );
  }
  # }}}
  function invalid(string &$txt): ?object # {{{
  {
    $this->log->warnInput($txt);
    return $this['replyInvalid']
      ? $this->alert('!inline-markup')
      : null;
  }
  # }}}
}
# }}}
class BotEventGame extends BotEvent # {{{
{
  const LOGNAME = 'game';
  function response(): ?object # {{{
  {
    return null;
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
class BotEventInput extends BotEvent # {{{
{
  const LOGNAME = 'input';
  static function from(# {{{
    object $bot, object $o
  ):object
  {
    static $E = [
      'missing message.from',
      'missing message.chat'
    ];
    if (!($from = $o->from ?? null)) {
      return ErrorEx::fail($E[0]);
    }
    if (!($chat = $o->chat ?? null)) {
      return ErrorEx::fail($E[1]);
    }
    if (isset($o->text) && $o->text[0] === '/')
    {
      return BotEventCommand::construct(
        $bot, $o, $from, $chat
      );
    }
    return self::construct(
      $bot, $o, $from, $chat
    );
  }
  # }}}
  function response(): ?object # {{{
  {
    return null;
    /***
    # wipe when attached or in private chat
    $this['wipeInput'] &&
    ($user || !$this->bot->user->isGroup) &&
    $this->bot->api->deleteMessage($this->data);
    # complete
    return $user
      ? $user->update($this->item)
      : false;
    /***/
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
}
# }}}
class BotEventCommand extends BotEvent # {{{
{
  const LOGNAME = 'command';
  function response(): ?object # {{{
  {
    # prepare
    $msg  = $this->data;
    $text = &$msg->text;
    $chat = $this->chat;
    $bot  = $this->bot;
    # TODO: debounce?
    #var_dump($this->time);
    # parse
    if (!($a = $bot->cmd::parseCommand($text))) {
      return $this->asap()->warning('incorrect');
    }
    # check bot name (when specified)
    # name addressing is skipped for private chat,
    # otherwise it must match with the bot
    if ($a[3] && !$chat->isUser() &&
        $bot['name'] !== $a[3])
    {
      return null;
    }
    # get item
    if (!($item = $bot->cmd[$a[0]])) {
      return $this->asap()->warning('unknown');
    }
    # complete
    $this->log->infoInput($text);
    return Promise::One([
      $chat->command(
        $this, $item, $a[1], $a[2]
      ),
      $bot->api->deleteMessage([
        'chat_id'    => $chat->id,
        'message_id' => $msg->message_id
      ])
    ])
    ->then($this->result(...));
  }
  # }}}
  function warning(string $msg): ?object # {{{
  {
    if (!$this->chat->isUser()) {
      return null;
    }
    $this->log->warnInput($msg);
    return $this->bot->api->deleteMessage([
      'chat_id'    => $this->chat->id,
      'message_id' => $this->data->message_id
    ]);
  }
  # }}}
}
# }}}
class BotEventMember extends BotEvent # {{{
{
  const LOGNAME = 'member';
  static function from(# {{{
    object $bot, object $o
  ):object
  {
    $x = isset(
      $o->from, $o->chat, $o->date,
      $o->old_chat_member, $o->new_chat_member
    );
    if ($x === false)
    {
      return ErrorEx::fail(
        self::LOGNAME, 'missing required field'
      );
    }
    return new self(
      $bot, $o, $o->from, $o->chat
    );
  }
  # }}}
  function response(): ?object # {{{
  {
    return null;
  }
  # }}}
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
    return false;
  }
  # }}}
}
# }}}
# }}}
# user {{{
class BotUser
{
  use TimeTouchable;
  public $name,$username,$logname;
  static function from(# {{{
    object $bot, object $user
  ):self
  {
    # check cache
    if (isset($bot->users[$id = strval($user->id)])) {
      $user = $bot->users[$id];# take from cache
    }
    else
    {
      # create and store new instance
      $bot->users[$id] = $user = new self(
        $bot, $id, $user
      );
    }
    # update access timestamp and complete
    return $user->timeTouch();
  }
  # }}}
  function __construct(# {{{
    public object $bot,
    public string $id,
    public object $info
  ) {
    $this->name     = trim($info->first_name);
    $this->username = $info->username ?? '';
    $this->logname  = $this->username
      ? '@'.$this->username
      : $this->name.'#'.$id;
  }
  # }}}
  function todo(object $req): int # {{{
  {
    return 0;
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
  static function from(# {{{
    object $bot, object $user, ?object $chat
  ):?object
  {
    # determine identifier
    $id = $chat
      ? strval($chat->id)
      : '';
    # check cache
    if (isset($bot->chats[$id])) {
      return $bot->chats[$id]->timeTouch();
    }
    # create specific instance
    $I = match ($chat->type ?? 'none') {
      'private' => new BotUserChat($bot, $id),
      'group','supergroup' => new BotGroupChat($bot, $id),
      'channel' => new BotChanChat($bot, $id),
      'none' => new BotAnonChat($bot, $id)
    };
    # initialize and complete
    return $I->init($user, $chat)
      ? $bot->chats[$id] = $I->timeTouch()
      : null;
  }
  # }}}
  function __construct(# {{{
    public object $bot,
    public string $id
  ) {}
  # }}}
  function init(object $user, object $chat): bool # {{{
  {
    # set names
    $this->set($user, $chat);
    # set logger
    $this->log = ($bot = $this->bot)->log
      ->new(static::LOGNAME)
      ->new($this->logname);
    # get working directory
    if (!($dir = $this->dir())) {
      return true;
    }
    if (!$bot->file->dirCheckMake($dir)) {
      return false;
    }
    # complete
    return $this->load($dir, $user);
  }
  # }}}
  function load(# {{{
    string $dir, object $user
  ):bool
  {
    # load info: 0=Chat 1=lang
    $bot  = $this->bot;
    $file = $bot->file;
    if (!($data = $file->data($dir.self::FILE_INFO))) {
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
      $this->lang = $data[1] =
      $bot->text->lang($this->isUser()
        ? ($user->info->language_code ?? '')
        : ''
      );
      $data->sync();
    }
    else
    {
      # restore
      $this->info = $data[0];
      $this->lang = $data[1];
    }
    ###
    # load configuration: 0=view, 1=conf, 2=opts
    if (!($data = $file->data($dir.self::FILE_VIEW, 1))) {
      return false;
    }
    # set defaults
    if ($data->isEmpty()) {
      $data->set([[], [], []]);
    }
    # set
    $this->view = $data[0];
    $this->conf = $a = $data[1]->restruct(1);
    $this->opts = $b = $data[2]->restruct(1);
    # create nodes
    $data[0]->filt(function(&$v) use ($bot)
    {
      $v = BotNode::reconstruct($bot, $v);
      return $v !== null;
    });
    # set filter
    $a->jsonFilter = $b->jsonFilter =
    (function(array &$in) use ($bot) {
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
  function nodeOfMessage(int $id): ?object # {{{
  {
    foreach ($this->view->arr as $node)
    {
      if ($node->message($id)) {
        return $node;
      }
    }
    return null;
  }
  # }}}
  function nodeOfRoot(object $item): ?object # {{{
  {
    $root = $item->root;
    foreach ($this->view->arr as $node)
    {
      if ($node->item->root === $root) {
        return $node;
      }
    }
    return null;
  }
  # }}}
  function nodeOfItem(object $item): ?object # {{{
  {
    foreach ($this->view->arr as $node)
    {
      if ($node->item === $item) {
        return $node;
      }
    }
    return null;
  }
  # }}}
  function callback(# {{{
    object $req, ?object $node,
    object $item, string &$func, string &$args
  ):object
  {
    return new Promise(new BotChatCallback(
      $req, $node, $item,
      new BotItemQuery($func, $args)
    ));
  }
  # }}}
  function command(# {{{
    object $req, object $item, string &$func, string &$args
  ):object
  {
    return new Promise(new BotChatCommand(
      $req, null, $item,
      new BotItemQuery($func, $args)
    ));
  }
  # }}}
  function isUser():  bool {return false;}
  function isGroup(): bool {return false;}
  function isChan():  bool {return false;}
  abstract function dir(): string;
  abstract function set(
    object $user, ?object $chat
  ):object;
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
      : $this->name.'#'.$this->id;
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
  const NAVI = ['up'=>1,'close'=>1];
  function __construct(# {{{
    public object  $req,
    public ?object $node,
    public object  $item,
    public object  $query
  ) {}
  # }}}
  function stop(): ?object # {{{
  {
    # prepare
    $item = $this->item;
    $node = $this->node
      ?? $this->req->chat->nodeOfRoot($item);
    # handle common navigation
    if (isset(self::NAVI[$a = $this->query->func]))
    {
      if (!$node || $node->item !== $item)
      {
        $this->req->log->warn($item->path,
          #"\n$a: item node is not in the chat"
          "\n$a: wrong node ".$node->item->path
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
          ? $this->opUpdate($node)
          : $this->opReplace($node, $item))
        : $this->opCreate($item))
      : $this->opDelete($node);
  }
  # }}}
  function opDelete(object $node): ?object # {{{
  {
    return ($e = $node->item->detach($this->req))
      ? $this->opFail($e, $node->item, 'delete')
      : $this->delete($node);
  }
  # }}}
  function opCreate(object $item): ?object # {{{
  {
    $node = $item->attach($this->req, $this->query);
    return ErrorEx::is($node)
      ? $this->opFail($node, $item, 'create')
      : $this->create($node);
  }
  # }}}
  function opReplace(object $node0, object $item): ?object # {{{
  {
    static $op = 'replace';
    $item0 = $node0->item;
    if ($e = $item0->detach($this->req)) {
      return $this->opFail($e, $item0, $op, $item);
    }
    $node1 = $item->attach($this->req, $this->query);
    return ErrorEx::is($node1)
      ? $this->opFail($node1, $item0, $op, $item)
      : ($node1->isEmpty()
        ? $this->delete($node0)
        : $this->update($node0, $node1));
  }
  # }}}
  function opUpdate(object $node0): ?object # {{{
  {
    $item0 = $node0->item;
    $node1 = $item0->attach(
      $this->req, $this->query, $node0->tick
    );
    return ErrorEx::is($node1)
      ? $this->opFail($node1, $item0, 'update')
      : ($node1->isEmpty()
        ? $this->delete($node0)
        : $this->update($node0, $node1));
  }
  # }}}
  function opFail(# {{{
    object $e, object $itm0, string $op,
    object $itm1 = null
  ):void
  {
    $this->result->failure($e)->confirm(
      'item', $itm0->path, $op,
      ($itm1 ? $itm1->path : '')
    );
  }
  # }}}
  function delete(object $node): object # {{{
  {
    return $node
    ->delete($chat = $this->req->chat)
    ->then(function($r) use ($node,$chat) {
      $r->ok && $chat->view->delete($node);
      $r->pack()->confirm(
        'item',$node->item->path,'delete',''
      );
    });
  }
  # }}}
  function create(object $node): object # {{{
  {
    return $node
    ->create(
      $chat = $this->req->chat,
      $this->req->user
    )
    ->then(function($r) use ($chat,$node) {
      $r->ok && $chat->view->prepend($node);
      $r->pack()->confirm(
        'item',$node->item->path,'create',''
      );
    });
  }
  # }}}
  function update(# {{{
    object $node0, object $node1
  ):?object
  {
    if (!$node0->isFresh() ||
        !$node0->isCompatible($node1))
    {
      return $this->replace($node0, $node1);
    }
    $chat = $this->req->chat;
    if (!($p = $node0->update($chat, $node1)))
    {
      $this->result->confirm(
        'item',$node0->item->path,'update',
        'skip, no change'
      );
      return null;
    }
    return $p
    ->then(function($r) use ($chat,$node0,$node1) {
      # replace node
      $r->ok &&
      $chat->view->replace($node0, $node1);
      # complete
      $a = $node0->item;
      $b = $node1->item;
      $c = ($a === $b)
        ? ['item',$a->path,'update','']
        : ['item',$a->path,'morph',$b->path];
      $r->pack()->confirms($c);
    });
  }
  # }}}
  function replace(# {{{
    object $node0, object $node1
  ):?object
  {
    # prepare
    $chat = $this->req->chat;
    $user = $this->req->user;
    # check refresh is not needed
    if ($node0->isEqual($node1) &&
        $chat->view[0] === $node0 &&
        $node0->isFresh(5))
    {
      $this->result->confirm(
        'item',$node0->item->path,'refresh','skip'
      );
      return null;
    }
    # compose
    return Promise::OneStop([
      $node1->create($chat, $user, $node0),
      $node0->delete($chat)
    ])
    ->then(function($r) use ($chat,$node0,$node1) {
      # ignore failed deletion when creation succeed
      if (!$r->ok && ($a = $r->track[0]->group) &&
          count($a) === 2 && $a[1]->ok)
      {
        $r->success()->message(
          'ignore', 'unsuccessful delete/zap'
        );
      }
      # replace node
      $r->ok && $chat->view
        ->delete($node0)
        ->prepend($node1);
      # complete
      $a = $node0->item;
      $b = $node1->item;
      $c = ($a === $b)
        ? ['item',$a->path,'refresh','']
        : ['item',$a->path,'replace',$b->path];
      $r->confirms($c);
    });
  }
  # }}}
}
# }}}
class BotChatCommand extends BotChatCallback # {{{
{
  function update(object $n0, object $n1): ?object {
    return $this->replace($n0, $n1);
  }
}
# }}}
# }}}
# item {{{
abstract class BotItem implements ArrayAccess # {{{
{
  # {{{
  const OPTION = [
    'type.enter' => false,
    'type.leave' => false,
    'data.name'  => '',
    'data.scope' => 'chat',
    'data.fetch' => 0,# -1=always,0=never,X=seconds
  ];
  public
    $defs,$caps,$texts,
    $parent,$root,$children;
  # }}}
  final function __construct(# {{{
    public object   $bot,
    public string   $path,
    public int      $depth,
    public string   $name,
    public string   $id,
    public string   $type,
    public array    $spec,
    public ?object  $hand
  ) {
    # set default options
    $this->defs = $this::OPTION;
    $a = $this::class;
    while ($a = get_parent_class($a))
    {
      foreach ($a::OPTION as $k => $v)
      {
        if (!isset($this->defs[$k])) {
          $this->defs[$k] = $v;
        }
      }
    }
    # complete nested spec options
    foreach ($this->defs as $k => &$v)
    {
      if (is_array($v) && isset($spec[$k]))
      {
        foreach ($v as $a => &$b)
        {
          if (!isset($spec[$k][$a])) {
            $this->spec[$k][$a] = $b;
          }
        }
      }
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
  final function __debugInfo(): array # {{{
  {
    return [
      $this::class => $this->path.($this->hand ? '(...)' : '')
    ];
  }
  # }}}
  final function parseQuery(string $s): ?array # {{{
  {
    # parse
    $cmd = $this->bot->cmd;
    if (($a = $cmd::parseQuery($s)) === null) {
      return null;
    }
    # set item
    $a[0] = ($a[0] === '')
      ? $this
      : (($a[0][0] === '/')
        ? $cmd[$a[0]]
        : $cmd[$this->path.'/'.$a[0]]);
    # complete
    return $a[0] ? $a : null;
  }
  # }}}
  final function filename(): string # {{{
  {
    return str_replace(
      '/', '-', substr($this->path, 1)
    );
  }
  # }}}
  final function pathname(string &$text): string # {{{
  {
    return $this->filename().'-'.hash('xxh3', $text);
  }
  # }}}
  # [spec/defs] access {{{
  final function offsetExists(mixed $k): bool {
    return isset($this->defs[$k]);
  }
  final function offsetGet(mixed $k): mixed
  {
    return $this->spec[$k]
      ?? $this->defs[$k]
      ?? null;
  }
  final function offsetSet(mixed $k, mixed $v): void
  {}
  final function offsetUnset(mixed $k): void
  {}
  # }}}
  final function context(# {{{
    object $req, int $tick
  ):object
  {
    $chat = $req->chat;
    $lang = $chat->lang;
    return new BotItemCtx(
      $this, $tick, $req->log->new($this->path),
      $req->user, $chat, $lang,
      $this->caps, $this->texts[$lang],
      $chat->conf->obtain($this->id),
      new BotItemOptions(
        $this, $chat->opts->obtain($this->id)
      )
    );
  }
  # }}}
  final function data(object $ctx): object # {{{
  {
    static $Q = new BotItemQuery('.data');
    # prepare
    $bot  = $this->bot;
    $path = match ($this['data.scope']) {
      'global' => $bot->cfg->dirDataRoot,
      'bot'    => $bot->cfg->dirData,
      'chat'   => $ctx->chat->dir(),
      default  => '',
    };
    if ($path === '')
    {
      # temporary (mem only)
      throw ErrorEx::warn('TODO');
    }
    else
    {
      # persistent
      if (!($name = $this['data.name'])) {
        $name = '@'.$this->filename();
      }
      $path = $path.$name.'.json';
    }
    # set and initialize
    $data = $bot->file->node($path, 1);
    $data->isEmpty() && $data->set([
      [], ['time' => 0] # data and metadata
    ]);
    # check fetchable
    if (!($a = $this['data.fetch']) ||
        !($f = $this->hand) ||
        (($a > 0) &&
         ($b = time()) - $data[1]['time'] < $a))
    {
      return $data;
    }
    # fetch and refine
    if (!$f->call($ctx, $Q()))
    {
      throw ErrorEx::fail(
        __FUNCTION__, 'unable to fetch'
      );
    }
    if (!$this->operate($ctx, $Q))
    {
      throw ErrorEx::fail(
        __FUNCTION__, 'unable to refine'
      );
    }
    # store and update timestamp
    $data[0]->setRef($Q->result());
    if ($a > 0) {
      $data[1]['time'] = $b;
    }
    return $data;
  }
  # }}}
  final function attach(# {{{
    object $req, object $qry, int $tick = 0
  ):object
  {
    try
    {
      # advance update counter
      if (++$tick > 99) {
        $tick = 2;
      }
      # operate recursively
      $item = $this;
      while ($item)
      {
        # create item context
        $ctx = self::enter($item->context(
          $req, $tick
        ));
        # handle query
        if (!$item->operate($ctx, $qry)) {
          throw ErrorEx::warn('operation cancelled');
        }
        # extract next item
        $item = $qry->result();
      }
      # create new node
      $e = BotNode::construct($ctx);
    }
    catch (Throwable $e) {
      # TODO: delete next line
      $req->log->exception($e);
      $e = ErrorEx::from($e);
    }
    return $e;
  }
  # }}}
  final static function enter(object $ctx): object # {{{
  {
    static $Q = new BotItemQuery('.enter');
    # check item event enabled and
    # item is not present in the chat
    if (($item = $ctx->item)['type.enter'] &&
        !$ctx->chat->nodeOfItem($item))
    {
      # set first entry flag
      $Q->args = $ctx->conf->isEmpty();
      # invoke handlers
      if (!$item->operate($ctx, $Q))
      {
        throw ErrorEx::warn(
          __FUNCTION__, 'denied by component'
        );
      }
      if (($f = $item->hand) && !$f->call($ctx, $Q))
      {
        throw ErrorEx::warn(
          __FUNCTION__, 'denied by service'
        );
      }
    }
    return $ctx;
  }
  # }}}
  function operate(object $ctx, object $q): bool # {{{
  {
    return ($f = $this->hand)
      ? $f->call($ctx, $q)
      : true;
  }
  # }}}
  final static function leave(object $ctx): ?object # {{{
  {
    static $Q = new BotItemQuery('.leave');
    try
    {
      if (!$item->operate($ctx, $Q))
      {
        throw ErrorEx::warn(
          __FUNCTION__, 'denied by component'
        );
      }
      if (($f = $item->hand) && !$f->call($ctx, $Q))
      {
        throw ErrorEx::warn(
          __FUNCTION__, 'denied by service'
        );
      }
      $e = null;
    }
    catch (Throwable $e) {
      $e = ErrorEx::from($e);
    }
    return $e;
  }
  # }}}
  final function detach(object $req): ?object # {{{
  {
    return $this['type.leave']
      ? self::leave($this->context($req, 0))
      : null;
  }
  # }}}
  abstract function render(object $ctx): array;
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
    array|string $data = [], string $template = ''
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
    public mixed  &$args = null
  ) {}
  function __invoke(mixed $args = null): self
  {
    if (($this->args = &$args) === null) {
      $this->res = null;
    }
    return $this;
  }
  function &result(): mixed
  {
    $res = $this->res;
    $this->res = null;
    return $res;
  }
}
# }}}
class BotItemCtx implements ArrayAccess # {{{
{
  public $data,$vars;
  function __construct(# {{{
    public object $item,
    public int    $tick,
    public object $log,
    public object $user,
    public object $chat,
    public string $lang,
    public object $caps,
    public object $text,
    public object $conf,
    public object $opts
  ) {}
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
  function load(): object # {{{
  {
    if (($data = &$this->data) === null) {
      $data = $this->item->data($this);
    }
    return $data[0];
  }
  # }}}
  function markup(# InlineKeyboardMarkup {{{
    ?array $mkup  = null,
    ?array $flags = null
  ):object
  {
    if ($mkup === null) {# select default
      $mkup = $this->opts['markup'];
    }
    return BotItemMarkup::construct(
      $this, $mkup, $flags
    );
  }
  # }}}
}
# }}}
class BotItemOptions implements ArrayAccess # {{{
{
  function __construct(
    private object $item,
    private object $spec
  ) {}
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
class BotItemMarkup implements Stringable # InlineKeyboardMarkup {{{
{
  static function construct(# {{{
    object $ctx, array &$mkup, ?array &$flags
  ):self
  {
    $list = [];
    foreach ($mkup as &$a)
    {
      $row = BotItemMarkupRow::construct(
        $ctx, $a, $flags
      );
      if ($row) {
        $list[] = $row;
      }
    }
    return new self($list, $ctx->tick);
  }
  # }}}
  function __construct(# {{{
    public array &$list,
    public int   $tick
  ) {}
  # }}}
  function __toString(): string # {{{
  {
    return $this->render(0);
  }
  # }}}
  function __invoke(): string # {{{
  {
    return $this->render();
  }
  # }}}
  function render(int $tick = 0): string # {{{
  {
    if (count($this->list) === 0) {
      return '';
    }
    $t = (func_num_args() === 0)
      ? strval($this->tick)
      : strval($tick);
    $a = [];
    foreach($this->list as $row) {
      $a[] = $row->render($t);
    }
    $t = json_encode(
      ['inline_keyboard' => $a],
      JSON_UNESCAPED_UNICODE
    );
    return $t ?: '';
  }
  # }}}
}
class BotItemMarkupRow
{
  static function construct(# {{{
    object $ctx, array &$cells, ?array $flags
  ):?self
  {
    $list = [];
    foreach ($cells as &$a)
    {
      # check constructed
      if (is_object($a))
      {
        $list[] = $a;
        continue;
      }
      # skip incorrect/empty
      if (!is_string($a) || strlen($a) === 0) {
        continue;
      }
      # construct
      $b = BotItemMarkupBtn::construct(
        $ctx, $a, $flags
      );
      if ($b) {
        $list[] = $b;
      }
    }
    return ($count = count($list))
      ? new self($list, $count)
      : null;
  }
  # }}}
  function __construct(# {{{
    public array  &$list,
    public int    $count
  ) {}
  # }}}
  function &render(string &$tick): array # {{{
  {
    $a = [];
    foreach ($this->list as $btn) {
      $a[] = $btn->render($tick);
    }
    return $a;
  }
  # }}}
}
class BotItemMarkupBtn
{
  static function construct(# {{{
    object $ctx, string &$exp, ?array $flags
  ):?object
  {
    # check no operation
    $item = $ctx->item;
    if ($exp === ' ') {
      return self::nop($item);
    }
    # parse query expression
    if (!($q = $item->parseQuery($exp))) {
      return null;
    }
    # check another item opener
    if ($q[0] !== $item) {
      return self::open($ctx, $q);
    }
    # apply control flags
    if ($flags && isset($flags[$q[1]]))
    {
      # check hidden
      if (($i = $flags[$q[1]]) === 0) {
        return null;
      }
      # check disabled
      if ($i < 0) {
        return self::nop($item);
      }
    }
    # check special
    switch  ($q[1]) {
    case 'up':
      return self::up($ctx, $q);
    case 'game':
      return new BotItemMarkupGame(
        $item, self::qtext($ctx, $q[1])
      );
    }
    # standard
    return new self($item,
      self::qtext($ctx, $q[1]),
      self::qdata($q[1], $q[2])
    );
  }
  # }}}
  static function nop(# {{{
    object $item, string $text = ' '
  ):self
  {
    return new self($item, $text, '!nop');
  }
  # }}}
  static function op(# {{{
    object $item, string $text, string $func, string $args
  ):self
  {
    return new self(
      $item, $text, self::qdata($func, $args)
    );
  }
  # }}}
  static function open(# {{{
    object $ctx, array &$q
  ):self
  {
    $item = $q[0];
    $text = $ctx->text[$item->name]
      ?: $item->texts[$ctx->lang]['@']
      ?: $item->name;
    $text = $ctx->text->render(
      $text, $ctx->caps['open']
    );
    return new self(
      $item, $text, self::qdata($q[1], $q[2])
    );
  }
  # }}}
  static function up(# {{{
    object $ctx, array &$q
  ):self
  {
    if ($item = $q[0]->parent)
    {
      $tpl = $ctx->caps[$q[1]];
      $txt = $item->texts[$ctx->lang]['@']
        ?: $item->name;
    }
    else
    {
      $q[1] = $txt = 'close';
      $tpl = $ctx->caps[$txt];
      $txt = $ctx->text[$txt];
    }
    return new self($q[0],
      $ctx->text->render($txt, $tpl),
      self::qdata($q[1], $q[2])
    );
  }
  # }}}
  static function qtext(# {{{
    object $ctx, string &$k
  ):string
  {
    return ($tpl = $ctx->caps[$k])
      ? $ctx->text->render($ctx->text[$k], $tpl)
      : $ctx->text[$k];
  }
  # }}}
  static function qdata(# {{{
    string &$func, string &$args
  ):string
  {
    return
      ($func === '' ? '' : '!'.$func).
      ($args === '' ? '' : ' '.$args);
  }
  # }}}
  function __construct(# {{{
    public object $item,
    public string $text,
    public string $data
  ) {}
  # }}}
  function render(string &$tick): array # {{{
  {
    return [
      'text' => $this->text,
      'callback_data' => (
        $this->item->id.':'.$tick.
        $this->data
      )
    ];
  }
  # }}}
}
class BotItemMarkupGame
{
  function __construct(# {{{
    public object $item,
    public string $text
  ) {}
  # }}}
  function render(string &$tick): array # {{{
  {
    return [
      'text' => $this->text,
      'callback_game' => null
    ];
  }
  # }}}
}
# }}}
# }}}
# node {{{
class BotNode implements JsonSerializable
{
  const LIFETIME = 48*60*60;
  static function reconstruct(# {{{
    object $bot, array $a
  ):?self
  {
    # get item
    if (!($item = $bot->cmd[$a[0]]))
    {
      $bot->log->error(__METHOD__,
        'item not found: '.$a[0]
      );
      return null;
    }
    # create messages
    foreach ($a[1] as &$msg) {
      $msg = BotMessage::reconstruct($bot, $msg);
    }
    # construct
    return new self(
      $item, $a[1], $a[2], $a[3], $a[4]
    );
  }
  # }}}
  static function construct(object $ctx): self # {{{
  {
    # render messages and construct
    $item = $ctx->item;
    $msgs = $item->render($ctx);
    return new self($item, $msgs, $ctx->tick);
  }
  # }}}
  function __construct(# {{{
    public object $item,
    public array  &$msgs,
    public int    $tick,
    public int    $time  = 0,
    public string $owner = '',
  ) {}
  # }}}
  function __debugInfo(): array # {{{
  {
    $a = $this->item->__debugInfo();
    $a['message count'] = count($this->msgs);
    return $a;
  }
  # }}}
  function jsonSerialize(): array # {{{
  {
    return [
      $this->item->id, $this->msgs, $this->tick,
      $this->time, $this->owner,
    ];
  }
  # }}}
  function isEmpty(): bool # {{{
  {
    return count($this->msgs) === 0;
  }
  # }}}
  function isFresh(int $t = 0): bool # {{{
  {
    $t = $t ?: (int)(0.8 * self::LIFETIME);# 80%
    return (time() - $this->time) <= $t;
  }
  # }}}
  function isCompatible(object $node): bool # {{{
  {
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
  function isEqual(object $node): bool # {{{
  {
    # check items differ
    if ($this->item !== $node->item) {
      return false;
    }
    # check messages differ
    $a = count($m0 = &$this->msgs);
    $b = count($m1 = &$node->msgs);
    if ($a !== $b) {
      return false;
    }
    for ($a = 0; $a < $b; ++$a)
    {
      if ($m0[$a]->hash !== $m1[$a]->hash) {
        return false;
      }
    }
    # positive
    return true;
  }
  # }}}
  function message(int $id): ?object # {{{
  {
    foreach ($this->msgs as $msg)
    {
      if ($msg->id === $id) {
        return $msg;
      }
    }
    return null;
  }
  # }}}
  function hash(): string # {{{
  {
    $a = '';
    foreach ($this->msgs as $msg) {
      $a .= $msg->hash;
    }
    return $a;
  }
  # }}}
  function delete(object $chat): object # {{{
  {
    # prepare
    $I = $this;
    $a = [];
    # telegram allows to delete only "fresh" messages,
    # check creation timestamp
    if (($t = time() - $this->time) >= 0 &&
        ($t < self::LIFETIME))
    {
      # construct delete actions
      foreach ($this->msgs as $message) {
        $a[] = $message->delete($chat);
      }
      $op = 'delete';
    }
    else
    {
      # construct zaps, zap is a special procedure,
      # which removes message content (makes it neutral)
      foreach ($this->msgs as $message) {
        $a[] = $message->zap($chat);
      }
      $op = 'zap';
    }
    # complete all
    return Promise::AllStop($a)
    ->then(function($r) use ($I,$op) {
      $r->ok && ($I->msgs = []);
      $r->confirm($I->item->type, $op);
    });
  }
  # }}}
  function create(# {{{
    object $chat, object $user, ?object $node = null
  ):object
  {
    # create message promises
    $I = $this;
    $a = [];
    foreach ($this->msgs as $message) {
      $a[] = $message->send($chat);
    }
    # compose
    return
    Promise::Fn(function() use ($I,$user,$node) {
      # first, set timestamp and owner
      $I->time  = time();
      $I->owner = $node
        ? $node->owner
        : $user->id;
    })
    ->thenOneStop($a)
    ->then(function($r) use ($I) {
      $r->confirm(
        $I->item->type, 'create','tick='.$I->tick
      );
    });
  }
  # }}}
  function update(# {{{
    object $chat, object $node1
  ):?object
  {
    # prepare
    $node0 = $this;
    $n0 = count($m0 = &$this->msgs);
    $n1 = count($m1 = &$node1->msgs);
    $up = [];
    # change messages (when they differ)
    for ($i = 0; $i < $n1; ++$i)
    {
      if ($m0[$i]->hash !== $m1[$i]->hash) {
        $up[] = $m0[$i]->edit($chat, $m1[$i]);
      }
    }
    $n1 = count($up);
    # delete extra messages
    for ($i; $i < $n0; ++$i) {
      $up[] = $m0[$i]->delete($chat);
    }
    $n0 = ($n2 = count($up)) - $n1;
    # check no updates made
    if ($n2 === 0) {
      return null;
    }
    # compose
    return Promise::AllStop($up)
    ->then(function($r) use ($node0,$node1,$n0,$n1) {
      # get items
      $item0 = $node0->item;
      $item1 = $node1->item;
      # setup new node
      if ($r->ok)
      {
        $node1->time  = $node0->time;
        $node1->owner = $node0->owner;
      }
      # complete
      $r->confirm(
        $item0->type,$item1->type,'update',
        'delete='.$n0.' edit='.$n1.
        ' tick='.$node1->tick
      );
    });
  }
  # }}}
}
# }}}
# message {{{
abstract class BotMessage
  extends BotConfigAccess implements JsonSerializable
{
  # constructors {{{
  static function reconstruct(
    object $bot, array &$a
  ):self
  {
    return new $a[0]($bot, $a[1], $a[2]);
  }
  static function construct(
    object $bot, array &$a
  ):self
  {
    return new static(
      $bot, 0, static::hashData($a), $a
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
    return $this->bot->api->deleteMessage([
      'chat_id'    => $chat->id,
      'message_id' => $this->id
    ]);
  }
  # }}}
  abstract static function hashData(array &$a): string;
  abstract function zap(object $chat): object;
  abstract function send(object $chat): object;
  abstract function edit(
    object $chat, object $msg
  ):object;
}
class BotImgMessage extends BotMessage # {{{
{
  const UUID = '325d301047084c46bba3eeb44051a1c2';
  static function hashData(array &$a): string # {{{
  {
    return hash(
      'xxh3', $a['path'].$a['text'].$a['markup']
    );
  }
  # }}}
  function zap(object $chat): object # {{{
  {
    # prepare
    $bot  = $this->bot;
    $path = self::UUID;
    $file = null;
    if (!($id = $bot->file->id($path)))
    {
      # create placeholder
      $file = BotImgItem::imgPlaceholder(
        $this['blank']
      );
      # check failed
      if ($file instanceof ErrorEx)
      {
        return Promise::Stop($file->firstMsg(
          'failed to create placeholder image'
        ));
      }
    }
    # complete
    return $bot->api
    ->editMessageMedia([
      'chat_id'    => $chat->id,
      'message_id' => $this->id,
      'media'      => [
        'type'     => 'photo',
        'media'    => $id ?: $file
      ],
    ])
    ->then(function($x) use ($bot,$file,$path) {
      # store file identifier
      if ($x->ok && $file)
      {
        $a = end($x->value->photo);# last image
        $x->message($bot->file->id(
          $path, $a->file_id
        ));
      }
    });
  }
  # }}}
  function send(object $chat): object # {{{
  {
    # prepare
    $self = $this;
    $bot  = $this->bot;
    $data = &$this->data;
    # complete
    return $bot->api
    ->sendPhoto([
      'chat_id'    => $chat->id,
      'photo'      => $data['image'],
      'caption'    => $data['text'],
      'parse_mode' => 'HTML',
      'disable_notification' => true,
      'reply_markup' => $data['markup']()
    ])
    ->then(function($r) use ($self,$bot,&$data) {
      # check failed
      if (!$r->ok) {
        return null;
      }
      # store message identifier
      $self->id = $r->value->message_id;
      # store file identifier
      if (is_object($data['image']))
      {
        $a = end($r->value->photo);# last image
        $r->message($bot->file->id(
          $data['path'], $a->file_id
        ));
      }
    });
  }
  # }}}
  function edit(object $chat, object $msg): object # {{{
  {
    # prepare
    $self = $this;
    $bot  = $this->bot;
    $data = &$msg->data;
    # complete
    return $bot->api
    ->editMessageMedia([
      'chat_id'      => $chat->id,
      'message_id'   => $this->id,
      'media'        => [
        'type'       => 'photo',
        'media'      => $data['image'],
        'caption'    => $data['text'],
        'parse_mode' => 'HTML'
      ],
      'reply_markup' => $data['markup']()
    ])
    ->then(function($x) use ($self,$bot,$msg,&$data) {
      # check failed
      if (!$x->ok) {
        return null;
      }
      # equalize
      $msg->id = $self->id;
      $self->hash = $msg->hash;
      # store file identifier
      if (is_object($data['image']))
      {
        $a = end($x->value->photo);# last image
        $x->message($bot->file->id(
          $data['path'], $a->file_id
        ));
      }
    });
  }
  # }}}
}
# }}}
class BotTxtMessage extends BotMessage # {{{
{
  static function hashData(array &$a): string # {{{
  {
    return hash(
      'xxh3', $a['text'].$a['markup']
    );
  }
  # }}}
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
      'chat_id'      => $chat->id,
      'message_id'   => $this->id,
      'text'         => $bot->text->tp->render('{{END}}', ''),
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
## components
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
    'markup' => [['!up']],
  ];
  # }}}
  # hlp {{{
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
    if (!($img = imagecreatefromjpeg($file)))
    {
      throw ErrorEx::fail('imagecreatefromjpeg',
        "fail\n".$file
      );
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
      throw ErrorEx::fail('imagefttext',
        "fail\n".
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
  static function &renderImg(object $ctx): array # {{{
  {
    static $Q = new BotItemQuery('.render');
    # prepare
    $item = $ctx->item;
    $bot  = $item->bot;
    $opts = $ctx->opts;
    # custom render
    if (($f = $item->hand) && !$f->call($ctx, $Q)) {
      throw ErrorEx::skip();
    }
    # checkout message skel
    if (!is_array($msg = $Q->result())) {
      $msg = [];
    }
    if (isset($msg['path'], $msg['image'])) {
      return $msg;
    }
    # standard render
    # check variant
    switch ($opts['img.variant']) {
    case 'file':
      # determine path
      if (!($path = $opts['img.file.name'])) {
        $path = $item->filename();
      }
      if ($opts['img.file.vary'] && isset($msg['name'])) {
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
      $text = $msg['title']
        ?? $ctx->text['@']
        ?: $item->name;
      $path = $item->pathname($text);
      # get identifier from cache
      if ($file = $bot->file->id($path)) {
        break;
      }
      # prepare texts
      $a = [];
      if ($b = $opts['img.title.header'])
      {
        if (!($b[0] = $bot->file->font($b[0]))) {
          throw ErrorEx::fail();
        }
        $b[] = $text;
        $a[] = $b;
      }
      if ($b = $opts['img.title.breadcrumb'])
      {
        if (!($b[0] = $bot->file->font($b[0]))) {
          throw ErrorEx::fail();
        }
        $b[] = $item->path;
        $a[] = $b;
      }
      # create file for transmission
      $file = ($b = $opts['img.title.filename'])
        ? self::imgFileTexts($b, $a)
        : self::imgBlankTexts($opts['img.title.blank'], $a);
      # check
      if ($file instanceof ErrorEx) {
        throw $file;
      }
      break;
    }
    # complete
    $msg['path']  = $path;
    $msg['image'] = $file;
    return $msg;
  }
  # }}}
  # }}}
  function render(object $ctx): array # {{{
  {
    $a = self::renderImg($ctx);
    if (!isset($a[$b = 'text'])) {
      $a[$b] = $ctx->text->render();
    }
    if (!isset($a[$b = 'markup'])) {
      $a[$b] = $ctx->markup();
    }
    return [
      BotImgMessage::construct($this->bot, $a)
    ];
  }
  # }}}
}
# }}}
class BotListItem extends BotImgItem # {{{
{
  const OPTION = [# {{{
    'type.enter' => true,
    ## behaviour
    'list.type'   => 'select',# default action
    'list.select' => [
      'key'   => 'selected',
      'bound' => true,# bound to data, config otherwise
      'max'   => 1,# selected max, 0=unlimited
      'force' => 1,# deselect 0=no 1=first 2=last
      'min'   => 0,# selected min
    ],
    'list.open'   => '',# child to open
    'list.cycle'  => false,# cycle movement (first<=>last)
    ## layout
    'list.cols'  => 1,
    'list.rows'  => 6,
    'list.flexy' => true,# shrink rows
    ## data sorting tag and direction
    'list.order' => '',# no sorting when empty
    'list.desc'  => false,
    ## markup states
    'markup.empty' => [['!up']],
    'markup.head'  => [],
    'markup.foot'  => [['!prev','!next'],['!up']],
    'markup.flags' => [
      # single page
      'prev|next'  => -1,
      'first|last' => 0,
    ],
  ];
  # }}}
  # hlp {{{
  static function pageSize(object $o): int # {{{
  {
    return $o['list.cols'] * $o['list.rows'];
  }
  # }}}
  static function pageCount(# {{{
    object $data, int $pageSize
  ):int
  {
    $i = (int)(ceil($data->count / $pageSize));
    return $i ?: 1;
  }
  # }}}
  static function &pageItems(# {{{
    object $ctx, int $pageSize
  ):array
  {
    # slice page data
    $list = $ctx->data[0]->slice(
      $ctx['page'] * $pageSize, $pageSize
    );
    # add specific props
    if ($ctx->opts['list.type'] === 'select')
    {
      $o = $ctx->item['list.select'];
      $k = $o['key'];
      $a = $o['bound']
        ? ($ctx->data[1][$k] ?? [])
        : ($ctx[$k] ?? []);
      foreach ($list as &$b) {
        $b[$k] = in_array($b['id'], $a, true);
      }
    }
    return $list;
  }
  # }}}
  static function &pageMarkup(# {{{
    object $ctx, array &$data
  ):array
  {
    # prepare
    $item = $ctx->item;
    $opts = $ctx->opts;
    $size = count($data);
    $rows = $opts['list.rows'];
    $cols = $opts['list.cols'];
    $cap  = $ctx->caps['list.'.$opts['list.type']];
    $flex = $opts['list.flexy'];
    $mkup = [];
    # iterate
    for ($i = 0, $a = 0; $a < $rows; ++$a)
    {
      # create row
      $mkup[$a] = [];
      for ($b = 0; $b < $cols; ++$b, ++$i)
      {
        # create callback button
        if ($i < $size)
        {
          $c = $cap
            ? $ctx->text->render($data[$i], $cap)
            : $data[$i]['name'];
          $d = BotItemMarkupBtn::op(
            $item, $c, 'id', $data[$i]['id']
          );
        }
        else {
          $d = BotItemMarkupBtn::nop($item);
        }
        $mkup[$a][$b] = $d;
      }
      # check flexy and no more items
      if ($flex && $i >= $size) {
        break;
      }
    }
    return $mkup;
  }
  # }}}
  static function dataSelect(# {{{
    object $ctx, string $id
  ):bool
  {
    # prepare
    $o = $ctx->item['list.select'];
    $k = $o['key'];
    $a = $o['bound']
      ? ($ctx->data[1][$k] ?? [])
      : ($ctx[$k] ?? []);
    $i = array_search($id, $a, true);
    # check
    if ($i === false)
    {
      # new selection,
      # check unlimited or limit is not reached
      if (($j = $o['max']) === 0 ||
          $j > count($a))
      {
        $a[] = $id;
      }
      elseif ($j = $o['force'])
      {
        # deselect
        if ($j === 1) {
          array_shift($a);# first
        }
        else {
          array_pop($a);# last
        }
        # select
        $a[] = $id;
      }
      else {
        return false;
      }
    }
    else
    {
      # already selected,
      # check limit not reached
      if (count($a) >= $o['min']) {
        array_splice($a, $i, 1);# deselect
      }
      else {
        return false;
      }
    }
    # store and complete
    if ($o['bound']) {
      $ctx->data[1][$k] = $a;
    }
    else {
      $ctx[$k] = $a;
    }
    return true;
  }
  # }}}
  static function dataSort(# {{{
    array &$data, string $k, bool $desc
  ):bool
  {
    return is_string($data[0][$k])
      ? self::dataSortStr($data, $k, $desc)
      : self::dataSortNum($data, $k, $desc);
  }
  # }}}
  static function dataSortNum(# {{{
    array &$data, string $k, bool $desc
  ):object
  {
    return usort($data, function(&$a,&$b) use ($k,$desc)
    {
      # solve equal by identifier
      if ($a[$k] === $b[$k]) {
        return ($a['id'] > $b['id']) ? 1 : -1;
      }
      # compare
      return $desc
        ? (($a[$k] > $b[$k]) ? -1 :  1)
        : (($a[$k] > $b[$k]) ?  1 : -1);
    });
  }
  # }}}
  static function dataSortStr(# {{{
    array &$data, string $k, bool $desc
  ):bool
  {
    return usort($data, function(&$a,&$b) use ($k,$desc)
    {
      # solve equal by identifier
      if (($c = strcmp($a[$k], $b[$k])) === 0) {
        return ($a['id'] > $b['id']) ? 1 : -1;
      }
      # compare
      return $desc
        ? (($c > 0) ? -1 :  1)
        : (($c > 0) ?  1 : -1);
    });
  }
  # }}}
  static function markup(object $ctx): object # {{{
  {
    # prepare
    $item = $ctx->item;
    $vars = &$ctx->vars;
    # check empty
    if ($vars['count'] === 0) {
      return $ctx->markup($item['markup.empty']);
    }
    # render page markup
    $mkup = &self::pageMarkup(
      $ctx, self::pageItems($ctx, $vars['pageSize'])
    );
    # compose
    $a = [];
    foreach ($item['markup.head'] as $b) {
      $a[] = $b;
    }
    foreach ($mkup as $b) {
      $a[] = $b;
    }
    foreach ($item['markup.foot'] as $b) {
      $a[] = $b;
    }
    # complete
    return $ctx->markup(
      $a, self::markupFlags($ctx)
    );
  }
  # }}}
  static function markupFlags(object $ctx): array # {{{
  {
    if ($ctx->vars['pageCount'] < 2)
    {
      # single page
      $o  = $ctx->opts['markup.flags'];
      $F1 = $o['prev|next'];
      $F2 = $o['first|last'];
    }
    else {
      $F1 = $F2 = 1;
    }
    return [
      'prev'=>$F1,'next'=>$F1,
      'back'=>$F1,'forward'=>$F1,
      'first'=>$F2,'last'=>$F2,
    ];
  }
  # }}}
  # }}}
  static function &vars(object $ctx): array # {{{
  {
    # check already done
    if ($vars = &$ctx->vars) {
      return $vars;
    }
    # initialize temporary variables
    $data  = $ctx->load();
    $size  = self::pageSize($ctx->opts);
    $count = self::pageCount($data, $size);
    $last  = $count - 1;
    $vars  = [
      'pageSize'  => $size,
      'pageCount' => $count,
      'count'     => $data->count,
    ];
    # check and correct current page index
    if ($ctx['page'] >= $count) {
      $ctx['page'] = $pageLast;
    }
    # complete
    return $vars;
  }
  # }}}
  function operate(object $ctx, object $q): bool # {{{
  {
    # handle event
    switch ($q->func) {
    case '.enter': # {{{
      # initialize
      if ($q->args) {
        $ctx['page'] = 0;
      }
      return true;
      # }}}
    case '.data': # {{{
      # check number of items
      if (count($q->res) < 2) {
        return true;
      }
      # check sorting enabled
      if (!($tag = $this['list.order'])) {
        return true;
      }
      # sort
      self::dataSort(
        $data->arr, $tag, $this['list.desc']
      );
      return true;
      # }}}
    }
    # prepare
    $vars = &self::vars($ctx);
    $data = $ctx->data[0];
    $opts = $ctx->opts;
    $page = $ctx['page'];
    $pageLast = $vars['pageCount'] - 1;
    # handle operation
    switch ($q->func) {
    case '':
    case 'refresh':
      break;
    case 'first':
      $ctx['page'] = 0;
      break;
    case 'last':
      $ctx['page'] = $pageLast;
      break;
    case 'prev':
    case 'back':
      # {{{
      $ctx['page'] = ($page > 0)
        ? $page - 1
        : ($opts['list.cycle']
          ? $pageLast
          : 0);
      # }}}
      break;
    case 'next':
    case 'forward':
      # {{{
      $ctx['page'] = ($page < $pageLast)
        ? $page + 1
        : ($opts['list.cycle']
          ? 0
          : $pageLast);
      # }}}
      break;
    case 'id':
      # {{{
      # locate item index
      if (($id = $q->args) === '') {
        throw ErrorEx::fail($q->func, 'no argument');
      }
      $i = $data->each(function(&$v) use (&$id) {
        return ($v['id'] !== $id);
      });
      if ($i === -1)
      {
        # message may be outdated,
        # report issue and refresh
        $ctx->log->warn($q->func,
          'list item[id='.$id.'] not found'
        );
        break;
      }
      # execute
      switch ($k = $opts['list.type']) {
      case 'select':
        self::dataSelect($ctx, $id);
        break;
      case 'open':
        # check child exists
        if (!($k = $opts['list.open']) ||
            !isset($this->children[$k]))
        {
          break;
        }
        # redirect
        $q->res  = $this->children[$k];
        $q->args = $id;
        break;
      default:
        # custom
        if (($f = $this->hand) &&
            !$f->call($ctx, $q($i)))
        {
          throw ErrorEx::skip();
        }
        break;
      }
      # }}}
      break;
    default:
      # custom {{{
      if (($f = $this->hand) &&
          !$f->call($ctx, $q))
      {
        throw ErrorEx::skip();
      }
      # }}}
      break;
    }
    return true;
  }
  # }}}
  function render(object $ctx): array # {{{
  {
    # render vars
    $vars = &self::vars($ctx);
    $vars['page'] = $ctx['page'] + 1;
    $vars['pageItems'] = self::pageItems(
      $ctx, $vars['pageSize']
    );
    # render message
    $skel = &self::renderImg($ctx);
    if (!isset($skel[$k = 'text'])) {
      $skel[$k] = $ctx->text->render($vars);
    }
    if (!isset($skel[$k = 'markup'])) {
      $skel[$k] = self::markup($ctx);
    }
    return [
      BotImgMessage::construct($this->bot, $skel)
    ];
  }
  # }}}
}
# }}}
class BotFormItem extends BotImgItem # {{{
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
  function render(object $ctx): array # {{{
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
class BotTxtItem extends BotItem # {{{
{
  const OPTION = [# {{{
  ];
  # }}}
  function render(object $ctx): array # {{{
  {
    return [];
  }
  # }}}
}
# }}}
# TODO {{{
/**
* feature: temporary (mem only) file (ArrayNode)
* feature: item fixation
* architect: event debounce and throttle
* fix/test: file save & unload
* feature: file access time, lazy sync/cleanup
* feature: input filters
* feature: WebHook
* solve: form data separation, user changes its own data until completion
* fix: compatible msg update: unnecessary re-creation in private chat
* solve: old message callback action does ZAP? or.. REFRESH?!
* architect: BotConfig dirs (where to put dirs?)
* perf: mustache parser text reference and prepare()
* perf: api.actions pool of curl instances
*
* ᛉ 2021 - ᛣ 2022
***
* ╔═╗ ⎛     ⎞ ★★☆☆☆
* ║╬║ ⎜  *  ⎟ ✱✱✱ ✶✶✶ ⨳⨳⨳
* ╚═╝ ⎝     ⎠ ⟶ ➤ →
***
*/
# }}}
##
