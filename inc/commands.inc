<?php
return [
  'game' => # {{{
  [
    'type'   => 'game',
    'data'   => 'games',
    'markup' => [
      ['_play'],
      ['_fav','_up',' '],
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
  'debug' => # {{{
  [
    'type'   => 'menu',
    'markup' => [
      ['test1','test2'],
      ['_restartLoop'],
    ],
    'text' => [ # {{{
      'en' =>
      [
        '@' => 'debug',
        '_restartLoop' => 'restart getUpdates loop',
        'test1' => 'testform (inject)',
        'test2' => 'testform (open)',
      ],
      'ru' =>
      [
        '@' => 'debug',
        '_restartLoop' => 'restart getUpdates loop',
        'test1' => 'testform (inject)',
        'test2' => 'testform (open)',
      ],
    ],
    # }}}
    'items' => [
      'test1' => # {{{
      [
        'type' => 'injector',
        'path' => 'testform',
      ],
      # }}}
      'test2' => # {{{
      [
        'type' => 'opener',
        'path' => 'testform',
      ],
      # }}}
    ],
  ],
  # }}}
  'testform' => # {{{
  [
    'type'   => 'form',
    'markup' => [
      ###
      # states:
      #  0=input,1=filled,2=requirements
      #  3=awaiting
      #  4=failure,5=success,6=errors
      ### H=header
      'H1' => [['_refresh','_ok']],
      'H2' => [['_first','_refresh']],
      ### F=footer
      'F0' => [['_prev','_next','_last'],['_up']],
      'F1' => [['_up']],
    ],
    'fields' => [ # bits: 0=required,1=persistent
      'name'   => [0+2,'string',20],
      'class'  => [1+2,'list'],
      'age'    => [0+2,'int',10,100],
      'gender' => [0+2,'string',20],
      'num'    => [1+0,'int',0,9],
    ],
    'text' => [
      'en' =>
      [
        '@'      => 'testform',
        '.'      => '
Fill the form and try to guess the number.
        ',
        'name'   => 'Name',
        'class'  => 'Class',
        'age'    => 'Age',
        'gender' => 'Gender',
        'num'    => 'Number',
      ],
      'ru' =>
      [
        '@'      => 'testform',
        '.'      => '
Попробуйте заполнить форму и угадать число.
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
        'gender' => [1, 'male','female','LGBTQ+'],
        'num'    => [3, '1','2','3','4','5','6','7','8','9'],
      ],
      'ru' =>
      [
        'class'  => [3, 'Darkbone','Archer','Ghost','Berserk','Elf','Fanatic','Colonist','Infant','Hippy'],
        'gender' => [1, 'мужской','женский','LGBTQ+'],
        'num'    => [3, '1','2','3','4','5','6','7','8','9'],
      ],
    ],
  ],
  # }}}
];
?>
