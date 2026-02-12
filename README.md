# Laravel DB Explorer

> ⚠️ **IMPORTANT NOTICE**
> 
> **This entire package was built using AI tools: Codex, Antigravity, and GitHub Copilot.**
> 
> **No manual coding or manual testing was applied.**
> 
> **Coding standards may not be followed. Use with caution in production.**
> 
> Please review and test the code thoroughly before deploying to critical systems.

A modern database schema and data explorer for Laravel applications. This package provides a clean and intuitive UI to inspect your database tables, column metadata, browse records, and optionally perform write operations.

## Features

- **Table Overview**: Lists all database tables in a clean, card-based dashboard.
- **Table Metadata**: View column names, data types, nullability, and primary/foreign keys.
- **Records Data Grid Modes**:
  - `Raw` mode for read-only raw values.
  - `Editable` mode for formatted values + inline editing controls.
- **Inline Editing**: Edit records directly in the grid (with type-aware inputs).
- **Create / Edit / Delete**: Optional write operations with a configurable write mode.
- **Presentation Types**: Per-column UI control (text, textarea, number, date/time/datetime, boolean, dropdown, foreign key dropdown, color picker).
- **Foreign Key UX**: Searchable FK dropdowns and FK labels shown as `id - display`.
- **Search**: Fast keyword search across all searchable columns in a table.
- **Security-First**: Restricted by default to specific environments and middleware.

## Quick Start (Zero Config)

You can use this package without publishing any configuration or views. Just add this to your `.env` file:

```env
DB_EXPLORER_ENABLED=true
```

Then visit `/db-explorer` in your browser.

If you also want create/edit/delete actions:

```env
DB_EXPLORER_WRITE_ENABLED=true
```

## Installation

You can install the package via composer:

```bash
composer require hatchyu/laravel-db-explorer
```

The service provider will be automatically registered.

### Publishing Assets

Publish the configuration file and views (optional) using:

```bash
php artisan vendor:publish --provider="Hatchyu\DbExplorer\DbExplorerServiceProvider"
```

## Configuration

The configuration file is located at `config/db-explorer.php`.

```php
return [
    /*
     * Enable or disable the DB Explorer.
     */
    'enabled' => env('DB_EXPLORER_ENABLED', false),

    /*
     * List of environments where the DB Explorer is accessible.
     */
    'allowed_environments' => [
        'local',
    ],

    /*
     * Controls create/edit/delete actions.
     * If null, write mode is enabled only in local environment.
     */
    'write_enabled' => env('DB_EXPLORER_WRITE_ENABLED'),

    /*
     * Default records per page for the data browser.
     */
    'per_page' => 25,

    /*
     * Middleware applied to the DB Explorer routes.
     */
    'middleware' => ['web', 'auth'],
];
```

## Usage

Once installed and enabled, you can access the DB Explorer at:

`https://your-app.com/db-explorer`

### Security

The package includes an `EnsureDbExplorerIsAllowed` middleware that checks:
1. If the explorer is enabled via `DB_EXPLORER_ENABLED`.
2. If the current environment matches `allowed_environments`.

You should always keep `DB_EXPLORER_ENABLED=false` in production unless you have additional security layers.

### Write Mode

- `DB_EXPLORER_WRITE_ENABLED=true` enables create/edit/delete actions.
- `DB_EXPLORER_WRITE_ENABLED=false` forces read-only mode.
- If `DB_EXPLORER_WRITE_ENABLED` is not set, write mode is enabled only in `local` environment.

## Customization

If you want to customize the appearance, you can publish the Blade views:

```bash
php artisan vendor:publish --tag="db-explorer-views"
```

The views will be available in `resources/views/vendor/db-explorer`.

## Credits

- [Hatchyu Team](https://hatchyu.com)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
