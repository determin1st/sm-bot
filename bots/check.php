<?php declare(strict_types=1);
namespace SM;
require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'bot.php';
class BotTest
{
  const DELAY = 200000;
  public $console,$log;
  function __construct()
  {
    $this->console = $this;
    $this->log = new BotLog($this, 'check');
  }
  function write(string $s): void {
    fwrite(STDOUT, $s);
  }
  function check(): int
  {
    try
    {
      # check environment requirements
      $a = 'environment';
      $b = 'PHP version 8.0+';
      if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 80000) {
        throw BotError::stop($a, "fail\n$b");
      }
      $b = 'extension: Sync';
      if (!class_exists('SyncMutex', false) ||
          !class_exists('SyncEvent', false) ||
          !class_exists('SyncReaderWriter', false) ||
          !class_exists('SyncSharedMemory', false))
      {
        throw BotError::stop($a, "fail\n$b");
      }
      $b = 'extension: CURL';
      if (!function_exists('\\curl_version') ||
          !($c = curl_version()) ||
          !isset($c['version_number']) ||
          !isset($c['protocols']) ||
          $c['version_number'] < 478209 ||
          array_search('https', $c['protocols'], true) === false)
      {
        throw BotError::stop($a, "fail\n$b");
      }
      $b = 'extension: GD';
      if (!function_exists('\\gd_info') ||
          !($c = gd_info()) ||
          !($c['FreeType Support'] ?? false) ||
          !($c['JPEG Support'] ?? false) ||
          !($c['PNG Support'] ?? false))
      {
        throw BotError::stop($a, "fail\n$b");
      }
      $this->log->info($a, 'ok'); usleep(self::DELAY);
      # check core objects
      $a = 'configuration';
      if ($b = BotConfig::check()) {
        throw BotError::stop($a, "fail\n$b");
      }
      $this->log->info($a, 'ok'); usleep(self::DELAY);
      $a = 'texts';
      if ($b = BotText::check()) {
        throw BotError::stop($a, "fail\n$b");
      }
      $this->log->info($a, 'ok'); usleep(self::DELAY);
      # determine masterbot installed
      $a = BotConfig::isInstalled() ? 100 : 101;
    }
    catch (\Throwable $e)
    {
      $this->log->exception($e);
      $a = 1;
    }
    return $a;
  }
}
exit((new BotTest())->check());
?>
