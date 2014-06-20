<?php
namespace MSF;

class Logger {
    public static $fp;
    public static function notice($text) {
        if (!defined('STDERR')) {
            if (!static::$fp) {
                static::$fp = fopen('php://stderr', 'w');
            }
        } else {
            static::$fp = STDERR;
        }
        fwrite(static::$fp, $text);
    }
}

