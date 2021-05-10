<?php
return [
  'start' => # {{{
  [
    'type'   => 'menu',
    'markup' => [
      ['games'],
      ['favs','cach'],
      ['test1'],
      ['test2'],
      ['_quit'],
    ],
    'text' => [ # {{{
      'en' =>
      [
        '@' => 'menu',
        'games' => 'games',
        'favs'  => 'my games',
        'cach'  => 'cach desk',
        'test1' => 'input form (open)',
        'test2' => 'input form (inject)',
      ],
      'ru' =>
      [
        '@' => 'меню',
        'games' => 'игры',
        'favs'  => 'мои игры',
        'cach'  => 'касса',
        'test1' => 'форма ввода (открыть)',
        'test2' => 'форма ввода (встроить)',
      ],
    ],
    # }}}
    'items' => [
      'games' => # {{{
      [
        'type'   => 'list',
        'data'   => 'games',
        'public' => true,
        'cmd'    => 'game',
        'config' => [
          'rows'  => 10,
          'cols'  => 2,
          'order' => 'name',
          'desc'  => false,
          'fixed' => true,
        ],
        'markup' => [
          'foot'  => [['_prev','_up','_next']],
          'empty' => [['_up']],
        ],
        'text' => [
          'en' =>
          [
            '@' => 'games',
            '.' => '
{:video_game:} full list of games ({{item_count}})
{{#not_one_page}}
page <b>{{page}}</b> of {{page_count}}
{{/not_one_page}}
            ',
          ],
          'ru' =>
          [
            '@' => 'игры',
            '.' => '
{:video_game:} полный список игр ({{item_count}})
{{#not_one_page}}
страница <b>{{page}}</b> из {{page_count}}
{{/not_one_page}}
            ',
          ],
        ],
      ],
      # }}}
      'favs' => # {{{
      [
        'type'   => 'list',
        'data'   => 'fav_games',
        'cmd'    => 'game',
        'config' => [
          'rows'  => 6,
          'cols'  => 1,
          'order' => 'name',
          'desc'  => false,
          'fixed' => false,
        ],
        'markup' => [
          'foot'  => [['_prev','_up','_next']],
          'empty' => [['_up']],
        ],
        'text' => [
          'en' =>
          [
            '@' => 'my games',
            '.' => '
{:star:} your favorite games ({{item_count}})
{{#not_one_page}}
page <b>{{page}}</b> of {{page_count}}
{{/not_one_page}}
            ',
            '-' => '
{:star:} your favorite games (empty)
add games you like in this list
            ',
          ],
          'ru' =>
          [
            '@' => 'мои игры',
            '.' => '
{:star:} ваши избранные игры ({{item_count}})
{{#not_one_page}}
страница <b>{{page}}</b> из {{page_count}}
{{/not_one_page}}
            ',
            '-' => '
{:star:} ваши избранные игры (пусто)
добавляйте понравившиеся игры в этот список
            ',
          ],
        ],
      ],
      # }}}
      'cach' => # {{{
      [
        'type'   => 'menu',
        'markup' => [
          ['give','take'],
          ['_up'],
        ],
        'text' => [ # {{{
          'en' =>
          [
            '@' => 'cach desk',
            '.' => '
{:moneybag:} ...
            ',
            'give' => 'recharge',
            'take' => 'withdraw',
          ],
          'ru' =>
          [
            '@' => 'касса',
            '.' => '
{:moneybag:} ...
            ',
            'give' => 'пополнить',
            'take' => 'снять',
          ],
        ],
        # }}}
        'items' => [
          'give' => [
            'type' => 'input',
          ],
        ],
      ],
      # }}}
      'test1' => # {{{
      [
        'type' => 'opener',
        'path' => 'testform',
      ],
      # }}}
      'test2' => # {{{
      [
        'type' => 'injector',
        'path' => 'testform',
      ],
      # }}}
    ],
  ],
  # }}}
  'game' => # {{{
  [
    'type'   => 'game',
    'data'   => 'games',
    'markup' => [
      ['_play'],
      ['_fav','_stop',' '],
    ],
    'text'   => [
      'en' =>
      [
      ],
      'ru' =>
      [
      ],
    ],
  ],
  # }}}
  'testform' => # {{{
  [
    'type'   => 'form',
    'markup' => [
      ###
      # states:
      #  0=input,1=filled,2=requirement
      #  3=awaiting
      #  4=failure,5=success,6=errors
      ### H=header
      'H0' => [['_last']],
      'H1' => [['_refresh','_ok']],
      'H2' => [['_refresh']],
      ### F=footer
      'F0' => [['_prev','_next'],['_up']],
      'F1' => [['_up']],
    ],
    'fields' => [ # bits: 0=required,1=persistent
      'name'   => [1+2,'string',20],
      'class'  => [1+2,'list'],
      'age'    => [0+2,'int',10,100],
      'gender' => [0+2,'string',20],
      'num'    => [1+0,'int',0,9],
    ],
    'text' => [
      'en' =>
      [
        '@'      => '« title »',
        '.'      => '
This form demonstrates block interface design.
Name, class and guessed number - are required parameters.
Number is not a persistent parameter, e.g. it resets on form reload.
To emulate task calculations, delay is used, it equals to 5-10 seconds.
A simple progress indicator included.
        ',
        'name'   => 'Name',
        'class'  => 'Class',
        'age'    => 'Age',
        'gender' => 'Gender',
        'num'    => 'Number',
      ],
      'ru' =>
      [
        '@'      => '« заголовок »',
        '.'      => '
Эта форма демонстрирует дизайн блочного интерфейса.
Имя, класс и угадываемое число - обязательные параметры.
Число не является постоянным параметром, т.е. сбрасывается при перезагрузке формы.
Для эмуляции обработки задачи, используется задержка, она равна 5-10 секунд.
Имеется простой индикатор прогресса.
        ',
        'name'   => 'Имя',
        'class'  => 'Класс',
        'age'    => 'Возраст',
        'gender' => 'Пол',
        'num'    => 'Число',
      ],
    ],
    'list' => [
      'en' =>
      [
        'class'  => [3, 'Darkbone','Archer','Ghost','Berserk','Elf','Fanatic','Colonist','Infant','Hippy'],
        'gender' => [2, 'male','female','LGBTQ+','heli'],
        'num'    => [3, '1','2','3','4','5','6','7','8','9'],
      ],
      'ru' =>
      [
        'class'  => [3, 'Darkbone','Archer','Ghost','Berserk','Elf','Fanatic','Colonist','Infant','Hippy'],
        'gender' => [2, 'мужской','женский','LGBTQ+','heli'],
        'num'    => [3, '1','2','3','4','5','6','7','8','9'],
      ],
    ],
  ],
  # }}}
];
?>
