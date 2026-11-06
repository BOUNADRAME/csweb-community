<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use App\Service\PdoHelper;
use App\CSPro\Data\BreakoutScheduler;
use Psr\Log\LoggerInterface;

class SchedulerRunCommand extends Command {

    protected static $defaultName = 'csweb:scheduler-run';
    use LockableTrait;

    public function __construct(private PdoHelper $pdo, private KernelInterface $kernel, private LoggerInterface $logger) {
        parent::__construct();
    }

    protected function configure() {
        $this->setDescription('Run due breakout schedules (designed to be called every minute via crontab)');
        // Community layer: "Run now" runs one schedule through this command, so
        // the exit code and log file are recorded when the breakout ends
        // instead of staying at -1 (in progress) forever.
        $this->addOption('id', null, InputOption::VALUE_REQUIRED, 'Run this schedule now, even if it is not due');
        $this->addOption('log-file', null, InputOption::VALUE_REQUIRED, 'Log file name to write under var/logs/breakout (with --id)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);

        $onlyId = $input->getOption('id') !== null ? (int) $input->getOption('id') : null;
        $forcedLog = $input->getOption('log-file');
        if ($forcedLog !== null && !preg_match('/^[A-Za-z0-9_.-]{1,128}\.log$/', (string) $forcedLog)) {
            $io->error('Invalid --log-file name.');
            return Command::FAILURE;
        }

        // A manual run takes its own lock so it neither waits for nor blocks
        // the every-minute cron pass.
        if (!$this->lock($onlyId !== null ? 'csweb-scheduler-run-' . $onlyId : null)) {
            $io->warning('The scheduler is already running in another process.');
            return Command::SUCCESS;
        }

        $scheduler = new BreakoutScheduler($this->pdo, $this->logger);
        if ($onlyId !== null) {
            $one = $scheduler->getScheduleById($onlyId);
            $dueSchedules = $one ? [$one] : [];
        } else {
            $dueSchedules = $scheduler->getDueSchedules();
        }

        if (empty($dueSchedules)) {
            $io->text('No schedules due.');
            $this->release();
            return Command::SUCCESS;
        }

        $phpBinary = (new PhpExecutableFinder())->find();
        $consolePath = realpath($this->kernel->getProjectDir() . '/bin/console');
        $logDir = $this->kernel->getProjectDir() . '/var/logs/breakout';

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        foreach ($dueSchedules as $schedule) {
            $dictName = $schedule['name'];
            $timestamp = date('Y-m-d_H-i-s');
            $logFileName = $forcedLog !== null ? (string) $forcedLog : $dictName . '_' . $timestamp . '.log';
            $logFilePath = $logDir . '/' . $logFileName;

            $io->text("Running breakout for dictionary: $dictName");
            $this->logger->info("Scheduler: running breakout for $dictName");

            $cmd = [
                $phpBinary,
                $consolePath,
                'csweb:process-cases-by-dict',
                $dictName,
                '--env=' . $this->kernel->getEnvironment(),
            ];

            $process = new Process($cmd);
            $process->setTimeout(3600);
            $process->run();

            $logContent = "Command: " . implode(' ', $cmd) . "\n"
                . "Started: $timestamp\n"
                . "Exit code: " . $process->getExitCode() . "\n\n"
                . "--- STDOUT ---\n" . $process->getOutput() . "\n"
                . "--- STDERR ---\n" . $process->getErrorOutput() . "\n";

            file_put_contents($logFilePath, $logContent);

            $scheduler->markRun(
                (int) $schedule['id'],
                $process->getExitCode(),
                $logFileName
            );

            if ($process->getExitCode() === 0) {
                $io->success("Breakout completed for $dictName");
            } else {
                $io->error("Breakout failed for $dictName (exit code: " . $process->getExitCode() . ")");
            }
        }

        $this->release();
        return Command::SUCCESS;
    }
}
