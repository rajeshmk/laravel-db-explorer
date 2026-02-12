<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('db_explorer_column_presentations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('database_name', 191);
            $table->string('table_name', 191);
            $table->string('column_name', 191);
            $table->string('mysql_data_type', 64)->nullable();
            $table->string('presentation_type', 64);
            $table->timestamps();

            $table->unique(['user_id', 'database_name', 'table_name', 'column_name'], 'dbx_col_presentation_unique');
            $table->index(['database_name', 'table_name'], 'dbx_col_presentation_table_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('db_explorer_column_presentations');
    }
};
