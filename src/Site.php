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
        $this->siteDir = Configuration::sitePath() . '/' . $this->sitename;

        $this->configureSite();
        $this->enable();

        if ('wp' === $this->type) {
            $this->generateWpConfig();
        }

        $this->addHost();

        // (new Configuration())->addSite($this->sitename, ['something', 'other']);
    }

    /**
     * Delete the site
     *
     * @return void
     */
    public function delete()
    {
        $this->disable();
        $this->deleteHost();
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
            $this->installWp();
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
            'wordpress',
            'wp_user',
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
        $files->put($this->siteDir . '/app/index.php', '<?php phpinfo();');
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
                    'external' => [
                        'name' => '${VHOST_NAME}'
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
            'networks' => ['site-network']
        ];

        if ($this->requiresPhp()) {
            $config['services']['php'] = [
                'image'   => 'tareq1988/php-wp:7.4',
                'volumes' => [
                    './app:/var/www/html'
                ],
                'depends_on' => ['mariadb'],
                'networks' => ['site-network']
            ];

            $config['services']['nginx']['depends_on'] = [ 'php' ];

            // mariadb
            $config['services']['mariadb'] = [
                'image'          => 'mariadb:10.3',
                'restart'        => 'always',
                'ports'          => [ '3305:3306' ],
                'environment'    => [
                    'MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}',
                    'MYSQL_DATABASE=${MYSQL_DATABASE}',
                    'MYSQL_USER=${MYSQL_USER}',
                    'MYSQL_PASSWORD=${MYSQL_PASSWORD}'
                ],
                'volumes'        => [
                    './data/mysql:/var/lib/mysql'
                ],
                'networks' => ['site-network']
            ];

            $config['services']['redis'] = [
                'image'          => 'redis:6-alpine',
                'container_name' => 'redis',
                'restart'        => 'always',
                'ports'          => [ '6379:6379' ],
                'volumes'        => [
                    './data/redis:/data'
                ],
                'networks' => ['site-network']
            ];
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

    private function installWp()
    {
        output('Installing WordPress');

        $wp = new WP();
        $wp->setPath($this->siteDir . '/app');
        $wp->download();
    }

    private function generateWpConfig()
    {
        output('Generating WordPress Configuration');

        $config = \Dotenv\Dotenv::parse(file_get_contents($this->siteDir . '/.env'));

        $wp = new WP();
        $wp->setPath($this->siteDir);
        $wp->generateConfig([
            'dbname' => $config['MYSQL_DATABASE'],
            'dbuser' => $config['MYSQL_USER'],
            'dbpass' => $config['MYSQL_PASSWORD'],
            'dbhost' => 'mariadb',
            'dbport' => '3305',
        ]);
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
            output('Creating network: ' . $this->sitename);
            $docker->createNetwork($this->sitename);

            output('Connecting "' . $this->sitename . '" network to "nginx-proxy"');
            $docker->connectNetwork($this->sitename);

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
            output('Taking down docker-compose');
            $docker->composeDown($this->sitename);

            output('Disconnecting from "nginx-proxy" network');
            $docker->disconnectNetwork($this->sitename);

            output('Removing the network: ' . $this->sitename);
            $docker->removeNetwork($this->sitename);
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
