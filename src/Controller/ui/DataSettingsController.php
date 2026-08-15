<?php

namespace App\Controller\ui;

use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use App\Service\HttpHelper;
use App\Service\PdoHelper;
use App\CSPro\Data\DataSettings;
use App\CSPro\Data\BreakoutScheduler;
use App\CSPro\CSProResponse;
use GuzzleHttp\Client;
use App\CSPro\FileManager\CSProFileManager;
use App\Security\SettingsVoter;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Cron\CronExpression;

require_once __DIR__ . '/../../../maps/server.php';

/**
 * Description of DataSettingsController
 *
 * @author savy
 */
class DataSettingsController extends AbstractController implements TokenAuthenticatedController {

    private $dataSettings;
    // Community layer: breakout schedule management.
    private $breakoutScheduler;

    // Valid database name pattern: alphanumeric and underscores only
    private const SCHEMA_NAME_PATTERN = '/^[a-zA-Z0-9_]+$/';
    // Valid dictionary ID pattern: alphanumeric, underscores, hyphens only
    private const DICTIONARY_ID_PATTERN = '/^[a-zA-Z0-9_\-]+$/';
    // Safe filename pattern: alphanumeric, underscores, hyphens, dots  no path separators
    private const SAFE_FILENAME_PATTERN = '/^[a-zA-Z0-9_\-\.]+$/';

    public function __construct(private HttpHelper $client, private PdoHelper $pdo, private KernelInterface $kernel, private LoggerInterface $logger) {
        $this->kernel = $kernel;
    }

    // Override setContainer to get access to container parameters and initialize the roles repository
    public function setContainer(ContainerInterface $container = null): ?ContainerInterface {
        $this->dataSettings = new DataSettings($this->pdo, $this->logger);
        // Community layer: breakout scheduling.
        $this->breakoutScheduler = new BreakoutScheduler($this->pdo, $this->logger);
        return parent::setContainer($container);
    }

    #[Route('/dataSettings', name: 'dataSettings', methods: ['GET'])]
    public function viewDataSettingsAction(Request $request): Response {
        $this->denyAccessUnlessGranted(SettingsVoter::SETTINGS_READ);
        // Set the oauth token
        $dataSettings = $this->dataSettings->getDataSettings();
        $this->logger->debug('data settings ' . print_r($dataSettings, true));
        return $this->render('dataSettings.twig', ['dataSettings' => $dataSettings]);
    }

    #[Route('/getSettings', name: 'getSettings', methods: ['GET'])]
    public function getDataSettings(Request $request): Response {
        $this->denyAccessUnlessGranted(SettingsVoter::SETTINGS_READ);
//get data settings
        $dataSettings = $this->dataSettings->getDataSettings();
        $this->logger->debug('data settings ' . print_r($dataSettings, true));
        return $this->render('dataSettings.twig', ['dataSettings' => $dataSettings]);
    }

    #[Route('/addSetting', name: 'addSetting', methods: ['POST'])]
    public function addDataSetting(Request $request): Response {
        $this->denyAccessUnlessGranted(SettingsVoter::SETTINGS_WRITE);

        $result = [];
        //get the json setting  info to add
        $body = $request->getContent();
        $dataSetting = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        // Validate required fields before doing anything else
        $validationError = $this->validateDataSetting($dataSetting);
        if ($validationError !== null) {
            $result['description'] = $validationError;
            $result['code'] = 400;
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_BAD_REQUEST);
        }

        $label = $dataSetting['label'];
        $this->updateMetaDataInfo($dataSetting);
        try {
            $isValidMapURL = $this->checkMapURLConnection($dataSetting);
            $isAdded = $this->dataSettings->addDataSetting($dataSetting);

            if ($isAdded === true && $isValidMapURL === true) {
                $result['description'] = "Added configuration for $label";
                $result['code'] = 200;
                $response = new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
            } else {
                $result['description'] = "Failed to add configuration for $label";
                $result['code'] = 500;
                $response = new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        } catch (\Exception $e) {
            $errMsg = $e->getMessage();
            $pattern = "/(?<=SQLSTATE\[HY\d{3}\]\s\[\d{4}\]).*/";
            $match = preg_match($pattern, $errMsg, $matchStr);
            if ($match) {
                $errMsg = $matchStr[0];
            }
            $result['description'] = "Failed to add configuration for $label. $errMsg";
            $result['code'] = 500;
            $response = new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
            $this->logger->error("Failed adding configuration", ["context" => (string) $e]);
            return $response;
        }
        $response->headers->set('Content-Length', strlen($response->getContent()));
        return $response;
    }

