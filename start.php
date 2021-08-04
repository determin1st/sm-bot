<?php
require_once __DIR__.DIRECTORY_SEPARATOR.'bot.php';
if (isset($_SERVER['REQUEST_METHOD']))
{
  # WEBHOOK (single update)
  # ...
}
else
{
  # CLI (getUpdates loop)
  SM\Bot::start();
}
?>
