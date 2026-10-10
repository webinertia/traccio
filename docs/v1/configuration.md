# Configuration

## Configuration surface

Two sections drive Traccio. Both live in application config, so they can be set in any autoload file
and are merged normally.

| Key | Type | Default | Purpose |
| --- | --- | --- | --- |
| `debug` | `bool` | `false` | Master switch. No Tracy setup and no panels when false. |
| `Tracy\Debugger::class` | `array` | see below | Tracy itself. Keys map to `Tracy\Debugger`. |

```php
use Tracy\Debugger;

return [
    'debug'         => true,
    Debugger::class => [
        'enable'     => Debugger::Development,
        'dumpTheme'  => 'dark',
        'keysToHide' => ['password', 'pass', 'secret'],
    ],
];
```

## The `debug` flag

Read from the top level of application config. `TracyDebuggerMiddleware` does nothing at all when it
is false: Tracy is not enabled, no properties are assigned, and no panels are added. The same flag
gates `RequestPanelMiddleware`.

## The `Tracy\Debugger` section

### `enable`

Handled separately. When present, its value is passed to `Debugger::enable()`.

| Value | Constant value | Effect |
| --- | --- | --- |
| `Debugger::Development` | `false` | Force development mode. Recommended. |
| `Debugger::Production` | `true` | Force production mode. |
| `Debugger::Detect` | `null` | Decide from the request IP. Tracy's own default. |

Use the constants. `Development` is the boolean `false` and `Production` is `true`, so a raw boolean
works but reads backwards: `enable(true)` selects production.

Omitting `enable` leaves `Debugger::enable()` uncalled, so Tracy keeps whatever mode it already had.
Set it explicitly.

### Every other key

Each remaining key is assigned to the static property of the same name on `Tracy\Debugger`:

```php
foreach ($tracyConfig as $key => $value) {
    Debugger::${$key} = $value;
}
```

The key must name a public static property of `Tracy\Debugger`. Commonly used ones:

| Property | Type | Tracy default | Effect |
| --- | --- | --- | --- |
| `$dumpTheme` | `string` | `'light'` | Dump colour scheme, `'light'` or `'dark'`. |
| `$keysToHide` | `array` | `[]` | Array keys masked in dumps, at any nesting depth. |
| `$maxDepth` | `int` | `15` | Maximum depth `dump()` descends. |
| `$maxLength` | `int` | `150` | Maximum string length shown before truncation. |
| `$maxItems` | `int` | `100` | Maximum array items shown before truncation. |
| `$showBar` | `bool` | `true` | Whether the debug bar renders. |
| `$showLocation` | `?bool` | `null` | Show the file and line a dump came from. |
| `$strictMode` | `bool\|int` | `false` | Strict mode for notices and deprecations. |
| `$scream` | `bool\|int` | `false` | Disable `@` error suppression. |
| `$logDirectory` | `?string` | `null` | Directory for Tracy log files. |
| `$logSeverity` | `int` | `0` | Minimum severity written to the log. |
| `$email` | `string\|array\|null` | `null` | Where Tracy mails unhandled exceptions. |
| `$editor` | `?string` | `editor://...` | Editor URL template for clickable file links. |
| `$editorMapping` | `array` | `[]` | Map local paths for the editor in containers. |
| `$browser` | `?string` | `null` | Browser to open for file links. |
| `$errorTemplate` | `?string` | `null` | Custom error page template path. |
| `$customCssFiles` | `array` | `[]` | Extra stylesheets for the debug bar. |
| `$customJsFiles` | `array` | `[]` | Extra scripts for the debug bar. |
| `$transparentPaths` | `array` | `[]` | Paths collapsed in stack traces. |
| `$reservedMemorySize` | `int` | `500000` | Bytes reserved for handling fatal errors. |

