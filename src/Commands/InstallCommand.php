<?php
namespace Megh\Commands;

use Megh\Configure as Configuration;

class InstallCommand extends Command
{
    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('install')
            ->setDescription('Install and Configure Megh');
    }

    /**
     * Execute the command.
     *
     * @return void
     */
    public function handle()
    {
        $config = new Configuration();
        $config->install();

        $this->success('Megh installed');
    }
}
