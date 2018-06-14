<?php
use HostMyDocs\Controllers\ProjectController;
use HostMyDocs\Models\Language;
use HostMyDocs\Models\Project;
use HostMyDocs\Models\Version;
use Psr\Log\LoggerInterface;
use Slim\Http\Response as Response;
use Slim\Http\Request as Request;

if (!function_exists('createProjectFromRequest')) {
    /**
     * Take a Request to create the Project described in it's params
     *
     * @param  Request         $request    The request containing th params
     * @param  LoggerInterface $logger     The logger used by the app
     * @param  boolean         $allowEmpty If object allow empty params (e.g. to delete a whole Project)
     *
     * @return array                          If an error occur this array contain the error message
     * 		else it contains the part of the project
     */
    function createProjectFromRequest(Request $request, LoggerInterface $logger): array
    {
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

		return [
			'name' => $name,
			'version' => $version,
			'language' => $language
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
	$logger = $this->get('logger');
    $params = createProjectFromRequest($request, $logger);
    if (isset($params['errorMessage'])) {
        $response = $response->write($params['errorMessage']);
        return $response->withStatus(400);
    }

	$projectController = $this->get("projectController");
	$project = $projectController->getProject($params['name'], true);
	$projectVersion = $projectController->getVersion($params['version'], $project, true);
	$projectLanguage = $projectController->getLanguage($params['language'], $projectVersion, true);

	if ($project === null) {
		$response = $response->write('Cannot create a valid project');
        return $response->withStatus(400);
	}
	if ($projectVersion === null) {
		$response = $response->write('Cannot create a valid version');
        return $response->withStatus(400);
	}
	if ($projectLanguage === null) {
		$response = $response->write('Cannot create a valid language');
        return $response->withStatus(400);
	}

    $files = $request->getUploadedFiles();
    $archive = null;
    if ((array_key_exists('archive', $files))) {
        $archive = $files['archive'];
    } else {
        $response = $response->write('No file provided');
        return $response->withStatus(400);
    }

    if ($projectController->archiveIsValid($archive) === false) {
        $response = $response->write('Archive is not valid');
        return $response->withStatus(400);
    }

    $logger->info('Parameters OK');

    $logger->info("Name of the project : " . $project->getName());
    $logger->info("Version of the project : " . $projectVersion->getNumber());
    $logger->info("Language of the project : " . $projectLanguage->getName());

    $logger->info('Extracting the archive');

    if ($projectController->extract($archive, $projectLanguage->getUuid()) === false) {
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

$slim->delete('/deleteProject', function (Request $request, Response $response): Response {
	$logger = $this->get('logger');
    $params = createProjectFromRequest($request, $logger);

    if (isset($params['errorMessage'])) {
        $response = $response->write($params['errorMessage']);
        return $response->withStatus(400);
    }

	$project = $projectController->getPopulatedProject($params);

    $logger->info('Parameters OK');

    $logger->info("Name of the project : " . $project->getName());
    $logger->info("Version of the project : " . $projectVersion->getNumber());
    $logger->info("Language of the project : " . $projectLanguage->getName());

    $logger->info('Deleting folder + backup');

    if ($this->get('projectController')->deleteProject($project) === false) {
        $response = $response->write('Project deletion failed');
        return $response->withStatus(400);
    }

    $logger->info('Deleting done.');

    $logger->info('Project deleted successfully');

    return $response->withStatus(200);
});
