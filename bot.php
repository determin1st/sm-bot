<?php
# TODO: separate data fetchers as well as task handlers
# TODO: upgrade list options, add dynamics
# TODO: language switch form
# TODO: input bob
# TODO: advanced image renderer (Fortune algorithm?)
namespace SM;
class Bot {
  # data {{{
  public
    $opts     = [     # default options
      'admins'     => [],
      'colors'     => [
        [240,248,255],# darkslateblue (background)
        [72,61,139],  # aliceblue (foreground)
      ],
      'debug_task' => false,
      'debuglog'   => true,
      'file_id'    => true,
      'force_lang' => '',
      'sfx'        => true,
    ],
    $id       = '',   # telegram bot identifier
    $api      = null, # telegram api instance
    $b2b      = null, # bot's data api instance
    $dir      = '',   # bot's data directory
    $errorlog = '',   # bot's ERROR.log
    $accesslog = '',  # bot's ACCESS.log
    $fontsdir = '',   # bot's fonts directory
    $commands = null, # command items tree
    $item     = null, # currently rendered block item
    $user     = null; # current user of the bot
  private
    $fids     = null; # common [file=>id] map
  public static
    $MSG_EXPIRE_TIME = 48*60*60,# telegram's default
    $WIN_OS   = true, # Windows OS environment
    $IS_TASK  = false,# tasks run without STDOUT/STDERR
    $inc      = '',   # includes directory
    $datadir  = '',   # common data directory
    $imgdir   = '',   # common images directory
    $tp       = null, # template parser (mustache)
    $messages = null, # bot service messages
    $buttons  = null; # common button captions
  # }}}
  # initializer {{{
  private function __construct() {}
  public static function init($id)
  {
    # initialize once {{{
    if (!self::$inc)
    {
      # set base directories
      self::$inc     = __DIR__.DIRECTORY_SEPARATOR.'inc'.DIRECTORY_SEPARATOR;
      self::$datadir = __DIR__.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR;
      self::$imgdir  = self::$inc.'img'.DIRECTORY_SEPARATOR;
      # load mustache parser
      if (!class_exists('Mustache_Engine')) {
        require(self::$inc.'mustache.php');
      }
      # create parser instance
      self::$tp = $tp = new Mustache_Engine([
        'charset' => 'UTF-8',
        'escape'  => (function($v) {return $v;}),
      ]);
      # load messages
      $a = require(self::$inc.'messages.inc');
      foreach ($a as &$b)
      {
        foreach ($b as &$c) {
          $c = $tp->render($c, BotApi::$emoji, '{: :}');
        }
      }
      unset($b, $c);
      self::$messages = $a;
      # load button captions
      $a = require(self::$inc.'buttons.inc');
      foreach ($a as $b => &$c) {
        $c = $tp->render($c, BotApi::$emoji, '{: :}');
      }
      unset($b, $c);
      self::$buttons = $a;
      # determine OS
      self::$WIN_OS = (
        defined('PHP_OS_FAMILY') &&
        strncasecmp(PHP_OS_FAMILY, 'WIN', 3) === 0
      );
    }
    # }}}
    # construct {{{
    # locate bot directory
    $dir = self::$datadir.$id;
    if (!file_exists($dir))
    {
      echo 'BOT DIRECTORY NOT FOUND';
      return null;
    }
    $dir = $dir.DIRECTORY_SEPARATOR;
    # load options
    $a = $dir.'opts.json';
    if (!file_exists($a) ||
        !($a = file_get_contents($a)) ||
        !($a = json_decode($a, true)) ||
        !is_array($a) ||
        !array_key_exists('token', $a) ||
        !($token = $a['token']))
    {
      echo 'BOT DIRECTORY NOT INITIALIZED';
      return null;
    }
    # match bot identifier
    if (strncmp($token, $id, strlen($id)) !== 0)
    {
      echo 'BOT IDENTIFIER MISMATCH';
      return null;
    }
    # create instance
    $bot = new Bot();
    $bot->dir = $dir;
    $bot->errorlog = $dir.'ERROR.log';
    $bot->accesslog = $dir.'ACCESS.log';
    $bot->fontsdir = file_exists($dir.'fonts')
      ? $dir.'fonts'.DIRECTORY_SEPARATOR
      : self::$inc.'fonts'.DIRECTORY_SEPARATOR;
    $bot->opts = array_merge($bot->opts, $a);
    $bot->id = $id;
    # create api instance
    if (!($bot->api = BotApi::init($token)))
    {
      echo 'BOT API FAILED';
      return null;
    }
    # create helper api instance
    if (!($bot->b2b = B2B::init(0)))
    {
      echo 'BOT HELPER API FAILED';
      return null;
    }
    # load and merge commands
    $a = 'commands.inc';
    $b = include(self::$inc.$a);# common
    if (file_exists($bot->dir.$a)) {
      $b = array_merge($b, include($bot->dir.$a));# specific
    }
    $bot->commands = $bot->itemAssemble($b);
    # load file_id map
    $a = $bot->dir.'file_id.json';
    $bot->fids = (file_exists($a) && $bot->opts['file_id'])
      ? json_decode(file_get_contents($a), true)
      : [];
    # done
    return $bot;
    # }}}
  }
  # }}}
  # api {{{
  public static function command($a) # {{{
  {
    # prepare
    ini_set('html_errors', 0);
    ini_set('implicit_flush', 1);
    set_time_limit(0);
    set_error_handler(function($no, $msg, $file, $line) {
      if (error_reporting() !== 0)
      {
        # any, which wasn't suppressed with @ operator
        $msg = "exception($no) in file($line): $file\n$msg\n";
        throw new \Exception($msg, $no);
        return true;
      }
      return false;
    });
    # check
    switch ($a[0]) {
    case 'getUpdates':
      # create bot instance
      if (!($b = self::init($a[1]))) {
        break;
      }
      # enter getUpdates loop
      cli_set_process_title('sm-bot.getUpdates.'.$b->id);
      # complete according to the loop logic
      return $b->loop($a[2]);
      ###
    case 'task':
    case 'progress':
      # signal successful start and
      # set proper mode to avoid STDOUT/ERR output,
      # which will terminate process intantly
      echo "TASK STARTED\n";
      self::$IS_TASK = true;
      # load task plan and create bot instance
      if (!file_exists($a[1]) ||
          !($plan = file_get_contents($a[1])) ||
          !($plan = json_decode($plan, true)) ||
          !is_array($plan) ||
          !array_key_exists('id', $plan) ||
          !($plan['id'] === $a[2]) ||
          !($b = self::init($plan['bot'])))
      {
        break;
      }
      # register task unlocker
      register_shutdown_function(function($file) {
        if (file_exists($file)) {
          unlink($file);
        }
      }, $a[1]);
      # attach bot's user
      $b->user = (object)$plan['user'];
      $b->user->chat = (object)$b->user->chat;
      # operate
      try
      {
        switch ($a[0]) {
        case 'task':
          if (!$b->itemTaskWork($plan)) {
            throw new Exception('TASK FAILED #'.$plan['id'].': '.$plan['item']);
          }
          break;
        case 'progress':
          if (!$b->itemTaskProgress($plan, $a[1])) {
            throw new Exception('PROGRESS FAILED #'.$plan['id'].': '.$plan['item']);
          }
          break;
        }
      }
      catch (\Exception $e) {
        $b->logException($e);# recorded
      }
      break;
      ###
    default:
      # incorrect syntax, unknown command
      break;
    }
    # complete
    restore_error_handler();
    return false;# negative to avoid console loops
  }
  # }}}
  public static function webhook() # {{{
  {
  }
  # }}}
  # }}}
  # replier {{{
  private function loop($timeout) # {{{
  {
    # set graceful loop termination
    self::$WIN_OS && sapi_windows_set_ctrl_handler(function ($i) {
      exit(1);
    });
    # prepare
    $this->log('sm-bot #'.$this->id.' started');
    $offset = 0;
    $fails  = 0;
    $cycles = 0;
    $jobs   = 0;
    # loop forever
    while ($fails < 5)
    {
      # get updates (long polling)
      $a = $this->api->send('getUpdates', [
        'offset'  => $offset,
        'timeout' => $timeout,
      ]);
      # check result
      if (!$a)
      {
        $this->log($this->api->error);
        $fails++;
        continue;
      }
      # reset counter
      $fails = 0;
      # operate
      foreach ($a->result as $b)
      {
        $offset = $b->update_id + 1;
        if (!$this->reply($b))
        {
          # save offset
          $this->api->send('getUpdates', [
            'offset'  => $offset,
            'timeout' => 0,
            'limit'   => 1,
          ]);
          # terminate
          $this->log("restart\n");
          return true;
        }
        ++$jobs;
      }
      if (++$cycles > 100)
      {
        $this->log('100 cycles, '.$jobs.' updates');
        $cycles = $jobs = 0;
      }
    }
    # terminate
    $this->log("shutdown\n");
    return false;
  }
  # }}}
  private function reply($u) # {{{
  {
    $result = true;
    if ($this->userAttach($u))
    {
      $noWrite = false;
      try
      {
        if (isset($u->callback_query))
        {
          # {{{
          $u = $u->callback_query;
          if (($a = $this->replyCallback($u)) === null)
          {
            # negative result with empty reply (will RESTART the loop)
            $a = ['callback_query_id' => $u->id];
            $result = false;
          }
          else
          {
            # positive result with custom reply (may be empty)
            $a['callback_query_id'] = $u->id;
          }
          if (!$this->api->send('answerCallbackQuery', $a)) {
            $this->log($this->api->error);
          }
          # }}}
        }
        elseif (isset($u->inline_query))
        {
          # {{{
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
          # }}}
        }
        elseif (isset($u->message) &&
                ($u = $u->message) &&
                isset($u->text))
        {
          # {{{
          # handle user command or input
          if ($a = strlen($u->text))
          {
            $a = ($u->text[0] === '/')
              ? $this->replyCommand($u->text)
              : $this->replyInput($u->text);
          }
          # wipe unhandled
          if (!$a)
          {
            $a = $this->api->send('deleteMessage', [
              'chat_id'    => $u->chat->id,
              'message_id' => $u->message_id,
            ]);
            if (!$a) {
              $this->log($this->api->error);
            }
          }
          # }}}
        }
      }
      catch (\Exception $e)
      {
        $this->logException($e);
        $noWrite = true;
      }
      $this->userDetach($noWrite);
    }
    return $result;
  }
  # }}}
  private function replyCommand($text) # {{{
  {
    # prepare {{{
    $lang = $this->user->lang;
    $chat = $this->user->chat;
    # }}}
    # reply {{{
    # generally, a command creates new message for the item,
    # because of that, rendering of the item should go with creation hint (flag)
    $res  = '';
    $item = $this->itemAttach($text, $res, '', true);
    if (!$item || $res)
    {
      if ($res && is_string($res))
      {
        $this->log($res);
        $a = $this->api->send('sendMessage', [
          'chat_id' => $chat->id,
          'text'    => self::$messages[$lang][1].': '.$text,
        ]);
        if (!$a) {
          $this->log($this->api->error);
        }
      }
      return false;
    }
    # create new or re-create message
    $res = $this->itemSend($item);
    # update user
    if ($res && ~$res) {
      $this->userUpdate($item, $res);
    }
    # }}}
    # complete {{{
    # report render failure to the user,
    # it may help in debugging later problems
    if (!$res)
    {
      $this->log('command failed');
      $a = $this->api->send('sendMessage', [
        'chat_id' => $chat->id,
        'text'    => self::$messages[$lang][2].': '.$text,
      ]);
      if (!$a) {
        $this->log($this->api->error);
      }
    }
    # negative, wipes user input
    return false;
    # }}}
  }
  # }}}
  private function replyInput($text) # {{{
  {
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
    $res = $this->itemUpdate($res, $item);
    # update user
    if ($res && ~$res) {
      $this->userUpdate($item, $res);
    }
    # done
    return false;
  }
  # }}}
  private function replyCallback($q) # {{{
  {
    # prepare {{{
    if (!isset($q->message) ||
        !($msg = $q->message) ||
        !($msg = $msg->message_id))
    {
      return [];
    }
    $lang = $this->user->lang;
    $userCfg = &$this->user->config;
    # handle game callback first
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
          'text' => self::$messages[$lang][0],
          'show_alert' => true,
        ];
      }
    }
    # check data (unified command)
    if (!isset($q->data) ||
        !($text = $q->data) ||
        !($text[0] === '/'))
    {
      return [];# no operation, skip
    }
    # }}}
    # reply {{{
    # determine if the message has active root,
    # this should be done before item rendering,
    # because the message may be detached
    $isRooted = false;
    if (array_key_exists('/', $userCfg))
    {
      foreach ($userCfg['/'] as $b)
      {
        if (array_key_exists('_msg', $userCfg[$b]) &&
            $userCfg[$b]['_msg'] === $msg)
        {
          $isRooted = true;
          break;
        }
      }
    }
    # render item
    $error = '';
    if (!($item = $this->itemAttach($text, $error)))
    {
      # command or path doesn't exist,
      # message contains incorrect markup and
      # should be terminated
      $this->itemZap($msg);
      return null;# restart loop
    }
    # check if message belongs to the item's root
    $root = $item['root']['config'];
    if (!array_key_exists('_msg', $root) ||
        $root['_msg'] !== $msg)
    {
      $root = null;
    }
    # check the result
    if ($error)
    {
      # nullify non-rooted message
      if (!$isRooted) {
        $this->itemZap($msg);
      }
      # display error if it's specified and
      # message is a part of the tree, otherwise,
      # check for exit code or skip action
      return is_string($error)
        ? ['text'=>$error,'show_alert'=>true]
        : (~$error ? [] : null);
    }
    # determine if item is the same,
    # which means it updates itself
    $isSameItem = ($root && $root['_item'] === $item['id']);
    # determine if the message is fresh (not outdated)
    $isFresh = ($root &&
                array_key_exists('_time', $root) &&
                ($a = time() - $root['_time']) >= 0 &&
                ($a < self::$MSG_EXPIRE_TIME));
    # determine if the item has re-activated input
    $isReactivated = false;
    if ($root && $item['isInputAccepted'])
    {
      # make sure it's the first from the start
      if (!array_key_exists('/', $userCfg) ||
          $userCfg['/'][0] !== $item['root']['id'])
      {
        $isReactivated = true;
      }
    }
    # update or create/re-create message
    $res = ($isFresh && !$isReactivated)
      ? $this->itemUpdate($msg, $item, !$isSameItem)
      : $this->itemSend($item);
    # }}}
    # complete {{{
    # update user
    if ($res && ~$res) {
      $this->userUpdate($item, $res);
    }
    # nullify non-rooted message
    if (!$isRooted)
    {
      $this->log('is unrooted! '.$msg);
      $this->itemZap($msg);
    }
    # success
    if ($res) {
      return [];
    }
    # failure
    $this->log('callback failed');
    return [
      'text' => self::$messages[$lang][2],
      'show_alert' => true,
    ];
    # }}}
  }
  # }}}
  # }}}
  # renderer {{{
  public function itemAttach($item, &$error, $input = '', $new = false)
  {
    # prepare {{{
    # check
    if ($inputLen = strlen($input))
    {
      # set input parameters
      $id   = $item['id'];
      $func = '';
      $args = null;
      # report
      $this->log('input: '.$id);
    }
    else
    {
      # check string
      if (!$item || !is_string($item))
      {
        $this->logError('invalid command, not a string');
        return null;
      }
      if (($c = strlen($item)) > 1200)
      {
        $this->logError("invalid command, too big ($c)");
        return null;
      }
      # check groupchat command (ends with @botname)
      $isGroupChat = false;
      if (($a = strrpos($item, '@')) !== false)
      {
        # check proper designation
        $this->log(substr($item, $a));
        if (substr($item, $a + 1) !== $this->opts['name']) {
          return null;# ignore input
        }
        # correct the command
        $item = substr($item, 0, $a);
        $isGroupChat = true;
      }
      # parse command,
      # syntax: /<id>[:<id>][!<func>][ [<arg>[,<arg>]]]
      $a = '|^\/((\w+)([:/-](\w+)){0,8})(!(\w{1,})){0,1}( (.{1,})){0,1}$|';
      $b = null;
      if (!preg_match_all($a, $item, $b))
      {
        $this->logError("incorrect command syntax: $item");
        return null;
      }
      # extract parameters
      $id   = $b[1][0];
      $func = $b[6][0];
      $args = strlen($b[8][0])
        ? explode(',', $b[8][0])
        : null;
      # identifier may contain [/] or [-] as a separator,
      # convert them into a standard [:] for user convenience
      if (strpos($id, '/')) {
        $id = str_replace('/', ':', $id);
      }
      elseif (strpos($id, '-')) {
        $id = str_replace('-', ':', $id);
      }
      # check deep link invocation,
      # syntax: tg://<BOT_NAME>?start=<args>
      if ($id === 'start' && !$func && $args)
      {
        # [:] is not allowed in the "deep link", [-] is used,
        # use arguments as id/path and convert separators
        $id   = str_replace('-', ':', $args[0]);
        $args = null;
      }
      # get item
      if (!($a = $this->itemGet($id)))
      {
        $this->logError("item not found: $id");
        return null;
      }
      $this->log($item.' ['.implode(',', $a['blocks']).']');
      $item = $a;
    }
    # initialize
    # attach root and other basic stuff
    $root = $this->itemGetRoot($id);
    $conf = &$item['config'];
    $item['root'] = &$root;
    $item['lang'] = $lang = $this->user->lang;
    $item['isTitleCached'] = true;
    $item['isInputAccepted'] = false;
    # get language specific texts
    $text = array_key_exists('text', $item)
      ? $item['text'][$lang]
      : [];
    # set basic stuff
    $item['titleId'] = str_replace(':', '-', $item['id']).'-'.$lang;
    $item['title'] = array_key_exists('@', $text)
      ? $text['@']
      : $item['name'];
    $item['content'] = array_key_exists('.', $text)
      ? $text['.']
      : '';
    ###
    $item['titleImage'] = null;   # file_id or BotFile
    $item['textContent'] = '';    # rendered message contents
    $item['inlineMarkup'] = null; # inline buttons array
    # }}}
    # handle common function {{{
    switch ($func) {
    case 'inject':
      # remove self from the view,
      # because injection is global
      $this->itemDetach($item);
      # get injection origin
      if (!($a = $this->itemGetRoot($args[0])) ||
          !array_key_exists('_msg', $a['config']) ||
          !$a['config']['_msg'])
      {
        $this->log('wrong injection root');
        $error = 1;
        return null;
      }
      # copy origin's configuration
      $b = &$a['config'];
      $c = &$root['config'];
      $c['_from'] = $args[0];
      $c['_msg']  = $b['_msg'];
      $c['_time'] = $b['_time'];
      $c['_item'] = '';# not the same item
      # clear origin
      $b['_msg']  = 0;
      $b['_time'] = 0;
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
      break;
    case 'up':
      # check
      if ($item['parent'])
      {
        # CLIMB UP THE TREE (render parent)
        return $this->itemAttach('/'.$item['parent']['id'], $error);
      }
      if (array_key_exists('_from', $root['config']))
      {
        # EJECTOR
        # move root parameters to the origin
        $a = &$root['config'];
        $b = $a['_from'];
        $c = &$this->user->config[$b];
        $c['_msg']  = $a['_msg'];
        $c['_time'] = $a['_time'];
        $c['_item'] = '';# same root but not the same item
        $a['_msg']  = 0;
        $a['_time'] = 0;
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
        # recurse
        return $this->itemAttach('/'.$b, $error);
      }
      # continue..
    case 'stop':
      # TODO: TERMINATE ITEM
      # detete multigame icon
      if ($item['type'] === 'game' &&
          array_key_exists('icon', $conf) &&
          ($a = $conf['icon']))
      {
        $a = $this->api->send('deleteMessage', [
          'chat_id'    => $this->user->chat->id,
          'message_id' => $a,
        ]);
        if (!$a) {
          $this->log($this->api->error);
        }
        $conf['icon'] = 0;
      }
      # complete
      $this->userUpdate($item, 0);
      $error = 1;
      return $item;
    case 'restartLoop':
      # terminate getUpdates loop or skip webhook
      $error = -1;
      return $item;
    case '':
      # check new message desired
      if ($new)
      {
        # to properly render a new message for the item,
        # previous injection hint should be removed (if it exists)
        if (array_key_exists('_from', $root['config']))
        {
          unset($root['config']['_from']);
          $this->user->changed = true;
        }
      }
      break;
    }
    # }}}
    # load block handlers {{{
    $a = 'blocks'.DIRECTORY_SEPARATOR;
    $b = $this->dir.$a;
    $c = self::$inc.$a;
    foreach ($item['blocks'] as $a)
    {
      $a = "$a.php";
      $a = file_exists($b.$a) ? $b.$a : $c.$a;
      include_once($a);
    }
    $ItemHandler = '';
    # determine handler classes (blocks)
    $a = '\\'.__NAMESPACE__.'\\';
    #$a = '';
    $b = str_replace(':', '_', $item['id']);
    $c = $a.'item_'.$b;
    if (class_exists($c, false))
    {
      $ItemHandler = $c;
      $this->logDebug("handler: $ItemHandler");
    }
    # }}}
    # load data {{{
    # each item may or may not have it's own data
    $data = [];
    $dataChanged = false;
    # determine full path to the datafile
    $file = $item['type'].'-'.str_replace(':', '-', $item['id']).'.json';
    $file = $item['isPublicData']
      ? $this->dir.$file
      : $this->user->dir.$file;
    # load data
    if (file_exists($file) &&
        ($a = file_get_contents($file)) &&
        ($a = json_decode($a, true)))
    {
      $data = $a;
    }
    # }}}
    switch ($item['type']) {
    case 'menu':
      # render {{{
      if ($ItemHandler) {
        $ItemHandler::render($item, $this);
      }
      if (!$item['textContent']) {
        $item['textContent'] = $item['content'];
      }
      if (!$item['inlineMarkup'])
      {
        $item['inlineMarkup'] = $this->itemInlineMarkup(
          $item, $item['markup'], $text
        );
      }
      # }}}
      break;
    case 'list':
      # prepare {{{
      $rows  = $conf['rows'];
      $cols  = $conf['cols'];
      $size  = $rows * $cols;
      $count = $data ? count($data) : 0;
      # determine total page count
      if (!($total = intval(ceil($count / $size)))) {
        $total = 1;
      }
      # determine current page
      if (!array_key_exists('page', $conf))
      {
        $conf['page'] = $page = 0;
        $this->user->changed = true;
      }
      elseif (($page = $conf['page']) >= $total)
      {
        $conf['page'] = $page = $total - 1;
        $this->user->changed = true;
      }
      # }}}
      # handle function {{{
      switch ($func) {
      case 'first':
        $page = 0;
        break;
      case 'last':
        $page = $total - 1;
        break;
      case 'prev':
        $page = ($page > 0)
          ? $page - 1
          : $total - 1;
        break;
      case 'next':
        $page = ($page < $total - 1)
          ? $page + 1
          : 0;
        break;
      }
      # }}}
      # check {{{
      if ($page !== $conf['page'])
      {
        $this->user->changed = true;
      }
      # }}}
      # render markup {{{
      $mkup = [];
      if ($count)
      {
        # NON-EMPTY
        # content {{{
        # sort list items
        if (!self::sortList($data, $conf['order'], $conf['desc']))
        {
          $this->log('failed to sort data');
          $error = self::$messages[$lang][2];
          return $item;
        }
        # extract records from the ordered data set
        $a = $page * $size;
        $recs = array_slice($data, $a, $size);
        # create list markup
        for ($a = 0, $c = 0; $a < $rows; ++$a)
        {
          # create row
          $mkup[$a] = [];
          for ($b = 0; $b < $cols; ++$b)
          {
            # determine caption and command
            if ($c < count($recs))
            {
              $d = $recs[$c]['name'];
              $e = '/'.$item['cmd'].' '.$recs[$c]['id'];
            }
            else
            {
              $d = ' ';
              $e = '!';
            }
            # create cell
            $mkup[$a][$b] = [
              'text'          => $d,
              'callback_data' => $e,
            ];
            $c++;
          }
          if ($c >= count($recs) && !$conf['fixed']) {
            break;
          }
        }
        # }}}
        # controls {{{
        # determine first_last
        $a = '_first_last';
        $b = ($total === 1)
          ? ''
          : (($total - $page > $page)
            ? '_last'
            : '_first'
          );
        $this->setMarkupItem($item['markup'], $a, $b, true);
        # if it's only one page
        # remove page navigation controls
        if (!$conf['fixed'] && $total === 1)
        {
          $a = ['_first','_last','_prev','_next'];
          foreach ($a as $b) {
            $this->setMarkupItem($item['markup'], $b, '', true);
          }
        }
        ###
        # header
        if (array_key_exists('head', $item['markup']))
        {
          $a = $this->itemInlineMarkup(
            $item, $item['markup']['head'], $text
          );
          foreach (array_reverse($a) as $b) {
            array_unshift($mkup, $b);
          }
        }
        # footer
        if (array_key_exists('foot', $item['markup']))
        {
          $a = $this->itemInlineMarkup(
            $item, $item['markup']['foot'], $text
          );
          foreach ($a as $b) {
            array_push($mkup, $b);
          }
        }
        # }}}
      }
      else
      {
        # EMPTY (controls only)
        # {{{
        if (array_key_exists('empty', $item['markup']))
        {
          $mkup = $this->itemInlineMarkup(
            $item, $item['markup']['empty'], $text
          );
        }
        # }}}
      }
      $item['inlineMarkup'] = $mkup;
      # }}}
      # render content {{{
      if ($count)
      {
        # NON-EMPTY
        $a = ($item['content'] ?: self::$messages[$lang][4]);
        $item['textContent'] = self::$tp->render($a, [
          'item_count'   => $count,
          'page'         => 1 + $page,
          'page_count'   => $total,
          'not_one_page' => ($total > 1),
        ]);
      }
      else
      {
        # EMPTY
        $item['textContent'] = array_key_exists('-', $text)
          ? $text['-']
          : self::$messages[$lang][2];
      }
      # }}}
      # set {{{
      $conf['page'] = $page;
      # }}}
      break;
    case 'form':
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
              $dataChanged = true;
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
          $dataChanged = true;
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
          $dataChanged = true;
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
                $dataChanged = true;
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
        $dataChanged = true;
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
        $a = $this->opts['debug_task'];# debugging?
        if (!$this->itemTaskStart($item, $data, $a))
        {
          $error = $a ? 1 : self::$messages[$lang][11];
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
        $dataChanged = true;
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
        $error = self::$messages[$lang][2];
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
      if ($dataChanged)
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
                'text' => self::$tp->render(
                  self::$buttons[$n],
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
        $b = self::$tp->render(self::$buttons['select1'], ['text'=>$a]);
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
      # select controls
      $d = [
        'first' => self::$messages[$lang][15],# reset
      ];
      $d['refresh'] = ($state === 5)
        ? self::$messages[$lang][18] # repeat
        : self::$messages[$lang][14];# refresh
      if ($state === 0 && $indexField)
      {
        $d['prev'] = $index
          ? self::$messages[$lang][16]
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
            $d['last'] = self::$messages[$lang][12];
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
          $d['next'] = self::$messages[$lang][17];
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
          $c = self::$messages[$lang][7];
          $c = self::$tp->render($c, ['max'=>$b[2]]);
          break;
        case 'int':
          $c = self::$messages[$lang][8];
          $c = self::$tp->render($c, ['min'=>$b[2],'max'=>$b[3]]);
          break;
        case 'list':
          $c = self::$messages[$lang][13];
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
        : self::$messages[$lang][6];
      # get description
      $b = array_key_exists('.', $text)
        ? preg_replace('/\n\s*/m', ' ', trim($text['.']))
        : '';
      # compose full text
      $item['textContent'] = self::render_content($a, [
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
      break;
    case 'opener':
    case 'injector':
      # {{{
      # check destination
      if (!array_key_exists('path', $item))
      {
        $error = self::$messages[$lang][1];
        return $item;
      }
      # compose command (simply open or inject)
      $a = '/'.$item['path'];
      if ($item['type'] === 'injector') {
        $a = $a.'!inject '.$item['parent']['id'];
      }
      # recurse
      return $this->itemAttach($a, $error);
      # }}}
    default:
      # CUSTOM TYPE
      $error = self::$messages[$lang][1];
      return $item;
    }
    # render title {{{
    # check wasn't already rendered
    if (!$item['titleImage'])
    {
      # all standard sm-bot items have a title image,
      # image may be dynamic (generated) or static
      # first, check file_id cache
      if ($a = $this->getFileId($item['titleId']))
      {
        # CACHED
        $item['titleImage'] = $a;
      }
      elseif (!$item['title'])
      {
        # STATIC IMAGE? (no text specified)
        # determine image sources
        $a = str_replace(':', '-', $item['id']);
        $b = 'img'.DIRECTORY_SEPARATOR;
        $c = $b.$a.'.jpg';
        $d = $b.$a.'-'.$lang.'.jpg';
        $a = $this->dir;
        $b = self::$inc;
        # determine single location
        $a = (file_exists($a.$c)
          ? $a.$c : (file_exists($a.$d)
            ? $a.$d : (file_exists($b.$c)
              ? $b.$c : (file_exists($b.$d)
                ? $b.$d : ''))));
        # check found
        if ($a)
        {
          # static image file
          $item['titleImage'] = $this->imageFile($a);
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
        # TEXT SPECIFIED
        # generate nice header with language specific breadcrumbs
        $a = $this->itemBreadcrumb($item, $lang);
        $item['titleImage'] = $this->imageTitle($item['title'], $a);
      }
    }
    # }}}
    # encode markup {{{
    $item['inlineMarkup'] = $item['inlineMarkup']
      ? json_encode(['inline_keyboard' => $item['inlineMarkup']])
      : '';
    # }}}
    # save data {{{
    if ($dataChanged && $file)
    {
      if (!$data) {
        @unlink($file);
      }
      elseif (!file_put_contents($file, json_encode($data))) {
        $this->logError('file_put_contents('.$file.') failed');
      }
    }
    # }}}
    return $item;
  }
  # }}}
  # item {{{
  private function itemAssemble(&$m, &$p = null) # {{{
  {
    # iterate menu items
    foreach ($m as $a => &$b)
    {
      # set item's identifier, parent and root
      if ($p)
      {
        $b['id'] = $p['id'].':'.$a;
        $b['parent'] = &$p;
      }
      else
      {
        $b['id'] = $a;
        $b['parent'] = null;
      }
      $b['name'] = $a;
      # set item's config entry (should have one)
      if (!array_key_exists('config', $b)) {
        $b['config'] = [];
      }
      # set language texts (english must present)
      if (!array_key_exists('text', $b)) {
        $b['text'] = ['en'=>[]];
      }
      elseif (!array_key_exists('en', $b['text'])) {
        $b['text'] = ['en'=>$b['text']];
      }
      # parse emojis and button captions
      foreach ($b['text'] as $c => &$d)
      {
        foreach ($d as &$e)
        {
          $e = self::$tp->render($e, BotApi::$emoji, '{: :}');
          $e = self::$tp->render($e, self::$buttons, '{! !}');
        }
      }
      unset($d, $e);
      # merge add languages other than [en] with [en],
      # which allows to gradually translate commands,
      # so, default [en] captions act as a backup
      foreach (array_keys(self::$messages) as $c)
      {
        if ($c !== 'en')
        {
          $d = $b['text']['en'];
          $b['text'][$c] = array_key_exists($c, $b['text'])
            ? array_merge($d, $b['text'][$c])
            : $d;
        }
      }
      # set item's children dummy (tree navigation is common)
      if (!array_key_exists('items', $b)) {
        $b['items'] = null;
      }
      # set item's data publicity flag
      if (!array_key_exists('isPublicData', $b)) {
        $b['isPublicData'] = false;
      }
      # set blocks list
      if (array_key_exists('blocks', $b))
      {
        # specified
        array_unshift($b['blocks'], 'std');
      }
      else
      {
        if ($b['parent'])
        {
          # inherit
          $b['blocks'] = $b['parent']['blocks'];
        }
        else
        {
          # standard
          $b['blocks'] = ['std'];
        }
      }
      # recurse
      if ($b['items']) {
        $this->itemAssemble($b['items'], $b);
      }
    }
    # done
    return $m;
  }
  # }}}
  public function itemBreadcrumb(&$item, $lang = '', $short = false) # {{{
  {
    # determine first crumb,
    # check item injected
    if (!$item['parent'] && array_key_exists('_from', $item['config']))
    {
      # use origin
      $crumb = $this->itemGet($item['config']['_from']);
    }
    else {
      $crumb = $item['parent'];
    }
    # assemble
    $bread = '';
    if ($short)
    {
      if ($crumb)
      {
        if ($lang &&
            array_key_exists('@', $crumb['text'][$lang]))
        {
          $bread = $crumb['text'][$lang]['@'];
        }
        else {
          $bread = $crumb['name'];
        }
      }
    }
    else
    {
      while ($crumb)
      {
        if ($lang &&
            array_key_exists('@', $crumb['text'][$lang]))
        {
          $a = $crumb['text'][$lang]['@'];
        }
        else
        {
          $a = $crumb['name'];
        }
        $bread = '/'.$a.$bread;
        $crumb = $crumb['parent'];
      }
    }
    # done
    return $bread;
  }
  # }}}
  private function itemInlineMarkup(&$item, &$m, &$text, $ext = null) # {{{
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
        if (!strlen($b)) {
          continue;
        }
        # check tree navigation
        if ($b[0] !== '_')
        {
          if (array_key_exists('items', $item))
          {
            # goto child item
            $c = array_key_exists($b, $text)
              ? $text[$b]
              : $b;
            $c = self::$tp->render(self::$buttons['item'], ['text'=>$c]);
            $d = array_key_exists($b, $item['items'])
              ? '/'.$item['id'].':'.$b
              : '!';
          }
          else
          {
            # dummy button
            $c = ' ';
            $d = '!';
          }
          $row[] = ['text'=>$c,'callback_data'=>$d];
          continue;
        }
        # callback button
        # check goto command (navigation)
        $d = substr($b, 1);
        if (strncmp($d, 'go:', 3) === 0)
        {
          # determine goto caption
          $c = array_key_exists($b, $text)
            ? $text[$b]
            : self::$buttons['go'];
          $d = '/'.substr($d, 3);
          $c = self::$tp->render($c, [
            'text' => str_replace(':', '/', $d)
          ]);
          # compose nav button
          $row[] = ['text'=>$c,'callback_data'=>$d];
          continue;
        }
        # determine default caption
        $c = array_key_exists($b, $text)
          ? $text[$b]
          : (array_key_exists($d, self::$buttons)
            ? self::$buttons[$d]
            : $d);
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
            $e = self::$messages[$lang][10];
            $c = self::$buttons['close'];
          }
          $c = self::$tp->render($c, ['text'=>$e]);
          # }}}
        }
        elseif ($d === 'prev' || $d === 'next' ||
                $d === 'last' || $d === 'first' ||
                $d === 'refresh')
        {
          # {{{
          if ($ext)
          {
            # do not render this element
            if (!array_key_exists($d, $ext)) {
              continue;
            }
            # render empty nop
            if (!$ext[$d])
            {
              $row[] = ['text'=>' ','callback_data'=>'!'];
              continue;
            }
            # render specified value
            $c = self::$tp->render($c, ['name'=>$ext[$d]]);
          }
          else
          {
            # render empty
            $c = self::$tp->render($c, ['name'=>'']);
          }
          # }}}
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
  private function itemTaskStart(&$item, $data, $debug = false) # {{{
  {
    # compose php interpreter command
    $task = __DIR__.DIRECTORY_SEPARATOR.'index.php';
    $task = '"'.PHP_BINARY.'" -f "'.$task.'" -- ';
    # check
    if (is_string($item))
    {
      # unmanaged, custom operation
      if (self::async_execute($task.$item)) {
        $this->log("custom task: $item");
      }
    }
    else
    {
      # create item's task plan
      $plan = [
        'id'   => uniqid(),
        'bot'  => $this->id,
        'item' => $item['id'],
        'data' => $data,
        'user' => $this->user,
      ];
      if ($debug)
      {
        # launch now (debug mode),
        # as item is passed by reference, it can be replaced,
        $this->log('task debug: '.$item['id']);
        $item = $this->itemTaskWork($plan, true);
        return false;# prevents current attachment (replaced item)
      }
      else
      {
        # write task file
        $file = str_replace(':', '-', $item['id']);
        $file = 'task-'.$file.'.json';
        $file = $this->user->dir.$file;
        if (!file_put_contents($file, json_encode($plan)))
        {
          $this->logError("file_put_contents($file) failed");
          return false;
        }
        # launch two processes
        if (self::async_execute($task.'task "'.$file.'" '.$plan['id']) &&
            self::async_execute($task.'progress "'.$file.'" '.$plan['id']))
        {
          $this->log("task: $file");
        }
      }
    }
    return true;
  }
  # }}}
  private function itemTaskWork($plan, $debug = false) # {{{
  {
    # get current time
    $time = microtime(true);
    # hookup handlers
    $a = 'tasks.inc';
    include_once(self::$inc.$a);# common
    if (file_exists($this->dir.$a)) {
      include_once($this->dir.$a);# bot specific
    }
    # execute task handler
    $a = 'task_'.str_replace(':', '_', $plan['item']);
    $a = '\\'.__NAMESPACE__.'\\'.$a;
    if (class_exists($a, false))
    {
      $res = $a::handle($this, $plan);
      $msg = $a::$message;
    }
    else
    {
      # no handler, no problem
      $res = -1;
      $msg = '';
    }
    # update item
    if ($debug) {
      $item = $this->itemTaskUpdate($plan['item'], 'done', [$res,$msg]);
    }
    elseif ($this->userConfigAttach())
    {
      # measure time spent working and
      # delay completion for at least 0.4 second
      $a = 400000;
      if (($b = microtime(true) - $time) > 0 && $b < $a) {
        usleep($a - $b);
      }
      # to prevent config file deadlock,
      # lets wrap refresher
      try
      {
        $item = $this->itemTaskUpdate($plan['item'], 'done', [$res,$msg]);
      }
      catch (\Exception $e)
      {
        $this->logException($e);
        $item = null;
      }
      $this->userConfigDetach(!$item);
    }
    # done
    return $item;
  }
  # }}}
  private function itemTaskProgress($plan, $file) # {{{
  {
    $res = 0;
    while (file_exists($file))
    {
      # suspend
      usleep(500000);# 500ms
      if (!file_exists($file)) {
        break;
      }
      # determine progress value
      $res = $res ? 0 : 1;
      # load configuration
      if (!$this->userConfigAttach()) {
        return false;
      }
      # update
      $item = $this->itemTaskUpdate($plan['item'], 'progress', [$res]);
      # unload configuration (no write)
      $this->userConfigDetach(true);
    }
    return true;
  }
  # }}}
  private function itemTaskUpdate($id, $func, $args) # {{{
  {
    # prepare
    $a = '/'.$id.'!'.$func.' '.implode(',', $args);
    $b = '';
    # attach item
    if (($item = $this->itemAttach($a, $b)) && !$b)
    {
      # check displayed
      $a = $this->user->config;
      $b = $item['root']['id'];
      if (array_key_exists($b, $a) &&
          array_key_exists('_msg', $a[$b]) &&
          $a[$b]['_msg'] &&
          $a[$b]['_item'] === $item['id'])
      {
        # update
        $this->itemUpdate($a[$b]['_msg'], $item);
        return $item;
      }
    }
    # not updated
    return null;
  }
  # }}}
  public function itemGet($id) # {{{
  {
    # search item
    # start from root item
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
        $error = self::$messages[$this->user->lang][1];
        return null;
      }
      $item = $item['items'][$a];
    }
    # attach item's configuration
    if (!array_key_exists($id, $this->user->config)) {
      $this->user->config[$id] = $item['config'];
    }
    $item['config'] = &$this->user->config[$id];
    # done
    return $item;
  }
  # }}}
  public function itemGetRoot($id) # {{{
  {
    # extract root identfier
    if (($i = strpos($id, ':')) !== false) {
      $id = substr($id, 0, $i);
    }
    # complete
    return $this->itemGet($id);
  }
  # }}}
  private function itemSend($item) # {{{
  {
    switch ($item['type']) {
    case 'menu':
    case 'list':
    case 'form':
      # check title specified
      if (!$item['titleImage'])
      {
        $this->logError('no titleImage specified');
        return 0;
      }
      # assemble parameters
      $a = [
        'chat_id' => $this->user->chat->id,
        'photo'   => $item['titleImage'],
        'disable_notification' => true,
      ];
      if ($item['textContent'])
      {
        $a['caption'] = $item['textContent'];
        $a['parse_mode'] = 'HTML';
      }
      if ($item['inlineMarkup']) {
        $a['reply_markup'] = $item['inlineMarkup'];
      }
      # send
      if (!($a = $this->api->send('sendPhoto', $a)))
      {
        $this->logError($this->api->error);
        return 0;
      }
      # store file_id
      if ($item['titleId'] && ($item['titleImage'] instanceOf BotFile))
      {
        $b = end($a->result->photo);
        $this->setFileId($item['titleId'], $b->file_id);
      }
      # done
      return $a->result->message_id;
    }
    # unknown type
    $this->logError('unknown item type: '.$item['type']);
    return -1;# not fatal
  }
  # }}}
  private function itemUpdate($msg, $item, $refresh = false) # {{{
  {
    switch ($item['type']) {
    case 'menu':
    case 'list':
    case 'form':
      if ($refresh && $item['titleImage'])
      {
        # update everything (image, text and markup)
        $func = 'editMessageMedia';
        $file = $item['titleImage'];
        if ($file instanceof BotFile) {
          $img  = 'attach://'.$file->postname;# attachment
        }
        else
        {
          $img  = $file;# file_id
          $file = null;# no attachments
        }
        $res = [
          'chat_id'      => $this->user->chat->id,
          'message_id'   => $msg,
          'media'        => json_encode([
            'type'       => 'photo',
            'media'      => $img,
            'caption'    => $item['textContent'],
            'parse_mode' => 'HTML',
          ]),
          'reply_markup' => $item['inlineMarkup'],
        ];
      }
      else
      {
        # update only text and markup
        $func = 'editMessageCaption';
        $file = null;
        $res  = [
          'chat_id'      => $this->user->chat->id,
          'message_id'   => $msg,
          'caption'      => $item['textContent'],
          'parse_mode'   => 'HTML',
          'reply_markup' => $item['inlineMarkup'],
        ];
      }
      # send and check the result
      if (!($res = $this->api->send($func, $res, $file)) || $res === true)
      {
        $this->log($func.'('.$item['id'].') failed: '.$this->api->error);
        return -1;
      }
      # store file_id
      if ($item['titleId'] && $file)
      {
        $b = end($res->result->photo);
        $this->setFileId($item['titleId'], $b->file_id);
      }
      # complete
      return $res->result->message_id;
    }
    # unknown type
    $this->logError('unknown item type: '.$item['type']);
    return -1;# not fatal
  }
  # }}}
  private function itemDetach($item) # {{{
  {
    # prepare
    $conf = &$this->user->config;
    $root = array_key_exists('root', $item)
      ? $item['root']['id']
      : $item['id'];# assume item is root
    # check attached
    if (!array_key_exists($root, $conf) ||
        !array_key_exists('_msg', $conf[$root]) ||
        !($msg = $conf[$root]['_msg']))
    {
      return false;
    }
    $this->log('detaching /'.$root);
    # check message timestamp (telegram allows to delete only "fresh" messages)
    if (($a = time() - $conf[$root]['_time']) >= 0 &&
        ($a < self::$MSG_EXPIRE_TIME))
    {
      # wipe it
      $a = $this->api->send('deleteMessage', [
        'chat_id'    => $this->user->chat->id,
        'message_id' => $msg,
      ]);
      if (!$a) {
        $this->log($this->api->error);
      }
    }
    else {
      $a = false;
    }
    # zap message if it wasn't deleted
    if (!$a)
    {
      # the message may have different types, so careful here
      # let's check resolved item's command type
      switch ($item['type']) {
      case 'menu':
      case 'list':
      case 'form':
        $a = $this->imageZap($msg);
        break;
      default:
        $a = false;
        break;
      }
    }
    # remove item's root from the list of active roots
    if (array_key_exists('/', $conf) &&
        ($a = array_search($root, $conf['/'])) !== false)
    {
      array_splice($conf['/'], $a, 1);
    }
    # reset message configuration
    $conf[$root]['_msg']  = 0;
    $conf[$root]['_time'] = 0;
    $conf[$root]['_item'] = '';
    $this->user->changed = true;
    # done
    return $a;
  }
  # }}}
  private function itemZap($msg) # {{{
  {
    # try a simply delete
    $a = $this->api->send('deleteMessage', [
      'chat_id'    => $this->user->chat->id,
      'message_id' => $msg,
    ]);
    if ($a)
    {
      # succeeded
      $b = 'delete';
    }
    elseif (($a = $this->api->result) &&
            isset($a->error_code) &&
            $a->error_code === 400)
    {
      # message is too old for deletion,
      # it should be "zapped", parasite markup removed,
      # no text and neutral image block..
      # but the message type is unknown,
      # start with a most common one
      if ($this->imageZap($msg)) {
        $b = 'zap';
      }
      else {
        $b = 'impossible';
      }
    }
    else
    {
      # failed
      $b = $this->api->error;
    }
    # report
    $this->log('nullifying message: '.$b);
  }
  # }}}
  # }}}
  # user {{{
  private function userAttach($update) # {{{
  {
    # prepare
    $chat = null;
    $from = null;
    $isCallback = false;
    if (isset($update->callback_query))
    {
      if (!isset($update->callback_query->message)) {
        return false;
      }
      $chat = $update->callback_query->message->chat;
      $from = $update->callback_query->from;
      $isCallback = true;
    }
    elseif (isset($update->inline_query)) {
      $from = $update->inline_query->from;
    }
    elseif (isset($update->message))
    {
      $chat = $update->message->chat;
      $from = $update->message->from;
    }
    elseif (isset($update->edited_message))
    {
      $this->logDebug('ignoring edited message: '.var_export($update, true));
      return false;
    }
    # check properly specified
    if (!$from || !isset($from->id) || !isset($from->is_bot))
    {
      $this->log('no user specified, ignoring update: '.var_export($update, true));
      return false;
    }
    # determine chat name
    $chat->name = (
      (isset($chat->title) ? $chat->title : $chat->id).
      '@'.
      (isset($chat->username) ? $chat->username : $chat->id)
    );
    # determine user names and language
    $first_name = isset($from->first_name)
      ? preg_replace('/[[:^print:]]/', '', trim($from->first_name))
      : $from->id;
    $username = isset($from->username)
      ? $from->username
      : $from->id;
    $fullname = $first_name.'@'.$username;
    if (!array_key_exists('force_lang', $this->opts) ||
        !($lang = $this->opts['force_lang']))
    {
      $lang = (isset($from->language_code) &&
               array_key_exists($from->language_code, self::$messages))
        ? $from->language_code
        : 'en';
    }
    # attach refined object
    $this->user = (object)[
      'chat'     => $chat,
      'id'       => $from->id,
      'is_bot'   => $from->is_bot,
      'is_admin' => in_array($from->id, $this->opts['admins']),
      'uname'    => $username,
      'name'     => $fullname,
      'lang'     => $lang,
      'dir'      => '',
      'file'     => '',# config file path
      'config'   => null,
      'changed'  => false,
    ];
    # check chat access
    # TODO: refactor, make it smarter
    if ($chat->type !== 'private' &&
        !$this->user->is_admin)
    {
      # complain, no service :(
      $this->log('chat ['.$chat->name.'] ignored');
      # callback should be replied for a graceful ignore
      if ($isCallback)
      {
        $a = $update->callback_query->id;
        $a = ['callback_query_id' => $a];
        if (!$this->api->send('answerCallbackQuery', $a)) {
          $this->log($this->api->error);
        }
      }
      return false;
    }
    # set directory
    $this->user->dir = $this->dir.$from->id.DIRECTORY_SEPARATOR;
    if (!file_exists($this->user->dir)) {
      @mkdir($this->user->dir);
    }
    # attach configuration
    return $this->userConfigAttach();
  }
  # }}}
  private function userConfigAttach($noread = false) # {{{
  {
    # determine filename (depends on chat)
    $file = $this->user->dir.'config';
    $chat = $this->user->chat;
    if ($chat->type !== 'private') {
      $file = $file.strval($chat->id);
    }
    $file = $file.'.json';
    # aquire lock
    if (!self::file_lock($file))
    {
      $this->logError("failed to lock: $file");
      return false;
    }
    # read, decode contents and
    # set user's configuration
    if (!$noread)
    {
      $this->user->config = file_exists($file)
        ? json_decode(file_get_contents($file), true)
        : [];
    }
    # done
    $this->user->file = $file;
    return true;
  }
  # }}}
  private function userUpdate($item, $new_msg) # {{{
  {
    # prepare
    $dir  = $this->user->dir;
    $conf = &$this->user->config;
    $root = $item['root']['id'];
    $old_msg = array_key_exists('_msg', $conf[$root])
      ? $conf[$root]['_msg']
      : 0;
    # check active roots
    if (!array_key_exists('/', $conf))
    {
      # initialize, nothing was active
      $conf['/'] = [];
    }
    elseif (!$old_msg && $new_msg)
    {
      # item was not active, but,
      # there is a chance that another item is opened from it,
      # so, according to common logic (user intention),
      # injector message must be found and removed from the view,
      # in other words, replaced with new message
      foreach ($conf['/'] as $a)
      {
        if (array_key_exists('_from', $conf[$a]) &&
            $conf[$a]['_from'] === $root)
        {
          $this->itemDetach($this->commands[$a]);
          break;
        }
      }
    }
    elseif ($old_msg && $new_msg !== $old_msg)
    {
      # new message replaces old message, so,
      # old (current) must be removed
      $this->itemDetach($item);
    }
    # when the new message arrives,
    # it becomes an active root (may recieve input)
    if ($new_msg && $new_msg !== $old_msg) {
      array_unshift($conf['/'], $root);
    }
    # update configuration
    if ($root === $item['id'])
    {
      # item is root
      $conf[$root] = $item['config'];
    }
    else
    {
      # item and root
      $conf[$item['id']] = $item['config'];
      $conf[$root] = $item['root']['config'];
    }
    # update root configuration
    $conf[$root]['_msg']  = $new_msg;
    $conf[$root]['_item'] = $item['id'];
    if ($new_msg && $new_msg !== $old_msg) {
      $conf[$root]['_time'] = time();
    }
    # done
    $this->user->changed = true;
    return true;
  }
  # }}}
  private function userDetach($noWrite = false) # {{{
  {
    if ($this->user)
    {
      $noWrite = ($noWrite || !$this->user->changed);
      $this->userConfigDetach($noWrite);
      $this->user = null;
    }
    return true;
  }
  # }}}
  private function userConfigDetach($nowrite = false) # {{{
  {
    if ($this->user->file)
    {
      # write changes
      if (!$nowrite)
      {
        file_put_contents(
          $this->user->file,
          json_encode($this->user->config)
        );
      }
      # release lock
      self::file_unlock($this->user->file);
    }
    return true;
  }
  # }}}
  # }}}
  # logger {{{
  public function log($m, $type = 0) # {{{
  {
    static $e = null;
    if (!is_string($m)) {
      $m = var_export($m, true);
    }
    # TO FILE {{{
    if ($type)
    {
      file_put_contents(
        $this->errorlog,
        "<$type> ".date(DATE_ATOM).": $m\n",
        FILE_APPEND
      );
    }
    elseif (false)
    {
      file_put_contents(
        $this->accesslog,
        "<$type> ".date(DATE_ATOM).": $m\n",
        FILE_APPEND
      );
    }
    # }}}
    # TO STDERR CONSOLE {{{
    if (!self::$IS_TASK)
    {
      if (!$e) {
        $e = fopen('php://stderr', 'w');
      }
      if ($this->user) {
        $m = trim($this->user->name).'> '.$m;
      }
      fwrite($e, $type.'> '.$m."\n");
    }
    # }}}
    # TO SFX DEVICE {{{
    if (self::$WIN_OS && $this->opts['sfx'] && !self::$IS_TASK)
    {
      # play sound through batch-file
      $m = self::$inc.'sfx';
      $m = 'START "" /D "'.$m.'" /B play.bat info.wav';
      if ($m = popen($m, 'r'))
      {
        fgetc($m);
        pclose($m);
      }
    }
    # }}}
  }
  # }}}
  public function logError($m) {
    $this->log($m, 1);
  }
  public function logException($e) {
    $this->log($e->getMessage()."\n".$e->getTraceAsString()."\n",  1);
  }
  public function logDebug($m)
  {
    if ($this->opts['debuglog']) {
      $this->log($m, 0);
    }
  }
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
    # determine defaults
    if (!$font) {
      $font = $this->fontsdir.'title.ttf';
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
  private function imageFile($path) # {{{
  {
    # TODO: refine to proper size
    # create as non-temporary
    return new BotFile($path, false);
  }
  # }}}
  private function imageZap($msg) # {{{
  {
    # get or create empty image
    if (array_key_exists('empty', $this->fids))
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
  # TODO:
  private function createEmptyImage($variant, $fg = null, $bg = null) # {{{
  {
    # create image (RGB color)
    if (($img = @imagecreatetruecolor(640, 160)) === false)
    {
      $this->log('imagecreatetruecolor() failed');
      return null;
    }
    # determine colors
    if (!$bg) {
      $bg = [72,61,139];# darkslateblue
    }
    if (!$fg) {
      $fg = [240,248,255];# aliceblue
    }
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
  public static function file_lock($file) # {{{
  {
    # prepare
    $id    = uniqid();
    $count = 99;
    $lock  = $file.'.lock';
    # wait until lock released or count exhausted
    while (file_exists($lock) && --$count) {
      usleep(100000);# 100ms
    }
    # check exhausted
    if (!$count) {
      return false;
    }
    # set new lock and
    # make sure no collisions
    if (!file_put_contents($lock, $id) ||
        !file_exists($lock) ||
        file_get_contents($lock) !== $id)
    {
      return false;
    }
    return true;
  }
  # }}}
  public static function file_unlock($file) # {{{
  {
    $lock = $file.'.lock';
    if (file_exists($lock)) {
      unlink($lock);
    }
    return true;
  }
  # }}}
  public static function async_execute($command) # {{{
  {
    if (self::$WIN_OS)
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
  public static function render_content($text, $data = []) # {{{
  {
    $data['br'] = "\n";
    return self::$tp->render(
      preg_replace('/\n\s*/m', '', $text),# trim input
      $data
    );
  }
  # }}}
  public function setFileId($file, $id) # {{{
  {
    # set current
    $this->fids[$file] = $id;
    # store
    $a = $this->dir.'file_id.json';
    $b = json_encode($this->fids);
    if (file_put_contents($a, $b) === false)
    {
      $this->log('file_put_contents('.$a.') failed');
      return false;
    }
    return true;
  }
  # }}}
  public function getFileId($file) # {{{
  {
    if ($file && array_key_exists($file, $this->fids))
    {
      $this->logDebug("file_id found: $file");
      return $this->fids[$file];
    }
    return '';
  }
  # }}}
  # }}}
  # TODO:
  private function setMarkupItem(&$markup, $old, $new, $multi = false) # {{{
  {
    if ($multi)
    {
      foreach ($markup as &$set)
      {
        foreach ($set as &$row)
        {
          foreach ($row as &$cell)
          {
            if ($cell === $old) {
              $cell = $new;
            }
          }
        }
      }
    }
    else
    {
      foreach ($markup as &$row)
      {
        foreach ($row as &$cell)
        {
          if ($cell === $old) {
            $cell = $new;
          }
        }
      }
    }
  }
  # }}}
  public static function sortList(&$list, $k, $desc = false) # {{{
  {
    # define numeric keys (others are strings)
    static $keys = ['id','order'];
    # check
    if (in_array($k, $keys))
    {
      # sort numbers
      $c = uasort($list, function($a, $b) use ($k, $desc) {
        # check equal and resolve by unique identifier
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
      $c = uasort($list, function($a, $b) use ($k, $desc) {
        # compare items
        if (($c = strcmp($a[$k], $b[$k])) === 0)
        {
          # resolve equal by unique identifier
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
class BotApi {
  # {{{
  private
    $curl  = null,
    $token = '';
  private static
    $url = 'https://api.telegram.org/bot';
  public
    $error  = '',
    $result = null;
  public static
    $emoji = [
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
    ],
    $hex = [
      "\xC2\xAD",# SOFT HYPHEN U+00AD
      "\xC2\xA0",# non-breakable space (nbsp)
    ];
  # initializer {{{
  private function __construct($curl, $token)
  {
    $this->curl  = $curl;
    $this->token = $token;
  }
  public static function init($token)
  {
    # create curl instance
    if (!function_exists('curl_init') || !($curl = curl_init())) {
      exit('curl missing');
    }
    # configure it
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
    return new BotApi($curl, $token);
  }
  # }}}
  public function send($method, $args, $file = null) # {{{
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
      CURLOPT_URL  => self::$url.$this->token.'/'.$method,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $args,
    ]);
    $a = curl_exec($this->curl);
    # explicitly remove temporary files
    if ($file && $file instanceof BotFile) {
        $file->__destruct();
    }
    if (array_key_exists($method, $tempfile))
    {
      $b = $tempfile[$method];
      if (array_key_exists($b, $args) &&
          $args[$b] instanceof BotFile)
      {
        $args[$b]->__destruct();
      }
    }
    # check result
    if ($a === false)
    {
      $this->error  = 'curl error #'.curl_errno($this->curl).': '.curl_error($this->curl);
      $this->result = null;
      return false;
    }
    # decode
    if (($a = json_decode($a)) === null)
    {
      $this->error  = 'json error #'.json_last_error().': '.json_last_error_msg();
      $this->result = null;
      return false;
    }
    # check
    $this->result = $a;
    if (!$a->ok)
    {
      $this->error = $method.' failed';
      if (isset($a->description)) {
        $this->error .= ': '.$a->description;
      }
      return false;
    }
    return $a;
  }
  # }}}
  # }}}
}
class BotFile extends \CURLFile {
  # {{{
  private $isTemporary = true;
  public function __construct($file, $temp = true)
  {
    parent::__construct($file);
    $this->postname = basename($file);
    $this->isTemporary = $temp;
  }
  public function __destruct()
  {
    if ($this->name && $this->isTemporary)
    {
      unlink($this->name);
      $this->name = '';
    }
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
?>
