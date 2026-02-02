<?php

declare(strict_types=1);

// ============================================================================
// VALID JSON (passthrough tests)
// ============================================================================

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

dataset('nested_structures', [
    '{"key1": {"key2": [1, 2, 3]}}',
    '{"employees":["John", "Anna", "Peter"]}',
]);

// ============================================================================
// SYNTAX ERRORS
// ============================================================================

dataset('trailing_commas', [
    'object with trailing comma' => [
        '{"key": "value",}',
        '{"key": "value"}',
    ],
    'object with multiple keys and trailing comma' => [
        '{"key1": "v1", "key2": "v2",}',
        '{"key1": "v1", "key2": "v2"}',
    ],
    'array with trailing comma' => [
        '[1, 2, 3,]',
        '[1, 2, 3]',
    ],
]);

dataset('missing_commas', [
    'object missing comma' => [
        '{"key1": "v1" "key2": "v2"}',
        '{"key1": "v1","key2": "v2"}',
    ],
    'array missing commas' => [
        '["a" "b" "c"]',
        '["a","b","c"]',
    ],
]);

dataset('missing_closing_brackets', [
    'object missing closing brace' => [
        '{"key": "value"',
        '{"key": "value"}',
    ],
    'array missing closing bracket' => [
        '["a", "b"',
        '["a", "b"]',
    ],
]);

dataset('missing_closing_braces', [
    'simple object' => ['{"key": "value"', 'key', 'value'],
    'nested object' => ['{"key1": {"key2": "value"', 'key1.key2', 'value'],
]);

dataset('missing_values', [
    'single missing value' => [
        '{"key": }',
        '{"key":""}',
    ],
    'multiple keys with missing value' => [
        '{"key1": "v1", "key2": }',
        '{"key1": "v1", "key2":""}',
    ],
]);

// ============================================================================
// QUOTES
// ============================================================================

dataset('single_quotes_to_double', [
    'single key-value' => [
        "{'key': 'value'}",
        '{"key": "value"}',
    ],
    'multiple key-values' => [
        "{'name': 'John', 'age': 30}",
        '{"name": "John", "age": 30}',
    ],
]);

dataset('unquoted_keys', [
    'single unquoted key' => [
        '{key: "value"}',
        '{"key": "value"}',
    ],
    'multiple unquoted keys' => [
        '{name: "John", age: 30}',
        '{"name": "John", "age": 30}',
    ],
]);

dataset('mixed_quotes', [
    'mixed single and double quotes' => [
        "{'key': 'string', 'key2': false, \"key3\": null, \"key4\": unquoted}",
        '{"key": "string", "key2": false, "key3": null, "key4": "unquoted"}',
    ],
    'unquoted value in middle' => [
        '{"name": "John", "age": 30, "city": New York}',
        '{"name": "John", "age": 30, "city": "New York"}',
    ],
    'unquoted value at start' => [
        '{"name": John, "age": 30, "city": "New York"}',
        '{"name": "John", "age": 30, "city": "New York"}',
    ],
    'slanted delimiters' => [
        '{""slanted_delimiter"": "value"}',
        '{"slanted_delimiter": "value"}',
    ],
    'double quotes inside string value' => [
        '{"key": ""value"}',
        '{"key": "value"}',
    ],
    'numeric key' => [
        '{"key": "value", 5: "value"}',
        '{"key": "value", "5": "value"}',
    ],
    'empty key' => [
        '{"" key":"val"}',
        '{" key":"val"}',
    ],
    'unquoted value before quoted key' => [
        '{"key": value "key2" : "value2"}',
        '{"key": "value", "key2": "value2"}',
    ],
    'trailing comma and space' => [
        '{"key": value , }',
        '{"key": "value"}',
    ],
]);

dataset('quotes_inside_strings', [
    'quotes inside string with comma' => [
        '{"key": "lorem ipsum ... "sic " tamet. ...}',
        '{"key": "lorem ipsum ... \\"sic \\" tamet. ..."}',
    ],
    'quotes inside string with comma and text' => [
        '{"comment": "lorem, "ipsum" sic "tamet". To improve"}',
        '{"comment": "lorem, \\"ipsum\\" sic \\"tamet\\". To improve"}',
    ],
    'quotes splitting value' => [
        '{"key": "v"alu"e"}',
        '{"key": "v\\"alu\\"e"}',
    ],
    'quotes splitting value with comma' => [
        '{"key": "v"alue", "key2": "value2"}',
        '{"key": "v\\"alue", "key2": "value2"}',
    ],
    'quotes splitting value in array' => [
        '[{"key": "v"alu,e", "key2": "value2"}]',
        '[{"key": "v\\"alu,e", "key2": "value2"}]',
    ],
]);

