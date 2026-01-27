<?php

declare(strict_types=1);

dataset('single_quotes_to_double', [
    'single key-value' => [
        "{'key': 'value'}", [
            'key' => 'value',
        ]],
    'multiple key-values' => [
        "{'name': 'John', 'age': 30}", [
            'name' => 'John',
            'age' => 30,
        ]],
]);

dataset('unquoted_keys', [
    'single unquoted key' => [
        '{key: "value"}', [
            'key' => 'value',
        ]],
    'multiple unquoted keys' => [
        '{name: "John", age: 30}', [
            'name' => 'John',
            'age' => 30,
        ]],
]);

dataset('mixed_quotes', [
    'mixed single and double quotes' => [
        "{'key': 'string', 'key2': false, \"key3\": null, \"key4\": unquoted}",
        [
            'key' => 'string',
            'key2' => false,
            'key3' => null,
            'key4' => 'unquoted',
        ],
    ],
    'unquoted value in middle' => [
        '{"name": "John", "age": 30, "city": New York}',
        [
            'name' => 'John',
            'age' => 30,
            'city' => 'New York',
        ],
    ],
    'unquoted value at start' => [
        '{"name": John, "age": 30, "city": "New York"}',
        [
            'name' => 'John',
            'age' => 30,
            'city' => 'New York',
        ],
    ],
    'slanted delimiters' => [
        '{""slanted_delimiter"": "value"}',
        [
            'slanted_delimiter' => 'value',
        ],
    ],
    'double quotes inside string value' => [
        '{"key": ""value"}',
        [
            'key' => 'value',
        ],
    ],
    'numeric key' => [
        '{"key": "value", 5: "value"}',
        [
            'key' => 'value',
            '5' => 'value',
        ],
    ],
    'empty key' => [
        '{"" key":"val"}',
        [
            ' key' => 'val',
        ],
    ],
    'unquoted value before quoted key' => [
        '{"key": value "key2" : "value2"}',
        [
            'key' => 'value',
            'key2' => 'value2',
        ],
    ],
    'trailing comma and space' => [
        '{"key": value , }',
        [
            'key' => 'value',
        ],
    ],
]);

dataset('quotes_inside_strings', [
    'quotes inside string with comma' => [
        '{"key": "lorem ipsum ... "sic " tamet. ...}',
        [
            'key' => 'lorem ipsum ... "sic " tamet. ...',
        ],
    ],
    'quotes inside string with comma and text' => [
        '{"comment": "lorem, "ipsum" sic "tamet". To improve"}',
        [
            'comment' => 'lorem, "ipsum" sic "tamet". To improve',
        ],
    ],
    'quotes splitting value' => [
        '{"key": "v"alu"e"}',
        [
            'key' => 'v"alu"e',
        ],
    ],
    'quotes splitting value with comma' => [
        '{"key": "v"alue", "key2": "value2"}',
        [
            'key' => 'v"alue',
            'key2' => 'value2',
        ],
    ],
    'quotes splitting value in array' => [
        '[{"key": "v"alu,e", "key2": "value2"}]',
        [
            [
                'key' => 'v"alu,e',
                'key2' => 'value2',
            ],
        ],
    ],
]);
