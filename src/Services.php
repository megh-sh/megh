<?php
namespace Megh;

class Services
{
    /**
     * @var Docker
     */
    protected $docker;
    
    /**
     * Name of the global nginx proxy network
     *
     * @var string
     */
    const proxyNetwork = 'nginx-proxy';

    /**
     * Name of the global DB network
     *
     * @var string
     */
    const dbNetwork = 'db-network';
    
    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        $this->docker = new Docker();
    }

    /**
     * Path to services directory
     *
     * @return string
     */
    protected function path()
    {
        return MEGH_HOME_PATH;
    }
    
    /**
     * Start the global docker services
     *
     * @return void
     */
    public function start()
    {
        $this->docker->composeUp($this->path());
    }
    
    /**
     * Stop the global docker services
     *
     * @return void
     */
    public function stop()
    {
        $this->docker->composeDown($this->path());
    }

    /**
     * Start the global networks
     *
     * @return void
     */
    public function startNetworks()
    {
        if (!$this->docker->networkExists(self::proxyNetwork)) {
            $this->docker->createNetwork(self::proxyNetwork);
        }

        if (!$this->docker->networkExists(self::dbNetwork)) {
            $this->docker->createNetwork(self::dbNetwork);
        }
    }

    /**
     * Stop the global networks
     *
     * @return void
     */
    public function stopNetworks()
    {
        $this->docker->removeNetwork(self::proxyNetwork);
        $this->docker->removeNetwork(self::dbNetwork);
    }
}
