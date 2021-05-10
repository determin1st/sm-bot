<?php
namespace SM;
require_once __DIR__.DIRECTORY_SEPARATOR.'bot.php';
# check invocation variant
if (array_key_exists('REQUEST_METHOD', $_SERVER))
{
  # WEBHOOK
  # ...
}
elseif ($argc === 4)
{
  # CLI (getUpdates or task)
  if (Bot::command(array_slice($argv, 1))) {
    exit(0);# no problems
  }
}
else
{
  # CLI (no parameters)
  echo <<<INFO
▌╔══════════════════╗
▌║ sm-bot index.php ║
▌╚══════════════════╝
▌
INFO;
}
exit(1);# something's wrong
?>
