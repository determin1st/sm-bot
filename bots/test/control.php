<?php
namespace SM;
class item_testmenu_tree_cycle_start_play {
  public static function render($bot, &$item) # {{{
  {
    # throw two dices
    $dice1  = rand(1,6);
    $dice2  = rand(1,6);
    $sum    = $dice1 + $dice2;
    $luck   = ($sum === 12);
    # render text
    $item['textContent'] = $bot->render_content($item['content'], [
      'dice1' => $dice1,
      'dice2' => $dice2,
      'sum'   => $sum,
      'luck'  => $luck,
    ]);
    # add dice values to the title identifier and
    # check bot's file_id storage (don't render already cached)
    $id = $item['titleId']."-$dice1-$dice2";
    if (!($file = $bot->getFileId($id)))
    {
      # render title (overlay)
      # create blank title image with breadcrumbs
      $bread = $bot->itemBreadcrumb($item, $bot->user->lang);
      $img   = $bot->imageTitle('', $bread, null, '', 0);
      # open dice imagepack
      $dices = $bot->imgdir.'dice.png';
      if (($dices = imagecreatefrompng($dices)) === false)
      {
        $bot->log("imagecreatefromjpeg($dices) failed");
        return true;
      }
      # determine dice picture rects
      $rect1 = self::getDiceRect($dice1);
      $rect2 = self::getDiceRect($dice2);
      # determine dice area offsets (center align)
      $a = $rect1[2] + $rect2[2];# total width
      $b = $rect1[3];# total height
      $x = (640 - $a) / 2;# item's title has constant width
      $y = 9 + (160 - $b) / 2;# and, height as well
      # for proper overlay,
      # set dice transparent color (background)
      $a = imagecolorat($dices, 1, 1);
      $b = imagecolortransparent($dices, $a);
      # copy both dices to the title
      $a = imagecopy(
        $img, $dices,
        $x, $y,
        $rect1[0], $rect1[1], $rect1[2], $rect1[3]
      );
      $b = imagecopy(
        $img, $dices,
        $x + $rect1[2], $y,
        $rect2[0], $rect2[1], $rect2[2], $rect2[3]
      );
      # create a file
      $file = $bot->imageToFile($img);
      imagedestroy($dices);
    }
    # complete
    $item['titleId'] = $id;
    $item['titleImage'] = $file;
    return true;
  }
  public static function getDiceRect($dice)
  {
    static $spec = [73,71];# [width,height]
    # prepare
    $rect = [0,0,$spec[0],$spec[1]];
    $dice = $dice - 1;# [1..N] => [0..N-1]
    # one row contains 3 images
    while ($dice > 2)
    {
      ++$rect[1];
      $dice = $dice - 3;
    }
    # calculate offsets
    $rect[0] = 1 + $dice * (1 + $spec[0]);
    $rect[1] = 1 + $rect[1] * (1 + $spec[1]);
    return $rect;
  }
  # }}}
}
class task_testformmm {
  # DELET {{{
  public static function task($bot, $plan)
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
    return [$res, self::$result[$lang][$msg]];
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
