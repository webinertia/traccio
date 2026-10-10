# traccio Documentation (v1)

Tracy Debugger integration and profiler for [Mezzio](https://docs.mezzio.dev/) applications. Traccio
wires [Tracy](https://tracy.nette.org/) into a Mezzio middleware pipeline and adds debug bar panels
for configuration, routes, requests, and SQL query profiling.

Applies to traccio `1.0.x` and later.

## Contents

- [Getting Started](./getting-started.md): requirements, installation, and pipeline wiring.
- [Configuration](./configuration.md): every configuration option, panel requirements, and database
  profiling.

## How it works

`TracyDebuggerMiddleware` runs at the front of the pipeline, so Tracy is initialised before your
application handles the request. It reads the `debug` flag from application config and, when enabled:

1. Calls `Debugger::enable()` when an `enable` key is present.
2. Assigns every other key in the `Tracy\Debugger` config section to the matching static property on
   `Tracy\Debugger`.
3. Adds each panel to the Tracy bar when that panel's dependencies are present in the container.

Nothing is registered when `debug` is false, so no configuration has to be removed for production.
