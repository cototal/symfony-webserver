<?php

namespace App\Command;

use App\Entity\Project;
use App\Model\WebServer;
use App\Service\ProjectManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ProjectResetCommand extends Command
{
    protected static $defaultName = 'project:reset';

    /**
     * @var EntityManagerInterface
     */
    private $em;
    /**
     * @var ProjectManager
     */
    private $pm;

    public function __construct(EntityManagerInterface $em, ProjectManager $pm)
    {
        $this->em = $em;
        parent::__construct();
        $this->pm = $pm;
    }

    protected function configure()
    {
        $this
            ->setDescription('Actually running apps may be out of sync with the database. Use this to reset the status on all')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projects = $this->em->getRepository(Project::class)->findAll();
        /** @var Project $project */
        foreach ($projects as $project) {
            $this->pm->statusUpdate($project, WebServer::STOPPED);
            try {
                $server = new WebServer($project);
                $server->stop(null);
                $io->success("Stopped " . $project->displayName());
            } catch (\Throwable $ex) {
                $io->comment("Project not started: " . $project->displayName());
            }
        }

        return 0;
    }
}
