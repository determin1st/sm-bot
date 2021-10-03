<?php return [
  'en' => [# {{{
    'BotListItem' => # {{{
    '
    {{#cnt}}
      Page <b>{{page}}</b> of {{total}} (total items: {{cnt}})
      {{|}}
      List is empty
    {{/cnt}}
    ',# }}}
    'BotFormItem' => # {{{
    '
    {{BEG}}
    {{#head}}
      {{head}}{{BR}}
    {{/head}}
    <i>parameters</i>{{BR}}
    {{#fields}}
      {{^status}}
        {{#flag}}
          {:white_small_square:}<b>{{name}}</b>: 
          {{#value}}
            <code>&lt;</code>{{value}}<code>&gt;</code>
          {{|}}
            <code>&lt;{{#hint}}{{hint}}{{|}}·{{/hint}}&gt;</code>
          {{/value}}
        {{|}}
          {:black_medium_small_square:}{{name}}
          {{#value}}: {{value}}{{/value}}
        {{/flag}}
      {{|-1}}
        {{#flag}}{:yellow_circle:} {{|}}{:white_circle:} {{/flag}}
        <b>{{name}}</b>
        {{#value}}: {{value}}{{|}} --{{/value}}
      {{|1}}
        {:green_circle:} 
        <b>{{name}}</b>
        {{#value}}: {{value}}{{|}} --{{/value}}
      {{|-2}}
        {{#flag}}{:red_circle:} {{|}}{:green_circle:} {{/flag}}
        <b>{{name}}</b>
        {{#value}}: {{value}}{{|}} --{{/value}}
      {{|}}
        {:black_small_square:} 
        <b>{{name}}</b>
        {{#value}}: {{value}}{{/value}}
      {{/status}}
      {{BR}}
    {{/fields}}
    {{BR}}
    {{#status}}
      <i>status</i>{{BR}}
      {{|-1}}
        {:yellow_circle:} 
        missing required parameter
      {{|1}}
        {:blue_circle:} 
        confirm operation
      {{|-2}}
        {:red_circle:} 
        failure{{#info}}, {{info}}{{/info}}
      {{|2}}
        {:green_circle:} 
        complete{{#info}}, {{info}}{{/info}}
      {{|-3}}
        {:blue_circle:} 
        processing
    {{/status}}
    {{END}}
    ',# }}}
    'play'      => 'play',
    'close'     => 'close',
    'op-fail'   => 'operation failed',
    'cmd-fail'  => 'command failed',
    'task-fail' => 'task failed to start',
    'no-game'   => 'game not available',
    'not-found' => 'not found',
    'add'       => 'add',
    'create'    => 'create',
    'empty'     => 'empty',
    'refresh'   => 'refresh',
    'prev'      => 'previous',
    'next'      => 'next',
    'back'      => 'back',
    'forward'   => 'next',
    'complete'  => 'complete',
    'repeat'    => 'repeat',
    'retry'     => 'retry',
    'reset'     => 'reset',
    'clear'     => 'clear',
    'select'    => 'select',
    'start'     => 'start',
    'stop'      => 'stop',
    '#string'   => 'string ({{x}})',
    '#int'      => 'integer [{{a}}..{{b}}]',
    '#list'     => 'list value [{{x}}]',
  ],
  # }}}
  'ru' => [# {{{
    'BotListItem' => # {{{
    '
    {{#cnt}}
      Страница <b>{{page}}</b> из {{total}} (всего элементов: {{cnt}})
      {{|}}
      Список пуст
    {{/cnt}}
    ',# }}}
    'play'      => 'играть',
    'close'     => 'закрыть',
    'op-fail'   => 'сбой операции',
    'cmd-fail'  => 'сбой комманды',
    'task-fail' => 'не удалось запустить задачу',
    'no-game'   => 'игра недоступна',
    'not-found' => 'не найден',
    'add'       => 'добавить',
    'create'    => 'создать',
    'empty'     => 'пусто',
    'refresh'   => 'обновить',
    'prev'      => 'предыдущая',
    'next'      => 'следующая',
    'back'      => 'назад',
    'forward'   => 'далее',
    'complete'  => 'завершить',
    'repeat'    => 'повторить',
    'retry'     => 'повтор',
    'reset'     => 'сбросить',
    'clear'     => 'сброс',
    'select'    => 'выбрать',
    'start'     => 'запустить',
    'stop'      => 'остановить',
    '#string'   => 'строка',
    '#int'      => 'целое число',
    '#list'     => 'значение из списка',
  ],
  # }}}
];
?>
