<?php

namespace App\Command;

use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ProjectListCommand extends Command
{
    protected static $defaultName = 'project:list';
    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Display the available projects')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projects = $this->em->getRepository(Project::class)->findBy([], ["name" => "ASC"]);
        $projectList = array_map(function($project) {
            return [$project->getId(), $project->getName(), $project->getPort(), $project->getPath(), $project->displayStatus()];
        }, $projects);
        $table = new Table($output);
        $table
            ->setHeaders(["ID", "Name", "Port", "Path", "Status"])
            ->setRows($projectList);
        $table->render();
        return 0;
    }
}
