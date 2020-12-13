<?php
namespace Megh\Commands;

use Megh\Docker;
use Megh\Helper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class Command extends SymfonyCommand
{
    /**
     * The input implementation.
     *
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    public $input;

    /**
     * The output implementation.
     *
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    public $output;

    /**
     * Execute the command.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        Helper::app()->instance('input', $this->input = $input);
        Helper::app()->instance('output', $this->output = $output);

        if (!(new Docker())->dockerRunning()) {
            throw new \Exception('Docker is not running');
        }

        $this->configureOutputStyles($output);

        return $this->handle() ?: SymfonyCommand::SUCCESS;
    }

    public function handle()
    {
    }

    /**
     * Configure the output styles for the application.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return void
     */
    protected function configureOutputStyles(OutputInterface $output)
    {
        $output->getFormatter()->setStyle(
            'finished',
            new OutputFormatterStyle('green', 'default', ['bold'])
        );
    }

    /**
     * Get an argument from the input list.
     *
     * @param string $key
     *
     * @return mixed
     */
    protected function argument($key)
    {
        return $this->input->getArgument($key);
    }

    /**
     * Get an option from the input list.
     *
     * @param string $key
     *
     * @return mixed
     */
    protected function option($key)
    {
        return $this->input->getOption($key);
    }

    /**
     * Write a single line
     *
     * @param string $text
     *
     * @return mixed
     */
    protected function writeln($text)
    {
        return $this->output->writeln($text);
    }

    /**
     * Write a single line success
     *
     * @param string $text
     *
     * @return void
     */
    protected function success($text)
    {
        echo $this->output->writeln('<info>'.$text.'</info>');
    }

    /**
     * Write a debug message
     *
     * @param string $text
     *
     * @return void
     */
    protected function debug($text)
    {
        if ($this->output->isDebug()) {
            echo $this->output->writeln($text);
        }
    }

    /**
     * Write a verbose message
     *
     * @param string $text
     *
     * @return void
     */
    protected function verbose($text)
    {
        if ($this->output->isVerbose()) {
            echo $this->output->writeln($text);
        }
    }
}
