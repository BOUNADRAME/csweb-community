<?php

namespace AppBundle\Controller\ui;

use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use AppBundle\Service\HttpHelper;
use AppBundle\Service\PdoHelper;
use AppBundle\CSPro\Data\DataSettings;
use AppBundle\CSPro\Data\BreakoutScheduler;
use AppBundle\CSPro\CSProResponse;
use Cron\CronExpression;
use GuzzleHttp\Client;
use AppBundle\CSPro\FileManager;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\PhpExecutableFinder;

require_once __DIR__ . '/../../../../maps/server.php';

/**
 * Description of DataSettingsController
 *
 * @author savy
 */
class DataSettingsController extends AbstractController implements TokenAuthenticatedController {

    private $dataSettings;
    private $breakoutScheduler;

    public function __construct(private HttpHelper $client, private PdoHelper $pdo, KernelInterface $kernel, private LoggerInterface $logger) {
        $this->kernel = $kernel;
    }

//override the setcontainer to get access to container parameters and initiailize the roles repository
    public function setContainer(?ContainerInterface $container): ?ContainerInterface {
        $this->dataSettings = new DataSettings($this->pdo, $this->logger);
        $this->breakoutScheduler = new BreakoutScheduler($this->pdo, $this->logger);
        return parent::setContainer($container);
    }

    #[Route('/dataSettings', name: 'dataSettings', methods: ['GET'])]
    public function viewDataSettingsAction(Request $request): Response {
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
        // Set the oauth token
        $dataSettings = $this->dataSettings->getDataSettings();
        $this->logger->debug('data settings ' . print_r($dataSettings, true));
        return $this->render('dataSettings.twig', ['dataSettings' => $dataSettings]);
    }

    #[Route('/getSettings', name: 'getSettings', methods: ['GET'])]
    public function getDataSettings(Request $request): Response {
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
//get data settings
        $dataSettings = $this->dataSettings->getDataSettings();
        $this->logger->debug('data settings ' . print_r($dataSettings, true));
        return $this->render('dataSettings.twig', ['dataSettings' => $dataSettings]);
    }

