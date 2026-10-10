<?php

declare(strict_types=1);

/**
 * This file is part of the Webware Traccio package.
 *
 * Copyright (c) 2026 Joey Smith <jsmith@webinertia.net>
 * and contributors.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WebwareTest\Traccio;

use PhpDb\Adapter\ParameterContainer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webware\Traccio\Debug\ProfilerDataFormatter;

use function array_column;

#[CoversClass(ProfilerDataFormatter::class)]
final class ProfilerDataFormatterTest extends TestCase
{
    private ProfilerDataFormatter $formatter;

    public function testFormatEmptyProfiles(): void
    {
        $result = $this->formatter->format([]);

        $this->assertSame(0, $result['summary']['total_queries']);
        $this->assertSame(0, $result['summary']['unique_statements']);
        $this->assertSame(0.0, $result['summary']['total_elapsed']);
        $this->assertSame(0.0, $result['summary']['slowest_elapsed']);
        $this->assertSame([], $result['groups']);
    }

    public function testFormatExecutionIndexMatchesOriginalProfileIndex(): void
    {
        $profiles = [
            ['sql' => 'SELECT 1', 'parameters' => null, 'start' => 1000.0, 'end' => 1000.001, 'elapse' => 0.001],
            ['sql' => 'SELECT 2', 'parameters' => null, 'start' => 1001.0, 'end' => 1001.001, 'elapse' => 0.001],
            ['sql' => 'SELECT 1', 'parameters' => null, 'start' => 1002.0, 'end' => 1002.001, 'elapse' => 0.001],
        ];

        $result = $this->formatter->format($profiles);

        $group1 = null;
        foreach ($result['groups'] as $g) {
            if ('SELECT 1' !== $g['sql']) {
                continue;
            }

            $group1 = $g;

            break;
        }

        $this->assertNotNull($group1);
        $this->assertSame(0, $group1['executions'][0]['index']);
        $this->assertSame(2, $group1['executions'][1]['index']);
    }

    public function testFormatGroupsRepeatedStatements(): void
    {
        $profiles = [
            [
                'sql'        => 'SELECT * FROM users',
                'parameters' => null,
                'start'      => 1000.0,
                'end'        => 1000.002,
                'elapse'     => 0.002,
            ],
            [
                'sql'        => 'SELECT * FROM users',
                'parameters' => null,
                'start'      => 1001.0,
                'end'        => 1001.003,
                'elapse'     => 0.003,
            ],
            [
                'sql'        => 'SELECT * FROM users',
                'parameters' => null,
                'start'      => 1002.0,
                'end'        => 1002.001,
                'elapse'     => 0.001,
            ],
        ];

        $result = $this->formatter->format($profiles);

        $this->assertSame(3, $result['summary']['total_queries']);
        $this->assertSame(1, $result['summary']['unique_statements']);
        $this->assertCount(1, $result['groups']);

        $group = $result['groups'][0];
        $this->assertSame(3, $group['count']);
        $this->assertEqualsWithDelta(0.006, $group['total_elapsed'], 0.000_001);
        $this->assertEqualsWithDelta(0.002, $group['avg_elapsed'], 0.000_001);
        $this->assertEqualsWithDelta(0.003, $group['slowest'], 0.000_001);
        $this->assertCount(3, $group['executions']);
    }

    public function testFormatMultipleDistinctStatements(): void
    {
        $profiles = [
            ['sql' => 'SELECT * FROM a', 'parameters' => null, 'start' => 1000.0, 'end' => 1000.005, 'elapse' => 0.005],
            ['sql' => 'SELECT * FROM b', 'parameters' => null, 'start' => 1001.0, 'end' => 1001.002, 'elapse' => 0.002],
        ];

        $result = $this->formatter->format($profiles);

        $this->assertSame(2, $result['summary']['total_queries']);
        $this->assertSame(2, $result['summary']['unique_statements']);
        $this->assertEqualsWithDelta(0.007, $result['summary']['total_elapsed'], 0.000_001);
        $this->assertEqualsWithDelta(0.005, $result['summary']['slowest_elapsed'], 0.000_001);
        // Slower statement first
        $this->assertSame('SELECT * FROM a', $result['groups'][0]['sql']);
    }

    public function testFormatPreservesParameterContainerReference(): void
    {
        $container = new ParameterContainer(['key' => 'value']);
        $profiles  = [
            ['sql' => 'SELECT ?', 'parameters' => $container, 'start' => 1000.0, 'end' => 1000.001, 'elapse' => 0.001],
        ];

        $result = $this->formatter->format($profiles);

        $this->assertSame($container, $result['groups'][0]['executions'][0]['parameters']);
    }

    public function testFormatSingleProfile(): void
    {
        $profiles = [
            ['sql' => 'SELECT 1', 'parameters' => null, 'start' => 1000.0, 'end' => 1000.001, 'elapse' => 0.001],
        ];

        $result = $this->formatter->format($profiles);

        $this->assertSame(1, $result['summary']['total_queries']);
        $this->assertSame(1, $result['summary']['unique_statements']);
        $this->assertEqualsWithDelta(0.001, $result['summary']['total_elapsed'], 0.000_001);
        $this->assertEqualsWithDelta(0.001, $result['summary']['slowest_elapsed'], 0.000_001);
        $this->assertCount(1, $result['groups']);
        $this->assertSame('SELECT 1', $result['groups'][0]['sql']);
        $this->assertSame(1, $result['groups'][0]['count']);
        $this->assertCount(1, $result['groups'][0]['executions']);
    }

    public function testFormatSkipsOpenProfiles(): void
    {
        $profiles = [
            ['sql' => 'OPEN QUERY', 'parameters' => null, 'start' => 1000.0, 'end' => null, 'elapse' => null],
            ['sql' => 'CLOSED QUERY', 'parameters' => null, 'start' => 1001.0, 'end' => 1001.001, 'elapse' => 0.001],
        ];

        $result = $this->formatter->format($profiles);

        $this->assertSame(1, $result['summary']['total_queries']);
        $this->assertSame('CLOSED QUERY', $result['groups'][0]['sql']);
    }

    public function testFormatSortsByTotalElapsedDescending(): void
    {
        $profiles = [
            ['sql' => 'FAST', 'parameters' => null, 'start' => 1000.0, 'end' => 1000.001, 'elapse' => 0.001],
            ['sql' => 'SLOW', 'parameters' => null, 'start' => 1001.0, 'end' => 1001.1, 'elapse' => 0.1],
            ['sql' => 'MEDIUM', 'parameters' => null, 'start' => 1002.0, 'end' => 1002.01, 'elapse' => 0.01],
        ];

        $result = $this->formatter->format($profiles);

        $this->assertSame(['SLOW', 'MEDIUM', 'FAST'], array_column($result['groups'], 'sql'));
    }

    public function testFormatSummarySlowEstIsMaxSingleElapsed(): void
    {
        $profiles = [
            ['sql' => 'A', 'parameters' => null, 'start' => 1000.0, 'end' => 1000.005, 'elapse' => 0.005],
            ['sql' => 'A', 'parameters' => null, 'start' => 1001.0, 'end' => 1001.001, 'elapse' => 0.001],
            ['sql' => 'B', 'parameters' => null, 'start' => 1002.0, 'end' => 1002.003, 'elapse' => 0.003],
        ];

        $result = $this->formatter->format($profiles);

        // Slowest single execution is 0.005, even though 'A' total (0.006) > any single
        $this->assertEqualsWithDelta(0.005, $result['summary']['slowest_elapsed'], 0.000_001);
    }

    protected function setUp(): void
    {
        $this->formatter = new ProfilerDataFormatter();
    }
}
