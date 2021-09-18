<?php
namespace SM\BotItem;
use SM\BotConfig;
function startbots(object $item): ?array # {{{
{
  # prepare
  $bot  = $item->bot;
  $dir  = $bot->dir->dataRoot;
  $data = [];
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
    if ($id[0] !== '.' && ($a = getBotInfo($bot, $id))) {
      $data[] = $a;
    }
  }
  # done
  return $data;
}
# }}}
function startbotsbot(object $item, string $func, string $args): ?array # {{{
{
  # prepare
  # determine bot identifier
  $id = (!$func && $args)
    ? $args # take from request
    : ($item['id'] ?? '');# take from config
  # get information
  if (!$id || !($data = getBotInfo($item->bot, $id, true)))
  {
    $item->log->warn($id
      ? "failed to get info: $id"
      : "no identifier specified"
    );
    return null;
  }
  # operate
  if ($func)
  {
    $item->log->out(0, 0, $func, $data['name']);
    switch ($func) {
    case 'start':
      # recurse upon success
      if ($item->bot->proc->start($id)) {
        return startbotsbot($item, '', $id);
      }
      # set error
      $data['isError'] = true;
      $data['message'] = $item->bot->text['op-fail'];
      break;
    case 'stop':
      # same logic
      if ($item->bot->proc->stop($id)) {
        return startbotsbot($item, '', $id);
      }
      $data['isError'] = true;
      $data['message'] = $item->bot->text['op-fail'];
      break;
    }
  }
  # render markup
  $mkup = $data['isMaster']
    ? [['!up']]
    : $item->skel['markup'];
  $mkup = $item->markup($mkup, [
    'start' => $data['isRunning'] ? 0 : 1,
    'stop'  => $data['isRunning'] ? 1 : 0,
  ]);
  # store identifier
  $item['id'] = $id;
  # complete
  return [
    'id'     => $data['id'],
    'file'   => '',# dynamic title, no cache
    'title'  => $data['name'],
    'text'   => $item->bot->tp->render($item->text['#'], $data),
    'markup' => $mkup,
  ];
}
# }}}
###
function getBotInfo(# {{{
  object  $bot,
  string  $id,
  bool    $extra = false
):?array
{
  # determine config file path
  $dirData = $bot->dir->dataRoot.$id.DIRECTORY_SEPARATOR;
  $fileCfg = $dirData.BotConfig::FILE_JSON;
  # read bot configuration
  if (!($cfg = $bot->file->getJSON($fileCfg))) {
    return null;
  }
  # to determine if bot is running,
  # check configuration is locked
  $isRunning = file_exists($fileCfg.'.lock');
  # determine identifier
  if ($isMaster = ($id === 'master'))
  {
    $botId = $cfg['token'];
    $botId = substr($botId, 0, strpos($botId, ':'));
  }
  else {
    $botId = $id;
  }
  # determine ascending order index:
  # master => running => type => name
  $order  = $isMaster  ? '0' : '1';
  $order .= $isRunning ? '0' : '1';
  $order .= $cfg['source'].$cfg['name'];
  # create base
  $info = [
    'id'    => $id,
    'botId' => $botId,
    'name'  => $cfg['name'],
    'order' => $order,
    'type'  => $cfg['source'],
    'isMaster'  => $isMaster,
    'isRunning' => $isRunning,
  ];
  if (!$extra) {
    return $info;
  }
  # set extra information
  $info['isError'] = false;
  $info['message'] = '';
  # complete
  return $info;
}
# }}}
?>
