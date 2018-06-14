<?php
namespace HostMyDocs\Controllers;

use Chumper\Zipper\Zipper;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Id\UuidGenerator;
use HostMyDocs\Models\Language;
use HostMyDocs\Models\Project;
use HostMyDocs\Models\Version;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\Http\UploadedFile;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class used to create, list and delete the projects
 * You must get it using the slim dependency injector
 *
 * @see https://www.slimframework.com/docs/concepts/di.html
 */
class ProjectController
{
    /**
     * @var Filesystem Object used to interact with the projects folder
     */
    private $filesystem;

    /**
     * @var string Path to the folder where projects are stored
     */
    private $storageRoot;

    /**
     * @var string Path to the folder where archives are stored
     */
    private $archiveRoot;

    /**
     * @var LoggerInterface Logger used by the class
     */
    private $logger;

    /**
     * @var EntityManager Doctrine entityManager used to interact with database
     */
    private $entityManager;

    /**
     * Create a project controller
     *
     * You must not call this function by yourselves but get an instance from the slim container
     *
     * @param ContainerInterface $container the slim container, it must contain the following keys
     * 		- string                     storageRoot   Path to the folder where projects are stored
     * 		- string                     archiveRoot   Path to the folder where archives are stored
     * 		- Psr\Log\LoggerInterface    logger        Logger used by the class
     * 		- Doctrine\ORM\EntityManager entityManager the doctrine entityManager used to interact with database
     *
     * @throws InvalidArgumentException When the container miss a key
     *
     * @see https://www.slimframework.com/docs/concepts/di.html
     */
    public function __construct(ContainerInterface $container)
    {
        if (empty($container['storageRoot'])) {
            throw new \InvalidArgumentException("Container doesn't contain the key 'storageRoot'");
        }

        if (empty($container['archiveRoot'])) {
            throw new \InvalidArgumentException("Container doesn't contain the key 'archiveRoot'");
        }

        if (empty($container['logger'])) {
            throw new \InvalidArgumentException("Container doesn't contain the key 'logger'");
        }

        if (empty($container['entityManager'])) {
            throw new \InvalidArgumentException("Container doesn't contain the key 'entityManager'");
        }

        $this->filesystem = new Filesystem();
        $this->storageRoot = $container['storageRoot'];
        $this->archiveRoot = $container['archiveRoot'];
        $this->logger = $container['logger'];
        $this->entityManager = $container['entityManager'];
    }

	/**
	 * [getPopulatedProject description]
	 * @param  array   $params must have "name", "version", "language"
	 * @return Project         [description]
	 */
    public function getPopulatedProject(array $params): Project
    {
		if (empty($params['name'])) {
			throw new \InvalidArgumentException("Can't get project : miss the name parameter");
		}

		if (empty($params['version'])) {
			throw new \InvalidArgumentException("Can't get project : miss the version parameter");
		}

		if (empty($params['language'])) {
			throw new \InvalidArgumentException("Can't get project : miss the language parameter");
		}

        $projectRepository = $this->entityManager->getRepository('HostMyDocs\Models\Project');
        $projects = $projectRepository->findBy(["name" => $params["name"]]);
        if (count($projects) === 1) {
            $project = $projects[0];

			if (strlen($params['version']) !== 0) {
				$versions = $versionRepository->findBy(["number" => $params['version'], "project" => $projects->getId()]);
			} else {
				$versions = $versionRepository->findBy(["project" => $projects->getId()]);
			}
			foreach ($versions as $version) {

				if (strlen($params['language']) !== 0) {
					$languages = $languageRepository->findBy(["name" => $params['language'], "version" => $version->getId()]);
		        } else {
					$languages = $languageRepository->findBy(["version" => $version->getId()]);
		        }
				foreach ($languages as $language) {
					$version->addLanguage($language);
				}

				$project->addVersion($version);
			}

        } else {
			$this->logger->info('no project found');
            return null;
        }

        return $project;
    }

    public function getProject(string $projectName, bool $canCreateNew = false): Project
    {
        $projectRepository = $this->entityManager->getRepository('HostMyDocs\Models\Project');
        $projects = $projectRepository->findBy(["name" => $projectName]);
        if (count($projects) === 0) {
            if ($canCreateNew) {
                $project = (new Project($this->logger))
                    ->setName($projectName);

                if ($project === null) {
                    return null;
                }

                $this->entityManager->persist($project);
                $this->entityManager->flush();
            } else {
                $project = null;
            }
        } else {
            $project = $projects[0];
        }

        return $project;
    }

    public function getVersion(string $versionNumber, Project $parentProject, bool $canCreateNew = false): Version
    {
        $versionRepository = $this->entityManager->getRepository('HostMyDocs\Models\Version');
        $versions = $versionRepository->findBy(["number" => $versionNumber, "project" => $parentProject->getId()]);
        if (count($versions) === 0) {
            if ($canCreateNew) {
                $version = (new Version($this->logger))
                    ->setProject($parentProject)
                    ->setNumber($versionNumber);

                if ($version === null) {
                    return null;
                }

                $this->entityManager->persist($version);
                $this->entityManager->flush();
            } else {
                $version = null;
            }
        } else {
            $version = $versions[0];
        }

        return $version;
    }

