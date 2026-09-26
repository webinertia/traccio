# Webware\Traccio - GitHub Copilot Instructions

## Read this before acting — fleet working agreements

`vendor/webware/webware-tools/agent-working-agreements.md` is authoritative for how work is done
in this fleet, and it overrides anything else in this file or in any local note. Read it before
acting, and again before reporting. The non-negotiables:

- Do exactly the scope asked — only the files, branches and repositories named. No adjacent edits,
  no cleanup, no onboarding, no surveys.
- Anything else you notice gets one line at most, with no proposed fix.
- Never close a reply with a condensed restatement of it, and never present an inference as a
  measurement.
- Every change lands through a pull request. Releases and tags belong to the repository owner.

## Project Overview

**Webware\Traccio** is a Tracy Debugger integration and profiler library for Mezzio PHP applications. It provides a seamless way to integrate the powerful Tracy debugging bar into Mezzio applications with custom debug panels for configuration, routes, requests, and database profiling.

### Key Information
- **License**: BSD-3-Clause
- **PHP Versions**: 8.2, 8.3, 8.4, 8.5
- **Primary Framework**: Mezzio (PHP middleware framework)
- **Core Debugger**: Tracy (tracy/tracy ^2.11.0)
- **Namespace**: `Webware\Traccio`
- **Config Provider**: `Webware\Traccio\ConfigProvider`

## Architecture

### Core Components

#### ConfigProvider (`src/ConfigProvider.php`)
- **Purpose**: Laminas component installer configuration provider
- **Provides**:
  - Service factory definitions
  - Default Tracy configuration (dark theme, key hiding for sensitive data)
  - Optional database profiling delegator (commented out by default)

### Debug Panel Architecture

All debug panels follow a consistent pattern:

#### Panel Structure
Each panel consists of three components:

1. **Panel Class** (e.g., `RoutesPanel.php`):
   - Implements `Tracy\IBarPanel` interface directly — no shared trait
   - Declared `final readonly` (or `final` if `setData()` mutation is needed)
   - Has a `private string $id` property set in the constructor
   - Constructor receives typed data dependency via `private` constructor promotion
   - Implements `getTab()` and `getPanel()` inline using `Tracy\Helpers::capture()`
   - Complex panels (e.g., `SqlProfilerPanel`) inject pre-computed variables instead of raw `$data`

2. **Factory Class** (e.g., `RoutesPanelFactory.php`):
   - Resolves dependencies from PSR-11 container
   - Creates and returns panel instance with required data

3. **Template Files** (in `src/Debug/panels/`):
   - **Tab template** (`{id}.tab.phtml`): Icon/label shown in Tracy bar
   - **Panel template** (`{id}.panel.phtml`): Full panel content when clicked

#### Panel Rendering Pattern
- All panels implement `getTab()` and `getPanel()` **directly** on the panel class — there is no shared trait
- **Variables injected into templates**:
  - `getTab()` injects `$data` (raw panel data) and `$title` (the panel `$id` string)
  - `getPanel()` injects `$data` (raw panel data)
- **Standard implementation**: Both methods wrap a `require` of the panel template inside `Tracy\Helpers::capture()`, injecting `$data` (and `$title` for the tab)
- **Complex panels** (e.g., `SqlProfilerPanel`): inject pre-computed variables instead of raw `$data` when the dependency must be transformed before rendering
- `setData()` should be added as a public method only when the data must be set after construction (e.g., `RequestPanel`, which receives the request from a later middleware)

### Existing Debug Panels

#### 1. ConfigPanel (`src/Debug/ConfigPanel.php`)
- **ID**: `config`
- **Data**: `array` - Application configuration
- **Purpose**: Display full application configuration in Tracy bar
- **Factory**: Retrieves config from container

#### 2. RequestPanel (`src/Debug/RequestPanel.php`)
- **ID**: `request`
- **Data**: `?ServerRequestInterface` - Current PSR-7 request
- **Purpose**: Display HTTP request details (headers, method, URI, etc.)
- **Factory**: Can accept null (set later by middleware)

