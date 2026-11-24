<?php

namespace App\Controller\ui;

use App\Security\LogsVoter;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;
use App\Service\PdoHelper;

class ApplicationLogsController extends AbstractController implements TokenAuthenticatedController {

    public function __construct(private PdoHelper $pdo, private LoggerInterface $logger) {
    }

    public function setContainer(?ContainerInterface $container): ?ContainerInterface {
        return parent::setContainer($container);
    }

    #[Route('/application-logs', name: 'applicationLogs', methods: ['GET'])]
    public function viewAction(Request $request): Response {
        $this->denyAccessUnlessGranted(LogsVoter::LOGS_READ);
        return $this->render('applicationLogs.twig');
    }

    #[Route('/application-logs/data', name: 'applicationLogsData', methods: ['GET'])]
    public function dataAction(Request $request): Response {
        $this->denyAccessUnlessGranted(LogsVoter::LOGS_READ);
        try {
            $draw = (int) $request->query->get('draw', 1);
            $start = (int) $request->query->get('start', 0);
            $length = (int) $request->query->get('length', 25);
            // DataTables sends `search` and `order` as arrays. Symfony 6.4's
            // InputBag::get() throws on a non-scalar value — on 5.4 it merely
            // returned the array — so these must go through all(), which is the
            // supported accessor for array parameters. Reading them with get()
            // aborts the request and leaves the table spinning on "Loading..."
            // behind a "search contains a non-scalar value" warning.
            $searchParam = $request->query->all('search');
            $searchValue = trim($searchParam['value'] ?? '');
            $channel = trim($request->query->get('channel', ''));
            $level = trim($request->query->get('level', ''));
            $dateFrom = trim($request->query->get('dateFrom', ''));
            $dateTo = trim($request->query->get('dateTo', ''));

            // Order
            $orderParam = $request->query->all('order');
            $orderCol = (int) ($orderParam[0]['column'] ?? 0);
            $orderDir = strtolower($orderParam[0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
            $columns = ['id', 'channel', 'level_name', 'message', 'created_time'];
            $orderColumn = $columns[$orderCol] ?? 'id';

            // Total count
            $totalCount = (int) $this->pdo->fetchValue("SELECT COUNT(*) FROM cspro_log");

            // Build WHERE
            $where = [];
            $params = [];

            if ($channel !== '') {
                $where[] = "channel = :channel";
                $params['channel'] = $channel;
            }
            if ($level !== '') {
                $where[] = "level_name = :level";
                $params['level'] = $level;
            }
            if ($dateFrom !== '') {
                $where[] = "created_time >= :dateFrom";
                $params['dateFrom'] = $dateFrom . ' 00:00:00';
            }
            if ($dateTo !== '') {
                $where[] = "created_time <= :dateTo";
                $params['dateTo'] = $dateTo . ' 23:59:59';
            }
            if ($searchValue !== '') {
                $where[] = "(message LIKE :search OR context LIKE :search)";
                $params['search'] = '%' . $searchValue . '%';
            }

            $whereClause = '';
            if (!empty($where)) {
                $whereClause = 'WHERE ' . implode(' AND ', $where);
            }

            // Filtered count
            $filteredCount = (int) $this->pdo->fetchValue(
                "SELECT COUNT(*) FROM cspro_log $whereClause",
                $params
            );

            // Fetch page
            $sql = "SELECT id, channel, level_name, SUBSTRING(message, 1, 200) AS message, created_time
                    FROM cspro_log $whereClause
                    ORDER BY $orderColumn $orderDir
                    LIMIT :limit OFFSET :offset";
            $params['limit'] = $length;
            $params['offset'] = $start;

            $rows = $this->pdo->fetchAll($sql, $params);

            $result = [
                'draw' => $draw,
                'recordsTotal' => $totalCount,
                'recordsFiltered' => $filteredCount,
                'data' => $rows,
            ];

            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
        } catch (\Exception $e) {
            $result = ['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => $e->getMessage()];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
        }
    }

    #[Route('/application-logs/stats', name: 'applicationLogsStats', methods: ['GET'])]
    public function statsAction(Request $request): Response {
        $this->denyAccessUnlessGranted(LogsVoter::LOGS_READ);
        try {
            $rows = $this->pdo->fetchAll(
                "SELECT level_name, COUNT(*) as cnt FROM cspro_log GROUP BY level_name ORDER BY cnt DESC"
            );
            $total = (int) $this->pdo->fetchValue("SELECT COUNT(*) FROM cspro_log");
            $stats = [];
            foreach ($rows as $row) {
                $stats[$row['level_name']] = (int) $row['cnt'];
            }
            $result = ['stats' => $stats, 'total' => $total];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
        } catch (\Exception $e) {
            $result = ['description' => 'Failed to load stats. ' . $e->getMessage(), 'code' => 500];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/application-logs/detail/{id}', name: 'applicationLogsDetail', methods: ['GET'])]
    public function detailAction(Request $request, int $id): Response {
        $this->denyAccessUnlessGranted(LogsVoter::LOGS_READ);
        try {
            $row = $this->pdo->fetchOne(
                "SELECT id, channel, level, level_name, message, context, created_time FROM cspro_log WHERE id = :id",
                ['id' => $id]
            );
            if (!$row) {
                $result = ['description' => 'Log entry not found.', 'code' => 404];
                return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_NOT_FOUND);
            }
            return new Response(json_encode($row, JSON_THROW_ON_ERROR), Response::HTTP_OK);
        } catch (\Exception $e) {
            $result = ['description' => 'Failed to load log detail. ' . $e->getMessage(), 'code' => 500];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/application-logs/truncate', name: 'applicationLogsTruncate', methods: ['DELETE'])]
    public function truncateAction(Request $request): Response {
        $this->denyAccessUnlessGranted(LogsVoter::LOGS_WRITE);
        try {
            $this->pdo->perform("TRUNCATE TABLE cspro_log");
            $result = ['description' => 'All log entries have been deleted.', 'code' => 200];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
        } catch (\Exception $e) {
            $result = ['description' => 'Failed to truncate logs. ' . $e->getMessage(), 'code' => 500];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/application-logs/delete-filtered', name: 'applicationLogsDeleteFiltered', methods: ['DELETE'])]
    public function deleteFilteredAction(Request $request): Response {
        $this->denyAccessUnlessGranted(LogsVoter::LOGS_WRITE);
        try {
            $body = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $channel = trim($body['channel'] ?? '');
            $level = trim($body['level'] ?? '');
            $dateTo = trim($body['dateTo'] ?? '');

            $where = [];
            $params = [];

            if ($channel !== '') {
                $where[] = "channel = :channel";
                $params['channel'] = $channel;
            }
            if ($level !== '') {
                $where[] = "level_name = :level";
                $params['level'] = $level;
            }
            if ($dateTo !== '') {
                $where[] = "created_time <= :dateTo";
                $params['dateTo'] = $dateTo . ' 23:59:59';
            }

            if (empty($where)) {
                $result = ['description' => 'No filter specified. Use Purge All to delete everything.', 'code' => 400];
                return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_BAD_REQUEST);
            }

            $whereClause = 'WHERE ' . implode(' AND ', $where);
            $count = (int) $this->pdo->fetchValue("SELECT COUNT(*) FROM cspro_log $whereClause", $params);
            $this->pdo->perform("DELETE FROM cspro_log $whereClause", $params);

            $result = ['description' => "$count log entries deleted.", 'code' => 200, 'deleted' => $count];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_OK);
        } catch (\Exception $e) {
            $result = ['description' => 'Failed to delete filtered logs. ' . $e->getMessage(), 'code' => 500];
            return new Response(json_encode($result, JSON_THROW_ON_ERROR), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
