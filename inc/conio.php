<?php declare(strict_types=1);
return (PHP_OS_FAMILY === 'Windows')
  ? new class
  {
    private static $o;
    function __construct()
    {
      self::$o ||
      self::$o = FFI::load(__DIR__.DIRECTORY_SEPARATOR.'conio.h');
    }
    function getch(): string
    {
      return self::$o->_kbhit()
        ? chr(self::$o->_getch())
        : '';
    }
    function getwch(): string
    {
      if (!self::$o->_kbhit()) {
        return '';
      }
      return (($a = self::$o->_getwch()) > 255)
        ? mb_chr($a, 'UTF-16LE') # assume it doesnt fail
        : chr($a);
    }
  }
  : null;# TODO: NixOS
?>
