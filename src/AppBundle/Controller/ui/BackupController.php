<?php

namespace AppBundle\Controller\ui;

use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use AppBundle\Service\PdoHelper;
use AppBundle\CSPro\Data\BackupScheduler;
use Cron\CronExpression;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\PhpExecutableFinder;

class BackupController extends AbstractController implements TokenAuthenticatedController {

    private $backupScheduler;

    public function __construct(private PdoHelper $pdo, private KernelInterface $kernel, private LoggerInterface $logger) {
    }

    public function setContainer(?ContainerInterface $container): ?ContainerInterface {
        $this->backupScheduler = new BackupScheduler($this->pdo, $this->logger);
        return parent::setContainer($container);
    }

    #[Route('/backup', name: 'backup', methods: ['GET'])]
    public function viewBackupAction(Request $request): Response {
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
        return $this->render('backup.twig');
    }

    #[Route('/backup/config', name: 'backupConfig', methods: ['GET'])]
    public function getConfig(Request $request): Response {
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
        try {
            $config = $this->backupScheduler->getConfig();
            return new Response(json_encode($config, JSON_THROW_ON_ERROR), Response::HTTP_OK);
        } catch (\Exception $e) {
            $result = ['description' => 'Failed to load backup config. ' . $e->getMessage(), 'code' => 500];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/backup/config', name: 'backupConfigUpdate', methods: ['PUT'])]
    public function updateConfig(Request $request): Response {
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
        try {
            $body = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $cronExpression = trim($body['cronExpression']);
            $retentionDays = (int) ($body['retentionDays'] ?? 30);
            $enabled = (bool) ($body['enabled'] ?? false);

            if (!CronExpression::isValidExpression($cronExpression)) {
                $result = ['description' => 'Invalid cron expression: ' . $cronExpression, 'code' => 400];
                return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_BAD_REQUEST);
            }

            if ($retentionDays < 1) {
                $result = ['description' => 'Retention days must be at least 1.', 'code' => 400];
                return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_BAD_REQUEST);
            }

            $this->backupScheduler->updateConfig($cronExpression, $retentionDays, $enabled);
            $result = ['description' => 'Backup configuration updated successfully', 'code' => 200];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
        } catch (\Exception $e) {
            $result = ['description' => 'Failed to update backup config. ' . $e->getMessage(), 'code' => 500];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/backup/toggle', name: 'backupToggle', methods: ['PUT'])]
    public function toggleBackup(Request $request): Response {
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
        try {
            $this->backupScheduler->toggleEnabled();
            $result = ['description' => 'Backup toggled successfully', 'code' => 200];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
        } catch (\Exception $e) {
            $result = ['description' => 'Failed to toggle backup. ' . $e->getMessage(), 'code' => 500];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/backup/run-now', name: 'backupRunNow', methods: ['POST'])]
    public function runNow(Request $request): Response {
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
        try {
            $phpBinary = (new PhpExecutableFinder())->find();
            $consolePath = realpath($this->kernel->getProjectDir() . '/bin/console');
            $logDir = $this->kernel->getProjectDir() . '/var/logs/backup';
            $backupDir = $this->kernel->getProjectDir() . '/var/backups';

            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            // Load DB credentials
            $configPath = $this->kernel->getProjectDir() . '/src/AppBundle/config.php';
            require_once $configPath;

            $timestamp = date('Y-m-d_H-i-s');
            $backupFileName = 'csweb_metadata_manual_' . $timestamp . '.sql.gz';
            $backupFilePath = $backupDir . '/' . $backupFileName;
            $logFileName = 'backup_manual_' . $timestamp . '.log';
            $logFilePath = $logDir . '/' . $logFileName;

            // Run mysqldump async
            $shellCmd = sprintf(
                'mysqldump --skip-ssl -h %s -u %s --password=%s %s | gzip > %s 2>%s &',
                escapeshellarg(DBHOST),
                escapeshellarg(DBUSER),
                escapeshellarg(DBPASS),
                escapeshellarg(DBNAME),
                escapeshellarg($backupFilePath),
                escapeshellarg($logFilePath)
            );

            exec($shellCmd);

            // Register file in DB + mark config as running
            $this->backupScheduler->registerFile($backupFileName, 'manual', $logFileName);
            $this->backupScheduler->markRun(-1, $logFileName);

            $result = ['description' => "Backup started. File: $backupFileName", 'code' => 200];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
        } catch (\Exception $e) {
            $result = ['description' => 'Failed to run backup. ' . $e->getMessage(), 'code' => 500];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/backup/files', name: 'backupFilesList', methods: ['GET'])]
    public function listFiles(Request $request): Response {
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
        try {
            $backupDir = $this->kernel->getProjectDir() . '/var/backups';
            $files = [];

            // Get DB records indexed by filename
            $dbRows = $this->backupScheduler->listFiles();
            $dbIndex = [];
            foreach ($dbRows as $row) {
                $dbIndex[$row['filename']] = $row;
            }

            // Scan disk files
            $diskFiles = [];
            if (is_dir($backupDir)) {
                $globFiles = glob($backupDir . '/*.sql.gz');
                foreach ($globFiles as $filePath) {
                    $filename = basename($filePath);
                    $diskFiles[$filename] = true;
                    $dbInfo = $dbIndex[$filename] ?? null;
                    $files[] = [
                        'filename' => $filename,
                        'date' => $dbInfo ? $dbInfo['created_time'] : date('Y-m-d H:i:s', filemtime($filePath)),
                        'size' => filesize($filePath),
                        'sizeFormatted' => $this->formatBytes(filesize($filePath)),
                        'source' => $dbInfo['source'] ?? 'unknown',
                        'status' => $dbInfo['status'] ?? 'unknown',
                    ];

                    // Sync: file exists on disk but not in DB → register it
                    if (!$dbInfo) {
                        $this->backupScheduler->registerFile($filename, 'unknown');
                        $this->backupScheduler->completeFile($filename, 0, filesize($filePath));
                    }
                }
            }

            // Sync: DB record exists but file missing on disk → delete from DB
            foreach ($dbIndex as $filename => $row) {
                if (!isset($diskFiles[$filename])) {
                    $this->backupScheduler->deleteFileRecord($filename);
                }
            }

            // Sort by date descending
            usort($files, function ($a, $b) {
                return strcmp($b['date'], $a['date']);
            });

            return new Response(json_encode(['files' => $files], JSON_THROW_ON_ERROR), Response::HTTP_OK);
        } catch (\Exception $e) {
            $result = ['description' => 'Failed to list backup files. ' . $e->getMessage(), 'code' => 500];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/backup/files/download', name: 'backupFileDownload', methods: ['GET'])]
    public function downloadFile(Request $request): Response {
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
        $file = $request->query->get('file', '');
        $safe = basename($file);
        $backupDir = $this->kernel->getProjectDir() . '/var/backups';
        $filePath = $backupDir . '/' . $safe;

        if ($safe === '' || !file_exists($filePath)) {
            return new Response('Backup file not found.', Response::HTTP_NOT_FOUND, ['Content-Type' => 'text/plain']);
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $safe);
        return $response;
    }

    #[Route('/backup/files/delete', name: 'backupFileDelete', methods: ['DELETE'])]
    public function deleteFile(Request $request): Response {
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
        try {
            $body = json_decode($request->getContent(), true);
            $file = $body['file'] ?? '';
            $safe = basename($file);
            $backupDir = $this->kernel->getProjectDir() . '/var/backups';
            $filePath = $backupDir . '/' . $safe;

            if ($safe === '') {
                $result = ['description' => 'Backup file not found.', 'code' => 404];
                return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_NOT_FOUND);
            }

            // Delete physical file if exists
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            // Delete associated log file
            $this->deleteAssociatedLog($safe);
            // Delete DB record
            $this->backupScheduler->deleteFileRecord($safe);

            $result = ['description' => 'Backup file "' . $safe . '" deleted.', 'code' => 200];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
        } catch (\Exception $e) {
            $result = ['description' => 'Failed to delete backup file. ' . $e->getMessage(), 'code' => 500];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/backup/files/delete-bulk', name: 'backupFileDeleteBulk', methods: ['POST'])]
    public function deleteFilesBulk(Request $request): Response {
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
        try {
            $body = json_decode($request->getContent(), true);
            $files = $body['files'] ?? [];
            $backupDir = $this->kernel->getProjectDir() . '/var/backups';
            $deleted = 0;
            $failed = 0;
            $deletedNames = [];

            foreach ($files as $file) {
                $safe = basename($file);
                if ($safe === '') {
                    $failed++;
                    continue;
                }
                $filePath = $backupDir . '/' . $safe;
                // Delete physical file if exists
                if (file_exists($filePath)) {
                    if (unlink($filePath)) {
                        $deleted++;
                        $deletedNames[] = $safe;
                    } else {
                        $failed++;
                    }
                } else {
                    // File missing on disk, still clean DB
                    $deleted++;
                    $deletedNames[] = $safe;
                }
            }

            // Delete associated logs + DB records
            if (!empty($deletedNames)) {
                foreach ($deletedNames as $dName) {
                    $this->deleteAssociatedLog($dName);
                }
                $this->backupScheduler->deleteFileRecords($deletedNames);
            }

            $result = [
                'deleted' => $deleted,
                'failed' => $failed,
                'description' => $deleted . ' backup file(s) deleted.' . ($failed > 0 ? ' ' . $failed . ' failed.' : ''),
                'code' => 200,
            ];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
        } catch (\Exception $e) {
            $result = ['description' => 'Bulk delete failed. ' . $e->getMessage(), 'code' => 500];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/backup/cleanup', name: 'backupCleanupNow', methods: ['POST'])]
    public function cleanupNow(Request $request): Response {
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
        try {
            $config = $this->backupScheduler->getConfig();
            $retentionDays = $config ? (int) $config['retention_days'] : 30;
            $backupDir = $this->kernel->getProjectDir() . '/var/backups';
            $deleted = 0;
            $deletedNames = [];

            if (is_dir($backupDir)) {
                $cutoff = time() - ($retentionDays * 86400);
                $files = glob($backupDir . '/*.sql.gz');
                foreach ($files as $file) {
                    if (filemtime($file) < $cutoff) {
                        $fname = basename($file);
                        if (unlink($file)) {
                            $deleted++;
                            $deletedNames[] = $fname;
                        }
                    }
                }
            }

            // Delete associated logs + DB records for purged files
            if (!empty($deletedNames)) {
                foreach ($deletedNames as $dName) {
                    $this->deleteAssociatedLog($dName);
                }
                $this->backupScheduler->deleteFileRecords($deletedNames);
            }

            $result = ['description' => "$deleted backup file(s) purged (retention: $retentionDays days).", 'code' => 200, 'deleted' => $deleted];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
        } catch (\Exception $e) {
            $result = ['description' => 'Cleanup failed. ' . $e->getMessage(), 'code' => 500];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/backup/logs', name: 'backupLogsList', methods: ['GET'])]
    public function logsList(Request $request): Response {
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
        try {
            $logDir = $this->kernel->getProjectDir() . '/var/logs/backup';
            $logs = [];

            if (is_dir($logDir)) {
                $files = glob($logDir . '/*.log');
                foreach ($files as $filePath) {
                    $filename = basename($filePath);
                    $logs[] = [
                        'filename' => $filename,
                        'date' => date('Y-m-d H:i:s', filemtime($filePath)),
                        'size' => filesize($filePath),
                        'sizeFormatted' => $this->formatBytes(filesize($filePath)),
                    ];
                }
                usort($logs, function ($a, $b) {
                    return strcmp($b['date'], $a['date']);
                });
            }

            return new Response(json_encode(['logs' => $logs], JSON_THROW_ON_ERROR), Response::HTTP_OK);
        } catch (\Exception $e) {
            $result = ['description' => 'Failed to list backup logs. ' . $e->getMessage(), 'code' => 500];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/backup/logs/content', name: 'backupLogContent', methods: ['GET'])]
    public function logContent(Request $request): Response {
        $this->denyAccessUnlessGranted('ROLE_SETTINGS_ALL');
        $file = $request->query->get('file', '');
        $safe = basename($file);
        $logDir = $this->kernel->getProjectDir() . '/var/logs/backup';
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

    /**
     * Derive log filename from backup filename.
     * csweb_metadata_manual_2026-03-20_14-04-30.sql.gz → backup_manual_2026-03-20_14-04-30.log
     * csweb_metadata_2026-03-20_02-00-00.sql.gz        → backup_2026-03-20_02-00-00.log
     */
    private function backupToLogFilename(string $backupFilename): string {
        // Remove extension
        $name = preg_replace('/\.sql\.gz$/', '', $backupFilename);
        // Replace prefix csweb_metadata_ → backup_
        $logName = preg_replace('/^csweb_metadata_/', 'backup_', $name);
        return $logName . '.log';
    }

    private function deleteAssociatedLog(string $backupFilename): void {
        $logDir = $this->kernel->getProjectDir() . '/var/logs/backup';
        $logFile = $logDir . '/' . $this->backupToLogFilename($backupFilename);
        if (file_exists($logFile)) {
            unlink($logFile);
        }
    }

    private function formatBytes(int $bytes): string {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
