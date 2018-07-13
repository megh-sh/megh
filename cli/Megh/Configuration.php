<?php
namespace Megh;

/**
 * Configuration class
 */
class Configuration
{
    private $files;

    function __construct() {
        $this->files = new Filesystem();
    }

    public function install()
    {
        $this->createConfigurationDirectory();
        $this->createSitesDirectory();
        $this->createNginxConfigurationDirectory();
        $this->writeBaseConfiguration();

        $this->initDocker();
    }

    public function initDocker() {
        output( 'Initializing docker nginx-proxy' );

        (new Docker())->initProxy();
    }

    function createConfigurationDirectory()
    {
        $this->files->ensureDirExists( MEGH_HOME_PATH, user() );
    }

    function createSitesDirectory()
    {
        $this->files->ensureDirExists( self::sitePath(), user() );
    }

    function createNginxConfigurationDirectory()
    {
        $this->files->ensureDirExists( MEGH_HOME_PATH . '/nginx/certs', user() );
        $this->files->ensureDirExists( MEGH_HOME_PATH . '/nginx/conf.d', user() );
        $this->files->ensureDirExists( MEGH_HOME_PATH . '/nginx/dhparam', user() );
        $this->files->ensureDirExists( MEGH_HOME_PATH . '/nginx/htpasswd', user() );
        $this->files->ensureDirExists( MEGH_HOME_PATH . '/nginx/vhost.d', user() );
    }

    function writeBaseConfiguration()
    {
        if ( ! $this->files->exists($this->path())) {
            $this->write([
                'sites' => []
            ]);
        }
    }

    /**
     * Add the given path to the configuration.
     *
     * @param  string  $path
     * @param  array  $info
     * @return void
     */
    function addSite($site, $info = [])
    {
        $this->write(tap($this->read(), function (&$config) use ($site, $info) {
            $config['sites'][$site] = $info;
        }));
    }

    /**
     * Remove the given path from the configuration.
     *
     * @param  string  $path
     * @return void
     */
    function removeSite($site)
    {
        $this->write(tap($this->read(), function (&$config) use ($site) {
            unset( $config['sites'][$site] );
        }));
    }

    /**
     * Read the configuration file as JSON.
     *
     * @return array
     */
    function read()
    {
        return json_decode( $this->files->get( $this->path() ), true);
    }

    /**
     * Update a specific key in the configuration file.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return array
     */
    function updateKey($key, $value)
    {
        return tap($this->read(), function (&$config) use ($key, $value) {
            $config[$key] = $value;
            $this->write( $config );
        });
    }

    /**
     * Write the given configuration to disk.
     *
     * @param  array  $config
     * @return void
     */
    function write($config)
    {
        $this->files->putAsUser( $this->path(), json_encode(
            $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . PHP_EOL );
    }

    private function path()
    {
        return MEGH_HOME_PATH . '/config.json';
    }

    public static function sitePath()
    {
        return $_SERVER['HOME'] . '/sites';
    }
}
