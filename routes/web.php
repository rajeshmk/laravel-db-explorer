<?php

declare(strict_types=1);

use Hatchyu\DbExplorer\Http\Controllers\ExplorerController;
use Hatchyu\DbExplorer\Http\Middleware\EnsureDbExplorerIsAllowed;
use Illuminate\Support\Facades\Route;

Route::middleware(array_merge(
    config('db-explorer.middleware'),
    [EnsureDbExplorerIsAllowed::class]
))
    ->prefix('db-explorer')
    ->group(function () {
        Route::get('/', [ExplorerController::class, 'index'])->name('db-explorer.index');
        Route::get('/schema', [ExplorerController::class, 'schema'])->name('db-explorer.schema');
        Route::get('/table/{table}', [ExplorerController::class, 'table'])->name('db-explorer.table');
        Route::get('/table/{table}/records', [ExplorerController::class, 'table'])->name('db-explorer.table.records');
        Route::get('/table/{table}/schema', [ExplorerController::class, 'table'])->name('db-explorer.table.schema');
        Route::get('/table/{table}/record/{id}', [ExplorerController::class, 'record'])->name('db-explorer.record');
    })
;
