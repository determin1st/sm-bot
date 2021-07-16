<?php
#declare(strict_types=1);
# TODO: refactor user/replier
# TODO: refactor list/form
# TODO: list of lists
# TODO: language switch form
# TODO: input bob
# TODO: advanced image renderer (Fortune algorithm?)
namespace SM;
abstract class StaticInit # {{{
{
  protected function __construct(array $o) {
    foreach ($o as $k => $v) {$this->$k = $v;}
  }
}
# }}}
class Bot extends StaticInit {
  # data {{{
  # syntax: /<item=id[:id[:id[..]]]>!<func> <args=[arg[,arg[..]]]>
  const COMMAND_EXP = '|^\/((\w+)([:/-](\w+)){0,8})(!(\w{1,})){0,1}( (.{1,})){0,1}$|';
  const MESSAGE_LIFETIME = 48*60*60;
  static
    $INIT    = true,
    $IS_WIN  = true,
    $ERROR   = '',
    $BUTTONS = [
      # {{{
      'up'       => '{:eject_symbol:} {{text}}',
      'close'    => '{:stop_button:} {{text}}',
      'prev'     => '{:rewind:} {{x}}',
      'next'     => '{{x}} {:fast_forward:}',
      'first'    => '{:previous_track:} {{x}}',
      'last'     => '{{x}} {:next_track:}',
      'play'     => '{:arrow_forward:}',
      'fav_on'   => '{:star:}',
      'fav_off'  => '{:sparkles:}{:star:}{:sparkles:}',
      'refresh'  => '{{x}} {:arrows_counterclockwise:}',
      'reset'    => '{:arrows_counterclockwise:} {{x}}',
      'retry'    => '{:arrow_right_hook:} {{x}}',
      'ok'       => 'OK',
      'select0'  => '{{text}}',
      'select1'  => '{:green_circle:} {{text}}',
      'go'       => '{:arrow_forward:} {{text}}',
      'add'      => '{{x}} {:new:}',
      # }}}
    ],
    $MESSAGES = [
      'en' => [# {{{
        0 => '{:no_entry_sign:} game not available',
        1 => '{:exclamation:} command failed',
        2 => '{:exclamation:} operation failed',
        3 => '{:exclamation:} not found',
        4 => 'add',
        5 => 'empty',
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
        7 => 'string ({{max}})',
        8 => 'number [{{min}}..{{max}}]',
        9 => '',
        10 => 'close',
        11 => '{:exclamation:} task failed to start',
        12 => 'complete',
        13 => 'select option',
        14 => 'refresh',
        15 => 'reset',
        16 => 'previous',
        17 => 'next',
        18 => 'repeat',
        19 => '',
      ],
      # }}}
      'ru' => [# {{{
        0 => '{:no_entry_sign:} игра не доступна',
        1 => '{:exclamation:} неверная комманда',
        2 => '{:exclamation:} сбой операции',
        3 => '{:exclamation:} не найдено',
        4 => 'добавить',
        5 => 'пусто',
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
        7 => 'строка ({{max}})',
        8 => 'число [{{min}},{{max}}]',
        9 => '',
        10 => 'закрыть',
        11 => '{:exclamation:} не удалось запустить задачу',
        12 => 'завершить',
        13 => 'выберите опцию',
        14 => 'обновить',
        15 => 'сброс',
        16 => 'предыдущий',
        17 => 'далее',
        18 => 'повторить',
        19 => '',
      ],
      # }}}
    ],
    $EMOJI = [
      # {{{
      'arrow_up'         => "\xE2\xAC\x86",
      'arrow_down'       => "\xE2\xAC\x87",
      'arrow_up_small'   => "\xF0\x9F\x94\xBC",
      'arrow_down_small' => "\xF0\x9F\x94\xBD",
      'arrow_left'       => "\xE2\xAC\x85",
      'arrow_right'      => "\xE2\x9E\xA1",
      'arrow_forward'    => "\xE2\x96\xB6",
      'arrow_backward'   => "\xE2\x97\x80",
      'fast_forward'     => "\xE2\x8F\xA9",
      'rewind'           => "\xE2\x8F\xAA",
      'eject_symbol'     => "\xE2\x8F\x8F",
      'scroll'           => "\xF0\x9F\x93\x9C",
      'game_die'         => "\xF0\x9F\x8E\xB2",
      'video_game'       => "\xF0\x9F\x8E\xAE",
      'exclamation'      => "\xE2\x9D\x97",
      'question'         => "\xE2\x9D\x93",
      'slot_machine'     => "\xF0\x9F\x8E\xB0",
      'no_entry_sign'    => "\xF0\x9F\x9A\xAB",
      'star'             => "\xE2\xAD\x90",
      'star2'            => "\xF0\x9F\x8C\x9F",
      'stop_button'      => "\xE2\x8F\xB9",
      'previous_track'   => "\xE2\x8F\xAE",
      'next_track'       => "\xE2\x8F\xAD",
      'heavy_multiplication_x' => "\xE2\x9C\x96",
      'heavy_minus_sign' => "\xE2\x9E\x96",
      'x'                => "\xE2\x9D\x8C",
      'record_button'    => "\xE2\x8F\xBA",
      'heavy_check_mark' => "\xE2\x9C\x94",
      'white_small_square' => "\xE2\x96\xAB",
      'black_small_square' => "\xE2\x96\xAA",
      'black_medium_small_square' => "\xE2\x97\xBC",
      'ballot_box_with_check' => "\xE2\x98\x91",
      'moneybag'         => "\xF0\x9F\x92\xB0",
      'sparkles'         => "\xE2\x9C\xA8",
      'watermelon'       => "\xF0\x9F\x8D\x89",
      'grapes'           => "\xF0\x9F\x8D\x87",
      'cherries'         => "\xF0\x9F\x8D\x92",
      'flame'            => "\xF0\x9F\x94\xA5",
      'boom'             => "\xF0\x9F\x92\xA5",
      'anger'            => "\xF0\x9F\x92\xA2",
      'red_circle'       => "\xF0\x9F\x94\xB4",
      'green_circle'     => "\xF0\x9F\x9F\xA2",
      'blue_circle'      => "\xF0\x9F\x94\xB5",
      'arrows_counterclockwise' => "\xF0\x9F\x94\x84",
      'yellow_circle'    => "\xF0\x9F\x9F\xA1",
      'orange_circle'    => "\xF0\x9F\x9F\xA0",
      'white_circle'     => "\xE2\x9A\xAA",
      'black_circle'     => "\xE2\x9A\xAB",
      'purple_circle'    => "\xF0\x9F\x9F\xA3",
      'arrow_right_hook' => "\xE2\x86\xAA",
      'tada'             => "\xF0\x9F\x8E\x89",
      'double_vertical_bar' => "\xE2\x8F\xB8",
      'new'              => "\xF0\x9F\x86\x95",
      'ok'               => "\xF0\x9F\x86\x97",
      'up'               => "\xF0\x9F\x86\x99",
      'vs'               => "\xF0\x9F\x86\x9A",
      'zap'              => "\xE2\x9A\xA1",
      # }}}
    ];
  # }}}
  # logger {{{
  function log(string $msg, int $level = 0): void
  {
    # prepare
    static $PREFIX='> ', $COLOR=['green','red','yellow'];
    $user = $this->user ? $this->user->name : '';
    # console output
    if ($this->stdout)
    {
      $a = $COLOR[$level];
      $b = self::str_fg_color($PREFIX, $a, 1);
      $c = $user ? $b.self::str_bg_color($user, $a) : '';
      fwrite($this->stdout, $c.$b.$msg."\n");
    }
    # file output
    if ($level === 0 && $this->opts->saveAccessLog)
    {
      $a = date(DATE_ATOM).': ';
      $b = $user ? $user.$PREFIX : '';
      file_put_contents($this->accesslog, $a.$b.$msg."\n", FILE_APPEND);
    }
    elseif ($level === 1 && $this->opts->saveErrorLog)
    {
      $a = date(DATE_ATOM).': ';
      $b = $user ? $user.$PREFIX : '';
      file_put_contents($this->errorlog, $a.$b.$msg."\n", FILE_APPEND);
    }
    /***
    # sfx {{{
    if (self::$IS_WIN && $this->opts['sfx'] && !self::$IS_TASK)
    {
      # play sound through batch-file
      $m = $this->incdir.'sfx';
      $m = 'START "" /D "'.$m.'" /B play.bat info.wav';
      if ($m = popen($m, 'r'))
      {
        fgetc($m);
        pclose($m);
      }
    }
    # }}}
    /***/
  }
  function logError(string $msg): void
  {
    #$this->tasks = [];# abort
    $this->log($msg, 1);
    $this->errors++;
  }
  function logException(object $e): void
  {
    #$this->tasks = [];# abort
    $a = $e->getMessage();
    $b = $e->getTraceAsString();
    $this->log("$a\n$b\n", 1);
    $this->errors++;
  }
  function logWarn(string $msg): void {
    $this->log($msg, 2);
  }
  function logDebug($o): void {
    $this->log(var_export($o, true), 0);
  }
  function logMustache(string $msg, int $level): void
  {
    static $PREFIX='> ', $OK='cyan', $ERR='magenta';
    if ($level)
    {
      $a = self::str_fg_color($PREFIX, $ERR, 1);
      $a = self::str_bg_color('mustache').$a;
      $this->log($a.$msg, 1);
    }
  }
  # }}}
  static function start(array $a): bool # {{{
  {
    # configure environment
    ini_set('html_errors', 0);
    ini_set('implicit_flush', 1);
    set_time_limit(0);
    set_error_handler(function($no, $msg, $file, $line) {
      # skip supressed failures (prefixed by @)
      if (error_reporting() === 0) {
        return false;
      }
      # generate exception
      $msg = "exception($no) in file($line): $file\n$msg\n";
      throw new \Exception($msg, $no);
      return true;
    });
    # operate
    switch ($a[0]) {
    case 'loop':
      # create instance and enter getUpdates loop
      if ($b = self::init($a[1], true)) {
        return $b->loop(intval($a[2]));
      }
      else {
        echo "\nERROR: ".self::$ERROR."\n";
      }
      break;
    case 'task':
      /***
      # output startup signal,
      # any other output to STDOUT/STDERR will terminate process
      echo "TASK STARTED\n";
      # load task plan
      if (!file_exists($a[1]) ||
          !($plan = file_get_contents($a[1])) ||
          !($plan = json_decode($plan, true)) ||
          !is_array($plan) ||
          !array_key_exists('id', $plan) ||
          !($plan['id'] === $a[2]))
      {
        break;
      }
      # create instance
      if (($b = self::init($plan['bot'], true)) === null) {
        break;
      }
      # register task unlocker
      register_shutdown_function(function($file) {
        if (file_exists($file)) {
          unlink($file);
        }
      }, $a[1]);
      # operate
      try
      {
        # attach bot's user
        $b->user = (object)$plan['user'];
        $b->user->chat = (object)$b->user->chat;
        # execute
        if (!$b->taskWork($plan)) {
          throw new \Exception($a[0].' failed #'.$plan['id'].': '.$plan['item']);
        }
        $b->taskDetach();# enable continuation
      }
      catch (\Exception $e) {
        $b->logException($e);# recorded
      }
      /***/
      break;
    }
    # complete
    restore_error_handler();
    return false;# avoid infinte loop
  }
  # }}}
  static function init(string $botid, bool $console): ?self # {{{
  {
    # check identifier
    $isMaster = ($botid === 'master');
    if (!$isMaster && (!ctype_digit($botid) || strlen($botid) > 14))
    {
      self::$ERROR = "incorrect bot identifier: $botid";
      return null;
    }
    # determine directory paths
    $homedir = __DIR__.DIRECTORY_SEPARATOR;
    $incdir  = $homedir.'inc'.DIRECTORY_SEPARATOR;
    $fontdir = $incdir.'font'.DIRECTORY_SEPARATOR;
    $datadir = $homedir.'data'.DIRECTORY_SEPARATOR.$botid.DIRECTORY_SEPARATOR;
    if (!file_exists($datadir))
    {
      self::$ERROR = "directory not found: $datadir";
      return null;
    }
    # construct configuration
    if (!($opts = BotConfig::init($datadir)))
    {
      self::$ERROR = "failed to load configuration: $datadir";
      return null;
    }
    # determine bot directory
    $botdir = $isMaster ? $botid : $opts->bot;
    $botdir = $homedir.'bots'.DIRECTORY_SEPARATOR.$botdir.DIRECTORY_SEPARATOR;
    if (!file_exists($botdir))
    {
      self::$ERROR = "directory not found: $botdir";
      return null;
    }
    # load bot controllers
    if (file_exists($a = $botdir.'control.php')) {
      require_once $a;
    }
    elseif (file_exists($a = $botdir.'control.js'))
    {
      # TODO: wrap NODE.js
      return null;
    }
    else
    {
      self::$ERROR = "bot controller not found: $botdir";
      return null;
    }
    # construct self
    $bot = new static([
      'id'        => $botid,
      'opts'      => $opts,
      'stdout'    => ($console ? fopen('php://stdout', 'w') : false),
      'errors'    => 0,
      'isMaster'  => $isMaster,
      ###
      'homedir'   => $homedir,  # /
      'incdir'    => $incdir,   # /inc/
      'fontdir'   => $fontdir,  # /inc/font/
      'datadir'   => $datadir,  # /data/<botid>/
      'botdir'    => $botdir,   # /bots/<botid>/
      'errorlog'  => $datadir.'ERROR.log',
      'accesslog' => $datadir.'ACCESS.log',
      ###
      'api'       => null,
      'tp'        => null,# Mustache
      'messages'  => null,# [lang:[index:text]]
      'buttons'   => null,# [button:caption]
      'fids'      => null,# [file:id]
      'items'     => null,# BotItems
      'user'      => null,# BotUser
    ]);
    # construct api (telegram)
    if (($api = BotApi::init($bot)) === null)
    {
      self::$ERROR = "failed to initialize api: $botid";
      return null;
    }
    # construct template parser
    # {{{
    require_once $incdir.'mustache.php';
    $a = [
      'logger'  => (function(string $msg, int $level) use ($bot) {
        $bot->logMustache($msg, $level);
      }),
      'helpers' => [
        'BR'    => "\n",
        'NBSP'  => "\xC2\xA0",# non-breakable space
        'END'   => "\xC2\xAD",# SOFT HYPHEN U+00AD
      ],
    ];
    if (($bot->tp = Mustache::init($a)) === null)
    {
      self::$ERROR = "failed to initialize template parser";
      return null;
    }
    # }}}
    # initialize messages
    # {{{
    if (file_exists($a = $datadir.'messages.json'))
    {
      # load precompiled
      $bot->messages = json_decode(file_get_contents($a), true);
    }
    else
    {
      # merge and render
      $bot->messages = file_exists($b = $botdir.'messages.inc')
        ? array_merge(self::$MESSAGES, (require $b))
        : self::$MESSAGES;
      foreach ($bot->messages as &$c)
      {
        foreach ($c as &$d) {
          $d = $bot->tp->render($d, '{: :}', self::$EMOJI);
        }
      }
      unset($c, $d);
      # store
      $b = json_encode($bot->messages, JSON_UNESCAPED_UNICODE);
      $b && file_put_contents($a, $b);
    }
    # }}}
    # initialize button captions
    # {{{
    if (file_exists($a = $datadir.'buttons.json'))
    {
      # load precompiled
      $bot->buttons = json_decode(file_get_contents($a), true);
    }
    else
    {
      # merge and render
      $bot->buttons = file_exists($b = $botdir.'buttons.inc')
        ? array_merge(self::$BUTTONS, (require $b))
        : self::$BUTTONS;
      foreach ($bot->buttons as &$c) {
        $c = $bot->tp->render($c, '{: :}', self::$EMOJI);
      }
      unset($c);
      # store
      $b = json_encode($bot->buttons, JSON_UNESCAPED_UNICODE);
      $b && file_put_contents($a, $b);
    }
    # }}}
    # initialize file_id map
    $bot->fids = file_exists($a = $datadir.'file_id.json')
      ? json_decode(file_get_contents($a), true)
      : [];
    # initialize commands items
    if (($bot->commands = BotItems::init($bot)) === null) {
      return null;
    }
    # initialize static props
    if (self::$INIT)
    {
      self::$INIT = false;
      self::$IS_WIN = (
        defined('PHP_OS_FAMILY') &&
        strncasecmp(PHP_OS_FAMILY, 'WIN', 3) === 0
      );
    }
    # complete
    return $bot;
  }
  # }}}
  function loop(int $timeout): bool # {{{
  {
    # show commands tree
    $name = self::str_fg_color('@'.$this->opts->name, 'cyan');
    $this->log("$name\n".$this->commands->dump(2, 'cyan'));
    # acquire a lock
    if (!($lock = self::file_lock($file = $this->datadir.'o.json')))
    {
      $this->logWarn($name.' has already been started');
      return false;
    }
    # enforce graceful termination
    self::$IS_WIN && sapi_windows_set_ctrl_handler(function ($i) use ($lock) {
      @unlink($lock);
      exit(1);
    });
    # prepare
    $this->log("$name starting getUpdates loop..");
    $req = [
      'offset'  => 0,
      'timeout' => $timeout,
    ];
    # operate
    while ($this->errors < 5 && file_exists($lock))
    {
      # request updates (long polling)
      if (!($a = $this->api->send('getUpdates', $req))) {
        sleep(1); continue;
      }
      # reset error counter
      $this->errors = 0;
      # process updates
      foreach ($a->result as $update)
      {
        if (!$this->operate($update))
        {
          # refresh api offset
          $this->api->send('getUpdates', [
            'offset'  => $update->update_id + 1,
            'timeout' => 0,
            'limit'   => 1,
          ]);
          # restart
          self::file_unlock($file);
          $this->log("$name restart\n");
          return true;
        }
      }
      # shift offset
      $req['offset'] = $update->update_id + 1;
    }
    # terminate
    self::file_unlock($file);
    $this->log("$name finished\n");
    return false;
  }
  # }}}
  function operate(object $u): bool # {{{
  {
    # construct the request object
    $req = null;
    if (isset($u->callback_query)) {
      #$req = BotRequestCallback::init($u->callback_query);
    }
    elseif (isset($u->inline_query)) {
      #$req = BotRequestQuery::init($u->inline_query);
    }
    elseif (isset($u->message)) {
      $req = BotRequestInput::init($u->message);
    }
    # reply user request and complete
    return ($req && ($res = BotUser::init($this, $req)))
      ? $res->finit()
      : true;
  }
  # }}}
  # user {{{
  private function userConfigAttach() # {{{
  {
    # determine filename (depends on chat)
    $file = $this->user->dir.'config';
    $chat = $this->user->chat;
    if ($chat->type !== 'private') {
      $file = $file.strval($chat->id);
    }
    $file = $file.'.json';
    # aquire a forced lock
    if (!self::file_lock($file, true))
    {
      $this->logError("forced lock failed: $file");
      return false;
    }
    # read, decode contents and
    # set user's configuration
    $this->user->config = file_exists($file)
      ? json_decode(file_get_contents($file), true)
      : [];
    # done
    $this->user->file = $file;
    $this->user->changed = false;
    return true;
  }
  # }}}
  private function userUpdate($msg) # {{{
  {
    # prepare
    $item = &$this->item;
    $id0  = $item['root']['id'];
    $conf = &$this->user->config;
    $msg0 = isset($conf[$id0]['_msg']) ? $conf[$id0]['_msg'] : 0;
    # check active roots
    if (!isset($conf['/'])) {
      $conf['/'] = [];# nothing was active
    }
    elseif (!$msg0)
    {
      # item wasn't active, but it could be injector,
      # in this case it must be removed
      foreach ($conf['/'] as $a)
      {
        if (isset($conf[$a]['_from']) &&
            $conf[$a]['_from'] === $id0)
        {
          $this->itemDetach($this->commands[$a]);
          break;
        }
      }
    }
    elseif ($msg !== $msg0)
    {
      # new message replaces current
      $this->itemDetach($item);
    }
    # check item is root and
    # update configuration
    if ($id0 === $item['id'])
    {
      # item only
      $conf[$id0] = $item['config'];
    }
    else
    {
      # item and root
      $conf[$item['id']] = $item['config'];
      $conf[$id0] = $item['root']['config'];
    }
    # message
    $conf[$id0]['_msg']  = $msg;
    $conf[$id0]['_item'] = $item['id'];
    $conf[$id0]['_hash'] = $item['hash'];
    if ($msg !== $msg0)
    {
      # for a new message,
      # set its creation time
      $conf[$id0]['_time'] = time();
      # activate item's root
      array_unshift($conf['/'], $id0);
    }
    # done
    return $this->user->changed = true;
  }
  # }}}
  private function userConfigDetach($nowrite = false) # {{{
  {
    if ($file = $this->user->file)
    {
      # write changes
      if (!$nowrite && $this->user->changed) {
        file_put_contents($file, json_encode($this->user->config));
      }
      # release lock
      self::file_unlock($file);
    }
    return true;
  }
  # }}}
  # }}}
  # item {{{
  function itemAttach($text, $isNew = false) # {{{
  {
    # reset
    $this->item = null;
    # check parameter
    if (!$text || !is_string($text) || strlen($text) > 200)
    {
      $this->log('incorrect argument');
      return 0;
    }
    # check groupchat command (ends with @botname)
    $isGroupChat = false;
    if (($a = strrpos($text, '@')) !== false)
    {
      # check addressed to this bot
      if (substr($text, $a + 1) !== $this->opts['bot']) {
        return -1;# ignore
      }
      # correct
      $text = substr($text, 0, $a);
      $isGroupChat = true;
    }
    # parse
    $a = null;
    if (!preg_match_all(self::COMMAND_EXP, $text, $a))
    {
      $this->logError("incorrect syntax: $text");
      return 0;
    }
    # extract
    $id   = $a[1][0];
    $func = $a[6][0];
    $args = strlen($a[8][0])
      ? explode(',', $a[8][0])
      : null;
    # refine,
    # identifier may contain [/] or [-] separators,
    # convert to normal [:] for user convenience, but first,
    # check deep link invocation, tg://<BOT_NAME>?start=<args>
    if ($id === 'start' && !$func && $args)
    {
      # [:] is not allowed in the "deep link", so [-] assumed
      $id   = str_replace('-', ':', $args[0]);
      $args = null;
    }
    elseif (strpos($id, '/')) {
      $id = str_replace('/', ':', $id);
    }
    elseif (strpos($id, '-')) {
      $id = str_replace('-', ':', $id);
    }
    # report
    $this->log($text);
    # get item and set hint
    if (!($item = $this->itemGet($id)))
    {
      $this->logError("failed to get the item: $id");
      return 0;
    }
    $item['isNew'] = $isNew;
    /***
    # check new item is injected
    if ($isNew && ($func !== 'up') && isset($item['root']['config']['_from']))
    {
      unset($item['root']['config']['_from']);
      $this->user->changed = true;
    }
    /***/
    # invoke, attach and complete
    $a = $this->itemRender($item, $func, $args);
    $this->item = ~$a ? $item : null;
    return $a;
  }
  # }}}
  function itemRender(&$item, $func = '', $args = null) # {{{
  {
    # prepare {{{
    $id   = $item['id'];
    $name = $item['name'];
    $root = $this->itemGetRoot($id);
    $conf = &$item['config'];
    $lang = $item['lang'] = $this->user->lang;
    $text = &$item['text'][$lang];
    ###
    $item['root'] = &$root;
    $item['hash'] = '';
    $item['data'] = null;
    $item['dataChanged'] = false;
    $item['isTitleCached'] = true;
    $item['isInputAccepted'] = false;
    $item['titleId'] = $name.'_'.$lang;
    $item['title'] = isset($text['@']) ? $text['@'] : '';
    $item['content'] = isset($text['.']) ? $text['.'] : '';
    ###
    $item['titleImage'] = null;   # file_id or BotFile
    $item['textContent'] = null;  # message text
    $item['inlineMarkup'] = null; # null=skip, []=zap, [buttons]=otherwise
    # }}}
    # handle common function {{{
    switch ($func) {
    case 'inject':
      # {{{
      # remove current item
      $this->itemDetach($item);
      # check injection origin
      if (!isset($args['root']) ||
          !($a = $args['root']) ||
          !isset($a['config']['_msg']) ||
          !$a['config']['_msg'])
      {
        $this->logError('no injection origin');
        return 0;
      }
      # copy origin configuration
      $b = &$a['config'];
      $c = &$root['config'];
      $c['_from'] = $args['id'];
      $c['_msg']  = $b['_msg'];
      $c['_time'] = $b['_time'];
      $c['_item'] = $c['_hash'] = '';# not the same item
      # clear origin
      $b['_msg']  = 0;
      $b['_time'] = 0;
      $b['_hash'] = '';
      unset($b, $c);
      # replace active root
      $b = &$this->user->config['/'];
      if (($c = array_search($a['id'], $b, true)) !== false) {
        $b[$c] = $item['root']['id'];
      }
      unset($b, $c);
      # set changed
      $this->user->changed = true;
      # cleanup
      $func = '';
      # }}}
      break;
    case 'up':
      # {{{
      # check
      if ($item['parent'])
      {
        # CLIMB UP THE TREE (replace with parent)
        return $this->itemRender($item = $item['parent']);
      }
      if (isset($root['config']['_from']))
      {
        # EJECT BACK TO THE ORIGIN
        # copy root parameters
        $a = &$root['config'];
        $b = $a['_from'];
        $c = &$this->user->config[$b];
        $c['_msg']  = $a['_msg'];
        $c['_time'] = $a['_time'];
        $c['_item'] = $c['_hash'] = '';# not the same item
        $a['_msg']  = $a['_time'] = 0;
        unset($a['_from']);
        unset($a, $c);
        # rename active root
        $c = &$this->user->config['/'];
        if (($a = array_search($root['id'], $c, true)) !== false) {
          $c[$a] = $b;
        }
        unset($a, $c);
        # set changed
        $this->user->changed = true;
        # replace item and recurse
        return $this->itemRender($item = $this->itemGet($b));
      }
      # }}}
      # fallthrough otherwise..
    case 'close':
      $this->itemDetach($item);
      # fallthrough..
    case 'nop':
      return -1;
    }
    # }}}
    # handle navigational item {{{
    if (($a = $item['type']) === 'open' || $a === 'inject')
    {
      # checkout destination
      $b = $item;
      if (!isset($item['path']) || !($item = $this->itemGet($item['path'])))
      {
        $this->log('failed to '.$a.' item, path not found');
        return 0;
      }
      # recurse
      return ($a === 'open')
        ? $this->itemRender($item)
        : $this->itemRender($item, $a, $b);
    }
    # }}}
    # attach data {{{
    if ($a = $item['dataHandler'])
    {
      # invoke handler
      if (!$a::attach($this, $item)) {
        return 0;
      }
    }
    elseif ($item['type'])
    {
      # load from file
      $file = $item['type'].'_'.$name.'.json';
      $file = $item['isPublicData']
        ? $this->datadir.$file
        : $this->user->dir.$file;
      if (file_exists($file) && ($a = file_get_contents($file))) {
        $item['data'] = json_decode($a, true);
      }
    }
    # }}}
    # invoke item/type handler {{{
    if ((($a = $item['itemHandler']) && method_exists($a, 'render')) ||
        ($a = $item['typeHandler']))
    {
      if (($b = $a::render($this, $item, $func, $args)) !== 1)
      {
        !$b && $this->logError('failed to render: '.$a);
        return $b;
      }
    }
    elseif ($item['type'])# non-basic
    {
      $this->logError('unknown type: '.$item['type']);
      return 0;
    }
    # }}}
    # set defaults {{{
    if ($item[$a = 'textContent'] === null) {
      $item[$a] = $item['content'];
    }
    $b = ($item[$a = 'inlineMarkup'] === null && $item['markup'])
      ? $this->itemInlineMarkup($item, $item['markup'], $text)
      : $item[$a];
    $item[$a] = $b ? json_encode(['inline_keyboard'=>$b]) : '';
    ###
    if ($item['titleImage'] === null)
    {
      # all standard sm-bot items have a title image,
      # image may be dynamic (generated) or static
      # so first, check file_id cache
      if ($a = $this->getFileId($item['titleId']))
      {
        # CACHED
        $item['titleImage'] = $a;
      }
      elseif (!$item['title'])
      {
        # STATIC IMAGE? (no text specified)
        # determine base image paths
        $b = 'img'.DIRECTORY_SEPARATOR;
        $c = $b.$item['titleId'].'.jpg';# more specific first
        $d = $b.$name.'.jpg';# less specific last
        $a = $this->botdir;
        $b = $this->incdir;
        # determine single source
        $a = (file_exists($a.$c)
          ? $a.$c : (file_exists($a.$d)
            ? $a.$d : (file_exists($b.$c)
              ? $b.$c : (file_exists($b.$d)
                ? $b.$d : ''))));
        # check found
        if ($a)
        {
          # static image file
          $this->logDebug("image request: $a");
          $item['titleImage'] = new BotFile($a, false);
        }
        else
        {
          # use item's name and raw breadcrumb (no language)
          $a = $this->itemBreadcrumb($item);
          $item['titleImage'] = $this->imageTitle($item['name'], $a);
        }
      }
      else
      {
        # SPECIFIED TEXT
        # generate nice header with language specific breadcrumbs
        $a = $this->itemBreadcrumb($item, $lang);
        $item['titleImage'] = $this->imageTitle($item['title'], $a);
      }
    }
    # }}}
    # detach data {{{
    if ($item['dataChanged'])
    {
      if ($a = $item['dataHandler'])
      {
        # invoke handler
        if (!$a::detach($this, $item)) {
          $this->logError("failed to detach data: $a");
        }
      }
      else
      {
        # store file
        if ($item['data'] && !file_put_contents($file, json_encode($item['data']))) {
          $this->logError('file_put_contents('.$file.') failed');
        }
        elseif (!$item['data'] && !@unlink($file)) {
          $this->logError('unlink('.$file.') failed');
        }
      }
    }
    # }}}
    return 1;
  }
  # }}}
  function itemGet($id) # {{{
  {
    # get item
    # search from the root
    $item = $this->commands;
    $path = explode(':', $id);
    if (!array_key_exists($path[0], $item)) {
      return null;
    }
    $item = $item[$path[0]];
    # iterate remaining path
    foreach (array_slice($path, 1) as $a)
    {
      if (!array_key_exists('items', $item) ||
          !array_key_exists($a, $item['items']))
      {
        $error = $this->messages[$this->user->lang][1];
        return null;
      }
      $item = $item['items'][$a];
    }
    # attach item's configuration
    if (!array_key_exists($id, $this->user->config)) {
      $this->user->config[$id] = $item['config'];
    }
    $item['config'] = &$this->user->config[$id];
    # set handlers
    $a = '\\'.__NAMESPACE__.'\\';
    $b = str_replace(':', '_', $item['id']);
    $item['dataHandler'] = class_exists(($c = $a.'data_'.$b), false) ? $c : '';
    $item['itemHandler'] = class_exists(($c = $a.'item_'.$b), false) ? $c : '';
    $item['typeHandler'] = class_exists(($c = $a.'type_'.$item['type']), false) ? $c : '';
    # done
    return $item;
  }
  # }}}
  function itemGetRoot($id) # {{{
  {
    # extract root identfier
    if (($i = strpos($id, ':')) !== false) {
      $id = substr($id, 0, $i);
    }
    # complete
    return $this->itemGet($id);
  }
  # }}}
  function itemBreadcrumb(&$item, $lang = '', $short = false) # {{{
  {
    # determine first crumb
    $crumb = (!$item['parent'] && isset($item['config']['_from']))
      ? $this->itemGet($item['config']['_from'])
      : $item['parent'];
    # assemble
    $bread = '';
    if ($short)
    {
      if ($crumb)
      {
        $bread = ($lang && isset($crumb['text'][$lang]['@']))
          ? $crumb['text'][$lang]['@']
          : $crumb['name'];
      }
    }
    else
    {
      while ($crumb)
      {
        $a = ($lang && isset($crumb['text'][$lang]['@']))
          ? $crumb['text'][$lang]['@']
          : $crumb['name'];
        $bread = '/'.$a.$bread;
        $crumb = $crumb['parent'];
      }
    }
    return $bread;
  }
  # }}}
  function itemInlineMarkup(&$item, &$m, &$text, $ext = null) # {{{
  {
    # check
    if (!$m) {
      return [];# empty
    }
    # prepare
    $id   = $item['id'];
    $lang = $this->user->lang;
    $mkup = [];
    # iterate
    foreach ($m as $a)
    {
      # assemble single row
      $row = [];
      foreach ($a as $b)
      {
        # check already created button (keep as is)
        if (!is_string($b))
        {
          $row[] = $b;
          continue;
        }
        # check no element
        if (!($c = strlen($b))) {
          continue;
        }
        # check NOP
        if ($c === 1)
        {
          $row[] = ['text'=>' ','callback_data'=>'!'];
          continue;
        }
        # check tree navigation
        if ($b[0] !== '_')
        {
          if ($item['items'] && isset($item['items'][$b]))
          {
            # child goto
            $c = isset($text[$b]) ? $text[$b] : $b;
            $c = $this->tp->render($this->buttons['go'], ['text'=>$c]);
            $d = '/'.$item['id'].':'.$b;
          }
          else
          {
            # NOP
            $c = ' ';
            $d = '!';
          }
          $row[] = ['text'=>$c,'callback_data'=>$d];
          continue;
        }
        # callback button _
        # check goto command (navigation)
        $d = substr($b, 1);
        if (strncmp($d, 'go:', 3) === 0)
        {
          # determine goto caption
          $c = isset($text[$b]) ? $text[$b] : $this->buttons['go'];
          $d = '/'.substr($d, 3);
          $c = $this->tp->render($c, [
            'text' => str_replace(':', '/', $d)
          ]);
          # compose nav button
          $row[] = ['text'=>$c,'callback_data'=>$d];
          continue;
        }
        # determine default caption
        $c = isset($text[$b])
          ? $text[$b]
          : (isset($this->buttons[$d])
            ? $this->buttons[$d]
            : (isset($text[$d])
              ? $text[$d]
              : $d));
        # check common commands
        if ($d === 'play')
        {
          # play button (game message)
          $row[] = ['text'=>$c,'callback_game'=>null];
          continue;
        }
        elseif ($d === 'up')
        {
          # {{{
          # determine caption variant
          if ($item['parent'] ||
              array_key_exists('_from', $item['config']))
          {
            # BREADCRUMB to the root
            $e = $this->itemBreadcrumb($item, $lang, true);
          }
          else
          {
            # CLOSE button (message will be deleted)
            $e = $this->messages[$lang][10];
            $c = $this->buttons['close'];
          }
          $c = $this->tp->render($c, ['text'=>$e]);
          # }}}
        }
        elseif (isset($this->buttons[$d]))
        {
          # {{{
          if ($ext)
          {
            # do not render this element
            if (!isset($ext[$d])) {
              continue;
            }
            # render empty nop
            if (!$ext[$d])
            {
              $row[] = ['text'=>' ','callback_data'=>'!'];
              continue;
            }
            # render specified value
            $c = $this->tp->render($c, ['x'=>$ext[$d]]);
          }
          else
          {
            # render without extras
            $c = $this->tp->render($c, [
              'x'=>(isset($text[$d]) ? $text[$d] : '')
            ]);
          }
          # }}}
        }
        elseif ($d === 'done' || $d === 'progress') {
          continue;# prohibited
        }
        # complete creation
        $d = '/'.$id.'!'.$d;
        $row[] = ['text'=>$c,'callback_data'=>$d];
      }
      # collect non-empty
      $row && $mkup[] = $row;
    }
    # done
    return $mkup;
  }
  # }}}
  function itemSend(&$item = null) # {{{
  {
    # prepare
    if ($item === null && !($item = &$this->item)) {
      return 0;
    }
    # CUSTOM
    if (($a = $item['typeHandler']) && method_exists($a, 'send')) {
      return $a::send($this, $item);
    }
    # STANDARD
    # check title specified
    if (!$item['titleImage'])
    {
      $this->logError('no titleImage specified');
      return 0;
    }
    # assemble parameters
    $a = $item['titleId'];
    $b = $item['textContent'];
    $c = $item['inlineMarkup'];
    $q = [
      'chat_id' => $this->user->chat->id,
      'photo'   => $item['titleImage'],
      'disable_notification' => true,
    ];
    if ($b)
    {
      $q['caption']    = $b;
      $q['parse_mode'] = 'HTML';
    }
    if ($c) {
      $q['reply_markup'] = $c;
    }
    # send
    if (!($d = $this->api->send('sendPhoto', $q)))
    {
      $this->logError('sendPhoto('.$item['id'].') failed: '.$this->api->error);
      $this->logError($q);
      return 0;
    }
    # set message hash
    $item['hash'] = $a.hash('md4', $b.$c);
    # set file_id
    if ($a && ($item['titleImage'] instanceOf BotFile))
    {
      $b = end($d->result->photo);
      $this->setFileId($a, $b->file_id);
    }
    # complete
    return $this->userUpdate($d->result->message_id);
  }
  # }}}
  function itemUpdate($msg, &$item, $reportHashFail = false) # {{{
  {
    # CUSTOM
    if (($a = $item['typeHandler']) && method_exists($a, 'update')) {
      return $a::update($this, $msg, $item);
    }
    # STANDARD
    # prepare
    $root = &$item['root']['config'];
    $a = $item['titleId'];
    $b = $item['textContent'];
    $c = $item['inlineMarkup'];
    $d = hash('md4', $b.$c);
    # check message hash
    if ($a.$d === ($e = $root['_hash']))
    {
      if ($reportHashFail) {
        $this->logError('update skipped: '.$item['id']);
      }
      return -1;# positive
    }
    # determine which API function to use,
    # image changes when title identifier changes
    $func = strlen($e) - strlen($d);
    $func = ($a !== substr($e, 0, $func));
    # determine message parameters
    if ($func)
    {
      # IMAGE and TEXT
      # {{{
      $file = $item['titleImage'];
      $func = 'editMessageMedia';
      if ($file instanceof BotFile) {
        $e = 'attach://'.$file->postname;# attachment
      }
      else
      {
        $e = $file;# file_id
        $file = null;# not attachment
      }
      $res = [
        'type'       => 'photo',
        'media'      => $e,
        'caption'    => $b,
        'parse_mode' => 'HTML',
      ];
      $res = [
        'chat_id'    => $this->user->chat->id,
        'message_id' => $msg,
        'media'      => json_encode($res),
      ];
      # }}}
    }
    else
    {
      # TEXT
      # {{{
      $file = null;
      $func = 'editMessageCaption';
      $res  = [
        'chat_id'    => $this->user->chat->id,
        'message_id' => $msg,
        'caption'    => $b,
        'parse_mode' => 'HTML',
      ];
      # }}}
    }
    if ($c) {# add MARKUP
      $res['reply_markup'] = $c;
    }
    # send
    if (!($b = $this->api->send($func, $res, $file)) || $b === true)
    {
      $this->logError($func.'('.$item['id'].') failed: '.$this->api->error);
      $this->logError($res);
      return 0;
    }
    # set message hash
    $item['hash'] = $a.$d;
    # set file_id
    if ($a && $file)
    {
      $c = end($b->result->photo);
      $this->setFileId($a, $c->file_id);
    }
    # complete
    return $this->userUpdate($b->result->message_id);
  }
  # }}}
  function itemZap($msg) # {{{
  {
    # try simply delete
    $a = $this->api->send('deleteMessage', [
      'chat_id'    => $this->user->chat->id,
      'message_id' => $msg,
    ]);
    if ($a)
    {
      $this->log("message $msg deleted");
      return true;
    }
    # check result details
    if (($a = $this->api->result) &&
        isset($a->error_code) &&
        $a->error_code === 400)
    {
      # message is too old for deletion,
      # it should be "nulified": image/text/markup blanked,
      # but the message type is unknown, so,
      # try common
      if ($this->imageZap($msg))
      {
        $this->log("message $msg nulified");
        return true;
      }
      # eh..
    }
    return false;
  }
  # }}}
  function itemDetach(&$item = null, $wipeConfig = false) # {{{
  {
    # prepare
    if (($item === null) && !($item = &$this->item)) {
      return true;
    }
    $conf = &$this->user->config;
    $id1  = $item['id'];
    $id0  = isset($item['root']) ? $item['root']['id'] : $id1;
    # check root attached and active
    if (!isset($conf[$id0]) || !($root = &$conf[$id0]) ||
        !isset($root['_msg']) || !$root['_msg'])
    {
      return false;
    }
    # report
    $this->log("detaching /$id0");
    # telegram allows to delete only "fresh" messages
    # check message timestamp to determine proper operation
    if (($a = time() - $root['_time']) >= 0 &&
        ($a < self::MESSAGE_LIFETIME))
    {
      # DELETE
      # primary message
      $a = [
        'chat_id'    => $this->user->chat->id,
        'message_id' => $root['_msg'],
      ];
      if (!$this->api->send('deleteMessage', $a)) {
        $this->logError($this->api->error);
      }
      # item or type specific
      if ((($a = $item['itemHandler']) && method_exists($a, 'delete')) ||
          (($a = $item['typeHandler']) && method_exists($a, 'delete')))
      {
        $a::delete($this, $item);
      }
    }
    else
    {
      # ZAP
      # primary message
      $a = $this->imageZap($msg);
      # item or type specific
      if ((($a = $item['itemHandler']) && method_exists($a, 'zap')) ||
          (($a = $item['typeHandler']) && method_exists($a, 'zap')))
      {
        $a::zap($this, $item);
      }
    }
    # deactivate item's root
    if (isset($conf['/']) && ($a = array_search($id0, $conf['/'])) !== false) {
      array_splice($conf['/'], $a, 1);
    }
    # reset message configuration
    $root['_msg']  = $root['_time'] = 0;
    $root['_item'] = $root['_hash'] = '';
    if ($wipeConfig && isset($conf[$id1])) {
      $conf[$id1] = [];
    }
    # complete
    return $this->user->changed = true;
  }
  # }}}
  # }}}
  # task manager {{{
  public function taskAttach( # {{{
    &$item,       # object (standard) or string (custom)
    $data = null, # task data
    $tick = false # enables progress ticks
  ) {
    ###
    if ($item)
    {
      # compose php interpreter command
      $task = __DIR__.DIRECTORY_SEPARATOR.'index.php';
      $task = '"'.PHP_BINARY.'" -f "'.$task.'" -- ';
      # check type
      if (is_string($item))
      {
        # unmanaged, custom operation
        $this->log("custom task: $item");
        $this->tasks[] = [0, $task.$item];
      }
      else
      {
        # standard
        # determine task plan file
        $file = 'task_'.$item['path'].'.json';
        $file = $this->user->dir.$file;
        if ($this->opts['debugtask'])
        {
          $plan = -1;
          $this->log('debug task: '.$item['id']);
        }
        else
        {
          $plan = uniqid();
          $this->log("task: $file");
        }
        # determine process arguments
        $args = ' "'.$file.'" '.$plan;
        # add worker
        $this->tasks[] = [$plan, $task.'task'.$args, $file, $item['id'], $data];
        # add ticker
        if ($tick) {
          $this->tasks[] = [$plan, $task.'progress'.$args, $file, $item['id'], $data];
        }
      }
    }
    return true;
  }
  # }}}
  public function taskDetach() # {{{
  {
    # copy and reset
    $tasks = $this->tasks;
    $debugtask = $this->opts['debugtask'];
    $this->tasks = [];
    # spawn all tasks one by one
    foreach ($tasks as $task)
    {
      # create task plan
      $file = $task[2];
      $plan = [
        'id'   => $task[0],
        'bot'  => $this->id,
        'item' => $task[3],
        'data' => $task[4],
        'user' => $this->user,
      ];
      # run
      if ($debugtask)
      {
        # sync
        $this->log('===');
        if (!$this->taskWork($plan)) {break;}
        $this->log('===');
      }
      else
      {
        # async
        # create plan file
        if (!file_put_contents($file, json_encode($plan)))
        {
          $this->logError("file_put_contents($file) failed");
          break;
        }
        # launch
        if (!self::async_execute($task[1])) {
          break;
        }
      }
    }
    # update user configuration (in debug mode)
    if ($debugtask) {
      $this->userConfigDetach();
    }
    # done
    return true;
  }
  # }}}
  private function taskWork($plan) # {{{
  {
    # measure time
    $time = microtime(true);
    # possess item
    if (!($item = $this->itemGet($plan['item'])))
    {
      $this->logError('item not found: '.$plan['item']);
      return false;# unlikely
    }
    # possess handler
    if (!($a = $item['typeHandler']))
    {
      $this->logError('handler not found: '.$plan['item']);
      return false;# no handler
    }
    # execute
    if (!($res = $a::task($this, $item, $plan['data'])) ||
        !is_array($res) || !count($res))
    {
      $res = [0];
    }
    # measure time spent and delay completion
    $a = 300000;# 300ms
    if (($b = microtime(true) - $time) > 0 && $b < $a) {
      usleep($a - $b);
    }
    # complete
    return $this->taskUpdate(
      $item, 'done',
      $res, $this->opts['debugtask'],
      null
    );
  }
  # }}}
  private function taskProgress($plan, $file) # TODO {{{
  {
    /***
    static
      $tickSize = 500000;# 500ms
      $maxTicks = 2*7200;# x500ms, 7200=1h
    ###
    # determine item handler
    $item = null;
    $a = '\\'.__NAMESPACE__.'\\item_';
    $a = $a.str_replace(':', '_', $plan['item']);
    if (!class_exists($a, false)) {
      return false;
    }
    # start ticks loop
    $b = method_exists($a, 'tick');
    $c = 0;# counter
    $d = 0;# default result
    while (file_exists($file) && $c < $maxTicks)
    {
      # suspend
      usleep($tickSize);
      if (!file_exists($file)) {
        break;
      }
      # tick
      if ($b)
      {
        # custom
        $e = $a::tick($this, $item, $c, $d);
      }
      else
      {
        # default
        $e = ($c % 2) ? 1 : 0;
      }
      ++$c;
      # update when changed
      if ($d !== $e)
      {
        $this->taskRender($item, [$e]);
        $d = $e;
      }
    }
    # done
    return true;
    /***/
  }
  # }}}
  public function taskRender(&$item, $args, $callback = null) # {{{
  {
    return $this->taskUpdate(
      $item, 'progress',
      $args, $this->opts['debugtask'],
      $callback
    );
  }
  # }}}
  private function taskUpdate(&$item, $func, $args, $debug, $callback) # {{{
  {
    # before any task update is made to the user's view,
    # configuration must be locked (except the debug case)
    if (!$debug && !$this->userConfigAttach()) {
      return false;
    }
    try
    {
      # attach task item
      $a = $args ? ' '.implode(',', $args) : '';
      $a = '/'.$item['id'].'!'.$func.$a;
      if (!($b = $this->itemAttach($a))) {
        throw new \Exception("failed to attach: $a");
      }
      elseif ($b === -1) {
        throw new \Exception('', -1);
      }
      $item = $this->item;
      # check displayed
      # the _item parameter is untrusted..
      $c = $this->user->config;
      $b = $item['root']['id'];
      if (array_key_exists($b, $c) &&
          array_key_exists('_msg', $c[$b]) &&
          $c[$b]['_msg'] &&
          $c[$b]['_item'] === $item['id'])
      {
        # callback and update
        if (!$callback || $callback($item)) {
          $this->itemUpdate($c[$b]['_msg'], $item, ($func !== 'done'));
        }
      }
    }
    catch (\Exception $e) {
      ~$e->getCode() && $this->logException($e);
    }
    # release user configuration lock
    if (!$debug) {
      $this->userConfigDetach();
    }
    # complete
    return $item;
  }
  # }}}
  # }}}
  # image {{{
  public function imageTitle( # {{{
    $text,          # header
    $bread  = '',   # breadcrumb (tree path)
    $color  = null, # [foreground,background] RGBs
    $font   = '',   # font (full path to a file)
    $asFile = 1     # result is BotFile (1=temporary, 2=persistent)
  )
  {
    # prepare {{{
    # get font and color
    if (!$font) {
      $font = $this->fontdir.$this->opts['fonts']['title'];
    }
    if (!$color) {
      $color = $this->opts['colors'];
    }
    # create image (RGB color)
    if (($img = @imagecreatetruecolor(640, 160)) === false)
    {
      $this->log('imagecreatetruecolor() failed');
      return null;
    }
    # get colors
    $fg = $color[0];
    $bg = $color[1];
    # allocate colors and fill the background
    if ((($bg = imagecolorallocate($img, $bg[0], $bg[1], $bg[2])) === false) ||
        (($fg = imagecolorallocate($img, $fg[0], $fg[1], $fg[2])) === false) ||
        !imagefill($img, 0, 0, $bg))
    {
      $this->log('imagecolorallocate() failed');
      imagedestroy($img);
      return null;
    }
    # }}}
    # draw HEADER {{{
    if ($text)
    {
      # determine proper font size of a text,
      # that should fit into x:140-500,y:0-160 area
      # NOTE: font size points not pixels? (seems not!)
      $size = 64;#72;
      while ($size > 6)
      {
        # get the bounding box
        if (!($a = imagettfbbox($size, 0, $font, $text)))
        {
          $this->log('imagettfbbox() failed');
          imagedestroy($img);
          return null;
        }
        # check it fits width and height
        if ($a[2] - $a[0] <= 360 &&
            $a[1] - $a[7] <= 160)
        {
          break;
        }
        # reduce and try again
        $size -= 2;
      }
      # determine coordinates (center align)
      $x = 140 + intval((360 - $a[2]) / 2);
      #$y = 160 / 2 + intval(($a[1] - $a[7]) / 2) - 8;
      $y = 160 / 2 + 24;
      # apply
      if (!imagettftext($img, $size, 0, $x, $y, $fg, $font, $text))
      {
        $this->log('imagettftext() failed');
        imagedestroy($img);
        return null;
      }
    }
    # }}}
    # draw BREADCRUMB {{{
    if ($bread)
    {
      $x = 140;
      $y = 32;
      if (!imagettftext($img, 16, 0, $x, $y, $fg, $font, $bread))
      {
        $this->log('imagettftext() failed');
        imagedestroy($img);
        return null;
      }
    }
    # }}}
    return $asFile
      ? $this->imageToFile($img, ($asFile === 1))
      : $img;
  }
  # }}}
  public function imageToFile($img, $temp = true) # {{{
  {
    # create temporary image file
    if (!($file = tempnam(sys_get_temp_dir(), 'img')) ||
        !imagejpeg($img, $file))
    {
      $this->log("imagejpeg($file) failed");
      imagedestroy($img);
      return null;
    }
    imagedestroy($img);
    return new BotFile($file, $temp);
  }
  # }}}
  private function imageZap($msg) # {{{
  {
    # get or create empty image
    if (isset($this->fids['empty']))
    {
      $img = $this->fids['empty'];
      $media = false;
    }
    else
    {
      $img = $this->createEmptyImage(0);
      $media = 'attach://'.$img->postname;
    }
    # assemble parameters
    $a = [
      'chat_id'      => $this->user->chat->id,
      'message_id'   => $msg,
      'media'        => json_encode([
        'type'       => 'photo',
        'media'      => ($media ?: $img),
        'caption'    => '',
      ]),
      'reply_markup' => '',
    ];
    # send
    if ($media)
    {
      # with file attachment
      $a = $this->api->send('editMessageMedia', $a, $img);
    }
    else
    {
      # with file id
      $a = $this->api->send('editMessageMedia', $a);
    }
    # check the result
    if (!$a || $a === true)
    {
      $this->log('editMessageMedia failed: '.$this->api->error);
      return false;
    }
    # update file_id
    if ($media)
    {
      $b = end($a->result->photo);
      $this->setFileId('empty', $b->file_id);
    }
    # done
    return true;
  }
  # }}}
  private function createEmptyImage($variant) # {{{
  {
    # create image (RGB color)
    if (($img = @imagecreatetruecolor(640, 160)) === false)
    {
      $this->log('imagecreatetruecolor() failed');
      return null;
    }
    # get colors
    $fg = $this->opts['colors'][0];
    $bg = $this->opts['colors'][1];
    # allocate colors and fill the background
    if ((($bg = imagecolorallocate($img, $bg[0], $bg[1], $bg[2])) === false) ||
        (($fg = imagecolorallocate($img, $fg[0], $fg[1], $fg[2])) === false) ||
        !imagefill($img, 0, 0, $bg))
    {
      $this->log('imagecolorallocate() failed');
      imagedestroy($img);
      return null;
    }
    # DRAW VARIANT
    if ($variant)
    {
      # TODO
      # ...
    }
    # create temporary image file
    if (!($file = tempnam(sys_get_temp_dir(), 'bot')) ||
        !imagejpeg($img, $file))
    {
      $this->log('imagejpeg() failed');
      imagedestroy($img);
      return null;
    }
    # done
    imagedestroy($img);
    return new BotFile($file);
  }
  # }}}
  # }}}
  # helpers {{{
  static function file_lock(string $file, bool $force = false) # {{{
  {
    # prepare
    $id    = uniqid();
    $count = 80;
    $lock  = $file.'.lock';
    # wait until lock released or count exhausted
    while (file_exists($lock) && --$count) {
      usleep(100000);# 100ms
    }
    # check exhausted
    if (!$count)
    {
      # check not forced or apply force (remove lockfile)
      if (!$force || !@unlink($lock)) {
        return '';
      }
      # clear cache
      clearstatcache(true, $lock);
    }
    # set new lock and
    # make sure no collisions
    if (!file_put_contents($lock, $id) ||
        !file_exists($lock) ||
        file_get_contents($lock) !== $id)
    {
      return '';
    }
    return $lock;
  }
  # }}}
  static function file_unlock(string $file) # {{{
  {
    return (file_exists($lock = $file.'.lock'))
      ? @unlink($lock) : false;
  }
  # }}}
  static function async_execute(string $command) # {{{
  {
    if (self::$IS_WIN)
    {
      # this will not work,
      # kept for history of stdin/out redirection to NUL,
      # which would signal of no output and
      # make function return instantly (no wait)
      #system($task.' 1>NUL 2>&1');
      ###
      # this trick will not work,
      # seem that it relies on CPU speed,
      # using START command neither works
      #pclose(popen($task, 'r'));
      ###
      # this will start the START command,
      # WARNING: without the START command, it will go sync!
      # which will start the PHP CLI command (NOWAIT),
      # which will start the bot task handler,
      # which will return "TASK STARTED" here
      $command = 'START "" /B '.$command.' 2>&1';
      if (!($a = popen($command, 'r')) ||
          !($b = fgets($a)))
      {
        $this->logError("async_execute($command) failed");
        return false;
      }
      ###
      # pclose() will terminate START before
      # it actually start anything, so the task
      # response is obtained before (by reading),
      # which resolves timing problem (i guess)
      #sleep(5); # NO NEED!
      pclose($a);
      # just check that process belongs here
      if (strncmp('TASK STARTED', $b, 12) !== 0)
      {
        $this->logError("async_execute($command) incorrect response");
        return false;
      }
    }
    else
    {
      # TODO: test nix* variant
      shell_exec('/usr/bin/nohup '.$command.' >/dev/null 2>&1 &');
    }
    # done
    return true;
  }
  # }}}
  static function delay(float|int $sec): void # {{{
  {
    # determine base and remainder
    $a = intval($sec);
    $b = intval(100000 * ($sec - $a));
    # sleep
    if ($a) {sleep($a);}
    if ($b) {usleep($b);}
  }
  # }}}
  static function str_bg_color(string $str, string $name, int $strong=0): string # {{{
  {
    static $color = [
      'black'   => [40,100],
      'red'     => [41,101],
      'green'   => [42,102],
      'yellow'  => [43,103],
      'blue'    => [44,104],
      'magenta' => [45,105],
      'cyan'    => [46,106],
      'white'   => [47,107],
    ];
    $x = $color[$name][$strong];
    return (strpos($str, "\n") === false)
      ? "[{$x}m{$str}[0m"
      : "[{$x}m".str_replace("\n", "[0m\n[{$x}m", $str).'[0m';
  }
  # }}}
  static function str_fg_color(string $str, string $name, int $strong=0): string # {{{
  {
    static $color = [
      'black'   => [30,90],
      'red'     => [31,91],
      'green'   => [32,92],
      'yellow'  => [33,93],
      'blue'    => [34,94],
      'magenta' => [35,95],
      'cyan'    => [36,96],
      'white'   => [37,97],
    ];
    $x = $color[$name][$strong];
    return "[{$x}m{$str}[0m";
  }
  # }}}
  function setFileId(string $file, string $id): void # {{{
  {
    # update current
    $this->fids[$file] = $id;
    # prepare
    $a = $this->datadir.'file_id.json';
    $b = json_encode($this->fids);
    # lock file
    if (self::file_lock($a))
    {
      # check changed
      if (!file_exists($a) || file_get_contents($a) !== $b)
      {
        # update
        if (file_put_contents($a, $b) === false) {
          $this->logError('file_put_contents('.$a.') failed');
        }
      }
      # release lock
      self::file_unlock($a);
    }
  }
  # }}}
  function getFileId(string $file) # {{{
  {
    if ($file && array_key_exists($file, $this->fids) &&
        $this->opts['file_id'])
    {
      #$this->logDebug("file_id: $file");
      return $this->fids[$file];
    }
    return '';
  }
  # }}}
  ###
  function render_content($text, $data = []) # {{{
  {
    ### {{current}}, apply specific template trims
    $data['BR']  = "\n";
    $data['END'] = "\xC2\xAD";# SOFT HYPHEN U+00AD
    $data['NBSP'] = "\xC2\xA0";# non-breakable space
    $text = $this->tp->render(preg_replace('/\n\s*/m', '', $text), $data);
    $text = str_replace("\r", '', $text);
    ### {[base]}
    return $this->tp->render($text, [
      'user' => $this->user,
    ], '{[ ]}');
  }
  # }}}
  # }}}
}
# HELPERS {{{
class BotConfig extends StaticInit implements \JsonSerializable {
  # {{{
  static $DEFS = [
    # {{{
    'bot'    => '',
    'token'  => '',
    'name'   => '',
    'admins' => [],
    'colors' => [
      [240,248,255],# foreground (aliceblue)
      [0,0,0],      # background (black)
    ],
    'fonts' => [
      'title'      => 'Cuprum-Bold.ttf',
      'breadcrumb' => 'Bender-Italic.ttf',
    ],
    'forceLang' => '',
    'saveAccessLog' => false,
    'saveErrorLog' => true,
    'saveFileIds' => true,
    'gracefulCallbacks' => true,
    'debugTasks' => false,
    'sfx' => true,
    # }}}
  ];
  static function init(string $dir): ?self
  {
    # read and decode json file
    if (!file_exists($file = $dir.'o.json') ||
        !($opts = file_get_contents($file)) ||
        !($opts = json_decode($opts, true)) ||
        !is_array($opts))
    {
      return null;
    }
    # check required
    if (!isset($opts[$k = 'bot'])   || !$opts[$k] ||
        !isset($opts[$k = 'token']) || !$opts[$k] ||
        !isset($opts[$k = 'name'])  || !$opts[$k])
    {
      return null;
    }
    # construct
    return new static(array_merge(self::$DEFS, $opts));
  }
  function jsonSerialize(): array
  {
    $o = [];
    foreach (self::$DEFS as $k => &$v) {
      $o[$k] = $this->$k;
    }
    return $o;
  }
  # }}}
}
class BotApi extends StaticInit {
  # {{{
  static $URL = 'https://api.telegram.org/bot';
  static function init(object $bot): ?self
  {
    # create curl instance
    if (!function_exists('curl_init') || !($curl = curl_init())) {
      return null;
    }
    # configure
    curl_setopt_array($curl, [
      #CURLOPT_FORBID_REUSE   => true, # close after response
      CURLOPT_RETURNTRANSFER => true, # as a string
      CURLOPT_CONNECTTIMEOUT => 120,
      CURLOPT_TIMEOUT        => 120,
      #CURLOPT_HTTPHEADER     => ['connection: keep-alive', 'keep-alive: 120'],
      #CURLOPT_SSL_VERIFYHOST => 0,
      #CURLOPT_SSL_VERIFYPEER => false,
      #CURLOPT_VERBOSE        => true,
    ]);
    # construct
    return new static([
      'bot'    => $bot,
      'curl'   => $curl,
      'url'    => self::$URL.$bot->opts->token,
      'result' => null,
    ]);
  }
  # }}}
  function send(string $method, array $args, ?object $file = null): object|bool # {{{
  {
    static $tempfile = [
      'sendPhoto'     => 'photo',
      'sendAudio'     => 'audio',
      'sendDocument'  => 'document',
      'sendVideo'     => 'video',
      'sendAnimation' => 'animation',
      'sendVoice'     => 'voice',
      'sendVideoNote' => 'video_note',
    ];
    # prepare
    if ($file && ($a = $file->postname)) {
      $args[$a] = $file;
    }
    # send
    curl_setopt_array($this->curl, [
      CURLOPT_URL  => $this->url.'/'.$method,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $args,
    ]);
    $this->result = null;
    $a = curl_exec($this->curl);
    # explicitly remove temporary files
    if ($file && $file instanceof BotFile) {
        $file->__destruct();
    }
    if (isset($tempfile[$method]))
    {
      $b = $tempfile[$method];
      if (isset($args[$b]) && $args[$b] instanceof BotFile) {
        $args[$b]->__destruct();
      }
    }
    # check result
    if ($a === false)
    {
      $a = curl_errno($this->curl);
      $b = curl_error($this->curl);
      $this->bot->logError("curl_exec failed($a): $b");
      return false;
    }
    # decode
    if (!($this->result = json_decode($a)))
    {
      $a = json_last_error();
      $b = json_last_error_msg();
      $this->bot->logError("json_decode failed($a): $b");
      return false;
    }
    # check response
    if (!$this->result->ok)
    {
      $a = isset($this->result->description)
        ? ': '.$this->result->description
        : '';
      $this->bot->logError("api.$method failed$a");
      return false;
    }
    return $this->result;
  }
  # }}}
}
class BotFile extends \CURLFile {
  # {{{
  public bool $isTemp;
  function __construct(string $file, bool $temp = true)
  {
    parent::__construct($file);
    $this->postname = basename($file);
    $this->isTemp = $temp;
  }
  function __destruct()
  {
    if ($this->name && $this->isTemp) {
      @unlink($this->name) && ($this->name = '');
    }
  }
  # }}}
}
class BotItems extends StaticInit {
  # {{{
  static function init(object $bot): ?self
  {
    # check
    if ($b = file_exists($a = $bot->datadir.'commands.json'))
    {
      # load precompiled
      if (!($c = file_get_contents($a)) ||
          !($c = json_decode($c, true)))
      {
        $bot->logError("failed to load: $a");
        return null;
      }
    }
    else
    {
      # merge
      $c = require $bot->botdir.'commands.inc';
      if (file_exists($d = $bot->datadir.'commands.inc')) {
        $c = array_merge($c, (require $d));
      }
    }
    # construct root items
    foreach ($c as $d => &$e)
    {
      if (($e = BotItem::init($bot, $d, $e)) === null)
      {
        $bot->logError("failed to initialize command: $d");
        return null;
      }
    }
    # store
    if (!$b)
    {
      $b = json_encode($c, JSON_UNESCAPED_UNICODE);
      $b && file_put_contents($a, $b);
    }
    # construct
    return new static([
      'bot'  => $bot,
      'tree' => $c,
      'map'  => self::createMap($c),
    ]);
  }
  static function createMap(array &$tree): array
  {
    $map = [];
    foreach ($tree as $a)
    {
      $map[$a->id] = $a;
      if ($a->items)
      {
        foreach (self::createMap($a->items) as $b) {
          $map[$b->id] = $b;
        }
      }
    }
    return $map;
  }
  # }}}
  function dump( # {{{
    int    $pad   = 0,
    string $color = 'cyan',
    ?array &$tree = null,
    array  &$indent = []
  ):string
  {
    # prepare
    !$tree && ($tree = $this->tree);
    $x = '';
    $i = 0;
    $j = count($tree);
    # compose tree items
    foreach ($tree as &$a)
    {
      # compose indent
      $pad && ($x .= str_repeat(' ', $pad));
      foreach ($indent as $b) {
        $x .= $b ? Bot::str_fg_color('│ ', $color) : '  ';
      }
      # compose item line
      $b  = (++$i === $j);
      $c  = Bot::str_fg_color(($b ? '└─' : '├─'), $color);
      $x .= $c.$a->name."\n";
      # recurse
      if ($a->items)
      {
        $indent[] = !$b;
        $x .= $this->dump($pad, $color, $a->items, $indent);
        array_pop($indent);
      }
    }
    # done
    return $x;
  }
  # }}}
}
class BotItem extends StaticInit implements \JsonSerializable {
  # {{{
  static function init(
    object $bot,
    string $name,
    array &$data,
    ?object $parent = null
  ):?self
  {
    # prepare
    $id   = $parent ? $parent->id.$name : $name;
    $type = isset($data['type']) ? ucfirst($data['type']) : 'Img';
    $type = '\\'.__NAMESPACE__.'\\BotItem'.$type;
    # initialize language texts
    # {{{
    # refine empty or single language
    if (!isset($data['text'])) {
      $data['text'] = ['en'=>[],'~'=>1];
    }
    elseif (!isset($data['text']['en'])) {
      $data['text'] = ['en'=>$data['text']];
    }
    # render emojis and captions
    if (!isset($data['text']['~']))
    {
      foreach ($data['text'] as &$a)
      {
        foreach ($a as &$b)
        {
          if (strpos($b, "\r") !== false) {
            $b = str_replace("\r\n", "\n", $b);
          }
          $b = $bot->tp->render($b, '{: :}', $bot::$EMOJI);
          $b = $bot->tp->render($b, '{! !}', $bot->buttons);
        }
      }
      unset($a, $b);
      $data['text']['~'] = 1;
    }
    # }}}
    # construct self
    $item = new static([
      'parent'    => $parent,
      'name'      => $name,
      'id'        => $id,
      'type'      => $type,
      'data'      => $data,
      'items'     => null,
      'config'    => [],
    ]);
    # construct items (recurse)
    if (isset($data['items']) && ($a = &$data['items']))
    {
      $item->items = [];
      foreach ($a as $b => &$c) {
        $item->items[] = self::init($bot, $b, $c, $item);
      }
      unset($a);
    }
    return $item;
  }
  function jsonSerialize(): array {
    return $this->data;
  }
  # }}}
}
class B2B {
  # {{{
  private
    $curl  = null,
    $id    = 0,# operator id
    $token = '';
  private static
    $url = 'https://int.apiforb2b.com/';
  public
    $error = '';
  # factory
  # {{{
  private function __construct($curl, $id)
  {
    $this->curl = $curl;
    $this->id   = $id;
  }
  public static function init($id)
  {
    # create curl instance
    if (!($curl = curl_init())) {
      return null;
    }
    # configure it
    curl_setopt_array($curl, [
      CURLOPT_RETURNTRANSFER => true, # result as a string
      CURLOPT_FORBID_REUSE   => true, # close after response
      #CURLOPT_POST           => true,
      CURLOPT_CONNECTTIMEOUT => 120,
      CURLOPT_TIMEOUT        => 120,
      #CURLOPT_VERBOSE        => true,
    ]);
    # construct
    return new B2B($curl, $id);
  }
  # }}}
  # methods
  public function getGameUrl($name) # {{{
  {
    return self::$url.'games/'.$name.
      '?operator_id='.$this->id.
      '&user_id=0'.
      '&auth_token='.
      '&currency=';
  }
  # }}}
  public function getGames() # {{{
  {
    # fetch remote data {{{
    # prepare
    $a = self::$url.'frontendsrv/apihandler.api?'.
      'cmd={"api":"ls-games-by-operator-id-get","operator_id":"'.$this->id.'"}';
    curl_setopt_array($this->curl, [
      CURLOPT_URL  => $a,
      CURLOPT_POST => false,
    ]);
    # send
    if (($a = curl_exec($this->curl)) === false)
    {
      $this->error = 'curl error #'.curl_errno($this->curl).': '.curl_error($this->curl);
      return null;
    }
    # decode
    if (($a = json_decode($a, true)) === null)
    {
      $this->error = 'json error #'.json_last_error().': '.json_last_error_msg();
      return null;
    }
    # check
    if (!$a['success'])
    {
      $this->error = print_r($a, true);
      return null;
    }
    $a = $a['locator'];
    # }}}
    # refine {{{
    $groups = [];
    $games  = [];
    foreach ($a['groups'] as $b)
    {
      # create group
      $i0 = $b['gr_id'];
      $groups[$i0] = [
        'id'   => $i0,
        'name' => $b['gr_title'],
        'list' => [],
      ];
      # iterate group games
      foreach ($b['games'] as $c)
      {
        # add identifier to group
        $i1 = $c['gm_bk_id'];
        $groups[$i0]['list'][] = $i1;
        # create game
        $games[$i1] = [
          'id'   => $i1,
          'name' => $c['gm_title'],
          'url'  => $c['gm_url'],
          'icon' => '',
          'logo' => '',
        ];
        # search for proper icon and logo
        foreach ($c['icons'] as $d)
        {
          if ($d['ic_w'] === 480 && $d['ic_h'] === 160) {
            $games[$i1]['icon'] = $d['ic_name'];
          }
          elseif ($d['ic_w'] === 640 && $d['ic_h'] === 360) {
            $games[$i1]['logo'] = $d['ic_name'];
          }
        }
      }
    }
    # sort by name?
    #sort($group_list, SORT_STRING);
    #sort($game_list, SORT_STRING);
    # }}}
    return [
      'id'     => uniqid(),
      'groups' => $groups,
      'games'  => $games,
    ];
  }
  # }}}
  public function getIconUrl($name) # {{{
  {
    return self::$url.'game/icons/'.$name;
  }
  # }}}
  # }}}
}
# }}}
# REQUEST/RESPONSE {{{
class BotRequestInput extends StaticInit {
  # {{{
  static function init(object $msg): ?self
  {
    if (!isset($msg->from)) {
      return null;
    }
    if (($text = isset($msg->text) ? $msg->text : '') &&
        ($text[0] === '/'))
    {
      return BotRequestCommand::init($msg);
    }
    return new static([
      'from' => $msg->from,
      'chat' => $msg->chat,
      'msg'  => $msg->message_id,
      'text' => $text,
    ]);
  }
  function reply(object $user)
  {
    /***
    static $types = ['form'];
    # prepare
    $conf = &$this->user->config;
    $lang = $this->user->lang;
    $chat = $this->user->chat;
    # check active roots
    if (!array_key_exists('/', $conf) ||
        !count($conf['/']))
    {
      return false;# NO ACTIVE ROOT
    }
    # determine active item's identifier
    $a = $conf['/'][0];
    if (!array_key_exists('_item', $conf[$a]) ||
        !($a = $conf[$a]['_item']))
    {
      return false;# NO ACTIVE ITEM
    }
    # get item and check if it accepts input
    if (!($item = $this->itemGet($a)) ||
        !in_array($item['type'], $types))
    {
      return false;# INPUT IS NOT ACCEPTED
    }
    # render item with the given input
    $res = '';
    if (!($item = $this->itemAttach($item, $res, $text)) || $res)
    {
      # report problems
      if ($res && is_string($res)) {
        $this->logError($res);
      }
      return false;
    }
    # update message that receives input
    $res = $item['root']['config']['_msg'];
    $this->itemUpdate($res, $item);
    # done
    return false;
    /***/
  }
  # }}}
}
class BotRequestCommand extends StaticInit {
  # {{{
  static function init(object $msg): self
  {
    return new static([
      'from' => $msg->from,
      'chat' => $msg->chat,
      'msg'  => $msg->message_id,
      'text' => $msg->text,
    ]);
  }
  function reply(object $user)
  {
    # prepare
    $bot  = $user->bot;
    $conf = $user->config;
    $text = $this->text;
    $chat = $this->chat;
    $lang = $user->lang;
    # handle special command
    switch ($text) {
    case '/reset':
      # {{{
      # check allowed
      if (!$user->isAdmin)
      {
        $this->log("access denied: $text");
        return 1;
      }
      # check replied
      if (!isset($this->msg->reply_to_message))
      {
        $this->log("$text: replied not found");
        return 1;
      }
      # get message and item ids
      $a = $this->msg->reply_to_message->message_id;
      if (!($b = $conf->getMessageItemId($a)))
      {
        $this->log("$text: message $a is not rooted");
        return 1;
      }
      $this->log("$text: $a => $b");
      # get and remove item
      ($item = $this->itemGet($b)) && $this->itemDetach($item, true);
      # compose navigation command
      $text = '/'.$b;
      # }}}
      break;
    case '/restart':
      # {{{
      if ($this->user->is_admin)
      {
        $this->log("$text\n");
        return 0;
      }
      return 1;
      # }}}
    }
    # handle item command
    if (!($a = $this->itemAttach($text, true)))
    {
      $a = [
        'chat_id' => $chat->id,
        'text'    => $this->messages[$lang][1].': '.$text,
      ];
      $this->logError('failed command: '.$text);
      if (!$this->api->send('sendMessage', $a)) {
        $this->logError($this->api->error);
      }
    }
    elseif (~$a && !$this->itemSend()) {# send new item
      $this->logError('failed to send item: '.$text);
    }
    return 1;
  }
  # }}}
}
class BotRequestCallback extends StaticInit {
  # {{{
  static function init($q)
  {
    if (!isset($q->from) || !isset($q->message)) {
      return null;
    }
    if (isset($q->game_short_name)) {
      #return BotRequestGame::init($q);
      return null;
    }
    if (!isset($q->data) || $q->data[0] !== '/') {
      #return BotRequestZap::init($q);
      return null;
    }
    return new static([
      'from'  => $q->from,
      'chat'  => $q->message->chat,
      'msg'   => $q->message->message_id,
      'query' => $q->id,
      'data'  => $q->data,
    ]);
  }
  function finit($user)
  {
    $id = $this->getMessageItemId($msg);
    $isRooted = !!$id;
    # attach item
    if (!($a = $this->itemAttach($text)))
    {
      # failed, item message should be removed/nullified
      $isRooted && $this->itemDetach();
      !$isRooted && $this->itemZap($msg);
      return ['text'=>$this->messages[$lang][2],'show_alert'=>true];
    }
    elseif ($a === -1) {# nop
      return [];
    }
    # get item and it's root configuration
    $item = &$this->item;
    $root = &$item['root']['config'];
    $msg1 = isset($root['_msg']) ? $root['_msg'] : 0;
    # determine if message is fresh
    $isFresh = ($msg1 &&
                ($a = time() - $root['_time']) >= 0 &&
                ($a < self::MESSAGE_LIFETIME));
    # determine if the item has re-activated input
    if (!($isNew = $item['isNew']))
    {
      if ($root && $item['isInputAccepted'])
      {
        # make sure it's the first from the start
        if (!array_key_exists('/', $userCfg) ||
            $userCfg['/'][0] !== $item['root']['id'])
        {
          $isNew = true;
        }
      }
    }
  }
  # }}}
  function reply() # {{{
  {
    # reply {{{
    if (!$isFresh || $isNew)
    {
      # recreate message
      $res = $this->itemSend();
    }
    else
    {
      # update message
      $res = $this->itemUpdate($msg, $item);
    }
    # nullify non-rooted message
    if (!$isRooted)
    {
      $this->log('unrooted, zap '.$msg);
      $this->itemZap($msg);
    }
    # success
    if ($res) {
      return [];
    }
    # failure
    return [
      'text' => $this->messages[$lang][2],
      'show_alert' => true,
    ];
    # }}}
    return [];
    # check message
    if (!isset($q->message) || !$q->message->message_id) {
      return -1;
    }
    # operate
    $answer = ['callback_query_id' => $q->id];
    $result = isset($q->game_short_name)
      ? $this->replyGameCallback($q)
      : (isset($q->data)
        ? $this->replyDataCallback($q)
        : null);
    # check
    if ($a === null) {# no traction, ignore
      return -1;
    }
    # complete
    if (!$this->api->send('answerCallbackQuery', array_merge($answer, $result))) {
      $this->logError($this->api->error);
    }
    return 1;
  }
  # }}}
}
class BotRequestGame extends StaticInit {
  # {{{
  static function init()
  {
    return new static([
    ]);
  }
  function reply()
  {
    /***
    if (isset($q->game_short_name) &&
        ($a = $q->game_short_name))
    {
      $this->log('game callback: '.$a);
      if ($a = $this->getGameUrl($a, $msg))
      {
        return [
          'url' => $a,
          'cache_time' => 0,
        ];
      }
      else
      {
        return [
          'text' => $this->messages[$lang][0],
          'show_alert' => true,
        ];
      }
    }
    /***/
  }
  # }}}
}
class BotRequestQuery extends StaticInit {
  # {{{
  static function init(object $q): ?self
  {
    return null;
    return new static([
    ]);
  }
  function reply()
  {
    $this->log('inline query!!!');
    /***
    $out = (string)rand();
    $results[] = [
      "type"  => "article",
      "id"    => $out,
      "title" => $out,
      "input_message_content" => [
        "message_text"             => $out,
        "disable_web_page_preview" => true
      ],
    ];
    $client->answerInlineQuery($u->inline_query->id, $results, 1, false);
    unset($results);
    /***/
  }
  # }}}
}
# }}}
# USER {{{
class BotUser extends StaticInit {
  # {{{
  static function init(object $bot, object $request): ?self
  {
    # prepare
    $from = $request->from;
    $chat = $request->chat;
    # determine name
    if ($isGroup = ($chat && $chat->type !== 'private'))
    {
      $a = (isset($chat->title) ? $chat->title : $chat->id);
      $b = (isset($chat->username) ? '@'.$chat->username : '');
      $name = $a.$b.'/';
    }
    else {
      $name = '';
    }
    $a = preg_replace('/[[:^print:]]/', '', trim($from->first_name));
    $b = (isset($from->username) ? '@'.$from->username : '');
    $name = $name.$a.$b;
    # determine admin flag
    # TODO: groups
    $isAdmin = in_array($from->id, $bot->opts->admins);
    # check masterbot access
    if ($bot->isMaster && !$isAdmin)
    {
      $bot->logWarn("access denied: $name");
      return null;
    }
    # determine language
    if (!($lang = $bot->opts->forceLang) &&
        (!isset($from->language_code) ||
         !($lang = $from->language_code) ||
         !isset($bot->messages[$lang])))
    {
      $lang = 'en';
    }
    # determine directory
    $dir = $isGroup ? '_'.$chat->id : $from->id;
    $dir = $bot->datadir.$dir.DIRECTORY_SEPARATOR;
    if (!file_exists($dir) && !@mkdir($dir))
    {
      $bot->logError("failed to create directory: $dir");
      return null;
    }
    # construct
    $user = new static([
      'bot'      => $bot,
      'request'  => $request,
      'id'       => $from->id,
      'name'     => $name,
      'isGroup'  => $isGroup,
      'isAdmin'  => $isAdmin,
      'lang'     => $lang,
      'dir'      => $dir,
      'config'   => null,
      'changed'  => false,
    ]);
    # set configuration
    if (!($user->config = BotUserConfig::init($user)))
    {
      $bot->logError("failed to attach user configuration: $name");
      return null;
    }
    # attach and complete
    return $bot->user = $u;
  }
  function finit(): void
  {
    # reply
    $res = $this->request->reply();
    # detach
    $this->config->finit();
    $this->bot->user = null;
    # complete
    return $res;
  }
  # }}}
}
class BotUserConfig extends StaticInit {
  # {{{
  static function init(object $user): ?self
  {
    # determine file path and aquire a forced lock
    $file = $user->dir.'config.json';
    if (!Bot::file_lock($file, true)) {
      return null;
    }
    # get contents
    if (!file_exists($file) ||
        !($data = file_get_contents($file)) ||
        !($data = json_decode($data, true)))
    {
      $data = [   # initial (empty)
        '/' => [],# root list (displayed item messages)
        '*' => [],# items [id=>config]
      ];
      $user->changed = true;
    }
    # create roots
    foreach ($data['/'] as &$a) {
      $item = BotItemMessage::init($a);
    }
    unset($a);
    # construct
    return new static([
      'user'  => $user,
      'file'  => $file,
      'data'  => $data,
    ]);
  }
  function finit(bool $unlock = true)
  {
    if ($this->user->changed)
    {
      file_put_contents($this->file, json_encode($this->data));
      $this->user->changed = false;
    }
    $unlock && Bot::file_unlock($this->file);
  }
  # }}}
  function getItemByMessageId($msgId) # {{{
  {
    foreach ($this->data['/'] as $msg)
    {
      if (isset($conf[$id]['_msg']) &&
          $conf[$id]['_msg'] === $msg)
      {
        return $conf[$id]['_item'];
      }
    }
    return '';
  }
  # }}}
}
class BotUserMessage extends StaticInit implements \JsonSerializable {
  # {{{
  static function init(object $user, array $o): self
  {
    return new static([
      'msg'  => $o[0],# message identifiers
      'hash' => $o[1],# message hashes
      'time' => $o[2],# creation timestamp (seconds)
      'item' => BotUserItem::init($user, $o[3]),# item
      'from' => BotUserItem::init($user, $o[4]),# origin item (injector)
    ]);
  }
  function jsonSerialize(): array {
    return [$this->msg,$this->hash,$this->time,$this->item,$this->from];
  }
  # }}}
}
class BotUserItem extends StaticInit implements \JsonSerializable {
  # {{{
  static function init(object $user, ?string $id): ?self
  {
    # check
    if (!$id) {
      return null;
    }
    # ...
  }
  function jsonSerialize(): string {
    return $this->id;
  }
  # }}}
}
# }}}
# ITEMS {{{
class BotItemImg {
}
class BotItemList {
  # data {{{
  static public $template = [
    'en' => # {{{
    '
page <b>{{page}}</b> of {{page_count}} ({{item_count}})
    ',
    # }}}
    'ru' => # {{{
    '
страница <b>{{page}}</b> из {{page_count}} ({{item_count}})
    ',
    # }}}
  ];
  # }}}
  static function render($bot, &$item, $func, $args) # {{{
  {
    # prepare {{{
    $conf = &$item['config'];
    $data = &$item['data'];
    $lang = $bot->user->lang;
    $text = &$item['text'][$lang];
    if (!isset($item['opts']) || !isset($item['markup'])) {
      return 0;
    }
    $opts  = $item['opts'];
    $rows  = isset($opts['rows']) ? $opts['rows'] : 8;
    $cols  = isset($opts['cols']) ? $opts['cols'] : 1;
    $flexy = isset($opts['flexy']) ? $opts['flexy'] : true;
    $size  = $rows * $cols;
    $count = $data ? count($data) : 0;
    # determine total page count
    if (!($total = intval(ceil($count / $size)))) {
      $total = 1;
    }
    # determine current page
    if (!isset($conf['page']))
    {
      $conf['page'] = $page = 0;
      $bot->user->changed = true;
    }
    elseif (($page = $conf['page']) >= $total)
    {
      $conf['page'] = $page = $total - 1;
      $bot->user->changed = true;
    }
    # determine prev/next pages
    $nextPage = ($page > 0) ? $page - 1 : $total - 1;
    $prevPage = ($page < $total - 1) ? $page + 1 : 0;
    # }}}
    # handle list operation {{{
    switch ($func) {
    case 'first':
      $page = 0;
      break;
    case 'last':
      $page = $total - 1;
      break;
    case 'prev':
      $page = $prevPage;
      break;
    case 'next':
      $page = $nextPage;
      break;
    #case 'add':
    case 'item':
      # check identifier
      if (!$args || !isset($args[0]))
      {
        $this->log('no arguments');
        return -1;
      }
      if (!($a = $args[0]) || !ctype_alnum($a) || strlen($a) > 32)
      {
        $this->log('incorrect identifier');
        return -1;
      }
      # get the item for the list
      $b = null;
      foreach ($data as &$c) {
        if ($c['id'] === $a) {$b = $c; break;}
      }
      unset($c);
      if (!$b)
      {
        $this->log('list item "'.$a.'" not found');
        break;# refresh the list
      }
      # check child
      if (isset($b['type']) && ($c = $b['type']) &&
          isset($item['items'][$c]))
      {
        # re-attach to the child
        $item = $item['items'][$c];
        return $bot->itemRender($item, 'list', $b);
      }
      return -1;
    }
    # }}}
    # set markup {{{
    if ($count)
    {
      # NON-EMPTY
      # sort list items
      $a = isset($opts['order']) ? $opts['order'] : 'id';
      $b = isset($opts['desc']) ? $opts['desc'] : false;
      if (!self::sort($data, $a, $b))
      {
        $bot->logError('failed to sort');
        return 0;
      }
      # extract records from the ordered data set
      $a = $page * $size;
      $d = array_slice($data, $a, $size);
      $e = count($d);
      # create list
      $mkup = [];
      for ($a = 0, $c = 0; $a < $rows; ++$a)
      {
        $mkup[$a] = [];
        for ($b = 0; $b < $cols; ++$b)
        {
          if ($c < $e)
          {
            $mkup[$a][$b] = [
              'text'=>$d[$c]['name'],
              'callback_data'=>'/'.$item['id'].'!item '.$d[$c]['id']
            ];
          }
          else {
            $mkup[$a][$b] = ['text'=>' ','callback_data'=>'!'];
          }
          $c++;
        }
        if ($flexy && $c >= $e) {
          break;
        }
      }
      # add controls
      $a = isset($item['markup']['.'])
        ? $item['markup']['.'] : [['_up']];
      foreach ($a as $b) {
        $mkup[] = $b;
      }
    }
    else
    {
      # EMPTY
      $mkup = isset($item['markup']['-'])
        ? $item['markup']['-']
        : [['_up']];
    }
    # determine control extras
    $a = $count <= $size;
    $a = [
      'prev' => ($a ? null : strval($prevPage)),
      'next' => ($a ? null : strval($nextPage)),
      'add'  => $bot->messages[$lang][4],
    ];
    # parse and set
    $item['inlineMarkup'] = $bot->itemInlineMarkup($item, $mkup, $text, $a);
    # }}}
    # set content {{{
    if ($count)
    {
      # NON-EMPTY
      $a = ($item['content'] ?: self::$template[$lang]);
      $item['textContent'] = $bot->tp->render($a, [
        'item_count'   => $count,
        'page'         => 1 + $page,
        'page_count'   => $total,
        'not_one_page' => ($total > 1),
      ]);
    }
    else
    {
      # EMPTY
      $item['textContent'] = isset($text['-'])
        ? $text['-'] : '';
    }
    # }}}
    # complete
    if ($page !== $conf['page'])
    {
      $conf['page'] = $page;
      $bot->user->changed = true;
    }
    return 1;
  }
  # }}}
  static function sort(&$list, $k, $desc = false) # {{{
  {
    if (!isset($list[0][$k])) {
      return false;
    }
    if (is_int($list[0][$k]))
    {
      # sort numbers
      $c = usort($list, function($a, $b) use ($k, $desc) {
        # check equal and resolve by identifier
        if ($a[$k] === $b[$k]) {
          return ($a['id'] > $b['id']) ? 1 : -1;
        }
        # operate
        return $desc
          ? (($a[$k] > $b[$k]) ? -1 :  1)
          : (($a[$k] > $b[$k]) ?  1 : -1);
      });
    }
    else
    {
      # sort strings
      $c = usort($list, function($a, $b) use ($k, $desc) {
        # check equal and resolve by identifier
        if (($c = strcmp($a[$k], $b[$k])) === 0) {
          return ($a['id'] > $b['id']) ? 1 : -1;
        }
        # operate
        return $desc
          ? (($c > 0) ? -1 :  1)
          : (($c > 0) ?  1 : -1);
      });
    }
    return $c;
  }
  # }}}
}
class BotItemForm {
  static function render($bot, &$item, $func, $args) # {{{
  {
    # prepare {{{
    # get current state and field index
    if (!array_key_exists('index', $conf))
    {
      $conf['index'] = 0;
      $conf['state'] = 0;
      $this->user->changed = true;
    }
    $index = $conf['index'];
    $state = $conf['state'];
    $stateInfo = array_key_exists('info', $conf)
      ? $conf['info']
      : [0, ''];
    # get language specific options list
    $options = [];
    if (array_key_exists('options', $item))
    {
      # static options
      $options = $item['options'];
      $options = array_key_exists($lang, $options)
        ? $options[$lang]
        : $options['en'];
    }
    elseif (false)
    {
      # dynamic options
      # TODO: ...
    }
    # determine fields count
    $count = count($item['fields']);
    # determine field name
    $indexField = ($state === 0)
      ? array_keys($item['fields'])[$index]
      : '';
    # determine count of empty required fields
    $emptyRequired = 0;
    foreach ($item['fields'] as $a => $b)
    {
      if (($b[0] & 1) && !array_key_exists($a, $data)) {
        ++$emptyRequired;
      }
    }
    # }}}
    # handle input {{{
    if ($inputLen)
    {
      switch ($state) {
      case 0:
        # execute input command {{{
        if ($inputLen === 1)
        {
          switch ($input) {
          case '-':
            # wipe current field value
            if (!array_key_exists($indexField, $data)) {
              $error = 1;return $item;# NOP
            }
            unset($data[$indexField]);
            $item['dataChanged'] = true;
            break 2;
          case '>':
            # advance to the next field but don't cycle around
            if (++$index === $count) {
              $error = 1;return $item;# NOP
            }
            break 2;
          case '<':
            # retreat to the previous field but don't cycle around
            if (--$index < 0) {
              $error = 1;return $item;# NOP
            }
            break 2;
          }
        }
        # }}}
        # set field value {{{
        # refine by type
        $a = $item['fields'][$indexField];
        switch ($a[1]) {
        case 'string':
          $input = substr($input, 0, $a[2]);
          break;
        case 'int':
          $input = intval(substr($input, 0, 16));
          if ($input < $a[2]) {
            $input = $a[2];
          }
          elseif ($input > $a[3]) {
            $input = $a[3];
          }
          break;
        case 'list':
          # check list index specified correctly [1..N]
          if (!ctype_digit($input) ||
              ($a = intval($input)) <= 0 ||
              $a > count($options[$indexField]))
          {
            $error = 1; return $item;# NOP
          }
          # select value from the list
          $input = $options[$indexField][$a];
          break;
        }
        # check not changed
        if (array_key_exists($indexField, $data) &&
            $data[$indexField] === $input)
        {
          $error = 1;return $item;# NOP
        }
        # update
        $data[$indexField] = $input;
        $item['dataChanged'] = true;
        # advance to the next field (if option specified)
        #if (array_key_exists('submitOnInput', $item) &&
        if (array_key_exists('submitOnInput', $item) &&
            $item['submitOnInput'] &&
            ++$index === $count)
        {
          # last field completes input,
          # change form state
          $state = 1;
        }
        # }}}
        break;
      case 2:
        # set final required field {{{
        # check first
        if ($emptyRequired !== 1)
        {
          # NOP, more than one empty required,
          # this is assumed as non-deterministic state,
          # no errors either..
          $error = 1;
          return $item;
        }
        # search field name
        foreach ($item['fields'] as $a => $b)
        {
          if (($b[0] & 1) && !array_key_exists($a, $data)) {
            break;
          }
        }
        # accept input and store data
        $data[$a] = $input;
        $item['dataChanged'] = true;
        # form is now filled (complete),
        # change its state
        $state = 1;
        $index = $count;
        # }}}
        break;
      }
    }
    # }}}
    # handle function {{{
    switch ($func) {
    case 'prev': # input index backward {{{
      if ($state !== 0) {
        $error = 1;return $item;
      }
      if (--$index < 0)
      {
        if (array_key_exists('inputMoveAround', $item) &&
            $item['inputMoveAround'])
        {
          $index = $count - 1;
        }
        else
        {
          $error = 1;
          return $item;
        }
      }
      break;
    # }}}
    case 'next': # input index forward {{{
      if ($state !== 0) {
        $error = 1;return $item;
      }
      if (++$index === $count)
      {
        if (array_key_exists('inputMoveAround', $item) &&
            $item['inputMoveAround'])
        {
          $index = 0;
        }
        else
        {
          $error = 1;
          return $item;
        }
      }
      break;
    # }}}
    case 'refresh': # form refresher {{{
      switch ($state) {
      case 1:
      case 4:
      case 5:
        # reset filled, failed and completed form states,
        # clear all non-persistent fields and
        # set input to the first one
        $a = -1;
        $b = 0;
        foreach ($item['fields'] as $c => $d)
        {
          if (!($d[0] & 2))
          {
            if (!~$a) {
              $a = $b;
            }
            if (array_key_exists($c, $data))
            {
              unset($data[$c]);
              $item['dataChanged'] = true;
            }
          }
          ++$b;
        }
        # set input index to the first non-persistent field and
        # reset completed form state
        if (~$a)
        {
          $index = $a;
          $state = 0;
          break;
        }
        # all the fields are persistent,
        # preserve data and find first required from the end
        $state = 0;
        $index = $count - 1;
        foreach (array_reverse($item['fields']) as $a => $b)
        {
          if ($b & 1) {
            break;
          }
          --$index;
        }
        break;
      case 2:
        # required field missing,
        # locate the index of the first empty required,
        $index = 0;
        foreach ($item['fields'] as $a => $b)
        {
          if (($b[0] & 1) && !array_key_exists($a, $data)) {
            break;
          }
          ++$index;
        }
        # check not found
        if ($index === $count)
        {
          # this is a rare case occurse when command structure changes and
          # don't cataches with previous state,
          # because of all the required are filled,
          # complete form input
          $state = 1;
        }
        else
        {
          # refreshing resets form into input state
          $state = 0;
        }
        break;
      case 3:
        # this is a positive waiting state,
        # refreshing doesn't do anything here
        # because it's the job of the item's task
        $error = 1;return $item;# NOP
      case 6:
        # TODO: errors display state
        $error = 1;return $item;# NOP for now
      }
      break;
    # }}}
    case 'select': # input selector {{{
      if ($state !== 0 || !$args ||
          ($a = intval($args[0])) < 0 ||
          ($a &&
            (!array_key_exists($indexField, $options) ||
            !($b = $options[$indexField]) ||
            $a > count($b))))
      {
        $this->logError("failed to select field value");
        $error = 1;
        return $item;
      }
      # check same value selected
      if ($a && array_key_exists($indexField, $data) &&
          $data[$indexField] === $b[$a])
      {
        # NOP when required
        if ($item['fields'][$indexField][0] & 1)
        {
          $error = 1;
          return $item;
        }
        # de-select (set empty)
        $a = 0;
      }
      # change data
      if ($a)
      {
        # correct integer type
        $data[$indexField] = ($item['fields'][$indexField][1] === 'int')
          ? intval($b[$a])
          : $b[$a];
      }
      elseif (array_key_exists($indexField, $data)) {
        unset($data[$indexField]);
      }
      $item['dataChanged'] = true;
      # advance field index (if option set)
      if (array_key_exists('submitOnSelect', $item) &&
          $item['submitOnSelect'])
      {
        ++$index;
      }
      break;
    # }}}
    case 'last': # submit input {{{
      if ($state !== 0 || $index === $count) {
        $error = 1; return $item;# NOP
      }
      # TODO: autofill
      # set final index value
      $index = $count;
      # check confirmation required
      if (array_key_exists('submitConfirm', $item) &&
          $item['submitConfirm'])
      {
        break;
      }
      # check any required fields miss
      if ($emptyRequired > 0) {
        break;
      }
      # continue to form submittion..
      $state = 1;
    # }}}
    case 'ok': # submit form {{{
      # check mode is correct
      if (!in_array($state, [1,4])) {
        $error = 1;return $item;# NOP guard
      }
      # create task
      if (!$this->taskAttach($item, $data, true))
      {
        $error = 1;
        return $item;
      }
      # change form state
      $state = 3;
      break;
    # }}}
    case 'progress': # form progress {{{
      if ($state !== 3 || !$args) {
        $error = 1;return $item;# NOP guard
      }
      $stateInfo[0] = intval($args[0]);
      break;
    # }}}
    case 'done': # form result {{{
      if (!in_array($state,[1,4,3]) || !$args)
      {
        $error = "incorrect state ($state) or no args";
        return $item;
      }
      $state = intval($args[0]) ? 5 : 4;
      $stateInfo = [1, base64_decode($args[1])];
      break;
    # }}}
    case 'first': # form reset {{{
      # make sure in the correct state
      if ($state < 4 && $state !== 2)
      {
        $error = 'form reset is only possible after completion or failure';
        return $item;
      }
      # wipe data
      $data = [];
      $item['dataChanged'] = true;
      # reset input index and state
      $state = $index = 0;
      break;
    # }}}
    case '':
      # SKIP
      break;
    default:
      # NOP error
      $this->logError("unknown form function: $func");
      $error = $this->messages[$lang][2];
      return $item;
    }
    # }}}
    # sync changes {{{
    if ($index !== $conf['index'] ||
        $state !== $conf['state'])
    {
      # set flag
      $this->user->changed = true;
      # check input completion state
      if ($state === 0 && $index === $count)
      {
        # resolve to completed (optimistic)
        $state = 1;
        # check all required fields are set
        foreach ($item['fields'] as $a => $b)
        {
          if (($b[0] & 1) &&
              !array_key_exists($a, $data))
          {
            # required missing!
            $state = 2;
            break;
          }
        }
      }
      # reset state info
      if ($state < 4) {
        $stateInfo = [0,''];
      }
    }
    # re-determine current/prev/next field names
    $indexField = '';
    $nextField  = $prevField = '';
    if ($state === 0)
    {
      $a = array_keys($item['fields']);
      $indexField = $a[$index];
      # determine localized captions
      if ($count > 1)
      {
        $nextField  = ($index + 1 < $count)
          ? $a[$index + 1]
          : $a[0];
        $prevField  = ($index - 1 >= 0)
          ? $a[$index - 1]
          : $a[$count - 1];
        $nextField = $text[$nextField];
        $prevField = $text[$prevField];
      }
    }
    # determine last field flag
    $indexIsLast = ($index === $count - 1);
    # re-determine count of empty required fields
    if ($item['dataChanged'])
    {
      $emptyRequired = 0;
      foreach ($item['fields'] as $a => $b)
      {
        if (($b[0] & 1) && !array_key_exists($a, $data)) {
          ++$emptyRequired;
        }
      }
    }
    # }}}
    # render markup {{{
    $mkup = [];
    # compose input selector group {{{
    if ($state === 0 && $indexField &&
        array_key_exists($indexField, $options))
    {
      # get value options and column count
      $b = $options[$indexField];
      $c = $b[0];
      $b = array_slice($b, 1);
      # determine row count
      $d = ceil(count($b) / $c);
      # create select group
      for ($i = 0, $k = 0; $i < $d; ++$i)
      {
        # create row
        for ($j = 0, $e = []; $j < $c; ++$j)
        {
          # create option
          if ($k < count($b))
          {
            # determine current or standard
            $n = (array_key_exists($indexField, $data) &&
                  !strcmp($data[$indexField], $b[$k]))
              ? 'select1'
              : 'select0';
            # parse it and add to row
            $e[] = [
              'text' => $this->tp->render(
                $this->buttons[$n],
                [
                  'index' => ($k + 1),
                  'text'  => $b[$k],
                ]
              ),
              'callback_data' => '/'.$item['id'].'!select '.($k + 1)
            ];
          }
          else
          {
            # empty pad
            $e[] = ['text'=>' ','callback_data'=>'!'];
          }
          ++$k;
        }
        # add row
        $mkup[] = $e;
      }
    }
    # }}}
    # compose input value display {{{
    while ($state === 0 && $indexField)
    {
      # check empty
      if (!array_key_exists($indexField, $data))
      {
        # display empty bar
        $mkup[] = [['text'=>' ','callback_data'=>'!']];
        break;
      }
      # check options list
      $a = $data[$indexField];
      if (array_key_exists($indexField, $options))
      {
        # check value is already shown in the list
        $b = array_slice($options[$indexField], 1);
        if (!is_string($a) && is_string($b[0])) {
          $a = strval($a);# avoid comparison problem
        }
        if (array_search($a, $b, true) !== false)
        {
          # display empty bar
          $mkup[] = [['text'=>' ','callback_data'=>'!']];
          break;
        }
      }
      # display non-empty value
      $b = $this->tp->render($this->buttons['select1'], ['text'=>$a]);
      $a = !($item['fields'][$indexField][0] & 1)
        ? '/'.$item['id'].'!select 0' # with de-selector
        : '!';
      $mkup[] = [['text'=>$b,'callback_data'=>$a]];
      break;
    }
    # }}}
    # compose controls {{{
    # select markup
    $a = $item['markup'];
    $b = 'S'.$state;
    if (array_key_exists($b, $a)) {
      $c = $a[$b];
    }
    elseif ($state === 4 || $state === 6) {
      $c = $a['S1'];
    }
    elseif ($state === 5) {
      $c = $a['S2'];
    }
    else {
      $c = [['_up']];
    }
    # determine controls
    $d = [
      'first' => $this->messages[$lang][15],# reset
    ];
    $d['refresh'] = ($state === 5)
      ? $this->messages[$lang][18] # repeat
      : $this->messages[$lang][14];# refresh
    if ($state === 0 && $indexField)
    {
      $d['prev'] = $index
        ? $this->messages[$lang][16]
        : '';
      if ($indexIsLast)
      {
        if ($emptyRequired &&
            (!array_key_exists('noRequiredSubmit', $item) ||
              $item['noRequiredSubmit']))
        {
          # display empty bar
          $d['last'] = '';
        }
        else
        {
          # standard
          $d['last'] = $this->messages[$lang][12];
        }
      }
      elseif (($item['fields'][$indexField][0] & 1) &&
              !array_key_exists($indexField, $data) &&
              (!array_key_exists('noRequiredSkip', $item) ||
                $item['noRequiredSkip']))
      {
        # non-last, empty but required,
        # display empty bar
        $d['next'] = '';
      }
      else
      {
        # standard
        $d['next'] = $this->messages[$lang][17];
      }
    }
    # render
    $c = $this->itemInlineMarkup($item, $c, $text, $d);
    foreach ($c as $a) {
      $mkup[] = $a;
    }
    # }}}
    $item['inlineMarkup'] = $mkup;
    # }}}
    # render content {{{
    # compose input fields
    $fields = [];
    $i = 0;
    foreach ($item['fields'] as $a => $b)
    {
      # determine type hint
      switch ($b[1]) {
      case 'string':
        $c = $this->messages[$lang][7];
        $c = $this->tp->render($c, ['max'=>$b[2]]);
        break;
      case 'int':
        $c = $this->messages[$lang][8];
        $c = $this->tp->render($c, ['min'=>$b[2],'max'=>$b[3]]);
        break;
      case 'list':
        $c = $this->messages[$lang][13];
        break;
      default:
        $c = '.';
        break;
      }
      # determine value
      $d = array_key_exists($a, $data)
        ? $data[$a]
        : '';
      # add form field descriptor
      $fields[] = [
        'name' => (array_key_exists($a, $text)
          ? $text[$a]
          : $a),
        'value'    => $d,
        'valueLen' => strlen($d),
        'before'   => ($index > $i),
        'current'  => ($index === $i),
        'after'    => ($index < $i),
        'required' => ($b[0] & 1),
        'hint'     => $c,
      ];
      $i++;
    }
    # get custom or common template
    $a = array_key_exists('*', $text)
      ? $text['*']
      : $this->messages[$lang][6];
    # get description
    $b = array_key_exists('.', $text)
      ? preg_replace('/\n\s*/m', ' ', trim($text['.']))
      : '';
    # compose full text
    $item['textContent'] = $this->render_content($a, [
      'desc'   => $b,
      'fields' => $fields,
      'info'   => $stateInfo,
      's0' => ($state === 0),
      's1' => ($state === 1),
      's2' => ($state === 2),
      's3' => ($state === 3),
      's4' => ($state === 4),
      's5' => ($state === 5),
      's6' => ($state === 6),
      's1s3' => ($state === 1 || $state === 3),
      's3s4' => ($state === 3 || $state === 4),
      's3s5' => ($state === 3 || $state === 5),
      's3s4s5' => ($state === 3 || $state === 4 || $state === 5),
    ]);
    # }}}
    # set {{{
    $conf['index']  = $index;
    $conf['state']  = $state;
    $conf['info']   = $stateInfo;
    $item['isInputAccepted'] = (
      ($state === 0) ||
      ($state === 2 && $emptyRequired === 1)
    );
    # }}}
  }
  # }}}
}
class BotItemGame {
  # {{{
  static function handle($bot, $plan)
  {
    # prepare {{{
    # check data
    if (!$data || !($count = count($data)))
    {
      $error = $bot->messages[$lang][0];
      return $item;
    }
    # determine game identifier
    if (!$func && $args)
    {
      # specified
      if (($id = $args[0]) === '-1') {
        $id = array_rand($data, 1);# random
      }
    }
    else
    {
      # previous
      $id = array_key_exists('id', $conf)
        ? $conf['id']
        : '';
    }
    # get game
    if (!array_key_exists($id, $data))
    {
      $this->log('game not found');
      $error = $bot->messages[$lang][2];
      return $item;
    }
    $data = $data[$id];
    # get game icon
    $a = $this->data['config']['created'];
    if ($isCreated = in_array($id, $a))
    {
      $icon_id = '';
      $icon = null;
    }
    else
    {
      ###
      # game icon is only related to multigame,
      # it may include instructions and full-size logo
      # for admin to create the game with @botfather
      # also, when remote icon is not provided,
      # it will be auto-generated (as a title block)
      ###
      if ($icon = $data['icon'])
      {
        # get stored file_id or
        # compose remote url for upload
        $icon_id = 'game-'.$id.'-icon';
        $icon = array_key_exists($icon_id, $this->fids)
          ? $this->fids[$icon_id]
          : $this->b2b->getIconUrl($icon);
      }
      else
      {
        # get stored file_id or
        # generate temporary icon file
        $icon_id = 'game-'.$id.'-name';
        if (array_key_exists($icon_id, $this->fids)) {
          $icon = $this->fids[$icon_id];
        }
        elseif (!($icon = $this->imageTitle($data['name'])))
        {
          $error = $bot->messages[$lang][2];
          return $item;
        }
      }
    }
    # get favorites
    $a = 'fav_'.$item['data'];
    $fav_data = null;
    if (array_key_exists($a, $this->data)) {
      $fav_data = &$this->data[$a];
    }
    # }}}
    # handle function {{{
    switch ($func) {
    case 'fav_on':
      if ($fav_data !== null && !array_key_exists($data['id'], $fav_data)) {
        $fav_data[$data['id']] = $data;
      }
      break;
    case 'fav_off':
      if ($fav_data !== null && array_key_exists($data['id'], $fav_data)) {
        unset($fav_data[$data['id']]);
      }
      break;
    }
    # }}}
    # check {{{
    if ($page !== $conf['page'])
    {
      $this->user->changed = true;
    }
    # }}}
    # determine markup {{{
    /***
    # set favorite control
    $a = ($fav_data === null)
      ? ''
      : '_fav_'.(array_key_exists($data['id'], $fav_data)
        ? 'off'
        : 'on');
    $this->setMarkupItem($item['markup'], '_fav', $a);
    /***/
    # create
    $mkup = $this->itemInlineMarkup(
      $item, $item['markup'], $text
    );
    # }}}
    # set {{{
    $conf['id']         = $id;
    $data['icon_id']    = $icon_id;
    $data['icon']       = $icon;
    $data['is_created'] = $isCreated;
    $item['data']       = &$data;
    # }}}
  }
  private function editMarkup($markup, $msg) # {{{
  {
    $a = $this->api->send('editMessageReplyMarkup', [
      'chat_id'      => $this->user->chat->id,
      'message_id'   => $msg,
      'reply_markup' => $markup,
    ]);
    if (!$a || $a === true)
    {
      $this->log($this->api->error);
      return -1;
    }
    return $a->result->message_id;
  }
  # }}}
  private function sendGame(&$item) # {{{
  {
    # check variant
    if (($game = $item['data'])['is_created'])
    {
      # send created game
      $this->log('game: '.$game['name']);
      $icon = 0;
      $res = $this->api->send('sendGame', [
        'chat_id'              => $this->user->chat->id,
        'game_short_name'      => 'a'.$game['id'],
        'disable_notification' => true,
        'reply_markup'         => $item['markup'],
      ]);
    }
    else
    {
      # send multigame
      $this->log('multigame: '.$game['name']);
      $icon = $this->sendImage($game['icon_id'], $game['icon'], '', '');
      if (!$icon) {
        return 0;
      }
      $res = $this->api->send('sendGame', [
        'chat_id'              => $this->user->chat->id,
        'game_short_name'      => 'multigame',
        'disable_notification' => true,
        'reply_markup'         => $item['markup'],
      ]);
    }
    # check result
    if (!$res)
    {
      $this->log($this->api->error);
      return 0;
    }
    # remove previous icon
    $conf = &$item['config'];
    if (array_key_exists('icon', $conf) && $conf['icon'])
    {
      $a = $this->api->send('deleteMessage', [
        'chat_id'    => $this->user->chat->id,
        'message_id' => $conf['icon'],
      ]);
      if (!$a) {
        $this->log($this->api->error);
      }
    }
    # update config
    $conf['icon'] = $icon;
    # done
    return $res->result->message_id;
  }
  # }}}
  private function getGameUrl($name, $msg) # {{{
  {
    # get data
    if (!($data = $this->data['games']) || !count($data)) {
      return '';
    }
    # determine game identifier
    if ($name === 'multigame')
    {
      # multigame
      # get identifier from the game command configuration
      $id = $this->user->config;
      if (!array_key_exists('game', $id) ||
          !($id = $id['game']) ||
          !array_key_exists('id', $id) ||
          !($id = intval($id['id'])))
      {
        return '';
      }
      # check
      if ($id === -1)
      {
        # randomize?
        return '';
      }
      $id = strval($id);
      if (!$msg)
      {
        $this->log('TODO: REPOSTED GAME CALLBACK');
      }
    }
    else
    {
      # created game (name includes identifier)
      $id = substr($name, 1);
    }
    # complete
    return array_key_exists($id, $data)
      ? $this->b2b->getGameUrl($data[$id]['url'])
      : '';
  }
  # }}}
  private function getRefList($file, &$list) # {{{
  {
    ###
    ###
    # TODO: load favorite games list
    #$a = $dir.'fav_games.json';
    #$this->data['fav_games'] = $this->getRefList($a, $this->data['games']);
    # check
    if (!$list || !file_exists($file)) {
      return [];
    }
    # load identifiers and
    # create reference list
    $a = json_decode(file_get_contents($file), true);
    $b = [];
    foreach ($a as $c)
    {
      if (array_key_exists($c, $list)) {
        $b[$c] = &$list[$c];
      }
    }
    # done
    return $b;
  }
  # }}}
  private function setRefList($file, &$list) # {{{
  {
    if ($list)
    {
      # collect identifiers
      $a = [];
      foreach ($list as $b) {
        $a[] = $b['id'];
      }
      # store
      if (file_put_contents($file, json_encode($a)) === false)
      {
        $this->log('file_put_contents('.$a.') failed');
        return false;
      }
      return true;
    }
    return file_exists($file)
      ? unlink($file) : true;
  }
  # }}}
  private function setDataConfig() # {{{
  {
    $a = $this->dir.'games-config.json';
    $b = json_encode($this->data['config']);
    if (file_put_contents($a, $b) === false)
    {
      $this->log('file_put_contents('.$a.') failed');
      return false;
    }
    return true;
  }
  # }}}
  private function loadData() # {{{
  {
    # check stored
    $file = $this->dir.'games.json';
    if (file_exists($file))
    {
      # load from cache
      if (!($a = file_get_contents($file)) ||
          !($a = json_decode($a, true)))
      {
        $this->log('failed to load '.$file);
        return null;
      }
      # load configuration
      $file = $this->dir.'games-config.json';
      if (!file_exists($file) ||
          !($b = file_get_contents($file)) ||
          !($b = json_decode($b, true)))
      {
        $this->log('failed to load '.$file);
        return null;
      }
    }
    else
    {
      # load from remote
      if (!($a = $this->b2b->getGames()))
      {
        $this->log($this->b2b->error);
        return null;
      }
      # store
      file_put_contents($file, json_encode($a));
      # create initial config
      $file = $this->dir.'games-config.json';
      $b = [
        'created' => [],# non-multigames
      ];
      file_put_contents($file, json_encode($b));
    }
    # set configuration
    $a['config'] = $b;
    # done
    return $a;
  }
  # }}}
  # }}}
}
class BotItemCaptcha {
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
