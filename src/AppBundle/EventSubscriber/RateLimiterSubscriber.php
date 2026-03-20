<?php

namespace AppBundle\EventSubscriber;

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

    private const LOGIN_ROUTES = ['fos_user_security_check', 'token'];

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
        $limit = self::LIMITS[$category];

        $key = $category . '_' . md5($ip . '|' . $route);
        $now = time();

        $hits = $this->getHits($key, $now, $limit['window']);
        $hits[] = $now;
        $this->saveHits($key, $hits, $now, $limit['window']);

        if (count($hits) > $limit['max']) {
            $retryAfter = $limit['window'] - ($now - $hits[0]);
            $retryAfter = max(1, $retryAfter);

            $response = new JsonResponse(
                ['code' => 429, 'description' => 'Too many requests. Please wait before retrying.'],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => (string) $retryAfter]
            );
            $event->setResponse($response);
        }
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