    public function getLanguage(string $languageName, Version $parentVersion, bool $canCreateNew = false): Language
    {
        $languageRepository = $this->entityManager->getRepository('HostMyDocs\Models\Language');
        $languages = $languageRepository->findBy(["name" => $languageName, "version" => $parentVersion->getId()]);
        if (count($languages) === 0) {
            if ($canCreateNew) {
                $language = (new Language($this->logger))
                    ->setName($languageName);

                if ($language === null) {
                    return null;
                }

                do {
                    $uuid = (new UuidGenerator())->generate($this->entityManager, $language);
                    $languagesAlreadyUsingUuid = $languageRepository->findBy(["uuid" => $uuid]);
                } while (count($languagesAlreadyUsingUuid) != 0);

                $language->setUuid($uuid)
                    ->setVersion($parentVersion);
                $this->entityManager->persist($language);
                $this->entityManager->flush();
            } else {
                $language = null;
            }
        } else {
            $language = $languages[0];
        }

        return $language;
    }

    public function listProjects(): array
    {
        $projectRepository = $this->entityManager->getRepository('HostMyDocs\Models\Project');
        $projects = $projectRepository->findAll();

        $versionRepository = $this->entityManager->getRepository('HostMyDocs\Models\Version');

        $languageRepository = $this->entityManager->getRepository('HostMyDocs\Models\Language');

        foreach ($projects as $project) {
            $versions = $versionRepository->findBy(["project" => $project->getId()]);
            foreach ($versions as $version) {
                $project->addVersion($version);

                $languages = $languageRepository->findBy(["version" => $version->getId()]);
                foreach ($languages as $language) {
                    $version->addLanguage($language);
                    $language->setRootPaths([
                        'storageRoot' => $this->storageRoot,
                        'archiveRoot' => $this->archiveRoot
                    ]);
                }
            }
        }

        return $projects;
    }

    public function archiveIsValid(UploadedFile $archive)
    {
		$zip = new \ZipArchive();

		// ZipArchive::CHECKCONS will enforce additional consistency checks
		$res = $zip->open($archive->file, \ZipArchive::CHECKCONS);
		return $res === true;
    }

    /**
     * Take the archive from a project and extract it in the storage folder
     *
     * @param  Project $project The project to extract
     *
     * @return bool             Whether the extration succeed
     */
    public function extract(UploadedFile $archive, string $uuid): bool
    {

        $zipper = new Zipper();

        if (is_file($archive->file) === false) {
            $this->logger->warning('impossible to open archive file');
            return false;
        }

        $this->logger->info("Opening file : " . $archive->file);

        $zipFile = $zipper->make($archive->file);

        $rootCandidates = array_values(array_filter($zipFile->listFiles(), function ($path) {
            return preg_match('@^[^/]+/index\.html$@', $path);
        }));

        if (count($rootCandidates) > 1) {
            $this->logger->warning('More than one index file found');
            return false;
        }

        $splittedPath = explode('/', $rootCandidates[0]);
        $zipRoot = array_shift($splittedPath);

        $destinationPath = $this->storageRoot . DIRECTORY_SEPARATOR . $uuid;

        if (filter_var($destinationPath, FILTER_SANITIZE_URL) === false) {
            $this->logger->warning('extract path contains invalid characters');
            return false;
        }

        if (file_exists($destinationPath)) {
            $this->filesystem->remove($destinationPath);
        }

        if (mkdir($destinationPath, 0755, true) === false) {
            $this->logger->critical('failed to create folder');
            return false;
        }

        $this->logger->info('Extracting to ' . $destinationPath);

        $zipFile->folder($zipRoot)->extractTo($destinationPath);

        $zipper->close();

        return true;
    }

    /**
     * Delete every files targetted by a Project
     *
     * @param  Project $project The project to delete
     *
     * @return bool             Whether the deletion succeed
     */
    public function deleteProject(Project $project): bool
    {
		foreach ($project->getVersions() as $version) {
			foreach ($version->getLanguages() as $language) {
				// code...
			}
		}

        // $version = $project->getFirstVersion();
        // $language = $version->getFirstLanguage();
		//
        // if ($version === null) {
        //     $this->logger->critical('An error occured while building the project (it has no version)');
        //     return false;
        // }
		//
        // if ($language === null) {
        //     $this->logger->critical('An error occured while building the project (it has no language)');
        //     return false;
        // }
		//
        // $fileNameParts = array_filter(
        //     [
        //         $project->getName(),
        //         $version->getNumber(),
        //         $language->getName()
        //     ],
        //     function ($v) {
        //         return strlen($v) !== 0;
        //     }
        // );
		//
        // $archiveDestinationGlob = $this->archiveRoot . DIRECTORY_SEPARATOR . implode('-', $fileNameParts) . '*.zip';
        // $archiveToDelete = glob($archiveDestinationGlob);
        // if (count($archiveToDelete) !== 0) {
        //     $this->filesystem->remove($archiveToDelete);
        // } else {
        //     $this->logger->error('No backup found ' . $archiveDestinationGlob);
        // }
		//
        // $storageDestinationPath = $this->storageRoot . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $fileNameParts);
		//
        // if (file_exists($storageDestinationPath) === true) {
        //     try {
        //         $this->filesystem->remove($storageDestinationPath);
        //     } catch (\Exception $e) {
        //         $this->logger->critical('deleting project failed.');
        //         return false;
        //     }
        // } else {
        //     $this->logger->info('project does not exists.');
        //     return false;
        // }
		//
        // return true;
    }
}
