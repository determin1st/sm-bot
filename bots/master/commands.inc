<?php return
[
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
          #'rows'  => 4,
          'order' => 'order',
          #'desc'  => true,
        ],
        'markup' => [
          'head' => [['!create','!refresh']],
        ],
        'caps' => [
          'listItem' => '
          {{#isRunning}}{:zap:}{{/isRunning}}
          {{type}}
          {:white_small_square:}
          {{name}}
          ',
        ],
        'text' => [
          'en' => [
            '@'   => 'bots',
          ],
          'ru' => [
            '@'   => 'боты',
          ],
        ],
        'items' =>
        [
          'bot' => [
            'markup'=> [['!kek'],[' '],['!up']],
            'text'=> [
              'kek' => 'kek test',
            ],
          ],
        ],
      ],
      # }}}
      'tests' =>
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
    ],
  ],
  # }}}
];
?>