    #[Route('/updateSetting', name: 'updateSetting', methods: ['PUT'])]
    public function updateDataSetting(Request $request): Response {
        $this->denyAccessUnlessGranted(SettingsVoter::SETTINGS_WRITE);
        $result = [];
        //get the json setting  info to add
        $body = $request->getContent();
        $dataSetting = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        // Validate required fields before doing anything else
        $validationError = $this->validateDataSetting($dataSetting);
        if ($validationError !== null) {
            $result['description'] = $validationError;
            $result['code'] = 400;
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_BAD_REQUEST);
        }

        $label = $dataSetting['label'];
        $this->updateMetaDataInfo($dataSetting);
        try {
            $isValidMapURL = $this->checkMapURLConnection($dataSetting);
            $isAdded = $this->dataSettings->updateDataSetting($dataSetting);

            if ($isAdded === true && $isValidMapURL === true) {
                $result['description'] = "Updated configuration for $label";
                $result['code'] = 200;
                $response = new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
            } else {
                $result['description'] = "Failed to update configuration for $label";
                $result['code'] = 500;
                $response = new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        } catch (\Exception $e) {
            $errMsg = $e->getMessage();
            $pattern = "/(?<=SQLSTATE\[HY\d{3}\]\s\[\d{4}\]).*/";
            $match = preg_match($pattern, $errMsg, $matchStr);
            if ($match) {
                $errMsg = $matchStr[0];
            }
            $result['description'] = "Failed to update configuration for $label. $errMsg";
            $result['code'] = 500;
            $response = new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
            $this->logger->error("Failed updating configuration", ["context" => (string) $e]);
            return $response;
        }
        $response->headers->set('Content-Length', strlen($response->getContent()));
        return $response;
    }

