<?php

declare(strict_types=1);

namespace Cortex\JsonRepair\Tests\Unit;

use Cortex\JsonRepair\JsonRepairer;

use function Cortex\JsonRepair\json_repair;
use function Cortex\JsonRepair\json_repair_decode;

covers(JsonRepairer::class);

describe('JSON repairs', function (): void {
    it('passes through valid JSON unchanged', function (string $json): void {
        $result = json_repair($json);
        expect(json_validate($result))->toBeTrue();
        expect(json_decode($result, true))->toBe(json_decode($json, true));
    })->with('valid_json');

    it('handles non-JSON strings', function (string $input, string $expected): void {
        $result = json_repair($input);
        expect($result)->toBe($expected);

        if ($result !== '') {
            expect(json_validate($result))->toBeTrue();
            expect(json_decode($result, true))->toBe([]);
        }
    })->with('parse_string');

    it('repairs single quotes to double quotes', function (string $input, array $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);
        expect($decoded)->toBe($expected);
    })->with('single_quotes_to_double');

    it('repairs unquoted keys', function (string $input, array $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);
        expect($decoded)->toBe($expected);
    })->with('unquoted_keys');

    it('repairs missing quotes around keys', function (): void {
        $result = json_repair('{key: "value"}');
        expect(json_validate($result))->toBeTrue();
        expect(json_decode($result, true)['key'])->toBe('value');
    });

    it('handles mixed single and double quotes', function (string $input, array $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);
        expect($decoded)->toBe($expected);
    })->with('mixed_quotes');

    it('handles quotes inside string values', function (string $input, array $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);
        expect($decoded)->toBe($expected);
    })->with('quotes_inside_strings');

    it('repairs trailing commas', function (string $input, array $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);
        expect($decoded)->toBe($expected);
    })->with('trailing_commas');

    it('repairs missing commas', function (string $input, array $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);
        expect($decoded)->toBe($expected);
    })->with('missing_commas');

    it('repairs missing colons', function (): void {
        $result = json_repair('{"key" "value"}');
        expect(json_validate($result))->toBeTrue();
        expect(json_decode($result, true)['key'])->toBe('value');
    });

    it('repairs missing closing brackets', function (string $input, array $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);
        expect($decoded)->toBe($expected);
    })->with('missing_closing_brackets');

    it('repairs missing closing braces', function (string $input, string $expectedPath, string $expectedValue): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);

        $value = $decoded;
        foreach (explode('.', $expectedPath) as $key) {
            $value = $value[$key];
        }

        expect($value)->toBe($expectedValue);
    })->with('missing_closing_braces');

    it('repairs missing values', function (string $input, array $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);
        expect($decoded)->toBe($expected);
    })->with('missing_values');

    it('handles missing keys in objects', function (): void {
        $input = '{: "value"}';
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);
        expect($decoded)->toBeArray();
        expect($decoded)->toHaveKey('value');
        expect($decoded['value'])->toBe('');
    });
});

describe('Values and structures', function (): void {
    it('repairs non-standard booleans and null', function (string $input, mixed $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);

        if (is_array($expected)) {
            expect($decoded)->toBe($expected);
        } else {
            expect($decoded['key'])->toBe($expected);
        }
    })->with('booleans_and_null');

    it('handles standalone booleans and null', function (string $input, string $expected): void {
        $result = json_repair($input);
        expect($result)->toBe($expected);

        if ($result !== '') {
            expect(json_validate($result))->toBeTrue();
        }
    })->with('standalone_booleans_null');

    it('handles numbers correctly', function (string $input, int|float|string $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();

        if (is_string($expected)) {
            return;
        }

        expect(json_decode($result, true)['key'])->toBe($expected);
    })->with('numbers');

    it('handles strings with special characters', function (string $input, string $expectedKey, string $expectedValue): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);
        expect($decoded[$expectedKey])->toBe($expectedValue);
    })->with('special_characters');

    it('handles escape sequences', function (string $input, string $expectedValue): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);
        expect($decoded)->toBeArray();
        expect($decoded)->toHaveKey('key');
        expect($decoded['key'])->toBe($expectedValue);
    })->with('escape_sequences');

    it('handles advanced escaping cases', function (string $input, array $expected): void {
        $result = json_repair($input);

        if (! json_validate($result)) {
            expect(json_validate($result))->toBeTrue()
                ->and($result)->not->toBeEmpty();

            return;
        }

        $decoded = json_decode($result, true);
        expect($decoded)->toBe($expected);
    })->with('advanced_escaping');

    it('handles unicode characters when ensureAscii is false', function (): void {
        $input = "{'test_中国人_ascii':'统一码'}";
        $result = json_repair($input, ensureAscii: false);
        expect(json_validate($result))->toBeTrue();
        expect($result)->toContain('统一码');
        expect($result)->toContain('test_中国人_ascii');

        $decoded = json_decode($result, true);
        expect($decoded)->toHaveKey('test_中国人_ascii');
        expect($decoded['test_中国人_ascii'])->toBe('统一码');
    });

    it('handles empty strings as values', function (string $input, array $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);
        expect($decoded)->toBe($expected);
    })->with('empty_strings');

    it('handles nested structures', function (string $input): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        expect(json_decode($result, true))->toBe(json_decode($input, true));
    })->with('nested_structures');

    it('handles empty structures', function (string $input, mixed $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        expect(json_decode($result, true))->toBe($expected);
    })->with('empty_structures');

    it('handles arrays with mixed types', function (string $input, array $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        expect(json_decode($result, true))->toBe($expected);
    })->with('mixed_type_arrays');
});

