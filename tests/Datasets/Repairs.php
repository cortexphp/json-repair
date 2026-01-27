<?php

declare(strict_types=1);

dataset('trailing_commas', [
    'object with trailing comma' => [
        '{"key": "value",}', [
            'key' => 'value',
        ]],
    'object with multiple keys and trailing comma' => [
        '{"key1": "v1", "key2": "v2",}', [
            'key1' => 'v1',
            'key2' => 'v2',
        ]],
    'array with trailing comma' => ['[1, 2, 3,]', [1, 2, 3]],
]);

dataset('missing_commas', [
    'object missing comma' => [
        '{"key1": "v1" "key2": "v2"}', [
            'key1' => 'v1',
            'key2' => 'v2',
        ]],
    'array missing commas' => ['["a" "b" "c"]', ['a', 'b', 'c']],
]);

dataset('missing_closing_brackets', [
    'object missing closing brace' => [
        '{"key": "value"', [
            'key' => 'value',
        ]],
    'array missing closing bracket' => ['["a", "b"', ['a', 'b']],
]);

dataset('missing_closing_braces', [
    'simple object' => ['{"key": "value"', 'key', 'value'],
    'nested object' => ['{"key1": {"key2": "value"', 'key1.key2', 'value'],
]);

dataset('missing_values', [
    'single missing value' => [
        '{"key": }', [
            'key' => '',
        ]],
    'multiple keys with missing value' => [
        '{"key1": "v1", "key2": }', [
            'key1' => 'v1',
            'key2' => '',
        ]],
]);
