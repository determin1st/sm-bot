<?php declare(strict_types=1);
namespace SM;
# defs {{{
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
  str_repeat,str_replace,strtolower,
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
###
require_once(
  __DIR__.DIRECTORY_SEPARATOR.
  '..'.DIRECTORY_SEPARATOR.
  'sm-utils'.DIRECTORY_SEPARATOR.
  'all.php'
);
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
  function timeDelta(?int &$base = null): int
  {
    # check argument specified
    if ($base === null) {
      $base = hrtime(true);
    }
    # substract and convert nano into milli
    return (int)(($base - $this->time) / 1000000);
  }
}
# }}}
# }}}
############### boundary
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
    $this->membuf = new Buffer(self::UUID, self::SIZE);
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
        throw ErrorEx::failFn('Buffer::write');
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
      'green','yellow', # 0:info,1:warning
      'red', # 2:error
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
      $c = str_fg_color($c, $color, $strong);
    }
    # trim and split into non-colored
    # for reading and colored for writing
    $s = trim($s);
    $a = explode("\n", str_no_color($s));
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
        $a .= str_fg_color($msg[0], $c, 1);
        $a .= str_fg_color($s, $c);
        $a .= self::opBlock($msg[1], $level);
      }
      else
      {
        # path and operation
        $a .= str_fg_color($msg[0], $c);
        $a .= str_fg_color(self::SEP[3], $c);
        $a .= str_fg_color($msg[1], $c, 1);
      }
      break;
    default:
      # path, operation and message
      $b = implode(
        self::SEP[3], array_slice($msg, 0, -2)
      );
      $a .= str_fg_color($b, $c);
      if (($b = $msg[$i - 2]) !== '')
      {
        $a .= str_fg_color(self::SEP[3], $c);
        $a .= str_fg_color($b, $c, 1);
      }
      if (($b = $msg[$i - 1]) !== '')
      {
        $a .= str_fg_color($s, $c);
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
        $x .= $a ? str_fg_color('│ ', $color, 1) : '  ';
      }
      # compose item line
      $a = (++$i === $j);
      $b = str_fg_color(($a ? '└─' : '├─'), $color, 1);
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
      array_unshift($x, '●');
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
  static function separator(# {{{
    int $level, int $sep = 0
  ):string
  {
    $i = ($level === 1) ? 1 : 0;
    return str_fg_color(
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
    $a = str_fg_color(self::SEP[2], $c, 1);
    $b = '';
    # compose title
    $a = $t->title
      ? $a.self::op($d, $t->title)
      : ($depth
        ? $a.str_fg_color(' ', $c)
        : '');
    # compose errors
    if ($t->error) {
      $b = self::resultError($t->error);
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
  static function resultError(object $e): string # {{{
  {
    $a = '';
    while ($e)
    {
      if (($i = $e->level) > 2)
      {
        $i = 2;
        $m = &self::parseException(
          $e->value ?? $e
        );
      }
      else {
        $m = &$e->msg;
      }
      if ($b = self::op($i, $m, true)) {
        $a .= $b."\n";
      }
      $e = $e->next;
    }
    return $a;
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
  function new(string $name = ''): self # {{{
  {
    return ($name !== $this->name)
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
      $text = $sep.str_fg_color($p->name, $color).$text;
      $p = $p->parent;
    }
    # compose msg chain
    if ($msg)
    {
      for ($i = 0, $j = count($msg) - 1; $i < $j; ++$i)
      {
        $text = $text.$sep.
          str_fg_color($msg[$i], $color, 1);
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
      str_fg_color($prompt, self::COLOR[3], 1).
      str_fg_color($p->name, self::COLOR[3], 0).$text;
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
    $a = str_fg_color($a, self::COLOR[3]);
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
        'name'          => '',
        'admins'        => [],
        'canJoinGroups' => false,
        'canReadGroups' => false,
        'isInline'      => false,
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
      ###
      'BotCallbackEvent' => [
        'replyFast'      => true,# reply before rendering
        'replyInvalid'   => true,# incorrect data
      ],
      'BotInputEvent' => [
        'wipeInput'   => true,
      ],
      'BotCommandEvent' => [
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
abstract class BotConfigAccess implements ArrayAccess
{
  # {{{
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
    $log,$fid,$img = [],$fnt = [],$dataMap = [];
  # }}}
  function __construct(public object $bot) # {{{
  {
    $this->log = $bot->log->new('file');
  }
  # }}}
  function init(): bool # {{{
  {
    try
    {
      # prepare
      $cfg = $this->bot->cfg;
      $flg = GLOB_BRACE|GLOB_NOSORT|GLOB_NOESCAPE;
      # scan images
      $a = self::DIR_IMG.DIRECTORY_SEPARATOR;
      $b = [$cfg->dirInc.$a];
      if (file_exists($c = $cfg->dirSrc.$a)) {
        $b[] = $c;
      }
      if (file_exists($c = $cfg->dirData.$a)) {
        $b[] = $c;
      }
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
      if (file_exists($c = $cfg->dirSrc.$a)) {
        $b[] = $c;
      }
      if (file_exists($c = $cfg->dirData.$a)) {
        $b[] = $c;
      }
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
      # load file identifiers
      $a = $cfg->dirData.self::FILE_ID;
      if (!($b = $this->data($a, 0, 1|4))) {
        throw ErrorEx::skip();
      }
      $this->fid = $b->node;
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
    return '+file_id['.$path.']';
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
  function data(# {{{
    string $path, int $depth = 0, int $flags = 1|4|8
  ):?object
  {
    # check exists
    if (isset($this->dataMap[$path])) {
      return $this->dataMap[$path];
    }
    # construct
    $o = new BotFileData(
      $this->log, $path, $depth,
      (bool)($flags & 1), # associative arrays
      (bool)($flags & 2), # prettify stored json
      (bool)($flags & 4), # save to disk (sync)
      (bool)($flags & 8)  # unload automatically
    );
    # load and store
    return $o->timeTouch()->load()
      ? ($this->dataMap[$path] = $o)
      : null;
  }
  # }}}
  function node(# {{{
    string $path, int $depth = 0
  ):object
  {
    return ($o = $this->data($path, $depth))
      ? $o->node : new ArrayNode([], $depth);
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
  function sync(# {{{
    int $t = 0, int $tSave = 0, int $tUnload = 0
  ):void
  {
    # prepare
    $t = $t ?: hrtime(true);
    $a = '';
    $b = 0;
    $c = count($this->dataMap);
    # synchronize data objects
    foreach ($this->dataMap as $path => $data)
    {
      $tDelta = $data->timeDelta($t);
      if ($data->save && $tDelta >= $tSave &&
          ($i = $data->sync()))
      {
        $d = ($i > 0) ? '[save]' : '[✶]';
      }
      else {
        $d = '';
      }
      if ($data->unload && $tDelta >= $tUnload)
      {
        unset($this->dataMap[$path]);
        $d .= '[unload]';
      }
      if ($d !== '')
      {
        $a .= "\n".$d.' '.$path;
        $a .= ' ('.$data->size.')';
        $b++;
      }
    }
    # report results
    $b && $this->log->info(
      __FUNCTION__, $b.' of '.$c.$a
    );
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
    public int    $depth,
    public bool   $assoc,
    public bool   $pretty,
    public bool   $save,
    public bool   $unload
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
      if (file_exists($path = $this->path))
      {
        if (($a = file_get_contents($path)) === false)
        {
          throw ErrorEx::fail('file_get_contents',
            "fail\n".$path
          );
        }
        $b = strlen($a);
        $a = json_decode($a, $this->assoc, 128,
          JSON_INVALID_UTF8_IGNORE
        );
        if ($a === null)
        {
          throw ErrorEx::fail('json_decode',
            "fail\n".$path."\n".json_error()
          );
        }
        if (!is_array($a))
        {
          throw ErrorEx::fail(
            "incorrect datatype\n".$path
          );
        }
      }
      else
      {
        $a = [];
        $b = 0;
      }
      $this->node = new ArrayNode($a, $this->depth);
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
      $a = json_encode($this->node,
        JSON_UNESCAPED_UNICODE|
        JSON_UNESCAPED_SLASHES|
        ($this->pretty ? JSON_PRETTY_PRINT : 0)
      );
      if ($a === false)
      {
        throw ErrorEx::fail('json_encode',
          "fail\n".$this->path.
          "\n".json_error()
        );
      }
      $b = file_put_contents($this->path, $a);
      if ($b === false)
      {
        throw ErrorEx::fail('file_put_contents',
          "fail\n".$this->path
        );
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
        while (!$a)
        {
          # probe
          if (($a = curl_multi_select($murl, 0)) < 0)
          {
            throw ErrorEx::fail('curl_multi_select',
              "fail\n".$api::mError($murl)
            );
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
  public $log,$queue = [];
  function __construct(public object $bot) {
    $this->log = $bot->log->new('event');
  }
  function load(object $update): bool # {{{
  {
    # construct the event
    $e = BotEvent::fromUpdate($this->bot, $update);
    if (ErrorEx::is($e))
    {
      $this->log->warnObject(
        $update, $e->message('unknown')
      );
      return false;
    }
    # generate the response
    if (($o = $e->response()) === null) {
      return false;
    }
    # stash into queue
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
    $this->hlp = ArrayStackValue::new()->push([
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
  # constructor {{{
  const
    FILE_SCHEMA    = 'commands.inc',
    FILE_HANDLERS  = 'commands.php',
    EXP_PATH       = '|^(\/[a-z0-9_]+){1,16}$|i',
    TYPE_NAMESPACE = '\\'.__NAMESPACE__.'\\',
    TYPE_DEFAULT   = 'Img';
  public
    $log,$tree,$map;
  function __construct(public object $bot) {
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
        $bot->text
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
  # [] access {{{
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
    IDLE_DELAY      = 20000,# usec, min delay
    IDLE_FACTOR     = 10,# max factor
    IDLE_LEVEL      = 1000,# idle ticks before next factor
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
    $idleTicks,$idleLevel,$idleFactor,$idleDelay,
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
    if (!$this->active->wait(0) ||
        !file_exists($this->pidfile))
    {
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
    # reset
    $this->idleTicks  = 0;
    $this->idleFactor = 1;
    $this->idleLevel  = self::IDLE_LEVEL;
    $this->idleDelay  = self::IDLE_DELAY;
  }
  # }}}
  function wait(): void # {{{
  {
    # levelup check
    if (++$this->idleTicks > $this->idleLevel)
    {
      # increase factor
      $i = &$this->idleFactor;
      $j = self::IDLE_FACTOR;
      if ($i < $j && ++$i === $j) {
        $this->log->info('going to sleep..');
      }
      # increase delay
      $this->idleTicks = 0;
      $this->idleLevel = (int)(self::IDLE_LEVEL / $i);
      $this->idleDelay = (int)(self::IDLE_DELAY * $i);
      # do some periodic work
      $this->bot->sync();
    }
    # cooldown
    usleep($this->idleDelay);
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
###############
# bot {{{
class Bot extends BotConfigAccess
{
  const # {{{
    EXP_PIDFILE  = '/^bot(-{0,1}[0-9]+)\.pid$/',
    EXP_BOTID    = '/^-{0,1}[0-9]+$/',
    EXP_BOTNAME  = '/^[a-z]\w{1,29}bot$/i',
    EXP_USERNAME = '/^[a-z]\w{4,32}$/i',
    EXP_CALLBACK = '|^'.
      '([0-9a-f]{16})'.   # id
      '([0-9]{1,2})'.     # tick
      '(!([a-z]+)){0,1}'. # func
      '( (.+)){0,1}'.     # args
      '$|is',
    EXP_COMMAND  = '|^'.
      '((\/[a-z0-9_]{1,64}){1,16})'.# item path
      '(@([a-z_]+bot)){0,1}'.       # botname
      '(\s(.+)){0,1}'.              # arguments
      '$|is',
    EXP_DEEPLINK = '|^'.
      '\/start ([-a-z0-9_]+){1}'.
      '$|i',
    EXP_Q_SELF   = '|^'.
      '(())'.
      '(!([a-z]+)){1}'.
      '([ \n](.*)){0,1}'.
      '$|is',
    EXP_Q_CHILD  = '|^'.
      '([a-z0-9_]+(\/[a-z0-9_]+){0,15})'.
      '(!([a-z]+)){0,1}'.
      '([ \n](.*)){0,1}'.
      '$|is',
    EXP_Q_COMMAND = '|^'.
      '((\/[a-z0-9_]+){1,16})'.
      '(!([a-z]+)){0,1}'.
      '([ \n](.*)){0,1}'.
      '$|is',
    INIT = [
      'console','cfg','log','file',
      'api','text','cmd','proc'
    ];
  # }}}
  # constructor {{{
  public
    $id,$task,
    $console,$log,$cfg,$api,$events,$text,$cmd,$file,$proc,
    $inited = [],$users = [],$chats = [];
  ###
  function __construct(string $id)
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
  # utils {{{
  static function parseCallback(string &$s): ?array # {{{
  {
    # result is [id,tick,func,args] or null
    $a = null;
    return preg_match(self::EXP_CALLBACK, $s, $a)
      ? [$a[1],intval($a[2]),($a[4]??''),($a[6]??'')]
      : null;
  }
  # }}}
  static function parseCommand(string &$s): ?array # {{{
  {
    # result is [path,argument,botname] or null
    $a = null;
    return preg_match(self::EXP_COMMAND, $s, $a)
      ? [$a[1],($a[6]??''),($a[4]??'')];
      : null;
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
  static function typecheck(# {{{
    string $name, object $o, string $from = ''
  ):string
  {
    static $REQUIRED = [],$RECUR = [],$OBJECT = [
      'Update' => [# {{{
        'update_id'            => [1,1],
        'message'              => [0,0,'Message'],
        'edited_message'       => [0,0,'Message'],
        'channel_post'         => [0,0,'Message'],
        'edited_channel_post'  => [0,0,'Message'],
        'inline_query'         => [0,0],
        'chosen_inline_result' => [0,0],
        'callback_query'       => [0,0,'CallbackQuery'],
        'shipping_query'       => [0,0],
        'pre_checkout_query'   => [0,0],
        'poll'                 => [0,0],
        'poll_answer'          => [0,0],
        'my_chat_member'       => [0,0,'ChatMemberUpdated'],
        'chat_member'          => [0,0,'ChatMemberUpdated'],
        'chat_join_request'    => [0,0],
      ],
      # }}}
      'User' => [# {{{
        'id'                          => [1,1],
        'is_bot'                      => [3,1],
        'first_name'                  => [2,1],
        'last_name'                   => [2,0],
        'username'                    => [2,0],
        'language_code'               => [2,0],
        'is_premium'                  => [3,0],
        'added_to_attachment_menu'    => [3,0],
        'can_join_groups'             => [3,0],
        'can_read_all_group_messages' => [3,0],
        'supports_inline_queries'     => [3,0],
      ],
      # }}}
      'Chat' => [# {{{
        'id'                           => [1,1],
        'type'                         => [2,1],
        'title'                        => [2,0],
        'username'                     => [2,0],
        'first_name'                   => [2,0],
        'last_name'                    => [2,0],
        'is_forum'                     => [3,0],
        'photo'                        => [0,0],
        'active_usernames'             => [4,0],
        'emoji_status_custom_emoji_id' => [2,0],
        'bio'                          => [2,0],
        'has_private_forwards'         => [3,0],
        'has_restricted_voice_and_video_messages' => [3,0],
        'join_to_send_messages'        => [3,0],
        'join_by_request'              => [3,0],
        'description'                  => [2,0],
        'invite_link'                  => [2,0],
        'pinned_message'               => [0,0,'Message'],
        'permissions'                  => [0,0],
        'slow_mode_delay'              => [1,0],
        'message_auto_delete_time'     => [1,0],
        'has_aggressive_anti_spam_enabled' => [3,0],
        'has_hidden_members'           => [3,0],
        'has_protected_content'        => [3,0],
        'sticker_set_name'             => [2,0],
        'can_set_sticker_set'          => [3,0],
        'linked_chat_id'               => [1,0],
        'location'                     => [0,0],
      ],
      # }}}
      'Message' => [# {{{
        'message_id'                => [1,1],
        'message_thread_id'         => [1,0],
        'from'                      => [0,0,'User'],
        'sender_chat'               => [0,0,'Chat'],
        'date'                      => [1,1],
        'chat'                      => [0,1,'Chat'],
        'forward_from'              => [0,0,'User'],
        'forward_from_chat'         => [0,0,'Chat'],
        'forward_from_message_id'   => [1,0],
        'forward_signature'         => [2,0],
        'forward_sender_name '      => [2,0],
        'forward_date'              => [1,0],
        'is_topic_message'          => [3,0],
        'is_automatic_forward'      => [3,0],
        'reply_to_message'          => [0,0,'Message'],
        'via_bot'                   => [0,0,'User'],
        'edit_date'                 => [1,0],
        'has_protected_content'     => [3,0],
        'media_group_id'            => [2,0],
        'author_signature'          => [2,0],
        'text'                      => [2,0],
        'entities'                  => [4,0],
        'animation'                 => [0,0],
        'audio'                     => [0,0],
        'document'                  => [0,0],
        'photo'                     => [4,0],
        'sticker'                   => [0,0],
        'video'                     => [0,0],
        'video_note'                => [0,0],
        'voice'                     => [0,0],
        'caption'                   => [2,0],
        'caption_entities'          => [4,0],
        'has_media_spoiler'         => [3,0],
        'contact'                   => [0,0],
        'dice'                      => [0,0],
        'game'                      => [0,0],
        'poll'                      => [0,0],
        'venue'                     => [0,0],
        'location'                  => [0,0],
        'new_chat_members'          => [4,0],
        'left_chat_member'          => [0,0,'User'],
        'new_chat_title'            => [2,0],
        'new_chat_photo'            => [4,0],
        'delete_chat_photo'         => [3,0],
        'group_chat_created'        => [3,0],
        'supergroup_chat_created'   => [3,0],
        'channel_chat_created'      => [3,0],
        'message_auto_delete_timer_changed' => [0,0],
        'migrate_to_chat_id'        => [1,0],
        'migrate_from_chat_id'      => [1,0],
        'pinned_message'            => [0,0,'Message'],
        'invoice'                   => [0,0],
        'successful_payment'        => [0,0],
        'user_shared'               => [0,0],
        'chat_shared'               => [0,0],
        'connected_website'         => [2,0],
        'write_access_allowed'      => [0,0],
        'passport_data'             => [0,0],
        'proximity_alert_triggered' => [0,0],
        'forum_topic_created'       => [0,0],
        'forum_topic_edited'        => [0,0],
        'forum_topic_closed'        => [0,0],
        'forum_topic_reopened'      => [0,0],
        'general_forum_topic_hidden'   => [0,0],
        'general_forum_topic_unhidden' => [0,0],
        'video_chat_scheduled'      => [0,0],
        'video_chat_started'        => [0,0],
        'video_chat_ended'          => [0,0],
        'video_chat_participants_invited' => [0,0],
        'web_app_data'              => [0,0],
        'reply_markup'              => [0,0],
      ],
      # }}}
      'CallbackQuery' => [# {{{
        'id'                => [2,1],
        'from'              => [0,1,'User'],
        'message'           => [0,0,'Message'],
        'inline_message_id' => [2,0],
        'chat_instance'     => [2,1],
        'data'              => [2,0],
        'game_short_name'   => [2,0],
      ],
      # }}}
      'ChatMember' => [# {{{
        'status'        => [2,1],
        'user'          => [0,1,'User'],
      ],
      'ChatMemberOwner' =>
      [
        'is_anonymous'  => [3,1],
        'custom_title'  => [2,0],
      ],
      'ChatMemberAdministrator' =>
      [
        'can_be_edited'          => [3,1],
        'is_anonymous'           => [3,1],
        'can_manage_chat'        => [3,1],
        'can_delete_messages'    => [3,1],
        'can_manage_video_chats' => [3,1],
        'can_restrict_members'   => [3,1],
        'can_promote_members'    => [3,1],
        'can_change_info'        => [3,1],
        'can_invite_users'       => [3,1],
        'can_post_messages'      => [3,0],
        'can_edit_messages'      => [3,0],
        'can_pin_messages'       => [3,0],
        'can_manage_topics'      => [3,0],
        'custom_title'           => [2,0],
      ],
      'ChatMemberRestricted' =>
      [
        'is_member'                 => [3,1],
        'can_send_messages'         => [3,1],
        'can_send_audios'           => [3,1],
        'can_send_documents'        => [3,1],
        'can_send_photos'           => [3,1],
        'can_send_videos'           => [3,1],
        'can_send_video_notes'      => [3,1],
        'can_send_voice_notes'      => [3,1],
        'can_send_polls'            => [3,1],
        'can_send_other_messages'   => [3,1],
        'can_add_web_page_previews' => [3,1],
        'can_change_info'           => [3,1],
        'can_invite_users'          => [3,1],
        'can_pin_messages'          => [3,1],
        'can_manage_topics'         => [3,1],
        'until_date'                => [1,1],
      ],
      'ChatMemberBanned' =>
      [
        'until_date' => [1,1],
      ],
      # }}}
      'ChatMemberUpdated' => [# {{{
        'chat'            => [0,1,'Chat'],
        'from'            => [0,1,'User'],
        'date'            => [1,1],
        'old_chat_member' => [0,1,'ChatMember'],
        'new_chat_member' => [0,1,'ChatMember'],
        'invite_link'     => [0,0],
        'via_chat_folder_invite_link' => [3,0],
      ],
      # }}}
    ];
    static $CHECK = [
      'ChatMemberUpdated' => function(object $o, string $from): string # {{{
      {
        static $PROP = [
          'old_chat_member',
          'new_chat_member'
        ];
        foreach ($PROP as &$k)
        {
          $m = $o->$k;
          $e = match ($m->status)
          {
            'creator'       => Bot::typecheck(
              $m, 'ChatMemberOwner',
              ($from ? $from.'.'.$k : $k)
            ),
            'administrator' => Bot::typecheck(
              $m, 'ChatMemberAdministrator',
              ($from ? $from.'.'.$k : $k)
            ),
            'restricted'    => Bot::typecheck(
              $m, 'ChatMemberRestricted',
              ($from ? $from.'.'.$k : $k)
            ),
            'kicked'        => Bot::typecheck(
              $m, 'ChatMemberBanned',
              ($from ? $from.'.'.$k : $k)
            ),
            'member','left' => '',
            default         => (
              'unknown '.($from ? $from.'.'.$k : $k).
              '.status='.$m->status
            )
          };
          if ($e) {
            return $e;
          }
        }
        return '';
      }
      # }}}
    ];
    # initialize
    # {{{
    if (!$REQUIRED)
    {
      foreach ($OBJECT as $a => &$c)
      {
        $REQUIRED[$a] = [];
        $RECUR[$a] = [];
        foreach ($c as $b => &$d)
        {
          if ($d[1]) {
            $REQUIRED[$a][] = $b;
          }
          if ($d[0] === 0 && isset($d[2])) {
            $RECUR[$a][$b] = $d[2];
          }
          $d = $d[0];
        }
      }
      unset($c, $d);
    }
    # }}}
    # check required properties
    foreach($REQUIRED[$name] as $a)
    {
      if (!isset($o->$a)) {
        return 'missing "'.($from ? $from.'.'.$a : $a).'" property';
      }
    }
    # iterate object properties and
    # check value types
    $c = &$OBJECT[$name];
    $d = &$RECUR[$name];
    foreach($o as $a => &$b)
    {
      # skip unknown
      if (!isset($c[$a])) {
        continue;
      }
      # check
      $ok = match ($c[$a]) {
        0 => is_object($b),
        1 => is_int($b),
        2 => is_string($b),
        3 => is_bool($b),
        4 => is_array($b),
        5 => is_float($b) || is_int($b)
      };
      if (!$ok) {
        return 'invalid "'.($from ? $from.'.'.$a : $a).'" type';
      }
      # check not an object recursion
      if ($c[$a] || !isset($d[$a])) {
        continue;
      }
      # recurse
      $e = self::typecheck(
        $b, $d[$a], ($from ? $from.'.'.$a : $a)
      );
      if ($e) {
        return $e;
      }
    }
    # all correct, check extra
    return isset($CHECK[$name])
      ? $CHECK[$name]($o, $from)
      : '';
  }
  # }}}
  static function typeof(# {{{
    string $name, object $o
  ):string
  {
    static $PROBE = [
      'Update' => function(object $o): string # {{{
      {
        return '';
      },
      # }}}
      'Message' => function(object $o): string # {{{
      {
        return match (true)
        {
          isset($o->text)       => 'text',
          isset($o->animation)  => 'animation',
          isset($o->audio)      => 'audio',
          isset($o->document)   => 'document',
          isset($o->photo)      => 'photo',
          isset($o->sticker)    => 'sticker',
          isset($o->video)      => 'video',
          isset($o->video_note) => 'video_note',
          isset($o->voice)      => 'voice',
          isset($o->contact)    => 'contact',
          isset($o->dice)       => 'dice',
          isset($o->game)       => 'game',
          isset($o->poll)       => 'poll',
          isset($o->venue)      => 'venue',
          isset($o->location)   => 'location',
          isset($o->invoice)    => 'invoice',
          default               => ''
        };
      },
      # }}}
    ];
    return isset($PROBE[$name])
      ? $PROBE[$name]($o)
      : '';
    }
  }
  # }}}
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
  function sync(): void # {{{
  {
    static $TIMEOUT = [
      'user' => 2*60*1000,
      'chat' => 4*60*1000,
      'file' => [
        4*60*1000,# save
        6*60*1000,# unload
      ],
    ];
    $t = hrtime(true);
    $i = $TIMEOUT['user'];
    foreach ($this->users as $id => $o)
    {
      if ($o->timeDelta($t) >= $i)
      {
        unset($this->users[$id]);
        $o->unload();
      }
    }
    $i = $TIMEOUT['chat'];
    foreach ($this->chats as $id => $o)
    {
      if ($o->timeDelta($t) >= $i)
      {
        unset($this->chats[$id]);
        $o->unload();
      }
    }
    $a = &$TIMEOUT['file'];
    $this->file->sync($t, $a[0], $a[1]);
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
############### aggregates
# event {{{
abstract class BotEvent extends BotConfigAccess # {{{
{
  use TimeTouchable;
  # constructors {{{
  static function fromUpdate(object $bot, object $o): object # {{{
  {
    # check object type
    static $TYPE = 'Update';
    if (!$noCheck && ($e = $bot::typecheck($TYPE, $o))) {
      return ErrorEx::fail('incorrect '.$TYPE, $e);
    }
    # select
    if (isset($o->callback_query)) {
      return self::fromCallback($bot, $o->callback_query, true);
    }
    if (isset($o->message)) {
      return self::fromMessage($bot, $o->message, true);
    }
    if (isset($o->my_chat_member)) {
      return self::fromMember($bot, $o->my_chat_member, true);
    }
    if (isset($o->chat_member)) {
      return self::fromMember($bot, $o->chat_member, true);
    }
    return ErrorEx::skip();# unknown
  }
  # }}}
  static function fromCallback(# {{{
    object $bot, object $o, bool $noCheck = false
  ):object
  {
    # check object type
    static $TYPE = 'CallbackQuery';
    if (!$noCheck && ($e = $bot::typecheck($TYPE, $o))) {
      return ErrorEx::fail('incorrect '.$TYPE, $e);
    }
    # check unbound (no source message)
    if (!isset($o->message)) {
      return ErrorEx::skip();
    }
    # handle bound callback
    $from = $o->from;
    $chat = $o->message->chat;
    return match (true)
    {
      isset($o->data) =>
        BotCallbackEvent::new($bot, $o, $from, $chat),
      isset($o->game_short_name) =>
        BotGameEvent::new($bot, $o, $from, $chat),
      default =>
        ErrorEx::skip()
    };
  }
  # }}}
  static function fromMessage(# {{{
    object $bot, object $o, bool $noCheck = false
  ):object
  {
    # check object type
    static $TYPE = 'Message';
    if (!$noCheck && ($e = $bot::typecheck($TYPE, $o))) {
      return ErrorEx::fail('incorrect '.$TYPE, $e);
    }
    # check unknown user or type
    if (!isset($o->from) ||
        !($type = $bot::typeof($TYPE, $o)))
    {
      return BotServiceEvent::new(
        $bot, $o, $o->from, $o->chat
      );
    }
    # construct user input
    return (isset($o->text) && $o->text[0] === '/')
      ? BotCommandEvent::construct($bot, $o, $type)
      : BotInputEvent::construct($bot, $o, $type);
  }
  # }}}
  static function fromMember(# {{{
    object $bot, object $o, bool $noCheck = false
  ):object
  {
    # check object type
    static $TYPE = 'ChatMemberUpdated';
    if (!$noCheck && ($e = $bot::typecheck($TYPE, $o))) {
      return ErrorEx::fail('incorrect '.$TYPE, $e);
    }
    # construct
    return BotMemberEvent::new($bot, $o, $o->from, $o->chat);
  }
  # }}}
  # }}}
  # base constructor {{{
  static function new(
    object $bot, object $data, ?object $user, ?object $chat
  ):object
  {
    # determine user and chat
    $user = BotUser::construct($bot, $user);
    $chat = BotChat::construct($bot, $user, $chat);
    if ($chat === null)
    {
      return ErrorEx::fail(
        'unable to construct chat object'
      );
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
  function __construct(
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
  function asap(): self # {{{
  {
    # clear sequence identifier
    $this->id = '';
    return $this;
  }
  # }}}
  function warning(string $s): self # {{{
  {
    $this->log->warnInput($s);
    return $this;
  }
  # }}}
  function response(): ?object # {{{
  {
    # generate the response with result logging
    return ($p = $this->responsePromise())
      ? $p->thenFunc($this->responseLog(...))
      : null;
  }
  function responseLog(object $r): void
  {
    # display time of completion in summary
    $this->log->result($r,
      ($r->ok ? 'ok' : 'fail'),
      $this->timeDelta().'ms'
    );
  }
  # }}}
  abstract function responsePromise(): ?object;
}
# }}}
# CallbackQuery
class BotCallbackEvent extends BotEvent # {{{
{
  const LOGNAME = 'callback';
  const TIMEOUT = 14500;# max=15000ms
  function responsePromise(): ?object # {{{
  {
    # prepare
    $bot  = $this->bot;
    $data = &$this->data->data;
    $id   = $this->data->message->message_id;
    $node = $this->chat->nodeOfMessage($id);
    # check source
    if (!$node)
    {
      $this->log->warnInput(
        "ignored\n".
        'unknown source, message id='.
        $id.' is not bound to any node'
      );
      return null;
    }
    # check empty
    if (strlen($data) <= 1) {
      return $this->asap()->answer();
    }
    # parse callback data
    if (!($a = $bot::parseCallback($data)))
    {
      $this->log->warnInput(
        "ignored\n".
        'incorrect callback data=['.$data.']'
      );
      return null;
    }
    # check no operation
    if ($a[2] === 'nop') {
      return $this->asap()->answer();
    }
    # check ticks
    if ($a[1] !== $node->tick)
    {
      $this->log->warnInput(
        "ignored\n".
        'node has changed (tick mismatch)'
      );
      return null;
    }
    # create query and check item
    $q = BotNodeQuery::fromCallback($bot, $a);
    if ($q->item === null)
    {
      $this->log->warnInput(
        "ignored\n".
        'unknown target, item id='.
        $a[0].' is not found'
      );
      return null;
    }
    # complete
    $this->log->infoInput($data);
    return $node
      ->eventQuery($this, $q)
      ->thenFunc($this->responseAnswer(...));
  }
  # }}}
  function responseAnswer(object $r): ?object # {{{
  {
    # answering the callback is only possible when
    # - the query did not timed out
    # - the message still persist (was not deleted)
    # - the message didn't change its markup (TODO)
    # check invalid
    if ($this->timeDelta() > self::TIMEOUT) {
      return null;
    }
    $id   = $this->data->message->message_id;
    $node = $this->chat->nodeOfMessage($id);
    if ($node === null) {
      return null;
    }
    # valid
    return $this->answer();
  }
  # }}}
  function answer(# {{{
    string $text  = '',
    bool   $alert = false
  ):object
  {
    $a = ['callback_query_id' => $this->data->id];
    if ($text !== '')
    {
      $a['text']       = $text;
      $a['show_alert'] = $alert;
    }
    return $this->bot->api->answerCallbackQuery($a);
  }
  # }}}
}
# }}}
class BotGameEvent extends BotEvent # {{{
{
  const LOGNAME = 'game';
  function responsePromise(): ?object # {{{
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
# Message
class BotServiceEvent extends BotEvent # {{{
{
  const LOGNAME = 'service';
  function responsePromise(): ?object # {{{
  {
    return null;
  }
  # }}}
}
# }}}
class BotInputEvent extends BotEvent # {{{
{
  const LOGNAME = 'input';
  public $type;
  static function construct(# {{{
    object $bot, object $msg, string $type
  ):object
  {
    $e = self::new(
      $bot, $msg, $msg->from, $msg->chat
    );
    if (ErrorEx::is($e)) {
      $e->type = $type;
    }
    return $e;
  }
  # }}}
  function responsePromise(): ?object # {{{
  {
    # check not replied
    if (!($m = $this->data->reply_to_message ?? null))
    {
      return $this # INDIRECT
        ->chat->eventInput($this)
        ->thenFunc($this->complete(...));
    }
    # check not replied to a bot
    if (!isset($m->from) || !$m->from->is_bot)
    {
      return $this # EXCHANGE
        ->chat->eventInputFilter($this)
        ->thenFunc($this->complete(...));
    }
    # check replied to another bot
    if (($id = $m->from->id) !== $this->bot->id)
    {
      $this->warning('addressed to another bot id='.$id);
      return null;# ignore
    }
    # get the node of replied message
    $id = $m->message_id;
    if (!($node = $this->chat->nodeOfMessage($id)))
    {
      return $this
        ->warning(
          'unknown target, replied message id='.
          $id.' is not bound'
        )
        ->asap()->cleanup();
    }
    # check input accepted
    if ($node['type.input'])
    {
      return $node # DIRECT
        ->eventInput($this)
        ->thenFunc($this->complete(...));
    }
    # cleanup
    return $this
      ->warning('not accepted')
      ->asap()->cleanup();
  }
  # }}}
  function cleanup(): object # {{{
  {
    return $this->bot->api->deleteMessage([
      'chat_id'    => $this->chat->id,
      'message_id' => $this->data->message_id
    ]);
  }
  # }}}
  function complete(object $r): ?object # {{{
  {
    # TODO: check successful in group chat
    #$r->ok;
    return $this->chat->isUser()
      ? $this->cleanup()
      : null;
  }
  # }}}
}
# }}}
class BotCommandEvent extends BotInputEvent # {{{
{
  const LOGNAME = 'command';
  function responsePromise(): ?object # {{{
  {
    # prepare
    $msg = $this->data;
    $bot = $this->bot;
    # parse input
    if (!($a = $bot::parseCommand($msg->text)))
    {
      return $this
        ->warning('incorrect syntax')
        ->asap()->cleanup();
    }
    # check bot name specified
    if ($a[2])
    {
      # check in private chat
      if ($this->chat->isUser())
      {
        return $this
          ->warning('bot addressing doesnt make sense')
          ->asap()->cleanup();
      }
      # check not addressed to this bot
      if ($a[2] !== $bot['name'])
      {
        $this->warning('addressed to another bot');
        return null;
      }
    }
    # check item
    if (!($item = $bot->cmd[$a[0]]))
    {
      return $this
        ->warning('uknown item')
        ->asap()->cleanup();
    }
    # complete
    return $this
      ->chat->eventCommand($this, $item, $a[1])
      ->thenFunc($this->complete(...));
  }
  # }}}
  function warning(string $s): self # {{{
  {
    $text = &$this->data->text;
    $this->log->warnInput(
      $s."\n".
      ((strlen($text) > 65)
        ? substr($text, 0, 65).'..'
        : $msg->text)
    );
    return $this;
  }
  # }}}
  function complete(object $r): ?object # {{{
  {
    return $this->cleanup();
  }
  # }}}
}
# }}}
# ChatMemberUpdated
class BotMemberEvent extends BotEvent # {{{
{
  const LOGNAME = 'member';
  function responsePromise(): ?object # {{{
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
  static function construct(# {{{
    object $bot, ?object $user
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
  function lang(): string # {{{
  {
    return $this->bot->text->lang(
      $this->info->language_code ?? ''
    );
  }
  # }}}
  function todo(object $req): int # {{{
  {
    return 0;
  }
  # }}}
  function unload(): void # {{{
  {
  }
  # }}}
}
# }}}
# chat {{{
abstract class BotChat implements ArrayAccess # {{{
{
  use TimeTouchable;
  # {{{
  const
    FILE_INFO = 'info.json',
    FILE_VIEW = 'view.json';
  public
    $name,$username,$logname,$log,
    $files,$lang,$info,$view,$conf,$opts;
  # }}}
  static function construct(# {{{
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
    return $I->init($user, $chat)->load($user)
      ? $bot->chats[$id] = $I->timeTouch()
      : null;
  }
  # }}}
  function __construct(# {{{
    public object $bot,
    public string $id
  ) {}
  # }}}
  function load(object $user): bool # {{{
  {
    # prepare
    $bot  = $this->bot;
    $file = $bot->file;
    $dir  = $this->dir();
    # set logger
    $this->log = $bot->log
      ->new(static::LOGNAME)
      ->new($this->logname);
    # check working directory
    if (!$dir) {# TODO
      return true;
    }
    if (!$file->dirCheckMake($dir)) {
      return false;
    }
    # load chat information
    $path = $dir.self::FILE_INFO;
    if (!($info = $file->data($path, 0, 2|4))) {
      return false;
    }
    if ($info->isEmpty())
    {
      # initialize
      # request chat details
      $a = $bot->api->send('getChat', [
        'chat_id' => $this->id
      ]);
      if (!$a)
      {
        $this->log->error('failed to get details');
        return false;
      }
      # report and store
      $this->log->infoObject($a, 'new');
      $info[0] = $this->isUser()
        ? $user->lang()
        : $bot->text->lang();
      $info[1] = $a;
      $info[2] = $user->info;
    }
    # load view data
    $path = $dir.self::FILE_VIEW;
    if (!($view = $file->data($path, 1, 1|4))) {
      return false;
    }
    # initialize
    $view->isEmpty() &&
      $view->set([[], [], []]);
    # re-construct nodes
    $view[0]->filter(function(array &$a) use ($bot): bool
    {
      $a = BotNode::reconstruct($bot, $a);
      return $a === null;
    });
    # tune configuration objects
    $view[1]->restruct(1);
    $view[2]->restruct(1);
    #$f = (function(&$v, $i, $k) use ($bot): bool {
    #  return isset($bot->cmd[$k]);
    #});
    #$view[1]->restruct(1)->filter($f);
    #$view[2]->restruct(1)->filter($f);
    # complete
    $this->files = [$info, $view];
    $this->lang  = $info[0];
    $this->info  = $info[1];
    $this->view  = $view[0];
    $this->conf  = $view[1];
    $this->opts  = $view[2];
    return true;
  }
  # }}}
  function nodeForInput(): ?object # {{{
  {
    # get active node and check it accepts input
    if (($node = $this->view[0]) &&
        $node->item['type.input'])
    {
      return $node;
    }
    return null;
  }
  # }}}
  function nodeForItem(object $item): ?object # {{{
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
  function nodeOfMessage(int $id): ?object # {{{
  {
    foreach ($this->view->arr as $node)
    {
      if ($node->msgsGet($id)) {
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
  function unload(): void # {{{
  {
    if ($this->files)
    {
      foreach ($this->files as $o) {
        $o->unload = true;
      }
      $this->files = null;
    }
  }
  # }}}
  # promise constructors
  function eventInput(object $e): object # {{{
  {
    return ($node = $this->nodeForInput())
      ? $node->eventInput($e)
      : $this->eventInputFilter($e);
  }
  # }}}
  function eventInputFilter(object $e): object # {{{
  {
    # get target item
    static $SKIP = ErrorEx::warn('skip');
    if (!($item = $this->bot->cmd['/MESSAGE'])) {
      return Promise::Fail($SKIP);
    }
    # compose
    return Promise
      ::Value(new BotItemCtx($e))
      ->thenFunc(
        $item->refresh(...),
        new BotItemQuery($e->type, $e->data)
      );
  }
  # }}}
  function eventCommand(# {{{
    object $e, object $item, string &$args
  ):object
  {
    # create node query
    $q = new BotNodeQuery(
      $item, 0, new BotItemQuery('open', $args)
    );
    # compose executor
    return ($node = $this->nodeForItem($item))
      ? $node->event($e, $q)
      : BotNode::create($e, $q);
  }
  # }}}
  ###
  function isUser():  bool {return false;}
  function isGroup(): bool {return false;}
  function isChan():  bool {return false;}
  abstract function dir(): string;
  abstract function init(object $user, ?object $chat): self;
}
# }}}
class BotUserChat extends BotChat # {{{
{
  const LOGNAME = 'user';
  function isUser(): bool {
    return true;
  }
  function dir(): string
  {
    return $this->bot->cfg->dirUsr.
      $this->id.DIRECTORY_SEPARATOR;
  }
  function init(object $user, ?object $chat): self
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
  function isGroup(): bool {
    return true;
  }
  function dir(): string
  {
    return $this->bot->cfg->dirGrp.
      $this->id.DIRECTORY_SEPARATOR;
  }
  function init(object $user, ?object $chat): self
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
  function isChan(): bool {
    return true;
  }
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
  function init(object $user, ?object $chat): self
  {
    $this->name     = '';
    $this->username = '';
    $this->logname  = 'inline';
    return $this;
  }
}
# }}}
# }}}
# node {{{
class BotNode implements JsonSerializable, ArrayAccess
{
  const # {{{
    LIFETIME  = 48*60*60,
    FRESHTIME = 38*60*60,# 80% of lifetime
    LOCKTIME  = 30*1000000000,# nsec, lock is valid
    WAITTIME  = 5*1000000000,# nsec, waiting for unlock
    MAX_TICK  = 99;
  # }}}
  # constructors {{{
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
    return new self($item, $a[1], $a[2], $a[3]);
  }
  # }}}
  static function construct(# {{{
    object $ctx, ?object $error = null
  ):?self
  {
    try
    {
      # render messages and create new node
      return ($msgs = $ctx->render())
        ? new self($ctx->item, $msgs)
        : null;
    }
    catch (Throwable $e)
    {
      $error = ErrorEx::from($e);
      return null;
    }
  }
  # }}}
  function spawn(# {{{
    object $ctx, ?object &$error = null
  ):?self
  {
    # create new node
    if (($o = self::construct($ctx, $error)) === null) {
      return null;
    }
    # inherit and advance tick number
    if (($o->tick = 1 + $this->tick) > self::MAX_TICK) {
      $o->tick = 2;# rotate
    }
    return $o;
  }
  # }}}
  function __construct(
    public object $item,
    public array  &$msgs,
    public int    $tick    = 1,
    public int    $created = 0,
    public int    $locked  = 0,
    public int    $time    = 0
  ) {}
  # }}}
  # utils {{{
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
      $this->item->id, $this->msgs,
      $this->tick, $this->created
    ];
  }
  # }}}
  # [] access {{{
  function offsetExists(mixed $k): bool {
    return isset($this->item[$k]);
  }
  function offsetGet(mixed $k): mixed {
    return $this->item[$k];
  }
  function offsetSet(mixed $k, mixed $v): void
  {}
  function offsetUnset(mixed $k): void
  {}
  # }}}
  function isEmpty(): bool # {{{
  {
    return count($this->msgs) === 0;
  }
  # }}}
  function isActive(object $chat): bool # {{{
  {
    return $chat->view[0] === $this;
  }
  # }}}
  function isLocked(int &$hrtime): bool # {{{
  {
    return ($i = $this->locked) &&
           ($hrtime - $i < self::LOCKTIME);
  }
  # }}}
  function isFresh(int $t = 0): bool # {{{
  {
    $t = $t ?: self::FRESHTIME;
    return (time() - $this->created) <= $t;
  }
  # }}}
  function isDeletable(): bool # {{{
  {
    return (time() - $this->created) < self::LIFETIME;
  }
  # }}}
  function isEqual(object $node): bool # {{{
  {
    # check items
    if ($this->item !== $node->item) {
      return false;
    }
    # prepare
    $i = count($m0 = &$this->msgs);
    $j = count($m1 = &$node->msgs);
    # check message counts
    if ($i !== $j) {
      return false;
    }
    # check message hashes
    for ($i = 0; $i < $j; ++$i)
    {
      if ($m0[$i]->hash !== $m1[$i]->hash) {
        return false;
      }
    }
    return true;# nodes are equal
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
  # }}}
  # message group {{{
  function msgsGet(int $id): ?object # {{{
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
  function msgsHash(): string # {{{
  {
    $a = '';
    foreach ($this->msgs as $msg) {
      $a .= $msg->hash;
    }
    return $a;
  }
  # }}}
  function msgsSend(object $chat): object # {{{
  {
    # compose message actions
    $list = [];
    foreach ($this->msgs as $msg) {
      $list[] = $msg->send($chat);
    }
    # to have a safety gap,
    # set creation timestamp before creation
    $this->created = time();
    # messages must appear in order
    return Promise::OneBreak($list);
  }
  # }}}
  function msgsDelete(object $chat, ?bool &$isDeletable = null): ?object # {{{
  {
    # determine flag
    if ($isDeletable === null) {
      $isDeletable = $this->isDeletable();
    }
    # collect message actions
    $list = [];
    if ($isDeletable)
    {
      # for the fresh node,
      # messages can be safely removed
      foreach ($this->msgs as $msg) {
        $list[] = $msg->delete($chat);
      }
    }
    else
    {
      # for the stale node,
      # zapping clears content of each message,
      # which makes it look empty/neutral
      foreach ($this->msgs as $msg) {
        $list[] = $msg->zap($chat);
      }
    }
    # actions may complete in no particular order
    return Promise::AllBreak($list);
  }
  # }}}
  function msgsEdit(object $chat, object $node): object # {{{
  {
    # prepare
    $up = [];
    $n0 = count($m0 = &$this->msgs);# current
    $n1 = count($m1 = &$node->msgs);# new
    # update messages only when they differ
    for ($i = 0; $i < $n1; ++$i)
    {
      if ($m0[$i]->hash !== $m1[$i]->hash) {
        $up[] = $m0[$i]->edit($chat, $m1[$i]);
      }
    }
    # delete extra messages
    for ($i; $i < $n0; ++$i) {
      $up[] = $m0[$i]->delete($chat);
    }
    # complete
    return Promise::AllBreak($up);
  }
  # }}}
  # }}}
  # promise constructors {{{
  function eventInput(object $e): object # {{{
  {
    return $this->event($e, new BotNodeQuery(
      $this->item, $this->tick,
      new BotItemQuery('INPUT', $e->data)
    );
  }
  # }}}
  function eventQuery(object $e, object $q): object # {{{
  {
    # check native
    if ($this->item === $q->item) {
      return $this->event($e, $q);
    }
    # select proper variant (native or foreign)
    return ($node = $e->chat->nodeForItem($q->item))
      ? (($node === $this)
        ? $this->event($e, $q)
        : $node->event($e, $q(0)))
      : self::create($e, $q(0));
  }
  # }}}
  function event(object $e, object $q, int $n = 0): object # {{{
  {
    # update access time
    $this->time = (++$n === 1)
      ? $e->time
      : hrtime(true);
    # check locked (using event time)
    if ($this->isLocked($e->time))
    {
      # postpone until limit reached
      if (($this->time - $e->time) < self::WAITTIME)
      {
        return Promise::Call(
          $this->event(...), $e, $q, $n
        );
      }
      # cancel
      return Promise::Fail(ErrorEx::warn(
        'item',$q->item->path,
        'event','ignored, node is busy/locked'
      ));
    }
    # TODO: tick === 0
    # check ticks match
    if ($this->tick !== $q->tick)
    {
      return Promise::Fail(ErrorEx::warn(
        'item',$q->item->path,
        'event','ignored, node has changed'
      ));
    }
    # lock
    $this->locked = $this->time;
    # handle common navigation
    $renew = false;
    switch ($q->query->func) {
    case 'close':
      $q->item = null;
      break;
    case 'up':
      $q->item = $q->item->parent;
      break;
    case 'INPUT':
      $renew = (
        $this['type.activate'] &&
        !$this->isActive($e->chat)
      );
      break;
    case 'open':
      $renew = true;
      break;
    }
    # select proper operation and create promise
    $p = $q->item
      ? (($this->item === $q->item)
        ? $this->refresh($e, $q, $renew)
        : $this->update($e, $q, $renew))
      : $this->delete($e);
    # complete composition
    return $p->thenFunc($this->complete(...));
  }
  # }}}
  static function create(object $e, object $q): object # {{{
  {
    return Promise
      ::Value(new BotItemCtx($e))
      ->thenFunc($q->item->update(...), $q->query)
      ->okayFunc(self::createStart(...));
  }
  # }}}
  function refresh(object $e, object $q, bool $renew): object # {{{
  {
    $f = $renew
      ? $this->replaceStart(...)
      : $this->updateStart(...);
    return Promise
      ::Value(new BotItemCtx($e))
      ->thenFunc($q->item->refresh(...), $q->query)
      ->okayFunc($f);
  }
  # }}}
  function update(object $e, object $q, bool $renew): object # {{{
  {
    $f = $renew
      ? $this->replaceStart(...)
      : $this->updateStart(...);
    return Promise
      ::Value(new BotItemCtx($e))
      ->thenFunc($this->item->leave(...))
      ->okayFunc($q->item->update(...), $q->query)
      ->okayFunc($f)
      ->thenCatch(Promise
        ::Func($this->item->enter(...))
        ->okayFunc($f)
        ->thenCatch($this->deleteStart(...))
      )
      ->okayThen(Promise
        ::Func($q->item->update(...), $q->query)
        ->okayFunc($f)
        ->thenCatch(Promise
          ::Func($this->item->enter(...))
          ->okayFunc($f)
          ->thenCatch($this->deleteStart(...))
        )
      );
  }
  # }}}
  function delete(object $e): object # {{{
  {
    return Promise
      ::Value(new BotItemCtx($e))
      ->thenFunc($this->item->leave(...))
      ->okayFunc($this->deleteStart(...));
  }
  # }}}
  # }}}
  # promise actions {{{
  static function createStart(object $r): ?object # {{{
  {
    # create new node
    $ctx = $r->value;
    if ($node = self::construct($ctx, $err))
    {
      return $node
        ->msgsSend($ctx->chat)
        ->thenFunc($node->createComplete(...));
    }
    # handle failure
    if ($err)
    {
      $r->failure($err);
      return null;
    }
    # handle empty result
    $r->confirm(
      'item',$ctx->item->path,
      'create','skip'
    );
    return null;
  }
  # }}}
  function createComplete(object $r): void # {{{
  {
    if ($r->ok) {
      $r->value->chat->view->prepend($this);
    }
    $r->confirm(
      'item',$this->item->path,
      'create',''
    );
  }
  # }}}
  function deleteStart(object $r): ?object # {{{
  {
    return $this
      ->msgsDelete($r->value->chat, $isDelete)
      ->thenFunc($this->deleteComplete(...), $isDelete);
  }
  # }}}
  function deleteComplete(object $r, bool $isDelete): void # {{{
  {
    $r->ok && $r->value->chat->view->delete($this);
    $r->confirm(
      'item',$this->item->path,
      ($isDelete ? 'delete' : 'zap'),''
    );
  }
  # }}}
  function updateStart(# {{{
    object $r, ?object $node = null
  ):?object
  {
    # prepare
    $ctx = $r->value;
    if ($node === null)
    {
      # spawn new node
      if (!($node = $this->spawn($ctx, $err)))
      {
        # handle failure
        if ($err)
        {
          $r->failure($err);
          return null;
        }
        # handle empty
        return $this->deleteStart($r);
      }
      # check expired or incompatible
      if (!$this->isFresh() ||
          !$this->isCompatible($node))
      {
        return $this->replaceStart($r, $node);
      }
      # check no difference
      $isRefresh = ($this->item === $node->item);
      if ($isRefresh && $this->isEqual($node))
      {
        $r->confirm(
          'item',$this->item->path,
          'refresh','skip'
        );
        return null;
      }
    }
    else {# comes from replace
      $isRefresh = false;
    }
    # compose
    return $this
      ->msgsEdit($ctx->chat, $node)
      ->thenFunc(
        $this->updateComplete(...),
        $node, $isRefresh
      );
  }
  # }}}
  function updateComplete(# {{{
    object $r, object $node, bool $isRefresh
  ):void
  {
    if ($r->ok) {
      $r->value->chat->view->replace($this, $node);
    }
    if ($isRefresh)
    {
      $r->confirmGroup(
        'item',$this->item->path,
        'refresh',''
      );
    }
    else
    {
      $r->confirmGroup(
        'item',$this->item->path,
        'update',$node->item->path
      );
    }
  }
  # }}}
  function replaceStart(# {{{
    object $r, ?object $node = null
  ):?object
  {
    # prepare
    $ctx  = $r->value;
    $chat = $ctx->chat;
    if ($node === null)
    {
      # create new node
      if (!($node = $this->spawn($ctx, $err)))
      {
        # handle failure
        if ($err)
        {
          $r->failure($err);
          return null;
        }
        # handle empty
        return $this->deleteStart($r);
      }
      # check compatible
      if ($this->isFresh() &&
          $this->isCompatible($node))
      {
        return $this->updateStart($r, $node);
      }
      # check renewal is excessive
      if (($isRenewal = $this->isEqual($node)) &&
          $this->isActive($chat) &&
          $this->isFresh(5))
      {
        $r->confirm(
          'item',$this->item->path,
          'renew','skip'
        );
        return null;
      }
    }
    else {# comes from update
      $isRenewal = true;
    }
    # compose
    return Promise
      ::OneBreak([
        $node->msgsSend($chat),
        $this->msgsDelete($chat)
      ])
      ->thenFunc(
        $this->replaceComplete(...),
        $node, $isRenewal
      );
  }
  # }}}
  function replaceComplete(# {{{
    object $r, object $node, bool $isRenewal
  ):void
  {
    # ignore failed deletion when creation succeed
    if (!$r->ok && ($a = $r->track[0]->group) &&
        count($a) === 2 && $a[1]->ok)
    {
      $r->success()->message(
        'ignore', 'unsuccessful delete/zap'
      );
    }
    # update chat configuration
    $r->ok &&
    $r->value->chat->view
      ->delete($this)
      ->prepend($node);
    # complete
    if ($isRenewal)
    {
      $r->confirm(
        'item',$this->item->path,
        'renew',''
      );
    }
    else
    {
      $r->confirm(
        'item',$this->item->path,
        'replace',$node->item->path
      );
    }
  }
  # }}}
  function complete(): void # {{{
  {
    $this->locked = 0;
  }
  # }}}
  # }}}
}
class BotNodeQuery
{
  # {{{
  static function fromCallback(object $bot, array &$q): self
  {
    return new self(
      $bot->cmd[$q[0]], $q[1],
      new BotItemQuery($q[2], $q[3])
    );
  }
  function __construct(
    public ?object $item,
    public int     $tick,
    public object  $query
  ) {}
  function __invoke(int $tick): self
  {
    $this->tick = $tick;
    return $this;
  }
  # }}}
}
# }}}
# item {{{
abstract class BotItem implements ArrayAccess # {{{
{
  # constructor {{{
  const OPTION = [
    'type.enter'    => false,
    'type.input'    => false,
    'type.activate' => true,# upon input
    'type.leave'    => false,
    'data.name'     => '',
    'data.scope'    => 'chat',
    'data.fetch'    => 0,# -1=always,0=never,X=seconds
  ];
  public $defs,$caps,$texts,$parent,$root,$children;
  function __construct(
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
    $this->caps = ArrayStackValue::new()
      ->pushRef($bot->text->caps)
      ->pushRef($spec['caps']);
    # set texts
    foreach ($spec['text'] as $lang => &$node) {
      $node = new BotItemText($this, $lang, $node);
    }
    $this->texts = &$spec['text'];
    # apply specific setup
    $this->restruct();
  }
  function restruct(): void
  {}
  # }}}
  # utils {{{
  function __debugInfo(): array # {{{
  {
    return [
      $this::class => $this->path.($this->hand ? '(*)' : '')
    ];
  }
  # }}}
  function __invoke(object $ctx, object $q): ?object # {{{
  {
    try {
      return $this->hand?->call($ctx, $q);
    }
    catch (Throwable $e) {
      return ErrorEx::from($e);
    }
  }
  # }}}
  function parseQuery(string $s): ?array # {{{
  {
    # parse
    $bot = $this->bot;
    if (($a = $bot::parseQuery($s)) === null) {
      return null;
    }
    # set item
    $a[0] = ($a[0] === '')
      ? $this
      : (($a[0][0] === '/')
        ? $bot->cmd[$a[0]]
        : $bot->cmd[$this->path.'/'.$a[0]]);
    # complete
    return $a[0] ? $a : null;
  }
  # }}}
  function filename(): string # {{{
  {
    return str_replace(
      '/', '-', substr($this->path, 1)
    );
  }
  # }}}
  function pathname(string &$text): string # {{{
  {
    return $this->filename().'-'.hash('xxh3', $text);
  }
  # }}}
  function data(object $ctx): object # {{{
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
  # }}}
  # [] access {{{
  function offsetExists(mixed $k): bool {
    return isset($this->defs[$k]);
  }
  function offsetGet(mixed $k): mixed
  {
    return $this->spec[$k]
      ?? $this->defs[$k]
      ?? null;
  }
  function offsetSet(mixed $k, mixed $v): void
  {}
  function offsetUnset(mixed $k): void
  {}
  # }}}
  # context actions {{{
  function enter(object $r): ?object # {{{
  {
    static $E = new BotItemQuery('ENTER');
    return $this['type.enter']
      ? $r->value($E, $this)
      : null;
  }
  # }}}
  function refresh(object $r, object $q): object # {{{
  {
    return Promise
      ::From($r->value($q, $this))
      ->okayFunc($this->redirect(...), $q);
  }
  # }}}
  function update(object $r, object $q): object # {{{
  {
    return Promise
      ::From($this->enter($r))
      ->okayFunc($r->value, $q, $this)
      ->okayFunc($this->redirect(...), $q);
  }
  # }}}
  function redirect(# {{{
    object $r, object $q, int $n = 0
  ):?object
  {
    # check result
    if (!($item = $q->result()) ||
        !($item instanceof BotItem))
    {
      return null;
    }
    # check hop number
    if (++$n >= 3)
    {
      $r->failure(ErrorEx::fail(
        'redirection limit reached'
      ));
      return null;
    }
    # continue
    $r->message('redirect', $item->path);
    return Promise
      ::From($this->leave($r))
      ->okayFunc($item->enter(...))
      ->okayFunc($r->value, $q, $item)
      ->okayFunc($item->redirect(...), $q, $n);
  }
  # }}}
  function query(# {{{
    object $r, string $func, mixed &$args = null
  ):?object
  {
    return $r->value(
      new BotItemQuery($func, $args), $this
    );
  }
  # }}}
  function leave(object $r): ?object # {{{
  {
    static $E = new BotItemQuery('LEAVE');
    return $this['type.leave']
      ? $r->value($E, $this)
      : null;
  }
  # }}}
  # }}}
  # extendable {{{
  function operate(object $ctx, object $q): ?object {
    return $this($ctx, $q);
  }
  abstract function render(object $ctx): array;
  # }}}
}
# }}}
class BotItemText implements ArrayAccess # {{{
{
  # constructor {{{
  const TEMPLATE_PREFIX = '#';
  private $template = '',$tp,$texts;
  function __construct(
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
    $this->texts = ArrayStackValue::new()
      ->pushRef($base)
      ->pushRef($spec);
  }
  # }}}
  # access {{{
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
  function __construct(
    public string $func  = '',
    public mixed  &$args = null,
    public mixed  $res   = null
  ) {}
  function __invoke(mixed $args = null): self
  {
    # reassign parameter and clear result
    if (($this->args = &$args) === null) {
      $this->res = null;
    }
    return $this;
  }
  function init(string $func, mixed $args = null): self
  {
    $this->func = $func;
    $this->args = &$args;
    $this->res  = null;
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
  # constructor {{{
  public
    $log,$input,$user,$chat,$lang,
    $item,$caps,$text,$conf,$opts;
  function __construct(object $e)
  {
    $this->log   = $e->log;
    $this->input = $e->data;
    $this->user  = $e->user;
    $this->chat  = $e->chat;
    $this->lang  = $e->chat->lang;
  }
  # }}}
  # access {{{
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
  # service shortcuts {{{
  function __invoke(
    object $q, ?object $item = null
  ):?object
  {
    return $this
      ->select($item)
      ->operate($q);
  }
  function select(?object $item): self
  {
    if (!$item || $item === $this->item) {
      return $this;
    }
    $this->item = $item;
    $this->caps = $item->caps;
    $this->text = $item->texts[$this->lang];
    $this->conf = $this->chat->conf->obtain($item->id);
    $this->opts = new ArrayNodeTwins(
      $item, $this->chat->opts->obtain($item->id)
    );
    return $this;
  }
  function operate(object $q): ?object {
    return $this->item->operate($this, $q);
  }
  function render(): array {
    return $this->item->render($this);
  }
  # }}}
}
# }}}
class BotItemCtx_OLD implements ArrayAccess # {{{
{
  # constructor {{{
  public
    $tick,$log,$input,$user,$chat,$lang,
    $item,$caps,$text,$conf,$opts,
    $data,$vars;
  function __construct(
    object $req, int $tick = 0, ?object $item = null
  ) {
    $this->tick  = $tick,
    $this->log   = $req->log->new();
    $this->input = $req->data;
    $this->user  = $req->user;
    $this->chat  = $req->chat;
    $this->lang  = $req->lang;
    $item && $this->select($item);
  }
  # }}}
  function __invoke(object $q): ?object # {{{
  {
    $x = $this->item->operate($this, $q);
    $q();# cleanup
    return $x;
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
  function select(object $item): self # {{{
  {
    if ($item !== $this->item)
    {
      $this->log->name = $item->path;
      $this->item = $item;
      $this->caps = $item->caps;
      $this->text = $item->texts[$this->lang];
      $this->conf = $this->chat->conf->obtain($item->id);
      $this->opts = new ArrayNodeTwins(
        $item, $this->chat->opts->obtain($item->id)
      );
    }
    return $this;
  }
  # }}}
  function load(): object # {{{
  {
    if (($data = &$this->data) === null) {
      $data = $this->item->data($this);
    }
    return $data;
  }
  # }}}
  function markup(# InlineKeyboardMarkup {{{
    ?array $mkup  = null,
    ?array $flags = null
  ):object
  {
    $o = BotItemMarkup::construct(
      $this, $mkup ?? $this->opts['markup']
    );
    return BotItemMarkup::construct(
      $this, $mkup ?? $this->opts['markup'], $flags
    );
  }
  # }}}
}
# }}}
class BotItemMarkup implements Stringable # InlineKeyboardMarkup {{{
{
  static function construct(# {{{
    object $ctx, array &$mkup, ?array &$flags
  ):self
  {
    $a = [];
    foreach ($mkup as &$row)
    {
      $b = [];
      foreach ($row as &$c)
      {
        # skip constructed
        if (is_object($c))
        {
          $b[] = $c;
          continue;
        }
        # skip incorrect/empty
        if (!is_string($c) || strlen($c) === 0) {
          continue;
        }
        # construct
        $b[] = BotMarkup::construct($ctx, $c);
      }
      if ($b) {
        $a[] = $b;
      }
    }
    return new self($ctx->tick, $a);
  }
  # }}}
  function __construct(# {{{
    public int   $tick,
    public array &$list
  ) {}
  # }}}
  function __toString(): string # {{{
  {
    return $this->render(0);
  }
  # }}}
  function __invoke(): string # {{{
  {
    return $this->render($this->tick);
  }
  # }}}
  function render(int $tick): string # {{{
  {
    # transform tick
    $k = str_pad(
      dechex($tick), 2, '0', STR_PAD_LEFT
    );
    # compose array of arrays
    $a = [];
    foreach ($this->list as &$row)
    {
      $b = [];
      foreach ($row as $o)
      {
        if ($c = $o->render($k)) {
          $b[] = $c;
        }
      }
      if (count($b)) {
        $a[] = $b;
      }
    }
    # check empty
    if (count($a) === 0) {
      return '';
    }
    # complete
    return json_encode(
      ['inline_keyboard' => $a],
      JSON_UNESCAPED_UNICODE
    );
  }
  # }}}
}
# }}}
# }}}
# markup {{{
abstract class BotMarkup
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
      return new BotGameMarkup(
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
  static function op(# {{{
    object $item, string $text,
    string $func, string $args
  ):self
  {
    return new BotDataMarkup(
      $item, $text, self::qdata($func, $args)
    );
  }
  # }}}
  static function nop(# {{{
    object $item, string $text = ' '
  ):self
  {
    return new BotDataMarkup($item, $text, '!nop');
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
    return new BotDataMarkup(
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
    return new BotDataMarkup($q[0],
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
  abstract function render(string $tick): array;
}
class BotDataMarkup extends BotMarkup # {{{
{
  function render(string $tick): array
  {
    return [
      'text' => $this->text,
      'callback_data' => (
        $this->item->id.':'.$tick.$this->data
      )
    ];
  }
}
# }}}
class BotGameMarkup extends BotMarkup # {{{
{
  function render(string $tick): array
  {
    return [
      'text' => $this->text,
      'callback_game' => null
    ];
  }
}
# }}}
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
    return new static($bot, 0, static::hashData($a), $a);
  }
  function __construct(
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
  abstract function edit(object $chat, object $msg): object;
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
        return Promise::FailStop(ErrorEx
          ::fail('failed to create placeholder image')
          ->last($file)
        );
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
############### values
# base {{{
abstract class BotValue
  implements ArrayAccess,JsonSerializable
{
  static function assign(# {{{
    object $ctx, string $name, array &$spec
  ):?object
  {
    static $Q = new BotItemQuery('.value');
    # check assigned
    if (is_object($state = $ctx[$name])) {
      return null;
    }
    # prepare dummy state
    if ($state === null) {
      $state = [null];
    }
    # determine value class
    $cls = '\\'.__NAMESPACE__.'\\Bot'
      .ucfirst($spec['type'])
      .'Value';
    # construct dynamically
    $val = new $cls(
      $ctx, $name, $state, $spec,
      $ctx->opts->obtain($name)
    );
    # complete
    return Promise::From($ctx($Q($val)))
    ->thenCheck(function($r) use ($ctx,$val,&$name) {
      # initialize
      if ($e = $val->init())
      {
        $r->failure($e)->message($name, 'init');
        return false;
      }
      # assign
      $ctx[$name] = $val;
      return true;
    });
  }
  # }}}
  # constructor {{{
  public $data,$result;
  function __construct(
    public object  $ctx,
    public string  $name,
    public array   &$state,
    public array   &$spec,
    public object  $opts
  ) {
    $this->result = &$state[0];
  }
  # }}}
  # [opts/spec/base] access {{{
  final function offsetExists(mixed $k): bool {
    return isset(static::BASE[$k]);
  }
  final function offsetGet(mixed $k): mixed
  {
    return $this->opts[$k]
      ?? $this->spec[$k]
      ?? static::BASE[$k]
      ?? null;
  }
  final function offsetSet(mixed $k, mixed $v): void
  {
    if (isset(static::BASE[$k])) {
      $this->opts[$k] = $v;
    }
  }
  final function offsetUnset(mixed $k): void
  {}
  # }}}
  final function jsonSerialize(): array # {{{
  {
    return $this->state;
  }
  # }}}
  final function load(object $ctx): bool # {{{
  {
    static $Q = new BotItemQuery('.value');
    return $ctx($Q($this));
  }
  # }}}
  function text(object $ctx): string # {{{
  {
    return $ctx->text['?'.$this->name]
      ?: $ctx->text['?'.$this->type];
  }
  # }}}
  function markup(object $ctx): array # {{{
  {
    return [];
  }
  # }}}
  abstract function init(): ?ErrorEx;
}
# }}}
# list {{{
class BotListValue extends BotValue
{
  # {{{
  const BASE = [
    'func'   => 'select',# name of operation
    'key'    => 'selected',# list item key
    'minmax' => [0,1],# selected min/max
    'cols'   => 3,# columns
    'rows'   => 3,# rows
    'flexy'  => true,# shrink rows
    'cycle'  => false,# page cycle (first<=>last)
  ];
  const MARKUP = [
    'head' => [],
    'foot' => [['!prev','!next']],
  ];
  public int
    $count = 0,$page = 0,$pageLast = 0,
    $pageSize = 0,$pageCount = 1,$pageNum = 1;
  # }}}
  function init(): bool # {{{
  {
    # restore
    if ($state)
    {
      $this->value = $state[0];
      $this->page  = $state[1];
    }
    # load
    if (!$this->load($ctx)) {
      return false;
    }
    if (!$this->refine()) {
      return false;
    }
    # initialize
    $cnt   = count($this->data);
    $pSize = $this['cols'] * $this['rows'];
    $pCnt  = (int)(ceil($cnt / $pSize)) ?: 1;
    $pLast = $pCnt - 1;
    if ($this->page > $pLast) {
      $this->page = $pLast;
    }
    $this->count     = $cnt;
    $this->pageSize  = $pSize;
    $this->pageCount = $pCnt;
    $this->pageLast  = $pLast;
    $this->pageNum   = $this->page + 1;
    return true;
  }
  # }}}
  function refine(): bool # {{{
  {
    # prepare
    $a = &$this->data;
    $v = &$this->value;
    # check and transform data
    if (!is_object($a))
    {
      if (!is_array($a)) {
        return false;
      }
      if (count($a) > 0 && !is_array($a[0]))
      {
        foreach ($a as &$b) {
          $b = ['id'=>$b,'name'=>strval($b)];
        }
      }
      $a = new ArrayNode($a);
    }
    # check and transform value
    if ($a->count === 0 || $v === null)
    {
      $v = [];
      return true;
    }
    if (!is_array($v)) {
      $v = [$v];
    }
    # clear incorrect selections
    for ($i = 0, $j = count($v); $i < $j; ++$i)
    {
      if ($a->indexOfArray('id', $v[$i]) === -1) {
        array_splice($v, $i--, 1); --$j;
      }
    }
    return true;
  }
  # }}}
  function &pageItems(): array # {{{
  {
    # slice page data
    $size = $this->pageSize;
    $list = $this->data->slice(
      $this->page * $size, $size
    );
    # add selection flags
    $v = &$this->value;
    $k = $this['key'];
    foreach ($list as &$a) {
      $a[$k] = in_array($a['id'], $v, true);
    }
    return $list;
  }
  # }}}
  function &pageMarkup(object $ctx): array # {{{
  {
    # prepare
    $mkup = [];
    $data = &$this->pageItems();
    $size = count($data);
    $item = $ctx->item;
    $name = $this->name;
    $func = $this['func'];
    $cap  = $ctx->caps[$name.'.'.$func];
    $rows = $this['rows'];
    $cols = $this['cols'];
    # compose
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
          $d = BotMarkup::op(
            $item, $c, $func, $data[$i]['id']
          );
        }
        else {
          $d = BotMarkup::nop($item);
        }
        $mkup[$a][$b] = $d;
      }
      # check no more rows
      if ($i >= $size && $this['flexy']) {
        break;
      }
    }
    return $mkup;
  }
  # }}}
  function markup(object $ctx): array # {{{
  {
    if ($this->count === 0) {
      return [];
    }
    $k = $this->name.'.markup';
    $m = $ctx->item[$k] ?? self::MARKUP;
    return array_merge(
      $m['head'], $this->pageMarkup($ctx), $m['foot']
    );
    return $ctx->markup(
      $a, self::markupFlags($ctx)
    );
  }
  # }}}
  function markupFlags(object $ctx): array # {{{
  {
    static $k = 'markup.flags';
    $vars = $ctx->vars;
    $item = $ctx->item;
    if ($vars['count'] === 0) {
      return $item[$k]['empty'];
    }
    if ($vars['pageCount'] < 2) {
      return $item[$k]['single-page'];
    }
    if ($ctx['page'] === 0) {
      return $item[$k]['first-page'];
    }
    if ($ctx['page'] === $vars['pageCount'] - 1) {
      return $item[$k]['last-page'];
    }
    return [];
  }
  # }}}
}
# }}}
# string {{{
class BotStringValue extends BotValue
{
  # {{{
  const CONFIG = [
  ];
  # }}}
  function state(): array # {{{
  {
    return [$this->value];
  }
  # }}}
  function init(): bool # {{{
  {
    return true;
  }
  # }}}
  function v(object $ctx): bool # {{{
  {
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
}
# }}}
############### components
# basic {{{
class BotImgItem extends BotItem
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
    if (!$item($ctx, $Q)) {
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
class BotTxtItem extends BotItem
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
# list {{{
class BotListItem extends BotImgItem
{
  const OPTION = [# {{{
    'type.enter'  => true,
    'list.config' => [
      'type'   => 'list',
      'func'   => 'select',# list action
      'key'    => 'selected',# data key
      'minmax' => [0,1],# selected min/max
      'cols'   => 1,# columns
      'rows'   => 6,# rows
      'flexy'  => true,# shrink rows
      'cycle'  => false,# page cycle (first<=>last)
    ],
    'list.order'  => '',# order tag is a data key
    'list.desc'   => false,# order direction
    'list.select' => [
      'key'   => 'selected',
      'data'  => true,# false=>config
      'max'   => 1,# selected max, 0=unlimited
      'force' => 1,# deselect 0=no 1=first 2=last
      'min'   => 0,# selected min
    ],
    'list.open' => '',# child to open
    ###
    'markup' => [
      'head' => [],
      'foot' => [['!prev','!next'],['!up']],
    ],
    'markup.flags' => [
      'empty' => [
        'prev'=>0,'next'=>0,
        'back'=>0,'forward'=>0,
        'first'=>0,'last'=>0,
      ],
      'single-page' => [
        'prev'=>-1,'next'=>-1,
        'back'=>-1,'forward'=>-1,
        'first'=>0,'last'=>0,
      ],
      'first-page' => [
        'first'=>0
      ],
      'last-page' => [
        'last'=>0
      ],
    ],
  ];
  # }}}
  # hlp {{{
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
    $o = $ctx->opts['list.config'];
    if ($o['func'] === 'select')
    {
      $o = $ctx->item['list.select'];
      $k = $o['key'];
      $a = $o['data']
        ? ($ctx->data[1][$k] ?? [])
        : ($ctx[$k] ?? []);
      foreach ($list as &$b) {
        $b[$k] = in_array($b['id'], $a, true);
      }
    }
    return $list;
  }
  # }}}
  static function &pageMarkup(object $ctx): array # {{{
  {
    # prepare
    $mkup = [];
    $data = &self::pageItems(
      $ctx, $ctx->vars['pageSize']
    );
    if (($size = count($data)) === 0) {
      return $mkup;
    }
    $item = $ctx->item;
    $opts = $ctx->opts['list.config'];
    $cap  = $ctx->caps['list.'.$opts['func']];
    $rows = $opts['rows'];
    $cols = $opts['cols'];
    $flex = $opts['flexy'];
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
          $d = BotMarkup::op(
            $item, $c, 'id', $data[$i]['id']
          );
        }
        else {
          $d = BotMarkup::nop($item);
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
    $a = $o['data']
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
    if ($o['data']) {
      $ctx->data[1][$k] = $a;
    }
    else {
      $ctx[$k] = $a;
    }
    return true;
  }
  # }}}
  static function sort(# {{{
    array &$data, string $key, bool $desc
  ):bool
  {
    return is_string($data[0][$key])
      ? self::sortStr($data, $key, $desc)
      : self::sortNum($data, $key, $desc);
  }
  # }}}
  static function sortNum(# {{{
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
  static function sortStr(# {{{
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
    $m = $ctx->item['markup'];
    $a = [];
    foreach ($m['head'] as &$b) {
      $a[] = $b;
    }
    foreach (self::pageMarkup($ctx) as &$b) {
      $a[] = $b;
    }
    foreach ($m['foot'] as &$b) {
      $a[] = $b;
    }
    return $ctx->markup(
      $a, self::markupFlags($ctx)
    );
  }
  # }}}
  static function markupFlags(object $ctx): array # {{{
  {
    static $k = 'markup.flags';
    $vars = $ctx->vars;
    $item = $ctx->item;
    if ($vars['count'] === 0) {
      return $item[$k]['empty'];
    }
    if ($vars['pageCount'] < 2) {
      return $item[$k]['single-page'];
    }
    if ($ctx['page'] === 0) {
      return $item[$k]['first-page'];
    }
    if ($ctx['page'] === $vars['pageCount'] - 1) {
      return $item[$k]['last-page'];
    }
    return [];
  }
  # }}}
  # }}}
  # operations {{{
  function opEnter(object $ctx, object $q): bool # {{{
  {
    if ($q->args) {
      $ctx['page'] = 0;
    }
    return true;
  }
  # }}}
  function opData(object $ctx, object $q): bool # {{{
  {
    # check sorting is needed and enabled
    if (count($q->res) < 2 ||
        !($tag = $this['list.order']))
    {
      return true;
    }
    # order data
    $data = $ctx->load()[0];
    return self::sort(
      $data->arr, $tag, $this['list.desc']
    );
  }
  # }}}
  function opFirst(object $ctx): bool # {{{
  {
    if ($ctx['page'] === 0) {
      return false;
    }
    $ctx['page'] = 0;
    return true;
  }
  # }}}
  function opLast(object $ctx): bool # {{{
  {
    $vars = &self::vars($ctx);
    $last = $vars['pageCount'] - 1;
    if ($ctx['page'] === $last) {
      return false;
    }
    $ctx['page'] = $last;
    return true;
  }
  # }}}
  function opBack(object $ctx): bool # {{{
  {
    # prepare
    $a = &self::vars($ctx);
    $n = $a['pageCount'] - 1;
    # make sure current number is correct
    if (($i = $ctx['page']) > $n)
    {
      $ctx['page'] = $n;
      return true;
    }
    # decrement page index
    if ($i > 0)
    {
      $ctx['page'] = $i - 1;
      return true;
    }
    # at the first page,
    # check whether cycling is possible
    if ($n && $ctx->opts['list.config']['cycle'])
    {
      $ctx['page'] = $n;
      return true;
    }
    # skip
    return false;
  }
  # }}}
  function opForward(object $ctx): bool # {{{
  {
    # prepare
    $a = &self::vars($ctx);
    $n = $a['pageCount'] - 1;
    # make sure current number is correct
    if (($i = $ctx['page']) > $n)
    {
      $ctx['page'] = $n;
      return true;
    }
    # increment page index
    if ($i < $n)
    {
      $ctx['page'] = $i + 1;
      return true;
    }
    # at the last page,
    # check whether cycling is possible
    if ($n && $ctx->opts['list.config']['cycle'])
    {
      $ctx['page'] = 0;
      return true;
    }
    # skip
    return false;
  }
  # }}}
  function opId(object $ctx, object $q): bool # {{{
  {
    # prepare
    $data = $ctx->data[0];
    $opts = $ctx->opts;
    # locate item index
    if (($id = $q->args) === '') {
      throw ErrorEx::fail($q->func, 'no argument');
    }
    if (($i = $data->indexOfArray('id', $id)) === -1)
    {
      # message may be outdated,
      # report issue and refresh
      $ctx->log->warn($q->func,
        'list item[id='.$id.'] not found'
      );
      return true;
    }
    # operate
    switch ($opts['list.config']['func']) {
    case 'select':
      self::dataSelect($ctx, $id);
      return true;
    case 'open':
      # check child exists
      if (!($k = $opts['list.open']) ||
          !isset($this->children[$k]))
      {
        return true;
      }
      # redirect
      $q->res  = $this->children[$k];
      $q->args = $id;
      return true;
    }
    # custom
    return $this($ctx, $q($i));
  }
  # }}}
  # }}}
  function operate(object $ctx, object $q): bool # {{{
  {
    switch ($q->func) {
    case '.enter':
      return $this->opEnter($ctx, $q);
    case '.data':
      return $this->opData($ctx, $q);
    case 'first':
      return $this->opFirst($ctx);
    case 'last':
      return $this->opLast($ctx);
    case 'prev':
    case 'back':
      return $this->opBack($ctx);
    case 'next':
    case 'forward':
      return $this->opForward($ctx);
    case 'id':
      return $this->opId($ctx, $q);
    }
    return $this($ctx, $q);
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
# OLD list {{{
class BotListItem_DELETE extends BotImgItem
{
  const OPTION = [# {{{
    'type.enter'  => true,
    'list.config' => [
      'type'   => 'list',
      'func'   => 'select',# list action
      'key'    => 'selected',# data key
      'minmax' => [0,1],# selected min/max
      'cols'   => 1,# columns
      'rows'   => 6,# rows
      'flexy'  => true,# shrink rows
      'cycle'  => false,# page cycle (first<=>last)
    ],
    'list.order'  => '',# order tag is a data key
    'list.desc'   => false,# order direction
    'list.select' => [
      'key'   => 'selected',
      'data'  => true,# false=>config
      'max'   => 1,# selected max, 0=unlimited
      'force' => 1,# deselect 0=no 1=first 2=last
      'min'   => 0,# selected min
    ],
    'list.open' => '',# child to open
    ###
    'markup' => [
      'head' => [],
      'foot' => [['!prev','!next'],['!up']],
    ],
    'markup.flags' => [
      'empty' => [
        'prev'=>0,'next'=>0,
        'back'=>0,'forward'=>0,
        'first'=>0,'last'=>0,
      ],
      'single-page' => [
        'prev'=>-1,'next'=>-1,
        'back'=>-1,'forward'=>-1,
        'first'=>0,'last'=>0,
      ],
      'first-page' => [
        'first'=>0
      ],
      'last-page' => [
        'last'=>0
      ],
    ],
  ];
  # }}}
  # hlp {{{
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
    $o = $ctx->opts['list.config'];
    if ($o['func'] === 'select')
    {
      $o = $ctx->item['list.select'];
      $k = $o['key'];
      $a = $o['data']
        ? ($ctx->data[1][$k] ?? [])
        : ($ctx[$k] ?? []);
      foreach ($list as &$b) {
        $b[$k] = in_array($b['id'], $a, true);
      }
    }
    return $list;
  }
  # }}}
  static function &pageMarkup(object $ctx): array # {{{
  {
    # prepare
    $mkup = [];
    $data = &self::pageItems(
      $ctx, $ctx->vars['pageSize']
    );
    if (($size = count($data)) === 0) {
      return $mkup;
    }
    $item = $ctx->item;
    $opts = $ctx->opts['list.config'];
    $cap  = $ctx->caps['list.'.$opts['func']];
    $rows = $opts['rows'];
    $cols = $opts['cols'];
    $flex = $opts['flexy'];
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
          $d = BotMarkup::op(
            $item, $c, 'id', $data[$i]['id']
          );
        }
        else {
          $d = BotMarkup::nop($item);
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
    $a = $o['data']
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
    if ($o['data']) {
      $ctx->data[1][$k] = $a;
    }
    else {
      $ctx[$k] = $a;
    }
    return true;
  }
  # }}}
  static function sort(# {{{
    array &$data, string $key, bool $desc
  ):bool
  {
    return is_string($data[0][$key])
      ? self::sortStr($data, $key, $desc)
      : self::sortNum($data, $key, $desc);
  }
  # }}}
  static function sortNum(# {{{
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
  static function sortStr(# {{{
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
    $m = $ctx->item['markup'];
    $a = [];
    foreach ($m['head'] as &$b) {
      $a[] = $b;
    }
    foreach (self::pageMarkup($ctx) as &$b) {
      $a[] = $b;
    }
    foreach ($m['foot'] as &$b) {
      $a[] = $b;
    }
    return $ctx->markup(
      $a, self::markupFlags($ctx)
    );
  }
  # }}}
  static function markupFlags(object $ctx): array # {{{
  {
    static $k = 'markup.flags';
    $vars = $ctx->vars;
    $item = $ctx->item;
    if ($vars['count'] === 0) {
      return $item[$k]['empty'];
    }
    if ($vars['pageCount'] < 2) {
      return $item[$k]['single-page'];
    }
    if ($ctx['page'] === 0) {
      return $item[$k]['first-page'];
    }
    if ($ctx['page'] === $vars['pageCount'] - 1) {
      return $item[$k]['last-page'];
    }
    return [];
  }
  # }}}
  # }}}
  # operations {{{
  function opEnter(object $ctx, object $q): bool # {{{
  {
    if ($q->args) {
      $ctx['page'] = 0;
    }
    return true;
  }
  # }}}
  function opData(object $ctx, object $q): bool # {{{
  {
    # check sorting is needed and enabled
    if (count($q->res) < 2 ||
        !($tag = $this['list.order']))
    {
      return true;
    }
    # order data
    $data = $ctx->load()[0];
    return self::sort(
      $data->arr, $tag, $this['list.desc']
    );
  }
  # }}}
  function opFirst(object $ctx): bool # {{{
  {
    if ($ctx['page'] === 0) {
      return false;
    }
    $ctx['page'] = 0;
    return true;
  }
  # }}}
  function opLast(object $ctx): bool # {{{
  {
    $vars = &self::vars($ctx);
    $last = $vars['pageCount'] - 1;
    if ($ctx['page'] === $last) {
      return false;
    }
    $ctx['page'] = $last;
    return true;
  }
  # }}}
  function opBack(object $ctx): bool # {{{
  {
    # prepare
    $a = &self::vars($ctx);
    $n = $a['pageCount'] - 1;
    # make sure current number is correct
    if (($i = $ctx['page']) > $n)
    {
      $ctx['page'] = $n;
      return true;
    }
    # decrement page index
    if ($i > 0)
    {
      $ctx['page'] = $i - 1;
      return true;
    }
    # at the first page,
    # check whether cycling is possible
    if ($n && $ctx->opts['list.config']['cycle'])
    {
      $ctx['page'] = $n;
      return true;
    }
    # skip
    return false;
  }
  # }}}
  function opForward(object $ctx): bool # {{{
  {
    # prepare
    $a = &self::vars($ctx);
    $n = $a['pageCount'] - 1;
    # make sure current number is correct
    if (($i = $ctx['page']) > $n)
    {
      $ctx['page'] = $n;
      return true;
    }
    # increment page index
    if ($i < $n)
    {
      $ctx['page'] = $i + 1;
      return true;
    }
    # at the last page,
    # check whether cycling is possible
    if ($n && $ctx->opts['list.config']['cycle'])
    {
      $ctx['page'] = 0;
      return true;
    }
    # skip
    return false;
  }
  # }}}
  function opId(object $ctx, object $q): bool # {{{
  {
    # prepare
    $data = $ctx->data[0];
    $opts = $ctx->opts;
    # locate item index
    if (($id = $q->args) === '') {
      throw ErrorEx::fail($q->func, 'no argument');
    }
    if (($i = $data->indexOfArray('id', $id)) === -1)
    {
      # message may be outdated,
      # report issue and refresh
      $ctx->log->warn($q->func,
        'list item[id='.$id.'] not found'
      );
      return true;
    }
    # operate
    switch ($opts['list.config']['func']) {
    case 'select':
      self::dataSelect($ctx, $id);
      return true;
    case 'open':
      # check child exists
      if (!($k = $opts['list.open']) ||
          !isset($this->children[$k]))
      {
        return true;
      }
      # redirect
      $q->res  = $this->children[$k];
      $q->args = $id;
      return true;
    }
    # custom
    return $this($ctx, $q($i));
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
    $data  = $ctx->load()[0];
    $opts  = $ctx->opts['list.config'];
    $size  = $opts['cols'] * $opts['rows'];
    $count = self::pageCount($data, $size);
    $last  = $count - 1;
    $vars  = [
      'pageSize'  => $size,
      'pageCount' => $count,
      'count'     => $data->count,
    ];
    # check and correct current page index
    if ($ctx['page'] > $last) {
      $ctx['page'] = $last;
    }
    # complete
    return $vars;
  }
  # }}}
  function operate(object $ctx, object $q): bool # {{{
  {
    switch ($q->func) {
    case '.enter':
      return $this->opEnter($ctx, $q);
    case '.data':
      return $this->opData($ctx, $q);
    case 'first':
      return $this->opFirst($ctx);
    case 'last':
      return $this->opLast($ctx);
    case 'prev':
    case 'back':
      return $this->opBack($ctx);
    case 'next':
    case 'forward':
      return $this->opForward($ctx);
    case 'id':
      return $this->opId($ctx, $q);
    }
    return $this($ctx, $q);
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
 # form {{{
class BotFormItem extends BotImgItem
{
  const OPTION = [# {{{
    'type.enter'    => true,
    'type.input'    => true,
    'form.key'      => 'state',# storage key
    'form.data'     => false,# false=>config storage
    'form.enter'    => [
      'autoReset'   => false,# reset on entry?
      'resetStatus' => [-2,-1,1],# auto-resettable
      'resetAll'    => false,# all steps (repeat)?
    ],
    # field/step transition flags
    # (1) step down from the first field
    # (2) reset fields when (1)
    # (4) step up from the last field
    # (8) submit at the last step's field
    # (16) submit only at the last field
    'form.transit'  => 1|4|8,
    'form.cycle'    => false,# first/last field cycling
    'form.noskip'   => false,# dont skip required fields
    'form.retry'    => true,# resubmit failed
    'form.confirm'  => false,# enable confirmation
    ###
    'isPersistent' => false,# form type, change instead of repeat
    'resetFailed' => true,# allow to reset from negative state
    'resetCompleted' => true,# allow to reset from positive state
    'resetAll' => false,# do a full reset (all steps)
    'okConfirm' => true,# enable confirmation step
    'forwardSkip' => false,# allow to skip required field
    'moveAround' => false,# allow to cycle back/forward
    'backStep' => true,# allow to return to the previous step
    'backStepReset' => true,# reset current step before returning
    'retryFailed' => true,# allow to retry failed submission
    'okIsForward' => false,# ok acts as forward until the last field
    # disable retry rather than hide
    'retryDisable' => true,
    # disable at start position
    'backDisable' => false,
    # disable at last position
    'forwardDisable' => false,
    # last forward becomes ok
    'forwardToOk' => true,
    # ok only when all required fields filled
    'okWhenReady' => true,
    # disable ok when missing required
    'okDisable' => true,
    # show empty bar when clearing is not feasible
    'clearSolid' => true,
    # allow to de-select selected option
    'selectDeselect' => true,
    'hiddenValue' => '✶✶✶',
    # forward after input accepted
    'inputForward' => false,
    # number of options in a row
    'cols' => 3,
    # allow variable cols in the last row
    'colsFlex' => false,
    ##
    'markup'    => [
      'head'    => [],
      'failure' => [['!back','!retry']],
      'miss'    => [['!back']],
      'input'   => [['!back','!forward']],
      'confirm' => [['!back','!submit']],
      'done'    => [['!repeat','!change']],
      'foot'    => [['!up']],
    ],
    'markup.flags' => [
    ],
  ];
  const STATUS = [
    -3 => 'in progress',
    -2 => 'failure',
    -1 => 'missing required',
     0 => 'input',
     1 => 'confirmation',
     2 => 'complete',
  ];
  # }}}
  function value_init_TODO() # {{{
  {
    # fill missing options
    foreach (static::CONFIG as $k => $v)
    {
      if (!isset($config[$k])) {
        $config[$k] = $v;
      }
    }
    if (!isset($config[$k = 'required'])) {
      $config[$k] = true;
    }
    if (!isset($config[$k = 'hidden'])) {
      $config[$k] = false;
    }
  }
  # }}}
  # hlp {{{
  function restruct(): void # {{{
  {
    ###
    # fields    = [fieldName => fieldSpec,..]
    # fieldSpec = [type,flags,fieldTypeSpec]
    # fieldTypeSpec = [..]
    # steps     = [[fieldName,..],..]
    ###
    $spec = &$this->spec;
    if (!isset($spec[$a = 'fields']) ||
        !is_array($spec[$a]) ||
        !count($spec[$a]))
    {
      throw ErrorEx::fail($this->path,
        "incorrect specification\n".
        '['.$a.'] is not set'
      );
    }
    # initialize steps
    if (!isset($spec[$b = 'steps'])) {
      $spec[$b] = [array_keys($spec[$a])];
    }
    # create fields
    foreach ($spec[$a] as $name => &$field) {
      $field = BotValue::construct($name, $field);
    }
  }
  # }}}
  function init(object $ctx): object # {{{
  {
    static $Q = new BotItemQuery('init');
    # invoke handler
    if (!$this($ctx, $Q)) {
      throw ErrorEx::warn($Q->func, 'denied');
    }
    # get current state
    $k = $this['form.key'];
    $s = $ctx->conf->obtain($k);
    # check data source
    if ($this['form.data'] &&
        ($a = $ctx->load()[1][$k]))
    {
      # restore
      $s->setRef($a);
      $this->fieldsRestore($ctx);
    }
    else
    {
      # initialize
      $s->set([0, 0, 0]);# status,step,field
      $this->fieldsReset($ctx, 0);
    }
    return $s;
  }
  # }}}
  function restore(object $ctx): bool # {{{
  {
    $this->init($ctx);
    return true;
  }
  # }}}
  function state(object $ctx): object # {{{
  {
    return isset($ctx->conf[$k = $this['form.key']])
      ? $ctx->conf->obtain($k)
      : $this->init($ctx);
  }
  # }}}
  function field(int $step, int $index): object # {{{
  {
    return $this['fields'][
      $this['steps'][$step][$index]
    ];
  }
  # }}}
  function &fields(int $step): array # {{{
  {
    $a = [];
    foreach ($this['steps'][$step] as $name) {
      $a[$name] = $this['fields'][$name];
    }
    return $a;
  }
  # }}}
  function &fieldsFrom(int $step): array # {{{
  {
    $a = [];
    $b = $this['steps'];
    $c = count($b);
    while (++$step < $c)
    {
      foreach ($b[$step] as $name) {
        $a[$name] = $this['fields'][$name];
      }
    }
    return $a;
  }
  # }}}
  function &fieldsTo(int $step = -1): array # {{{
  {
    $a = [];
    $b = $this['steps'];
    $c = count($b);
    if ($step < 0 || $step >= $c) {
      $step = $c - 1;
    }
    for ($c = 0; $c <= $step; ++$c)
    {
      foreach ($b[$c] as $name) {
        $a[$name] = $this['fields'][$name];
      }
    }
    return $a;
  }
  # }}}
  function &fieldsMissing(object $ctx): array # {{{
  {
    $a = [];
    $s = $this->state($ctx);
    foreach ($this->fields($s[1]) as $k => $o)
    {
      if ($o->required && !isset($ctx->conf[$k])) {
        $a[$k] = $o;
      }
    }
    return $a;
  }
  # }}}
  function fieldsReset(# {{{
    object $ctx, int $step
  ):void
  {
    $conf = $ctx->conf;
    foreach ($this->fieldsFrom($step) as $k => $o) {
      $conf[$k] = null;
    }
    foreach ($this->fields($step) as $k => $o) {
      $conf[$k] = $o->defaultValue($ctx);
    }
  }
  # }}}
  function fieldsRestore(object $ctx): void # {{{
  {
    $data = $ctx->load()[0];
    $conf = $ctx->conf;
    foreach ($this->fieldsTo() as $k => $o) {
      $conf[$k] = $data[$k];
    }
  }
  # }}}
  static function &vars(object $ctx): array # {{{
  {
    # check already done
    if ($vars = &$ctx->vars) {
      return $vars;
    }
    # initialize temporary variables
    # determine field vars
    $fields         = &$this->skel['fields'][$state[1]];
    $fieldMissCount = $this->fieldMissCount($fields);
    $fieldName      = array_key($fields, $state[2]);
    $field          = &$fields[$fieldName];
    $fieldIsLast    = $state[2] === count($fields) - 1;
    $vars = [
      'description' => $text,
      'complete' => $this->fieldsGetComplete($cfg, $state),
      'current'  => $this->fieldsGetCurrent($cfg, $state),
      'status'   => $state[0],
      'progress' => ($this['progress'] ?? 0),
      'info'     => $info,
    ];
    # complete
    return $vars;
  }
  # }}}
  # }}}
  # operations {{{
  function opEnter(object $ctx): bool # {{{
  {
    # restore when bound
    if ($this['form.data']) {
      return $this->restore($ctx);
    }
    # check option
    $o = $this['form.enter'];
    if ($o['autoReset'])
    {
      # check current status resettable
      $s = $this->state($ctx);
      $a = &$o['resetStatus'];
      if (!in_array($s[0], $a, true)) {
        return true;
      }
      # reset
      return $o['resetAll']
        ? $this->restore($ctx)
        : $this->opReset($ctx);
    }
    return true;
  }
  # }}}
  function opInput(object $ctx): bool # {{{
  {
    static $Q = new BotItemQuery('input');
    # check current status
    if (($s = $this->state($ctx))[0]) {
      return false;
    }
    # get field value
    $o = $this->field($s[1], $s[2]);
    if (($v = $o->value($ctx->input)) === null) {
      return false;
    }
    # store and complete
    $ctx->conf[$o->name] = $v;
    return true;
  }
  # }}}
  function opBack(object $ctx): bool # {{{
  {
    switch (($s = $this->state($ctx))[0]) {
    case 0:# input
      # get back to the previous field
      if ($s[2] > 0)
      {
        $s[2] = $s[2] - 1;
        return true;
      }
      # at the first field,
      # get back to the previous step
      $i = $this['form.transit'];
      if ($s[1] > 0 && $i & 1) {
        return $this->opStepDown($ctx, (bool)($i & 2));
      }
      # cycle from the first to the last field
      if ($cfg['form.cycle'])
      {
        $s[2] = count($this['steps'][$s[1]]) - 1;
        return true;
      }
      break;
    case -2:# failure
    case  1:# confirmation
      # get back to the input
      $s[0] = 0;
      return true;
    }
    return false;
  }
  # }}}
  function opForward(object $ctx): bool # {{{
  {
    # check current status (must be input)
    if (($s = $this->state($ctx))[0]) {
      return false;
    }
    # prepare
    $steps  = $this['steps'];
    $step   = $s[1];
    $fields = $steps[$step];
    $field  = $s[2];
    # check unconditional skipping is prohibited
    if ($this['noskip'])
    {
      # required field must be filled
      $a = $fields[$field];
      $b = $this['fields'][$a];
      if ($b->required && !isset($ctx->conf[$a]))
      {
        # TODO: notify
        #$ctx->text['req-field'];
        return false;
      }
    }
    # go to the next field
    if ($field < count($fields) - 1)
    {
      $s[2] = $field + 1;
      return true;
    }
    # at the last field,
    # check more steps to go
    $i = $this['form.transit'];
    if ($step < count($steps) - 1)
    {
      # go to the next step
      if ($i & 4) {
        return $this->opStepUp($ctx);
      }
    }
    else
    {
      # submit at the last step
      if ($i & 8) {
        return $this->opSubmit($ctx);
      }
    }
    # go to the first field
    if ($this['form.cycle'])
    {
      $s[2] = 0;
      return true;
    }
    return false;
  }
  # }}}
  function opStepDown(object $ctx, bool $reset): bool # {{{
  {
    static $Q = new BotItemQuery('step.down');
    # prepare
    $s = $this->state($ctx);
    if (($i = $s[1]) === 0) {
      return true;
    }
    # invoke handler
    if (!$this($ctx, $Q($i))) {
      return false;
    }
    # reset fields
    if ($reset) {
      $this->fieldsReset($ctx, $i);
    }
    # get back to the previous step
    $s[1] = $i = $i - 1;
    $s[2] = count($this['steps'][$i]) - 1;
    return true;
  }
  # }}}
  function opStepUp(object $ctx): bool # {{{
  {
    static $Q = new BotItemQuery('step.up');
    # prepare
    $s = $this->state($ctx);
    # check missing required fields
    if ($a = &$this->fieldsMissing($ctx))
    {
      # transition to the missing state
      $s[0] = -1;
      $s[2] = $a[0]->index;
      return true;
    }
    # invoke handler
    if (!$this($ctx, $Q($i = $s[1])))
    {
      # transition to the failed state
      $s[0] = -2;
      return true;
    }
    # go to the next step
    $s[1] = $i + 1;
    $s[2] = 0;
    return true;
  }
  # }}}
  function opReset(object $ctx): bool # {{{
  {
    static $Q = new BotItemQuery('reset');
    # check current status
    $s = $this->state($ctx);
    switch ($s[0]) {
    case -2:# failed
      # get back to confirmation or input
      if ($this['form.confirm'] &&
          $s[1] === count($this['steps']) - 1)
      {
        $s[0] = 1;
      }
      else {
        $s[0] = 0;
      }
      return true;
    case -1:# missing
      # get back to the input
      $s[0] = 0;
      return true;
    case 0:# input ~ TRUE RESET
      # invoke handler
      if (!$this($ctx, $Q)) {
        break;
      }
      # reset current step fields and
      # seek to the first field
      $this->fieldsReset($ctx, $s[1]);
      $s[2] = 0;
      return true;
    case 1:# confirmation
      # get back to the last step input
      $s[0] = $s[2] = 0;
      return true;
    case 2:# complete
      # repeat without resetting
      $s[0] = $s[1] = $s[2] = 0;
      return true;
    }
    return false;
  }
  # }}}
  function opSubmit(object $ctx): bool # {{{
  {
    static $VALID = [-2,0,1];
    static $Q = new BotItemQuery('submit');
    # check current status
    $s = $this->state($ctx);
    if (!in_array($i = $s[0], $VALID, true) ||
        ($i === -2 && !$cfg['form.retry']))
    {
      return false;
    }
    # prepare
    $steps  = $this['steps'];
    $step   = $s[1];
    $fields = $steps[$step];
    $field  = $s[2];
    # before submission,
    # all steps must be completed
    if ($step < count($steps) - 1)
    {
      # while in the input mode,
      # check being at the last field is required,
      # but not met
      if ($i === 0 &&
          $this['form.transit'] & 16 &&
          $field < count($fields) - 1)
      {
        return $this->opForward($ctx);
      }
      return $this->opStepUp($ctx);
    }
    # check confirmation required
    if ($i === 0 && $this['form.confirm'])
    {
      $s[0] = 1;
      return true;
    }
    # invoke handler
    if (!$this($ctx, $Q($i)))
    {
      # transition to the failed state
      $s[0] = -2;
      return true;
    }
    # store data
    if ($this['form.data'])
    {
      # set state
      $ctx->data[1][$this['form.key']] = [2,0,0];
      # set fields
      $data = $ctx->data[0];
      $conf = $ctx->conf;
      foreach ($this['fields'] as $k => $o) {
        $data[$k] = $conf[$k];
      }
    }
    # complete
    $s[0] = 2;
    return true;
  }
  # }}}
  # }}}
  function operate(object $ctx, object $q): bool # {{{
  {
    switch ($q->func) {
    case '.enter':
      return $this->opEnter($ctx);
    case '.input':
      return $this->opInput($ctx);
    case 'back':
    case 'prev':
      return $this->opBack($ctx);
    case 'forward':
    case 'next':
      return $this->opForward($ctx);
    case 'reset':
    case 'repeat':
    case 'change':
      return $this->opReset($ctx);
    case 'ok':
    case 'retry':
    case 'submit':
      return $this->opSubmit($ctx);
    case 'select':
      return false;
    }
    return $this($ctx, $q);
  }
  # }}}
  function render(object $ctx): array # {{{
  {
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
    # render vars
    $vars = &self::vars($ctx);
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
# TODO {{{
/**
* refactor: reuse context object more (instead of request)
* architect: value objects
* feature: temporary (mem only) file (ArrayNode)
* feature: item fixation
* architect: async: chat => item operation
* refactor: BotConfig/dirs (where to put dirs?)
* feature: event debounce / throttle / user stats
* feature: filters (FSM without rendering/output)
* solve: old callback action does ZAP? or.. REFRESH?!
* solve: chat message extension when possible
* perf: mustache parser text reference and prepare()
* perf: api.actions: curl instances pool
*
* ᛣ 2021 - ᛉ 2023 - ...
***
* ╔═╗ ⎛     ⎞ ★★☆☆☆
* ║╬║ ⎜  *  ⎟ ✱✱✱ ✶✶✶ ⨳⨳⨳
* ╚═╝ ⎝     ⎠ ⟶ ➤ →
***
*/
# }}}
###############
