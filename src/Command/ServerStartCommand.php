<?php

namespace App\Command;

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
use Symfony\Component\EventDispatcher\EventDispatcher;

class ServerStartCommand extends Command
{
    protected static $defaultName = 'server:start';

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
                new InputOption('remote', 'r', InputOption::VALUE_NONE, 'Indicate project should listen on all addresses'),
                new InputOption('port', 'p', InputOption::VALUE_REQUIRED, 'Specify the port to use with this project'),
                new InputOption('title', 't', InputOption::VALUE_REQUIRED, 'Name the project'),
                new InputOption('pidfile', null, InputOption::VALUE_REQUIRED, 'PID file')
            ])
            ->setDescription('Starts a web server in the background');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);
        $project = $this->pm->findOrCreateProject($input->getArgument("project"));
        $this->pm->updateProject($project, $input->getOption("port"), $input->getOption("title"));

        if (!\extension_loaded('pcntl')) {
            $io->error([
                'This command needs the pcntl extension to run.',
                'You can either install it or use the "server:run" command instead.',
            ]);

            if ($io->confirm('Do you want to execute <info>server:run</info> immediately?', false)) {
                return $this->getApplication()->find('server:run')->run($input, $output);
            }

            return 1;
        }

        // replace event dispatcher with an empty one to prevent console.terminate from firing
        // as container could have changed between start and stop
        $this->getApplication()->setDispatcher(new EventDispatcher());

        try {
            $server = new WebServer($project);
            if ($server->isRunning($input->getOption('pidfile'))) {
                $io->error(sprintf('The web server has already been started. It is currently listening on http://%s. Please stop the web server before you try to start it again.', $server->getAddress($input->getOption('pidfile'))));

                return 1;
            }

            $config = new WebServerConfig($project, $input->getOption("remote"));

            if (WebServer::STARTED === $server->start($config, $input->getOption('pidfile'))) {
                $this->pm->statusUpdate($project, WebServer::STARTED);
                $message = sprintf('Server listening on http://%s', $config->getAddress());
                if ('' !== $displayAddress = $config->getDisplayAddress()) {
                    $message = sprintf('Server listening on all interfaces, port %s -- see http://%s', $config->getPort(), $displayAddress);
                }
                $io->success($message);
                if (ini_get('xdebug.profiler_enable_trigger')) {
                    $io->comment('Xdebug profiler trigger enabled.');
                }
            }
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            $this->pm->statusUpdate($project, WebServer::STOPPED);
            return 1;
        }

        return 0;
    }
}
