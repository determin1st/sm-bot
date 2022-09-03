<?php declare(strict_types=1);
namespace SM;
require_once __DIR__.DIRECTORY_SEPARATOR.'bot.php';
switch ($_SERVER['argc'] ?? 0) {
case 1:
  Bot::start();# console
  break;
case 2:
  Bot::start($_SERVER['argv'][1]);# bot
  break;
}
exit(0);
?>