#### 3. RoutesPanel (`src/Debug/RoutesPanel.php`)
- **ID**: `routes`
- **Data**: `RouteCollector` - Mezzio route collector
- **Purpose**: Display all registered application routes
- **Factory**: Injects `RouteCollectorInterface` from container

#### 4. SqlProfilerPanel (`src/Debug/SqlProfilerPanel.php`)
- **ID**: `database`
- **Data**: `AdapterInterface` (PhpDb) - Database adapter with profiler
- **Purpose**: Display grouped SQL query profiling information with timing and parameters
- **Rendering**: Overrides `getTab()` and `getPanel()` directly — does NOT rely on the trait's default implementations
- **Key behaviour**:
  - Uses `instanceof PhpDb\Adapter\Profiler\Profiler` guard before calling `getProfiles()`, because `getProfiles()` is only on the concrete `Profiler` class, not on `ProfilerInterface`
  - Delegates raw profile aggregation to `ProfilerDataFormatter`
  - Injects pre-computed `$count` (int) and `$total` (float, seconds) into the tab template
  - Injects formatted `$data` array (`['summary' => [...], 'groups' => [...]]`) into the panel template
- **Factory**: Conditional - only registered when `PhpDb\Adapter\ProfilerInterface` is available in the container

#### ProfilerDataFormatter (`src/Debug/ProfilerDataFormatter.php`)
- **Purpose**: Pure aggregation utility — no framework dependencies
- **Method**: `format(array $profiles): array`
  - Accepts raw entries from `Profiler::getProfiles()`
  - Skips profiles with `null` elapse (still-open / prepare-only queries)
  - Groups identical SQL statements, computing count, total/avg/slowest per group
  - Sorts groups by `total_elapsed` descending
  - Returns:
    ```php
    [
        'summary' => [
            'total_queries'     => int,
            'unique_statements' => int,
            'total_elapsed'     => float,  // seconds
            'slowest_elapsed'   => float,  // seconds
        ],
        'groups' => [
            [
                'sql'           => string,
                'count'         => int,
                'total_elapsed' => float,
                'avg_elapsed'   => float,
                'slowest'       => float,
                'executions'    => [
                    [
                        'index'      => int,    // original profile index
                        'start'      => float,  // Unix timestamp with microseconds
                        'elapsed'    => float,  // seconds
                        'parameters' => ?ParameterContainer,
                    ],
                ],
            ],
        ],
    ]
    ```

### Middleware

#### TracyDebuggerMiddleware (`src/Middleware/TracyDebuggerMiddleware.php`)
- **Purpose**: Initialize Tracy debugger and register panels
- **Constructor Parameters**:
  - `bool $debug` - Whether debug mode is enabled
  - `array $tracyConfig` - Tracy configuration (theme, keys to hide, etc.)
  - `bool $enableSqlProfiler` - Whether to add the SQL profiler panel to the bar
  - `?ConfigPanel $configPanel` - Optional config panel
  - `?SqlProfilerPanel $sqlProfilerPanel` - Optional database panel
  - `?RoutesPanel $routesPanel` - Optional routes panel
- **Process Flow**:
  1. Check if debug mode is enabled
  2. Apply Tracy configuration (set Debugger static properties dynamically)
  3. Add panels to Tracy bar based on availability and `$enableSqlProfiler` flag
  4. Pass request to next handler

#### TracyDebuggerMiddlewareFactory (`src/Middleware/TracyDebuggerMiddlewareFactory.php`)
- **Purpose**: Create middleware with conditional panel registration
- **Logic**:
  - Retrieves `debug` flag from config
  - Retrieves Tracy configuration from `config[Debugger::class]`
  - Sets `$enableSqlProfiler` to `true` when `PhpDb\Adapter\Profiler\ProfilerInterface` is in the container
  - Conditionally resolves each panel; passes `null` when not available

#### RequestPanelMiddleware (`src/Middleware/RequestPanelMiddleware.php`)
- **Purpose**: Inject the resolved PSR-7 request into `RequestPanel` after routing
- **Placement**: Must be piped **after** routing middleware so the fully-resolved request is available
- **Factory**: `RequestPanelMiddlewareFactory`

### Database Profiling Integration

