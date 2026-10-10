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

use Override;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Adapter\Profiler\Profiler;
use Tracy\Helpers;
use Tracy\IBarPanel;

final readonly class SqlProfilerPanel implements IBarPanel
{
    private string $id;

    public function __construct(
        private AdapterInterface $data,
    ) {
        $this->id = 'database';
    }

    #[Override]
    public function getPanel(): string
    {
        return Helpers::capture(function () {
            $profiler = $this->data->getProfiler();
            $profiles = $profiler instanceof Profiler ? $profiler->getProfiles() : [];
            $data     = new ProfilerDataFormatter()->format($profiles);

            require __DIR__ . "/panels/{$this->id}.panel.phtml";
        });
    }

    #[Override]
    public function getTab(): string
    {
        return Helpers::capture(function () {
            $profiler = $this->data->getProfiler();
            $profiles = $profiler instanceof Profiler ? $profiler->getProfiles() : [];
            $summary  = new ProfilerDataFormatter()->format($profiles)['summary'];
            $count    = $summary['total_queries'];
            $total    = $summary['total_elapsed'];

            require __DIR__ . "/panels/{$this->id}.tab.phtml";
        });
    }
}
