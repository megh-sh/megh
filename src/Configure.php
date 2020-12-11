<?php
namespace Megh;

use Symfony\Component\Yaml\Yaml;

/**
 * Configuration class
 */
class Configure
{
    private $files;

    public function __construct()
    {
        $this->files = new Filesystem();
    }

    /**
     * Run the installer
     *
     * @return void
     */
    public function install()
    {
        $this->createConfigurationDirectory();
        $this->createSitesDirectory();
        $this->createNginxConfigurationDirectory();
        $this->writeBaseDockerCompose();
    }

    /**
     * Create configuration directory
     *
     * @return void
     */
    public function createConfigurationDirectory()
    {
        Helper::verbose('Creating configuration directory');
        
        $this->files->ensureDirExists(MEGH_HOME_PATH, Helper::user());
    }

    /**
     * Create sites directory
     *
     * @return void
     */
    public function createSitesDirectory()
    {
        Helper::verbose('Creating sites directory');

        $this->files->ensureDirExists(self::sitePath(), Helper::user());
    }

    /**
     * Create nginx config directory
     *
     * @return void
     */
    public function createNginxConfigurationDirectory()
    {
        Helper::verbose('Creating nginx configuration directory');

        $this->files->ensureDirExists(MEGH_HOME_PATH . '/nginx/certs', Helper::user());
        $this->files->ensureDirExists(MEGH_HOME_PATH . '/nginx/conf.d', Helper::user());
        $this->files->ensureDirExists(MEGH_HOME_PATH . '/nginx/dhparam', Helper::user());
        $this->files->ensureDirExists(MEGH_HOME_PATH . '/nginx/htpasswd', Helper::user());
        $this->files->ensureDirExists(MEGH_HOME_PATH . '/nginx/vhost.d', Helper::user());
    }

    /**
     * Write docker compose file
     *
     * @return void
     */
    public function writeBaseDockerCompose()
    {
        Helper::verbose('Creating docker-compose.yml file');

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
                    'external' => [
                        'name' => 'nginx-proxy'
                    ]
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
     * Path to the sites directory
     *
     * @return string
     */
    public static function sitePath()
    {
        return $_SERVER['HOME'] . '/megh-sites';
    }
}