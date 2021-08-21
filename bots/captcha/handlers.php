<?php
namespace SM;
function BotItem_todo( # {{{
  object $user,
  object $item,
  string $func,
  ?array $args,
):?array
{
  return null;
}
# }}}
class BotItemCaptcha extends BotItem # {{{
{
  # {{{
  public static
    $STEP   = 2,# seconds, one decrement
    $BLINK  = 0,# seconds, indicator blinks
    $PAUSE  = 2,# seconds, transition
    $STAGES = 3,# total indicator stages
    $TEMPLATE = # {{{
    '
    <a href="tg://user?id={[user.id]}">{[user.name]}</a> 
    {{#A1}}
      {{#t1}}
        {{#blink}}{:red_circle:}{{/blink}}
        {{^blink}}{:orange_circle:}{{/blink}}
      {{/t1}}
      {{#t2}}
        {{#blink}}{:orange_circle:}{{/blink}}
        {{^blink}}{:purple_circle:}{{/blink}}
      {{/t2}}
      {{#t3}}
        {{#blink}}{:purple_circle:}{{/blink}}
        {{^blink}}{:blue_circle:}{{/blink}}
      {{/t3}}
      <b> {{time}}</b>
      {{br}}{{br}}
      {{question}}
    {{/A1}}
    {{#A2}}
      {:red_circle:} <a href="https://youtu.be/mQ_AdzWE5Ec">timed out</a>
    {{/A2}}
    {{#A3}}
      {:green_circle:} correct
      {{br}}{{br}}
      {{question}}
    {{/A3}}
    {{#A4}}
      {:red_circle:} incorrect
      {{br}}{{br}}
      {{question}}
    {{/A4}}
    {{#A5}}
      {:green_circle:} qualified
      {{br}}{{br}}
      {:tada:}{:tada:}{:tada:}
    {{/A5}}
    {{#A6}}
      {:red_circle:} failed
    {{/A6}}
    {{br}}{{end}}
    ';
    # }}}
  ###
  static function getTotalSize($time)
  {
    $step   = self::$STEP;
    $blink  = self::$BLINK;
    $stages = self::$STAGES;
    $size   = intval(ceil($time / $stages));
    $total  = intval($stages * ceil($size / $step));
    return [$total,$size];
  }
  static function getQA($q, $A)
  {
    # extract question's answers
    $a = [];
    $b = 0;
    while (array_key_exists(($c = $q.'a'.$b), $A)) {
      $a[$c] = $A[$c]; ++$b;
    }
    return $a;
  }
  static function getMixedQA($q, $A)
  {
    # get answers and mixup
    $a = array_keys(self::getQA($q, $A));
    shuffle($a);
    return $a;
  }
  # }}}
  static function render($bot, &$item, $func, $args) # {{{
  {
    # prepare {{{
    $conf  = &$item['config'];
    $text  = $item['text'][$bot->user->lang];
    $retry = $item['retry'];
    $A = 0;
    if ($init = array_key_exists('A', $conf))
    {
      $A = $conf['A'];# current captcha stage
      $B = $conf['B'];# total ticks left
      $C = $conf['C'];# question group index
      $D = $conf['D'];# question index
      $E = $conf['E'];# question answer keys
      $F = $conf['F'];# current time stage
      $G = $conf['G'];# current blink state
      $H = $conf['H'];# current retry timeout
      $I = $conf['I'];# current answer
    }
    # }}}
    # update {{{
    switch ($A) {
    case 2:
    case 6:
      # TIMED OUT or FAILED {{{
      # check retry state
      if ($H)
      {
        if ($func === 'retry')
        {
          # restart requirested
          # message should be updated
          $bot->user->changed = true;
          # check time passed
          $retry = $retry - (time() - $H);
          if ($retry <= 0)
          {
            # unlock restart
            $H = $retry = 0;
          }
        }
        else {
          $retry = 0;# dont show
        }
      }
      if ($H) {break;}
      # set to recreate item's message
      $item['isNew'] = true;
      # }}}
      ### fallthrough..
    case 0:
      # STARTUP {{{
      ### switch to the next stage
      $A = 1;
      $B = self::getTotalSize($item['timeout'])[0];
      ### select first question
      $C = 0;
      $D = count($item['markup'][$C]);
      $D = ($D === 1) ? 0 : rand(0, $D - 1);
      ### mixup answers
      $E = self::getMixedQA($item['markup'][$C][$D], $text);
      $E = implode(',', $E);
      ### initial time stage
      $F = self::$STAGES;
      $G = 0;
      $H = 0;
      $I = '';
      $bot->taskAttach($item, $item['timeout']);
      # }}}
      break;
    case 1:
    case 3:
      # OPERATE
      switch ($func) {
      case 'done':
        # TIMED OUT {{{
        $A = 2;
        $B = 0;
        $H = $retry ? time() : 0;# record when
        $retry = 0;# dont show
        break;
        # }}}
      case 'progress':
        # {{{
        # update indicator [total,stage,blink]
        $B = intval($args[0]);
        $F = intval($args[1]);
        $G = intval($args[2]);
        # check correct stage approval
        if ($A === 3 && $G === 2)
        {
          # advance question group index
          if (($C = $C + 1) < count($item['markup']))
          {
            ### recharge
            $A = 1;
            $D = count($item['markup'][$C]);
            $D = ($D === 1) ? 0 : rand(0, $D - 1);
            ### mixup answers
            $E = self::getMixedQA($item['markup'][$C][$D], $text);
            $E = implode(',', $E);
          }
          else
          {
            ### complete captcha
            $A = 5;
          }
        }
        break;
        # }}}
      default:
        # ANSWER {{{
        # check already
        if ($A === 3) {break;}
        # check correct
        $a = $item['markup'][$C][$D].'a';
        $b = false;# wrong
        foreach (explode(',', $text[$a]) as $c)
        {
          if ($func === $a.$c) {
            $b = true; break;
          }
        }
        # set anser
        $A = $b ? 3 : 4;
        $H = $retry ? time() : 0;# charge retry
        $I = $func;
        $bot->logDebug('answer '.$I.($b?'+':'-'));
        break;
        # }}}
      }
      break;
    case 4:
      # INCORRECT {{{
      if ($func === 'done')
      {
        $A = 6;
        $B = 0;
        $retry = 0;# dont show hint
      }
      # }}}
      break;
    case 5:
      # COMPLETE {{{
      if ($func === 'reset')
      {
        # reset and re-render
        unset($conf['A']);
        $item['isNew'] = true;
        return self::render($bot, $item, '', null);
      }
      # }}}
      break;
    }
    if (!$init ||
        $A !== $conf['A'] ||
        $B !== $conf['B'] ||
        $C !== $conf['C'] ||
        $D !== $conf['D'] ||
        $E !== $conf['E'] ||
        $F !== $conf['F'] ||
        $G !== $conf['G'] ||
        $H !== $conf['H'] ||
        $I !== $conf['I'])
    {
      $conf['A'] = $A;
      $conf['B'] = $B;
      $conf['C'] = $C;
      $conf['D'] = $D;
      $conf['E'] = $E;
      $conf['F'] = $F;
      $conf['G'] = $G;
      $conf['H'] = $H;
      $conf['I'] = $I;
      $bot->user->changed = true;
    }
    # }}}
    # render {{{
    switch ($A) {
    case 1:
    case 3:
    case 4:
      # question is being asked.. {{{
      # determine text
      $a = $item['markup'][$C][$D];
      $a = trim($text[$a]);
      # determine markup
      $b = explode(',', $E);
      $c = count($b);
      $d = [];
      if ($A === 1)
      {
        # all answer variants shown
        $i = 0;
        while ($i < $c)
        {
          $e = [];
          $j = -1;
          while (++$j < $item['rowLimit'] && $i < $c)
          {
            $e[] = '_'.$b[$i];
            $i++;
          }
          $d[] = $e;
        }
      }
      else
      {
        # show selected variant and blank others
        $i = 0;
        while ($i < $c)
        {
          $e = [];
          $j = -1;
          while (++$j < $item['rowLimit'] && $i < $c)
          {
            if ($b[$i] === $I) {
              $e[] = ['text'=>$text[$b[$i]],'callback_data'=>'!'];
            }
            else {
              $e[] = ' ';
            }
            $i++;
          }
          $d[] = $e;
        }
      }
      $b = $d;
      # }}}
      break;
    case 2:
    case 6:
      # failed states {{{
      # replace question with last answer
      $a = $I;
      # custom retry button
      # displayed with or without a hint
      $b = $bot->tp->render($text['retry'], ['x'=>$retry]);
      $c = '/'.$item['id'].'!retry';
      $b = [['text'=>$b,'callback_data'=>$c]];
      $b = [$b];
      # }}}
      break;
    case 5:
      $a = '';
      $b = $item['markupComplete'];
      break;
    }
    # get the template
    if (!($c = $item['content']))
    {
      # default, add emojis
      $c = $bot->tp->render(self::$TEMPLATE, Bot::$EMOJI, '{: :}');
    }
    # set
    $item['textContent'] = $bot->render_content($c, [
      'question' => $a,
      'A1'    => ($A === 1),
      'A2'    => ($A === 2),
      'A3'    => ($A === 3),
      'A4'    => ($A === 4),
      'A5'    => ($A === 5),
      'A6'    => ($A === 6),
      'time'  => $B,
      't1'    => ($F == 1),
      't2'    => ($F == 2),
      't3'    => ($F >= 3),
      'blink' => $G,
      'retry' => $retry,
    ]);
    $item['markup'] = $b;
    $item['title'] = '';
    $a = array_key_exists('brand', $item)
      ? $item['brand']
      : $item['name'];
    $item['titleId'] = ($A === 1 && $C === 0 && $item['intro'])
      ? $a.'_0'
      : $a.'_'.$A;
    # }}}
    return true;
  }
  # }}}
  static function task($bot, &$item, $data) # {{{
  {
    # prepare
    $step  = self::$STEP;
    $blink = self::$BLINK;
    $stage = 1 + self::$STAGES;
    $total = self::getTotalSize($data);
    $size  = $total[1];
    $total = $total[0];
    $A = 1;
    # iterate
    while (--$stage)
    {
      # make stage steps
      $a = $step + $size;
      while (($a -= $step) > 0)
      {
        # delay
        $bot::delay($step - $blink);
        # decrement
        if (--$total === 0) {
          break 2;
        }
        # update
        $bot->taskRender($item, [$total,$stage,1], function(&$item) use ($bot) {
          # make sure update is valid
          return ($item['config']['A'] === 1);
        });
        # check stage changed
        if (($A = $item['config']['A']) !== 1)
        {
          # delay
          $bot::delay(self::$PAUSE);
          # complete incorrect
          if ($A !== 3) {
            break 2;
          }
          # approve correct (with special blink)
          if (!$bot->taskRender($item, [$total,$stage,2])) {
            break 2;
          }
          # complete correct
          if ($item['config']['A'] === 5) {
            break 2;
          }
          $bot::delay($blink);
        }
        /***
        if ($blink)
        {
          $bot::delay($blink);
          $bot->taskRender($item, [$total,$stage,0]);
        }
        /***/
      }
    }
    # complete
    return [1];
  }
  # }}}
}
# }}}
?>
