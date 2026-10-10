# Getting Started

## Requirements

| Requirement | Version |
| --- | --- |
| PHP | `~8.2 \|\| ~8.3 \|\| ~8.4 \|\| ~8.5` |
| tracy/tracy | `^2.11` |
| mezzio/mezzio | `^3.26` (host application) |

Database profiling additionally requires a [PhpDb](https://github.com/php-db) adapter and the
`php-db/phpdb` package. See [Database profiling](./configuration.md#database-profiling).

## Installation

```bash
composer require webware/traccio
```

## Register the ConfigProvider

Add `Webware\Traccio\ConfigProvider` to your application's config aggregator. The
[laminas-component-installer](https://docs.laminas.dev/laminas-component-installer/) plugin offers to
do this for you.

```php
// config/config.php
use Laminas\ConfigAggregator\ConfigAggregator;
use Webware\Traccio\ConfigProvider;

$aggregator = new ConfigAggregator([
    ConfigProvider::class,
    // ... other providers
]);
```

## Enable debug

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

Setting the top level `'debug' => true` flag is what turns Traccio on. Enabling Mezzio development
mode sets that flag for you.

## Wire the pipeline

`TracyDebuggerMiddleware` takes the place of `ErrorHandler` while debugging, so that panels see the
request and Tracy renders the blue screen for uncaught exceptions.

```php
// config/pipeline.php
use Webware\Traccio\Http\Middleware\TracyDebuggerMiddleware;

$app->pipe(TracyDebuggerMiddleware::class);

// ... the rest of your pipeline
```

Pipe it first, or at least before anything that can throw. A guarded pipe keeps it optional and only
active while debugging:

```php
class_exists(TracyDebuggerMiddleware::class) && $container->get('config')['debug']
    ? $app->pipe(TracyDebuggerMiddleware::class)
    : $app->pipe(ErrorHandler::class);
```

## Optional: the request panel

`RequestPanelMiddleware` captures the final state of the request, so it belongs near the end of the
pipeline. It is not required for Traccio to work.

```php
use Webware\Traccio\Http\Middleware\RequestPanelMiddleware;

$app->pipe(RequestPanelMiddleware::class);
```

## Next

- [Configuration](./configuration.md) for every option, panel requirement, and database profiling.
