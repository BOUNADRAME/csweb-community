<?php

namespace App\Controller\ui;

use App\Security\DashboardVoter;
use App\Service\BreakoutStatusService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController implements TokenAuthenticatedController {

    public function __construct(
        private BreakoutStatusService $breakoutStatus,
        private LoggerInterface $logger,
        private KernelInterface $kernel
    ) {
    }

    #[Route('/breakout/dashboard', name: 'breakoutDashboard', methods: ['GET'])]
    public function viewDashboard(): Response {
        $this->denyAccessUnlessGranted(DashboardVoter::DASHBOARD_READ);
        return $this->render('dashboard.twig', []);
    }

    #[Route('/breakout/dashboard/summary', name: 'breakoutDashboardSummary', methods: ['GET'])]
    public function summary(Request $request): Response {
        $this->denyAccessUnlessGranted(DashboardVoter::DASHBOARD_READ);
        $force  = $this->wantsFreshData($request);
        $bypass = $force || $this->wantsReload($request);
        try {
            // Case counts are pre-computed in the background. Refresh (nocache)
            // or old counters start a recount without making the page wait;
            // where background processes are not allowed, Refresh counts here.
            $metrics = $this->breakoutStatus->metricsInfo();
            $refreshing = false;
            if ($force || $metrics['stale']) {
                $refreshing = $this->startBackgroundRefresh($force);
                if ($force && !$refreshing) {
                    $this->breakoutStatus->refreshMetrics();
                    $metrics = $this->breakoutStatus->metricsInfo();
                }
            }
            $payload = [
                'summary'      => $this->breakoutStatus->getGlobalSummary($bypass),
                'topProblems'  => $this->breakoutStatus->getTopProblems(5, $bypass),
                'metrics'      => $metrics + ['refreshing' => $refreshing],
                'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
                'cache'        => $bypass ? 'bypass' : 'hit-or-fresh',
            ];
            return $this->jsonResponse($payload);
        } catch (\Throwable $e) {
            $this->logger->error('Dashboard summary failed', ['context' => (string) $e]);
            return $this->jsonResponse(
                ['description' => 'Failed to load dashboard summary.', 'code' => 500],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/breakout/dashboard/dictionaries', name: 'breakoutDashboardDictionaries', methods: ['GET'])]
    public function dictionaries(Request $request): Response {
        $this->denyAccessUnlessGranted(DashboardVoter::DASHBOARD_READ);
        try {
            $filters = $this->parseFilters($request);
            $page  = max(1, (int) $request->query->get('page', 1));
            $limit = min(100, max(1, (int) $request->query->get('limit', 25)));

            $bypass = $this->wantsFreshData($request) || $this->wantsReload($request);
            $payload = $this->breakoutStatus->getStatusList($filters, $page, $limit, $bypass);
            $payload['generated_at'] = date('Y-m-d H:i:s');
            $payload['cache']        = $bypass ? 'bypass' : 'hit-or-fresh';
            return $this->jsonResponse($payload);
        } catch (\Throwable $e) {
            $this->logger->error('Dashboard list failed', ['context' => (string) $e]);
            return $this->jsonResponse(
                ['description' => 'Failed to load dictionaries.', 'code' => 500],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * @return array{dictionary?:string, dictionaries?:string[], cron_enabled?:bool, breakout_configured?:bool, status?:string}
     */
    private function parseFilters(Request $request): array {
        $filters = [];

        $dict = trim((string) $request->query->get('dictionary', ''));
        if ($dict !== '') {
            $filters['dictionary'] = $dict;
        }

        $dicts = trim((string) $request->query->get('dictionaries', ''));
        if ($dicts !== '') {
            $filters['dictionaries'] = array_values(array_filter(array_map('trim', explode(',', $dicts))));
        }

        $cron = $request->query->get('cron_enabled');
        if ($cron === 'true' || $cron === '1') {
            $filters['cron_enabled'] = true;
        } elseif ($cron === 'false' || $cron === '0') {
            $filters['cron_enabled'] = false;
        }

        // Community layer: search and status are applied server side so they
        // cover every page and the KPI cards can follow the filter.
        $search = trim((string) $request->query->get('search', ''));
        if ($search !== '') {
            $filters['search'] = mb_substr($search, 0, 100);
        }
        $status = (string) $request->query->get('status', 'all');
        if (in_array($status, ['up_to_date', 'pending', 'error', 'running', 'not_configured', 'never_run', 'unreachable', 'overdue'], true)) {
            $filters['status'] = $status;
        }

        $hideEmpty = $request->query->get('hide_empty');
        if ($hideEmpty === '1' || $hideEmpty === 'true') {
            $filters['hide_empty'] = true;
        }

        $configured = $request->query->get('breakout_configured');
        if ($configured === 'true' || $configured === '1') {
            $filters['breakout_configured'] = true;
        } elseif ($configured === 'false' || $configured === '0') {
            $filters['breakout_configured'] = false;
        }

        return $filters;
    }

    private function wantsFreshData(Request $request): bool {
        $v = $request->query->get('nocache');
        return $v === '1' || $v === 'true';
    }

    /** Skip the short response cache without starting a recount. */
    private function wantsReload(Request $request): bool {
        return $request->query->get('reload') === '1';
    }

    /**
     * Start `csweb:breakout-metrics` in the background. Returns false when
     * the server does not allow it (exec disabled), true when a recount is
     * running or was started less than a minute ago.
     */
    private function startBackgroundRefresh(bool $force): bool {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (!function_exists('exec') || in_array('exec', $disabled, true)) {
            return false;
        }
        if (!$this->breakoutStatus->claimRefreshSlot(60) && !$force) {
            return true;
        }
        $php = (new PhpExecutableFinder())->find();
        $console = realpath($this->kernel->getProjectDir() . '/bin/console');
        if (!$php || !$console) {
            return false;
        }
        try {
            exec(sprintf(
                '%s %s csweb:breakout-metrics --env=%s > /dev/null 2>&1 &',
                escapeshellarg($php),
                escapeshellarg($console),
                escapeshellarg($this->kernel->getEnvironment())
            ));
        } catch (\Throwable $e) {
            $this->logger->warning('Could not start the breakout counters refresh: ' . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function jsonResponse(array $payload, int $status = Response::HTTP_OK): Response {
        $response = new Response(json_encode($payload, JSON_THROW_ON_ERROR), $status);
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Length', strlen($response->getContent()));
        return $response;
    }
}
