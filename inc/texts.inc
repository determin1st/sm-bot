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
    {{#desc}}
    <i>Description:</i>{{br}}
    {{desc}}{{br}}
    {{br}}
    {{/desc}}
    <i>Parameters:</i>{{br}}
    {{#fields}}
    {{#s0}}
      {{#before}}
        {{#valueLen}}
          {:green_circle:} {{name}}: {{value}}
        {{/valueLen}}
        {{^valueLen}}
          {{#required}}{:yellow_circle:} {{/required}}
          {{^required}}{:green_circle:} {{/required}}
          {{name}} -
        {{/valueLen}}
      {{/before}}
      {{#current}}
        {:white_small_square:} <b>{{name}}: </b>
        {{#valueLen}}<code>&lt;</code>{{value}}<code>&gt;</code>{{/valueLen}}
        {{^valueLen}}<code>&lt;{{hint}}&gt;</code>{{/valueLen}}
      {{/current}}
      {{#after}}
        {:black_small_square:} {{name}}
        {{#valueLen}}: {{value}}{{/valueLen}}
      {{/after}}
      {{br}}
    {{/s0}}
    {{#s1}}
      {:green_circle:} {{name}}
      {{#valueLen}}: <b>{{value}}</b>{{/valueLen}}
      {{^valueLen}} -{{/valueLen}}
      {{br}}
    {{/s1}}
    {{#s2}}
      {{#required}}
        {{#valueLen}}{:green_circle:} {{/valueLen}}
        {{^valueLen}}{:yellow_circle:} {{/valueLen}}
      {{/required}}
      {{^required}}
        {:green_circle:} 
      {{/required}}
      {{#valueLen}}{{name}}: {{value}}{{/valueLen}}
      {{^valueLen}}{{name}} -{{/valueLen}}
      {{br}}
    {{/s2}}
    {{#s3s4s5}}
      {{#valueLen}}
        {:green_circle:} {{name}}: <b>{{value}}</b>
        {{br}}
      {{/valueLen}}
    {{/s3s4s5}}
    {{/fields}}
    {{br}}
    {{^s0}}
    <i>Status:</i>{{br}}
    {{/s0}}
    {{#s1}}
    {:blue_circle:} confirm operation
    {{/s1}}
    {{#s2}}
    {:yellow_circle:} missing required parameter
    {{/s2}}
    {{#s3}}
    {{^info.0}}{:blue_circle:} {{/info.0}}
    {{#info.0}}{:purple_circle:} {{/info.0}}
    processing{{#info.0}}..{{/info.0}}
    {{/s3}}
    {{#s4}}
    {:red_circle:} <b>failure</b>{{#info.1}}: {{info.1}}{{/info.1}}
    {{/s4}}
    {{#s5}}
    {:green_circle:} <b>complete</b>{{#info.1}}: {{info.1}}{{/info.1}}
    {{/s5}}
    {{br}}
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
    'reset'     => 'reset',
    'select'    => 'select',
    'type-str'  => 'string ({{max}})',
    'type-num'  => 'number [{{min}}..{{max}}]',
    'start'     => 'start',
    'stop'      => 'stop',
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
    'BotFormItem' => # {{{
    '
    {{#desc}}
    <i>Описание:</i>{{br}}
    {{desc}}{{br}}
    {{br}}
    {{/desc}}
    <i>Параметры:</i>{{br}}
    {{#fields}}
    {{#s0}}
      {{#before}}
        {{#valueLen}}
          {:green_circle:} {{name}}: {{value}}
        {{/valueLen}}
        {{^valueLen}}
          {{#required}}{:yellow_circle:} {{/required}}
          {{^required}}{:green_circle:} {{/required}}
          {{name}} -
        {{/valueLen}}
      {{/before}}
      {{#current}}
        {:white_small_square:} <b>{{name}}: </b>
        {{#valueLen}}<code>&lt;</code>{{value}}<code>&gt;</code>{{/valueLen}}
        {{^valueLen}}<code>&lt;{{hint}}&gt;</code>{{/valueLen}}
      {{/current}}
      {{#after}}
        {:black_small_square:} {{name}}
        {{#valueLen}}: {{value}}{{/valueLen}}
      {{/after}}
      {{br}}
    {{/s0}}
    {{#s1}}
      {:green_circle:} {{name}}
      {{#valueLen}}: <b>{{value}}</b>{{/valueLen}}
      {{^valueLen}} -{{/valueLen}}
      {{br}}
    {{/s1}}
    {{#s2}}
      {{#required}}
        {{#valueLen}}{:green_circle:} {{/valueLen}}
        {{^valueLen}}{:yellow_circle:} {{/valueLen}}
      {{/required}}
      {{^required}}
        {:green_circle:} 
      {{/required}}
      {{#valueLen}}{{name}}: {{value}}{{/valueLen}}
      {{^valueLen}}{{name}} -{{/valueLen}}
      {{br}}
    {{/s2}}
    {{#s3s4s5}}
      {{#valueLen}}
        {:green_circle:} {{name}}: <b>{{value}}</b>
        {{br}}
      {{/valueLen}}
    {{/s3s4s5}}
    {{/fields}}
    {{br}}
    {{^s0}}
    <i>Статус:</i>{{br}}
    {{/s0}}
    {{#s1}}
    {:blue_circle:} подтвердите действие
    {{/s1}}
    {{#s2}}
    {:yellow_circle:} не задан обязательный параметр
    {{/s2}}
    {{#s3}}
    {{^info.0}}{:blue_circle:} {{/info.0}}
    {{#info.0}}{:purple_circle:} {{/info.0}}
    обработка{{#info.0}}..{{/info.0}}
    {{/s3}}
    {{#s4}}
    {:red_circle:} <b>ошибка</b>{{#info.1}}: {{info.1}}{{/info.1}}
    {{/s4}}
    {{#s5}}
    {:green_circle:} <b>выполнено</b>{{#info.1}}: {{info.1}}{{/info.1}}
    {{/s5}}
    {{br}}
    ',# }}}
    'play'      => 'играть',
    'close'     => 'закрыть',
    'op-fail'   => 'сбой операции',
    'cmd-fail'  => 'сбой комманды',
    'task-fail' => 'не удалось запустить задачу',
    'no-game'   => '{:no_entry_sign:} игра недоступна',
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
    'repeat'    => 'повтор',
    'reset'     => 'сброс',
    'select'    => 'выбрать',
    'type-str'  => 'строка ({{max}})',
    'type-num'  => 'число [{{min}},{{max}}]',
    'start'     => 'запустить',
    'stop'      => 'остановить',
  ],
  # }}}
];
?>