#### ProfilingDelegator (`src/PhpDb/ProfilingDelegator.php`)
- **Purpose**: Wrap a PhpDb adapter with a `Profiler` instance
- **Type**: Laminas service manager delegator factory
- **Process**:
  1. Calls the original factory callback to get the adapter
  2. Creates a `new PhpDb\Adapter\Profiler\Profiler()` and attaches it via `setProfiler()`
  3. Returns the profiler-enabled adapter
- **Activation**: Add the delegator to your application config (it is commented out in `ConfigProvider` by default)

## Development Patterns

### Creating a New Debug Panel

#### Simple panel (data passed directly to template)

Use this when the template can work directly with the raw data dependency. All panels use `final readonly class` unless `setData()` mutation is required.

1. **Create Panel Class** (`src/Debug/{Name}Panel.php`):
```php
<?php
declare(strict_types=1);

namespace Webware\Traccio\Debug;

use Tracy\Helpers;
use Tracy\IBarPanel;

final readonly class {Name}Panel implements IBarPanel
{
    private string $id;

    public function __construct(
        private {DataType} $data,
    ) {
        $this->id = '{panel-id}';
    }

    public function getTab(): string
    {
        return Helpers::capture(function () {
            $data  = $this->data;
            $title = $this->id;

            require __DIR__ . "/panels/{$this->id}.tab.phtml";
        });
    }

    public function getPanel(): string
    {
        return Helpers::capture(function () {
            $data = $this->data;

            require __DIR__ . "/panels/{$this->id}.panel.phtml";
        });
    }
}
```

2. **Create Factory** (`src/Debug/{Name}PanelFactory.php`):
```php
<?php
declare(strict_types=1);

namespace Webware\Traccio\Debug;

use Psr\Container\ContainerInterface;

final readonly class {Name}PanelFactory
{
    public function __invoke(ContainerInterface $container): {Name}Panel
    {
        return new {Name}Panel(
            $container->get({DependencyInterface}::class)
        );
    }
}
```

3. **Create Templates**:
   - `src/Debug/panels/{panel-id}.tab.phtml` - SVG icon + label
   - `src/Debug/panels/{panel-id}.panel.phtml` - Full panel content

4. **Register in ConfigProvider** (`src/ConfigProvider.php`):
```php
'factories' => [
    Debug\{Name}Panel::class => Debug\{Name}PanelFactory::class,
],
```

5. **Add to Middleware** (if needed):
   - Add a constructor parameter to `TracyDebuggerMiddleware`
   - Resolve it conditionally in `TracyDebuggerMiddlewareFactory`
   - Call `Debugger::getBar()->addPanel(...)` in the middleware `process()` method

#### Complex panel (pre-processed data injected into template)

Use this when the panel must aggregate, transform, or guard data before rendering. Inject only the variables the template needs — do not pass the raw dependency:

```php
final readonly class {Name}Panel implements IBarPanel
{
    private string $id;

    public function __construct(
        private {ServiceType} $data,
    ) {
        $this->id = '{panel-id}';
    }

    public function getTab(): string
    {
        return Helpers::capture(function () {
            // compute $varA, $varB from $this->data
            require __DIR__ . '/panels/{panel-id}.tab.phtml';
        });
    }

    public function getPanel(): string
    {
        return Helpers::capture(function () {
            // compute $data from $this->data
            require __DIR__ . '/panels/{panel-id}.panel.phtml';
        });
    }
}
```

### Panel Template Guidelines

#### Tab Template Pattern (standard panel — receives `$data` and `$title`):
```php
<?php
declare(strict_types=1);

namespace Webware\Traccio\Debug;

if (!isset($data)) {
    return;
}
?>
<svg viewBox="0 0 640 512">
    <!-- SVG path -->
</svg>
<span class="tracy-label"><?= isset($title) ? $title : 'Panel Name' ?></span>
```

#### Tab Template Pattern (complex panel — receives pre-computed scalars):
```php
<?php
declare(strict_types=1);

namespace Webware\Traccio\Debug;

// Variables injected directly by the panel class, e.g. $count (int), $total (float seconds)
$timeStr = ($total ?? 0.0) > 0.0 ? sprintf(' / %.1f ms', ($total ?? 0.0) * 1000) : '';
?>
<svg viewBox="..."><!-- icon --></svg>
<span class="tracy-label"><?= 'Label: ' . ($count ?? '0') . $timeStr ?></span>
```

