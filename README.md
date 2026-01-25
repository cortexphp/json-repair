# Repair broken JSON strings

[![Latest Version](https://img.shields.io/packagist/v/cortexphp/json-repair.svg?style=flat-square&logo=composer)](https://packagist.org/packages/cortexphp/json-repair)
![GitHub Actions Test Workflow Status](https://img.shields.io/github/actions/workflow/status/cortexphp/json-repair/run-tests.yml?style=flat-square&logo=github)
![GitHub License](https://img.shields.io/github/license/cortexphp/json-repair?style=flat-square&logo=github)

## Requirements

- PHP 8.3+

## Installation

```bash
composer require cortexphp/json-repair
```

## Quick Start

```php
use Cortex\JsonRepair\JsonRepair;

use function Cortex\JsonRepair\json_repair;
use function Cortex\JsonRepair\json_repair_decode;

// Repair broken JSON (single quotes, unquoted keys, trailing comma)
$broken = "{'name': 'John', age: 30, active: true,}";

$repaired = (new JsonRepair($broken))->repair();
// {"name": "John", "age": 30, "active": true}

// Or use the helper function
$repaired = json_repair($broken);

// Repair and decode in one step
$data = (new JsonRepair($broken))->decode();
// ['name' => 'John', 'age' => 30, 'active' => true]

// Or use the helper function
$data = json_repair_decode($broken);
// ['name' => 'John', 'age' => 30, 'active' => true]
```

## Benchmarking

Run performance benchmarks using PHPBench:

```bash
composer run benchmark
```

## Documentation

📚 **[View Full Documentation →](https://docs.cortexphp.com/json-repair)**

## Credits

- [Sean Tymon](https://github.com/tymondesigns)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
