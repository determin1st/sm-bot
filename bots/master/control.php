<?php
namespace SM;
function BotItem_startbots( # {{{
  object $user,
  object $item,
  string $func,
  ?array $args,
):?array
{
  return null;
  # prepare
  $lang = $bot->user->lang;
  $text = &$item['text'][$lang];
  $datadir = $bot->datadir.'..'.DIRECTORY_SEPARATOR;
  # get directory list
  if (($a = @scandir($datadir, SCANDIR_SORT_DESCENDING)) === false)
  {
    $bot->logError("scandir($datadir) failed");
    return false;
  }
  # refine
  $c = [];
  $i = 0;
  foreach ($a as $b)
  {
    if ($b[0] !== '.')
    {
      $file0 = $datadir.$b.DIRECTORY_SEPARATOR;
      $file1 = $file0.'o.json';
      $file0 = $file0.'o.lock';
      $name = trim($text['name']);
      $isUp = file_exists($file0);
      if (!($info = json_decode(file_get_contents($file1), true))) {
        continue;
      }
      $name = $bot->tp->render($name, [
        'up'   => $isUp,
        'id'   => $b,
        'name' => $info['name'],
        'bot'  => (isset($info['bot']) ? $info['bot'] : $b),
      ]);
      $c[] = [
        'id'    => $b,
        'type'  => 'bot',
        'order' => ($isUp ? 1000+$i : $i),
        'name'  => $name,
      ];
      $i++;
    }
  }
  # done
  $item['data'] = &$c;
  return true;
}
# }}}
?>
