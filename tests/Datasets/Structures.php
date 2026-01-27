<?php

declare(strict_types=1);

dataset('nested_structures', [
    '{"key1": {"key2": [1, 2, 3]}}',
    '{"employees":["John", "Anna", "Peter"]}',
]);

dataset('empty_structures', [
    'empty object' => ['{}', []],
    'empty array' => ['[]', []],
    'object with empty array' => [
        '{"key": []}', [
            'key' => [],
        ]],
    'object with empty object' => [
        '{"key": {}}', [
            'key' => [],
        ]],
]);

dataset('mixed_type_arrays', [
    'JSON booleans and null' => ['[1, "two", true, false, null]', [1, 'two', true, false, null]],
    'capitalized booleans and null' => ['[True, False, None, "string", 123]', [true, false, null, 'string', 123]],
]);
