<?php

namespace App\CSPro\Community;

use App\Service\PdoHelper;
use Psr\Log\LoggerInterface;

/**
 * Installs the Community layer's own schema additions on top of a stock
 * CSWeb 8.1 database.
 *
 * The upstream installer (setup/configure.php) and the upstream upgrade
 * script (upgrade/upgrade.php) are left untouched: they create the vanilla
 * schema and own `cspro_config.schema_version`. This installer runs after
 * them and only adds what the Community layer needs, tracked separately
 * under `cspro_config.community_schema_version` so the two never fight
 * over the same counter.
 *
 * Every step is idempotent: running it twice is a no-op, which is what
 * lets docker-entrypoint.sh call it on every boot.
 */
class CommunitySchemaInstaller {

    /**
     * Bumped when a new step is added below. Tracked in `cspro_config`
     * under `community_schema_version`, independently of the upstream
     * `schema_version`.
     */
    public const COMMUNITY_SCHEMA_VERSION = 1;

    public const CONFIG_KEY = 'community_schema_version';

    public function __construct(private PdoHelper $pdo, private LoggerInterface $logger) {
    }

    /**
     * Brings the Community schema up to COMMUNITY_SCHEMA_VERSION.
     *
     * @return int the version installed (unchanged if already current)
     */
    public function install(): int {
        $current = $this->getInstalledVersion();

        if ($current >= self::COMMUNITY_SCHEMA_VERSION) {
            $this->logger->debug('Community schema already at version {version}', ['version' => $current]);
            return $current;
        }

        if ($current < 1) {
            $this->installPermissions();
        }

        $this->setInstalledVersion(self::COMMUNITY_SCHEMA_VERSION);
        $this->logger->info('Community schema upgraded from {from} to {to}', [
            'from' => $current,
            'to' => self::COMMUNITY_SCHEMA_VERSION,
        ]);

        return self::COMMUNITY_SCHEMA_VERSION;
    }

    public function getInstalledVersion(): int {
        try {
            $value = $this->pdo->fetchValue(
                'SELECT `value` FROM `cspro_config` WHERE `name` = :name',
                ['name' => self::CONFIG_KEY]
            );
            return empty($value) ? 0 : (int) $value;
        } catch (\Exception $e) {
            // cspro_config not there yet: upstream setup has not run.
            $this->logger->debug('Community schema version unavailable', ['context' => (string) $e]);
            return 0;
        }
    }

    /**
     * Registers the Community permissions and grants them to the built-in
     * roles. Uses INSERT IGNORE throughout so an operator who already
     * tuned these grants by hand keeps their choices.
     */
    private function installPermissions(): void {
        $values = [];
        foreach (CommunityPermissions::PERMISSIONS as $id => $name) {
            $values[] = sprintf('(%d, %s)', $id, $this->pdo->quote($name));
        }

        $this->pdo->exec(
            'INSERT IGNORE INTO `cspro_permissions` (`id`, `name`) VALUES ' . implode(', ', $values)
        );

        $grants = [];
        foreach (CommunityPermissions::DEFAULT_ROLE_GRANTS as $roleId => $permissionIds) {
            foreach ($permissionIds as $permissionId) {
                $grants[] = sprintf('(%d, %d)', $roleId, $permissionId);
            }
        }

        if ($grants !== []) {
            $this->pdo->exec(
                'INSERT IGNORE INTO `cspro_role_permissions` (`role_id`, `permission_id`) VALUES '
                . implode(', ', $grants)
            );
        }

        $this->logger->info('Installed {count} Community permissions', [
            'count' => count(CommunityPermissions::PERMISSIONS),
        ]);
    }

    private function setInstalledVersion(int $version): void {
        $this->pdo->exec(sprintf(
            'INSERT INTO `cspro_config` (`name`, `value`) VALUES (%s, %s) '
            . 'ON DUPLICATE KEY UPDATE `value` = %s',
            $this->pdo->quote(self::CONFIG_KEY),
            $this->pdo->quote((string) $version),
            $this->pdo->quote((string) $version)
        ));
    }
}