// ============================================================================
// INCOMPLETE JSON (streaming, cut-off)
// ============================================================================

dataset('incomplete_json', [
    'incomplete string value' => [
        '{"key": "val',
        '{"key": "val"}',
    ],
    'missing value' => [
        '{"key": ',
        '{"key":""}',
    ],
    'incomplete array' => [
        '["a", "b',
        '["a", "b"]',
    ],
]);

dataset('streaming_llm_responses', [
    'cut off mid-string value' => [
        '{"name": "John", "description": "A person who',
        '{"name": "John", "description": "A person who"}',
    ],
    'cut off mid-number' => [
        '{"count": 123',
        '{"count": 123}',
    ],
    'cut off mid-decimal' => [
        '{"price": 99.9',
        '{"price": 99.9}',
    ],
    'cut off mid-boolean' => [
        '{"active": tru',
        '{"active": ""}',
    ],
    'cut off after colon' => [
        '{"name": "John", "age": ',
        '{"name": "John", "age":""}',
    ],
    'cut off mid-key' => [
        '{"name": "John", "user',
        '{"name": "John", "user":""}',
    ],
    'cut off mid-object' => [
        '{"user": {"name": "John", "age": 30',
        '{"user": {"name": "John", "age": 30}}',
    ],
    'cut off mid-nested-object' => [
        '{"data": {"user": {"name": "John", "profile": {"bio": "Developer"',
        '{"data": {"user": {"name": "John", "profile": {"bio": "Developer"}}}}',
    ],
    'cut off mid-array' => [
        '{"items": [1, 2, 3',
        '{"items": [1, 2, 3]}',
    ],
    'cut off mid-array-with-objects' => [
        '{"users": [{"name": "John"}, {"name": "Jane"',
        '{"users": [{"name": "John"}, {"name": "Jane"}]}',
    ],
    'cut off mid-string-in-array' => [
        '{"tags": ["php", "json", "repair"',
        '{"tags": ["php", "json", "repair"]}',
    ],
    'cut off after comma' => [
        '{"name": "John", "age": 30, ',
        '{"name": "John", "age": 30}',
    ],
    'cut off mid-escape-sequence' => [
        '{"message": "Hello\\',
        '{"message": "Hello"}',
    ],
    'cut off mid-complete-unicode-escape' => [
        '{"emoji": "\\u263a',
        '{"emoji": "\\u263a"}',
    ],
    'cut off mid-incomplete-unicode-escape' => [
        '{"emoji": "\\u26',
        '{"emoji": "\\\\u26"}',
    ],
    'multiple-incomplete-values' => [
        '{"name": "John", "age": 30, "bio": "A developer who loves',
        '{"name": "John", "age": 30, "bio": "A developer who loves"}',
    ],
    'cut off mid-null' => [
        '{"value": nul',
        '{"value": ""}',
    ],
    'cut off mid-false' => [
        '{"enabled": fals',
        '{"enabled": ""}',
    ],
    'cut off mid-true' => [
        '{"active": tr',
        '{"active": ""}',
    ],
    'cut off with-trailing-comma-before-incomplete' => [
        '{"name": "John", "age": 30, "bio": "A',
        '{"name": "John", "age": 30, "bio": "A"}',
    ],
    'cut off mid-nested-array' => [
        '{"matrix": [[1, 2], [3, 4',
        '{"matrix": [[1, 2], [3, 4]]}',
    ],
    'cut off with-mixed-complete-and-incomplete' => [
        '{"complete": "value", "incomplete": "partial',
        '{"complete": "value", "incomplete": "partial"}',
    ],
]);

