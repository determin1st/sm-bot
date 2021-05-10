<?php
namespace SM;
class task_testform {
  # {{{
  public static function handle($o, $lang)
  {
    # suspend for a while..
    sleep(rand(5, 10));
    # ...
    # ...
    # success
    self::$message = self::$result[$lang][0];
    return rand(0, 1);
  }
  public static
    $message = '',
    $result  = [
      'en' =>
      [
        0 => 'chosen number doesn\'t match',
        1 => 'you guessed the number',
      ],
      'ru' =>
      [
        0 => 'выбранное число не подходит',
        1 => 'вы угадали число',
      ],
    ];
  # }}}
}
?>
