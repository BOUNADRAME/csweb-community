<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use AppBundle\Service\PdoHelper;
use Psr\Log\LoggerInterface;
use AppBundle\CSPro\DictionarySchemaHelper;
use AppBundle\CSPro\Data\BreakoutErrorFormatter;

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
        // Déclaré hors du try pour être accessible dans le catch (marquage FAILED).
        $dictionarySchemaHelper = null;
        try {
            $dictionarySchemaHelper = new DictionarySchemaHelper($dictName, $this->pdo, $this->logger);
            $dictionarySchemaHelper->initialize();
            $processCasesOptions = $dictionarySchemaHelper->getProcessCaseOptions();
            $dictionarySchemaHelper->blobBreakOut($jobId, $processCasesOptions);
        } catch (\Throwable $e) {
            // Message clair pour l'opérateur (base + résumé) et bloc structuré
            // pour le log fichier (message en tête, trace technique en dessous).
            $shortMessage = BreakoutErrorFormatter::shortMessage($e);
            $logBlock = BreakoutErrorFormatter::structuredLogBlock($e, [
                'Dictionary' => $dictName,
                'Job'        => (string) $jobId,
            ]);

            // Le bloc lisible va sur stdout : la route "run now" et le scheduler
            // redirigent stdout vers var/logs/breakout/*.log (affiché dans l'UI).
            $output->writeln($logBlock);
            // Résumé clair aussi dans le log applicatif (plus la trace en contexte).
            $this->logger->error(
                "Breakout FAILED — Dictionary: $dictName JobID: $jobId — $shortMessage",
                ["context" => (string) $e]
            );

            // Persiste l'échec dans la base cible (best-effort) : status = FAILED
            // + error_message lisible, pour que l'UI le montre sans SSH.
            if ($dictionarySchemaHelper !== null) {
                $dictionarySchemaHelper->markJobFailed($jobId, $shortMessage);
            }

            // Code de sortie non-zéro : l'échec cesse d'être un faux succès.
            return 1;
        }
        $this->logger->debug('Thread completed processing cases for  Dictionary: ' . $dictName . ' JobID: ' . $jobId);
        $output->writeln('Thread completed processing cases for Dictionary: ' . $dictName . ' JobID: ' . $jobId);
        return 0;
    }

}
