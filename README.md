# webware/traccio

[![PHP Version](https://img.shields.io/packagist/php-v/webware/traccio)](https://packagist.org/packages/webware/traccio)
[![Latest Version](https://img.shields.io/packagist/v/webware/traccio)](https://packagist.org/packages/webware/traccio)
[![License](https://img.shields.io/github/license/webinertia/traccio)](LICENSE)
[![Required CI](https://github.com/webinertia/traccio/actions/workflows/required/webinertia/.github/.github/workflows/org-required-ci.yml/badge.svg)](https://github.com/webinertia/traccio/actions/workflows/required/webinertia/.github/.github/workflows/org-required-ci.yml)
[![codecov](https://codecov.io/gh/webinertia/traccio/graph/badge.svg)](https://codecov.io/gh/webinertia/traccio)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fwebinertia%2Ftraccio%2F1.0.x)](https://dashboard.stryker-mutator.io/reports/github.com/webinertia/traccio/1.0.x)

Tracy Debugger integration and profiler for [Mezzio](https://docs.mezzio.dev/) applications.

Traccio wires [Tracy](https://tracy.nette.org/) into a Mezzio middleware pipeline and provides purpose-built debug bar panels for configuration, routes, HTTP requests, and SQL query profiling.

---

## Requirements

| Requirement | Version |
| --- | --- |
| PHP | `~8.2 \|\| ~8.3 \|\| ~8.4 \|\| ~8.5` |
| tracy/tracy | `^2.11` |
| mezzio/mezzio | `^3.26` *(dev / host app)* |

Database profiling additionally requires a [PhpDb](https://github.com/php-db) adapter and the `php-db/phpdb` package.

---

## Installation

```bash
composer require webware/traccio
```

If you are using the [laminas-component-installer](https://docs.laminas.dev/laminas-component-installer/) plugin it will prompt you to inject the `ConfigProvider` automatically. Otherwise add it manually (see [Configuration](#configuration)).

---

## Configuration

### 1. Register the ConfigProvider

Add `Webware\Traccio\ConfigProvider` to your application's config aggregator:

```php
// config/config.php
use Laminas\ConfigAggregator\ConfigAggregator;
use Webware\Traccio\ConfigProvider;

$aggregator = new ConfigAggregator([
    ConfigProvider::class,
    // ... other providers
]);
```

### 2. Tracy settings

Create `config/autoload/tracy.global.php` (or add to an existing autoload file):

```php
use Tracy\Debugger;

return [
    'debug' => true,

    Debugger::class => [
        'dumpTheme'  => 'dark',   // 'light' or 'dark'
        'keysToHide' => [
            'password',
            'pass',
            'secret',
        ],
        // Any public static property of Tracy\Debugger can be set here.
        // 'maxDepth'     => 10,
        // 'maxLength'    => 250,
        // 'showLocation' => true,
    ],
];
```

Set `'debug' => true` or enable Mezzio development mode in config.

---

## Middleware pipeline

Add the two middleware classes to your pipeline. `TracyDebuggerMiddleware` should come early so it initialises Tracy before your application logic runs. `RequestPanelMiddleware` should be placed **very late** in the pipeline so the final state of the Request is reflected in the panel (this is not required for Traccio to work).

```php
// config/pipeline.php
use Webware\Traccio\Middleware\RequestPanelMiddleware;
use Webware\Traccio\Middleware\TracyDebuggerMiddleware;

$app->pipe(TracyDebuggerMiddleware::class);

// ... error handler, routing, ...

$app->pipe(RequestPanelMiddleware::class);
```

---

## Debug panels

All panels are registered automatically when their dependencies are available in the container. No extra wiring is needed beyond the steps above.

| Panel | Tracy bar label | Shows |
| --- | --- | --- |
| `ConfigPanel` | Config | Full application config array |
| `RoutesPanel` | Routes | All registered Mezzio routes |
| `RequestPanel` | Request | PSR-7 request headers, method, URI, body |
| `SqlProfilerPanel` | Database | SQL query groups, timings, parameters |

### SQL Profiler panel

The database panel groups identical queries together and displays:

- **Summary bar** — total queries, unique statements, total elapsed, slowest single query (all in ms)
- **Per-group cards** — execution count badge, SQL text, total / average timing
- **Per-execution rows** — wall-clock start time (`H:i:s.mmm`), elapsed ms, bound parameters

Parameter tokens reflect the style used in the original query:

| Parameter style | Displayed token |
| --- | --- |
| Positional `?` (stored as `"0"`, `"1"`, …) | `?1`, `?2`, … |
| Named (stored as `"C_1"`, `"name"`, …) | `:C_1`, `:name` |

#### Enabling database profiling

Database profiling requires:

1. A PhpDb adapter registered in the container as `PhpDb\Adapter\AdapterInterface`.
2. The `ProfilingDelegator` wrapping that adapter to attach a `Profiler` instance.

Enable the delegator in your application's config (it is commented out in `ConfigProvider` by default):

```php
// config/autoload/development.local.php
use PhpDb\Adapter\AdapterInterface;
use Webware\Traccio\PhpDb\ProfilingDelegator;

return [
    'dependencies' => [
        'delegators' => [
            AdapterInterface::class => [
                ProfilingDelegator::class,
            ],
        ],
    ],
];
```

Once the delegator is active, the SQL panel will appear in the Tracy bar automatically whenever `debug` is `true`.

---

## License

BSD-3-Clause — see [LICENSE](LICENSE) for details.
