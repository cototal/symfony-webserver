<?php

namespace App\Service;

use App\Entity\Project;
use App\Model\WebServer;
use Doctrine\ORM\EntityManagerInterface;

class ProjectManager
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function findOrCreateProject(string $input): Project
    {
        $repo = $this->em->getRepository(Project::class);
        $id = intval($input);
        if ($id > 0) {
            $project = $repo->find($id);
            if (is_null($project)) {
                throw new \RuntimeException("Project with that ID could not be found");
            }
            return $project;
        }

        $project = $repo->findOneBy(["name" => $input]);
        if (!is_null($project)) {
            return $project;
        }

        $project = $repo->findOneBy(["path" => $input]);
        if (!is_null($project)) {
            return $project;
        }

        if (!is_dir($input)) {
            throw new \RuntimeException("Project not found, please provide a valid path.");
        }
        $project = (new Project)->setPath($input);
        $this->em->persist($project);
        return $project;
    }

    public function updateProject(Project $project, $port, $name)
    {
        $repo = $this->em->getRepository(Project::class);
        if (!is_null($port)) {
            $portNum = intval($port);
            if ($portNum > 0) {
                $match = $repo->findOneBy(["port" => $portNum]);
                if (!is_null($match) && $match->getId() != $project->getId()) {
                    throw new \RuntimeException("There is already a project assigned to the port $portNum");
                }
                $project->setPort($portNum);
            }
        }

        if (!empty($name)) {
            $match = $repo->findOneBy(["name" => $name]);
            if (!is_null($match) && $match->getId() != $project->getId()) {
                throw new \RuntimeException("There is already a project named $name");
            }
            $project->setName($name);
        }
        $this->em->flush();
    }

    public function statusUpdate(Project $project, $status)
    {
        $project->setStatus($status);
        $this->em->flush();
    }
}