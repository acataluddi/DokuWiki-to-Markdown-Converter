<?php

namespace Dokumd\Utils;

/**
 * Class Console
 * Services methods to output information in the console.
 *
 * @package Dokumd\Utils
 * @author Adriano Cataluddi <acataluddi@gmail.com>
 */
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