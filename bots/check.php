<?php declare(strict_types=1);
namespace SM;
require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'bot.php';
class BotCheck
{
  const DELAY = 100000;
  public $console,$log;
  function __construct()
  {
    $this->console = $this;
    $this->log = new BotLog($this, 'check');
  }
  function write(string $s): void {
    fwrite(STDOUT, $s);
  }
  function assert(): int
  {
    try
    {
      # check environment requirements
      $a = 'PHP v8';
      if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 80000) {
        throw BotError::stop($a, 'fail');
      }
      $this->log->info($a, 'ok'); usleep(self::DELAY);
      $a = 'CURL v7.76.1';
      if (!function_exists('\\curl_version') ||
          !($b = curl_version()) ||
          !isset($b['version_number']) ||
          !isset($b['protocols']) ||
          $b['version_number'] < 478209 ||
          array_search('https', $b['protocols'], true) === false)
      {
        throw BotError::stop($a, 'fail');
      }
      $this->log->info($a, 'ok'); usleep(self::DELAY);
      $a = 'GD v2';
      if (!function_exists('\\gd_info') ||
          !($b = gd_info()) ||
          !($b['FreeType Support'] ?? false) ||
          !($b['JPEG Support'] ?? false) ||
          !($b['PNG Support'] ?? false))
      {
        throw BotError::stop($a, 'fail');
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
      ###
      ###
      ###
      ###
      throw BotError::stop('under construction..');
    }
    catch (\Throwable $e)
    {
      $this->log->exception($e);
      return 1;
    }
    return 0;
  }
}
exit((new BotCheck())->assert());
?>
