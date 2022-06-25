<?php return [
  'en' => [# {{{
    '#List' => # {{{
    '
    {{#cnt}}
      Page <b>{{page}}</b> of {{total}} (total items: {{cnt}})
    {{|}}
      List is empty
    {{/cnt}}
    ',# }}}
    '#Form' => # {{{
    '
    {:BEG:}
    {{?description}}
      {{description}}{{BR}}{{BR}}
    {{/description}}
    {{?complete}}
      {{#complete}}
        {:green_circle:}{:NBSP:}<b>{{name}}</b>
        {{#value}}: <code>{{value}}</code>{{|}} <code>──</code>{{/value}}
        {{BR}}
      {{/complete}}
      {{?current}}
        ──{{BR}}
      {{/current}}
    {{/complete}}
    {{#current}}
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
        {{#flag}}{:red_circle:}{{|}}{:yellow_circle:}{{/flag}}
        {:NBSP:}<b>{{name}}</b>
        {{#value}}: <code>{{value}}</code>{{|}} <code>──</code>{{/value}}
      {{|-1}}
        {{#flag}}{:orange_circle:}{{|}}{:white_circle:}{{/flag}}
        {:NBSP:}<b>{{name}}</b>
        {{#value}}: <code>{{value}}</code>{{|}} <code>──</code>{{/value}}
      {{|1}}
        {:white_circle:}
        {:NBSP:}<b>{{name}}</b>
        {{#value}}: <code>{{value}}</code>{{|}} <code>──</code>{{/value}}
      {{/status}}
      {{BR}}
    {{/current}}
    {{?info}}
      {{BR}}
      {{#status}}
        {:blue_circle:}
      {{|-2}}
        {:red_circle:}
      {{|-1}}
        {:yellow_circle:}
      {{|}}
        {:information_source:}
      {{/status}}
      {:NBSP:}{{info}}
    {{/info}}
    {:END:}
    ',# }}}
    'play'       => 'play',
    'close'      => 'close',
    'op-fail'    => 'operation failed',
    'cmd-fail'   => 'command failed',
    'task-fail'  => 'task failed to start',
    'task-await' => 'awaiting process completion',
    'no-game'    => 'game not available',
    'not-found'  => 'not found',
    'oversized'  => 'size limit exceeded',
    'bad-data'   => 'incorrect data',
    'max-value'  => '{{x}} is the maximum value',
    'min-value'  => '{{x}} is the minimum value',
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
    'select'     => 'select',
    'clear'      => 'clear',
    'submit'     => 'submit',
    'complete'   => 'complete',
    'reset'      => 'reset',
    'retry'      => 'try again',
    'repeat'     => 'repeat',
    'change'     => 'change',
    'start'      => 'start',
    'stop'       => 'stop',
    '>string'    => '{:LINEPAD:}input a string',
    '>int'       => '{:LINEPAD:}input an integer',
    '>select'    => '{:LINEPAD:}select a value from the list',
  ],
  # }}}
  'ru' => [# {{{
    '#List' => # {{{
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
    'task-await' => 'ожидаю завершения процесса',
    'no-game'    => 'игра недоступна',
    'not-found'  => 'не найден',
    'oversized'  => 'превышен допустимый размер',
    'bad-data'   => 'неверные данные',
    'max-value'  => '{{x}} это максимальное значение',
    'min-value'  => '{{x}} это минимальное значение',
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
    'select'     => 'выбрать',
    'clear'      => 'очистить',
    'submit'     => 'утвердить',
    'complete'   => 'завершить',
    'reset'      => 'сброс',
    'retry'      => 'повторить попытку',
    'repeat'     => 'повторить',
    'change'     => 'изменить',
    'start'      => 'запустить',
    'stop'       => 'остановить',
    '>string'    => '{:LINEPAD:}введите строку',
    '>int'       => '{:LINEPAD:}введите целое число',
    '>select'    => '{:LINEPAD:}выберите значение из списка',
  ],
  # }}}
];
?>
