<?php

declare(strict_types=1);

dataset('valid_json', [
    '{"name": "John", "age": 30, "city": "New York"}',
    '{"employees":["John", "Anna", "Peter"]}',
    '{"key": "value:value"}',
    '{"text": "The quick brown fox,"}',
    '{"text": "The quick brown fox won\'t jump"}',
    '{"key": ""}',
    '{"key1": {"key2": [1, 2, 3]}}',
    '{"key": 12345678901234567890}',
]);

dataset('booleans_and_null', [
    'capitalized True' => ['{"key": True}', true],
    'capitalized False' => ['{"key": False}', false],
    'capitalized None' => ['{"key": None}', null],
    'JSON true' => ['{"key": true}', true],
    'JSON false' => ['{"key": false}', false],
    'JSON null' => ['{"key": null}', null],
    'array with capitalized booleans' => ['[True, False, None]', [true, false, null]],
]);

dataset('numbers', [
    'positive integer' => ['{"key": 123}', 123],
    'negative integer' => ['{"key": -123}', -123],
    'decimal' => ['{"key": 123.456}', 123.456],
    'scientific notation' => ['{"key": 123e10}', 'validate_only'],
    'large integer' => ['{"key": 12345678901234567890}', 'validate_only'],
]);

dataset('parse_string', [
    'single quote' => ['"', ''],
    'newline only' => ["\n", ''],
    'space only' => [' ', ''],
    'plain string' => ['string', ''],
    'text before object' => ['stringbeforeobject {}', '{}'],
]);

dataset('standalone_booleans_null', [
    'standalone True' => ['True', ''],
    'standalone False' => ['False', ''],
    'standalone Null' => ['Null', ''],
    'standalone true' => ['true', 'true'],
    'standalone false' => ['false', 'false'],
    'standalone null' => ['null', 'null'],
]);
