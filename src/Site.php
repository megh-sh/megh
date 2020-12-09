<?php
namespace Megh;

use Symfony\Component\Yaml\Yaml;
use Exception;

/**
 * Site
 */
class Site
{
    use UsesCli;

    /**
     * The site name
     *
     * @var string
     */
    private $sitename;

    /**
     * The type of the site
     *
     * Allowed types are: php, wordpress, static, laravel, bedrock
     *
     * @var string
     */
    private $type;

    /**
     * The PHP version
     *
     * @var string
     */
    private $php;

    /**
     * The webroot of the site
     *
     * @var string
     */
    private $root;

    /**
     * Path to the website
     *
     * @var string
     */
    private $siteDir;

    /**
     * Initialize the class
     *
     * @param string $sitename
     */
    public function __construct($sitename)
    {
        $this->sitename = $sitename;
        $this->siteDir = Configuration::sitePath() . '/' . $this->sitename;
    }

    /**
     * Create a site
     *
     * @param string $type
     * @param string $php
     * @param string $root
     *
     * @return void
     */
    public function create($type, $php, $root)
    {
        $this->type = $type;
        $this->php  = $php;
        $this->root = $root;

        $this->configureSite();
        $this->enable();

        if ('wp' === $this->type) {
            $this->createDatabase();
            $this->generateWpConfig();
            $this->installWp();
        }

        // $this->addHost();

        // (new Configuration())->addSite($this->sitename, ['something', 'other']);
    }

    /**
     * Delete the site
     *
     * @return void
     */
    public function delete()
    {
        $this->dropDatabase();
        $this->disable();
        // $this->deleteHost();
        $this->deleteFolder();

        (new Configuration())->removeSite($this->sitename);
    }

    /**
     * Bootstrap the site folder
     *
     * @return void
     */
    private function configureSite()
    {
        $this->copyFiles();
        $this->generateDockerCompose();

        if ('wp' === $this->type) {
            $this->downloadWp();
        }
    }

    /**
     * Copy the configs and creates initial dirs.
     *
     * @return void
     */
    private function copyFiles()
    {
        output('Copying files');

        $files   = new Filesystem();
        $confDir = MEGH_DIR . '/configs';

        $files->ensureDirExists($this->siteDir, user());
        $files->ensureDirExists($this->siteDir . '/app', user());
        $files->ensureDirExists($this->siteDir . '/conf', user());
        $files->ensureDirExists($this->siteDir . '/data', user());
        $files->ensureDirExists($this->siteDir . '/data/logs', user());
        $files->ensureDirExists($this->siteDir . '/data/mysql', user());
        $files->ensureDirExists($this->siteDir . '/data/redis', user());
        $files->ensureDirExists($this->siteDir . '/data/backups', user());
        $files->ensureDirExists($this->siteDir . '/data/nginx-cache', user());

        // env file
        $password = bin2hex(openssl_random_pseudo_bytes(8));
        $envFile = $files->get($confDir . '/.env.example');
        $envFile = str_replace([
            '{HOSTNAME}',
            '{MYSQL_HOSTNAME}',
            '{DATABASE}',
            '{MYSQL_USERNAME}',
            '{PASSWORD}'
        ], [
            $this->sitename,
            'mariadb',
            str_replace(['.', '-'], '_', $this->sitename),
            str_replace(['.', '-'], '_', $this->sitename),
            $password
        ], $envFile);
        $files->put($this->siteDir.'/.env', $envFile);

        // nginx conf
        $files->copyDir($confDir . '/default/config', $this->siteDir . '/conf');

        // replace nginx hostname
        $nginxConf = $this->siteDir . '/conf/nginx/default.conf';
        $nginxCont = $files->get($nginxConf);
        $nginxCont = str_replace('NGINX_HOST', $this->sitename, $nginxCont);
        $files->put($nginxConf, $nginxCont);

        // default index.php
        $files->put($this->siteDir . '/app/index.php', "<h1>{$this->sitename}</h1>");
        $files->append($this->siteDir . '/app/index.php', <<<'EOD'

<?php
$conn = new mysqli('mariadb', 'root', 'root');

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected successfully";
 
EOD);
    }

