<?php
return [
  'en' =>
  [
    0 => '{:no_entry_sign:} game not available',
    1 => '{:exclamation:} command failed',
    2 => '{:exclamation:} operation failed',
    3 => '{:exclamation:} not found',
    4 => '
page <b>{{page}}</b> of {{page_count}} ({{item_count}})
    ', # list status
    5 => 'this list is empty',
    6 => # FORM template {{{
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
            {{name}} —
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
        {{^valueLen}} —{{/valueLen}}
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
        {{^valueLen}}{{name}} —{{/valueLen}}
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
      {:green_circle:} input steady {{br}}
      {:yellow_circle:} confirm operation
    {{/s1}}
    {{#s2}}
      {:yellow_circle:} missing required parameter
    {{/s2}}
    {{#s3}}
      {:green_circle:} input steady {{br}}
      {{#progress}}{:purple_circle:} {{/progress}}
      {{^progress}}{:blue_circle:} {{/progress}}
      processing..
    {{/s3}}
    {{#s4}}
      {:green_circle:} input steady {{br}}
      {:red_circle:} failure
    {{/s4}}
    {{#s5}}
      {:green_circle:} input steady {{br}}
      {:green_circle:} complete
    {{/s5}}
    {{br}}
    ',# }}}
    7 => 'string ({{max}})',
    8 => 'number [{{min}}..{{max}}]',
    9 => '{:heavy_minus_sign:} do not specify',
    10 => 'close',
    11 => '{:exclamation:} task start failed',
    12 => 'complete',
    13 => 'select from the list',
  ],
  'ru' =>
  [
    0 => '{:no_entry_sign:} игра не доступна',
    1 => '{:exclamation:} неверная комманда',
    2 => '{:exclamation:} сбой операции',
    3 => '{:exclamation:} не найдено',
    4 => '
страница <b>{{page}}</b> из {{page_count}} ({{item_count}})
    ',
    5 => 'этот список пуст',
    6 => # FORM template {{{
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
            {{name}} —
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
        {{^valueLen}} —{{/valueLen}}
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
        {{^valueLen}}{{name}} —{{/valueLen}}
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
      {:green_circle:} ввод завершен {{br}}
      {:yellow_circle:} подтвердите действие
    {{/s1}}
    {{#s2}}
      {:yellow_circle:} не задан обязательный параметр
    {{/s2}}
    {{#s3}}
      {:green_circle:} ввод завершен {{br}}
      {{^info.0}}{:blue_circle:} {{/info.0}}
      {{#info.0}}{:purple_circle:} {{/info.0}}
      обработка..
    {{/s3}}
    {{#s4}}
      {:green_circle:} ввод завершен {{br}}
      {:red_circle:} ошибка{{#info.1}}: {{info.1}}{{/info.1}}
    {{/s4}}
    {{#s5}}
      {:green_circle:} ввод завершен {{br}}
      {:green_circle:} выполнено{{#info.1}}: {{info.1}}{{/info.1}}
    {{/s5}}
    {{br}}
    ',# }}}
    7 => 'строка ({{max}})',
    8 => 'число [{{min}},{{max}}]',
    9 => '{:heavy_minus_sign:} не указывать',
    10 => 'закрыть',
    11 => '{:exclamation:} не удалось запустить задачу',
    12 => 'завершить',
    13 => 'выберите из списка',
  ],
];
?>