    /**
     * Validates required fields and formats for add/update data setting requests.
     * Returns an error message string on failure, or null if valid.
     */
    private function validateDataSetting(?array $dataSetting): ?string {
        if (!is_array($dataSetting)) {
            return "Invalid or missing request body.";
        }

        // --- Required field presence and non-empty checks ---
        $requiredFields = ['label', 'targetSchemaName', 'targetHostName', 'dbUserName'];
        foreach ($requiredFields as $field) {
            if (empty(trim($dataSetting[$field] ?? ''))) {
                $friendlyNames = [
                    'label' => 'Data label',
                    'targetSchemaName' => 'Database name',
                    'targetHostName' => 'Hostname',
                    'dbUserName' => 'Database username',
                ];
                return ($friendlyNames[$field] ?? $field) . " is required and cannot be empty.";
            }
        }

        // --- Schema name format: alphanumeric + underscores only ---
        $schemaName = trim($dataSetting['targetSchemaName']);
        if (!preg_match(self::SCHEMA_NAME_PATTERN, $schemaName)) {
            return "Database name may only contain letters, numbers, and underscores.";
        }

        // --- Hostname basic sanity: no spaces ---
        $hostname = trim($dataSetting['targetHostName']);
        if (preg_match('/\s/', $hostname)) {
            return "Hostname must not contain spaces.";
        }

        // --- Community layer: per-dictionary target engine and port ---
        // Reject an unknown db_type rather than letting resolveDriver() fall
        // back to PostgreSQL for a typo, which would fail at connection time
        // with a confusing error.
        $dbType = strtolower(trim((string) ($dataSetting['dbType'] ?? 'postgresql')));
        if ($dbType !== '' && !in_array($dbType, ['postgresql', 'mysql', 'sqlserver'], true)) {
            return "Database type must be one of: PostgreSQL, MySQL, SQL Server.";
        }

        $port = $dataSetting['targetPort'] ?? '';
        if (trim((string) $port) !== '') {
            if (!ctype_digit(trim((string) $port)) || (int) $port < 1 || (int) $port > 65535) {
                return "Port must be a number between 1 and 65535, or left empty for the driver default.";
            }
        }

        // --- mapInfo structure validation (only when map is enabled) ---
        $mapInfo = $dataSetting['mapInfo'] ?? null;
        if (!is_array($mapInfo)) {
            return "Missing or invalid map configuration.";
        }

        $mapEnabled = $mapInfo['enabled'] ?? false;
        if ($mapEnabled === true) {
            $service = $mapInfo['service'] ?? null;
            if (!is_array($service)) {
                return "Map is enabled but no service configuration was provided.";
            }

            $serviceName = $service['name'] ?? null;
            if (empty($serviceName)) {
                return "Map service name is required when map is enabled.";
            }

            $keyRequired = $service['keyRequired'] ?? false;
            if ($keyRequired) {
                // testUrl must exist if a key is required (used by checkMapURLConnection)
                if (empty($service['testUrl'] ?? '')) {
                    return "Map service is missing a test URL required for key validation.";
                }
                // accessToken must be present
                $accessToken = trim($service['options']['accessToken'] ?? '');
                if (empty($accessToken)) {
                    return "An access token is required for the selected map service.";
                }
            }

            // GPS fields must be present and different
            $latitude = $mapInfo['gps']['latitude'] ?? null;
            $longitude = $mapInfo['gps']['longitude'] ?? null;
            if ($latitude === null || $longitude === null) {
                return "Latitude and longitude fields are required when map is enabled.";
            }
            if ($latitude === $longitude) {
                return "Latitude and longitude items must be different.";
            }

            // If using file-based basemap, filename must be set
            if ($serviceName === 'File') {
                $filename = trim($service['filename'] ?? '');
                if (empty($filename)) {
                    return "A map file must be selected when using the File tile provider.";
                }
                // Validate the filename is safe (no path traversal)
                if (!preg_match(self::SAFE_FILENAME_PATTERN, $filename) || str_contains($filename, '..')) {
                    return "Invalid map filename.";
                }
            }
        }

        return null; // all good
    }

    function updateMetaDataInfo(&$dataSetting): void {
        $enabled = null;
        $serviceType = null;
        if (($enabled = $dataSetting['mapInfo']['enabled'] ?? false) && ($serviceType = $dataSetting['mapInfo']['service']['name'] ?? '') === 'File') {
            $mapServer = new \Server();
            $mapfolderPath = realpath($this->kernel->getProjectDir() . '/maps/');
            $mbtFile = $mapfolderPath . DIRECTORY_SEPARATOR . $dataSetting['mapInfo']['service']['filename'];
            $metaData = $mapServer->metadataFromMbtiles($mbtFile);
            $dataSetting['mapInfo']['service']['options']['minZoom'] = $metaData['minzoom'];
            $dataSetting['mapInfo']['service']['options']['maxZoom'] = $metaData['maxzoom'];
            //bounds
            $dataSetting['mapInfo']['service']['bounds'] = $metaData['bounds'];
        }
    }

    #[Route('/dataSettings/fileInfo', name: 'mapFileInfo', methods: ['GET'])]
            function getMapFileList(Request $request): CSProResponse {

        $this->denyAccessUnlessGranted(SettingsVoter::SETTINGS_READ);
        $mapfolderPath = realpath($this->kernel->getProjectDir() . '/maps');

        $mapFiles = glob($mapfolderPath . DIRECTORY_SEPARATOR . "*.mbtiles");
        foreach ($mapFiles as &$fileName) {
            $fileName = basename($fileName);
        }
        $response = new CSProResponse(json_encode($mapFiles, JSON_THROW_ON_ERROR));
        $response->headers->set('Content-Length', strlen($response->getContent()));
        return $response;
    }

