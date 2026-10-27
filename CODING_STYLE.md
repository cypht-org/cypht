# Cypht Coding Style Guide

This guide describes conventions used in the Cypht codebase. Cypht has evolved
over time, so not every existing file follows one uniform style. Apply the
recommendations in this guide when writing new code or modifying existing code.
Keep changes focused and avoid reformatting unrelated lines.

## Indentation and formatting

- PHP and JavaScript are generally indented with four spaces per logical level.
- CSS uses two spaces per level in the main site stylesheet.
- Put opening braces on the same line as the declaration or control structure.

```php
if ($config->get('disable_ini_settings')) {
    return;
}
```

- Use one statement per line and leave blank lines between logically distinct
  steps.
- Break long expressions into named intermediate values when that makes the
  logic easier to follow. Do not wrap code only to enforce an arbitrary line
  length.

## Comments and documentation

- Add comments to explain why non-obvious code is needed, not to restate what
  the code already makes clear.
- Use PHPDoc for public or non-obvious classes and methods when parameter,
  return, or behavior details are useful to readers.
- Keep comments accurate when changing the behavior they describe.

## Naming

- Use camelCase for variable and function names.
- Use descriptive names for variables and functions. Short names are suitable
  for small, local scopes such as loop counters.
- PHP constants are commonly written in `UPPER_CASE`.

```php
class Hm_Site_Config_File {
    public function getDefaultTimezone() {
        // ...
    }
}
```

## PHP

- The project supports PHP 8.1 and later. See [composer.json](composer.json).
- Use `<?php` as the PHP opening tag. Do not add a closing `?>` to PHP-only
  files.
- Use single-quoted strings when interpolation is not needed.
- Although the codebase contains arrays initialized with `array()`, always
  prefer the newer `[]` syntax.
- Preserve existing public names and interfaces unless the change requires
  updating them.

```php
$filters = [
    'allowed_get' => [
        'contact_id' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
    ],
];
```

## Arrays and configuration

- Use associative arrays for named configuration and structured data. Keep
  related entries together and use trailing commas in multiline arrays.
- Follow the existing module setup and registration patterns when adding module
  behavior.
- Check the expected shape and defaults of configuration values before using
  them.

## Functions and control flow

- Validate inputs near the boundary and return early when that keeps the main
  path clear.
- Keep functions focused. Extract a helper when it gives a meaningful name to
  repeated or complicated logic.
- Use braces for control structures, even when their body contains a single statement.
- Handle errors explicitly. Do not hide failures behind broad catches or
  success-shaped fallback values.

## Request data and output

- Treat request data as untrusted. Cypht module setup defines allowlisted and
  filtered input fields; update those definitions when introducing request
  parameters.
- Keep output within the module's declared output allowlist and escape data
  appropriately for its output context, especially when generating HTML.
- Use the existing translation mechanisms for user-facing text where applicable.

## JavaScript

- End statements with semicolons.
- Use camelCase for variable and function names.
- Use `const` for bindings that are not reassigned and `let` when reassignment
  is needed. Avoid introducing globals; place shared behavior in the
  appropriate module or existing shared namespace.

### File organization and routing

- Put module-specific JavaScript in that module's `js_modules/` directory.
  Group shared helpers into descriptive subdirectories such as `utils/`,
  `actions/`, or `markup/`.
- Treat a module's `site.js` as a legacy location for its full JavaScript
  implementation. Put new code, and code separated during refactoring, in
  focused files under `js_modules/`, organized by responsibility as needed.
- Put page-specific setup in `route_handlers.js`. The router looks up handler
  functions by name on `window`, so declare route handlers at the top level
  using the `apply<Page>PageHandlers` naming pattern.
- Add or update the matching page entry in
  `modules/core/navigation/routes.js` when wiring a route. The handler receives
  route parameters and an optional URL hash, and may return a cleanup function,
  which the SPA router calls when leaving the page. Use cleanup to release
  resources such as timers or event listeners created by the handler.

## CSS

- Follow the surrounding stylesheet's indentation and organization.
- Prefer existing theme variables and established component classes over
  hard-coded colors or duplicate styling.
- Keep selectors scoped to the relevant module or component where practical.

## Tests

- Add or update PHPUnit tests for PHP behavior changes. Name test classes
  `Hm_Test_*` and test methods `test_*`.
