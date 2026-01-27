<?php

declare(strict_types=1);

dataset('incomplete_json', [
    'incomplete string value' => [
        '{"key": "val', [
            'key' => 'val',
        ]],
    'missing value' => [
        '{"key": ', [
            'key' => '',
        ]],
    'incomplete array' => ['["a", "b', ['a', 'b']],
]);

dataset('streaming_llm_responses', [
    'cut off mid-string value' => [
        '{"name": "John", "description": "A person who', [
            'name' => 'John',
            'description' => 'A person who',
        ]],
    'cut off mid-number' => [
        '{"count": 123', [
            'count' => 123,
        ]],
    'cut off mid-decimal' => [
        '{"price": 99.9', [
            'price' => 99.9,
        ]],
    'cut off mid-boolean' => [
        '{"active": tru', [
            'active' => '',
        ]],
    'cut off after colon' => [
        '{"name": "John", "age": ', [
            'name' => 'John',
            'age' => '',
        ]],
    'cut off mid-key' => [
        '{"name": "John", "user', [
            'name' => 'John',
            'user' => '',
        ]],
    'cut off mid-object' => [
        '{"user": {"name": "John", "age": 30', [
            'user' => [
                'name' => 'John',
                'age' => 30,
            ],
        ]],
    'cut off mid-nested-object' => [
        '{"data": {"user": {"name": "John", "profile": {"bio": "Developer"', [
            'data' => [
                'user' => [
                    'name' => 'John',
                    'profile' => [
                        'bio' => 'Developer',
                    ],
                ],
            ],
        ]],
    'cut off mid-array' => [
        '{"items": [1, 2, 3', [
            'items' => [1, 2, 3],
        ]],
    'cut off mid-array-with-objects' => [
        '{"users": [{"name": "John"}, {"name": "Jane"', [
            'users' => [
                [
                    'name' => 'John',
                ],
                [
                    'name' => 'Jane',
                ],
            ],
        ]],
    'cut off mid-string-in-array' => [
        '{"tags": ["php", "json", "repair"', [
            'tags' => ['php', 'json', 'repair'],
        ]],
    'cut off after comma' => [
        '{"name": "John", "age": 30, ', [
            'name' => 'John',
            'age' => 30,
        ]],
    'cut off mid-escape-sequence' => [
        '{"message": "Hello\\', [
            'message' => 'Hello',
        ]],
    'cut off mid-unicode-escape' => [
        '{"emoji": "\\u263a', [
            'emoji' => '\\u263a263a',
        ]],
    'multiple-incomplete-values' => [
        '{"name": "John", "age": 30, "bio": "A developer who loves', [
            'name' => 'John',
            'age' => 30,
            'bio' => 'A developer who loves',
        ]],
    'cut off mid-null' => [
        '{"value": nul', [
            'value' => '',
        ]],
    'cut off mid-false' => [
        '{"enabled": fals', [
            'enabled' => '',
        ]],
    'cut off mid-true' => [
        '{"active": tr', [
            'active' => '',
        ]],
    'cut off with-trailing-comma-before-incomplete' => [
        '{"name": "John", "age": 30, "bio": "A', [
            'name' => 'John',
            'age' => 30,
            'bio' => 'A',
        ]],
    'cut off mid-nested-array' => [
        '{"matrix": [[1, 2], [3, 4', [
            'matrix' => [
                [1, 2],
                [3, 4],
            ],
        ]],
    'cut off with-mixed-complete-and-incomplete' => [
        '{"complete": "value", "incomplete": "partial', [
            'complete' => 'value',
            'incomplete' => 'partial',
        ]],
]);

dataset('multiple_json_objects', [
    'empty array and object' => ['[]{}', null, null],
    'array then object' => ['[]{"key":"value"}', 'key', 'value'],
    'object then array' => ['{"key":"value"}[1,2,3,True]', null, null],
]);

dataset('markdown_code_blocks', [
    'single code block' => ['lorem ```json {"key":"value"} ``` ipsum', 'key', 'value'],
    'multiple code blocks' => ['```json {"key":"value"} ``` ```json [1,2,3,True] ```', null, null],
]);

dataset('markdown_links', [
    'markdown link in string' => [
        '{ "content": "[LINK]("https://google.com")" }',
        [
            'content' => '[LINK](',
            'https' => ')',
        ],
    ],
    'incomplete markdown link' => [
        '{ "content": "[LINK](" }',
        [
            'content' => '[LINK](',
        ],
    ],
    'incomplete markdown link with other keys' => [
        '{ "content": "[LINK](", "key": true }',
        [
            'content' => '[LINK](',
            'key' => true,
        ],
    ],
]);

dataset('leading_trailing_characters', [
    'multiple backticks' => [
        '````{ "key": "value" }```',
        [
            'key' => 'value',
        ],
    ],
    'trailing backticks with newlines' => [
        "{    \"a\": \"\",    \"b\": [ { \"c\": 1} ] \n}```",
        [
            'a' => '',
            'b' => [
                [
                    'c' => 1,
                ],
            ],
        ],
    ],
    'text before markdown code block' => [
        "Based on the information extracted, here is the filled JSON output: ```json { 'a': 'b' } ```",
        [
            'a' => 'b',
        ],
    ],
    'multiline text before code block' => [
        "
                       The next 64 elements are:
                       ```json
                       { \"key\": \"value\" }
                       ```",
        [
            'key' => 'value',
        ],
    ],
]);

dataset('json_in_strings', [
    'backticks in string value' => [
        '{"key": "``"}',
        [
            'key' => '``',
        ],
    ],
    'json code block in string' => [
        '{"key": "```json"}',
        [
            'key' => '```json',
        ],
    ],
    'nested JSON code block in string' => [
        '{"key": "```json {"key": [{"key1": 1},{"key2": 2}]}```"}',
        [
            'key' => [
                [
                    'key1' => 1,
                ],
                [
                    'key2' => 2,
                ],
            ],
        ],
    ],
    'incomplete JSON code block in string' => [
        '{"response": "```json{}"}',
        [
            'response' => '```json{}',
        ],
    ],
]);
