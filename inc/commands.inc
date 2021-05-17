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
      ['_restartLoop'],
      ['form1','form2'],
      ['_up'],
    ],
    'text' => [ # {{{
      'en' =>
      [
        '@' => 'debug',
        '_restartLoop' => 'restart loop',
        'form1' => 'testform (inject)',
        'form2' => 'testform (open)',
      ],
    ],
    # }}}
    'items' => [
      'form1' => # {{{
      [
        'type' => 'injector',
        'path' => 'testform',
      ],
      # }}}
      'form2' => # {{{
      [
        'type' => 'opener',
        'path' => 'testform',
      ],
      # }}}
    ],
  ],
  # }}}
  'testmenu' => # {{{
  [
    'type'   => 'menu',
    'markup' => [['tree'],['_up']],
    'text' => ['en'=>['@'=>'testmenu','.'=>'root node']],
    'items' => [
      'tree' => # {{{
      [
        'type' => 'menu',
        'markup' => [['nesting'], ['_up']],
        'text' => [
          'en' => ['@' => 'tree'],
        ],
        'items' => [
          'nesting' => # {{{
          [
            'type' => 'menu',
            'markup' => [['test'],['another'],['_up']],
            'text' => [
              'en' => ['@' => 'nesting'],
            ],
            'items' => [
              'test' => # {{{
              [
                'type' => 'menu',
                'markup' => [['deeper'], ['_up']],
                'text' => [
                  'en' => ['@' => 'test'],
                ],
                'items' => [
                  'deeper' => # {{{
                  [
                    'type' => 'menu',
                    'markup' => [['link'], ['_up']],
                    'text' => [
                      'en' => ['@' => 'deeper'],
                    ],
                    'items' => [
                      'link' => # {{{
                      [
                        'type' => 'menu',
                        'markup' => [['_up']],
                        'text' => [
                          'en' => [
                            '@' => 'link',
                            '.' => 'nothing is here, navigate back?'
                          ],
                        ],
                        'items' => [],
                      ],
                      # }}}
                    ],
                  ],
                  # }}}
                ],
              ],
              # }}}
              'another' => # {{{
              [
                'type'   => 'menu',
                'markup' => [['_up']],
                'text' => [
                  'en' => [
                    '@' => 'another',
                    '.' => 'leaf node',
                  ],
                ],
                'items' => [],
              ],
              # }}}
            ],
          ],
          # }}}
        ],
      ],
      # }}}
    ],
  ],
  # }}}
  'testform' => # {{{
  [
    'type' => 'form',
    'noRequiredSkip'   => 1,
    'noRequiredSubmit' => 1,
    'submitOnInput'    => 0,
    'submitOnSelect'   => 0,
    'submitConfirm'    => 0,
    'inputMoveAround'  => 0,
    'markup' => [
      ###
      # [S]tates:
      # 0=input,1=filled,2=requirements;3=awaiting;4=failure,5=success,6=errors
      ###
      'S0' => [['_prev','_next','_last'],['_up']],
      'S1' => [['_refresh','_ok'],['_up']],
      'S2' => [['_first','_refresh'],['_up']],
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
    'options' => [
      'en' =>
      [
        'class'  => [3, 'Darkbone','Archer','Ghost','Berserk','Elf','Fanatic','Colonist','Infant','Hippy'],
        'gender' => [1, 'male','female','LGBTQ+'],
        'num'    => [3, 1,2,3,4,5,6,7,8,9],
      ],
      'ru' =>
      [
        'class'  => [3, 'Darkbone','Archer','Ghost','Berserk','Elf','Fanatic','Colonist','Infant','Hippy'],
        'gender' => [1, 'мужской','женский','LGBTQ+'],
        'num'    => [3, 1,2,3,4,5,6,7,8,9],
      ],
    ],
  ],
  # }}}
];
?>