// ============================================================================
// EMBEDDED JSON (markdown, surrounding text)
// ============================================================================

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
        '{"content": "[LINK](","https":"google.com",")":""}',
    ],
    'incomplete markdown link' => [
        '{ "content": "[LINK](" }',
        '{ "content": "[LINK](" }',
    ],
    'incomplete markdown link with other keys' => [
        '{ "content": "[LINK](", "key": true }',
        '{ "content": "[LINK](", "key": true }',
    ],
]);

dataset('leading_trailing_characters', [
    'multiple backticks' => [
        '````{ "key": "value" }```',
        '{"key": "value"}',
    ],
    'trailing backticks with newlines' => [
        "{    \"a\": \"\",    \"b\": [ { \"c\": 1} ] \n}```",
        '{"a": "",    "b": [{"c": 1}]}',
    ],
    'text before markdown code block' => [
        "Based on the information extracted, here is the filled JSON output: ```json { 'a': 'b' } ```",
        '{"a": "b"}',
    ],
    'multiline text before code block' => [
        '
                       The next 64 elements are:
                       ```json
                       { "key": "value" }
                       ```',
        '{"key": "value"}',
    ],
]);

dataset('json_in_strings', [
    'backticks in string value' => [
        '{"key": "``"}',
        '{"key": "``"}',
    ],
    'json code block in string' => [
        '{"key": "```json"}',
        '{"key": "```json"}',
    ],
    'nested JSON code block in string' => [
        '{"key": "```json {"key": [{"key1": 1},{"key2": 2}]}```"}',
        '{"key": [{"key1": 1},{"key2": 2}]}',
    ],
    'incomplete JSON code block in string' => [
        '{"response": "```json{}"}',
        '{"response": "```json{}"}',
    ],
]);

dataset('comments', [
    'single line comment' => [
        '{"key": "value", // comment',
        '{"key": "value"}',
    ],
    'multi line comment' => [
        '{"key": "value", /* comment */',
        '{"key": "value"}',
    ],
    'multiple comments' => [
        "{// User information\n\"name\": \"John\", /* Age in years */ \"age\": 30}",
        '{"name": "John", "age": 30}',
    ],
    'comment inside array' => [
        "[1, // second\n2, 3]",
        '[1, 2, 3]',
    ],
    'inline comment between properties' => [
        '{"a": 1 /* keep a */, "b": 2}',
        '{"a": 1, "b": 2}',
    ],
    'multi line comment with newline' => [
        "{\n\"a\": 1, /*\ncomment\n*/ \"b\": 2}",
        '{"a": 1, "b": 2}',
    ],
    'comment after last property' => [
        '{"a": 1 // trailing',
        '{"a": 1}',
    ],
]);

// ============================================================================
// SPECIAL CHARACTERS & ESCAPING
// ============================================================================

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
        '{"key": "string\"\\n\\t\\\\le"}',
    ],
    'unicode escape sequences' => [
        '{"key": "\u0076\u0061\u006c\u0075\u0065"}',
        '{"key": "\u0076\u0061\u006c\u0075\u0065"}',
    ],
    'single quote in double-quoted string' => [
        '{"key": "valu\'e"}',
        '{"key": "valu\'e"}',
    ],
    'nested JSON string' => [
        '{\'key\': "{\\"key\\": 1, \\"key2\\": 1}"}',
        '{"key": "{\\"key\\": 1, \\"key2\\": 1}"}',
    ],
    'newline in key' => [
        '{"key_1\n": "value"}',
        '{"key_1\\n": "value"}',
    ],
    'tab in key' => [
        '{"key\t_": "value"}',
        '{"key\\t_": "value"}',
    ],
]);

// ============================================================================
// VALUES & TYPES
// ============================================================================

dataset('booleans_and_null', [
    'capitalized True' => ['{"key": True}', '{"key": true}'],
    'capitalized False' => ['{"key": False}', '{"key": false}'],
    'capitalized None' => ['{"key": None}', '{"key": null}'],
    'JSON true' => ['{"key": true}', '{"key": true}'],
    'JSON false' => ['{"key": false}', '{"key": false}'],
    'JSON null' => ['{"key": null}', '{"key": null}'],
    'array with capitalized booleans' => ['[True, False, None]', '[true, false, null]'],
]);

dataset('standalone_booleans_null', [
    'standalone True' => ['True', ''],
    'standalone False' => ['False', ''],
    'standalone Null' => ['Null', ''],
    'standalone true' => ['true', 'true'],
    'standalone false' => ['false', 'false'],
    'standalone null' => ['null', 'null'],
]);

