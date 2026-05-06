<?php

namespace AppBundle\Controller\ui;

use AppBundle\Service\BreakoutStatusService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController implements TokenAuthenticatedController {

    public function __construct(
        private BreakoutStatusService $breakoutStatus,
        private LoggerInterface $logger
    ) {
    }

    #[Route('/breakout/dashboard', name: 'breakoutDashboard', methods: ['GET'])]
    public function viewDashboard(): Response {
        $this->denyAccessUnlessGranted('ROLE_DASHBOARD_ALL');
        return $this->render('dashboard.twig', []);
    }

    #[Route('/breakout/dashboard/summary', name: 'breakoutDashboardSummary', methods: ['GET'])]
    public function summary(Request $request): Response {
        $this->denyAccessUnlessGranted('ROLE_DASHBOARD_ALL');
        $bypass = $this->wantsFreshData($request);
        try {
            $payload = [
                'summary'      => $this->breakoutStatus->getGlobalSummary($bypass),
                'topProblems'  => $this->breakoutStatus->getTopProblems(5, $bypass),
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
        $this->denyAccessUnlessGranted('ROLE_DASHBOARD_ALL');
        try {
            $filters = $this->parseFilters($request);
            $page  = max(1, (int) $request->query->get('page', 1));
            $limit = min(100, max(1, (int) $request->query->get('limit', 25)));

            $bypass = $this->wantsFreshData($request);
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