describe('Edge cases and special features', function (): void {
    it('handles incomplete JSON at end of string', function (string $input, array $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);
        expect($decoded)->toBe($expected);
    })->with('incomplete_json');

    it('repairs incomplete JSON from streaming LLM responses', function (string $input, array $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);
        expect($decoded)->toBe($expected);
    })->with('streaming_llm_responses');

    it('handles complex nested structures', function (): void {
        $input = '{"resourceType": "Bundle", "id": "1", "type": "collection", "entry": [{"resource": {"resourceType": "Patient", "id": "1", "name": [{"use": "official", "family": "Corwin", "given": ["Keisha", "Sunny"], "prefix": ["Mrs."}, {"use": "maiden", "family": "Goodwin", "given": ["Keisha", "Sunny"], "prefix": ["Mrs."]}]}}]}';
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();

        $decoded = json_decode($result, true);
        expect($decoded)->toBeArray();
        expect($decoded['resourceType'])->toBe('Bundle');
        expect($decoded['id'])->toBe('1');
        expect($decoded['type'])->toBe('collection');
        expect($decoded['entry'])->toBeArray();
        expect($decoded['entry'][0]['resource']['resourceType'])->toBe('Patient');
        expect($decoded['entry'][0]['resource']['name'])->toBeArray();
        expect($decoded['entry'][0]['resource']['name'])->toHaveCount(1);
        expect($decoded['entry'][0]['resource']['name'][0]['use'])->toBe('official');
        expect($decoded['entry'][0]['resource']['name'][0]['family'])->toBe('Corwin');
        expect($decoded['entry'][0]['resource']['name'][0]['given'])->toBe(['Keisha', 'Sunny']);
        expect($decoded['entry'][0]['resource']['name'][0]['prefix'][0])->toBe('Mrs.');
        expect($decoded['entry'][0]['resource']['name'][0]['prefix'][1])->toBeArray();
        expect($decoded['entry'][0]['resource']['name'][0]['prefix'][1]['use'])->toBe('maiden');
        expect($decoded['entry'][0]['resource']['name'][0]['prefix'][1]['family'])->toBe('Goodwin');
    });

    it('handles strings with quotes inside', function (): void {
        $input = '{\n"html": "<h3 id="aaa">Waarom meer dan 200 Technical Experts - "Passie voor techniek"?</h3>"}';
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);
        expect($decoded)->toBeArray();
        expect($decoded)->toHaveKey('n');
        expect($decoded)->toHaveKey('<h3 id=');
        expect($decoded)->toHaveKey('Passie');
        expect($decoded['n'])->toBe('html');
        expect($decoded['<h3 id='])->toBe('>Waarom meer dan 200 Technical Experts - ');
        expect($decoded['Passie'])->toBe('?</h3>');
    });

    it('handles multiple JSON objects', function (string $input, ?string $expectedKey, ?string $expectedValue): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();

        if ($expectedKey !== null) {
            $decoded = json_decode($result, true);

            if ($expectedValue !== null) {
                expect($decoded[$expectedKey])->toBe($expectedValue);
            } else {
                expect($decoded)->toBeArray();
            }
        }
    })->with('multiple_json_objects');

    it('extracts JSON from markdown code blocks', function (string $input, ?string $expectedKey, ?string $expectedValue): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();

        if ($expectedKey !== null) {
            expect(json_decode($result, true)[$expectedKey])->toBe($expectedValue);
        }
    })->with('markdown_code_blocks');

    it('handles markdown links in strings', function (string $input, array $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);
        expect($decoded)->toBe($expected);
    })->with('markdown_links');

    it('handles leading and trailing characters', function (string $input, array $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);
        expect($decoded)->toBe($expected);
    })->with('leading_trailing_characters');

    it('handles JSON code blocks inside string values', function (string $input, array $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);
        expect($decoded)->toBe($expected);
    })->with('json_in_strings');

    it('handles whitespace normalization', function (): void {
        $input = '{"key"   :   "value"   ,   "key2"   :   "value2"}';
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);
        expect($decoded)->toBe([
            'key' => 'value',
            'key2' => 'value2',
        ]);
    });

    it('removes comments', function (): void {
        $result = json_repair('{"key": "value"} // comment');
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);
        expect($decoded)->toBe([
            'key' => 'value',
        ]);
    });
});

