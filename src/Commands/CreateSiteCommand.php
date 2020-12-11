<?php
namespace Megh\Commands;

use Megh\Helper;
use Megh\Site;
use Symfony\Component\Console\Input\InputArgument;

class CreateSiteCommand extends Command
{
    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('create')
            ->setDescription('Create a new site')
            ->addArgument('name', InputArgument::REQUIRED, 'The site domain name')
            ->addOption('type', null, InputArgument::OPTIONAL, 'Type of the site. Options: "php", "wp".', 'php')
            ->addOption('php', null, InputArgument::OPTIONAL, 'The PHP version. Options: "7.2", "7.3", "7.4".', '7.4')
            ->addUsage('example.com --type=wp')
            ->addUsage('example.com --type=wp --php=7.3');
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
        $site->create(
            $this->option('type'),
            $this->option('php')
        );

        Helper::success("Site $name created");
    }
}
