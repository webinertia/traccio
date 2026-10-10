# webware/traccio

[![PHP Version](https://img.shields.io/packagist/php-v/webware/traccio)](https://packagist.org/packages/webware/traccio)
[![Latest Version](https://img.shields.io/packagist/v/webware/traccio)](https://packagist.org/packages/webware/traccio)
[![License](https://img.shields.io/github/license/webinertia/traccio)](LICENSE)
[![Required CI](https://github.com/webinertia/traccio/actions/workflows/required/webinertia/.github/.github/workflows/org-required-ci.yml/badge.svg)](https://github.com/webinertia/traccio/actions/workflows/required/webinertia/.github/.github/workflows/org-required-ci.yml)
[![codecov](https://codecov.io/gh/webinertia/traccio/graph/badge.svg)](https://codecov.io/gh/webinertia/traccio)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fwebinertia%2Ftraccio%2F1.0.x)](https://dashboard.stryker-mutator.io/reports/github.com/webinertia/traccio/1.0.x)

Tracy Debugger integration and profiler for [Mezzio](https://docs.mezzio.dev/) applications.

Traccio wires [Tracy](https://tracy.nette.org/) into a Mezzio middleware pipeline and provides purpose-built debug bar panels for configuration, routes, HTTP requests, and SQL query profiling.

## Documentation

- [v1 documentation](docs/v1/index.md)
  - [Getting Started](docs/v1/getting-started.md)
  - [Configuration](docs/v1/configuration.md)

## Requirements

| Requirement | Version |
| --- | --- |
| PHP | `~8.2 \|\| ~8.3 \|\| ~8.4 \|\| ~8.5` |
| tracy/tracy | `^2.11` |
| mezzio/mezzio | `^3.26` *(host app)* |

Database profiling additionally requires a [PhpDb](https://github.com/php-db) adapter and the `php-db/phpdb` package.

## Quick start

**1. Install**

```bash
composer require webware/traccio
```

**2. Register the ConfigProvider**

The [laminas-component-installer](https://docs.laminas.dev/laminas-component-installer/) plugin offers to do this for you.

```php
// config/config.php
use Laminas\ConfigAggregator\ConfigAggregator;
use Webware\Traccio\ConfigProvider;

$aggregator = new ConfigAggregator([
    ConfigProvider::class,
    // ... other providers
]);
```

**3. Enable debug**

```php
// config/autoload/traccio.global.php
use Tracy\Debugger;

return [
    'debug'         => true,
    Debugger::class => [
        'enable' => Debugger::Development,
    ],
];
```

**4. Pipe the middleware**

```php
// config/pipeline.php
use Webware\Traccio\Http\Middleware\TracyDebuggerMiddleware;

$app->pipe(TracyDebuggerMiddleware::class);
```

Pipe it first, so Tracy is initialised before your application runs. Panels appear automatically when their dependencies are present in the container.

## Panels

| Panel | Bar label | Requires |
| --- | --- | --- |
| `ConfigPanel` | Config | nothing |
| `RoutesPanel` | Routes | `Mezzio\Router\RouteCollectorInterface` |
| `SqlProfilerPanel` | Database | `PhpDb\Adapter\Profiler\ProfilerInterface` |
| `RequestPanel` | Request | `RequestPanelMiddleware` piped late |

See [Configuration](docs/v1/configuration.md) for every option, the full Tracy property list, and database profiling setup.

## License

BSD-3-Clause. See [LICENSE](LICENSE) for details.
