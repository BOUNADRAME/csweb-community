<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RateLimiterSubscriber implements EventSubscriberInterface {

    private string $cacheDir;

    private const LIMITS = [
        'login'  => ['max' => 5,   'window' => 60],
        'api'    => ['max' => 60,  'window' => 60],
        'ui'     => ['max' => 120, 'window' => 60],
    ];

    // 'login_check' is the dashboard login form (POST /); 'token' is the OAuth endpoint used by CSEntry/Designer.
    private const LOGIN_ROUTES = ['fos_user_security_check', 'login_check', 'token'];

    // Login attempts are counted per IP *and* username, so many devices behind one
    // office Wi-Fi / carrier NAT (each with its own account) can log in together,
    // while guessing the password of a given account stays limited to 'login.max'.
    // A coarser per-IP ceiling still caps credential stuffing across many accounts.
    private const LOGIN_PER_IP_MAX = 60;

    public function __construct(string $cacheDir) {
        $this->cacheDir = $cacheDir . '/rate_limit';
    }

    public static function getSubscribedEvents(): array {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 256],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route', '');
        $ip = $request->getClientIp() ?? '127.0.0.1';

        $category = $this->resolveCategory($route, $request->getPathInfo());
        // This subscriber runs before routing (priority 256), so '_route' is still empty here:
        // the dashboard login form (POST /) has to be recognised by method + path.
        if ($request->isMethod('POST') && $request->getPathInfo() === '/') {
            $category = 'login';
            $route = 'login_check';
        }
        $limit = self::LIMITS[$category];

        $now = time();
        $buckets = [];
        if ($category === 'login') {
            $user = $this->extractUsername($request);
            $buckets[] = [$category . '_' . md5($ip . '|' . $route . '|' . $user), $limit['max']];
            $buckets[] = [$category . '_ip_' . md5($ip . '|' . $route), self::LOGIN_PER_IP_MAX];
        } else {
            // Authenticated API calls are counted per session (bearer token) rather than per IP,
            // so devices sharing a public IP do not consume each other's quota.
            $auth = (string) $request->headers->get('Authorization', '');
            $who = ($category === 'api' && str_starts_with($auth, 'Bearer ')) ? '|t:' . substr($auth, 7) : '';
            $buckets[] = [$category . '_' . md5($ip . '|' . $route . $who), $limit['max']];
        }

        foreach ($buckets as [$key, $max]) {
            $hits = $this->getHits($key, $now, $limit['window']);
            $hits[] = $now;
            $this->saveHits($key, $hits, $now, $limit['window']);
            if (count($hits) <= $max) {
                continue;
            }
            $retryAfter = $limit['window'] - ($now - $hits[0]);
            $retryAfter = max(1, $retryAfter);

            $response = new JsonResponse(
                ['code' => 429, 'description' => 'Too many requests. Please wait before retrying.'],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => (string) $retryAfter]
            );
            $event->setResponse($response);
            return;
        }
    }

    private function extractUsername(\Symfony\Component\HttpFoundation\Request $request): string {
        $user = $request->request->get('_username') ?? $request->request->get('username');
        if ($user === null) {
            $body = json_decode((string) $request->getContent(), true);
            $user = is_array($body) ? ($body['username'] ?? '') : '';
        }
        return mb_strtolower(trim((string) $user));
    }

    private function resolveCategory(string $route, string $path): string {
        if (in_array($route, self::LOGIN_ROUTES, true) || str_starts_with($path, '/token')) {
            return 'login';
        }
        if (str_starts_with($path, '/api/') || str_starts_with($path, '/dictionaries') || str_starts_with($path, '/users')) {
            return 'api';
        }
        return 'ui';
    }

    private function getHits(string $key, int $now, int $window): array {
        $file = $this->getFilePath($key);
        if (!file_exists($file)) {
            return [];
        }

        $data = @file_get_contents($file);
        if ($data === false) {
            return [];
        }

        $hits = @json_decode($data, true);
        if (!is_array($hits)) {
            return [];
        }

        $cutoff = $now - $window;
        return array_values(array_filter($hits, static fn(int $ts) => $ts > $cutoff));
    }

    private function saveHits(string $key, array $hits, int $now, int $window): void {
        $dir = $this->cacheDir;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $cutoff = $now - $window;
        $hits = array_values(array_filter($hits, static fn(int $ts) => $ts > $cutoff));

        $file = $this->getFilePath($key);
        @file_put_contents($file, json_encode($hits), LOCK_EX);
    }

    private function getFilePath(string $key): string {
        return $this->cacheDir . '/' . $key . '.json';
    }
}
