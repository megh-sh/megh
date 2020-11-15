<?php

namespace Megh;

/**
 * Re-usable CLI trait
 */
trait UsesCli
{
    /**
     * The CLI object
     *
     * @var Object
     */
    protected $cliObject;

    /**
     * Get CLI
     *
     * @return CommandLine
     */
    public function cli()
    {
        if ($this->cliObject) {
            return $this->cliObject;
        }

        $this->cliObject = new CommandLine();

        return $this->cliObject;
    }
}
