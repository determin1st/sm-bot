<?php
require_once __DIR__.DIRECTORY_SEPARATOR.'bot.php';
if (isset($_SERVER['REQUEST_METHOD']))
{
  # CGI mode (WebHook only)
  # ...
}
elseif (isset($argc) && isset($argv))
{
  # CLI mode
  if ($argc === 1)
  {
    # bot console and masterbot (getUpdates only)
    SM\Bot::start();
  }
  elseif ($argc === 2)
  {
    # bot process
    SM\Bot::start($argv[1]);
  }
  elseif ($argc === 3) {
    # TODO: bot task
  }
}
exit(5);# not handled
?>
