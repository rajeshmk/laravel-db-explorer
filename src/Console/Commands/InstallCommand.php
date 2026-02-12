<?php

declare(strict_types=1);

namespace Hatchyu\DbExplorer\Console\Commands;

use Illuminate\Console\Command;

final class InstallCommand extends Command
{
    protected $signature = 'db-explorer:install
        {--force : Overwrite published files}
        {--skip-migrate : Skip running migrations even when write mode is enabled}
        {--with-views : Publish package views}
        {--with-config : Publish package config}';

    protected $description = 'Install Laravel DB Explorer by publishing assets and setup files';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $this->ensureExplorerEnabledEnvFlag();

        $this->info('Publishing explorer assets...');
        $this->publishTag('db-explorer-assets', $force);

        if ((bool) $this->option('with-config')) {
            $this->info('Publishing config...');
            $this->publishTag('db-explorer-config', $force);
        }

        if ((bool) $this->option('with-views')) {
            $this->info('Publishing views...');
            $this->publishTag('db-explorer-views', $force);
        }

        if ($this->isWriteModeEnabled()) {
            $this->info('Write mode is enabled; running migrations...');
            if (! (bool) $this->option('skip-migrate')) {
                $this->call('migrate', ['--force' => true]);
            } else {
                $this->warn('Skipped migrations due to --skip-migrate option.');
            }
        } else {
            $this->line('Write mode is disabled. Skipping migrate step.');
        }

        $this->newLine();
        $this->info('DB Explorer installation completed.');

        return self::SUCCESS;
    }

    private function publishTag(string $tag, bool $force): void
    {
        $this->call('vendor:publish', [
            '--tag' => $tag,
            '--force' => $force,
        ]);
    }

    private function isWriteModeEnabled(): bool
    {
        $configured = config('db-explorer.write_enabled');
        if ($configured === null) {
            return app()->environment('local');
        }

        return (bool) $configured;
    }

    private function ensureExplorerEnabledEnvFlag(): void
    {
        $envPath = base_path('.env');
        if (! is_file($envPath)) {
            $this->warn('No .env file found; skipping DB_EXPLORER_ENABLED insertion.');
            return;
        }

        $contents = (string) file_get_contents($envPath);
        if (preg_match('/^\s*DB_EXPLORER_ENABLED\s*=/m', $contents) === 1) {
            return;
        }

        $separator = str_ends_with($contents, PHP_EOL) || $contents === '' ? '' : PHP_EOL;
        $appended = $contents . $separator . 'DB_EXPLORER_ENABLED=true' . PHP_EOL;
        file_put_contents($envPath, $appended);

        $this->info('Added DB_EXPLORER_ENABLED=true to .env');
    }
}
