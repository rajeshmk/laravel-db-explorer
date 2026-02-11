<?php

declare(strict_types=1);

namespace Hatchyu\DbExplorer\Support;

final class EnumValueExtractor
{
    /**
     * Extract enum labels from a MySQL column_type definition.
     *
     * Example input:
     * enum('authorization','global_settings','punch-in')
     *
     * Example output:
     * ['authorization', 'global_settings', 'punch-in']
     */
    public static function extractFromColumnType(?string $columnType): array
    {
        if (! is_string($columnType) || $columnType === '') {
            return [];
        }

        preg_match_all("/'((?:\\\\'|[^'])*)'/", $columnType, $matches);

        return $matches[1] ?? [];
    }
}
