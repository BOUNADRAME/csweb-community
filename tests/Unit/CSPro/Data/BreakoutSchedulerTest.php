<?php

namespace Tests\Unit\CSPro\Data;

use AppBundle\CSPro\Data\BreakoutScheduler;
use AppBundle\Service\PdoHelper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BreakoutSchedulerTest extends TestCase
{
    private BreakoutScheduler $scheduler;
    private PdoHelper $pdo;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->pdo = $this->getMockBuilder(PdoHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->scheduler = new BreakoutScheduler($this->pdo, $this->logger);
    }

    public function testAddScheduleComputesNextRun(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())->method('execute')
            ->with($this->callback(function (array $bind) {
                $this->assertSame(1, $bind['dictionaryId']);
                $this->assertSame(1, $bind['enabled']);
                $this->assertSame('*/5 * * * *', $bind['cronExpression']);
                $this->assertNotNull($bind['nextRun']);
                $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $bind['nextRun']);
                return true;
            }));

        $this->pdo->expects($this->once())->method('prepare')->willReturn($stmt);

        $result = $this->scheduler->addSchedule(1, '*/5 * * * *', true);
        $this->assertTrue($result);
    }

    public function testAddScheduleDisabledSetsNextRunNull(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())->method('execute')
            ->with($this->callback(function (array $bind) {
                $this->assertSame(0, $bind['enabled']);
                $this->assertNull($bind['nextRun']);
                return true;
            }));

        $this->pdo->expects($this->once())->method('prepare')->willReturn($stmt);

        $result = $this->scheduler->addSchedule(1, '0 2 * * *', false);
        $this->assertTrue($result);
    }

    public function testAddScheduleInvalidCronThrows(): void
    {
        $this->expectException(\Exception::class);
        $this->scheduler->addSchedule(1, 'invalid cron', true);
    }

    public function testToggleFromEnabledToDisabled(): void
    {
        $this->pdo->expects($this->once())->method('fetchOne')
            ->willReturn(['enabled' => 1, 'cron_expression' => '*/5 * * * *']);

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())->method('execute')
            ->with($this->callback(function (array $bind) {
                $this->assertSame(0, $bind['enabled']);
                $this->assertNull($bind['nextRun']);
                return true;
            }));

        $this->pdo->expects($this->once())->method('prepare')->willReturn($stmt);

        $result = $this->scheduler->toggleSchedule(99);
        $this->assertTrue($result);
    }

    public function testToggleFromDisabledToEnabled(): void
    {
        $this->pdo->expects($this->once())->method('fetchOne')
            ->willReturn(['enabled' => 0, 'cron_expression' => '0 3 * * *']);

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())->method('execute')
            ->with($this->callback(function (array $bind) {
                $this->assertSame(1, $bind['enabled']);
                $this->assertNotNull($bind['nextRun']);
                $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $bind['nextRun']);
                return true;
            }));

        $this->pdo->expects($this->once())->method('prepare')->willReturn($stmt);

        $result = $this->scheduler->toggleSchedule(42);
        $this->assertTrue($result);
    }

    public function testToggleNotFoundThrows(): void
    {
        $this->pdo->expects($this->once())->method('fetchOne')
            ->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Schedule not found');
        $this->scheduler->toggleSchedule(999);
    }

    public function testDeleteReturnsTrue(): void
    {
        $this->pdo->expects($this->once())->method('fetchAffected')
            ->willReturn(1);

        $this->assertTrue($this->scheduler->deleteSchedule(1));
    }

    public function testDeleteReturnsFalse(): void
    {
        $this->pdo->expects($this->once())->method('fetchAffected')
            ->willReturn(0);

        $this->assertFalse($this->scheduler->deleteSchedule(999));
    }
}
