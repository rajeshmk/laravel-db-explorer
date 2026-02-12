<?php

declare(strict_types=1);

namespace Hatchyu\DbExplorer\Models;

use Illuminate\Database\Eloquent\Model;

final class ColumnPresentation extends Model
{
    protected $table = 'db_explorer_column_presentations';

    protected $fillable = [
        'user_id',
        'database_name',
        'table_name',
        'column_name',
        'mysql_data_type',
        'presentation_type',
    ];
}
