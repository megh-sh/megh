<?php
namespace Megh;

use Exception;
use const MEGH_HOME_PATH;
use Symfony\Component\Yaml\Yaml;

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
     * Undocumented variable
     *
     * @var array
     */
    private $supportedPhp = [ '7.2', '7.3', '7.4' ];

    /**
     * WordPress username
     *
     * @var string
     */
    private $username;
    
    /**
     * Password
     *
     * @var string
     */
    private $password;

    /**
     * Email
     *
     * @var string
     */
    private $email;

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
        $this->siteDir = Configure::sitePath() . '/' . $this->sitename;
    }

    /**
     * Create a site
     *
     * @param string $type
     * @param string $php
     * @param bool $root
     *
     * @return void
     */
    public function create($type, $php, $addHost, $username, $email, $password)
    {
        $this->type     = $type;
        $this->php      = $php;
        $this->username = $username;
        $this->email    = $email;
        $this->password = $password;

        if (!in_array($php, $this->supportedPhp, true)) {
            throw new Exception('The PHP version is not supported.');
        }

        $this->configureSite();
        $this->enable();

        if ($this->requiresPhp()) {
            $this->createDatabase();
        }

        if ('wp' === $this->type) {
            $this->downloadWp();
            $this->generateWpConfig();
            $this->installWp();
        }

        if ('laravel' === $this->type) {
            $this->downloadLaravel();
            $this->updateLaravelEnv();
        }

        if ($addHost) {
            $this->addHost();
        }

        if ('wp' === $this->type) {
            Helper::line('');

            $infoTable = [
                [ 'URL',  'http://' . $this->sitename ],
                [ 'Username', $this->username ],
                [ 'Password', $this->password ],
                [ 'Email', $this->email ]
            ];

            Helper::table([], $infoTable);
        }

        // (new Configuration())->addSite($this->sitename, ['something', 'other']);
    }

    /**
     * Delete the site
     *
     * @return void
     */
    public function delete()
    {
        if (!$this->siteExists()) {
            throw new Exception('The given site does not exist.');
        }

        $this->dropDatabase();
        $this->disable();
        // $this->deleteHost();
        $this->deleteFolder();
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
        $this->fixPermission();
    }

    /**
     * Copy the configs and creates initial dirs.
     *
     * @return void
     */
    private function copyFiles()
    {
        Helper::verbose('Copying files');

        $files   = new Filesystem();
        $confDir = MEGH_DIR . '/configs';

        $files->ensureDirExists($this->siteDir);
        $files->ensureDirExists($this->siteDir . '/app');
        $files->ensureDirExists($this->siteDir . '/conf');
        $files->ensureDirExists($this->siteDir . '/data');
        $files->ensureDirExists($this->siteDir . '/data/logs');
        $files->ensureDirExists($this->siteDir . '/data/logs/nginx');
        $files->ensureDirExists($this->siteDir . '/data/backups');
        $files->ensureDirExists($this->siteDir . '/data/nginx-cache');

        // env file
        $password = Helper::password();
        $envContent = $files->get($confDir . '/.env.example');
        $envContent = str_replace([
            '{HOSTNAME}',
        ], [
            $this->sitename,
        ], $envContent);

        if ($this->requiresPhp()) {
            $dbname = str_replace(['.', '-'], '_', $this->sitename);

            $envContent .= 'MYSQL_HOST=mariadb' . "\n";
            $envContent .= 'MYSQL_DATABASE=' . $dbname . "\n";
            $envContent .= 'MYSQL_USER=' . $dbname . "\n";
            $envContent .= 'MYSQL_PASSWORD=' . $password . "\n";
        }

        $files->put($this->siteDir.'/.env', $envContent);

        // nginx conf
        $files->copyDir($confDir . '/default/config', $this->siteDir . '/conf');

        // replace nginx hostname
        $nginxConf = $this->siteDir . '/conf/nginx/default.conf';
        $nginxCont = $files->get($nginxConf);
        $nginxCont = str_replace('NGINX_HOST', $this->sitename, $nginxCont);

        // change the laravel web root
        if ('laravel' === $this->type) {
            $nginxCont = str_replace('root /var/www/html', 'root /var/www/html/public', $nginxCont);
        }

        $files->put($nginxConf, $nginxCont);

        // default index.php
        $files->put($this->siteDir . '/app/index.php', "<h1>{$this->sitename}</h1>");
    }

    /**
     * Generate a `docker-composer.yml` file
     *
     * @return void
     */
    private function generateDockerCompose()
    {
        Helper::verbose('Generating docker-compose.yml');

        $files   = new Filesystem();

        $config = [
            'version' => '3.5',
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
                'image'   => 'meghsh/php:' . $this->php,
                'volumes' => [
                    './app:/var/www/html'
                ],
                'networks' => [
                    'site-network',
                    'db-network'
                ]
            ];

            $config['services']['nginx']['depends_on'] = [ 'php' ];
        }

        $yaml = Yaml::dump($config, 4, 2);
        $files->put($this->siteDir . '/docker-compose.yml', $yaml);
    }

    /**
     * Fix directory permission
     *
     * @return void
     */
    public function fixPermission()
    {
        $docker = new Docker();

        $command = 'sh -c "chown -R www-data:www-data /var/www/html"';
        $docker->runCommand($command, $this->siteDir, 'php', 'root');
    }

    /**
     * If the site requires PHP support
     *
     * @return boolean
     */
    private function requiresPhp()
    {
        return in_array($this->type, ['php', 'wp', 'laravel'], true);
    }

    /**
     * Download WordPress
     *
     * @return void
     */
    private function downloadWp()
    {
        Helper::verbose('Downloading WordPress');

        $wp = new WP();
        $wp->setPath($this->siteDir);
        $wp->download();
    }

    /**
     * Download laravel
     *
     * @return void
     */
    private function downloadLaravel()
    {
        Helper::verbose('Downloading Laravel');

        (new Docker())->runCommand('sh -c "rm index.php && composer create-project laravel/laravel --prefer-dist --no-dev ."', $this->siteDir);
    }

    private function updateLaravelEnv()
    {
        Helper::verbose('Updating .env file with credentials');

        $files = new Filesystem();

        $config = $this->getEnv();
        $laravelEnv = $files->get($this->siteDir . '/app/.env');

        $laravelEnv = str_replace(
            [
                'DB_HOST=127.0.0.1',
                'DB_DATABASE=laravel',
                'DB_USERNAME=root',
                'DB_PASSWORD='
            ],
            [
                'DB_HOST=mariadb',
                'DB_DATABASE=' . $config['MYSQL_DATABASE'],
                'DB_USERNAME=' . $config['MYSQL_USER'],
                'DB_PASSWORD=' . $config['MYSQL_PASSWORD'],
            ],
            $laravelEnv
        );

        $files->put($this->siteDir . '/app/.env', $laravelEnv);
    }

    /**
     * Create database
     *
     * @return void
     */
    private function createDatabase()
    {
        Helper::verbose('Creating database');

        $docker = new Docker();
        $config = $this->getEnv();

        $create = sprintf('CREATE USER "%1$s"@"%%" IDENTIFIED BY "%2$s"; CREATE DATABASE `%3$s`; GRANT ALL PRIVILEGES ON `%3$s`.* TO "%1$s"@"%%"; FLUSH PRIVILEGES;', $config['MYSQL_USER'], $config['MYSQL_PASSWORD'], $config['MYSQL_DATABASE']);
        $cmd = sprintf('mysql -h mariadb -u root -p%s -e\'%s\'', $this->getMySqlRootPass(), $create);
        $docker->runCommand($cmd, MEGH_HOME_PATH, 'mariadb');
    }

    /**
     * Drop database
     *
     * @return void
     */
    private function dropDatabase()
    {
        Helper::verbose('Deleting database');

        $docker = new Docker();
        $config = $this->getEnv();

        if (isset($config['MYSQL_DATABASE'])) {
            $create = sprintf('DROP DATABASE `%s`; DROP USER "%s"@"%%";', $config['MYSQL_DATABASE'], $config['MYSQL_USER']);
            $cmd = sprintf('mysql -h mariadb -u root -p%s -e\'%s\'', $this->getMySqlRootPass(), $create);
            $docker->runCommand($cmd, MEGH_HOME_PATH, 'mariadb');
        }
    }

    private function installWp()
    {
        Helper::verbose('Installing WordPress');

        if (!$this->email) {
            $this->email = $this->username . '@' . $this->sitename;
        }

        if (!$this->password) {
            $this->password = Helper::password();
        }

        $wp = new WP();
        $wp->setPath($this->siteDir);
        $wp->install($this->sitename, $this->sitename, $this->username, $this->password, $this->email);
    }

    public function generateWpConfig()
    {
        Helper::verbose('Generating WordPress Configuration');

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
     * MySQL Root Password
     *
     * @return string
     */
    public function getMySqlRootPass()
    {
        $config = \Dotenv\Dotenv::parse(file_get_contents(MEGH_HOME_PATH . '/.env'));

        return isset($config['MYSQL_ROOT_PASSWORD']) ? $config['MYSQL_ROOT_PASSWORD'] : '';
    }

    /**
     * Enable a site
     *
     * @return void
     */
    public function enable()
    {
        if (!$this->siteExists()) {
            throw new Exception('The given site does not exist.');
        }

        $docker = new Docker();

        try {
            // Helper::verbose('Creating network: ' . $this->sitename);
            // $docker->createNetwork($this->sitename);

            // Helper::verbose('Connecting "' . $this->sitename . '" network to "nginx-proxy"');
            // $docker->connectNetwork($this->sitename);

            Helper::verbose('Running docker-compose up -d');
            $docker->composeUp($this->siteDir);
        } catch (Exception $e) {
            Helper::warning($e->getMessage());
        }
    }

    /**
     * Disable a site
     *
     * @return void
     */
    public function disable()
    {
        if (!$this->siteExists()) {
            throw new Exception('The given site does not exist.');
        }

        $docker = new Docker();

        try {
            // Helper::verbose('Disconnecting from "nginx-proxy" network');
            // $docker->disconnectNetwork($this->sitename);

            // Helper::verbose('Removing the network: ' . $this->sitename);
            // $docker->removeNetwork($this->sitename);

            Helper::verbose('Taking down docker-compose');
            $docker->composeDown($this->siteDir);
        } catch (\Exception $e) {
            Helper::warning($e->getMessage());
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

            Helper::verbose('Host entry successfully added.');
        } else {
            Helper::warning('Host entry already exists. Skipped.');
        }
    }

    /**
     * Delete from /etc/hosts
     *
     * @return void
     */
    private function deleteHost()
    {
        Helper::verbose('Deleting Hosts file entry');
        $path       = '/etc/hosts';
        $line       = "127.0.0.1\t$this->sitename";

        $filesystem = new Filesystem();
        $content    = $filesystem->get($path);

        if (preg_match("/\s+$this->sitename\$/m", $content)) {
            $this->cli()->run('sudo sed -i "" "/^' . $line . '/d" ' . $path);
        }
    }

    /**
     * Delete site folder
     *
     * @return void
     */
    private function deleteFolder()
    {
        Helper::verbose('Deleting site folder');

        $siteDir = Configure::sitePath() . '/' . $this->sitename;

        $this->cli()->run('rm -rf ' . $siteDir);
    }

    /**
     * Check if a site exists
     *
     * @return bool
     */
    protected function siteExists()
    {
        return (new Filesystem())->exists($this->siteDir);
    }
}
