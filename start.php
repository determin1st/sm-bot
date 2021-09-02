<?php
require_once __DIR__.DIRECTORY_SEPARATOR.'bot.php';
if (isset($_SERVER['REQUEST_METHOD']))
{
  # WEBHOOK (single update)
  # ...
}
elseif (isset($argc) && isset($argv))
{
  # CLI (getUpdates loop)
  if ($argc === 1) {
    SM\Bot::start();# master
  }
  elseif ($argc === 2) {
    SM\Bot::start(intval($argv[1]));# slave
  }
  elseif ($argc === 3) {
    # TODO: task
  }
}
exit(5);# not handled
?>
