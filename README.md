# MB Logger

PSR-3 compatible file logger with channel support (subdirectories), filename patterns, and filesystem abstraction via `mb4it/filesystem`.

## Installation

```bash
composer require mb4it/logger
```

Requirements:

- PHP 8.1+
- `psr/log` ^3.0
- `mb4it/filesystem` ^1.0

## Overview

- **Log path** is set when creating the logger (`basePath`).
- **Channel** (`channel('payments')`) is a logger that writes to a subdirectory or set of files under `basePath`.
- File operations use `mb4it/filesystem`.
- The logger implements `Psr\Log\LoggerInterface` (via `Psr\Log\AbstractLogger`).

---

## Basic usage (single `app.log` file)

```php
<?php

use MB\Logger\Logger;

$logger = new Logger(__DIR__ . '/storage/logs');

$logger->info('User logged in', ['user_id' => 123]);

// creates: storage/logs/app.log
```

- The first constructor argument is the **base path** to the log directory.
- The directory is created automatically.
- All levels (`info`, `error`, `debug`, …) are written to `app.log` by default.

### Quick start with `Logger::create(...)`

```php
<?php

use MB\Logger\Logger;

$logger = Logger::create(__DIR__ . '/storage/logs');
$logger->info('Quick start');

// storage/logs/app.log
```

You can pass a filename pattern:

```php
<?php

use MB\Logger\Logger;

$logger = Logger::create(__DIR__ . '/storage/logs', 'error_{date}.log');
$logger->error('Critical failure');

// storage/logs/error_YYYY-MM-DD.log
```

Driver selection in `create(...)`:

- If the pattern contains `{date}` → `daily` driver is used.
- Otherwise → `single` driver is used.

---

## Channels as subdirectories (`channel()`)

```php
<?php

use MB\Logger\Logger;

/** @var \Psr\Log\LoggerInterface $logger */
$logger = new Logger(__DIR__ . '/storage/logs');

// channel payments → storage/logs/payments
$paymentsLogger = $logger->channel('payments');
$paymentsLogger->info('Payment processed', ['order_id' => 10]);

// channel auth → storage/logs/auth
$authLogger = $logger->channel('auth');
$authLogger->warning('User failed login', ['email' => 'test@example.com']);
```

Resulting files:

- `storage/logs/app.log` — default channel.
- `storage/logs/payments/app.log` — channel `payments`.
- `storage/logs/auth/app.log` — channel `auth`.

---

## Advanced configuration: `LoggerConfig`

Use config for flexible setup (separate error files, patterns, etc.).

### `driver`: `single` vs `daily`

- **`single`** — one file per channel (no date-based rotation); writes to `filenamePattern` as-is.
- **`daily`** — date-based rotation. If `filenamePattern` already contains `{date}` (e.g. `{date}.log`, `error_{date}.log`), it is used as-is. If it does **not** contain `{date}` (e.g. `app.log`), the date is injected automatically before the extension → `app-YYYY-MM-DD.log`. This guarantees a new file per day regardless of the pattern.
- Both support `path`, `filenamePattern`, `dateFormat`, and `levels`.

### Example: error files by date `error_YYYY-MM-DD.log`

```php
<?php

use MB\Logger\Logger;
use MB\Logger\LoggerConfig;
use Psr\Log\LogLevel;

$config = LoggerConfig::fromArray([
    'basePath' => __DIR__ . '/storage/logs',
    'defaultChannel' => 'app',
    'channels' => [
        'app' => [
            'driver' => 'single',
            'path' => '',
            'filenamePattern' => 'app.log',
        ],
        'error' => [
            'driver' => 'daily',
            'path' => 'errors',
            'filenamePattern' => 'error_{date}.log',
            'dateFormat' => 'Y-m-d',
            'levels' => [
                LogLevel::ERROR,
                LogLevel::CRITICAL,
                LogLevel::ALERT,
                LogLevel::EMERGENCY,
            ],
        ],
    ],
]);

$logger = Logger::fromConfig($config);

$logger->info('Regular message');   // storage/logs/app.log
$logger->error('Something failed'); // storage/logs/errors/error_YYYY-MM-DD.log
```

- `{date}` is formatted using `dateFormat` (default `Y-m-d`).
- `levels` restricts which levels are written to that channel.

### Example: pattern `{level}_{date}.log`

