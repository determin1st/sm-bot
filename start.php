<?php
require_once __DIR__.DIRECTORY_SEPARATOR.'bot.php';
if (isset($_SERVER['REQUEST_METHOD']))
{
  # WEBHOOK, handle single update
  # ...
}
else
{
  # CLI, start getUpdates loop
  exit(SM\Bot::start());
}
?>