#### Panel Template Pattern (standard panel — receives raw `$data`):
```php
<?php
declare(strict_types=1);

namespace Webware\Traccio\Debug;

use Tracy\Dumper;

if (!$data instanceof {ExpectedType}) {
    return;
}

Dumper::renderAssets();
?>
<div class="tracy-inner tracy-{CustomClass}">
    <div class="tracy-inner-container">
        <?php Dumper::dump($data); ?>
    </div>
</div>
```

#### Panel Template Pattern (complex panel — receives pre-processed `$data` array):
```php
<?php
declare(strict_types=1);

namespace Webware\Traccio\Debug;

use Tracy\Helpers;

// $data is an array produced by a formatter/aggregator, e.g.:
// $summary = $data['summary'];
// $groups  = $data['groups'];
```

#### CSS Override Guidelines

Tracy ships its own `bar.css` that sets light-theme defaults for all panels:
- `#tracy-debug table { background: #FDF5CE }` (yellowish)
- `#tracy-debug td, th { border: 1px solid #E6DFBF }`
- `#tracy-debug .tracy-panel { background: white; color: #333 }`
- `#tracy-debug th { background: #F4F3F1; color: #655E5E }`

When a panel uses custom styling (e.g., a dark theme), these must be explicitly overridden. Always scope custom rules to the panel's root class for sufficient specificity, and set `background` on every `td`/`th` variant:

```html
<style class="tracy-debug">
    #tracy-debug .tracy-{CustomClass} { background: #1e1e1e; color: #d0d0d0; }
    #tracy-debug .tracy-{CustomClass} table { background: transparent; }
    #tracy-debug .tracy-{CustomClass} td,
    #tracy-debug .tracy-{CustomClass} th { border-color: #444; color: #d0d0d0; }
    #tracy-debug .tracy-{CustomClass} tr:nth-child(2n) td { background: rgba(255,255,255,.03); }
    /* override th background explicitly */
    #tracy-debug .tracy-{CustomClass} th { background: #333; }
</style>
```

Attach the `<style>` block at the top of the panel template with the `class="tracy-debug"` attribute so Tracy includes it in the bar's stylesheet injection.

### Parameter Token Display (SQL panels)

`PhpDb\Adapter\ParameterContainer::getNamedArray()` stores keys as:
- Positional `?` parameters: string integer keys `"0"`, `"1"`, …
- Named parameters (e.g., `:C_1`, `:name`): string keys **without** the leading colon — `"C_1"`, `"name"`

To display the original token as used in the SQL string:
```php
$token = is_numeric($k) ? '?' . ((int) $k + 1) : ':' . ltrim((string) $k, ':');
```

This produces `?1`, `?2` for positional and `:C_1`, `:name` for named parameters.

> **Important:** Only queries that complete a full prepare + execute cycle produce finished profiler entries (non-null `elapse`). Calling `query($sql)` without parameters returns a `Statement` object and never calls `profilerFinish()` — such entries will have `elapse === null` and are skipped by `ProfilerDataFormatter`.

## Configuration

### Tracy Configuration (`config/autoload/tracy.global.php`):
```php
use Tracy\Debugger;

return [
    'debug' => true, // Enable debug mode
    Debugger::class => [
        'dumpTheme' => 'dark', // 'light' or 'dark'
        'keysToHide' => [
            'password',
            'pass',
            'secret',
            'token',
            'api_key',
        ],
        // Any public static property on Tracy\Debugger can be set here
        'maxDepth'     => 10,
        'maxLength'    => 250,
        'showLocation' => true,
    ],
];
```

### Enabling Database Profiling:
In your application's config file or a `ConfigProvider`:
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

## Testing

### Test Structure
- **Unit Tests**: `test/unit/` (namespace: `WebwareTest\Traccio`)
- **Integration Tests**: `test/integration/` (namespace: `WebwareIntegrationTest\Traccio`)
- **Test Assets**: `test/asset/` (namespace: `WebwareTestAsset\Traccio`)
- **Visual Reference**: `test/asset/panel-preview.html` — static HTML demonstrating panel layout with Tracy's actual conflicting CSS included to verify override specificity

