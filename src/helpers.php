<?php

use Symfony\Component\Process\Process;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Helper\Table;

define('MEGH_HOME_PATH', $_SERVER['HOME'] . '/.megh' );

/**
 * Output the given text to the console.
 *
 * @param  string  $output
 * @return void
 */
function info($output)
{
    output('<info>'.$output.'</info>');
}

/**
 * Output the given text to the console.
 *
 * @param  string  $output
 * @return void
 */
function warning($output)
{
    output('<fg=red>'.$output.'</>');
}

/**
 * Output a table to the console.
 *
 * @param array $headers
 * @param array $rows
 * @return void
 */
function table(array $headers = [], array $rows = [])
{
    $table = new Table(new ConsoleOutput);
    $table->setHeaders($headers)->setRows($rows);
    $table->render();
}

/**
 * Output the given text to the console.
 *
 * @param  string  $output
 * @return void
 */
function output($output)
{
    if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'testing') {
        return;
    }

    (new ConsoleOutput)->writeln($output);
}


if (! function_exists('retry')) {
    /**
     * Retry the given function N times.
     *
     * @param  int  $retries
     * @param  callable  $retries
     * @param  int  $sleep
     * @return mixed
     */
    function retry($retries, $fn, $sleep = 0)
    {
        beginning:

        try {
            return $fn();
        } catch (Exception $e) {
            if (! $retries) {
                throw $e;
            }

            $retries--;

            if ($sleep > 0) {
                usleep($sleep * 1000);
            }

            goto beginning;
        }
    }
}

if (! function_exists('tap')) {
    /**
     * Tap the given value.
     *
     * @param  mixed  $value
     * @param  callable  $callback
     * @return mixed
     */
    function tap($value, callable $callback)
    {
        $callback($value);

        return $value;
    }
}

/**
 * Verify that the script is currently running as "sudo".
 *
 * @return void
 */
function should_be_sudo()
{
    if (! isset($_SERVER['SUDO_USER'])) {
        throw new Exception('This command must be run with sudo.');
    }
}

/**
 * Get the user
 */
function user()
{
    if (! isset($_SERVER['SUDO_USER'])) {
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
function starts_with($haystack, $needles)
{
    foreach ((array) $needles as $needle) {
        if ($needle !== '' && substr($haystack, 0, strlen($needle)) === (string) $needle) {
            return true;
        }
    }
    return false;
}
