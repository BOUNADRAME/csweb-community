<?php

namespace Tests\Unit\CSPro;

use AppBundle\CSPro\CSProMySQLFormatter;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class CSProMySQLFormatterTest extends TestCase
{
    private function makeRecord(
        string $message = 'test',
        int $level = Logger::WARNING,
        string $channel = 'app',
        array $context = [],
        array $extra = []
    ): array {
        return [
            'message' => $message,
            'level' => $level,
            'level_name' => Logger::getLevelName($level),
            'channel' => $channel,
            'context' => $context,
            'extra' => $extra,
            'datetime' => new \DateTimeImmutable('2024-01-15 10:30:00'),
        ];
    }

    public function testDefaultFormat(): void
    {
        $formatter = new CSProMySQLFormatter();
        $this->assertSame("%context% %extra%\n", CSProMySQLFormatter::SIMPLE_FORMAT);
    }

    public function testFormatBasicRecord(): void
    {
        $formatter = new CSProMySQLFormatter();
        $record = $this->makeRecord('hello world');

        $output = $formatter->format($record);
        $this->assertIsString($output);
    }

    public function testFormatWithContext(): void
    {
        $formatter = new CSProMySQLFormatter();
        $record = $this->makeRecord('msg', Logger::INFO, 'app', ['key' => 'value']);

        $output = $formatter->format($record);
        $this->assertStringContainsString('value', $output);
    }

    public function testFormatWithExtra(): void
    {
        $formatter = new CSProMySQLFormatter();
        $record = $this->makeRecord('msg', Logger::INFO, 'app', [], ['ip' => '127.0.0.1']);

        $output = $formatter->format($record);
        $this->assertStringContainsString('127.0.0.1', $output);
    }

    public function testIgnoreEmptyContextAndExtra(): void
    {
        $formatter = new CSProMySQLFormatter(null, null, true, true);
        $record = $this->makeRecord('msg');

        $output = $formatter->format($record);
        // With ignoreEmptyContextAndExtra=true, empty context/extra are stripped
        $this->assertStringNotContainsString('%context%', $output);
        $this->assertStringNotContainsString('%extra%', $output);
    }

    public function testFormatBatch(): void
    {
        $formatter = new CSProMySQLFormatter();
        $records = [
            $this->makeRecord('first'),
            $this->makeRecord('second'),
        ];

        $output = $formatter->formatBatch($records);
        $this->assertIsString($output);
        // Batch output is concatenation of individual formats
        $this->assertSame(
            $formatter->format($records[0]) . $formatter->format($records[1]),
            $output
        );
    }

    public function testStringifyNull(): void
    {
        $formatter = new CSProMySQLFormatter();
        $this->assertSame('NULL', $formatter->stringify(null));
    }

    public function testStringifyBool(): void
    {
        $formatter = new CSProMySQLFormatter();
        $this->assertSame('true', $formatter->stringify(true));
        $this->assertSame('false', $formatter->stringify(false));
    }

    public function testReplaceNewlinesWhenDisabled(): void
    {
        $formatter = new CSProMySQLFormatter(null, null, false);
        $output = $formatter->stringify("line1\nline2");
        $this->assertStringNotContainsString("\n", $output);
        $this->assertStringContainsString('line1 line2', $output);
    }

    public function testAllowInlineLineBreaks(): void
    {
        $formatter = new CSProMySQLFormatter(null, null, true);
        $output = $formatter->stringify("line1\nline2");
        $this->assertStringContainsString("\n", $output);
    }
}
