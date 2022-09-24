<?php return [
'/start' => # {{{
[
  'markup' => [
    ['bots'],
    [' '],
    ['!up'],
  ],
  'text' => [
    #'bots' => 'bot list',
  ],
],
# }}}
'/start/bots' => # {{{
[
  'type'       => 'list',
  'list.type'  => 'open',
  'list.open'  => 'bot',
  'list.order' => 'order',
  'data.scope' => 'bot',
  'data.fetch' => 5,
  'markup' => [
    'head' => [['!create','!refresh']],
  ],
  'text' => [
    'en' => ['@' => 'bot list'],
    'ru' => ['@' => 'список ботов'],
  ],
  'caps' => [
    'list.open' => '
    {{source}}
    {{#isRunning}}
      {:small_orange_diamond:}
    {{|}}
      {:small_blue_diamond:}
    {{/isRunning}}
    {{name}}
    ',
  ],
],
# }}}
'/start/bots/bot' => # {{{
[
  'markup' => [
    'master' => [
      ['!refresh'],
      ['!up'],
    ],
    'slave' => [
      ['!start','!stop','!refresh'],
      ['!dropCache'],
      ['!up'],
    ],
  ],
  'text' => [
    'en' => [
      '#' => '
      {:BEG:}
      {:black_medium_small_square:} 
      <b>source</b>: {{source}}
      {:BR:}
      {:black_medium_small_square:} 
      <b>id</b>: <a href="https://t.me/{{name}}">{{id}}</a>
      {:BR:}
      {:black_medium_small_square:} 
      <b>api</b>:
      {{#polling}}
        {:NBSP:}polling
      {{|}}
        {:NBSP:}webhook
      {{/polling}}
      {:BR:}
      {:black_medium_small_square:} <b>state</b>
      {{#isRunning}}
        {:NBSP:}{:zap:}{:NBSP:}
      {{|}}
        {:NBSP:}──
      {{/isRunning}}
      {:BR:}
      {{#info}}
        {:BR:}
        {{#info.ok}}
          {:red_circle:}
        {{|}}
          {:green_circle:}
        {{/info.ok}}
        {:NBSP:}{{info.text}}
      {{/info}}
      {:END:}
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
'/start/bots/create' => # {{{
[
  'type'     => 'form',
  'datafile' => 1,
  'markup'  => [],
  'fields'  => [# bits: 1:required+2:persistent+4:nullable+8:hidden
    'class' => ['select', 1, [
      1
    ]],
    'token' => ['string', 1+8, [
      46, \SM\BotConfig::EXP_TOKEN
    ]],
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
];
