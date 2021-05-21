<?php
namespace SM;
class type_game {
  # {{{
  public static function handle($bot, $plan)
  {
    # prepare {{{
    # check data
    if (!$data || !($count = count($data)))
    {
      $error = self::$messages[$lang][0];
      return $item;
    }
    # determine game identifier
    if (!$func && $args)
    {
      # specified
      if (($id = $args[0]) === '-1') {
        $id = array_rand($data, 1);# random
      }
    }
    else
    {
      # previous
      $id = array_key_exists('id', $conf)
        ? $conf['id']
        : '';
    }
    # get game
    if (!array_key_exists($id, $data))
    {
      $this->log('game not found');
      $error = self::$messages[$lang][2];
      return $item;
    }
    $data = $data[$id];
    # get game icon
    $a = $this->data['config']['created'];
    if ($isCreated = in_array($id, $a))
    {
      $icon_id = '';
      $icon = null;
    }
    else
    {
      ###
      # game icon is only related to multigame,
      # it may include instructions and full-size logo
      # for admin to create the game with @botfather
      # also, when remote icon is not provided,
      # it will be auto-generated (as a title block)
      ###
      if ($icon = $data['icon'])
      {
        # get stored file_id or
        # compose remote url for upload
        $icon_id = 'game-'.$id.'-icon';
        $icon = array_key_exists($icon_id, $this->fids)
          ? $this->fids[$icon_id]
          : $this->b2b->getIconUrl($icon);
      }
      else
      {
        # get stored file_id or
        # generate temporary icon file
        $icon_id = 'game-'.$id.'-name';
        if (array_key_exists($icon_id, $this->fids)) {
          $icon = $this->fids[$icon_id];
        }
        elseif (!($icon = $this->imageTitle($data['name'])))
        {
          $error = self::$messages[$lang][2];
          return $item;
        }
      }
    }
    # get favorites
    $a = 'fav_'.$item['data'];
    $fav_data = null;
    if (array_key_exists($a, $this->data)) {
      $fav_data = &$this->data[$a];
    }
    # }}}
    # handle function {{{
    switch ($func) {
    case 'fav_on':
      if ($fav_data !== null && !array_key_exists($data['id'], $fav_data)) {
        $fav_data[$data['id']] = $data;
      }
      break;
    case 'fav_off':
      if ($fav_data !== null && array_key_exists($data['id'], $fav_data)) {
        unset($fav_data[$data['id']]);
      }
      break;
    }
    # }}}
    # check {{{
    if ($page !== $conf['page'])
    {
      $this->user->changed = true;
    }
    # }}}
    # determine markup {{{
    # set favorite control
    $a = ($fav_data === null)
      ? ''
      : '_fav_'.(array_key_exists($data['id'], $fav_data)
        ? 'off'
        : 'on');
    $this->setMarkupItem($item['markup'], '_fav', $a);
    # create
    $mkup = $this->itemInlineMarkup(
      $item, $item['markup'], $text
    );
    # }}}
    # set {{{
    $conf['id']         = $id;
    $data['icon_id']    = $icon_id;
    $data['icon']       = $icon;
    $data['is_created'] = $isCreated;
    $item['data']       = &$data;
    # }}}
  }
  # }}}
  private function editMarkup($markup, $msg) # {{{
  {
    $a = $this->api->send('editMessageReplyMarkup', [
      'chat_id'      => $this->user->chat->id,
      'message_id'   => $msg,
      'reply_markup' => $markup,
    ]);
    if (!$a || $a === true)
    {
      $this->log($this->api->error);
      return -1;
    }
    return $a->result->message_id;
  }
  # }}}
  private function sendGame(&$item) # {{{
  {
    # check variant
    if (($game = $item['data'])['is_created'])
    {
      # send created game
      $this->log('game: '.$game['name']);
      $icon = 0;
      $res = $this->api->send('sendGame', [
        'chat_id'              => $this->user->chat->id,
        'game_short_name'      => 'a'.$game['id'],
        'disable_notification' => true,
        'reply_markup'         => $item['markup'],
      ]);
    }
    else
    {
      # send multigame
      $this->log('multigame: '.$game['name']);
      $icon = $this->sendImage($game['icon_id'], $game['icon'], '', '');
      if (!$icon) {
        return 0;
      }
      $res = $this->api->send('sendGame', [
        'chat_id'              => $this->user->chat->id,
        'game_short_name'      => 'multigame',
        'disable_notification' => true,
        'reply_markup'         => $item['markup'],
      ]);
    }
    # check result
    if (!$res)
    {
      $this->log($this->api->error);
      return 0;
    }
    # remove previous icon
    $conf = &$item['config'];
    if (array_key_exists('icon', $conf) && $conf['icon'])
    {
      $a = $this->api->send('deleteMessage', [
        'chat_id'    => $this->user->chat->id,
        'message_id' => $conf['icon'],
      ]);
      if (!$a) {
        $this->log($this->api->error);
      }
    }
    # update config
    $conf['icon'] = $icon;
    # done
    return $res->result->message_id;
  }
  # }}}
  private function getGameUrl($name, $msg) # {{{
  {
    # get data
    if (!($data = $this->data['games']) || !count($data)) {
      return '';
    }
    # determine game identifier
    if ($name === 'multigame')
    {
      # multigame
      # get identifier from the game command configuration
      $id = $this->user->config;
      if (!array_key_exists('game', $id) ||
          !($id = $id['game']) ||
          !array_key_exists('id', $id) ||
          !($id = intval($id['id'])))
      {
        return '';
      }
      # check
      if ($id === -1)
      {
        # randomize?
        return '';
      }
      $id = strval($id);
      if (!$msg)
      {
        $this->log('TODO: REPOSTED GAME CALLBACK');
      }
    }
    else
    {
      # created game (name includes identifier)
      $id = substr($name, 1);
    }
    # complete
    return array_key_exists($id, $data)
      ? $this->b2b->getGameUrl($data[$id]['url'])
      : '';
  }
  # }}}
  private function getRefList($file, &$list) # {{{
  {
    ###
    ###
    # TODO: load favorite games list
    #$a = $dir.'fav_games.json';
    #$this->data['fav_games'] = $this->getRefList($a, $this->data['games']);
    # check
    if (!$list || !file_exists($file)) {
      return [];
    }
    # load identifiers and
    # create reference list
    $a = json_decode(file_get_contents($file), true);
    $b = [];
    foreach ($a as $c)
    {
      if (array_key_exists($c, $list)) {
        $b[$c] = &$list[$c];
      }
    }
    # done
    return $b;
  }
  # }}}
  private function setRefList($file, &$list) # {{{
  {
    if ($list)
    {
      # collect identifiers
      $a = [];
      foreach ($list as $b) {
        $a[] = $b['id'];
      }
      # store
      if (file_put_contents($file, json_encode($a)) === false)
      {
        $this->log('file_put_contents('.$a.') failed');
        return false;
      }
      return true;
    }
    return file_exists($file)
      ? unlink($file) : true;
  }
  # }}}
  private function setDataConfig() # {{{
  {
    $a = $this->dir.'games-config.json';
    $b = json_encode($this->data['config']);
    if (file_put_contents($a, $b) === false)
    {
      $this->log('file_put_contents('.$a.') failed');
      return false;
    }
    return true;
  }
  # }}}
  private function loadData() # {{{
  {
    # check stored
    $file = $this->dir.'games.json';
    if (file_exists($file))
    {
      # load from cache
      if (!($a = file_get_contents($file)) ||
          !($a = json_decode($a, true)))
      {
        $this->log('failed to load '.$file);
        return null;
      }
      # load configuration
      $file = $this->dir.'games-config.json';
      if (!file_exists($file) ||
          !($b = file_get_contents($file)) ||
          !($b = json_decode($b, true)))
      {
        $this->log('failed to load '.$file);
        return null;
      }
    }
    else
    {
      # load from remote
      if (!($a = $this->b2b->getGames()))
      {
        $this->log($this->b2b->error);
        return null;
      }
      # store
      file_put_contents($file, json_encode($a));
      # create initial config
      $file = $this->dir.'games-config.json';
      $b = [
        'created' => [],# non-multigames
      ];
      file_put_contents($file, json_encode($b));
    }
    # set configuration
    $a['config'] = $b;
    # done
    return $a;
  }
  # }}}
}
?>
