<?php
namespace Megh\Commands;

use Megh\Configure;
use Megh\Docker;
use Megh\Filesystem;
use Megh\Helper;
use Megh\Services;

class StartCommand extends Command
{
    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('start')
            ->setDescription('Start Megh services');
    }

    /**
     * Execute the command.
     *
     * @return void
     */
    public function handle()
    {
        // start the networks
        $this->startNetworks();

        // up the services
        $this->startServices();

        // start sites
        $this->startSites();

        Helper::success('Services started');
    }

    /**
     * Disable all the sites
     *
     * @return void
     */
    protected function startSites()
    {
        Helper::verbose('Starting the sites');

        $sitePath = Configure::sitePath();
        $docker = new Docker();
        $files = new Filesystem();

        $dirs = $files->listDir($sitePath);
        
        if ($dirs) {
            foreach ($dirs as $site) {
                $path = $sitePath . DIRECTORY_SEPARATOR . $site;

                if ($files->exists($path . '/docker-compose.yaml')) {
                    Helper::debug('Starting site: ' . $site);
                    $docker->composeUp($sitePath . DIRECTORY_SEPARATOR . $site);
                }
            }
        }
    }

    /**
     * Stop global services
     *
     * @return void
     */
    protected function startServices()
    {
        Helper::verbose('Starting the global services');

        (new Services())->start();
    }

    /**
     * Remove global networks
     *
     * @return void
     */
    protected function startNetworks()
    {
        Helper::verbose('Starting global networks');

        (new Services())->startNetworks();
    }
}
