<?php

namespace App\Command;

use App\Entity\Project;
use App\Model\WebServer;
use App\Model\WebServerConfig;
use App\Service\ProjectManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

class ServerRunCommand extends Command
{
    protected static $defaultName = 'server:run';

    /**
     * @var ProjectManager
     */
    private $pm;

    public function __construct(ProjectManager $pm)
    {
        parent::__construct();
        $this->pm = $pm;
    }

    protected function configure()
    {
        $this
            ->setDefinition([
                new InputArgument('project', InputArgument::REQUIRED, 'The project ID, name, or path'),
                new InputOption('remote', 'r', InputOption::VALUE_NONE, 'Indicate project should listen on all addresses'),
                new InputOption('port', 'p', InputOption::VALUE_REQUIRED, 'Specify the port to use with this project'),
                new InputOption('title', 't', InputOption::VALUE_REQUIRED, 'Name the project'),
            ])
            ->setDescription('Runs a local web server');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);
        $project = $this->pm->findOrCreateProject($input->getArgument("project"));
        $this->pm->updateProject($project, $input->getOption("port"), $input->getOption("title"));

        $callback = null;
        $disableOutput = false;
        if ($output->isQuiet()) {
            $disableOutput = true;
        } else {
            $callback = function ($type, $buffer) use ($output) {
                if (Process::ERR === $type && $output instanceof ConsoleOutputInterface) {
                    $output = $output->getErrorOutput();
                }
                $output->write($buffer, false, OutputInterface::OUTPUT_RAW);
            };
        }

        try {
            $server = new WebServer($project);
            $config = new WebServerConfig($project, $input->getOption("remote"));

            $message = sprintf('Server listening on http://%s', $config->getAddress());
            if ('' !== $displayAddress = $config->getDisplayAddress()) {
                $message = sprintf('Server listening on all interfaces, port %s -- see http://%s', $config->getPort(), $displayAddress);
            }
            $io->success($message);
            if (ini_get('xdebug.profiler_enable_trigger')) {
                $io->comment('Xdebug profiler trigger enabled.');
            }
            $io->comment('Quit the server with CONTROL-C.');

            $this->pm->statusUpdate($project, WebServer::STARTED);
            $exitCode = $server->run($config, $disableOutput, $callback);
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            $this->pm->statusUpdate($project, WebServer::STOPPED);
            return 1;
        }

        $this->pm->statusUpdate($project, WebServer::STOPPED);
        return $exitCode;
    }
}
