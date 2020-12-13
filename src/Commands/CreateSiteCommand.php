<?php
namespace Megh\Commands;

use Megh\Site;
use Megh\Helper;
use Symfony\Component\Console\Input\InputOption;
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
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Type of the site. Options: "php", "wp", "laravel".', 'php')
            ->addOption('php', null, InputOption::VALUE_OPTIONAL, 'The PHP version. Options: "7.2", "7.3", "7.4".', '7.4')
            ->addOption('add-host', null, InputOption::VALUE_OPTIONAL, 'Wheather to add the domain to the hosts file.', false)
            ->addUsage('example.com --type=wp')
            ->addUsage('example.com --type=wp --php=7.3')
            ->addUsage('example.com --type=wp --php=7.3 --add-host');
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
            $this->option('php'),
            $this->option('add-host') !== false
        );

        Helper::success("Site $name created");
    }
}
