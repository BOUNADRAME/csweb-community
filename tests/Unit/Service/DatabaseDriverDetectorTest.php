<?php

namespace Tests\Unit\Service;

use AppBundle\Service\DatabaseDriverDetector;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DatabaseDriverDetectorTest extends TestCase
{
    private DatabaseDriverDetector $detector;

    protected function setUp(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $this->detector = new DatabaseDriverDetector($logger);
    }

    public function testIsDatabaseTypeSupportedReturnsBool(): void
    {
        // These return bool based on loaded extensions — just verify the type
        $this->assertIsBool($this->detector->isDatabaseTypeSupported('postgresql'));
        $this->assertIsBool($this->detector->isDatabaseTypeSupported('mysql'));
        $this->assertIsBool($this->detector->isDatabaseTypeSupported('sqlserver'));
    }

    public function testIsDatabaseTypeSupportedUnknownReturnsFalse(): void
    {
        $this->assertFalse($this->detector->isDatabaseTypeSupported('oracle'));
    }

    public function testIsDatabaseTypeSupportedCaseInsensitive(): void
    {
        $lower = $this->detector->isDatabaseTypeSupported('postgresql');
        $upper = $this->detector->isDatabaseTypeSupported('PostgreSQL');
        $this->assertSame($lower, $upper);
    }

    public function testGetMissingExtensionsReturnsArray(): void
    {
        $missing = $this->detector->getMissingExtensions('postgresql');
        $this->assertIsArray($missing);
    }

    public function testGetMissingExtensionsUnknownReturnsEmpty(): void
    {
        $missing = $this->detector->getMissingExtensions('oracle');
        $this->assertSame([], $missing);
    }

    public function testGetExtensionStatusReturnsBoolMap(): void
    {
        $status = $this->detector->getExtensionStatus('postgresql');
        $this->assertIsArray($status);
        foreach ($status as $ext => $loaded) {
            $this->assertIsString($ext);
            $this->assertIsBool($loaded);
        }
    }

    public function testGenerateReportStructure(): void
    {
        $report = $this->detector->generateReport();
        $this->assertArrayHasKey('php_version', $report);
        $this->assertArrayHasKey('databases', $report);
        $this->assertArrayHasKey('recommended_extensions', $report);
        $this->assertArrayHasKey('system_info', $report);
    }

    public function testGenerateReportDatabaseFields(): void
    {
        $report = $this->detector->generateReport();
        foreach ($report['databases'] as $dbType => $info) {
            $this->assertArrayHasKey('supported', $info, "Missing 'supported' key for $dbType");
            $this->assertArrayHasKey('extensions', $info, "Missing 'extensions' key for $dbType");
            $this->assertArrayHasKey('missing_extensions', $info, "Missing 'missing_extensions' key for $dbType");
            $this->assertArrayHasKey('status', $info, "Missing 'status' key for $dbType");
        }
    }

    public function testGetFormattedReportContainsExpectedText(): void
    {
        $formatted = $this->detector->getFormattedReport();
        $this->assertStringContainsString('PHP Database Drivers Detection Report', $formatted);
        $this->assertStringContainsString('PHP Version:', $formatted);
        $this->assertStringContainsString('Database Drivers:', $formatted);
        $this->assertStringContainsString('Recommended Extensions:', $formatted);
    }

    public function testGetAvailablePdoDriversReturnsArray(): void
    {
        $drivers = $this->detector->getAvailablePdoDrivers();
        $this->assertIsArray($drivers);
    }

    public function testAreRecommendedExtensionsInstalledReturnsBool(): void
    {
        $this->assertIsBool($this->detector->areRecommendedExtensionsInstalled());
    }

    public function testGetInstallationInstructionsWhenAllInstalled(): void
    {
        // For a DB type where all extensions are installed, commands should be empty
        // We test the structure regardless
        $instructions = $this->detector->getInstallationInstructions('ubuntu', 'postgresql');
        $this->assertArrayHasKey('message', $instructions);
        $this->assertArrayHasKey('commands', $instructions);

        if ($this->detector->isDatabaseTypeSupported('postgresql')) {
            $this->assertEmpty($instructions['commands']);
            $this->assertStringContainsString('already installed', $instructions['message']);
        }
    }

    public function testGetInstallationInstructionsUbuntu(): void
    {
        // For a DB type with missing extensions, verify ubuntu commands
        $instructions = $this->detector->getInstallationInstructions('ubuntu', 'sqlserver');
        $this->assertArrayHasKey('message', $instructions);
        $this->assertArrayHasKey('commands', $instructions);

        if (!$this->detector->isDatabaseTypeSupported('sqlserver')) {
            $this->assertNotEmpty($instructions['commands']);
            $foundApt = false;
            foreach ($instructions['commands'] as $cmd) {
                if (str_contains($cmd, 'apt-get')) {
                    $foundApt = true;
                }
            }
            $this->assertTrue($foundApt, 'Ubuntu instructions should contain apt-get commands');
        }
    }
}
