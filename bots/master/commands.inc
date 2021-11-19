<?php return [
  'start' => # {{{
  [
    'markup' => [
      ['bots'],
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
          'create' => [# {{{
            'type'     => 'form',
            'datafile' => 1,
            'config'   => [
            ],
            'markup'  => [],
            'fields'  => [# bits: 1:required+2:persistent+4:nullable+8:hidden
              'class' => [1, 'select', 1],
              'token' => [1+8, 'string', 46, \SM\Bot::TOKEN_EXP],
            ],
            'text'  => [
              'en'  => [
                '@' => 'new bot',
                '#desc' => '
                To obtain <b>username</b> and <b>token</b> for a new bot,
                navigate to @BotFather and follow instructions.
                ',
                '>token' => 'input a token from @BotFather or forward message about bot creation here',
                'in-use' => '
                identifier is already in use by @{{name}}
                ',
              ],
              'en'  => [
                '@' => 'новый бот',
                '#desc' => '
                ',
                '.class' => 'класс',
                '.token' => 'токен',
                '>token' => 'введите токен полученный в @BotFather или перешлите сюда сообщение о создании бота',
                '.id'    => 'идентификатор',
                '.name'  => 'имя',
                'in-use' => '
                идентификатор уже используется ботом @{{name}}
                ',
              ],
            ],
          ],
          # }}}
          'bot' => [# {{{
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
                {:black_medium_small_square:} <b>id</b>: <a href="https://t.me/{{name}}">{{botId}}</a>{{BR}}
                {:black_medium_small_square:} <b>webhook</b>
                {{#webhook}}
                  {{#isWebhook}}{:NBSP:}{:zap:}{{|}}:{{/isWebhook}}
                  {:NBSP:}<code>{{webhook}}</code>
                {{|}}
                  {:NBSP:}──
                {{/webhook}}
                {{BR}}
                {:black_medium_small_square:} <b>state</b>
                {{#isRunning}}
                  {:NBSP:}{:zap:}{:NBSP:}<code>{{isRunning}}</code>
                {{|}}
                  {:NBSP:}──
                {{/isRunning}}
                {{BR}}
                {{#message}}
                  {{BR}}
                  {{#isError}}{:red_circle:}{{|}}{:green_circle:}{{/isError}} 
                  {{message}}
                {{/message}}
                {{END}}
                ',
                'dropCache' => 'reset cache',
                'cacheDropped' => 'cache dropped (%s)',
              ],
              'ru' => [
                'dropCache' => 'сбросить кэш',
                'cacheDropped' => 'кэш сброшен (%s)',
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
];
?>
