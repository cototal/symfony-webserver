<?php

namespace App\Command;

use App\Model\WebServer;
use App\Service\ProjectManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ServerStopCommand extends Command
{
    protected static $defaultName = 'server:stop';

    /**
     * @var ProjectManager
     */
    private $pm;

    public function __construct(ProjectManager  $pm)
    {
        parent::__construct();
        $this->pm = $pm;
    }

    protected function configure()
    {
        $this
            ->setDefinition([
                new InputArgument('project', InputArgument::REQUIRED, 'The project ID, name, or path'),
                new InputOption('pidfile', null, InputOption::VALUE_REQUIRED, 'PID file')
            ])
            ->setDescription('Stops the local web server');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);
        $project = $this->pm->findOrCreateProject($input->getArgument("project"));

        try {
            $server = new WebServer($project);
            $server->stop($input->getOption('pidfile'));
            $io->success('Stopped the web server. ' . $project->displayName());
            $this->pm->statusUpdate($project, WebServer::STOPPED);
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            $this->pm->statusUpdate($project, WebServer::STOPPED);
            return 1;
        }

        return 0;
    }
}