    #[Route('/addSetting', name: 'addSetting', methods: ['POST'])]
    public function addDataSetting(Request $request): Response {
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');

        $result = [];
        //get the json setting  info to add
        $body = $request->getContent();
        $dataSetting = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $label = $dataSetting['label'];
        $this->updateMetaDataInfo($dataSetting);
        try {
            $isValidMapURL = $this->checkMapURLConnection($dataSetting);
            $isAddded = $this->dataSettings->addDataSetting($dataSetting);

            if ($isAddded === true && $isValidMapURL === true) {
                //try connectint to the server 
                $result['description'] = "Added configuration for $label";
                $result['code'] = 200;
                $response = new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
            } else {
                $result['description'] = "Failed to add  configuration for $label";
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
            $result['description'] = "Failed to add  configuration for $label. $errMsg";
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
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
        $result = [];
        //get the json setting  info to add
        $body = $request->getContent();
        $dataSetting = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $label = $dataSetting['label'];
        $this->updateMetaDataInfo($dataSetting);
        try {
            $isValidMapURL = $this->checkMapURLConnection($dataSetting);
            $isAddded = $this->dataSettings->updateDataSetting($dataSetting);

            if ($isAddded === true && $isValidMapURL === true) {
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
            $result['description'] = "Failed to update  configuration for $label. $errMsg";
            $result['code'] = 500;
            $response = new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
            $this->logger->error("Failed updating configuration", ["context" => (string) $e]);
            return $response;
        }
        $response->headers->set('Content-Length', strlen($response->getContent()));
        return $response;
    }

    function updateMetaDataInfo(&$dataSetting) {

        $enabled = null;
        $serviceType = null;
        if (($enabled = $dataSetting['mapInfo']['enabled'] ?? false) && ($serviceType = $dataSetting['mapInfo']['service']['name'] ?? '') === 'File') {
            //get the metadata update minZoom | maxZooom | add bounds | Url extension ?
            $mapServer = new \Server();
            $mapfolderPath = realpath($this->kernel->getProjectDir() . '/maps/');
            $mbtFile = $mapfolderPath . DIRECTORY_SEPARATOR . $dataSetting['mapInfo']['service']['filename'];
            $metaData = $mapServer->metadataFromMbtiles($mbtFile);
            //zoom information
            $minZoom = $metaData['minzoom'];
            $dataSetting['mapInfo']['service']['options']['minZoom'] = $metaData['minzoom'];
            $dataSetting['mapInfo']['service']['options']['maxZoom'] = $metaData['maxzoom'];
            //bounds
            $dataSetting['mapInfo']['service']['bounds'] = $metaData['bounds'];
        }
    }

    #[Route('/dataSettings/fileInfo', name: 'mapFileInfo', methods: ['GET'])]
            function getMapFileList(Request $request): CSProResponse {

        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
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
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');

        $fileManager = new FileManager();
        $fileManager->rootFolder = realpath($this->kernel->getProjectDir() . '/maps');
        $md5Content = $request->headers->get('Content-MD5');
        $contentLength = $request->headers->get('Content-Length');
        $content = $request->getContent();
        //var_dump($content);
        $response = null;
        if (!isset($md5Content) && isset($contentLength)) {
            $saveFile = $contentLength == strlen($content);
        } else {
            //echo 'generated md5 :' . md5($content);
            //echo '$md5Content :' .$md5Content;
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
                    $this->logger->error('Internal error writing file' . $fileName);
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
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');

        $result = [];
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

    #[Route('/scheduler/schedules', name: 'schedulerList', methods: ['GET'])]
    public function getSchedules(Request $request): Response {
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
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
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
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
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
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
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
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
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
        $result = [];
        try {
            $body = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $id = (int) $body['id'];
            $dictName = $body['dictName'] ?? '';

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

            // Mark as running (exit_code -1 = in progress)
            $this->breakoutScheduler->markRun($id, -1, $logFileName);

            $result = ['description' => "Breakout started for $dictName. Log: $logFileName", 'code' => 200];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
        } catch (\Exception $e) {
            $result = ['description' => 'Failed to run breakout. ' . $e->getMessage(), 'code' => 500];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/scheduler/{id}', name: 'schedulerDelete', methods: ['DELETE'])]
    public function deleteSchedule(Request $request, $id): Response {
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
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
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
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
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
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
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
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

    #[Route('/breakout/logs/content', name: 'breakoutLogContent', methods: ['GET'])]
    public function breakoutLogContent(Request $request): Response {
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
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
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
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
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
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

    private function checkMapURLConnection($dataSetting): bool {
        $e = null;
        $flag = false;
        try {
            $client = new Client();
            $mapInfo = $dataSetting['mapInfo'];
            $this->logger->debug('mapInfo: ' . print_r($mapInfo, true));
            if ($mapInfo['enabled'] === false) {
                $flag = true; //no need for verification
            } else {
                if ($mapInfo['service']['keyRequired']) {//only need to check if a key is required
                    $mapURL = $mapInfo['service']['testUrl'];
                    $key = rtrim($mapInfo['service']['options']['accessToken']);
                    $mapURL = str_replace('{access_token}', $key, rtrim($mapURL));
                    $response = $client->request('GET', rtrim($mapURL), ['verify' => false]);
                } else {
                    return true;
                }
                if ($response->getStatusCode() != 200) {
                    throw new \Exception("Failed to contact map server $mapURL : error " . $response->getStatusCode());
                } else {
                    if (trim($mapInfo['service']['name']) === 'Mapbox') {
                        $serverResponse = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
                        if (isset($serverResponse['code']) && $serverResponse['code'] !== 'TokenValid') {
                            throw new \Exception("Failed to contact map server $mapURL : error " . $serverResponse['code']);
                        }
                    }
                    $flag = true;
                }
            }
        } catch (Exception) {
            throw new \Exception("Failed to contact map server $mapURL : " . $e->getMessage());
        }

        return $flag;
    }

}
