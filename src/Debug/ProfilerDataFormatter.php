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

namespace Webware\Traccio\Debug;

use PhpDb\Adapter\ParameterContainer;
use PhpDb\Adapter\Profiler\Profiler;

use function array_key_exists;
use function array_values;
use function count;
use function usort;

/**
 * @internal
 *
 * @phpstan-import-type ProfileShape from Profiler
 * @phpstan-import-type ProfilesShape from Profiler
 *
 * @phpstan-type GroupedProfileShape array{sql: string, count: int, total_elapsed: float, slowest: float, avg_elapsed: float, executions: list<array{index: int, start: float, elapsed: float, parameters: ParameterContainer|null}>}
 * @phpstan-type FormattedDataShape array{
 *   summary: array{total_queries: int, unique_statements: int, total_elapsed: float, slowest_elapsed: float},
 *   groups: list<GroupedProfileShape>
 * }
 */
final readonly class ProfilerDataFormatter
{
    /**
     * Aggregates raw profiler entries into a grouped, summary-enriched structure.
     * Profiles with a null elapse value (still-open queries) are skipped.
     * Groups are sorted by total elapsed time descending.
     *
     * @param ProfilesShape $profiles
     * @return FormattedDataShape
     */
    public function format(array $profiles): array
    {
        /** @var array<string, GroupedProfileShape> $grouped */
        $grouped        = [];
        $totalQueries   = 0;
        $totalElapsed   = 0.0;
        $slowestElapsed = 0.0;

        foreach ($profiles as $index => $profile) {
            /** @var ProfileShape $profile */
            if (null === $profile['elapse'] || null === $profile['end']) {
                continue;
            }

            $sql     = $profile['sql'];
            $elapsed = $profile['elapse'];

            $totalQueries++;
            $totalElapsed += $elapsed;

            if ($elapsed > $slowestElapsed) {
                $slowestElapsed = $elapsed;
            }

            if (! array_key_exists($sql, $grouped)) {
                $grouped[$sql] = [
                    'sql'           => $sql,
                    'count'         => 0,
                    'total_elapsed' => 0.0,
                    'slowest'       => 0.0,
                    'avg_elapsed'   => 0.0,
                    'executions'    => [],
                ];
            }

            $grouped[$sql]['count']++;
            $grouped[$sql]['total_elapsed'] += $elapsed;

            if ($elapsed > $grouped[$sql]['slowest']) {
                $grouped[$sql]['slowest'] = $elapsed;
            }

            $grouped[$sql]['executions'][] = [
                'index'      => $index,
                'start'      => $profile['start'],
                'elapsed'    => $elapsed,
                'parameters' => $profile['parameters'],
            ];
        }

        $groups = array_values($grouped);

        foreach ($groups as &$group) {
            $group['avg_elapsed'] = $group['count'] > 0
                ? $group['total_elapsed'] / $group['count']
                : 0.0;
        }
        unset($group);

        usort($groups, static fn(array $a, array $b): int => $b['total_elapsed'] <=> $a['total_elapsed']);

        return [
            'summary' => [
                'total_queries'     => $totalQueries,
                'unique_statements' => count($groups),
                'total_elapsed'     => $totalElapsed,
                'slowest_elapsed'   => $slowestElapsed,
            ],
            'groups'  => $groups,
        ];
    }
}
