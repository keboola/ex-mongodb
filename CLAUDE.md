# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PHP-based MongoDB extractor component for the Keboola platform. Exports data from MongoDB collections using the `mongoexport` CLI tool, parses the JSON output, and produces CSV files with manifest metadata. Runs as a Docker container.

**Namespace:** `MongoExtractor\` (PSR-4 from `src/`)

## Development Commands

All commands run inside Docker:

```bash
# Setup
cp .env.dist .env
docker compose build                    # On Apple Silicon: DOCKER_DEFAULT_PLATFORM=linux/amd64
docker compose run --rm dev composer install --no-scripts

# Full CI (validate + lint + phpcs + phpstan + all tests)
docker compose run --rm dev composer ci

# Individual checks
docker compose run --rm dev composer tests          # PHPUnit + functional tests
docker compose run --rm dev composer tests-phpunit   # Unit tests only
docker compose run --rm dev composer tests-datadir   # Functional tests only
docker compose run --rm dev composer phpstan          # Static analysis (level 8)
docker compose run --rm dev composer phpcs            # Code style check
docker compose run --rm dev composer phpcbf           # Auto-fix code style
docker compose run --rm dev composer phplint          # Syntax check

# Run a single test
docker compose run --rm dev phpunit --filter=TestClassName
docker compose run --rm dev phpunit --filter=testMethodName
docker compose run --rm dev phpunit tests/phpunit/ExportCommandFactoryTest.php
```

The `MONGODB_VERSION` env var (from `.env`) controls which MongoDB image version the test services use. CI runs a matrix across versions: `latest, 8.0, 6.0, 5.0, 4.4`.

## Architecture

### Data Flow

```
config.json → Component → Extractor → Export (mongoexport CLI) → Parse → CSV + manifest
```

1. **Component** (`src/Component.php`) — Entry point extending `BaseComponent`. Determines config format (old vs row-based), creates `Extractor`.
2. **Extractor** (`src/Extractor.php`) — Orchestrates extraction. Handles SSH tunnels, SSL files, connection testing, iterates over exports, manages incremental fetching state.
3. **Export** (`src/Export.php`) — Runs a single `mongoexport` command via `ExportCommandFactory`, decodes JSON output, handles retries with exponential backoff (5 attempts).
4. **Parse** (`src/Parse.php`) — Dispatches to the appropriate parser based on export mode.
5. **Parsers** (`src/Parser/`) — `Mapping` flattens nested MongoDB documents using csvmap; `Raw` exports documents as JSON strings.

### Key Supporting Classes

- **ExportCommandFactory** — Builds `mongoexport` shell commands from config params. Two protocol branches in `connectionOptions()`: `mongodb+srv` and `custom_uri` pass a single `--uri`; the default `mongodb://` path emits individual `--host`/`--port`/`--username`/`--password`/`--authenticationDatabase`/`--authenticationMechanism` flags (URI mode hangs against some servers — see comment in source). Any new auth-related option must be plumbed in both branches.
- **UriFactory / Uri** — Handles MongoDB URI construction including multi-host, `mongodb+srv://`, custom URI, and special character encoding via `league/uri`
- **ExportOptions** — Value object encapsulating all export parameters (collection, query, mode, mapping, incremental settings)
- **RelativeDateParser** — Converts relative date expressions (e.g., `"now - 7 days"`) in queries to absolute dates
- **Config** — Wraps raw JSON config; supports two formats: old (single `exports` array) and new (row-based, single export per row)

### Configuration Formats

The component supports two config shapes detected at runtime in `Component::getConfigDefinitionClass()`:
- **Old config** (`OldConfigDefinition`) — `parameters.exports[]` array with multiple exports
- **Row config** (`ConfigRowDefinition`) — Single export per config row (current standard)

### Connection Testing

`Extractor::testConnection()` uses `mongosh` (not `mongoexport`) to verify connectivity. This is because `mongoexport` requires a `--collection` parameter and always tries to export data — it can't just test a connection. The `testConnection` sync action (UI's "Test Connection" button) has no collection context, so it runs `db.runCommand({listCollections: 1})` via `mongosh` which is the canonical way to verify authentication and connectivity. `mongoexport` is the wrong tool here because you'd have to fake a collection name, then distinguish "collection not found" from "can't connect" in the error output.

### Incremental Fetching

Stores `lastFetchedRow` in state file between runs. Automatically builds `$gte` queries on the configured column. Supports date, numeric, and string column types with dot-notation paths for nested fields.

## Testing

- **Unit tests** (`tests/phpunit/`) — Test individual classes (URI factory, command factory, config definitions, date parsing)
- **Functional tests** (`tests/functional/`) — Data-directory based tests using `keboola/datadir-tests`. Each subdirectory is a test scenario with config input and expected output. `setUp.php` files handle test data setup.
- **Docker services for tests** — `mongodb` (plain), `mongodb-auth` (with auth), `mongodb-ssl` (with TLS), `node1.mongodb.cluster.local` (replica set for SRV testing), `sshproxy`, `dns.local` (dnsmasq for SRV records)

## Code Standards

- PHP 8.4, `declare(strict_types=1)` everywhere
- PHPStan level 8
- `keboola/coding-standard` for code style (phpcs/phpcbf)
- User-facing errors use `Keboola\Component\UserException`; system errors are uncaught `Throwable`