```php
<?php

use MB\Logger\LoggerConfig;
use MB\Logger\Logger;

$config = LoggerConfig::fromArray([
    'basePath' => __DIR__ . '/storage/logs',
    'defaultChannel' => 'app',
    'channels' => [
        'by_level' => [
            'driver' => 'single',
            'path' => 'by-level',
            'filenamePattern' => '{level}_{date}.log',
            'dateFormat' => 'Y-m-d',
        ],
    ],
]);

$logger = Logger::fromConfig($config);

$logger->info('Info msg');   // storage/logs/by-level/info_YYYY-MM-DD.log
$logger->error('Error msg'); // storage/logs/by-level/error_YYYY-MM-DD.log
```

### Placeholders in `filenamePattern`

Available:

- `{date}` — formatted with channel `dateFormat`.
- `{level}` — level in lowercase (`error`, `info`, …).
- `{LEVEL}` — level in uppercase (`ERROR`, `INFO`, …).

---

## Logger factory (`LoggerFactory`)

Convenience methods:

- **`LoggerFactory::single($basePath, $filePattern = 'app.log', ...)`** — single file (driver: single).
- **`LoggerFactory::daily($basePath, $filePattern = '{date}.log', ...)`** — daily rotation (driver: daily).

```php
<?php

use MB\Logger\LoggerFactory;

$logger = LoggerFactory::single(__DIR__ . '/storage/logs', 'app.log');
$logger->info('Hello');

// Daily log to e.g. 2025-02-18.log
$dailyLogger = LoggerFactory::daily(__DIR__ . '/storage/logs');
$dailyLogger->info('Daily entry');
```

### Building from array (`fromArray`)

For complex config (multiple channels, levels, patterns):

```php
<?php

use MB\Logger\LoggerFactory;
use Psr\Log\LogLevel;

$logger = LoggerFactory::fromArray([
    'basePath' => __DIR__ . '/storage/logs',
    'defaultChannel' => 'app',
    'channels' => [
        'app' => [
            'driver' => 'single',
            'path' => '',
            'filenamePattern' => 'app.log',
        ],
        'error' => [
            'driver' => 'daily',
            'path' => 'errors',
            'filenamePattern' => 'error_{date}.log',
            'dateFormat' => 'Y-m-d',
            'levels' => [
                LogLevel::ERROR,
                LogLevel::CRITICAL,
            ],
        ],
    ],
]);
```

---

## Log format and PSR-3

- The logger implements `Psr\Log\LoggerInterface` (via `Psr\Log\AbstractLogger`), so it can be used by type.
- All standard methods are available: `emergency`, `alert`, `critical`, `error`, `warning`, `notice`, `info`, `debug`, `log`.
- Invalid levels throw `Psr\Log\InvalidArgumentException`.

Default line format (`MB\Logger\Formatter\LineFormatter`):

```text
[YYYY-mm-dd HH:ii:ss][LEVEL] message {"context_key":"value", ...}
```

Context:

- Placeholders like `{user_id}` in the message are replaced only for scalar and `Stringable` values.
- Context is appended as JSON; values are stringified (objects become `object(ClassName)`).

### Array format (ArrayFormatter)

For one-JSON-object-per-line (e.g. for parsing or streaming), use `ArrayFormatter`:

```php
<?php

use MB\Logger\Logger;
use MB\Logger\Formatter\ArrayFormatter;

$logger = Logger::create(__DIR__ . '/storage/logs', 'app.log', new ArrayFormatter());
$logger->info('User action', ['user_id' => 42]);
```

Each line in the file will look like:

```json
{"date":"2025-02-18 12:00:00","level":"INFO","message":"User action","context":{"user_id":"42"}}
```

---

## Filesystem (`mb4it/filesystem`)

The logger does not use raw `file_put_contents` or `mkdir`:

- An instance of `MB\Filesystem\Filesystem` is used with `basePath` from `LoggerConfig`.
- Logs are written via `updateContent($path, $updater, $atomic = false)`:
  - `atomic = false` is faster and sufficient for logs.
  - You can inject a custom `Filesystem` in `Logger::fromConfig()` or the `Logger` constructor.

---

## Tests

PHPUnit tests cover:

- Base log directory creation.
- Writing to default `app.log`.
- Channel subdirectories (`channel('payments')`).
- Filename patterns (`error_{date}.log`, `{level}_{date}.log`).
- Daily driver and date-based filenames.
- Level filtering (`levels` in channel config).
- PSR-3 compliance (levels, `InvalidArgumentException` for invalid level).

Run tests:

```bash
composer install
vendor/bin/phpunit
```
