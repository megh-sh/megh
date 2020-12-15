<?php
namespace Megh;

/**
 * Configuration class
 */
class WP
{
    use UsesCli;

    /**
     * WordPress path
     *
     * @var string
     */
    private $path;

    /**
     * Set path to WordPress
     *
     * @param string $path
     *
     * @return this
     */
    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Download WordPress to the given path
     *
     * @param  string $path
     *
     * @return string
     */
    public function download()
    {
        return $this->cli()->run('wp core download --allow-root --path=' . $this->path);
    }

    /**
     * Generate wp-config.php
     *
     * @param  string $container
     * @param  string $config
     * @param  string $path
     *
     * @return string
     */
    public function generateConfig($config)
    {
        $docker = new Docker();

        // echo $this->path;
        // return;
        return $docker->runCommand("wp core config --dbname={$config['dbname']} --dbuser={$config['dbuser']} --dbpass={$config['dbpass']} --dbhost={$config['dbhost']} --allow-root --path=/var/www/html --extra-php <<'PHP'\n" . $this->extraPhp() . "\nPHP", $this->path);
    }

    public function extraPhp()
    {
        $var = <<<EOF
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'DISALLOW_FILE_EDIT', true );
define( 'WP_REDIS_HOST', 'redis' );
define( 'WP_REDIS_DATABASE', 0 );

/**
 * Allow WordPress to detect HTTPS when used behind a reverse proxy or a load balancer
 * See https://codex.wordpress.org/Function_Reference/is_ssl#Notes
 */
if (isset(\$_SERVER['HTTP_X_FORWARDED_PROTO']) && \$_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    \$_SERVER['HTTPS'] = 'on';
}
EOF;

        return $var;
    }

    /**
     * Install WordPress
     *
     * @param  string $container
     * @param  string $path
     * @param  string $url
     * @param  string $title
     * @param  string $username
     * @param  string $pass
     * @param  string $email
     *
     * @return void
     */
    public function install($url, $title, $username, $pass, $email)
    {
        $docker = new Docker();
        
        $command = "sh -c '";
        $command .= sprintf('wp core install --url="http://%s" --title="%s" --admin_user="%s" --admin_password="%s" --admin_email="%s" && ', $url, $title, $username, $pass, $email);
        $command .= 'wp rewrite structure "/%postname%/" --hard && ';
        $command .= "wp plugin delete akismet hello'";

        $docker->runCommand($command, $this->path);
    }

    /**
     * Install plugins on a site
     *
     * @param  string $container
     * @param  string $path
     * @param  array  $plugins
     *
     * @return string
     */
    public function installPlugins($container, $path, array $plugins)
    {
        if (!$plugins) {
            return;
        }
        return $this->cli()->run($container, 'wp plugin install --activate ' . implode(' ', $plugins) . ' --allow-root --path=' . $path);
    }

    public function activatePlugins($container, $path)
    {
        return $this->cli()->run($container, 'wp plugin activate --all --allow-root --path='. $path);
    }

    /**
     * @param $container
     * @param $path
     * @return string
     */
    public function installMUPlugins($container, $path)
    {
        $this->cli()->run($container, 'sudo apt-get install unzip'); // in case unzip was not installed
        $this->cli()->run($container, 'wget https://storage.googleapis.com/bediq-backups/bediq-core/mu-plugins.zip');
        $this->cli()->run($container, 'unzip mu-plugins.zip');
        return $this->cli()->run($container, 'mv mu-plugins ' . $path .'/wp-content/');
    }

    /**
     * @param $container
     * @param $path
     * @return string
     */
    public function defaultDataImport($container, $path)
    {
        $this->cli()->run($container, 'wget https://storage.googleapis.com/bediq-backups/bediq-core/bediq.sql');
        return $this->cli()->run($container, 'wp db import --allow-root bediq.sql --path=' . $path);
    }

    public function optionSet($container, $path, $key, $value)
    {
        return $this->cli()->run($container, 'wp option set '.$key.' '.$value.' --allow-root --path=' . $path);
    }



    /**
     * Install themes on a site
     *
     * @param  string $container
     * @param  string $path
     * @param  array  $themes
     *
     * @return string
     */
    public function installThemes($container, $path, array $themes)
    {
        if (!$themes) {
            return;
        }

        return $this->cli()->run($container, 'wp theme install ' . implode(' ', $themes) . ' --allow-root --path=' . $path);
    }

    public function activateTheme($container, $path, $theme)
    {
        return $this->cli()->run($container, 'wp theme activate --allow-root '.$theme .' --path='. $path);
    }


    /**
     * Change the WP installation ownership back to www-data
     *
     * @param  string $container
     *
     * @return string
     */
    public function changeOwner($container)
    {
        return $this->cli()->run($container, 'sh -c "chown -R www-data:www-data /var/www/html"');
    }

    public function backup($container, $path)
    {
        $fileName = $container . '-' . date('Y-m-d') . '.sql.gz';
        $this->cli()->run($container, 'sh -c "wp db export - --allow-root --path=' . $path . ' | gzip > ' . $fileName . '"');

        $this->pullFile($container, 'root/' . $fileName, '/root/backups/');
        $this->deleteFile($container, 'root/' . $fileName);

        return '/root/backups/' . $fileName;
    }
}
