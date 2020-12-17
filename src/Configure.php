<?php
namespace Megh;

use const MEGH_HOME_PATH;
use Symfony\Component\Yaml\Yaml;

/**
 * Configuration class
 */
class Configure
{
    /**
     * @var Filesystem
     */
    private $files;

    /**
     * Constructor
     */
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
        $this->createEnvFile();
    }

    /**
     * Create configuration directory
     *
     * @return void
     */
    public function createConfigurationDirectory()
    {
        Helper::verbose('Creating configuration directory on: ' . MEGH_HOME_PATH);
        
        $this->files->ensureDirExists(MEGH_HOME_PATH, Helper::user());
    }

    /**
     * Create sites directory
     *
     * @return void
     */
    public function createSitesDirectory()
    {
        Helper::verbose('Creating sites directory on: ' . self::sitePath());

        $this->files->ensureDirExists(self::sitePath(), Helper::user());
    }

    /**
     * Create nginx config directory
     *
     * @return void
     */
    public function createNginxConfigurationDirectory()
    {
        Helper::verbose('Creating nginx configuration directory on: ' . MEGH_HOME_PATH . '/nginx');

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
        Helper::verbose('Creating global service docker-compose.yml file on: ' . MEGH_HOME_PATH);

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
     * Create an env file
     *
     * @return void
     */
    protected function createEnvFile()
    {
        $path = MEGH_HOME_PATH . '/.env';

        if (!$this->files->exists($path)) {
            $password = Helper::password();
            $content = 'MYSQL_ROOT_PASSWORD=' . $password . "\n";

            $this->files->put($path, $content);
        }
    }

    /**
     * Path to the sites directory
     *
     * @return string
     */
    public static function sitePath()
    {
        $home = $_SERVER['HOME'];

        return Helper::isMac() ? $home . '/Sites' : $home;
    }
}
