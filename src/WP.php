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
        return (new Docker())->runCommand('wp core download', $this->path);
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

        return $docker->runCommand(
            sprintf(
                "wp core config --dbname=%s --dbuser=%s --dbpass=%s --dbhost=%s --extra-php <<'PHP'\n%s\nPHP",
                $config['dbname'],
                $config['dbuser'],
                $config['dbpass'],
                $config['dbhost'],
                $this->extraPhp()
            ), 
            $this->path
        );
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

}
