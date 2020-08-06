<?php

namespace App\Model;

use App\Entity\Project;

class WebServerConfig
{
    private $hostname;
    private $port;
    private $router;

    /**
     * @var Project
     */
    private $project;

    public function __construct(Project $project, $isRemote = false)
    {
        $this->project = $project;
        if (null === $file = $this->findFrontController()) {
            throw new \InvalidArgumentException("Unable to set front controller");
        }

        $_ENV['APP_FRONT_CONTROLLER'] = $file;

        $this->router = getcwd() . '/bin/router.php';
        $this->hostname = $isRemote ? "0.0.0.0" : "127.0.0.1";
        $this->port = is_null($project->getPort()) ? $this->findBestPort() : $project->getPort();
    }

    public function getRouter()
    {
        return $this->router;
    }

    public function getHostname()
    {
        return $this->hostname;
    }

    public function getPort()
    {
        return $this->port;
    }

    public function getAddress()
    {
        return $this->hostname.':'.$this->port;
    }

    /**
     * @return string contains resolved hostname if available, empty string otherwise
     */
    public function getDisplayAddress()
    {
        if ('0.0.0.0' !== $this->hostname) {
            return '';
        }

        if (false === $localHostname = gethostname()) {
            return '';
        }

        return gethostbyname($localHostname).':'.$this->port;
    }

    private function findFrontController(): ?string
    {
        $files = ["app_dev.php", "app.php", "index_dev.php", "index.php"];

        $dir = $this->project->getPath() . "/public";
        if (!is_dir($dir)) {
            $dir = $this->project->getPath() . "/web";
        }
        foreach ($files as $fileName) {
            if (file_exists("$dir/$fileName")) {
                return "$dir/$fileName";
            }
        }

        return null;
    }

    private function getFrontControllerFileNames(): array
    {
        return ['app_dev.php', 'app.php', 'index_dev.php', 'index.php'];
    }

    private function findBestPort(): int
    {
        $port = 8000;
        while (false !== $fp = @fsockopen($this->hostname, $port, $errno, $errstr, 1)) {
            fclose($fp);
            if ($port++ >= 8100) {
                throw new \RuntimeException('Unable to find a port available to run the web server.');
            }
        }

        return $port;
    }
}