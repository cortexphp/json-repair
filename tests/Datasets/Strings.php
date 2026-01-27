<?php

declare(strict_types=1);

dataset('special_characters', [
    'comma in string' => ['{"text": "The quick brown fox,"}', 'text', 'The quick brown fox,'],
    'apostrophe in string' => ['{"text": "The quick brown fox won\'t jump"}', 'text', "The quick brown fox won't jump"],
    'colon in string' => ['{"key": "value:value"}', 'key', 'value:value'],
]);

dataset('escape_sequences', [
    'newline' => ['{"key": "value\\nvalue"}', "value\nvalue"],
    'tab' => ['{"key": "value\\tvalue"}', "value\tvalue"],
    'escaped quote' => ['{"key": "value\\"value"}', 'value"value'],
    'backslash' => ['{"key": "value\\\\value"}', 'value\\value'],
    'carriage return' => ['{"key": "value\\rvalue"}', "value\rvalue"],
    'form feed' => ['{"key": "value\\fvalue"}', "value\fvalue"],
    'backspace' => ['{"key": "value\\bvalue"}', "value\x08value"],
    'forward slash' => ['{"key": "value\\/value"}', 'value/value'],
    'unicode escape' => ['{"key": "value\\u263avalue"}', 'value☺value'],
    'invalid unicode escape' => ['{"key": "value\\uXXYYvalue"}', 'value\\uXXYYvalue'],
    'invalid escape sequence' => ['{"key": "value\\xvalue"}', 'value\\xvalue'],
]);

dataset('advanced_escaping', [
    'mixed quote escaping with newlines' => [
        '{"key": \'string"\n\t\\le\'}',
        [
            'key' => "string\"\n\t\\le",
        ],
    ],
    'unicode escape sequences' => [
        '{"key": "\u0076\u0061\u006c\u0075\u0065"}',
        [
            'key' => 'value',
        ],
    ],
    'single quote in double-quoted string' => [
        '{"key": "valu\'e"}',
        [
            'key' => "valu'e",
        ],
    ],
    'nested JSON string' => [
        '{\'key\': "{\\"key\\": 1, \\"key2\\": 1}"}',
        [
            'key' => '{"key": 1, "key2": 1}',
        ],
    ],
    'newline in key' => [
        '{"key_1\n": "value"}',
        [
            "key_1\n" => 'value',
        ],
    ],
    'tab in key' => [
        '{"key\t_": "value"}',
        [
            "key\t_" => 'value',
        ],
    ],
]);

dataset('empty_strings', [
    'incomplete empty string' => [
        '{"key": ""', [
            'key' => '',
        ]],
    'complete with empty string' => [
        '{"key1": "", "key2": "value"}', [
            'key1' => '',
            'key2' => 'value',
        ]],
]);
