# Feed CLI

A high-performance PHP command-line application for importing large feed files into a PostgreSQL database. Built on Symfony 8, it processes CSV data in memory-efficient batches using direct DBAL queries and upsert semantics — designed to handle millions of records reliably and fast.

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Command Reference](#command-reference)
- [Architecture](#architecture)
- [Extending the CLI](#extending-the-cli)
  - [Adding a New Importer](#adding-a-new-importer)
  - [Adding a New Feed Reader](#adding-a-new-feed-reader)
- [Project Structure](#project-structure)
- [Performance Notes](#performance-notes)
- [Sample Data](#sample-data)

---

## Features

- **Batch processing** — rows are grouped into configurable batches and inserted in a single SQL statement, minimising round-trips to the database
- **Memory-efficient streaming** — files are read lazily via PHP generators; memory usage stays constant regardless of file size
- **Upsert semantics** — uses PostgreSQL `ON CONFLICT DO UPDATE` so imports are idempotent and safe to re-run
- **Row-level validation** — each row is validated before insertion; invalid rows are skipped and reported without halting the import
- **Strategy pattern** — new importers and feed formats can be added without touching existing code
- **Performance reporting** — every run reports total rows processed, rows skipped, elapsed time, and peak memory usage

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | 8.4+ |
| Composer | 2.x |
| Docker & Docker Compose | any recent version |
| PostgreSQL | 16 (provided via Docker) |

---

## Installation

### 1. Clone the repository

```bash
git clone <repository-url>
cd feed-cli
```

### 2. Copy the environment file

```bash
cp .env.example .env
```

### 3. Start the database

```bash
docker compose up -d database
```

This starts a PostgreSQL 16 container on `127.0.0.1:5432`.

### 4. Install PHP dependencies

```bash
composer install
```

### 5. Run database migrations

```bash
php bin/console doctrine:migrations:migrate
```

You are now ready to import data.

---

## Configuration

All configuration lives in the `.env` file at the project root.

| Variable | Default | Description |
|---|---|---|
| `APP_ENV` | `dev` | Application environment (`dev` or `prod`) |
| `DATABASE_URL` | see below | Full DSN for the PostgreSQL connection |

**Default `DATABASE_URL`:**

```
postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8
```

Update the credentials if you change the Docker Compose defaults.

---

## Usage

### Basic import

```bash
php bin/console feed:import <target> <file> <format>
```

| Argument | Description | Example |
|---|---|---|
| `target` | The entity type to import into | `products` |
| `file` | Path to the feed file | `feed.csv` |
| `format` | Feed file format | `csv` |

### Import the included sample file

```bash
php bin/console feed:import products feed.csv csv
```

### Example output

```
Starting import for target "products" from "feed.csv" (format: csv)...

Import complete.
  Processed : 98
  Skipped   : 2
  Errors    : Row 14: price must be a valid number. Row 37: gtin must not be blank.
  Time      : 1.24s
  Memory    : 8.50 MB
```

---

## Command Reference

### `feed:import`

```
php bin/console feed:import <target> <file> <format>
```

**Arguments:**

| Name | Required | Description |
|---|---|---|
| `target` | Yes | Registered importer key (e.g. `products`) |
| `file` | Yes | Absolute or relative path to the feed file |
| `format` | Yes | Registered reader type (e.g. `csv`) |

The command exits with:
- `0` — import completed (with or without skipped rows)
- `1` — fatal error (unresolvable target, unreadable file, etc.)

---

## Architecture

Feed CLI is built around three independent extension points connected by a thin orchestration layer.

```
FeedCommand
    │
    ├── ImporterResolver ──────► ProductFeedImporter (extends BaseImporter)
    │                                   │
    │                                   ├── FeedReaderResolver ──► CsvFeedReader
    │                                   │       (yields rows)
    │                                   │
    │                                   ├── RowValidator ──────── ProductRowValidator
    │                                   │
    │                                   ├── RowMapper ──────────── ProductRowMapper
    │                                   │
    │                                   └── Repository ──────────── ProductRepository
    │                                                               (DBAL batch upsert)
    │
    └── ImportResult { processed, skipped, errors[] }
```

### Key design decisions

**`BaseImporter` — Template Method pattern**
All batch orchestration logic lives here: iterate rows, validate, map, accumulate, flush at the batch boundary. Concrete importers only declare their key and inject their collaborators.

**`ImporterResolver` / `FeedReaderResolver` — Strategy pattern**
Resolvers map string keys (`"products"`, `"csv"`) to implementations. Adding a new implementation never requires changing existing code.

**Direct DBAL — bypass ORM for bulk operations**
`ProductRepository` uses `doctrine/dbal` directly to build a single multi-row `INSERT ... ON CONFLICT DO UPDATE` statement per batch. This avoids Doctrine's Unit of Work overhead and is significantly faster for large imports.

**Generator-based file reading**
`CsvFeedReader::read()` is a PHP generator. The entire file is never loaded into memory — each row is yielded one at a time. This allows processing of files that are larger than available RAM.

---

## Extending the CLI

### Adding a New Importer

Use this when you need to import a new entity type (e.g. categories, orders, inventory).

**Step 1 — Create the importer class**

```php
// src/Categories/CategoryFeedImporter.php
namespace App\Categories;

use App\Importer\BaseImporter;

class CategoryFeedImporter extends BaseImporter
{
    public function key(): string
    {
        return 'categories';
    }
}
```

**Step 2 — Create the mapper**

```php
// src/Categories/CategoryRowMapper.php
namespace App\Categories;

use App\Importer\Contracts\RowMapperInterface;

class CategoryRowMapper implements RowMapperInterface
{
    public function map(array $row): array
    {
        return [
            'slug'  => strtolower(trim($row['slug'])),
            'label' => trim($row['label']),
        ];
    }
}
```

**Step 3 — Create the validator**

```php
// src/Categories/CategoryRowValidator.php
namespace App\Categories;

use App\Importer\Contracts\RowValidatorInterface;

class CategoryRowValidator implements RowValidatorInterface
{
    private array $errors = [];

    public function validate(array $row): bool
    {
        $this->errors = [];
        if (empty($row['slug'])) {
            $this->errors[] = 'slug must not be blank';
        }
        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
```

**Step 4 — Create the repository**

```php
// src/Categories/CategoryRepository.php
namespace App\Categories;

use App\Importer\Contracts\RepositoryInterface;
use Doctrine\DBAL\Connection;

class CategoryRepository implements RepositoryInterface
{
    public function __construct(private readonly Connection $connection) {}

    public function saveBatch(array $rows): void
    {
        // Build and execute INSERT ... ON CONFLICT statement
    }
}
```

**Step 5 — Register in `config/services.yaml`**

```yaml
App\Importer\ImporterResolver:
    arguments:
        $importers:
            products:   '@App\Products\ProductFeedImporter'
            categories: '@App\Categories\CategoryFeedImporter'   # add this line

App\Categories\CategoryFeedImporter:
    bind:
        App\Importer\Contracts\RowMapperInterface:  '@App\Categories\CategoryRowMapper'
        App\Importer\Contracts\RowValidatorInterface: '@App\Categories\CategoryRowValidator'
        App\Importer\Contracts\RepositoryInterface:  '@App\Categories\CategoryRepository'
```

**Run it:**

```bash
php bin/console feed:import categories categories.csv csv
```

---

### Adding a New Feed Reader

Use this when you need to support a new file format (e.g. JSON, XML, TSV).

**Step 1 — Implement `FeedReaderInterface`**

```php
// src/Importer/JsonFeedReader.php
namespace App\Importer;

use App\Importer\Contracts\FeedReaderInterface;

class JsonFeedReader implements FeedReaderInterface
{
    public function type(): string
    {
        return 'json';
    }

    public function read(string $path): iterable
    {
        $handle = fopen($path, 'r');
        // stream and decode line-delimited JSON (NDJSON)
        while (($line = fgets($handle)) !== false) {
            yield json_decode(trim($line), true);
        }
        fclose($handle);
    }
}
```

**Step 2 — Register in `config/services.yaml`**

```yaml
App\Importer\FeedReaderResolver:
    arguments:
        $readers:
            csv:  '@App\Importer\CsvFeedReader'
            json: '@App\Importer\JsonFeedReader'   # add this line
```

**Run it:**

```bash
php bin/console feed:import products products.json json
```

---

## Project Structure

```
feed-cli/
├── bin/
│   └── console                        # CLI entry point
├── config/
│   ├── packages/
│   │   ├── doctrine.yaml              # ORM & DBAL configuration
│   │   ├── framework.yaml
│   │   └── validator.yaml
│   └── services.yaml                  # Dependency injection wiring
├── migrations/
│   └── Version20260205142840.php      # Creates the product table
├── src/
│   ├── Command/
│   │   └── FeedCommand.php            # Console command (feed:import)
│   ├── Importer/
│   │   ├── Contracts/
│   │   │   ├── FeedReaderInterface.php
│   │   │   ├── ImporterInterface.php
│   │   │   ├── RepositoryInterface.php
│   │   │   ├── RowMapperInterface.php
│   │   │   └── RowValidatorInterface.php
│   │   ├── BaseImporter.php           # Batch orchestration (Template Method)
│   │   ├── CsvFeedReader.php          # CSV parser (generator-based)
│   │   ├── FeedReaderResolver.php     # Resolves format → reader
│   │   ├── ImporterResolver.php       # Resolves target → importer
│   │   └── ImportResult.php           # Value object for import statistics
│   └── Products/
│       ├── ProductFeedImporter.php    # Products importer
│       ├── ProductRepository.php      # DBAL batch upsert
│       ├── ProductRowMapper.php       # Row transformation
│       └── ProductRowValidator.php    # Row validation (Symfony Validator)
├── compose.yaml                       # PostgreSQL 16 Docker service
├── feed.csv                           # Sample CSV (100 products)
└── .env                               # Environment configuration
```

---

## Performance Notes

The following design choices keep import time low even at scale:

- **Batch inserts** — rows are accumulated in memory and flushed as a single `INSERT` with multiple value tuples. The default batch size is 1 000 rows per statement.
- **DBAL over ORM** — Doctrine's entity manager and Unit of Work are bypassed entirely for insert operations, eliminating per-object overhead.
- **Upsert without pre-checks** — `ON CONFLICT (gtin) DO UPDATE` handles duplicates at the database level. No `SELECT` is issued before each insert.
- **Generator streaming** — `CsvFeedReader` yields one row at a time. Peak memory is proportional to batch size, not file size.

### Benchmarks (approximate, single process)

| Rows | File size | Time |
|---|---|---|
| 10 000 | ~1 MB | ~1s |
| 100 000 | ~10 MB | ~8s |
| 1 000 000 | ~100 MB | ~80s |

> Times measured on a local machine with PostgreSQL running in Docker. Results vary based on hardware, network latency to the database, and row complexity.

---

## Sample Data

A sample CSV file `feed.csv` is included in the project root. It contains 100 product rows with the following columns:

| Column | Type | Description |
|---|---|---|
| `gtin` | string | Global Trade Item Number (unique identifier) |
| `language` | string | ISO 639-1 language code (`en`, `de`, `fr`, `it`, `es`) |
| `title` | string | Product name |
| `picture` | string | URL to product image |
| `description` | string | Short product description |
| `price` | decimal | Unit price |
| `stock` | integer | Available stock quantity |

```csv
gtin,language,title,picture,description,price,stock
7034621736823,it,Product Title,https://example.com/img.jpg,Short description,199.99,42
...
```
