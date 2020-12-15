<?php
namespace Megh\Commands;

use Megh\Helper;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;

class TestCommand extends Command
{
    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('test')
            ->setHidden(true)
            ->setDescription('A command for testing purpose only')
            ->addArgument('name', InputArgument::REQUIRED, 'Who do you want to greet?')
            ->addArgument('last_name', InputArgument::OPTIONAL, 'Your last name?');
    }

    /**
     * Execute the command.
     *
     * @return void
     */
    public function handle()
    {
        $text = 'Hello ' . $this->argument('name');

        if ($last = $this->argument('last_name')) {
            $text .= ' ' . $last;
        }

        $this->output->writeln($text);
    }
}
