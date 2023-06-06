<?php declare(strict_types=1);
# {{{
namespace SM;
function getBotList(object $bot): array # {{{
{
  $list = [];
  foreach ($bot->listBots() as &$a)
  {
    if ($b = getBotInfo($bot, $a)) {
      $list[] = $b;
    }
  }
  return $list;
}
# }}}
function getBotInfo(object $bot, string $id): ?array # {{{
{
  # read bot configuration
  $a = file_get_json($bot->cfg->path($id));
  if (!$a || !isset($a['Bot'])) {
    return null;
  }
  # set flags
  $a = $a['Bot'];
  $a['isMaster'] = $a['source'] === 'master';
  $a['isRunning'] = file_persist($bot->proc->path($id));
  # determine order field
  $a['order'] =
    ($a['isMaster'] ? '0' : '1').
    ($a['isRunning'] ? '0' : '1').
    $a['source'].$a['name'];
  # complete
  return $a;
}
# }}}
function getBotSources(object $item): ?array # {{{
{
  # prepare
  $bot = $item->bot;
  $dir = $bot->dir->srcRoot;
  $map = [];
  # operate
  try
  {
    if (!($a = scandir($dir))) {
      throw BotError::text("scandir($dir) failed");
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
function dropCache(object $item, array &$data): bool # {{{
{
  # prepare
  $bot = $item->bot;
  $dir = $bot->dir->dataRoot.$data['id'].DIRECTORY_SEPARATOR;
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
# }}}
return [
'/start/bots' => function(object $q): ?object # {{{
{
  switch ($q->func) {
  case '.data':
    $q->res = getBotList($this->item->bot);
    break;
  }
},
# }}}
'/start/bots/bot' => function(object $q): ?object # {{{
{
  # set identifier
  if ($q->func === 'id')
  {
    $this['id'] = $q->args;
    return true;
  }
  # get identifier
  if (!($id = $this['id'])) {
    return false;
  }
  # operate
  switch ($q->func) {
  case '.render':# {{{
    # get details
    $bot = $this->item->bot;
    if (!($a = getBotInfo($bot, $id, true))) {
      return false;
    }
    # compose markup
    $b = $this->opts['markup'];
    if ($bot->id === $id) {
      $b = $this->markup($b['master']);
    }
    else
    {
      $c = $a['isRunning'];
      $b = $item->markup($b['slave'], [
        'start'     => $c ? 0 : 1,
        'stop'      => $c ? 1 : 0,
        'dropCache' => $c ? 0 : 1,
      ]);
    }
    # compose result
    $q->res = [
      'title'  => $a['name'],
      'text'   => $this->text->render($a),
      'markup' => $b,
    ];
    break;
    # }}}
  /***
  case 'start':# {{{
    if ($item->bot->proc->start($id)) {
      return startbotsbot($item, '', $id);
    }
    $data['isError'] = true;
    $data['message'] = $item->bot->text['op-fail'];
    break;
    # }}}
  case 'stop':# {{{
    if ($item->bot->proc->stop($id)) {
      return startbotsbot($item, '', $id);
    }
    $data['isError'] = true;
    $data['message'] = $item->bot->text['op-fail'];
    break;
    # }}}
  case 'dropCache':# {{{
    # invoke handler
    dropCache($item, $data);
    break;
    # }}}
  /***/
  }
  return true;
},
# }}}
'/start/bots/create' => function(object $q): bool # {{{
{
  static $NEWBOT_EXP = '/^.+ t\.me\/([^.]{5,32})\..+ HTTP API:\n([^\n]{44,46})\n.+$/s';
  switch ($func) {
  case 'options':# {{{
    # there is only one field with options,
    # get and return available bot classes
    return getBotSources($item);
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
},
# }}}
'/MESSAGE' => function(object $q): ?object # {{{
{
  switch ($q->func) {
  case 'text':
    break;
  }
  return null;
},
# }}}
];