describe('Options', function (): void {
    describe('omitEmptyValues', function (): void {
        it('omits empty values when omitEmptyValues is true', function (string $input, array $expected): void {
            $result = json_repair($input, omitEmptyValues: true);
            expect(json_validate($result))->toBeTrue();
            $decoded = json_decode($result, true);
            expect($decoded)->toBe($expected);
        })->with('omit_empty_values_true');

        it('keeps empty values when omitEmptyValues is false', function (string $input, array $expected): void {
            $result = json_repair($input, omitEmptyValues: false);
            expect(json_validate($result))->toBeTrue();
            $decoded = json_decode($result, true);
            expect($decoded)->toBe($expected);
        })->with('omit_empty_values_false');

        it('handles nested structures with omitEmptyValues', function (): void {
            $input = '{"user": {"name": "John", "age": }, "meta": {"count": }}';
            $result = json_repair($input, omitEmptyValues: true);
            expect(json_validate($result))->toBeTrue();
            $decoded = json_decode($result, true);
            expect($decoded)->toBe([
                'user' => [
                    'name' => 'John',
                ],
                'meta' => [],
            ]);
        });

        it('handles edge case where removing key leaves empty object', function (): void {
            $input = '{"key": }';
            $result = json_repair($input, omitEmptyValues: true);
            expect(json_validate($result))->toBeTrue();
            $decoded = json_decode($result, true);
            expect($decoded)->toBe([]);
            expect($result)->toBe('{}');
        });
    });

    describe('omitIncompleteStrings', function (): void {
        it('omits incomplete strings when omitIncompleteStrings is true', function (string $input, array $expected): void {
            $result = json_repair($input, omitIncompleteStrings: true);
            expect(json_validate($result))->toBeTrue();
            $decoded = json_decode($result, true);
            expect($decoded)->toBe($expected);
        })->with('omit_incomplete_strings_true');

        it('keeps incomplete strings when omitIncompleteStrings is false', function (string $input, array $expected): void {
            $result = json_repair($input, omitIncompleteStrings: false);
            expect(json_validate($result))->toBeTrue();
            $decoded = json_decode($result, true);
            expect($decoded)->toBe($expected);
        })->with('omit_incomplete_strings_false');

        it('handles edge case where removing incomplete string leaves empty object', function (): void {
            $input = '{"key": "val';
            $result = json_repair($input, omitIncompleteStrings: true);
            expect(json_validate($result))->toBeTrue();
            $decoded = json_decode($result, true);
            expect($decoded)->toBe([]);
            expect($result)->toBe('{}');
        });
    });

    describe('combined options', function (): void {
        it('handles both omitEmptyValues and omitIncompleteStrings together', function (string $input, array $expected): void {
            $result = json_repair($input, omitEmptyValues: true, omitIncompleteStrings: true);
            expect(json_validate($result))->toBeTrue();
            $decoded = json_decode($result, true);
            expect($decoded)->toBe($expected);
        })->with('combined_options');
    });
});

describe('API usage', function (): void {
    it('works with JsonRepairer class directly', function (): void {
        $repairer = new JsonRepairer("{'key': 'value'}");
        $result = $repairer->repair();
        expect(json_validate($result))->toBeTrue();
        expect(json_decode($result, true)['key'])->toBe('value');
    });

    it('can decode repaired JSON', function (): void {
        $repairer = new JsonRepairer("{'key': 'value', 'number': 123}");
        $decoded = $repairer->decode();

        expect($decoded)->toBeArray();
        expect($decoded['key'])->toBe('value');
        expect($decoded['number'])->toBe(123);
    });

    it('can use json_repair_decode helper function', function (): void {
        $decoded = json_repair_decode("{'key': 'value', 'number': 123}");

        expect($decoded)->toBeArray();
        expect($decoded['key'])->toBe('value');
        expect($decoded['number'])->toBe(123);
    });

    it('works with JsonRepairer class directly with omitEmptyValues', function (): void {
        $repairer = new JsonRepairer('{"key": }', omitEmptyValues: true);
        $result = $repairer->repair();
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);
        expect($decoded)->toBe([]);
    });

    it('works with JsonRepairer class directly with omitIncompleteStrings', function (): void {
        $repairer = new JsonRepairer('{"key": "val', omitIncompleteStrings: true);
        $result = $repairer->repair();
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);
        expect($decoded)->toBe([]);
    });

    it('works with json_repair_decode with new options', function (): void {
        $decoded = json_repair_decode('{"key": }', omitEmptyValues: true);
        expect($decoded)->toBeArray();
        expect($decoded)->toBe([]);
    });
});
