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

use Mezzio\Router\RouteCollectorInterface;
use Override;
use Tracy\Helpers;
use Tracy\IBarPanel;

final readonly class RoutesPanel implements IBarPanel
{
    private string $id;

    public function __construct(
        private RouteCollectorInterface $data,
    ) {
        $this->id = 'routes';
    }

    #[Override]
    public function getPanel(): string
    {
        return Helpers::capture(function () {
            $data = $this->data;

            require __DIR__ . "/panels/{$this->id}.panel.phtml";
        });
    }

    #[Override]
    public function getTab(): string
    {
        return Helpers::capture(function () {
            $data  = $this->data;
            $title = $this->id;

            require __DIR__ . "/panels/{$this->id}.tab.phtml";
        });
    }
}
