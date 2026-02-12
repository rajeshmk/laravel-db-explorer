<?php

declare(strict_types=1);

namespace Hatchyu\DbExplorer\Support;

final class PresentationTypeResolver
{
    public const TYPE_TEXT = 'text';

    public const TYPE_TEXTAREA = 'textarea';

    public const TYPE_NUMBER = 'number';

    public const TYPE_COLOR = 'color';

    public const TYPE_DATE = 'date';

    public const TYPE_TIME = 'time';

    public const TYPE_DATETIME = 'datetime';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_SELECT = 'select';

    public const TYPE_FOREIGN_SELECT = 'foreign-select';

    /**
     * @return array<int, string>
     */
    public static function allowedTypes(): array
    {
        return [
            self::TYPE_TEXT,
            self::TYPE_TEXTAREA,
            self::TYPE_NUMBER,
            self::TYPE_COLOR,
            self::TYPE_DATE,
            self::TYPE_TIME,
            self::TYPE_DATETIME,
            self::TYPE_BOOLEAN,
            self::TYPE_SELECT,
            self::TYPE_FOREIGN_SELECT,
        ];
    }

    public static function detect(object $column, bool $isForeignKey = false): string
    {
        $dataType = strtolower((string) ($column->data_type ?? ''));
        $columnType = strtolower((string) ($column->column_type ?? ''));

        if ($isForeignKey) {
            return self::TYPE_FOREIGN_SELECT;
        }

        if ($dataType === 'enum') {
            return self::TYPE_SELECT;
        }

        $columnName = strtolower((string) ($column->column_name ?? ''));
        if (
            str_contains($columnName, 'color')
            && in_array($dataType, ['char', 'varchar', 'text', 'tinytext', 'mediumtext', 'longtext'], true)
        ) {
            return self::TYPE_COLOR;
        }

        if ($dataType === 'date') {
            return self::TYPE_DATE;
        }

        if ($dataType === 'time') {
            return self::TYPE_TIME;
        }

        if (in_array($dataType, ['datetime', 'timestamp'], true)) {
            return self::TYPE_DATETIME;
        }

        if (in_array($dataType, ['tinyint', 'boolean', 'bool'], true) && str_contains($columnType, '(1)')) {
            return self::TYPE_BOOLEAN;
        }

        if (in_array($dataType, ['int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'float', 'double'], true)) {
            return self::TYPE_NUMBER;
        }

        if (in_array($dataType, ['text', 'tinytext', 'mediumtext', 'longtext'], true)) {
            return self::TYPE_TEXTAREA;
        }

        return self::TYPE_TEXT;
    }

    /**
     * @return array<int, string>
     */
    public static function allowedForColumn(object $column, bool $isForeignKey = false): array
    {
        $dataType = strtolower((string) ($column->data_type ?? ''));
        $columnType = strtolower((string) ($column->column_type ?? ''));

        if ($isForeignKey) {
            return [
                self::TYPE_FOREIGN_SELECT,
                self::TYPE_SELECT,
                self::TYPE_NUMBER,
                self::TYPE_TEXT,
            ];
        }

        if ($dataType === 'enum') {
            return [self::TYPE_SELECT];
        }

        if ($dataType === 'date') {
            return [self::TYPE_DATE, self::TYPE_TEXT];
        }

        if ($dataType === 'time') {
            return [self::TYPE_TIME, self::TYPE_TEXT];
        }

        if (in_array($dataType, ['datetime', 'timestamp'], true)) {
            return [self::TYPE_DATETIME, self::TYPE_TEXT];
        }

        if (in_array($dataType, ['tinyint', 'boolean', 'bool'], true) && str_contains($columnType, '(1)')) {
            return [self::TYPE_BOOLEAN, self::TYPE_NUMBER, self::TYPE_TEXT];
        }

        if (in_array($dataType, ['int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'float', 'double'], true)) {
            return [self::TYPE_NUMBER, self::TYPE_TEXT, self::TYPE_SELECT];
        }

        if (in_array($dataType, ['text', 'tinytext', 'mediumtext', 'longtext', 'json'], true)) {
            if (str_contains(strtolower((string) ($column->column_name ?? '')), 'color')) {
                return [self::TYPE_COLOR, self::TYPE_TEXTAREA, self::TYPE_TEXT];
            }

            return [self::TYPE_TEXTAREA, self::TYPE_TEXT];
        }

        if (in_array($dataType, ['char', 'varchar'], true)) {
            if (str_contains(strtolower((string) ($column->column_name ?? '')), 'color')) {
                return [self::TYPE_COLOR, self::TYPE_TEXT, self::TYPE_TEXTAREA];
            }

            return [self::TYPE_TEXT, self::TYPE_TEXTAREA];
        }

        return [self::TYPE_TEXT];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::TYPE_TEXT => 'Text Input',
            self::TYPE_TEXTAREA => 'Textarea',
            self::TYPE_NUMBER => 'Number',
            self::TYPE_COLOR => 'Color Picker',
            self::TYPE_DATE => 'Date',
            self::TYPE_TIME => 'Time',
            self::TYPE_DATETIME => 'Datetime',
            self::TYPE_BOOLEAN => 'Boolean (Yes/No)',
            self::TYPE_SELECT => 'Dropdown',
            self::TYPE_FOREIGN_SELECT => 'Foreign Key Dropdown',
        ];
    }

    /**
     * @param array<int, string> $types
     *
     * @return array<int, array{value:string,label:string}>
     */
    public static function optionsForTypes(array $types): array
    {
        $labels = self::labels();

        return array_values(array_map(
            fn (string $type) => ['value' => $type, 'label' => $labels[$type] ?? $type],
            $types
        ));
    }
}
