# Repair broken or incomplete JSON strings

Repair invalid JSON strings by automatically fixing common syntax errors like single quotes, unquoted keys, trailing commas, and missing brackets.

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
use Cortex\JsonRepair\JsonRepairer;

use function Cortex\JsonRepair\json_repair;
use function Cortex\JsonRepair\json_repair_decode;

// Broken JSON (single quotes, unquoted keys, trailing comma)
$broken = "{'name': 'John', age: 30, active: true,}";

$repaired = (new JsonRepairer($broken))->repair();
// {"name": "John", "age": 30, "active": true}

// Or use the helper function
$repaired = json_repair($broken);

// Repair and decode in one step
$data = (new JsonRepairer($broken))->decode();
// ['name' => 'John', 'age' => 30, 'active' => true]

// Or use the helper function
$data = json_repair_decode($broken);
```

## Configuration Options

### Omit Empty Values

When repairing JSON from streaming sources (e.g., LLM responses), you may want to remove keys with missing values instead of adding empty strings:

```php
// Missing value - defaults to adding empty string
$broken = '{"name": "John", "age": }';
$repaired = json_repair($broken);
// {"name": "John", "age": ""}

// Remove keys with missing values
$repaired = json_repair($broken, omitEmptyValues: true);
// {"name": "John"}
```

### Omit Incomplete Strings

Similarly, you can remove keys with incomplete string values instead of closing them:

```php
// Incomplete string - defaults to closing the string
$broken = '{"name": "John", "bio": "A developer who';
$repaired = json_repair($broken);
// {"name": "John", "bio": "A developer who"}

// Remove keys with incomplete strings
$repaired = json_repair($broken, omitIncompleteStrings: true);
// {"name": "John"}
```

### Using Both Options Together

Both options can be used together, which is especially useful for streaming JSON where deltas are concatenated:

```php
$broken = '{"name": "John", "age": , "bio": "A developer who';
$repaired = json_repair($broken, omitEmptyValues: true, omitIncompleteStrings: true);
// {"name": "John"}
```

### Using with JsonRepairer Class

You can also pass these options to the `JsonRepairer` constructor:

```php
$repairer = new JsonRepairer(
    $broken,
    ensureAscii: true,
    omitEmptyValues: true,
    omitIncompleteStrings: true
);
$repaired = $repairer->repair();
```

Or with `json_repair_decode`:

```php
$data = json_repair_decode(
    $broken,
    omitEmptyValues: true,
    omitIncompleteStrings: true
);
```

## Logging

The library supports PSR-3 logging for debugging repair operations. Pass any PSR-3 compatible logger to see what repairs are being made:

```php
use Psr\Log\LoggerInterface;

// Using the helper function
$repaired = json_repair($broken, logger: $logger);

// Using the class (implements LoggerAwareInterface)
$repairer = new JsonRepairer($broken);
$repairer->setLogger($logger);
$repaired = $repairer->repair();
```

Log messages include the position in the JSON string and a context snippet showing where the repair occurred. This is useful for:

- Debugging why certain repairs are being made
- Understanding how malformed JSON is being interpreted
- Tracking repair operations in production environments

## Credits

- [Sean Tymon](https://github.com/tymondesigns)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
