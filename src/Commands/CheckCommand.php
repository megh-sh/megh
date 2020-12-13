<?php
namespace Megh\Commands;

use Megh\Docker;
use Megh\Helper;
use Megh\Services;

class CheckCommand extends Command
{
    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('check')
            ->setDescription('Check docker and proxy status');
    }

    /**
     * Execute the command.
     *
     * @return void
     */
    public function handle()
    {
        $docker = new Docker();
        
        if ($docker->containerRunning('nginx-proxy')) {
            Helper::success('[container] nginx-proxy is running');
        } else {
            Helper::warning('[container] nginx-proxy is not running');
        }
        
        if ($docker->containerRunning('mariadb')) {
            Helper::success('[container] mariadb is running');
        } else {
            Helper::warning('[container] mariadb is not running');
        }

        if ($docker->networkExists(Services::proxyNetwork)) {
            Helper::success('[network] global nginx-proxy network exists');
        } else {
            Helper::warning('[network] global nginx-proxy network does not exist');
        }

        if ($docker->networkExists(Services::dbNetwork)) {
            Helper::success('[network] global db-network network exists');
        } else {
            Helper::warning('[network] global db-network network does not exist');
        }
    }
}
