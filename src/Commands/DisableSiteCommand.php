<?php
namespace Megh\Commands;

use Megh\Helper;
use Megh\Site;
use Symfony\Component\Console\Input\InputArgument;

class DisableSiteCommand extends Command
{
    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('disable')
            ->setDescription('Disable a site')
            ->addArgument('name', InputArgument::REQUIRED, 'The site domain name');
    }

    /**
     * Execute the command.
     *
     * @return void
     */
    public function handle()
    {
        $name = $this->argument('name');
        
        $site = new Site($name);
        $site->disable();

        Helper::success("Site $name disabled");
    }
}