### Running Tests
```bash
composer test              # Unit tests only
composer test-integration  # Integration tests
composer check             # Full check (cs-check, static analysis, all tests)
```

### Code Quality
```bash
composer cs-check          # Check code style
composer cs-fix            # Fix code style
composer sa                # Static analysis with PHPStan
composer sa-verbose        # Verbose static analysis
```

### Integration Test Notes
- Integration tests for `SqlProfilerPanel` use an in-memory SQLite adapter
- Queries **must** use the prepare+execute path (pass a parameter array) to produce finished profiler entries; `query($sql)` alone (prepare-only) leaves `elapse === null` and is invisible to the panel

## Dependencies

### Required
- `php`: ~8.2.0 || ~8.3.0 || ~8.4.0 || ~8.5.0
- `tracy/tracy`: ^2.11.0

### Development
- `mezzio/mezzio`: ^3.26
- `laminas/laminas-diactoros`: ^3.8 (PSR-7 implementation)
- `phpunit/phpunit`: ^11.5
- `phpstan/phpstan`: ^2.1
- `webware/coding-standard`: ^0.1.0 (code style)
- `php-db/phpdb-sqlite`: ^0.2.0 (SQLite adapter for integration tests)

## Coding Standards

### PSR Compliance
- **PSR-4**: Autoloading standard
- **PSR-7**: HTTP message interfaces
- **PSR-11**: Container interface
- **PSR-15**: HTTP server middleware

### Code Style
- **Strict Types**: All files use `declare(strict_types=1);`
- **Final Classes**: Use `final` keyword for classes that shouldn't be extended
- **Type Hints**: Full type declarations on all properties, parameters, and returns
- **Constructor Promotion**: Use `private` constructor property promotion

### PHPStan Configuration
- **Level**: 10 (configured in `phpstan.neon.dist`)
- **Global type alias**: `TracyConfig` defined in `phpstan.neon.dist` under `typeAliases` — no import needed in any file
- **Stubs**: Custom stubs in `stubs/` for Laminas ServiceManager and PSR Container
- **Baseline**: `phpstan-baseline.neon` for known/acceptable issues

## Key Integration Points

### Mezzio Application Bootstrap
```php
// In config/pipeline.php
$app->pipe(\Webware\Traccio\Middleware\TracyDebuggerMiddleware::class);
// ... error handler, routing, ...
$app->pipe(\Webware\Traccio\Middleware\RequestPanelMiddleware::class);
```

## Extension Opportunities

### Potential New Panels
- **Session Panel**: Display session data
- **Cache Panel**: Show cache hits/misses and stored keys
- **Event Panel**: Display dispatched events
- **Authentication Panel**: Show current user and permissions
- **Performance Panel**: Display detailed timing breakdown

### Integration Points
- **Laminas Components**: Additional Laminas-specific panels
- **Doctrine**: Database query profiling for Doctrine ORM
- **Logging**: PSR-3 logger integration panel
- **Queue/Job Systems**: Background job monitoring

## Important Notes

- Always use `declare(strict_types=1);` at the top of new PHP files
- Panel IDs must match template filenames (`{id}.tab.phtml`, `{id}.panel.phtml`)
- Database profiling requires explicit delegator configuration
- Tracy debugger only activates when `config['debug'] === true`
- All panels are optional and conditionally registered
- `getProfiles()` is only on the concrete `PhpDb\Adapter\Profiler\Profiler` class, not on `ProfilerInterface` — always guard with `instanceof Profiler` before calling it
- Use `Tracy\Dumper` for rendering complex data structures in panel templates
- SVG icons in tabs should use Tracy's colour scheme (`#7a86b8`)
- Tracy's default `bar.css` applies light-theme table styles to all panels — always scope custom CSS overrides to a panel-specific class for sufficient specificity

## Maintenance

- **Repository**: tyrsson/traccio
- **Branch**: 0.1.x
- **Security**: Uses roave/security-advisories for vulnerability scanning
- **Renovate**: Automated dependency updates enabled
