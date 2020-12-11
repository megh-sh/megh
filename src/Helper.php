<?php
namespace Megh;

use Illuminate\Container\Container;
use TitasGailius\Terminal\Terminal;

class Helper
{
    /**
     * Resolve a service from the container.
     *
     * @param string|null $name
     *
     * @return mixed
     */
    public static function app($name = null)
    {
        return $name ? Container::getInstance()->make($name) : Container::getInstance();
    }

    /**
     * Get the user
     *
     * @return string
     */
    public static function user()
    {
        if (!isset($_SERVER['SUDO_USER'])) {
            return $_SERVER['USER'];
        }

        return $_SERVER['SUDO_USER'];
    }

    /**
     * Determine if a given string starts with a given substring.
     *
     * @param  string  $haystack
     * @param  string|array  $needles
     * @return bool
     */
    public static function starts_with($haystack, $needles)
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && substr($haystack, 0, strlen($needle)) === (string) $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Write a single line
     *
     * @param string $text
     *
     * @return mixed
     */
    public static function line($text)
    {
        return static::app('output')->writeln($text);
    }

    /**
     * Write a single line success
     *
     * @param string $text
     *
     * @return void
     */
    public static function success($text)
    {
        echo static::app('output')->writeln('<info>'.$text.'</info>');
    }

    /**
     * Write a single line success
     *
     * @param string $text
     *
     * @return void
     */
    public static function warning($text)
    {
        echo static::app('output')->writeln('<comment>'.$text.'</comment>');
    }

    /**
     * Write a debug message
     *
     * @param string $text
     *
     * @return void
     */
    public static function debug($text)
    {
        if (static::app('output')->isDebug()) {
            echo static::app('output')->writeln($text);
        }
    }

    /**
     * Write a verbose message
     *
     * @param string $text
     *
     * @return void
     */
    public static function verbose($text)
    {
        if (static::app('output')->isVerbose()) {
            echo static::app('output')->writeln($text);
        }
    }

    /**
     * Check if we are on macOS
     *
     * @return boolean
     */
    public static function isMac()
    {
        return PHP_OS === 'Darwin';
    }
    
    /**
     * Check if we are on macOS
     *
     * @return boolean
     */
    public static function isLinux()
    {
        return PHP_OS === 'Linux';
    }
}
