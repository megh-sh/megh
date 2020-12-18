<?php
namespace Megh\Commands;

use Megh\Configure;
use Megh\Docker;
use Megh\Filesystem;
use Megh\Helper;
use Megh\Services;

class StopCommand extends Command
{
    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('stop')
            ->setDescription('Stop Megh services');
    }

    /**
     * Execute the command.
     *
     * @return void
     */
    public function handle()
    {
        // bring down all sites
        $this->disableSites();

        // down the services
        $this->disableServices();

        // remove networks
        $this->removeNetworks();

        Helper::success('Services stopped');
    }

    /**
     * Disable all the sites
     *
     * @return void
     */
    protected function disableSites()
    {
        Helper::verbose('Disabling the sites');

        $sitePath = Configure::sitePath();
        $docker = new Docker();
        $files = new Filesystem();

        $dirs = $files->listDir($sitePath);
        
        if ($dirs) {
            foreach ($dirs as $site) {
                $path = $sitePath . DIRECTORY_SEPARATOR . $site;

                if ($files->exists($path . '/docker-compose.yaml')) {
                    Helper::debug('Stopping site: ' . $site);
                    $docker->composeDown($sitePath . DIRECTORY_SEPARATOR . $site);
                }
            }
        }
    }

    /**
     * Stop global services
     *
     * @return void
     */
    protected function disableServices()
    {
        Helper::verbose('Disabling the global services');

        (new Services())->stop();
    }

    /**
     * Remove global networks
     *
     * @return void
     */
    protected function removeNetworks()
    {
        Helper::verbose('Disabling global networks');

        (new Services())->stopNetworks();
    }
}
