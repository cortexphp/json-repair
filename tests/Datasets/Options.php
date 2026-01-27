<?php

declare(strict_types=1);

dataset('omit_empty_values_true', [
    'missing value after colon' => [
        '{"key": }', [],
    ],
    'missing value with other keys' => [
        '{"key1": "v1", "key2": }', [
            'key1' => 'v1',
        ],
    ],
    'missing value at end' => [
        '{"name": "John", "age": ', [
            'name' => 'John',
        ],
    ],
    'key without colon' => [
        '{"key"', [],
    ],
    'multiple missing values' => [
        '{"key1": "v1", "key2": , "key3": "v3", "key4": }', [
            'key1' => 'v1',
            'key3' => 'v3',
        ],
    ],
    'nested object with missing value' => [
        '{"user": {"name": "John", "age": }}', [
            'user' => [
                'name' => 'John',
            ],
        ],
    ],
    'all values missing' => [
        '{"key1": , "key2": }', [],
    ],
]);

dataset('omit_empty_values_false', [
    'missing value after colon' => [
        '{"key": }', [
            'key' => '',
        ],
    ],
    'missing value with other keys' => [
        '{"key1": "v1", "key2": }', [
            'key1' => 'v1',
            'key2' => '',
        ],
    ],
]);

dataset('omit_incomplete_strings_true', [
    'cut off mid-string value' => [
        '{"name": "John", "description": "A person who', [
            'name' => 'John',
        ],
    ],
    'incomplete string at end' => [
        '{"key": "val', [],
    ],
    'multiple incomplete strings' => [
        '{"name": "John", "bio": "A developer who', [
            'name' => 'John',
        ],
    ],
    'complete and incomplete strings' => [
        '{"complete": "value", "incomplete": "partial', [
            'complete' => 'value',
        ],
    ],
    'nested object with incomplete string' => [
        '{"user": {"name": "John", "bio": "A person', [
            'user' => [
                'name' => 'John',
            ],
        ],
    ],
    'all strings incomplete' => [
        '{"key1": "val1', [],
    ],
]);

dataset('omit_incomplete_strings_false', [
    'cut off mid-string value' => [
        '{"name": "John", "description": "A person who', [
            'name' => 'John',
            'description' => 'A person who',
        ],
    ],
    'incomplete string at end' => [
        '{"key": "val', [
            'key' => 'val',
        ],
    ],
]);

dataset('combined_options', [
    'missing value and incomplete string' => [
        '{"name": "John", "age": , "bio": "A developer who', [
            'name' => 'John',
        ],
    ],
    'multiple issues' => [
        '{"key1": "v1", "key2": , "key3": "partial', [
            'key1' => 'v1',
        ],
    ],
    'all values problematic' => [
        '{"key1": , "key2": "incomplete', [],
    ],
]);
