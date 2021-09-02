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
        'type' => 'list',
        'opts' => [
          'rows'  => 4,
          'cols'  => 1,
          'order' => 'order',
          'desc'  => true,
        ],
        'markup' => [
          '.' => [['!prev','!add','!next'],['!up']],
        ],
        'text' => [
          'add' => 'add',
          'name' =>
          '{{bot}}{{#up}}{:zap:}{{|}}{:white_small_square:}{{/up}}{{name}}',
        ],
        'items' => [
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
        'markup' => [['!up']],
        'text'   => ['@'=>'simple tests'],
      ],
    ],
  ],
  # }}}
];
?>
