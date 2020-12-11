<?php
namespace Megh\Commands;

use Megh\Helper;
use Megh\Site;
use Symfony\Component\Console\Input\InputArgument;

class EnableSiteCommand extends Command
{
    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('enable')
            ->setDescription('Enable a site')
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
        $site->enable();

        Helper::success("Site $name enabled");
    }
}