    /**
     * Generate a `docker-composer.yml` file
     *
     * @return void
     */
    private function generateDockerCompose()
    {
        output('Generating docker-compose.yml');

        $files   = new Filesystem();

        $config = [
            'version' => '3',
            'services' => [],
            'networks' => [
                'site-network' => [
                    'name' => '${VHOST_NAME}'
                ],
                'nginx-proxy' => [
                    'external' => [
                        'name' => 'nginx-proxy'
                    ]
                ],
                'db-network' => [
                    'external' => [
                        'name' => 'db-network'
                    ]
                ]
            ]
        ];

        $config['services']['nginx'] = [
            'image'       => 'nginx:alpine',
            'restart'     => 'always',
            'environment' => [
                'VIRTUAL_HOST=${VHOST_NAME}'
            ],
            'volumes' => [
                './app:/var/www/html',
                './conf/nginx/common:/etc/nginx/common',
                './conf/nginx/default.conf:/etc/nginx/conf.d/default.conf',
                './conf/nginx/nginx.conf:/etc/nginx/nginx.conf',
                './data/logs/nginx:/var/log/nginx',
                './data/nginx-cache:/var/run/nginx-cache'
            ],
            'networks' => [
                'site-network',
                'nginx-proxy'
            ]
        ];

        if ($this->requiresPhp()) {
            $config['services']['php'] = [
                'image'   => 'tareq1988/php-wp:7.4',
                'volumes' => [
                    './app:/var/www/html'
                ],
                'networks' => [
                    'site-network',
                    'db-network'
                ]
            ];

            $config['services']['nginx']['depends_on'] = [ 'php' ];
            // $config['networks']['db-network']['external'] = true;

            // mariadb
            // $config['services']['mariadb'] = [
            //     'image'          => 'mariadb:10.3',
            //     'restart'        => 'always',
            //     // 'ports'          => [ '3306:3306' ],
            //     'environment'    => [
            //         'MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}',
            //         'MYSQL_DATABASE=${MYSQL_DATABASE}',
            //         'MYSQL_USER=${MYSQL_USER}',
            //         'MYSQL_PASSWORD=${MYSQL_PASSWORD}'
            //     ],
            //     'volumes'        => [
            //         './data/mysql:/var/lib/mysql'
            //     ],
            //     'networks' => ['site-network'],
            //     'healthcheck' => [
            //         'test' => 'mysqladmin ping -h 127.0.0.1 -u $$MYSQL_USER --password=$$MYSQL_PASSWORD',
            //         'interval' => '60s',
            //         'timeout' => '3s',
            //         'start_period' => '10s',
            //     ]
            // ];

            // $config['services']['redis'] = [
            //     'image'          => 'redis:6-alpine',
            //     'container_name' => 'redis',
            //     'restart'        => 'always',
            //     // 'ports'          => [ '6379:6379' ],
            //     'volumes'        => [
            //         './data/redis:/data'
            //     ],
            //     'networks' => ['site-network']
            // ];
        }

        $yaml = Yaml::dump($config, 4, 2);
        $files->put($this->siteDir . '/docker-compose.yml', $yaml);
    }

    /**
     * If the site requires PHP support
     *
     * @return boolean
     */
    private function requiresPhp()
    {
        return in_array($this->type, ['php', 'wp'], true);
    }

    private function downloadWp()
    {
        output('Downloading WordPress');

        $wp = new WP();
        $wp->setPath($this->siteDir . '/app');
        $wp->download();
    }

    private function createDatabase()
    {
        output('Creating databse');

        $docker = new Docker();
        $config = $this->getEnv();

        $create = sprintf('CREATE USER "%1$s"@"%%" IDENTIFIED BY "%2$s"; CREATE DATABASE `%3$s`; GRANT ALL PRIVILEGES ON `%3$s`.* TO "%1$s"@"%%"; FLUSH PRIVILEGES;', $config['MYSQL_USER'], $config['MYSQL_PASSWORD'], $config['MYSQL_DATABASE']);
        $cmd = sprintf('mysql -h mariadb -u root -proot -e\'%s\'', $create);
        $docker->runCommand($cmd, MEGH_HOME_PATH, 'mariadb');
    }