dataset('numbers', [
    'positive integer' => ['{"key": 123}', 123],
    'negative integer' => ['{"key": -123}', -123],
    'decimal' => ['{"key": 123.456}', 123.456],
    'scientific notation' => ['{"key": 123e10}', 'validate_only'],
    'large integer' => ['{"key": 12345678901234567890}', 'validate_only'],
]);

dataset('empty_strings', [
    'incomplete empty string' => [
        '{"key": ""',
        '{"key": ""}',
    ],
    'complete with empty string' => [
        '{"key1": "", "key2": "value"}',
        '{"key1": "", "key2": "value"}',
    ],
]);

dataset('parse_string', [
    'single quote' => ['"', ''],
    'newline only' => ["\n", ''],
    'space only' => [' ', ''],
    'plain string' => ['string', ''],
    'text before object' => ['stringbeforeobject {}', '{}'],
]);

// ============================================================================
// STRUCTURES
// ============================================================================

dataset('empty_structures', [
    'empty object' => ['{}', '{}'],
    'empty array' => ['[]', '[]'],
    'object with empty array' => [
        '{"key": []}',
        '{"key": []}',
    ],
    'object with empty object' => [
        '{"key": {}}',
        '{"key": {}}',
    ],
]);

dataset('mixed_type_arrays', [
    'JSON booleans and null' => [
        '[1, "two", true, false, null]',
        '[1, "two", true, false, null]',
    ],
    'capitalized booleans and null' => [
        '[True, False, None, "string", 123]',
        '[true, false, null, "string", 123]',
    ],
]);

// ============================================================================
// CONFIGURATION OPTIONS
// ============================================================================

dataset('omit_empty_values_true', [
    'missing value after colon' => [
        '{"key": }',
        '{}',
    ],
    'missing value with other keys' => [
        '{"key1": "v1", "key2": }',
        '{"key1": "v1"}',
    ],
    'missing value at end' => [
        '{"name": "John", "age": ',
        '{"name": "John"}',
    ],
    'key without colon' => [
        '{"key"',
        '{}',
    ],
    'multiple missing values' => [
        '{"key1": "v1", "key2": , "key3": "v3", "key4": }',
        '{"key1": "v1", "key3": "v3"}',
    ],
    'nested object with missing value' => [
        '{"user": {"name": "John", "age": }}',
        '{"user": {"name": "John"}}',
    ],
    'all values missing' => [
        '{"key1": , "key2": }',
        '{}',
    ],
]);

dataset('omit_empty_values_false', [
    'missing value after colon' => [
        '{"key": }',
        '{"key":""}',
    ],
    'missing value with other keys' => [
        '{"key1": "v1", "key2": }',
        '{"key1": "v1", "key2":""}',
    ],
]);

dataset('omit_incomplete_strings_true', [
    'cut off mid-string value' => [
        '{"name": "John", "description": "A person who',
        '{"name": "John"}',
    ],
    'incomplete string at end' => [
        '{"key": "val',
        '{}',
    ],
    'multiple incomplete strings' => [
        '{"name": "John", "bio": "A developer who',
        '{"name": "John"}',
    ],
    'complete and incomplete strings' => [
        '{"complete": "value", "incomplete": "partial',
        '{"complete": "value"}',
    ],
    'nested object with incomplete string' => [
        '{"user": {"name": "John", "bio": "A person',
        '{"user": {"name": "John"}}',
    ],
    'all strings incomplete' => [
        '{"key1": "val1',
        '{}',
    ],
]);

dataset('omit_incomplete_strings_false', [
    'cut off mid-string value' => [
        '{"name": "John", "description": "A person who',
        '{"name": "John", "description": "A person who"}',
    ],
    'incomplete string at end' => [
        '{"key": "val',
        '{"key": "val"}',
    ],
]);

dataset('combined_options', [
    'missing value and incomplete string' => [
        '{"name": "John", "age": , "bio": "A developer who',
        '{"name": "John"}',
    ],
    'multiple issues' => [
        '{"key1": "v1", "key2": , "key3": "partial',
        '{"key1": "v1"}',
    ],
    'all values problematic' => [
        '{"key1": , "key2": "incomplete',
        '{}',
    ],
]);
