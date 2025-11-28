# Caster

Strict and expressive casting helpers for PHP. The goal is to replace chains of `isset`, `is_*`
and ad-hoc checks with a single call that either returns a typed value or throws a descriptive
exception.

## Features

- Scalar casters (`int`, `float`, `bool`, `string`, `nonEmptyString`, trimmed variants, etc.).
- Date/time helpers for `DateTimeImmutable`.
- Enum support for backed enums (`enum`, `nullableEnum`, `tryEnum`).
- Array utilities (`listOf`, `arrayOf`, nullable and try-* variants).
- JSON decoding with validation.
- Payload facades (`fromArray`, `fromArrayNullable`) to extract and cast fields in one step.
- “Soft” `try*` methods that return `null` on failure instead of throwing.
- Convenience guards (`required`, `requiredNonEmptyString`, `requiredNonEmptyList`, …).

## Installation

```bash
composer require nitier/caster
```

## Basic Usage

```php
use Nitier\Caster\Caster;

$payload = [
    'id' => '42',
    'price' => '19.95',
    'active' => 'yes',
    'created_at' => '2024-05-10 12:00:00',
];

$id = Caster::positiveInt($payload['id']);                 // 42
$price = Caster::float($payload['price']);                 // 19.95
$isActive = Caster::bool($payload['active']);              // true
$createdAt = Caster::dateTimeImmutable($payload['created_at']);
```

If casting fails, a `CastException` is thrown with field context (when provided). Nullable variants
(`nullableInt`, `nullableFloat`, …) treat `null` and whitespace-only strings as “empty”.

### Strings and Booleans

```php
$slug = Caster::nonEmptyTrimmedString($payload['slug']);   // trims and rejects empty results
$alias = Caster::nullableString($payload['alias'] ?? null); // ?string
$flag = Caster::bool('Yes');                               // true
$maybeFlag = Caster::tryBool('unknown');                   // null
```

### Date & Time

```php
$publishedAt = Caster::dateTimeImmutable('2024-01-01T10:00:00+00:00');
$maybeExpires = Caster::nullableDateTimeImmutable(
    $payload['expires_at'] ?? null,
    'expires_at',
    DATE_ATOM
);
```

### Enums

```php
enum Status: string { case Draft = 'draft'; case Published = 'published'; }

$status = Caster::enum(Status::class, 'draft');        // Status::Draft
$maybeStatus = Caster::tryEnum(Status::class, 'oops'); // null
```

## Soft `try*` Helpers

When you prefer a nullable result over exceptions:

```php
$maybeAge = Caster::tryInt($payload['age'] ?? null);        // ?int
$maybeActive = Caster::tryBool($payload['active'] ?? null); // ?bool
$maybeDate = Caster::tryDateTimeImmutable($payload['published_at'] ?? null);
```

Each `try*` helper wraps the strict version and returns `null` on failure.

## Payload Helpers

Extract values from an associative array and cast them on the spot:

```php
$payload = [
    'id' => '100',
    'name' => 'Alice',
    'meta' => ['role' => 'admin'],
    'tags' => ['php', 'caster'],
];

$id = Caster::fromArray($payload, 'id', [Caster::class, 'positiveInt']);
$name = Caster::fromArrayNullable($payload, 'name', [Caster::class, 'nonEmptyTrimmedString']);
$meta = Caster::requiredNonEmptyArray(
    $payload['meta'] ?? null,
    'meta',
    static fn ($value) => Caster::string($value)
);
$tags = Caster::requiredNonEmptyList(
    $payload['tags'] ?? null,
    'tags',
    static fn ($value) => Caster::nonEmptyTrimmedString($value)
);
```

- `fromArray` throws `MissingValueException` when the key is missing.
- `fromArrayNullable` returns `null` when the key is missing or empty.
- `requiredNonEmpty*` helpers ensure presence and non-empty content for strings/lists/maps.

## Collections

`listOf` and `arrayOf` accept a caster callback applied to every item:

```php
$ids = Caster::listOf(['1', '2', '3'], static fn ($value) => Caster::int($value));
$attributes = Caster::arrayOf(
    ['a' => 'true', 'b' => 'false'],
    static fn ($value) => Caster::bool($value)
);

// Nullable variants return null for missing/empty input
$maybeTags = Caster::nullableListOf($payload['tags'] ?? null, static fn ($v) => Caster::string($v));
```

Use `requiredNonEmptyList`/`requiredNonEmptyArray` when the collection must contain at least one item.

## JSON

```php
$data = Caster::json($payload['data']);            // array<string,mixed>
$maybeData = Caster::tryJson($payload['data']);    // ?array<string,mixed>
```

You can pass `$assoc`, `$depth` and `$flags` just like `json_decode`.

## Required Guards

`Caster::required($value, 'field')` ensures a value is not `null`. For common cases you can use:

```php
$name = Caster::requiredNonEmptyString($payload['name'] ?? null, 'name', true);
$options = Caster::requiredNonEmptyArray(
    $payload['options'] ?? null,
    'options',
    static fn ($value) => Caster::string($value)
);
```

## Testing

The project includes an extensive PHPUnit suite. Run it with:

```bash
composer test
```

Static analysis:

```bash
composer stan
```

Code style:

```bash
composer cs
```

## License

MIT ©2025 Nitier
