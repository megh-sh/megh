<?php
namespace Megh;

use Illuminate\Container\Container;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\SymfonyStyle;

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
     * Format input into a textual table.
     *
     * @param array  $headers
     * @param array  $rows
     * @param string $style
     *
     * @return void
     */
    public static function table(array $headers, array $rows, $style = 'default')
    {
        if (empty($rows)) {
            return;
        }

        $table = new Table(static::app('output'));

        $table->setHeaders($headers)->setRows($rows)->setStyle($style)->render();
    }

    /**
     * Ask the user a confirmation question.
     *
     * @param string $question
     * @param mixed  $default
     *
     * @return mixed
     */
    public static function confirm($question, $default = true)
    {
        $style = new SymfonyStyle(static::app('input'), static::app('output'));

        return $style->confirm($question, $default);
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
