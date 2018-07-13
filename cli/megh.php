#!/usr/bin/env php
<?php

/**
 * Load correct autoloader depending on install location.
 */
if ( file_exists( __DIR__ . '/../vendor/autoload.php' ) ) {
    require __DIR__ . '/../vendor/autoload.php';
} else {
    require __DIR__ . '/../../../autoload.php';
}

use Silly\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Megh\Site;
use Megh\Docker;
use Megh\Configuration;

$version = '1.0';

$app = new Application('Megh Docker client', $version);

$app->command('check', function() {
    $docker = new Docker();
    $docker->flightCheck();
});

$app->command('proxy-start', function() {
    $docker = new Docker();
    $docker->initProxy();
});

$app->command('install', function() {

    $conf = new Configuration();
    $conf->install();

    info( "Megh installed" );

})->descriptions('Install Megh');

$app->command('create site [--type=] [--php=] [--root=]', function ($site, $type, $php, $root) {

    $sites = new Site( $site );
    $sites->create( $type, $php, $root );

    info( "Site $site created" );

})->descriptions('Create a new site', [
    'site'   => 'The url of the site',
    '--type' => 'Type of the site.',
    '--php'  => 'Version of PHP. latest, 7.1 or 7.2.',
    '--root' => 'The web root for nginx.',
])
->defaults([
    'type' => 'php', // wp, bedrock, laravl, static
    'php'  => 'latest', // 7.1, 7.2, latest
    'root' => '/',
]);

$app->command('enable name', function ($name) {

    $site = new Site( $name );
    $site->enable();

    info( "Site $name enabled" );

})->descriptions('Enable the site.');

$app->command('disable name', function ($name) {

    $site = new Site( $name );
    $site->disable();

    info( "Site $name disabled" );

})->descriptions('Disable the site.');


$app->command('delete name', function ($name) {

    $site = new Site( $name );
    $site->delete();

    info( "Site $name deleted" );

})->descriptions('Delete the site.');

$app->run();
