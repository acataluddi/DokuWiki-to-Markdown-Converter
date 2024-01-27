<?php

namespace Dokumd\Utils;

class Console
{
    /**
     * Simple Writeline function
     * @param string $line
     * @param mixed ...$args
     * @return void
     */
    public static function wl(string $line = '', ...$args)
    {
        print vsprintf($line, $args) . Constants::LF;
    }
}