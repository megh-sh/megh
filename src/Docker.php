<?php
namespace Megh;

/**
 * Docker class
 */
class Docker
{
    use UsesCli;

    /**
     * Check if Docker is running
     *
     * @return bool
     */
    public function dockerRunning()
    {
        $output = $this->cli()->run('docker ps');

        if (Helper::starts_with($output, 'Error response') || Helper::starts_with($output, 'Cannot connect to the Docker daemon')) {
            return false;
        }

        return true;
    }

    /**
     * Check the status of the container
     *
     * @return boolean
     */
    public function containerRunning($name)
    {
        $output = $this->cli()->run('docker ps');

        if (! str_contains($output, $name)) {
            return false;
        }

        return true;
    }

    /**
     * Create a docker network
     *
     * @param string $name
     *
     * @return string
     */
    public function createNetwork($name)
    {
        return $this->cli()->run('docker network create ' . $name, function ($code, $output) {
            Helper::warning($output);
            throw new \Exception('Error in starting the network');
        });
    }

    /**
     * Check if a network exists
     *
     * @param string $name
     *
     * @return boolean
     */
    public function networkExists($name)
    {
        $output = $this->cli()->run('docker network inspect ' . $name);

        if (str_contains($output, 'Error: No such network')) {
            return false;
        }

        return true;
    }

    public function removeNetwork($name)
    {
        return $this->cli()->run('docker network rm ' . $name);
    }

    /**
     * Docker compose up
     *
     * @param string $path
     *
     * @return string
     */
    public function composeUp($path)
    {
        chdir($path);
        $command = 'docker-compose up -d';

        return $this->cli()->run($command, function ($code, $output) {
            Helper::warning($output);
            throw new \Exception('Error in starting the site');
        });
    }

    /**
     * Take down docker compose
     *
     * @param string $path
     *
     * @return string
     */
    public function composeDown($path)
    {
        chdir($path);
        $command = 'docker-compose down';

        return $this->cli()->run($command, function ($code, $output) {
            Helper::warning($output);
            throw new \Exception('Error in removing the site');
        });
    }

    /**
     * Run a command inside a container
     *
     * @param string $command
     * @param string $sitePath
     * @param string $container
     *
     * @return mixed
     */
    public function runCommand($command, $sitePath, $container = 'php', $user = 'www-data')
    {
        chdir($sitePath);

        $cmd = "docker-compose run --rm --user $user $container $command";

        return $this->cli()->run($cmd, function ($code, $output) {
            Helper::warning($output);
        });
    }
}
