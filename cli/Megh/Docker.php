<?php
namespace Megh;

/**
 * Docker class
 */
class Docker
{
    private $cli;
    private $proxy = 'nginx-proxy';

    function __construct()
    {
        $this->cli = new CommandLine();
    }

    public function flightCheck()
    {
        $this->checkDocker();
        $this->checkProxy();
    }

    /**
     * Check if Docker is running
     *
     * @return void
     */
    private function checkDocker()
    {
        $output = $this->cli->run( 'docker ps' );

        if ( ! starts_with( $output, 'CONTAINER' ) ) {
            throw new \Exception( 'Docker is not running' );
        }

        info( 'Docker is running' );
    }

    private function checkProxy()
    {
        $output = $this->cli->run( 'docker images' );

        if ( ! str_contains( $output, 'jwilder/nginx-proxy') ) {
            warning( 'nginx proxy is not available. Pulling...' );

            $proxyInit = $this->initProxy();

            if ( str_contains( $proxyInit, 'The container name "/nginx-proxy" is already' ) ) {
                output( 'Proxy is already initialized.' );
            }
        }

        $output = $this->cli->run( 'docker ps' );

        if ( ! str_contains( $output, 'jwilder/nginx-proxy') ) {
            warning( 'nginx-proxy isn\'t running, attempting to start...' );

            $this->cli->run( 'docker start nginx-proxy' );
        }

        info( 'Nginx proxy is running' );
    }

    public function initProxy()
    {
        $path = $this->proxyPath();
        $command = 'docker run --name ' . $this->proxy . ' -e --restart=always -d -p 80:80 -p 443:443 -v ' . $path . '/certs:/etc/nginx/certs -v ' . $path . '/dhparam:/etc/nginx/dhparam -v ' . $path . '/conf.d:/etc/nginx/conf.d -v ' . $path . '/htpasswd:/etc/nginx/htpasswd -v ' . $path . '/vhost.d:/etc/nginx/vhost.d -v /var/run/docker.sock:/tmp/docker.sock:ro jwilder/nginx-proxy';

        return $this->cli->run( $command );
    }

    public function createNetwork( $sitename )
    {
        return $this->cli->run( 'docker network create ' . $sitename, function($code, $output) {
            warning( $output );
            throw new \Exception('Error in starting the network');
        } );
    }

    public function connectNetwork( $sitename )
    {
        return $this->cli->run( 'docker network connect ' . $sitename . ' ' . $this->proxy, function() {
            throw new \Exception( "There was some error connecting to {$this->proxy}." );
        } );
    }

    public function removeNetwork( $sitename )
    {
        return $this->cli->run( 'docker network rm ' . $sitename );
    }

    public function disconnectNetwork( $sitename )
    {
        return $this->cli->run( 'docker network disconnect ' . $sitename . ' ' . $this->proxy );
    }

    public function composeUp( $sitename )
    {
        $command = 'cd ' . Configuration::sitePath() . '/' . $sitename . ' && docker-compose up -d';

        return $this->cli->run( $command, function($code, $output) {
            warning( $output );
            throw new \Exception('Error in starting the site');
        } );
    }

    public function composeDown( $sitename )
    {
        $command = 'cd ' . Configuration::sitePath() . '/' . $sitename . ' && docker-compose down';

        return $this->cli->run( $command, function($code, $output) {
            warning( $output );
            throw new \Exception('Error in removing the site');
        } );
    }

    private function proxyPath()
    {
        return MEGH_HOME_PATH . '/nginx';
    }

}
