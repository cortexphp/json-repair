<?php

declare(strict_types=1);

namespace Cortex\JsonRepair\Tests\Unit;

use Cortex\JsonRepair\StreamingJsonRepairer;

covers(StreamingJsonRepairer::class);

describe('StreamingJsonRepairer', function (): void {
    it('repairs incrementally fed chunks', function (): void {
        $stream = new StreamingJsonRepairer();
        $stream->feed('{"key": ');
        $stream->feed('"val');

        $result = $stream->current();

        expect(json_validate($result))->toBeTrue();
        expect(json_decode($result, true))->toBe([
            'key' => 'val',
        ]);
    });
});
