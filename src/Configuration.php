<?php
namespace Megh;

use Symfony\Component\Yaml\Yaml;

/**
 * Configuration class
 */
class Configuration
{
    private $files;

    public function __construct()
    {
        $this->files = new Filesystem();
    }

    public function install()
    {
        $this->createConfigurationDirectory();
        $this->createSitesDirectory();
        $this->createNginxConfigurationDirectory();
        $this->writeBaseDockerCompose();

        // $this->initDocker();
    }

    public function initDocker()
    {
        output('Initializing docker nginx-proxy');

        (new Docker())->initProxy();
    }

    public function createConfigurationDirectory()
    {
        $this->files->ensureDirExists(MEGH_HOME_PATH, user());
    }

    public function createSitesDirectory()
    {
        $this->files->ensureDirExists(self::sitePath(), user());
    }

    public function createNginxConfigurationDirectory()
    {
        $this->files->ensureDirExists(MEGH_HOME_PATH . '/nginx/certs', user());
        $this->files->ensureDirExists(MEGH_HOME_PATH . '/nginx/conf.d', user());
        $this->files->ensureDirExists(MEGH_HOME_PATH . '/nginx/dhparam', user());
        $this->files->ensureDirExists(MEGH_HOME_PATH . '/nginx/htpasswd', user());
        $this->files->ensureDirExists(MEGH_HOME_PATH . '/nginx/vhost.d', user());
    }

    public function writeBaseDockerCompose()
    {
        $config = [
            'version' => '3',
            'services' => [
                'nginx-proxy' => [
                    'container_name' => 'nginx-proxy',
                    'image' => 'jwilder/nginx-proxy:alpine',
                    'restart' => 'always',
                    'ports' => [
                        '80:80',
                        '443:443'
                    ],
                    'volumes' => [
                        './nginx/certs:/etc/nginx/certs',
                        './nginx/dhparam:/etc/nginx/dhparam',
                        './nginx/conf.d:/etc/nginx/conf.d',
                        './nginx/htpasswd:/etc/nginx/htpasswd',
                        './nginx/vhost.d:/etc/nginx/vhost.d',
                        '/var/run/docker.sock:/tmp/docker.sock:ro'
                    ],
                    'networks' => [
                        'nginx-proxy'
                    ]
                ],
                'mariadb' => [
                    'container_name' => 'mariadb',
                    'image'          => 'mariadb:10.3',
                    'restart'        => 'always',
                    'ports'          => [ '3306:3306' ],
                    'environment'    => [
                        'MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}',
                        // 'MYSQL_DATABASE=${MYSQL_DATABASE}',
                        // 'MYSQL_USER=${MYSQL_USER}',
                        // 'MYSQL_PASSWORD=${MYSQL_PASSWORD}'
                    ],
                    'volumes'        => [
                        './data/mysql:/var/lib/mysql'
                    ],
                    'networks' => [
                        'db-network'
                    ],
                ]
            ],
            'networks' => [
                'nginx-proxy' => [
                    'external' => true
                ],
                'db-network' => [
                    'external' => [
                        'name' => 'db-network'
                    ]
                ],
            ]
        ];

        $yaml = Yaml::dump($config, 4, 2);
        $this->files->put(MEGH_HOME_PATH . '/docker-compose.yml', $yaml);
    }

    /**
     * Add the given path to the configuration.
     *
     * @param  string  $path
     * @param  array  $info
     * @return void
     */
    public function addSite($site, $info = [])
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
    public function removeSite($site)
    {
        $this->write(tap($this->read(), function (&$config) use ($site) {
            unset($config['sites'][$site]);
        }));
    }

    /**
     * Read the configuration file as JSON.
     *
     * @return array
     */
    public function read()
    {
        return json_decode($this->files->get($this->path()), true);
    }

    /**
     * Update a specific key in the configuration file.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return array
     */
    public function updateKey($key, $value)
    {
        return tap($this->read(), function (&$config) use ($key, $value) {
            $config[$key] = $value;
            $this->write($config);
        });
    }

    /**
     * Write the given configuration to disk.
     *
     * @param  array  $config
     * @return void
     */
    public function write($config)
    {
        $this->files->putAsUser($this->path(), json_encode(
            $config,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . PHP_EOL);
    }

    private function path()
    {
        return MEGH_HOME_PATH . '/config.json';
    }

    public static function sitePath()
    {
        return $_SERVER['HOME'] . '/megh-sites';
    }
}
