<?php declare(strict_types=1);
namespace SM;
use Throwable;
require_once __DIR__.DIRECTORY_SEPARATOR.'bot.php';
$o = new class {
  # {{{
  public $console,$cfg,$log,$api,$text;
  function __construct()
  {
    $this->console = $this;
    $this->log     = new BotLog($this, 'check');
    set_error_handler(function(int $no, string $msg, string $file, int $line) {
      throw BotError::rise($no, $msg);
    });
  }
  function write(string $s): void {
    fwrite(STDOUT, $s);
  }
  # }}}
  function check(): int # {{{
  {
    try
    {
      # check environment requirements
      $a = 'environment';
      $b = 'required: PHP version 8+';
      if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 80000) {
        throw BotError::stop($a, "fail\n$b");
      }
      $b = 'required extension:';
      if (!function_exists('\\curl_version') ||
          !($c = curl_version()) ||
          !isset($c['version_number']) ||
          !isset($c['protocols']) ||
          $c['version_number'] < 478209 ||
          array_search('https', $c['protocols'], true) === false)
      {
        throw BotError::stop($a, "fail\n$b cURL");
      }
      if (!function_exists('\\gd_info') ||
          !($c = gd_info()) ||
          !($c['FreeType Support'] ?? false) ||
          !($c['JPEG Support'] ?? false) ||
          !($c['PNG Support'] ?? false))
      {
        throw BotError::stop($a, "fail\n$b GD");
      }
      if (!class_exists('SyncMutex', false) ||
          !class_exists('SyncEvent', false) ||
          !class_exists('SyncReaderWriter', false) ||
          !class_exists('SyncSharedMemory', false))
      {
        throw BotError::stop($a, "fail\n$b Sync");
      }
      if (!class_exists('FFI', false)) {
        throw BotError::stop($a, "fail\n$b FFI");
      }
      $this->log->info($a, 'ok');
      # check core objects
      $a = 'configuration';
      if ($b = $this->checkConfig()) {
        throw BotError::stop($a, "fail\n$b");
      }
      $this->log->info($a, 'ok');
      $a = 'console';
      if ($b = $this->checkConsole()) {
        throw BotError::stop($a, "fail\n$b");
      }
      $this->log->info($a, 'ok');
      $a = 'texts';
      if ($b = $this->checkText()) {
        throw BotError::stop($a, "fail\n$b");
      }
      $this->log->info($a, 'ok');
      # check installed
      $a = $this->cfg->dirDataRoot.$this->cfg->data['Bot']['id'];
      $a = $a.DIRECTORY_SEPARATOR.BotConfig::FILE_BOT_CONFIG;
      if (!file_exists($a) && !$this->install()) {
        throw BotError::skip();
      }
      $a = 100;
    }
    catch (Throwable $e)
    {
      $this->log->exception($e);
      $a = 0;
    }
    return $a;
  }
  # }}}
  function checkConfig(): string # {{{
  {
    $a = BotConfig::getIncDir().BotConfig::FILE_CONFIG;
    if (!file_exists($a)) {
      return "file not found: $a";
    }
    if (!($b = file_get_array($a))) {
      return "file is incorrect: $a";
    }
    if (empty($b['token'])) {
      return "file: $a\ntoken is not specified";
    }
    if (!BotConfig::checkToken($c = $b['token'])) {
      return "file: $a\nincorrect token: $c";
    }
    if (!file_exists($c = BotConfig::getDataDir($b))) {
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
    try
    {
      $this->cfg = new BotConfig($this);
      $e = '';
    }
    catch (Throwable $e) {
      $e = BotLog::throwableToString($e);
    }
    return $e;
  }
  # }}}
  function checkConsole(): string # {{{
  {
    try
    {
      $o = new BotMasterConsole($this);
      $o->init();
      $e = '';
    }
    catch (Throwable $e) {
      $e = BotLog::throwableToString($e);
    }
    if ($e) {
      return $e;
    }
    if (!$o->conio) {
      return "conio is null";
    }
    $this->console = $o;
    return '';
  }
  # }}}
  function checkText(): string # {{{
  {
    $a = $this->cfg->dirInc;
    $b = [
      $a.BotText::DIR_PARSER.DIRECTORY_SEPARATOR.BotText::FILE_PARSER,
      $a.BotText::FILE_TEXTS,
      $a.BotText::FILE_CAPS,
      $a.BotText::FILE_EMOJIS,
    ];
    foreach ($b as $a)
    {
      if (!file_exists($a)) {
        return "file not found: $a";
      }
    }
    try
    {
      $this->text = new BotText($this);
      $this->text->init();
      $e = '';
    }
    catch (Throwable $e) {
      $e = BotLog::throwableToString($e);
    }
    return $e;
  }
  # }}}
  function install(): bool # {{{
  {
    try
    {
      # prepare
      $this->api = new BotApi($this);
      $this->log->warn('masterbot is not installed');
      $this->log->name = 'install';
      # ask
      $this->log->prompt('[Y,N]?');
      $a = ucfirst($this->console->choice(10));
      $this->console->write($a."\n");
      if ($a === 'N') {
        throw BotError::skip();
      }
      # TODO: check filesystem
      #$dir = $this->cfg->dirDataRoot;
      #var_dump($dir);
      # ...
      # ...
      # install masterbot
      $a = $this->cfg->install($this->cfg->data['Bot']);
    }
    catch (Throwable $e)
    {
      $this->log->exception($e);
      $a = false;
    }
    return $a;
  }
  # }}}
};
exit($o->check());
