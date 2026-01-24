<?php

declare(strict_types=1);

namespace Cortex\JsonRepair\Benchmarks;

use Cortex\JsonRepair\JsonRepair;

use function Cortex\JsonRepair\json_repair;
use function Cortex\JsonRepair\json_repair_decode;

/**
 * @Revs(100)
 * @Iterations(20)
 * @Warmup(2)
 */
class JsonRepairBench
{
    /**
     * @ParamProviders({"provideSimpleJson"})
     */
    public function benchRepairSimpleJson(array $params): void
    {
        json_repair($params['json']);
    }

    /**
     * @ParamProviders({"provideComplexJson"})
     */
    public function benchRepairComplexJson(array $params): void
    {
        json_repair($params['json']);
    }

    /**
     * @ParamProviders({"provideValidJson"})
     */
    public function benchRepairValidJson(array $params): void
    {
        json_repair($params['json']);
    }

    /**
     * @ParamProviders({"provideLargeJson"})
     */
    public function benchRepairLargeJson(array $params): void
    {
        json_repair($params['json']);
    }

    /**
     * @ParamProviders({"provideSimpleJson"})
     */
    public function benchRepairAndDecodeSimpleJson(array $params): void
    {
        json_repair_decode($params['json']);
    }

    /**
     * @ParamProviders({"provideComplexJson"})
     */
    public function benchRepairAndDecodeComplexJson(array $params): void
    {
        json_repair_decode($params['json']);
    }

    /**
     * @ParamProviders({"provideSimpleJson"})
     */
    public function benchRepairWithClass(array $params): void
    {
        $repairer = new JsonRepair($params['json']);
        $repairer->repair();
    }

    /**
     * @ParamProviders({"provideStreamingJson"})
     */
    public function benchRepairStreamingJson(array $params): void
    {
        json_repair($params['json']);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function provideSimpleJson(): array
    {
        return [
            'single_quotes' => ['json' => "{'key': 'value'}"],
            'unquoted_keys' => ['json' => '{key: "value", name: "John"}'],
            'trailing_comma' => ['json' => '{"key": "value",}'],
            'missing_comma' => ['json' => '{"key1": "v1" "key2": "v2"}'],
            'mixed_issues' => ['json' => "{'name': 'John', age: 30, 'city': 'NYC',}"],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function provideComplexJson(): array
    {
        return [
            'nested_object' => [
                'json' => "{'resourceType': 'Bundle', 'id': '1', 'type': 'collection', 'entry': [{'resource': {'resourceType': 'Patient', 'id': '1'}}]}",
            ],
            'complex_nested' => [
                'json' => '{"resourceType": "Bundle", "id": "1", "type": "collection", "entry": [{"resource": {"resourceType": "Patient", "id": "1", "name": [{"use": "official", "family": "Corwin", "given": ["Keisha", "Sunny"], "prefix": ["Mrs."}, {"use": "maiden", "family": "Goodwin", "given": ["Keisha", "Sunny"], "prefix": ["Mrs."]}]}}]}',
            ],
            'markdown_wrapped' => [
                'json' => '```json
{"key": "value", "number": 123}
```',
            ],
            'multiple_issues' => [
                'json' => "{'name': 'John', age: 30, 'items': [1, 2, 3,], 'nested': {'key': 'value', 'missing': }}",
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function provideValidJson(): array
    {
        return [
            'simple_object' => ['json' => '{"name": "John", "age": 30, "city": "New York"}'],
            'simple_array' => ['json' => '["John", "Anna", "Peter"]'],
            'nested_valid' => ['json' => '{"key1": {"key2": [1, 2, 3]}}'],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function provideLargeJson(): array
    {
        $largeArray = array_fill(0, 1000, ['id' => 1, 'name' => 'Item', 'value' => 123.45]);
        $largeJson = json_encode($largeArray);
        $brokenJson = str_replace(['"', ','], ["'", ', '], $largeJson);
        $brokenJson = rtrim($brokenJson, '}') . ',}';

        return [
            'large_array' => ['json' => $brokenJson],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function provideStreamingJson(): array
    {
        return [
            'cut_off_mid_string' => [
                'json' => '{"name": "John", "description": "A person who',
            ],
            'cut_off_mid_number' => [
                'json' => '{"count": 123',
            ],
            'cut_off_after_colon' => [
                'json' => '{"name": "John", "age": ',
            ],
            'cut_off_mid_key' => [
                'json' => '{"name": "John", "user',
            ],
            'cut_off_mid_object' => [
                'json' => '{"user": {"name": "John", "age": 30',
            ],
            'cut_off_mid_array' => [
                'json' => '{"items": [1, 2, 3',
            ],
            'cut_off_after_comma' => [
                'json' => '{"name": "John", "age": 30, ',
            ],
            'cut_off_mid_escape' => [
                'json' => '{"message": "Hello\\',
            ],
            'cut_off_mid_scientific' => [
                'json' => '{"value": 1.23e',
            ],
            'multiple_incomplete' => [
                'json' => '{"name": "John", "age": 30, "bio": "A developer who loves',
            ],
        ];
    }
}
