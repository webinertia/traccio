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

namespace Webware\Traccio;

use Tracy\Debugger;

/**
 * @internal
 *
 * @phpstan-type DependencyShape array{
 *     factories: array<class-string, class-string>,
 *     delegators?: array<class-string, array<int, class-string>>,
 * }
 * @phpstan-type ConfigShape array{
 *     debug?: bool,
 *     DependencyShape,
 *     Debugger::class: TracyConfig,
 * }
 */
final readonly class ConfigProvider
{
    /**
     * @phpstan-return DependencyShape
     */
    public function getDependencies(): array
    {
        return [
            'factories' => [
                Debug\ConfigPanel::class                       => Debug\ConfigPanelFactory::class,
                Debug\RequestPanel::class                      => Debug\RequestPanelFactory::class,
                Debug\SqlProfilerPanel::class                  => Debug\SqlProfilerPanelFactory::class,
                Debug\RoutesPanel::class                       => Debug\RoutesPanelFactory::class,
                Http\Middleware\TracyDebuggerMiddleware::class => Http\Middleware\Container\TracyDebuggerMiddlewareFactory::class,
                Http\Middleware\RequestPanelMiddleware::class  => Http\Middleware\Container\RequestPanelMiddlewareFactory::class,
            ],
            // Add this to the application's config to enable DB profiling
            // 'delegators' => [
            //     AdapterInterface::class => [
            //         PhpDb\ProfilingDelegator::class,
            //     ],
            // ],
        ];
    }

    /**
     * @return TracyConfig
     */
    public function getTracyConfig(): array
    {
        return [
            'dumpTheme'  => 'dark',
            'keysToHide' => [
                'password',
                'pass',
                'secret',
            ],
        ];
    }

    /**
     * @phpstan-return ConfigShape
     */
    public function __invoke(): array
    {
        return [
            'dependencies'  => $this->getDependencies(),
            Debugger::class => $this->getTracyConfig(),
        ];
    }
}
