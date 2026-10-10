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
use Psr\Http\Message\ServerRequestInterface;
use Tracy\Helpers;
use Tracy\IBarPanel;

final class RequestPanel implements IBarPanel
{
    private readonly string $id;

    public function __construct(
        private ?ServerRequestInterface $data = null,
    ) {
        $this->id = 'request';
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

    public function setData(?ServerRequestInterface $data): void
    {
        $this->data = $data;
    }
}
