<?php

declare(strict_types=1);

namespace Cortex\JsonRepair\Tests\Unit;

use Cortex\JsonRepair\JsonRepairer;
use Cortex\JsonRepair\DuplicateKeyPolicy;

use function Cortex\JsonRepair\json_repair_decode;

covers(JsonRepairer::class);

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

describe('RepairResult', function (): void {
    it('returns structured repair details', function (): void {
        $repairer = new JsonRepairer("{'key': 'value'}");
        $repairResult = $repairer->repairWithDetails();

        expect($repairResult->wasAlreadyValid)->toBeFalse();
        expect($repairResult->json)->toBe('{"key": "value"}');
        expect($repairResult->fixes)->toContain('Starting JSON repair');
    });

    it('marks valid JSON as already valid', function (): void {
        $repairResult = (new JsonRepairer('{"key": "value"}'))->repairWithDetails();

        expect($repairResult->wasAlreadyValid)->toBeTrue();
        expect($repairResult->json)->toBe('{"key": "value"}');
        expect($repairResult->fixes)->toContain('JSON is already valid, returning as-is');
    });
});

describe('repairAll', function (): void {
    it('repairs multiple top-level JSON values', function (): void {
        $repairer = new JsonRepairer('{"a":1}{"b":2}');
        $results = $repairer->repairAll();

        expect($results)->toHaveCount(2);
        expect(json_decode($results[0], true))->toBe([
            'a' => 1,
        ]);
        expect(json_decode($results[1], true))->toBe([
            'b' => 2,
        ]);
    });

    it('repairs NDJSON lines', function (): void {
        $repairer = new JsonRepairer("{'a':1}\n{'b':2}");
        $results = $repairer->repairAll();

        expect($results)->toHaveCount(2);
        expect(json_decode($results[0], true))->toBe([
            'a' => 1,
        ]);
        expect(json_decode($results[1], true))->toBe([
            'b' => 2,
        ]);
    });

    it('repairs top-level values despite leading stray closing brackets', function (): void {
        $repairer = new JsonRepairer('}{"a":1}{"b":2}');
        $results = $repairer->repairAll();

        expect($results)->toHaveCount(2);
        expect(json_decode($results[0], true))->toBe([
            'a' => 1,
        ]);
        expect(json_decode($results[1], true))->toBe([
            'b' => 2,
        ]);
    });
});

describe('Duplicate key policy', function (): void {
    it('keeps first duplicate key when configured', function (): void {
        $repairer = new JsonRepairer('{a: 1, a: 2}', duplicateKeyPolicy: DuplicateKeyPolicy::KeepFirst);
        $result = $repairer->repair();

        expect(json_decode($result, true))->toBe([
            'a' => 1,
        ]);
    });

    it('keeps last duplicate key when configured', function (): void {
        $repairer = new JsonRepairer('{a: 1, a: 2}', duplicateKeyPolicy: DuplicateKeyPolicy::KeepLast);
        $result = $repairer->repair();

        expect(json_decode($result, true))->toBe([
            'a' => 2,
        ]);
    });

    it('keeps first non-adjacent duplicate key without dropping intervening keys', function (): void {
        $repairer = new JsonRepairer('{a: 1, b: 2, a: 3}', duplicateKeyPolicy: DuplicateKeyPolicy::KeepFirst);
        $result = $repairer->repair();

        expect(json_validate($result))->toBeTrue();
        expect(json_decode($result, true))->toBe([
            'a' => 1,
            'b' => 2,
        ]);
    });

    it('keeps last non-adjacent duplicate key without dropping intervening keys', function (): void {
        $repairer = new JsonRepairer('{a: 1, b: 2, a: 3}', duplicateKeyPolicy: DuplicateKeyPolicy::KeepLast);
        $result = $repairer->repair();

        expect(json_validate($result))->toBeTrue();
        expect(json_decode($result, true))->toBe([
            'b' => 2,
            'a' => 3,
        ]);
    });

    it('keeps last across multiple non-adjacent duplicates', function (): void {
        $repairer = new JsonRepairer('{a: 1, b: 2, a: 3, b: 4}', duplicateKeyPolicy: DuplicateKeyPolicy::KeepLast);
        $result = $repairer->repair();

        expect(json_validate($result))->toBeTrue();
        expect(json_decode($result, true))->toBe([
            'a' => 3,
            'b' => 4,
        ]);
    });

    it('does not corrupt large integers when keep-last is enabled', function (): void {
        $repairer = new JsonRepairer(
            '{big: 12345678901234567890, a: 1}',
            duplicateKeyPolicy: DuplicateKeyPolicy::KeepLast,
        );
        $result = $repairer->repair();

        expect(json_validate($result))->toBeTrue();
        expect($result)->toContain('12345678901234567890');
        expect($result)->not->toContain('e+');
    });

    it('preserves large integers while still deduplicating with keep-last', function (): void {
        $repairer = new JsonRepairer(
            '{big: 12345678901234567890, a: 1, a: 2}',
            duplicateKeyPolicy: DuplicateKeyPolicy::KeepLast,
        );
        $result = $repairer->repair();

        expect(json_validate($result))->toBeTrue();
        expect($result)->toContain('12345678901234567890');
        expect($result)->not->toContain('e+');

        $decoded = json_decode($result, true);
        expect($decoded['a'])->toBe(2);
    });

    it('keeps first when skipped duplicate value is a structure containing brackets in strings', function (): void {
        $repairer = new JsonRepairer(
            '{a: 1, a: {b: [1, 2], c: "}}"}, z: 9}',
            duplicateKeyPolicy: DuplicateKeyPolicy::KeepFirst,
        );
        $result = $repairer->repair();

        expect(json_validate($result))->toBeTrue();
        expect(json_decode($result, true))->toBe([
            'a' => 1,
            'z' => 9,
        ]);
    });

    it(
        'keeps first when skipped duplicate value is an array containing its closing bracket in a string',
        function (): void {
            $repairer = new JsonRepairer(
                '{a: 1, a: ["]", 3], z: 9}',
                duplicateKeyPolicy: DuplicateKeyPolicy::KeepFirst,
            );
            $result = $repairer->repair();

            expect(json_validate($result))->toBeTrue();
            expect(json_decode($result, true))->toBe([
                'a' => 1,
                'z' => 9,
            ]);
        },
    );
});

describe('Scalar decode', function (): void {
    it('decodes top-level scalar values', function (): void {
        $repairer = new JsonRepairer('true');
        expect($repairer->decode())->toBeTrue();
    });

    it('decodes top-level null via json_repair_decode', function (): void {
        expect(json_repair_decode('null'))->toBeNull();
    });

    it('decodes top-level string scalars', function (): void {
        expect((new JsonRepairer('"hello"'))->decode())->toBe('hello');
    });
});
