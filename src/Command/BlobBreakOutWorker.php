<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use App\Service\PdoHelper;
use Psr\Log\LoggerInterface;
use App\CSPro\DictionarySchemaHelper;
use App\CSPro\Data\BreakoutErrorFormatter;

/**
 * Description of BlobBreakOutWorker
 *
 * @author savy
 */
class BlobBreakOutWorker extends Command {

    protected static $defaultName = 'csweb:blob-breakout-worker';

    public function __construct(private PdoHelper $pdo, private LoggerInterface $logger) {
        parent::__construct();
    }

    protected function configure() {
        $this->setDescription('CSWeb blob breakout processing thread')
                ->addOption('dictionaryName', 'd', InputOption::VALUE_REQUIRED, 'Dictionary Name')
                ->addOption('jobId', 'j', InputOption::VALUE_REQUIRED, 'Job Id');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $dictName = $input->getOption('dictionaryName');
        $jobId = $input->getOption('jobId');
        $output->writeln('Thread started processing cases Dictionary: ' . $dictName . ' JobID: ' . $jobId);
        $this->logger->debug('Thread started processing cases for Dictionary: ' . $dictName . ' JobID: ' . $jobId);
        // Community layer: declared outside the try so the catch can still mark
        // the job FAILED when initialize() itself is what threw.
        $dictionarySchemaHelper = null;
        try {
            $dictionarySchemaHelper = new DictionarySchemaHelper($dictName, $this->pdo, $this->logger);
            $dictionarySchemaHelper->initialize();
            $processCasesOptions = $dictionarySchemaHelper->getProcessCaseOptions();
            $dictionarySchemaHelper->blobBreakOut($jobId, $processCasesOptions);
        } catch (\Throwable $e) {
            // Community layer: breakout failures used to surface as a bare
            // "thread failed" line with no cause and an exit code of 0, so a
            // failed run looked like a successful one. Catch \Throwable (not
            // just \Exception) so PHP \Error also marks the job FAILED.
            //
            // Operator-facing summary (cause + short message) plus a structured
            // block for the log file (message first, technical trace below).
            $shortMessage = BreakoutErrorFormatter::shortMessage($e);
            $logBlock = BreakoutErrorFormatter::structuredLogBlock($e, [
                'Dictionary' => $dictName,
                'Job'        => (string) $jobId,
            ]);

            // The readable block goes to stdout: the "run now" route and the
            // scheduler both redirect stdout to var/logs/breakout/*.log, which
            // the UI displays.
            $output->writeln($logBlock);
            // Same summary in the application log, with the trace as context.
            $this->logger->error(
                "Breakout FAILED — Dictionary: $dictName JobID: $jobId — $shortMessage",
                ["context" => (string) $e]
            );

            // Persist the failure in the target database (best effort):
            // status = FAILED plus a readable error_message, so the UI can show
            // it without anyone having to SSH into the server.
            if ($dictionarySchemaHelper !== null) {
                $dictionarySchemaHelper->markJobFailed($jobId, $shortMessage);
            }

            return Command::FAILURE;
        }
        $this->logger->debug('Thread completed processing cases for  Dictionary: ' . $dictName . ' JobID: ' . $jobId);
        $output->writeln('Thread completed processing cases for Dictionary: ' . $dictName . ' JobID: ' . $jobId);
        return Command::SUCCESS;
    }

}
