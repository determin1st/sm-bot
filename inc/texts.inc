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
    {:BEG:}
    {{#desc}}
      {{desc}}
      {{BR}}──{:BR:}
    {{/desc}}
    {{#fields}}
      {{^status}}
        {{#flag}}
          {:white_small_square:}<b>{{name}}</b>{:ZWSP:}
          <b>⟹</b>{:NBSP:}
          <code>{{#value}}{{value}}{{|}}·{{/value}}</code>
        {{|}}
          {:black_medium_small_square:}{{name}}
          {{#value}}: <code>{{value}}</code>{{/value}}
        {{/flag}}
      {{|-2}}
        {{#flag}}{:red_circle:} {{|}}{:green_circle:} {{/flag}}
        <b>{{name}}</b>
        {{#value}}: {{value}}{{|}} --{{/value}}
      {{|-1}}
        {{#flag}}{:orange_circle:} {{|}}{:green_circle:} {{/flag}}
        <b>{{name}}</b>
        {{#value}}: {{value}}{{/value}}
      {{|1}}
        {:green_circle:} <b>{{name}}</b>
        {{#value}}
          : <code>{{value}}</code>
        {{|}}
          <code> ─</code>
        {{/value}}
      {{|}}
        {:black_small_square:} 
        <b>{{name}}</b>AAA
        {{#value}}: {{value}}{{/value}}
      {{/status}}
      {{BR}}
    {{/fields}}
    {{#info}}
      ──{:BR:}
      {:information_source:} {{info}}
    {{/info}}
    {:END:}
    ',# }}}
    'play'       => 'play',
    'close'      => 'close',
    'op-fail'    => 'operation failed',
    'cmd-fail'   => 'command failed',
    'task-fail'  => 'task failed to start',
    'no-game'    => 'game not available',
    'not-found'  => 'not found',
    'bad-data'   => 'incorrect data',
    'msg-parsed' => 'message parsed',
    'req-field'  => 'this field is required',
    'req-miss'   => 'missing required parameter',
    'add'        => 'add',
    'create'     => 'create',
    'empty'      => 'empty',
    'refresh'    => 'refresh',
    'prev'       => 'previous',
    'next'       => 'next',
    'back'       => 'back',
    'forward'    => 'next',
    'retry'      => 'retry',
    'reset'      => 'reset',
    'select'     => 'select',
    'clear'      => 'clear',
    'submit'     => 'submit',
    'complete'   => 'complete',
    'repeat'     => 'repeat',
    'start'      => 'start',
    'stop'       => 'stop',
    '#string'    => 'string ({{x}})',
    '#int'       => 'integer [{{a}}..{{b}}]',
    '#list'      => 'list value [{{x}}]',
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
    'play'       => 'играть',
    'close'      => 'закрыть',
    'op-fail'    => 'сбой операции',
    'cmd-fail'   => 'сбой комманды',
    'task-fail'  => 'не удалось запустить задачу',
    'no-game'    => 'игра недоступна',
    'not-found'  => 'не найден',
    'bad-data'   => 'неверные данные',
    'msg-parsed' => 'сообщение обработано',
    'req-field'  => 'это поле является обязательным',
    'req-miss'   => 'пропущен обязательный параметр',
    'add'        => 'добавить',
    'create'     => 'создать',
    'empty'      => 'пусто',
    'refresh'    => 'обновить',
    'prev'       => 'предыдущая',
    'next'       => 'следующая',
    'back'       => 'назад',
    'forward'    => 'далее',
    'retry'      => 'повтор',
    'reset'      => 'сброс',
    'select'     => 'выбрать',
    'clear'      => 'очистить',
    'submit'     => 'утвердить',
    'complete'   => 'завершить',
    'repeat'     => 'повторить',
    'start'      => 'запустить',
    'stop'       => 'остановить',
    '#string'    => 'строка',
    '#int'       => 'целое число',
    '#list'      => 'значение из списка',
  ],
  # }}}
];
?>
