<?php
return [
  'debug' => # {{{
  [
    'type'   => 'menu',
    'markup' => [
      ['_restartLoop'],
      ['menu1','menu2'],
      ['form1','form2'],
      ['_up'],
    ],
    'text' => [ # {{{
      '@' => 'debug',
      '_restartLoop' => 'restart loop',
      'menu1' => 'menu (inject)',
      'menu2' => 'menu (open)',
      'form1' => 'form (inject)',
      'form2' => 'form (open)',
    ],
    # }}}
    'items' => [
      'menu1' => # {{{
      [
        'type' => 'injector',
        'path' => 'testmenu',
      ],
      # }}}
      'menu2' => # {{{
      [
        'type' => 'opener',
        'path' => 'testmenu',
      ],
      # }}}
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
    'type' => 'menu',
    'blocks' => ['test'],
    'markup' => [['tree'],['_up']],
    'text' => ['en'=>['.'=>'Root node']],
    'items' => [
      'tree' => # {{{
      [
        'type' => 'menu',
        'markup' => [['nesting'],['cycle'],['_up']],
        'items' => [
          'nesting' => # {{{
          [
            'type' => 'menu',
            'markup' => [['test'],['another'],['_up']],
            'text' => [
              'en' => [
                '.' => '
This node has 2 items (separate branches). You can navigate in one or another, as well as out to the parent node.
                ',
              ],
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
                'markup' => [['_go:testmenu:tree:nesting:test:deeper:link'],['_up']],
                'text' => [
                  'en' => [
                    '@' => 'another',
                    '.' => '
leaf node, as well as:
<code>/testmenu/tree/nesting/test/deeper/link</code>

You may also type a node path as a command, the bot will try to remove this message and create a new one (re-creation). That usually happens fast enough for user to mention. Why? Because it makes item current (user intention) - new message is always the last one displayed in the chat.
                    ',
                  ],
                ],
                'items' => [],
              ],
              # }}}
            ],
          ],
          # }}}
          'cycle' => # {{{
          [
            'type' => 'menu',
            'markup' => [['start'],['_up']],
            'text' => [
              '.' => '
Nodes below, don\'t have an upward button.
              ',
            ],
            'items' => [
              'start' =>
              [
                'type' => 'menu',
                'markup' => [['play']],
                'items' => [
                  'play' =>
                  [
                    'type' => 'menu',
                    'markup' => [['stop']],
                    'text' => ['.' => '
This node has a custom handler. It throws 2 dices and shows their sum at each render. 
Current sum is <code>{{sum}}</code>. {{^luck}}Prize at <code>12</code>.{{/luck}}
{{br}}{{br}}
{{#luck}}
  Here is your prize:{{br}}
  {:star:} https://youtu.be/dQw4w9WgXcQ
{{/luck}}
{{br}}
                    '],
                    'items' => [
                      'stop' =>
                      [
                        'type' => 'menu',
                        'markup' => [['_go:testmenu:tree:cycle:start']],
                        'text' => ['.' => '
Able to get out?
                        '],
                        'items' => [],
                      ],
                    ],
                  ],
                ],
              ],
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
    'blocks' => ['test'],
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
