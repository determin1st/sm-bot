<?php return [
  'start' => # {{{
  [
    'markup' => [
      ['bots'],
      ['tests'],
      [' '],
      ['!up'],
    ],
    'text' => [
      'bots' => 'bots',
    ],
    'items' => [
      'bots' => # {{{
      [
        'type' => 'List',
        'config' => [
          'order' => 'order',
          'item'  => 'bot',
        ],
        'markup' => [
          'head' => [['!create','!refresh']],
        ],
        'caps' => [
          'listItem' => '
          {{#isMaster}}
            {{name}}
          {{|}}
            {{#isRunning}}{:zap:}{{/isRunning}}
            {{type}}
            {:white_small_square:}
            {{name}}
          {{/isMaster}}
          ',
        ],
        'text' => [
          'en' => ['@' => 'bots'],
          'ru' => ['@' => 'боты'],
        ],
        'items' =>
        [
          'bot' => [
            'markup'=> [
              ['!start','!stop','!refresh'],
              ['!dropCache'],
              ['!up'],
            ],
            'text'=> [
              'en' => [
                '#' => '
                {{BEG}}
                {:black_medium_small_square:} <b>class</b>: {{type}}{{BR}}
                {:black_medium_small_square:} <b>id</b>: <code>{{botId}}</code>{{BR}}
                {:black_medium_small_square:} <b>state</b>: 
                {{#isRunning}}{:zap:}started{{|}}──{{/isRunning}}{{BR}}
                {{#message}}
                  {{BR}}
                  {{#isError}}{:red_circle:}{{|}}{:green_circle:}{{/isError}}
                  {{message}}
                {{/message}}
                {{END}}
                ',
                'dropCache' => 'reset cache',
              ],
              'ru' => [
                'dropCache' => 'сбросить кэш',
              ],
            ],
          ],
        ],
      ],
      # }}}
      'tests' => # {{{
      [
        'markup' => [['t1','t2','t3'],['!up']],
        'text' => ['@'=>'simple tests'],
        'items' => [
          't1' =>
          [
            'title' => ['color' => [40,40,80]],
            'markup' => [['!up']],
          ],
          't2' =>
          [
            'image' => ['file' => 'logo.jpg'],
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
    ],
  ],
  # }}}
];
?>
