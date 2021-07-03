<?php return
[
  'start' => # {{{
  [
    'type'=> 'menu',
    'markup'=> [
      ['botlist','newbot'],
      [' '],
      ['_up'],
    ],
    'text'=> [
      'botlist' => 'bots',
      'newbot'  => 'new bot',
    ],
    'items' => [
      'botlist' => # {{{
      [
        'type'   => 'list',
        'config' => [
          'rows'  => 8,
          'cols'  => 1,
          'order' => 'name',
          'desc'  => false,
          'fixed' => true,
        ],
        'markup' => [
          '.' => null,
          '-' => null,
        ],
        'text' => [
          '@' => null,
          '.' => null,
          '-' => null,
          'name' =>
          '
{{#up}}{:white_small_square:} {{/up}}{{id}}
          ',
        ],
      ],
      # }}}
      'newbot' => # {{{
      [
        'type'=> 'injector',
        'path'=> 'testmenu',
      ],
      # }}}
    ],
  ],
  # }}}
  'debug' => # {{{
  [
    'type'=> 'menu',
    'markup'=> [
      ['_restartLoop'],
      ['_up'],
    ],
    'text'=> [ # {{{
      '@'=> 'debug',
      '_restartLoop'=> 'restart getUpdates',
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
  'help'=> # {{{
  [
    'type'=> 'menu',
    'markup'=> [['tree'],['_up']],
    'text'=> [
      '.'=>'
{:tada:} eh
      ',
    ],
    'items'=> [
      'tree'=> # {{{
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
  ### tests
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
  'vim' => # {{{
  [
    'type'=>'captcha',
    'brand'=>'aliengineer',# images
    'timeout'=> 30,# seconds
    'intro'=> 1,# special first question
    'retry'=> 15,# seconds
    'rowLimit'=> 3,# maximal number of answers in a row
    'markup'=> [# questions
      ['q0','q10'],
      ['q9'],
      ['q7','q8'],
      ['q1','q2','q3'],
    ],
    'markupComplete'=>[['_reset','_up']],
    'text' => [
      'en' =>
      [
        '@' => 'Are you human?',
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
To copy a line under the cursor in normal mode..
        ',
        'q1a'  => '0',
        'q1a0' => 'yy',
        'q1a1' => 'y$',
        'q1a2' => 'yw',
        'q1a3' => 'dd',
        # }}}
        'q2' => # {{{
        '
To copy from the cursor to the end of a line in normal mode..
        ',
        'q2a'  => '1',
        'q2a0' => 'yy',
        'q2a1' => 'y$',
        'q2a2' => 'yw',
        'q2a3' => 'dd',
        # }}}
        'q3' => # {{{
        '
How many modes in VIM?
        ',
        'q3a'  => '2',
        'q3a0' => '2',
        'q3a1' => '3',
        'q3a2' => '6',
        # }}}
        'q4' => # {{{
        '
What is the optimal algorithm for non-cryptographic use?
        ',
        'q4a'  => '0',
        'q4a0' => 'md4',
        'q4a1' => 'md5',
        'q4a2' => 'sha1',
        'q4a2' => 'crc32',
        'q4a2' => 'crc16',
        # }}}
        'q7' => # {{{
        '
To delete the whole line at the cursor in normal mode..
        ',
        'q7a'  => '0',
        'q7a0' => 'dd',
        'q7a1' => 'd$',
        'q7a2' => 'x',
        'q7a3' => 'D',
        # }}}
        'q8' => # {{{
        '
To delete the character under the cursor in normal mode..
        ',
        'q8a'  => '2',
        'q8a0' => 'dd',
        'q8a1' => 'd$',
        'q8a2' => 'x',
        'q8a3' => 'D',
        # }}}
        'q9' => # {{{
        '
To exit VIM..
        ',
        'q9a'  => '0',
        'q9a0' => ':q',
        'q9a1' => ';q',
        'q9a2' => 'exit',
        'q9a3' => 'q:',
        'q9a4' => 'q;',
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
  'doom' => # {{{
  [
    'type'=> 'captcha',
    'brand'=>'aliengineer',# images
    'timeout'=> 30,# seconds
    'intro'=> 1,# special first question
    'retry'=> 15,# seconds
    'rowLimit'=> 3,# maximal number of answers in a row
    'markup'=> [# questions
      ['q0'],
      ['q1'],
      ['q2'],
      ['q3'],
      ['q4'],
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
Что такое дум?
        ',
        'q0a'  => '1',
        'q0a0' => 'судьба',
        'q0a1' => 'игра',
        'q0a2' => 'говно',
        # }}}
        'q1' => # {{{
        '
Сколько выстрелов в дедушкином обрезе?
        ',
        'q1a'  => '1',
        'q1a0' => '1',
        'q1a1' => '2',
        'q1a2' => '9',
        # }}}
        'q2' => # {{{
        '
Как зовут игрока?
        ',
        'q2a'  => '0',
        'q2a0' => 'думгай',
        'q2a1' => 'думгей',
        'q2a2' => 'квакер',
        'q2a3' => 'думер',
        # }}}
        'q3' => # {{{
        '
Финальный босс в старых Doom..
        ',
        'q3a'  => '1',
        'q3a0' => 'какадемон',
        'q3a1' => 'кибердемон',
        'q3a2' => 'жирныйдемон',
        'q3a3' => 'паукомегамозг',
        'q3a4' => 'мегажелемозг',
        # }}}
        'q4' => # {{{
        '
Финальный босс в новых Doom..
        ',
        'q4a'  => '3',
        'q4a0' => 'какадемон',
        'q4a1' => 'кибердемон',
        'q4a2' => 'жирныйдемон',
        'q4a3' => 'паукомегамозг',
        'q4a4' => 'мегажелемозг',
        # }}}
      ],
    ],
  ],
  # }}}
];
?>
