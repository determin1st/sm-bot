<?php return [
  'start' => # {{{
  [
    'markup' => [
      ['images'],
      ['forms'],
      [' '],
      ['!up'],
    ],
    'items' => [
      'images' => # {{{
      [
        'markup' => [['t1','t2','t3'],['!up']],
        'text' => [
          '@'=>'image/title tests'
        ],
        'items' => [
          't1' =>
          [
            'title' => ['color' => [40,40,80]],
            'markup' => [['!up']],
          ],
          't2' =>
          [
            'image' => ['file' => 'big.jpg'],
            'markup' => [['!up']],
          ],
          't3' =>
          [
            'title' => ['file' => 'block-640x160.jpg'],
            'markup' => [['!up']],
          ],
        ],
      ],
      # }}}
      'forms' => # {{{
      [
        'markup' => [['t1'],['!up']],
        'text' => [
          '@'=>'form tests'
        ],
        'items' => [
          't1' => # {{{
          [
            'type'     => 'form',
            'datafile' => 1,
            'config'   => [
              'okConfirm' => false,
            ],
            'markup' => [],
            'fields' => [# bits: 1:required+2:persistent+4:nullable+8:hidden
              [
                'phone' => [1+2, 'string', 89031234567, '/^\+{0,1}\d{11}$/'],
                'city'  => [1, 'string', 20],
              ], [
                'code'  => [1, 'int', 100, 999],
              ],
            ],
            'text' => [
              'en'  => [
                '@' => 'sms',
              ],
              'en'  => [
                '@' => 'sms',
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
  'help'=> # {{{
  [
    'markup'=> [['tree'],['_up']],
    'text'=> [
      '.'=>'
{:tada:} eh
      ',
    ],
    'items'=> [
      'tree'=> # {{{
      [
        'markup' => [['nesting'],['cycle'],['_up']],
        'items' => [
          'nesting' => # {{{
          [
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
                'markup' => [['deeper'], ['_up']],
                'text' => [
                  'en' => ['@' => 'test'],
                ],
                'items' => [
                  'deeper' => # {{{
                  [
                    'markup' => [['link'], ['_up']],
                    'text' => [
                      'en' => ['@' => 'deeper'],
                    ],
                    'items' => [
                      'link' => # {{{
                      [
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
            'markup' => [['start'],['_up']],
            'text' => [
              '.' => '
Nodes below, don\'t have an upward button.
              ',
            ],
            'items' => [
              'start' =>
              [
                'markup' => [['play']],
                'items' => [
                  'play' =>
                  [
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
      'form' => # {{{
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
╨Я╨╛╨┐╤А╨╛╨▒╤Г╨╣╤В╨╡ ╨╖╨░╨┐╨╛╨╗╨╜╨╕╤В╤М ╤Д╨╛╤А╨╝╤Г ╨╕ ╤Г╨│╨░╨┤╨░╤В╤М ╤З╨╕╤Б╨╗╨╛.
            ',
            'name'   => '╨Ш╨╝╤П',
            'class'  => '╨Ъ╨╗╨░╤Б╤Б',
            'age'    => '╨Т╨╛╨╖╤А╨░╤Б╤В',
            'gender' => '╨Я╨╛╨╗',
            'num'    => '╨з╨╕╤Б╨╗╨╛',
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
            'gender' => [1, '╨╝╤Г╨╢╤Б╨║╨╛╨╣','╨╢╨╡╨╜╤Б╨║╨╕╨╣','LGBTQ+'],
            'num'    => [3, 1,2,3,4,5,6,7,8,9],
          ],
        ],
      ],
      # }}}
    ],
  ],
  # }}}
];
?>
