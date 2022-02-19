<?php declare(strict_types=1);
namespace SM;
require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'bot.php';
class BotBot
{
  public $cfg,$console,$log,$api;
  function __construct()
  {
    $this->console = $this;
    $this->log = new BotLog($this, 'setup');
    $this->cfg = new BotConfig($this);
    $this->api = new BotApi($this);
  }
  function write(string $s): void {
    fwrite(STDOUT, $s);
  }
  function install(): int
  {
    try
    {
      # initialize api
      if (!$this->api->init()) {
        throw BotError::skip();
      }
      # TODO: check filesystem
      $cfg = $this->cfg;
      $dir = $cfg->dirDataRoot;
      #var_dump($dir);
      # ...
      # ...
      # install masterbot
      $a = $cfg->install($cfg->data['Bot']) ? 100 : 0;
    }
    catch (\Throwable $e)
    {
      $this->log->exception($e);
      $a = 1;
    }
    return $a;
  }
}
exit((new BotBot())->install());
?>
