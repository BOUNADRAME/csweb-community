<?php

namespace App\EventSubscriber;

use App\CSPro\Community\CommunitySchemaInstaller;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Installs the Community schema additions (permissions, backup and scheduler
 * tables) on the first request after the setup wizard, so that hosts without
 * a shell — typically cPanel shared hosting — do not have to run
 * `bin/console csweb:community:install-schema` by hand.
 *
 * Docker keeps running the console command at boot; both paths are idempotent.
 * Once the schema is current a marker file in the cache directory short-circuits
 * every later request (no database query). Clearing the cache, as an update
 * does, re-checks once.
 */
class CommunitySchemaSubscriber implements EventSubscriberInterface {

    public function __construct(
        private CommunitySchemaInstaller $installer,
        private LoggerInterface $logger,
        private string $cacheDir,
    ) {
    }

    public static function getSubscribedEvents(): array {
        // After the rate limiter (256), before routing and security (32 / 8).
        return [KernelEvents::REQUEST => ['onKernelRequest', 128]];
    }

    public function onKernelRequest(RequestEvent $event): void {
        if (!$event->isMainRequest()) {
            return;
        }
        $marker = $this->cacheDir . '/community_schema_v' . CommunitySchemaInstaller::COMMUNITY_SCHEMA_VERSION . '.ok';
        if (is_file($marker)) {
            return;
        }
        try {
            $version = $this->installer->install();
            if ($version >= CommunitySchemaInstaller::COMMUNITY_SCHEMA_VERSION) {
                @file_put_contents($marker, (string) $version, LOCK_EX);
            }
        } catch (\Throwable $e) {
            // Never block the request: data collection does not depend on these tables.
            $this->logger->error('Automatic Community schema install failed: ' . $e->getMessage());
        }
    }
}
