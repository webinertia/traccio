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

use Psr\Container\ContainerInterface;
use Tracy\Debugger;

/**
 * @internal
 *
 * @phpstan-import-type ConfigShape from ConfigProvider
 */
final class Configuration
{
    public static function debug(ContainerInterface $container): bool
    {
        /** @var array{debug?: bool} $config */
        $config = $container->get('config');

        return $config['debug'] ?? false;
    }

    /**
     * @phpstan-return TracyConfig
     */
    public static function get(ContainerInterface $container): array
    {
        /** @var array{Debugger::class: TracyConfig} $config */
        $config = $container->get('config');

        return $config[Debugger::class];
    }
}
