<?php

declare(strict_types=1);

namespace Hatchyu\DbExplorer\Console\Commands;

use Illuminate\Console\Command;

final class UpdateCommand extends Command
{
    protected $signature = 'db-explorer:update
        {--skip-migrate : Skip running migrations even when write mode is enabled}
        {--with-views : Republish package views with force}
        {--with-config : Republish package config with force}';

    protected $description = 'Update Laravel DB Explorer assets and setup files';

    public function handle(): int
    {
        $this->info('Republishing explorer assets...');
        $this->publishTag('db-explorer-assets', true);

        if ((bool) $this->option('with-config')) {
            $this->info('Republishing config...');
            $this->publishTag('db-explorer-config', true);
        }

        if ((bool) $this->option('with-views')) {
            $this->info('Republishing views...');
            $this->publishTag('db-explorer-views', true);
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
        $this->info('DB Explorer update completed.');

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
}
