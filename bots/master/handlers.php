<?php
namespace SM\BotItem;
use SM\{# {{{
  BotError, BotConfig, BotText, BotFile, BotCommands
};
# }}}
function startbots(object $item): ?array # {{{
{
  # prepare
  $bot  = $item->bot;
  $dir  = $bot->cfg->dirDataRoot;
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
    $item->log->print(0, 0, $func, $data['name']);
    switch ($func) {
    case 'start':
      if ($item->bot->proc->start($id)) {
        return startbotsbot($item, '', $id);
      }
      $data['isError'] = true;
      $data['message'] = $item->bot->text['op-fail'];
      break;
    case 'stop':
      if ($item->bot->proc->stop($id)) {
        return startbotsbot($item, '', $id);
      }
      $data['isError'] = true;
      $data['message'] = $item->bot->text['op-fail'];
      break;
    case 'dropCache':
      # invoke handler
      dropCache($item, $data);
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
    'dropCache' => $data['isRunning'] ? 0 : 1,
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
function startbotscreate(# {{{
  object  $item,
  string  $func,
  mixed   &$args = null
):?array
{
  static $NEWBOT_EXP = '/^.+ t\.me\/([^.]{5,32})\..+ HTTP API:\n([^\n]{44,46})\n.+$/s';
  switch ($func) {
  case 'options':# {{{
    # there is only one field with options,
    # get and return available bot classes
    return getBotClassMap($item);
  # }}}
  case 'input':# {{{
    # parse forwarded BotFather message
    if (($a = $item->input->text ?? '') === '' ||
        ($b = $item->input->forward_from ?? null) === null ||
        !$b->is_bot || $b->username !== 'BotFather' ||
        !preg_match($NEWBOT_EXP, $a, $b))
    {
      break;
    }
    # store and complete
    $item->data['token'] = $b[2];
    return [1, $item->bot->text['msg-parsed']];
  # }}}
  case 'ok':# {{{
    # get token and extract identifier
    $bot   = $item->bot;
    $data  = $item->data;
    $token = $data['token'];
    $id    = substr($token, 0, strpos($token, ':'));
    # set extras
    $data['id']    = $id;
    $data['name']  = '';
    $data['error'] = [];
    # check identifier is already in use
    if ($id === strval($bot->id)) {
      $a = $bot->cfg->name;
    }
    elseif ($a = getBotInfo($bot, $id)) {
      $a = $a['name'];
    }
    if ($a)
    {
      $data->arrayPush('error', 'id');
      return [0, $bot->tp->render($item->text['in-use'], [
        'name' => $a,
      ])];
    }
    # request bot information
    if (!($a = $bot->api->send('getMe', null, null, $token)))
    {
      $data->arrayPush('error', 'token');
      return [0, $bot->api->error];
    }
    # store bot username
    $data['name'] = $a->username;
    return [1];
  # }}}
  case 'submit':# {{{
    # prepare
    $bot  = $item->bot;
    $data = $item->data;
    # get configuration template
    if (!file_exists($a = $bot->cfg->dirInc.BotConfig::FILE_INC) ||
        !($b = file_get_contents($a)))
    {
      $item->log->error("failed to read: $a");
      return [0];
    }
    # render it
    $a = $bot->tp->render($b, [
      'class'  => $data['class'],
      'token'  => $data['token'],
      'admins' => $item->user->id,
    ]);
    # create new bot directory and
    # store rendered configuration
    $b = $bot->cfg->dirDataRoot.$data['id'].DIRECTORY_SEPARATOR;
    $c = $b.BotConfig::FILE_INC;
    if ((!file_exists($b) && !mkdir($b)) ||
        !file_put_contents($c, $a))
    {
      $item->log->error("failed to set: $c");
      return [0];
    }
    # setup new bot
    if (!BotConfig::setup($b, $bot))
    {
      $item->log->error("failed to set: $c");
      return [0];
    }
    # success
    return [1];
  # }}}
  case 'fields':# {{{
    # check
    if (($status = $item['status']) > -2 && $status < 1) {
      break;
    }
    # add extra fields
    $args[] = $item->newFieldDescriptor('id');
    $args[] = $item->newFieldDescriptor('name');
    # determine failed fields
    if ($status === -2)
    {
      $b = $item->data['error'];
      foreach ($args as &$a) {
        $a['flag'] = in_array($a['id'], $b, true);
      }
      unset($a);
    }
    break;
  # }}}
  }
  return null;
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
  $dirData = $bot->cfg->dirDataRoot.$id.DIRECTORY_SEPARATOR;
  $fileCfg = $dirData.BotConfig::FILE_JSON;
  # read bot configuration
  if (!($cfg = $bot->file->getJSON($fileCfg))) {
    return null;
  }
  # determine if bot runs from the webhook
  if ($webhook = $cfg['BotApi']['url'] ?? '') {
    $isWebhook = $cfg['BotApi']['webhook'] ?? false;
  }
  else {
    $isWebhook = false;
  }
  # determine identifier
  if ($isMaster = ($id === 'master'))
  {
    $botId = $cfg['token'];
    $botId = substr($botId, 0, strpos($botId, ':'));
  }
  else {
    $botId = $id;
  }
  # to detect running bot,
  # check configuration is locked
  if ($isRunning = file_exists($a = $fileCfg.'.lock'))
  {
    # determine startup time
    $isRunning = ($b = file_time($a, true))
      ? date('Y-m-d H:i:s', $b) : '⨳';
    # make sure the slavebot process is running
    if (!$isMaster && !$isWebhook && !$bot->proc->get($botId))
    {
      $bot->log->warn($cfg['name'], "is not running: removing lockfile ($isRunning)");
      $isRunning = false;
      unlink($a);
    }
  }
  # determine ascending order index:
  # master => running => type => name
  $order  = $isMaster  ? '0' : '1';
  $order .= $isRunning ? '0' : '1';
  $order .= $cfg['source'].$cfg['name'];
  # create base
  $info = [
    'id'        => $id,
    'botId'     => $botId,
    'name'      => $cfg['name'],
    'order'     => $order,
    'type'      => $cfg['source'],
    'webhook'   => $webhook,
    'isWebhook' => $isWebhook,
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
function dropCache(object $item, array &$data): bool # {{{
{
  # prepare
  $bot = $item->bot;
  $dir = $bot->cfg->dirDataRoot.$data['id'].DIRECTORY_SEPARATOR;
  $cnt = 0;
  # operate
  try
  {
    # remove texts cache
    foreach (BotText::FILE_JSON as $a) {
      file_exists($a = $dir.$a) && unlink($a) && ++$cnt;
    }
    # remove files cache
    foreach (BotFile::FILE_JSON as $a) {
      file_exists($a = $dir.$a) && unlink($a) && ++$cnt;
    }
    # remove command tree cache
    file_exists($a = $dir.BotCommands::FILE_JSON) &&
    unlink($a) && ++$cnt;
    # success
    $data['message'] = sprintf(
      $item->text['cacheDropped'], $cnt
    );
  }
  catch (\Throwable $e)
  {
    # failure
    $item->log->exception($e);
    $data['message'] = $bot->text['op-fail'];
    $data['isError'] = true;
    return false;
  }
  return true;
}
# }}}
function getBotClassMap(object $item): ?array # {{{
{
  # prepare
  $bot = $item->bot;
  $dir = $bot->cfg->dirSrcRoot;
  $map = [];
  # operate
  try
  {
    if (!($a = scandir($dir))) {
      throw BotError::fail("scandir($dir) failed");
    }
    foreach ($a as $b)
    {
      if ($b[0] !== '.' && $b !== 'master') {
        $map[$b] = $b;
      }
    }
  }
  catch (\Throwable $e)
  {
    # failure
    $item->log->exception($e);
    return null;
  }
  return $map;
}
# }}}
?>
