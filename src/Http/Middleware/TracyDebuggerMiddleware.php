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

namespace Webware\Traccio\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tracy\Debugger;
use Webware\Traccio\Debug;

final readonly class TracyDebuggerMiddleware implements MiddlewareInterface
{
    final public const ENABLE_KEY = 'enable';

    public function __construct(
        private bool $debug,
        /** @var TracyConfig $tracyConfig */
        private array $tracyConfig,
        private bool $enableSqlProfiler,
        private ?Debug\ConfigPanel $configPanel,
        private ?Debug\SqlProfilerPanel $sqlProfilerPanel,
        private ?Debug\RoutesPanel $routesPanel,
    ) {}

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->debug) {
            if (isset($this->tracyConfig[self::ENABLE_KEY])) {
                Debugger::enable($this->tracyConfig[self::ENABLE_KEY]);
            }
            foreach ($this->tracyConfig as $key => $value) {
                if (self::ENABLE_KEY === $key) {
                    continue;
                }
                Debugger::${$key} = $value;
            }

            if ($this->enableSqlProfiler && $this->sqlProfilerPanel) {
                Debugger::getBar()->addPanel($this->sqlProfilerPanel);
            }

            if ($this->configPanel) {
                Debugger::getBar()->addPanel($this->configPanel);
            }

            if ($this->routesPanel) {
                Debugger::getBar()->addPanel($this->routesPanel);
            }
        }

        return $handler->handle($request);
    }
}
