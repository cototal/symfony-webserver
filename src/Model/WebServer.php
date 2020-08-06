<?php

namespace App\Model;

use App\Entity\Project;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class WebServer
{
    const STARTED = 0;
    const STOPPED = 1;
    /**
     * @var Project
     */
    private $project;

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    public function run(WebServerConfig $config, $disableOutput = true, callable $callback = null)
    {
        if ($this->isRunning()) {
            throw new \RuntimeException(sprintf('A process is already listening on http://%s.', $config->getAddress()));
        }

        $process = $this->createServerProcess($config);
        if ($disableOutput) {
            $process->disableOutput();
            $callback = null;
        } else {
            try {
                $process->setTty(true);
                $callback = null;
            } catch (\RuntimeException $e) {
                return 1;
            }
        }

        $process->run($callback);

        if (!$process->isSuccessful()) {
            $error = 'Server terminated unexpectedly.';
            if ($process->isOutputDisabled()) {
                $error .= ' Run the command again with -v option for more details.';
            }

            throw new \RuntimeException($error);
        }
        return 0;
    }

    public function start(WebServerConfig $config, $pidFile = null)
    {
        $pidFile = $pidFile ?: $this->getDefaultPidFile();
        if ($this->isRunning($pidFile)) {
            throw new \RuntimeException(sprintf('A process is already listening on http://%s.', $config->getAddress()));
        }

        $pid = pcntl_fork();

        if ($pid < 0) {
            throw new \RuntimeException('Unable to start the server process.');
        }

        if ($pid > 0) {
            return self::STARTED;
        }

        if (posix_setsid() < 0) {
            throw new \RuntimeException('Unable to set the child process as session leader.');
        }

        $process = $this->createServerProcess($config);
        $process->disableOutput();
        $process->start();

        if (!$process->isRunning()) {
            throw new \RuntimeException('Unable to start the server process.');
        }

        file_put_contents($pidFile, $config->getAddress());

        // stop the web server when the lock file is removed
        while ($process->isRunning()) {
            if (!file_exists($pidFile)) {
                $process->stop();
            }

            sleep(1);
        }

        return self::STOPPED;
    }

    public function stop($pidFile = null)
    {
        $pidFile = $pidFile ?: $this->getDefaultPidFile();
        if (!file_exists($pidFile)) {
            throw new \RuntimeException('No web server is listening.');
        }

        unlink($pidFile);
    }

    public function getAddress($pidFile = null)
    {
        $pidFile = $pidFile ?: $this->getDefaultPidFile();
        if (!file_exists($pidFile)) {
            return false;
        }

        return file_get_contents($pidFile);
    }

    public function isRunning($pidFile = null)
    {
        $pidFile = $pidFile ?: $this->getDefaultPidFile();
        if (!file_exists($pidFile)) {
            return false;
        }

        $address = file_get_contents($pidFile);
        $pos = strrpos($address, ':');
        $hostname = substr($address, 0, $pos);
        $port = substr($address, $pos + 1);
        if (false !== $fp = @fsockopen($hostname, $port, $errno, $errstr, 1)) {
            fclose($fp);

            return true;
        }

        unlink($pidFile);

        return false;
    }

    private function createServerProcess(WebServerConfig $config): Process
    {
        $finder = new PhpExecutableFinder();
        if (false === $binary = $finder->find(false)) {
            throw new \RuntimeException('Unable to find the PHP binary.');
        }

        $xdebugArgs = ini_get('xdebug.profiler_enable_trigger') ? ['-dxdebug.profiler_enable_trigger=1'] : [];

        $workDir = $this->project->getPath() . "/public";
        if (!is_dir($workDir)) {
            $workDir = $this->project->getPath() . "/web";
        }

        $process = new Process(array_merge([$binary], $finder->findArguments(), $xdebugArgs, ['-dvariables_order=EGPCS', '-S', $config->getAddress(), $config->getRouter()]));
        $process->setWorkingDirectory($workDir);
        $process->setTimeout(null);

        if (\in_array('APP_ENV', explode(',', getenv('SYMFONY_DOTENV_VARS')))) {
            $process->setEnv(['APP_ENV' => false]);

            if (!method_exists(Process::class, 'fromShellCommandline')) {
                // Symfony 3.4 does not inherit env vars by default:
                $process->inheritEnvironmentVariables();
            }
        }

        return $process;
    }

    private function getDefaultPidFile(): string
    {
        return $this->project->getPath() . '/.web-server-pid';
    }
}