    private function dropDatabase()
    {
        output('Deleting databse');

        $docker = new Docker();
        $config = $this->getEnv();

        $create = sprintf('DROP DATABASE `%s`; DROP USER "%s"@"%%";', $config['MYSQL_DATABASE'], $config['MYSQL_USER']);
        $cmd = sprintf('mysql -h mariadb -u root -proot -e\'%s\'', $create);
        $docker->runCommand($cmd, MEGH_HOME_PATH, 'mariadb');
    }

    private function installWp()
    {
        output('Installing WordPress');

        $wp = new WP();
        $wp->setPath($this->siteDir);
        $wp->install($this->sitename, $this->sitename, 'admin', 'admin', 'admin@gmail.com');
    }

    public function generateWpConfig()
    {
        output('Generating WordPress Configuration');

        $config = $this->getEnv();

        $wp = new WP();
        $wp->setPath($this->siteDir);
        $wp->generateConfig([
            'dbname' => $config['MYSQL_DATABASE'],
            'dbuser' => $config['MYSQL_USER'],
            'dbpass' => $config['MYSQL_PASSWORD'],
            'dbhost' => 'mariadb'
        ]);
    }
    
    /**
     * Get ENV variables of a site
     *
     * @return array
     */
    public function getEnv()
    {
        $config = \Dotenv\Dotenv::parse(file_get_contents($this->siteDir . '/.env'));

        return $config;
    }

    /**
     * Enable a site
     *
     * @return void
     */
    public function enable()
    {
        $docker = new Docker();

        try {
            // output('Creating network: ' . $this->sitename);
            // $docker->createNetwork($this->sitename);

            // output('Connecting "' . $this->sitename . '" network to "nginx-proxy"');
            // $docker->connectNetwork($this->sitename);

            output('Running docker-compose up -d');
            $docker->composeUp($this->sitename);
        } catch (Exception $e) {
            warning($e->getMessage());
        }
    }

    /**
     * Disable a site
     *
     * @return void
     */
    public function disable()
    {
        $docker = new Docker();

        try {
            // output('Disconnecting from "nginx-proxy" network');
            // $docker->disconnectNetwork($this->sitename);

            // output('Removing the network: ' . $this->sitename);
            // $docker->removeNetwork($this->sitename);

            output('Taking down docker-compose');
            $docker->composeDown($this->sitename);
        } catch (\Exception $e) {
            warning($e->getMessage());
        }
    }

    /**
     * Update the host entry
     *
     * @return void
     */
    private function addHost()
    {
        $path       = '/etc/hosts';
        $line       = "\n127.0.0.1\t$this->sitename";

        $filesystem = new Filesystem();
        $content    = $filesystem->get($path);

        if (! preg_match("/\s+$this->sitename\$/m", $content)) {
            // $filesystem->append( $path, $line );
            $this->cli()->run('echo "' . $line . '" | sudo tee -a ' . $path);

            info('Host entry successfully added.');
        } else {
            warning('Host entry already exists. Skipped.');
        }
    }

    private function deleteHost()
    {
        output('Deleting Hosts file entry');
        $path       = '/etc/hosts';
        $line       = "127.0.0.1\t$this->sitename";

        $filesystem = new Filesystem();
        $content    = $filesystem->get($path);

        if (preg_match("/\s+$this->sitename\$/m", $content)) {
            $this->cli()->run('sudo sed -i "" "/^' . $line . '/d" ' . $path);
        }
    }

    private function deleteFolder()
    {
        output('Deleting site folder');

        $siteDir = Configuration::sitePath() . '/' . $this->sitename;

        $this->cli()->run('rm -rf ' . $siteDir);
    }
}
