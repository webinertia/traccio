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

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Webware\Traccio\Configuration;
use Webware\Traccio\Debug;
use Webware\Traccio\Http\Middleware\RequestPanelMiddleware;

/**
 * @internal
 */
final readonly class RequestPanelMiddlewareFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): RequestPanelMiddleware
    {
        /** @var Debug\RequestPanel $requestPanel */
        $requestPanel = $container->get(Debug\RequestPanel::class);

        return new RequestPanelMiddleware(
            $requestPanel,
            Configuration::debug($container),
        );
    }
}
