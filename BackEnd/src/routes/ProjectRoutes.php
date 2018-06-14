<?php
use HostMyDocs\Controllers\ProjectController;
use HostMyDocs\Models\Language;
use HostMyDocs\Models\Project;
use HostMyDocs\Models\Version;
use Psr\Container\ContainerInterface;
use Slim\Http\Response as Response;
use Slim\Http\Request as Request;

if (!function_exists('createProjectFromRequest')) {
    /**
     * Take a Request to create the Project described in it's params
     *
     * @param  Request            $request    The request containing th params
     * @param  ContainerInterface $container  The dependancy container of the app
     * @param  boolean            $allowEmpty If object allow empty params (e.g. to delete a whole Project)
     *
     * @return array                          If an error occur this array contain the error message
     * 		else it contains the part of the project
     */
    function createProjectFromRequest(Request $request, ContainerInterface $container, bool $isCreating = false): array
    {
        $logger = $container->get('logger');

        $requestParams = $request->getParsedBody();

        if (count($requestParams) === 0) {
            return ['errorMessage' => 'No parameters found'];
        }

        $logger->info('Processing a new request');

        $name = null;
        if (array_key_exists('name', $requestParams)) {
            $name = $requestParams['name'];
        }

        $version = null;
        if (array_key_exists('version', $requestParams)) {
            $version = $requestParams['version'];
        }

        $language = null;
        if (array_key_exists('language', $requestParams)) {
            $language = $requestParams['language'];
        }

        $logger->info('Checking provided parameters');

        $project = $container->get("projectController")->getProject($name, $isCreating);
        $projectVersion = $container->get("projectController")->getVersion($version, $project, $isCreating);
        $projectLanguage = $container->get("projectController")->getLanguage($language, $projectVersion, $isCreating);

        if ($project === null) {
            return ['errorMessage' => 'Cannot create a valid project'];
        }
        if ($projectVersion === null) {
            return ['errorMessage' => 'Cannot create a valid version'];
        }
        if ($projectLanguage === null) {
            return ['errorMessage' => 'Cannot create a valid language'];
        }
        if ($allowEmpty) {
            if (strlen($language) !== 0 && strlen($version) === 0) {
                return ['errorMessage' => 'language must be empty when version is empty'];
            }
        }

        return [
            'project' => $project,
            'projectVersion' => $projectVersion,
            'projectLanguage' => $projectLanguage
        ];
    };
}

$slim->get('/listProjects', function (Request $request, Response $response): Response {
    $projects = [];
    try {
        $projects = $this->get('projectController')->listProjects();
    } catch (\Exception $e) {
        $response = $response->write('An unexpected error append');
        return $response->withStatus(400);
    }

    $cacheProvider = $this->get('cache');
    return $cacheProvider->withEtag($response->withJson($projects), md5(json_encode($projects)));
});

$slim->post('/addProject', function (Request $request, Response $response): Response {
    $params = createProjectFromRequest($request, $this, true);
    if (isset($params['errorMessage'])) {
        $response = $response->write($params['errorMessage']);
        return $response->withStatus(400);
    }

    list('project' => $project, 'projectVersion' => $projectVersion, 'projectLanguage' => $projectLanguage) = $params;

    $files = $request->getUploadedFiles();
    $archive = null;
    if ((array_key_exists('archive', $files))) {
        $archive = $files['archive'];
    } else {
        $response = $response->write('No file provided');
        return $response->withStatus(400);
    }

    if ($this->get('projectController')->archiveIsValid($archive) === false) {
        $response = $response->write('Archive is not valid');
        return $response->withStatus(400);
    }

	$logger = $this->get('logger');
    $logger->info('Parameters OK');

    $logger->info("Name of the project : " . $project->getName());
    $logger->info("Version of the project : " . $projectVersion->getNumber());
    $logger->info("Language of the project : " . $projectLanguage->getName());

    $logger->info('Extracting the archive');

    if ($this->get('projectController')->extract($archive, $projectLanguage->getUuid()) === false) {
        $response = $response->write('Failed to extract the archive');
        return $response->withStatus(400);
    }

    $logger->info('Extracting OK');

    $logger->info('Backuping uploaded file');

    $destinationFolder = $this->get('archiveRoot');
    if (file_exists($destinationFolder) === false) {
        if (mkdir($destinationFolder, 0755, true) === false) {
            $response = $response->write('Failed to create backup folder');
            return $response->withStatus(400);
        }
    }

    $destinationPath = $destinationFolder . DIRECTORY_SEPARATOR . $projectLanguage->getUuid() . '.zip';

    $logger->info('Trying to move upload file to ' . $destinationPath);

    try {
        $archive->moveTo($destinationPath);
    } catch (\Exception $e) {
        $logger->warning('moveTo method failed.');
        $logger->info('Trying with rename()');
        if (rename($projectLanguage->getArchiveFile()->file, $destinationPath) === false) {
            $logger->critical('Failed twice to move uploaded file to backup folder');
            $response = $response->write('Failed twice to move uploaded file to backup folder');
            return $response->withStatus(400);
        }
    }

    $logger->info('Backup done.');

    $logger->info('Project added successfully');

    return $response->withStatus(200);
});

// $slim->delete('/deleteProject', function (Request $request, Response $response): Response {
//     $logger = $this->get('logger');
//     $params = createProjectFromRequest($request, $logger);
//
//     if (isset($params['errorMessage'])) {
//         $response = $response->write($params['errorMessage']);
//         return $response->withStatus(400);
//     }
//
//     $project = $params['project'];
//
//     $logger->info('Parameters OK');
//
//     $logger->info("Name of the project : $name");
//     $logger->info("Version of the project : $version");
//     $logger->info("Language of the project : $language");
//
//     $logger->info('Deleting folder + backup');
//
//     if ($this->get('projectController')->deleteProject($project) === false) {
//         $response = $response->write('Project deletion failed');
//         return $response->withStatus(400);
//     }
//
//     $logger->info('Deleting done.');
//
//     $logger->info('Removing resulting empty folders');
//     $this->get('projectController')->removeEmptySubFolders($this->get('storageRoot'));
//     $logger->info('Empty folders removed');
//
//     $logger->info('Project deleted successfully');
//
//     return $response->withStatus(200);
// });
