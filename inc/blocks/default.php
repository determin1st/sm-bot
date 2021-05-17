<?php
namespace SM;
class type_game {
  # {{{
  public static function handle($bot, $plan)
  {
  }
  public static
    $message = '',
    $result  = [
      'en' =>
      [
        0 => '',
      ],
      'ru' =>
      [
        0 => '',
      ],
    ];
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
class task_testform {
  # {{{
  public static function handle($bot, $plan)
  {
    # prepare {{{
    $lang = $bot->user->lang;
    $item = $bot->getItem($plan['item']);
    $data = $plan['data'];
    $gender = array_key_exists('gender', $data)
      ? $data['gender']
      : '';
    $gender = (array_search($gender, $item['list'][$lang]['gender']) ?: 0);
    $age = array_key_exists('age', $data)
      ? $data['age']
      : 0;
    $name = array_key_exists('name', $data)
      ? $data['name']
      : '';
    # suspend for a while..
    if ($bot->opts['debug_task']) {
      sleep(1);
    }
    else {
      sleep(rand(5, 10));
    }
    # determine correct number
    $number = intval($data['num']);
    $secret = 1 + ord($data['class'][0]) - ord('A');
    $res = 0;
    $msg = -1;
    # }}}
    # operate {{{
    if (!$name)
    {
      # anonymous user
      if ($number === $secret)
      {
        # RIGHT, but
        # conspiracy random chance is 1%
        if ($res = (rand(0, 99) ? 0 : 1))
        {
          # rare, but may happen (monkey business)
          $msg = 9;
        }
        elseif ($gender === 2)
        {
          # random is disabled for females
          if ($age < 60)
          {
            # female has a hint
            $res = 0;
            $msg = 7;
          }
          else
          {
            # just please grandma
            $res = 1;
            $msg = 8;
          }
        }
        else
        {
          # right-to-wrong anonymous conspiracy plot
          $res = 0;
          $msg = 10;
        }
      }
      else
      {
        # WRONG
        if ($gender === 2)
        {
          if ($age < 60)
          {
            # female has a hint
            $msg = 11;
          }
          else
          {
            # just please grandma
            $res = 1;
            $msg = 8;
          }
        }
        else
        {
          # just wrong
          $res = 0;
          $msg = 0;
        }
      }
    }
    elseif ($number === 0)
    {
      # leet number
      $res = 1;
      $msg = 1337;
    }
    elseif ($number === $secret)
    {
      # CORRECT
      if ($gender === 3)
      {
        # LGBT reversed random (age doesnt matter)
        $res = (!rand(0, 3) ? 1 : 0);
        $msg = ($res ? 1 : 2);
      }
      elseif ($age > 30)
      {
        # concious binary adult
        if ($gender === 1)
        {
          # 20% male chances
          $res = rand(0,4) ? 0 : 1;
          $msg = 3;
        }
        elseif ($gender === 2)
        {
          # female has secret number hint
          $res = 1;
          $msg = 4;
        }
        else
        {
          # no gender
          $res = 1;
          $msg = 1;
        }
      }
      elseif ($age && $age < 30)
      {
        # unconsious binary
        $res = 1;
        $msg = 5;
      }
      else
      {
        # assume binary
        $res = 1;
        $msg = 1;
      }
    }
    elseif ($gender === 3)
    {
      # WRONG LGBT reversed positive
      $res = 1;
      $msg = 6;
    }
    else
    {
      # WRONG binary
      $res = 0;
      $msg = 0;
    }
    # }}}
    self::$message = base64_encode(self::$result[$lang][$msg]);
    return $res;
  }
  public static
    $message = '',
    $result  = [
      'en' =>
      [
        0 => 'The chosen number doesn\'t match',
        1 => 'You successfully guessed the number, or, you made a consious decision. Able to find more?',
        2 => 'Pervert\'s world reversed, you were right, but doors were closed this time, try more..',
        3 => 'As a concious male adult, you were right, but your chances to deliver the truth are low, so try more..',
        4 => 'Congratulations! You are very correct (btw, there is another type of correct number in this test)',
        5 => 'As an unconcious grownup, you are luckily pushed the right button, Able to find more?',
        6 => "Life in reversed mode has its benefits, perverts win when they lose",
        7 => 'You were correct, but, anonymosity became a scarce resource these days. As a female you have a hint: you may either impersonate you name (just type anything) or try again with 1% chance..',
        8 => 'Oooke grandma, you cannot be wrong.. (this trick is often used by prankers)',
        9 => 'Monkey business!',
        10 => 'These days, when anonymousity became a scarce resource, you were right without success, retries are futile.. (actually, there is 1% chance)',
        11 => 'Answer was wrong, adding age or name may give you another hints..',
        1337 => "Secret number found.\n\nhttps://t.me/opensourceclub",
      ],
      'ru' =>
      [
        0 => 'Выбранное число не подходит',
        1 => 'Вы успешно угадали число, либо, вы сделали осознанный выбор. Способны на большее?',
        2 => 'Мир извращенца - перевернут, в этот раз вы были правы, но двери были закрыты, попробуйте еще раз..',
        3 => 'Как взрослый мужчина в сознании, вы были правы, но ваши шансы донести верный ответ малы, так что попробуйте еще..',
        4 => 'Поздравляю! Вы совершенно правы (кстати, в этом тесте есть еще один тип верного числа)',
        5 => 'Как бессознательному подростку, вам повезло нажать на правильную кнопку',
        6 => 'Жизнь в перевернутом режиме имеет свои преимущества, извращенцы побеждают, когда проигрывают',
        7 => 'Вы оказались правы, однако в наши дни, анонимность стала ценным ресурсом. Для женщины есть подсказка: вы можете выдать себя за другого (просто введите любое имя) либо попробуйте снова с шансом на успех 1%..',
        8 => 'Oooke бабуль, ты не можешь быть не права.. (эта уловка часто используется пранкерами)',
        9 => 'Мартышкин труд!',
        10 => 'Сегодня анонимность стала ценным ресурсом, вы оказались правы но без успеха, повторные попытки бесполезны.. (на самом деле есть шанс в 1%)',
        11 => 'Ответ оказался неверным, если добавить возраст или имя это даст вам больше подсказок..',
        1337 => "Обнаружен скрытый номер.\n\nhttps://t.me/opensourceclub",
      ],
    ];
  # }}}
}
?>