Tracy's own documentation covers the full set in
[Configuration](https://tracy.nette.org/en/configuration). Any public static property works here
without Traccio needing to know about it.

### Shipped defaults

`ConfigProvider::getTracyConfig()` supplies these, and application config merges over them:

```php
[
    'dumpTheme'  => 'dark',
    'keysToHide' => [
        'password',
        'pass',
        'secret',
    ],
]
```

Dumps of a request, session, or config array therefore mask anything stored under those keys.

`dumpTheme` is overridden normally, since later config wins for a string key. `keysToHide` behaves
differently: the aggregator appends a later numeric list to an earlier one rather than replacing it,
so your entries add to the shipped defaults. Adding `'token'` yields
`['password', 'pass', 'secret', 'token']`, and the defaults do not need repeating.

## Panels

Panels are added to the Tracy bar by `TracyDebuggerMiddleware`, in this order: SQL profiler,
configuration, routes. All are registered in the container by `ConfigProvider`. `RequestPanel` is
added by its own middleware instead.

| Panel | Bar label | Added when | Requires in container |
| --- | --- | --- | --- |
| `ConfigPanel` | Config | always | nothing |
| `RoutesPanel` | Routes | always | `Mezzio\Router\RouteCollectorInterface` |
| `SqlProfilerPanel` | Database | always | `PhpDb\Adapter\Profiler\ProfilerInterface` |
| `RequestPanel` | Request | `RequestPanelMiddleware` is piped | nothing |

A panel whose dependency is missing is skipped silently. That is deliberate: Traccio never breaks an
application that lacks an optional component.

### Config panel

Dumps the merged application config array exactly as the container holds it, including every
provider's contributions. Useful for confirming what a merge actually produced. Use `keysToHide` to
mask credentials that reach config.

### Routes panel

Lists every route registered with the route collector, with its path, name, allowed methods, and
middleware. Requires `RouteCollectorInterface`, which Mezzio registers when a router is configured.

## Database profiling

Two things are needed: the profiler service, and an adapter that has had that profiler attached.

### Enable the profiler service

Register `ProfilerInterface` and the `Profiler` implementation. PhpDb's
`AbstractAdapterInterfaceFactory` looks the interface up when it builds an adapter and injects it
automatically.

```php
// config/autoload/phpdb.local.php
use Laminas\ServiceManager\Factory\InvokableFactory;
use PhpDb\Adapter\Profiler\Profiler;
use PhpDb\Adapter\Profiler\ProfilerInterface;

return [
    'dependencies' => [
        'aliases'   => [
            ProfilerInterface::class => Profiler::class,
        ],
        'factories' => [
            Profiler::class => InvokableFactory::class,
        ],
    ],
];
```

Registering `ProfilerInterface` is also what makes the Database panel appear, because the middleware
factory checks for it in the container.

### Adapters built another way

Adapters constructed outside `AbstractAdapterInterfaceFactory` need the profiler attached after the
fact. Use `ProfilingDelegator` on `AdapterInterface`:

```php
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

`ProfilingDelegator` sets a fresh `Profiler` on the adapter it wraps, so it needs
`PhpDb\Adapter\Profiler\ProfilerInterface` present in the container as well.

### What the panel shows

Queries are grouped by identical SQL. Each group shows an execution count badge, the SQL text, and
total against average timing. Each execution inside a group shows a wall clock start time
(`H:i:s.mmm`), elapsed milliseconds, and the bound parameters.

Parameter tokens reflect the style used in the original query:

| Parameter style | Displayed token |
| --- | --- |
| Positional `?`, stored as `"0"`, `"1"`, ... | `?1`, `?2`, ... |
| Named, stored as `"C_1"`, `"name"`, ... | `:C_1`, `:name` |

The summary bar reports total queries, unique statements, total elapsed time, and the slowest single
query, all in milliseconds.

## Disabling Traccio

Set `'debug' => false`, or leave it unset, and Traccio stays out of the way. No Tracy enable, no
static properties assigned, no panels. Because Mezzio's development mode flips the same flag, a
production deployment needs no Traccio specific change.
