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

final readonly class RequestPanelMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Debug\RequestPanel $panel,
        private bool $debug,
    ) {}

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->debug) {
            $this->panel->setData($request);
            Debugger::getBar()->addPanel($this->panel);
            Debugger::$showBar = true;
        }

        return $handler->handle($request);
    }
}
