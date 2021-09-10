<?php
namespace SM;
function BotItem_startbots(object $item): ?array # {{{
{
  # prepare
  $bot  = $item->bot;
  $data = [];
  # determine data root
  $dir = $bot->dir->data;
  $dir = substr($dir, 0, 1 + strrpos($dir, DIRECTORY_SEPARATOR, -2));
  # get bot directories
  if (($list = @scandir($dir, SCANDIR_SORT_DESCENDING)) === false)
  {
    $item->log->error("scandir($dir) failed");
    return null;
  }
  # iterate and create data
  foreach ($list as $id)
  {
    # skip special directories ('.' or '..')
    if ($id[0] === '.') {
      continue;
    }
    # determine config file path
    $dirData = $dir.$id.DIRECTORY_SEPARATOR;
    $fileCfg = $dirData.BotConfig::FILE_JSON;
    # read bot configuration
    if (!($cfg = $bot->file->getJSON($fileCfg))) {
      continue;
    }
    # determine masterbot
    if ($isMaster = ($id === 'master'))
    {
      $id = $cfg['token'];
      $id = substr($id, 0, strpos($id, ':'));
    }
    # to determine if bot is running,
    # check configuration is locked
    $isRunning = file_exists($fileCfg.'.lock');
    # create ascending order index:
    # master => running => type => name
    $order  = $isMaster ? '0' : '1';
    $order .= $isRunning ? '0' : '1';
    $order .= $cfg['source'].$cfg['name'];
    # create element
    $data[] = [
      'id'    => $id,
      'name'  => $cfg['name'],
      'order' => $order,
      'type'  => $cfg['source'],
      'isRunning' => $isRunning,
    ];
  }
  # done
  return $data;
}
# }}}
?>
