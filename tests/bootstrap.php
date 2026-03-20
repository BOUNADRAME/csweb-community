<?php

/**
 * PHPUnit bootstrap file.
 *
 * Registers a custom autoloader BEFORE the Composer autoloader to intercept
 * AppBundle\Service\PdoHelper. On PHP 8.4+, the real PdoHelper (which extends
 * Aura\Sql\ExtendedPdo) cannot be loaded due to a PDO::connect() static method
 * conflict (present in both aura/sql 4.x and 5.x). This stub provides a
 * mockable replacement for unit tests. It only activates if the class is not
 * already loaded, so it is safe to keep for forward compatibility.
 */

// Register stub autoloader before Composer
spl_autoload_register(function (string $class): void {
    if ($class === 'AppBundle\\Service\\PdoHelper') {
        // Define a stub PdoHelper that doesn't extend ExtendedPdo
        // This allows unit tests to mock it without triggering the aura/sql fatal error
        eval('
            namespace AppBundle\Service;
            class PdoHelper {
                public function __construct(...$args) {}
                public function fetchAll(string $stm, array $bind = []): array { return []; }
                public function fetchOne(string $stm, array $bind = []) { return null; }
                public function fetchAffected(string $stm, array $bind = []): int { return 0; }
                public function prepare(string $stm): \PDOStatement { throw new \RuntimeException("stub"); }
                public function getDsn(): string { return ""; }
            }
        ');
    }
}, true, true); // prepend = true

require __DIR__ . '/../vendor/autoload.php';
