<?php

namespace AppBundle\Command;

use AppBundle\Service\BreakoutStatusService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Recount cases for Breakout Health in the background, so the dashboard only
 * reads stored counters. Runs every 2 minutes from cron (Docker image) and is
 * also started by the dashboard when the counters are getting old.
 */
class BreakoutMetricsCommand extends Command {

    use LockableTrait;

    protected static $defaultName = 'csweb:breakout-metrics';

    public function __construct(private BreakoutStatusService $status) {
        parent::__construct();
    }

    protected function configure(): void {
        $this->setDescription('Recount cases of every dictionary for Breakout Health (run from cron)');
        $this->addOption('dictionary', 'd', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only these dictionaries');
        $this->addOption('if-older-than', null, InputOption::VALUE_REQUIRED, 'Skip when the counters are younger than this many seconds');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        if (!$this->lock('csweb-breakout-metrics')) {
            $output->writeln('A refresh is already running.', OutputInterface::VERBOSITY_VERBOSE);
            return Command::SUCCESS;
        }
        try {
            $minAge = $input->getOption('if-older-than');
            if ($minAge !== null) {
                $info = $this->status->metricsInfo();
                if ($info['age_seconds'] !== null && $info['age_seconds'] < (int) $minAge) {
                    $output->writeln('Counters are recent, nothing to do.', OutputInterface::VERBOSITY_VERBOSE);
                    return Command::SUCCESS;
                }
            }
            $only = $input->getOption('dictionary') ?: null;
            $r = $this->status->refreshMetrics($only);
            $output->writeln(sprintf(
                '%s Counted %d dictionaries in %.1f s%s.',
                date('Y-m-d H:i:s'),
                $r['dictionaries'],
                $r['ms'] / 1000,
                $r['unreachable'] ? sprintf(' (%d with an unreachable target)', $r['unreachable']) : ''
            ), OutputInterface::VERBOSITY_VERBOSE);
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('<error>Breakout metrics refresh failed: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        } finally {
            $this->release();
        }
    }
}