    #[Route('/dataSettings/{fileName}/content', name: 'mapUpload', methods: ['PUT'], requirements: ['filePath' => '.+'])]
            function updateMapFileContent(Request $request, $fileName): CSProResponse {
        $this->denyAccessUnlessGranted(SettingsVoter::SETTINGS_WRITE);

        // Validate filename: must end in .mbtiles, no path traversal, no unsafe characters
        if (
            !preg_match(self::SAFE_FILENAME_PATTERN, $fileName) ||
            str_contains($fileName, '..') ||
            strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'mbtiles'
        ) {
            $response = new CSProResponse();
            $response->setError(400, 'invalid_filename', 'Invalid file name. Only .mbtiles files with safe names are accepted.');
            $response->headers->set('Content-Length', strlen($response->getContent()));
            return $response;
        }

        $fileManager = new CSProFileManager($this->logger);
        $fileManager->rootFolder = realpath($this->kernel->getProjectDir() . '/maps');
        $md5Content = $request->headers->get('Content-MD5');
        $contentLength = $request->headers->get('Content-Length');
        $content = $request->getContent();

        $response = null;
        if (!isset($md5Content) && isset($contentLength)) {
            $saveFile = $contentLength == strlen($content);
        } else {
            $saveFile = md5($content) === $md5Content;
        }

        if ($saveFile) {
            $invalidFileName = is_dir($fileManager->rootFolder . DIRECTORY_SEPARATOR . $fileName);
            if ($invalidFileName == true) {
                $response = new CSProResponse();
                $response->setError(400, 'file_save_error', 'Error writing file. Filename is a directory');
            } else {
                $fileInfo = $fileManager->putFile($fileName, $content);
                if (isset($fileInfo)) {
                    $response = new CSProResponse(json_encode($fileInfo, JSON_THROW_ON_ERROR));
                } else {
                    $this->logger->error('Internal error writing file ' . $fileName);
                    $response = new CSProResponse();
                    $response->setError(500, 'file_save_error', 'Error writing file');
                }
            }
        } else {
            $response = new CSProResponse();
            $response->setError(403, 'file_save_failed', 'Unable to write to filePath. Content length or md5 does not match uploaded file contents or md5.');
        }
        $response->headers->set('Content-Length', strlen($response->getContent()));
        return $response;
    }

    #[Route('/dataSettings/{dictionaryId}', name: 'deleteSetting', methods: ['DELETE'])]
            function deleteSetting(Request $request, $dictionaryId): Response {
        $this->denyAccessUnlessGranted(SettingsVoter::SETTINGS_WRITE);

        $result = [];
        // Validate dictionary ID format before using it
        if (empty($dictionaryId) || !preg_match(self::DICTIONARY_ID_PATTERN, $dictionaryId)) {
            $result['description'] = 'Invalid dictionary ID format.';
            $result['code'] = 400;
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_BAD_REQUEST);
        }

