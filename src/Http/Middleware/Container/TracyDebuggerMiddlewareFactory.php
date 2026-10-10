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

namespace Webware\Traccio\Http\Middleware\Container;

use Mezzio\Router\RouteCollectorInterface;
use PhpDb\Adapter\Profiler\ProfilerInterface;
use Psr\Container\ContainerInterface;
use Webware\Traccio\Configuration;
use Webware\Traccio\Debug;

final readonly class TracyDebuggerMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): TracyDebuggerMiddleware
    {
        /** @var bool $hasProfiler */
        $hasProfiler = $container->has(ProfilerInterface::class);
        // Gotta have both the route collector and the panel to add it to the bar
        $hasRouteCollector =
            $container->has(RouteCollectorInterface::class) && $container->has(Debug\RoutesPanel::class);

        /** @var Debug\RoutesPanel|null $routesPanel */
        $routesPanel = $hasRouteCollector ? $container->get(Debug\RoutesPanel::class) : null;

        /** @var Debug\ConfigPanel|null $configPanel */
        $configPanel = $container->has(Debug\ConfigPanel::class) ? $container->get(Debug\ConfigPanel::class) : null;

        /** @var Debug\SqlProfilerPanel|null $sqlProfilerPanel */
        $sqlProfilerPanel = $hasProfiler && $container->has(Debug\SqlProfilerPanel::class)
            ? $container->get(Debug\SqlProfilerPanel::class)
            : null;

        return new TracyDebuggerMiddleware(
            Configuration::debug($container),
            Configuration::get($container),
            $hasProfiler,
            $configPanel,
            $sqlProfilerPanel,
            $routesPanel,
        );
    }
}
