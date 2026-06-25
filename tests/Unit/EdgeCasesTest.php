<?php

declare(strict_types=1);

namespace Cortex\JsonRepair\Tests\Unit;

use Cortex\JsonRepair\JsonRepairer;

use function Cortex\JsonRepair\json_repair;

covers(JsonRepairer::class);

describe('Edge cases and special features', function (): void {
    it('handles incomplete JSON at end of string', function (string $input, string $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        expect($result)->toBe($expected);

        $decoded = json_decode($result, true);
        expect($decoded)->toBe(json_decode($expected, true));
    })->with('incomplete_json');

    it('repairs incomplete JSON from streaming LLM responses', function (string $input, string $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        expect($result)->toBe($expected);

        $decoded = json_decode($result, true);
        expect($decoded)->toBe(json_decode($expected, true));
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

    it(
        'extracts JSON from markdown code blocks',
        function (string $input, ?string $expectedKey, ?string $expectedValue): void {
            $result = json_repair($input);
            expect(json_validate($result))->toBeTrue();

            if ($expectedKey !== null) {
                expect(json_decode($result, true)[$expectedKey])->toBe($expectedValue);
            }
        },
    )->with('markdown_code_blocks');

    it('handles markdown links in strings', function (string $input, string $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        expect($result)->toBe($expected);

        $decoded = json_decode($result, true);
        expect($decoded)->toBe(json_decode($expected, true));
    })->with('markdown_links');

    it('handles leading and trailing characters', function (string $input, string $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        expect($result)->toBe($expected);

        $decoded = json_decode($result, true);
        expect($decoded)->toBe(json_decode($expected, true));
    })->with('leading_trailing_characters');

    it('handles JSON code blocks inside string values', function (string $input, string $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        expect($result)->toBe($expected);

        $decoded = json_decode($result, true);
        expect($decoded)->toBe(json_decode($expected, true));
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

    it('removes comments', function (string $input, string $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        expect($result)->toBe($expected);
    })->with('comments');
});