        try {
            $isDeleted = $this->dataSettings->deleteDataSetting($dictionaryId);

            if ($isDeleted) {
                $result['description'] = 'Deleted configuration. Dictionary Id: ' . $dictionaryId;
                $result['code'] = 200;
                $this->logger->debug($result['description']);
                $response = new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
            } else {
                $result['description'] = 'Failed deleting configuration. Dictionary Id: ' . $dictionaryId;
                $result['code'] = 500;
                $response = new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        } catch (\Exception) {
            $result['description'] = 'Failed deleting configuration. Dictionary Id: ' . $dictionaryId;
            $result['code'] = 500;
            $response = new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        $response->headers->set('Content-Length', strlen($response->getContent()));
        return $response;
    }

    private function checkMapURLConnection($dataSetting): bool {
        $mapURL = '';
        $flag = false;
        try {
            $client = new Client();
            $mapInfo = $dataSetting['mapInfo'];
            $this->logger->debug('mapInfo: ' . print_r($mapInfo, true));

            if (($mapInfo['enabled'] ?? false) === false) {
                return true; // no need for verification
            }

            $service = $mapInfo['service'] ?? [];
            $keyRequired = $service['keyRequired'] ?? false;

            if (!$keyRequired) {
                return true;
            }

            // Both testUrl and accessToken are guaranteed present by validateDataSetting()
            $mapURL = $service['testUrl'];
            $key = rtrim($service['options']['accessToken']);
            $mapURL = str_replace('{access_token}', $key, rtrim($mapURL));
            $response = $client->request('GET', rtrim($mapURL), ['verify' => false]);

            if ($response->getStatusCode() != 200) {
                throw new \Exception("Failed to contact map server $mapURL : error " . $response->getStatusCode());
            }

            if (trim($service['name'] ?? '') === 'Mapbox') {
                $serverResponse = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
                if (isset($serverResponse['code']) && $serverResponse['code'] !== 'TokenValid') {
                    throw new \Exception("Failed to contact map server $mapURL : error " . $serverResponse['code']);
                }
            }

            $flag = true;
        } catch (\Exception $e) {
            throw new \Exception("Failed to contact map server $mapURL : " . $e->getMessage(), 0, $e);
        }

        return $flag;
    }


    // ------------------------------------------------------------------
    // Community layer: breakout scheduling and log management.
    //
    // Upstream has no scheduler and no log viewer, so none of the routes
    // below exist in vanilla 8.1. Permissions follow the 8.1 granular model:
    // /scheduler/* is settings.read / settings.write, /breakout/logs/* and
    // /logs/app are logs.read / logs.write, split by HTTP method.
    // ------------------------------------------------------------------

    #[Route('/scheduler/schedules', name: 'schedulerList', methods: ['GET'])]
    public function getSchedules(Request $request): Response {
        $this->denyAccessUnlessGranted(SettingsVoter::SETTINGS_READ);
        try {
            $schedules = $this->breakoutScheduler->getSchedules();
            $unscheduled = $this->breakoutScheduler->getUnscheduledDictionaries();
            $result = ['schedules' => $schedules, 'unscheduledDictionaries' => $unscheduled];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
        } catch (\Exception $e) {
            $result = ['description' => 'Failed to load schedules. ' . $e->getMessage(), 'code' => 500];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/scheduler/add', name: 'schedulerAdd', methods: ['POST'])]
    public function addSchedule(Request $request): Response {
        $this->denyAccessUnlessGranted(SettingsVoter::SETTINGS_WRITE);
        $result = [];
        try {
            $body = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $dictionaryId = (int) $body['dictionaryId'];
            $cronExpression = trim($body['cronExpression']);
            $enabled = (bool) ($body['enabled'] ?? false);

            if (!CronExpression::isValidExpression($cronExpression)) {
                $result = ['description' => 'Invalid cron expression: ' . $cronExpression, 'code' => 400];
                return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_BAD_REQUEST);
            }

            $this->breakoutScheduler->addSchedule($dictionaryId, $cronExpression, $enabled);
            $result = ['description' => 'Schedule added successfully', 'code' => 200];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
        } catch (\Exception $e) {
            $result = ['description' => 'Failed to add schedule. ' . $e->getMessage(), 'code' => 500];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/scheduler/update', name: 'schedulerUpdate', methods: ['PUT'])]
    public function updateSchedule(Request $request): Response {
        $this->denyAccessUnlessGranted(SettingsVoter::SETTINGS_WRITE);
        $result = [];
        try {
            $body = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $id = (int) $body['id'];
            $cronExpression = trim($body['cronExpression']);
            $enabled = (bool) ($body['enabled'] ?? false);

            if (!CronExpression::isValidExpression($cronExpression)) {
                $result = ['description' => 'Invalid cron expression: ' . $cronExpression, 'code' => 400];
                return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_BAD_REQUEST);
            }

            $this->breakoutScheduler->updateSchedule($id, $cronExpression, $enabled);
            $result = ['description' => 'Schedule updated successfully', 'code' => 200];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
        } catch (\Exception $e) {
            $result = ['description' => 'Failed to update schedule. ' . $e->getMessage(), 'code' => 500];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/scheduler/toggle', name: 'schedulerToggle', methods: ['PUT'])]
    public function toggleSchedule(Request $request): Response {
        $this->denyAccessUnlessGranted(SettingsVoter::SETTINGS_WRITE);
        $result = [];
        try {
            $body = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $id = (int) $body['id'];
            $this->breakoutScheduler->toggleSchedule($id);
            $result = ['description' => 'Schedule toggled successfully', 'code' => 200];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
        } catch (\Exception $e) {
            $result = ['description' => 'Failed to toggle schedule. ' . $e->getMessage(), 'code' => 500];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/scheduler/run-now', name: 'schedulerRunNow', methods: ['POST'])]
    public function runNowSchedule(Request $request): Response {
        $this->denyAccessUnlessGranted(SettingsVoter::SETTINGS_WRITE);
        $result = [];
        try {
            $body = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $id = (int) ($body['id'] ?? 0);
            $dictName = (string) ($body['dictName'] ?? '');

            // Validate dict name strictly: it is concatenated into a log file
            // path, and although escapeshellarg protects the exec call itself,
            // a name like "../../etc/passwd" would write the log outside
            // var/logs/breakout. Reject anything outside the CSPro identifier
            // charset.
            if (!preg_match('/^[A-Z0-9_]{1,64}$/', $dictName)) {
                $result = ['description' => 'Invalid dictName. Must match: ^[A-Z0-9_]{1,64}$', 'code' => 400];
                return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_BAD_REQUEST);
            }

            $phpBinary = (new PhpExecutableFinder())->find();
            $consolePath = realpath($this->kernel->getProjectDir() . '/bin/console');
            $logDir = $this->kernel->getProjectDir() . '/var/logs/breakout';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }

            $timestamp = date('Y-m-d_H-i-s');
            $logFileName = $dictName . '_manual_' . $timestamp . '.log';
            $logFilePath = $logDir . '/' . $logFileName;

            $shellCmd = sprintf(
                '%s %s csweb:process-cases-by-dict %s --env=%s > %s 2>&1 &',
                escapeshellarg($phpBinary),
                escapeshellarg($consolePath),
                escapeshellarg($dictName),
                escapeshellarg($this->kernel->getEnvironment()),
                escapeshellarg($logFilePath)
            );

            exec($shellCmd);

            // Mark as running (exit_code -1 = in progress) only when the
            // request comes from a real schedule entry. Manual triggers from
            // the dashboard pass id=0 and skip the markRun call to avoid
            // creating phantom schedule rows.
            if ($id > 0) {
                $this->breakoutScheduler->markRun($id, -1, $logFileName);
            }

            $result = ['description' => "Breakout started for $dictName. Log: $logFileName", 'code' => 200];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error('Failed to run breakout', ['context' => (string) $e]);
            $result = ['description' => 'Failed to run breakout.', 'code' => 500];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/scheduler/{id}', name: 'schedulerDelete', methods: ['DELETE'])]
    public function deleteSchedule(Request $request, $id): Response {
        $this->denyAccessUnlessGranted(SettingsVoter::SETTINGS_WRITE);
        $result = [];
        try {
            $isDeleted = $this->breakoutScheduler->deleteSchedule((int) $id);
            if ($isDeleted) {
                $result = ['description' => 'Schedule deleted', 'code' => 200];
                return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
            } else {
                $result = ['description' => 'Schedule not found', 'code' => 404];
                return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_NOT_FOUND);
            }
        } catch (\Exception $e) {
            $result = ['description' => 'Failed to delete schedule. ' . $e->getMessage(), 'code' => 500];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/breakout/logs', name: 'breakoutLogsList', methods: ['GET'])]
    public function breakoutLogsList(Request $request): Response {
        $this->denyAccessUnlessGranted(LogsVoter::LOGS_READ);
        try {
            $logDir = $this->kernel->getProjectDir() . '/var/logs/breakout';
            $dictFilter = $request->query->get('dict', '');
            $logs = [];
            $dictionaries = [];

            if (is_dir($logDir)) {
                $files = glob($logDir . '/*.log');
                foreach ($files as $filePath) {
                    $filename = basename($filePath);
                    // Parse: {DICT_NAME}_manual_{Y-m-d_H-i-s}.log or {DICT_NAME}_{Y-m-d_H-i-s}.log
                    if (preg_match('/^(.+?)_(manual_)?(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.log$/', $filename, $m)) {
                        $dictName = $m[1];
                        $type = $m[2] ? 'manual' : 'scheduled';
                        $dateStr = str_replace(['_', '-'], [' ', '-'], $m[3]);
                        // Convert "2024-01-15 14-30-00" to "2024-01-15 14:30:00"
                        $dateStr = preg_replace('/(\d{2})-(\d{2})-(\d{2})$/', '$1:$2:$3', $dateStr);
                        $size = filesize($filePath);

                        if (!in_array($dictName, $dictionaries)) {
                            $dictionaries[] = $dictName;
                        }

                        if ($dictFilter === '' || $dictFilter === $dictName) {
                            $logs[] = [
                                'filename' => $filename,
                                'dictName' => $dictName,
                                'type' => $type,
                                'date' => $dateStr,
                                'size' => $size,
                            ];
                        }
                    }
                }
                // Sort by date descending
                usort($logs, function ($a, $b) {
                    return strcmp($b['date'], $a['date']);
                });
                sort($dictionaries);
            }

            $result = ['logs' => $logs, 'dictionaries' => $dictionaries];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
        } catch (\Exception $e) {
            $result = ['description' => 'Failed to list logs. ' . $e->getMessage(), 'code' => 500];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/breakout/logs/delete', name: 'breakoutLogDelete', methods: ['DELETE'])]
    public function breakoutLogDelete(Request $request): Response {
        $this->denyAccessUnlessGranted(LogsVoter::LOGS_WRITE);
        try {
            $body = json_decode($request->getContent(), true);
            $file = $body['file'] ?? '';
            $safe = basename($file);
            $logDir = $this->kernel->getProjectDir() . '/var/logs/breakout';
            $filePath = $logDir . '/' . $safe;

            if ($safe === '' || !file_exists($filePath)) {
                $result = ['description' => 'Log file not found.', 'code' => 404];
                return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_NOT_FOUND);
            }

            unlink($filePath);
            $result = ['description' => 'Log file "' . $safe . '" deleted.', 'code' => 200];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
        } catch (\Exception $e) {
            $result = ['description' => 'Failed to delete log. ' . $e->getMessage(), 'code' => 500];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/breakout/logs/delete-bulk', name: 'breakoutLogDeleteBulk', methods: ['POST'])]
    public function breakoutLogDeleteBulk(Request $request): Response {
        $this->denyAccessUnlessGranted(LogsVoter::LOGS_WRITE);
        try {
            $body = json_decode($request->getContent(), true);
            $files = $body['files'] ?? [];
            $logDir = $this->kernel->getProjectDir() . '/var/logs/breakout';
            $deleted = 0;
            $failed = 0;

            foreach ($files as $file) {
                $safe = basename($file);
                $filePath = $logDir . '/' . $safe;
                if ($safe !== '' && file_exists($filePath)) {
                    if (unlink($filePath)) {
                        $deleted++;
                    } else {
                        $failed++;
                    }
                } else {
                    $failed++;
                }
            }

            $result = [
                'deleted' => $deleted,
                'failed' => $failed,
                'description' => $deleted . ' log file(s) deleted.' . ($failed > 0 ? ' ' . $failed . ' failed.' : ''),
                'code' => 200,
            ];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
        } catch (\Exception $e) {
            $result = ['description' => 'Bulk delete failed. ' . $e->getMessage(), 'code' => 500];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/breakout/logs/purge-all', name: 'breakoutLogPurgeAll', methods: ['POST'])]
    public function breakoutLogPurgeAll(Request $request): Response {
        $this->denyAccessUnlessGranted(LogsVoter::LOGS_WRITE);
        try {
            $body = json_decode($request->getContent(), true) ?: [];
            $dictFilter = isset($body['dict']) ? trim((string) $body['dict']) : '';
            $logDir = $this->kernel->getProjectDir() . '/var/logs/breakout';
            $deleted = 0;
            $failed = 0;

            if (is_dir($logDir)) {
                $files = glob($logDir . '/*.log') ?: [];
                foreach ($files as $filePath) {
                    $filename = basename($filePath);
                    if ($dictFilter !== '') {
                        if (!preg_match('/^(.+?)_(manual_)?\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.log$/', $filename, $m)
                            || $m[1] !== $dictFilter) {
                            continue;
                        }
                    }
                    if (@unlink($filePath)) {
                        $deleted++;
                    } else {
                        $failed++;
                    }
                }
            }

            $scope = $dictFilter === '' ? 'all dictionaries' : 'dictionary "' . $dictFilter . '"';
            $result = [
                'deleted' => $deleted,
                'failed' => $failed,
                'description' => $deleted . ' log file(s) purged for ' . $scope . '.'
                    . ($failed > 0 ? ' ' . $failed . ' failed.' : ''),
                'code' => 200,
            ];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
        } catch (\Exception $e) {
            $result = ['description' => 'Purge failed. ' . $e->getMessage(), 'code' => 500];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/breakout/logs/content', name: 'breakoutLogContent', methods: ['GET'])]
    public function breakoutLogContent(Request $request): Response {
        $this->denyAccessUnlessGranted(LogsVoter::LOGS_READ);
        $file = $request->query->get('file', '');
        $safe = basename($file);
        $logDir = $this->kernel->getProjectDir() . '/var/logs/breakout';
        $filePath = $logDir . '/' . $safe;

        if ($safe === '' || !file_exists($filePath)) {
            return new Response('Log file not found.', Response::HTTP_NOT_FOUND, ['Content-Type' => 'text/plain']);
        }

        $content = file_get_contents($filePath);
        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="' . $safe . '"',
        ]);
    }

    #[Route('/logs/app', name: 'appLogInfo', methods: ['GET'])]
    public function appLogInfo(Request $request): Response {
        $this->denyAccessUnlessGranted(LogsVoter::LOGS_READ);
        try {
            $logDir = $this->kernel->getProjectDir() . '/var/logs';
            $env = $this->kernel->getEnvironment();

            // Try env-specific file first (ui.dev.log), then generic (ui.log)
            $candidates = [
                $logDir . '/ui.' . $env . '.log',
                $logDir . '/ui.log',
            ];

            $logPath = null;
            foreach ($candidates as $c) {
                if (file_exists($c)) {
                    $logPath = $c;
                    break;
                }
            }

            if ($logPath === null) {
                $result = ['exists' => false];
                return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
            }

            $full = $request->query->get('full', '');
            $size = filesize($logPath);
            $lineCount = 0;
            $fh = fopen($logPath, 'r');
            if ($fh) {
                while (!feof($fh)) {
                    fgets($fh);
                    $lineCount++;
                }
                fclose($fh);
            }

            $result = [
                'exists' => true,
                'filename' => basename($logPath),
                'size' => $size,
                'lines' => $lineCount,
                'modified' => date('Y-m-d H:i:s', filemtime($logPath)),
            ];

            if ($full === '1') {
                $result['content'] = file_get_contents($logPath);
            }

            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
        } catch (\Exception $e) {
            $result = ['description' => 'Failed to read app log. ' . $e->getMessage(), 'code' => 500];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/logs/app', name: 'appLogDelete', methods: ['DELETE'])]
    public function appLogDelete(): Response {
        $this->denyAccessUnlessGranted(LogsVoter::LOGS_WRITE);
        try {
            $logDir = $this->kernel->getProjectDir() . '/var/logs';
            $env = $this->kernel->getEnvironment();

            $candidates = [
                $logDir . '/ui.' . $env . '.log',
                $logDir . '/ui.log',
            ];

            $logPath = null;
            foreach ($candidates as $c) {
                if (file_exists($c)) {
                    $logPath = $c;
                    break;
                }
            }

            if ($logPath === null) {
                $result = ['description' => 'No application log file found.', 'code' => 404];
                return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_NOT_FOUND);
            }

            $filename = basename($logPath);
            if (!unlink($logPath)) {
                $result = ['description' => 'Failed to delete ' . $filename, 'code' => 500];
                return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $result = ['description' => 'Application log ' . $filename . ' deleted successfully.', 'code' => 200];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
        } catch (\Exception $e) {
            $result = ['description' => 'Failed to delete app log. ' . $e->getMessage(), 'code' => 500];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}
