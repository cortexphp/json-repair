<?php

declare(strict_types=1);

namespace Cortex\JsonRepair\Tests\Unit;

use Cortex\JsonRepair\JsonRepairer;

use function Cortex\JsonRepair\json_repair;

covers(JsonRepairer::class);

describe('Options', function (): void {
    describe('omitEmptyValues', function (): void {
        it('omits empty values when omitEmptyValues is true', function (string $input, string $expected): void {
            $result = json_repair($input, omitEmptyValues: true);
            expect(json_validate($result))->toBeTrue();
            expect($result)->toBe($expected);

            $decoded = json_decode($result, true);
            expect($decoded)->toBe(json_decode($expected, true));
        })->with('omit_empty_values_true');

        it('keeps empty values when omitEmptyValues is false', function (string $input, string $expected): void {
            $result = json_repair($input, omitEmptyValues: false);
            expect(json_validate($result))->toBeTrue();
            expect($result)->toBe($expected);

            $decoded = json_decode($result, true);
            expect($decoded)->toBe(json_decode($expected, true));
        })->with('omit_empty_values_false');

        it('handles nested structures with omitEmptyValues', function (): void {
            $input = '{"user": {"name": "John", "age": }, "meta": {"count": }}';
            $expected = '{"user": {"name": "John"}, "meta": {}}';
            $result = json_repair($input, omitEmptyValues: true);
            expect(json_validate($result))->toBeTrue();
            expect($result)->toBe($expected);

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
            $expected = '{}';
            $result = json_repair($input, omitEmptyValues: true);
            expect(json_validate($result))->toBeTrue();
            expect($result)->toBe($expected);

            $decoded = json_decode($result, true);
            expect($decoded)->toBe([]);
        });
    });

    describe('omitIncompleteStrings', function (): void {
        it(
            'omits incomplete strings when omitIncompleteStrings is true',
            function (string $input, string $expected): void {
                $result = json_repair($input, omitIncompleteStrings: true);
                expect(json_validate($result))->toBeTrue();
                expect($result)->toBe($expected);

                $decoded = json_decode($result, true);
                expect($decoded)->toBe(json_decode($expected, true));
            },
        )->with('omit_incomplete_strings_true');

        it(
            'keeps incomplete strings when omitIncompleteStrings is false',
            function (string $input, string $expected): void {
                $result = json_repair($input, omitIncompleteStrings: false);
                expect(json_validate($result))->toBeTrue();
                expect($result)->toBe($expected);

                $decoded = json_decode($result, true);
                expect($decoded)->toBe(json_decode($expected, true));
            },
        )->with('omit_incomplete_strings_false');

        it('handles edge case where removing incomplete string leaves empty object', function (): void {
            $input = '{"key": "val';
            $expected = '{}';
            $result = json_repair($input, omitIncompleteStrings: true);
            expect(json_validate($result))->toBeTrue();
            expect($result)->toBe($expected);

            $decoded = json_decode($result, true);
            expect($decoded)->toBe([]);
        });
    });

    describe('combined options', function (): void {
        it(
            'handles both omitEmptyValues and omitIncompleteStrings together',
            function (string $input, string $expected): void {
                $result = json_repair($input, omitEmptyValues: true, omitIncompleteStrings: true);
                expect(json_validate($result))->toBeTrue();
                expect($result)->toBe($expected);

                $decoded = json_decode($result, true);
                expect($decoded)->toBe(json_decode($expected, true));
            },
        )->with('combined_options');
    });
});
