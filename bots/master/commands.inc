<?php return
[
  'start' => # {{{
  [
    'markup'=> [
      ['bots'],
      [' '],
      ['_up'],
    ],
    'text'=> [
      'bots' => 'bots',
    ],
    'items' => [
      'bots' => # {{{
      [
        'type' => 'list',
        'opts' => [
          'rows'  => 4,
          'cols'  => 1,
          'order' => 'order',
          'desc'  => true,
        ],
        'markup' => [
          '.' => [['_prev','_add','_next'],['_up']],
        ],
        'text' => [
          'add' => 'add',
          'name' =>
          '{{bot}}{{#up}}{:zap:}{{|}}{:white_small_square:}{{/up}}{{name}}',
        ],
        'items' => [
          'bot' => [
            'markup'=> [['_kek'],[' '],['_up']],
            'text'=> [
              'kek' => 'kek test',
            ],
          ],
        ],
      ],
      # }}}
    ],
  ],
  # }}}
  'test' => # {{{
  [
    'markup'=> [
      [' '],
      ['_up'],
    ],
    'items' => [
      'menu1' =>
      [
        'type' => 'inject',
        'path' => 'testmenu',
      ],
      'menu2' =>
      [
        'type' => 'open',
        'path' => 'testmenu',
      ],
      'form1' =>
      [
        'type' => 'inject',
        'path' => 'testform',
      ],
      'form2' =>
      [
        'type' => 'open',
        'path' => 'testform',
      ],
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
  'alien' => # {{{
  [
    'type'=> 'captcha',
    'brand'=>'aliengineer',# images
    'timeout'=> 30,# seconds
    'intro'=> 1,# special first question
    'retry'=> 15,# seconds
    'rowLimit'=> 3,# maximal number of answers in a row
    'markup'=> [# questions
      ['q0','q10'],
      ['q9'],
      ['q7','q8'],
      ['q1','q2','q3','q4','q5','q6'],
    ],
    'markupComplete'=>[['_reset','_up']],
    'text' => [
      'en' =>
      [
        '@' => 'Are you human?',# title (no image)
        'retry'=> '{:arrow_right_hook:} retry{{#x}} in {{x}}{{/x}}',
        'reset'=> 'restart',
        'q0' => # {{{
        '
Are you human?
        ',
        'q0a'  => '0',
        'q0a0' => 'yes',
        'q0a1' => 'no',
        # }}}
        'q1' => # {{{
        '
What will be the result of:
<code>
(function (a) {
  a = [\'one\',\'two\'];
  a.length = 0;
  return a[0];
})();
</code>
        ',
        'q1a'  => '0',
        'q1a0' => 'undefined',
        'q1a1' => '',
        'q1a2' => 'null',
        'q1a3' => '"one"',
        'q1a4' => '"two"',
        # }}}
        'q2' => # {{{
        '
What will be the result of:
<code>
(function (size, nums) {
  for (var i = 0; i &lt; size; ++i);{
    nums.push(i + 1);
  }
  return nums.join(\',\');
})(4, []);
</code>
        ',
        'q2a'  => '4',
        'q2a0' => '{:boom:}FATAL',
        'q2a1' => '1,2,3,4',
        'q2a2' => '1,2,3,4,5',
        'q2a3' => '4',
        'q2a4' => '5',
        # }}}
        'q3' => # {{{
        '
What will be the result of:
<code>
(function (size, nums) {
  for (let i = 0; i &lt; size; ++i);{
    nums.push(i + 1);
  }
  return nums.join(\',\');
})(4, []);
</code>
        ',
        'q3a'  => '0',
        'q3a0' => '{:boom:}FATAL',
        'q3a1' => '1,2,3,4',
        'q3a2' => '1,2,3,4,5',
        'q3a3' => '4',
        'q3a4' => '5',
        # }}}
        'q4' => # {{{
        '
What will be the result of:
<code>
(function (item) {
  return
    [item];
})(1);
</code>
        ',
        'q4a'  => '3',
        'q4a0' => '{:boom:}FATAL',
        'q4a1' => '1',
        'q4a2' => '[1]',
        'q4a3' => 'undefined',
        'q4a4' => '[]',
        'q4a5' => '0',
        # }}}
        'q5' => # {{{
        '
What will be the console output:
<code>
for (let a = 0; a &lt; 3; ++a)
{
  const b = () =&gt; {console.log(a);}
  setTimeout(b, 100);
}
</code>
        ',
        'q5a'  => '2',
        'q5a0' => '{:boom:}FATAL',
        'q5a1' => '3 3 3',
        'q5a2' => '0 1 2',
        # }}}
        'q6' => # {{{
        '
What will be the console output:
<code>
for (var a = 0; a &lt; 3; ++a)
{
  const b = () =&gt; {console.log(a);}
  setTimeout(b, 100);
}
</code>
        ',
        'q6a'  => '1',
        'q6a0' => '{:boom:}FATAL',
        'q6a1' => '3 3 3',
        'q6a2' => '0 1 2',
        # }}}
        'q7' => # {{{
        '
Asking without permission is..
        ',
        'q7a'  => '0,1',
        'q7a0' => 'recommended',
        'q7a1' => 'encouraged',
        'q7a2' => 'prohibited',
        'q7a3' => 'undefined',
        # }}}
        'q8' => # {{{
        '
Asking permission is..
        ',
        'q8a'  => '2',
        'q8a0' => 'recommended',
        'q8a1' => 'encouraged',
        'q8a2' => 'prohibited',
        'q8a3' => 'undefined',
        # }}}
        'q9' => # {{{
        '
Photos of the screen (snapshots) are..
        ',
        'q9a'  => '2',
        'q9a0' => 'recommended',
        'q9a1' => 'encouraged',
        'q9a2' => 'prohibited',
        'q9a3' => 'undefined',
        # }}}
        'q10' => # {{{
        '
Are you bot?
        ',
        'q10a'  => '1',
        'q10a0' => 'yes',
        'q10a1' => 'no',
        # }}}
      ],
    ],
  ],
  # }}}
];
?>
