<?php

declare(strict_types=1);

namespace Nitier\Caster;

use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use Throwable;
use Nitier\Caster\Exception\CastException;
use Nitier\Caster\Exception\MissingValueException;

class Caster
{
    private function __construct()
    {
    }

    // ==========================
    //      BASIC TYPES
    // ==========================

    /**
     * Forces the given value into an integer, accepting native ints, booleans, integer-like floats
     * and integer-like strings.
     * Example: `$age = Caster::int($payload['age']); // int`
     *
     * @param mixed $value Any scalar that should represent an integer (int, bool, whole float, numeric string).
     * @param string|null $field Optional field identifier added to exception messages.
     * @return int Strict integer value that mirrors PHP's `(int)` cast but with validation.
     *
     * @throws CastException When the value cannot be interpreted as an integer.
     */
    public static function int(mixed $value, ?string $field = null): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return (int) $value;
        }

        if (is_float($value)) {
            if (!is_finite($value) || floor($value) !== $value) {
                throw self::invalidType('int', $value, $field);
            }

            return (int) $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw self::invalidType('int', $value, $field);
    }

    /**
     * Soft version of {@see int()} that returns null instead of throwing on failure.
     * Example: `$age = Caster::tryInt($payload['age']); // ?int`
     *
     * @param mixed $value Value to cast.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return int|null Integer or null when casting fails.
     */
    public static function tryInt(mixed $value, ?string $field = null): ?int
    {
        try {
            return self::int($value, $field);
        } catch (CastException) {
            return null;
        }
    }

    /**
     * Ensures the value is a strictly positive integer (> 0).
     * Example: `$userId = Caster::positiveInt($payload['user_id']); // int`
     *
     * @param mixed $value Scalar that should represent a positive integer.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return int Positive integer.
     *
     * @throws CastException When the value is not an integer or is less than 1.
     */
    public static function positiveInt(mixed $value, ?string $field = null): int
    {
        $int = self::int($value, $field);

        if ($int <= 0) {
            throw new CastException(self::formatMessage(
                'Expected positive int (> 0)',
                $int,
                $field
            ));
        }

        return $int;
    }

    /**
     * Ensures the value is a non-negative integer (>= 0).
     * Example: `$offset = Caster::nonNegativeInt($payload['offset']); // int`
     *
     * @param mixed $value Scalar that should represent a non-negative integer.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return int Integer greater than or equal to zero.
     *
     * @throws CastException When the value is not an integer or is negative.
     */
    public static function nonNegativeInt(mixed $value, ?string $field = null): int
    {
        $int = self::int($value, $field);

        if ($int < 0) {
            throw new CastException(self::formatMessage(
                'Expected non-negative int (>= 0)',
                $int,
                $field
            ));
        }

        return $int;
    }

    /**
     * Ensures the value is an integer within the inclusive range [$min, $max].
     * Example: `$limit = Caster::intBetween($payload['limit'], 1, 100); // int`
     *
     * @param mixed $value Scalar that should represent an integer.
     * @param int $min Inclusive lower bound.
     * @param int $max Inclusive upper bound.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return int Integer clamped within the provided bounds.
     *
     * @throws CastException When the value is not an integer or falls outside the bounds.
     */
    public static function intBetween(mixed $value, int $min, int $max, ?string $field = null): int
    {
        if ($min > $max) {
            throw new CastException("Invalid boundaries: min ({$min}) is greater than max ({$max}).");
        }

        $int = self::int($value, $field);

        if ($int < $min || $int > $max) {
            throw new CastException(self::formatMessage(
                "Expected int between {$min} and {$max}",
                $int,
                $field
            ));
        }

        return $int;
    }

    /**
     * Returns null for empty inputs (null/whitespace), otherwise delegates to {@see int()}.
     * Example: `$age = Caster::nullableInt($row['age'] ?? null); // ?int`
     *
     * @param mixed $value Nullable scalar that should represent an integer or be empty/null.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return int|null Integer when provided, null for empty/null inputs.
     *
     * @throws CastException When the non-null value cannot be interpreted as an integer.
     */
    public static function nullableInt(mixed $value, ?string $field = null): ?int
    {
        if (self::isEmptyInput($value)) {
            return null;
        }

        return self::int($value, $field);
    }

    /**
     * Casts the value to float, accepting numeric strings, booleans and special tokens INF/-INF/NAN.
     * Example: `$price = Caster::float($payload['price']); // float`
     *
     * @param mixed $value Any scalar that should represent a float (float, int, bool, numeric string, INF token).
     * @param string|null $field Optional field identifier added to exception messages.
     * @return float Strict float value mirroring `(float)` cast with validation.
     *
     * @throws CastException When the value is not numeric.
     */
    public static function float(mixed $value, ?string $field = null): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        if (is_string($value)) {
            $normalized = strtoupper(trim($value));
            if ($normalized === 'INF' || $normalized === '+INF') {
                return INF;
            }

            if ($normalized === '-INF') {
                return -INF;
            }

            if ($normalized === 'NAN') {
                return NAN;
            }

            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        throw self::invalidType('float', $value, $field);
    }

    /**
     * Soft version of {@see float()} that returns null instead of throwing on failure.
     *
     * @param mixed $value Value to cast.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return float|null Float or null when casting fails.
     */
    public static function tryFloat(mixed $value, ?string $field = null): ?float
    {
        try {
            return self::float($value, $field);
        } catch (CastException) {
            return null;
        }
    }

    /**
     * Returns null for empty inputs (null/whitespace), otherwise delegates to {@see float()}.
     * Example: `$discount = Caster::nullableFloat($payload['discount'] ?? null); // ?float`
     *
     * @param mixed $value Nullable scalar that should represent a float or be empty/null.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return float|null Float when provided, null for empty/null inputs.
     *
     * @throws CastException When the non-null value is not numeric.
     */
    public static function nullableFloat(mixed $value, ?string $field = null): ?float
    {
        if (self::isEmptyInput($value)) {
            return null;
        }

        return self::float($value, $field);
    }

    /**
     * Normalizes the value to boolean, accepting ints (0/1) and common string toggles.
     * Example: `$isActive = Caster::bool($payload['active']); // bool`
     *
     * @param mixed $value Value that should represent a boolean (bool, 0/1, on/off, yes/no, true/false strings).
     * @param string|null $field Optional field identifier added to exception messages.
     * @return bool Boolean flag.
     *
     * @throws CastException When the value cannot be mapped to a boolean.
     */
    public static function bool(mixed $value, ?string $field = null): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return match ($value) {
                0 => false,
                1 => true,
                default => throw self::invalidType('bool (0|1)', $value, $field),
            };
        }

        if (is_string($value)) {
            $v = strtolower(trim($value));
            return match ($v) {
                '1', 'true', 'yes', 'on'  => true,
                '0', 'false', 'no', 'off' => false,
                default => throw self::invalidType(
                    "bool ('true'/'false'/'1'/'0')",
                    $value,
                    $field
                ),
            };
        }

        throw self::invalidType('bool', $value, $field);
    }

    /**
     * Soft version of {@see bool()} that returns null instead of throwing on failure.
     *
     * @param mixed $value Value to cast.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return bool|null Boolean or null when casting fails.
     */
    public static function tryBool(mixed $value, ?string $field = null): ?bool
    {
        try {
            return self::bool($value, $field);
        } catch (CastException) {
            return null;
        }
    }

    /**
     * Returns null for empty inputs (null/whitespace), otherwise delegates to {@see bool()}.
     * Example: `$isActive = Caster::nullableBool($payload['active'] ?? null); // ?bool`
     *
     * @param mixed $value Nullable scalar that should represent a boolean or be empty/null.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return bool|null Boolean when provided, null for empty/null inputs.
     *
     * @throws CastException When the non-null value cannot be mapped to a boolean.
     */
    public static function nullableBool(mixed $value, ?string $field = null): ?bool
    {
        if (self::isEmptyInput($value)) {
            return null;
        }

        return self::bool($value, $field);
    }

    /**
     * Coerces scalars into strings without trimming or normalization, formatting special floats as INF/-INF/NAN.
     * Example: `$identifier = Caster::string($input['id']); // string`
     *
     * @param mixed $value Scalar (string, int, float, bool) that should be represented as a string.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return string String representation without further sanitation.
     *
     * @throws CastException When the value is not a scalar stringable type.
     */
    public static function string(mixed $value, ?string $field = null): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (is_nan($value)) {
                return 'NAN';
            }

            if ($value === INF) {
                return 'INF';
            }

            if ($value === -INF) {
                return '-INF';
            }

            return (string) $value;
        }

        if (is_bool($value)) {
            return (string) $value;
        }

        throw self::invalidType('string', $value, $field);
    }

    /**
     * Soft version of {@see string()} that returns null instead of throwing on failure.
     *
     * @param mixed $value Value to cast.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return string|null String or null when casting fails.
     */
    public static function tryString(mixed $value, ?string $field = null): ?string
    {
        try {
            return self::string($value, $field);
        } catch (CastException) {
            return null;
        }
    }

    /**
     * Ensures the value is a non-empty string, reusing {@see string()} casting rules.
     * Example: `$name = Caster::nonEmptyString($payload['name']); // string`
     *
     * @param mixed $value Scalar that must result in a non-empty string.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return string Non-empty string.
     *
     * @throws CastException When the value is empty or not stringable.
     */
    public static function nonEmptyString(mixed $value, ?string $field = null): string
    {
        $str = self::string($value, $field);

        if ($str === '') {
            throw new CastException(self::formatMessage(
                'Expected non-empty string',
                $value,
                $field
            ));
        }

        return $str;
    }

    /**
     * Soft version of {@see nonEmptyString()} returning null when validation fails.
     *
     * @param mixed $value Value to cast.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return string|null Non-empty string or null.
     */
    public static function tryNonEmptyString(mixed $value, ?string $field = null): ?string
    {
        try {
            return self::nonEmptyString($value, $field);
        } catch (CastException) {
            return null;
        }
    }

    /**
     * Returns null for empty inputs (null/whitespace), otherwise delegates to {@see string()}.
     * Example: `$alias = Caster::nullableString($payload['alias'] ?? null); // ?string`
     *
     * @param mixed $value Nullable scalar that should represent a string or be empty/null.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return string|null String when provided, null for empty/null inputs.
     *
     * @throws CastException When the non-null value is not stringable.
     */
    public static function nullableString(mixed $value, ?string $field = null): ?string
    {
        if (self::isEmptyInput($value)) {
            return null;
        }

        return self::string($value, $field);
    }

    /**
     * Casts the value to string and applies {@see trim()}.
     * Example: `$identifier = Caster::trimmedString($payload['identifier']); // string`
     *
     * @param mixed $value Scalar that should become a trimmed string.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return string Trimmed string (may be empty).
     *
     * @throws CastException When the value is not stringable.
     */
    public static function trimmedString(mixed $value, ?string $field = null): string
    {
        return trim(self::string($value, $field));
    }

    /**
     * Ensures the trimmed string is non-empty.
     * Example: `$alias = Caster::nonEmptyTrimmedString($payload['alias']); // string`
     *
     * @param mixed $value Scalar that should become a trimmed non-empty string.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return string Trimmed non-empty string.
     *
     * @throws CastException When the value is not stringable or only whitespace.
     */
    public static function nonEmptyTrimmedString(mixed $value, ?string $field = null): string
    {
        $trimmed = self::trimmedString($value, $field);

        if ($trimmed === '') {
            throw new CastException(self::formatMessage(
                'Expected non-empty trimmed string',
                $value,
                $field
            ));
        }

        return $trimmed;
    }

    /**
     * Soft version of {@see nonEmptyTrimmedString()} returning null when validation fails.
     *
     * @param mixed $value Value to cast.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return string|null Trimmed non-empty string or null.
     */
    public static function tryNonEmptyTrimmedString(mixed $value, ?string $field = null): ?string
    {
        try {
            return self::nonEmptyTrimmedString($value, $field);
        } catch (CastException) {
            return null;
        }
    }

    /**
     * Casts the value to string and lowercases it using UTF-8 aware functions when available.
     * Example: `$slug = Caster::lowerString($payload['name']); // string`
     *
     * @param mixed $value Scalar that should become a lowercase string.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return string Lowercase string.
     *
     * @throws CastException When the value is not stringable.
     */
    public static function lowerString(mixed $value, ?string $field = null): string
    {
        $str = self::string($value, $field);

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($str, 'UTF-8');
        }

        return strtolower($str);
    }

    /**
     * Casts the value to string and uppercases it using UTF-8 aware functions when available.
     * Example: `$code = Caster::upperString($payload['code']); // string`
     *
     * @param mixed $value Scalar that should become an uppercase string.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return string Uppercase string.
     *
     * @throws CastException When the value is not stringable.
     */
    public static function upperString(mixed $value, ?string $field = null): string
    {
        $str = self::string($value, $field);

        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($str, 'UTF-8');
        }

        return strtoupper($str);
    }

    /**
     * Decodes a JSON string into an array/object depending on $assoc flag.
     * Example: `$payload = Caster::json($input['data']); // array<string,mixed>`
     *
     * @param mixed $value Stringable JSON payload.
     * @param bool $assoc Decode to associative array (true) or stdClass (false).
     * @param int $depth Maximum nesting depth for decoding.
     * @param int $flags JSON decode flags.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return mixed Decoded JSON structure.
     *
     * @throws CastException When the value is not stringable or contains invalid JSON.
     */
    public static function json(
        mixed $value,
        bool $assoc = true,
        int $depth = 512,
        int $flags = 0,
        ?string $field = null
    ): mixed {
        $json = self::string($value, $field);

        if ($depth < 1) {
            throw new CastException(self::formatMessage(
                'JSON depth must be >= 1',
                $value,
                $field
            ));
        }

        /** @var mixed $decoded */
        $decoded = json_decode($json, $assoc, $depth, $flags);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new CastException(self::formatMessage(
                'Invalid JSON: ' . json_last_error_msg(),
                $value,
                $field
            ));
        }

        return $decoded;
    }

    /**
     * Soft version of {@see json()} returning null instead of throwing on failure.
     *
     * @param mixed $value JSON payload.
     * @param bool $assoc Decode to associative array (true) or stdClass (false).
     * @param int $depth Maximum nesting depth for decoding.
     * @param int $flags JSON decode flags.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return mixed|null Decoded payload or null on error.
     */
    public static function tryJson(
        mixed $value,
        bool $assoc = true,
        int $depth = 512,
        int $flags = 0,
        ?string $field = null
    ): mixed {
        try {
            return self::json($value, $assoc, $depth, $flags, $field);
        } catch (CastException) {
            return null;
        }
    }

    // ==========================
    //      DATE / TIME
    // ==========================

    /**
     * Converts strings or existing {@see DateTimeInterface} instances to {@see DateTimeImmutable}.
     * When a format is provided it is used for parsing, otherwise DateTimeImmutable falls back to
     * PHP's default parsing rules. Example: `$createdAt = Caster::dateTimeImmutable($payload['created_at']);`
     *
     * @param mixed $value ISO-like string, timestamp string, {@see DateTimeInterface} or {@see DateTimeImmutable}.
     * @param string|null $field Optional field identifier added to exception messages.
     * @param string|null $format Explicit format used with {@see DateTimeImmutable::createFromFormat()}.
     * @return DateTimeImmutable Parsed immutable datetime object.
     *
     * @throws CastException When parsing fails or the value is not a string/date instance.
     */
    public static function dateTimeImmutable(
        mixed $value,
        ?string $field = null,
        ?string $format = null
    ): DateTimeImmutable {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (!is_string($value)) {
            throw self::invalidType('datetime string', $value, $field);
        }

        if ($format !== null) {
            $dt = DateTimeImmutable::createFromFormat($format, $value);
            if ($dt === false) {
                throw new CastException(self::formatMessage(
                    "Invalid datetime format '{$format}'",
                    $value,
                    $field
                ));
            }

            return $dt;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            throw new CastException(self::formatMessage(
                'Unable to parse datetime',
                $value,
                $field
            ));
        }
    }

    /**
     * Soft version of {@see dateTimeImmutable()} returning null when parsing fails.
     *
     * @param mixed $value Value to cast.
     * @param string|null $field Optional field identifier added to exception messages.
     * @param string|null $format Explicit format used with {@see DateTimeImmutable::createFromFormat()}.
     * @return DateTimeImmutable|null Parsed datetime or null.
     */
    public static function tryDateTimeImmutable(
        mixed $value,
        ?string $field = null,
        ?string $format = null
    ): ?DateTimeImmutable {
        try {
            return self::dateTimeImmutable($value, $field, $format);
        } catch (CastException) {
            return null;
        }
    }

    /**
     * Returns null for empty inputs (null/whitespace), otherwise delegates to {@see dateTimeImmutable()}.
     * Example: `$expiresAt = Caster::nullableDateTimeImmutable($payload['expires_at'] ?? null); // ?DateTimeImmutable`
     *
     * @param mixed $value Nullable string or {@see DateTimeInterface} instance.
     * @param string|null $field Optional field identifier added to exception messages.
     * @param string|null $format Explicit format used with {@see DateTimeImmutable::createFromFormat()}.
     * @return DateTimeImmutable|null Immutable datetime or null when input empty.
     *
     * @throws CastException When the non-null value cannot be converted to {@see DateTimeImmutable}.
     */
    public static function nullableDateTimeImmutable(
        mixed $value,
        ?string $field = null,
        ?string $format = null
    ): ?DateTimeImmutable {
        if (self::isEmptyInput($value)) {
            return null;
        }

        return self::dateTimeImmutable($value, $field, $format);
    }

    // ==========================
    //          ENUM
    // ==========================

    /**
     * @template T of BackedEnum
     *
     * Casts a scalar or BackedEnum instance to a concrete enum case of the provided class.
     * Example: `$status = Caster::enum(Status::class, $payload['status']); // Status`
     *
     * @param class-string<T> $enumClass Fully-qualified enum class that implements {@see BackedEnum}.
     * @param mixed $value Enum case instance or scalar backing value (int|string) to convert.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return T Enum case of the provided class.
     *
     * @throws CastException When the enum class is missing or the value is not part of the enum.
     */
    public static function enum(string $enumClass, mixed $value, ?string $field = null): BackedEnum
    {
        if (!enum_exists($enumClass)) {
            throw new CastException("Enum {$enumClass} does not exist.");
        }

        if ($value instanceof $enumClass) {
            /** @var T $value */
            return $value;
        }

        if (!is_int($value) && !is_string($value)) {
            throw self::invalidType("int|string enum backing value", $value, $field);
        }

        /** @var int|string $backingValue */
        $backingValue = $value;

        /** @var T|null $result */
        $result = $enumClass::tryFrom($backingValue);

        if ($result === null) {
            throw new CastException(self::formatMessage(
                "Invalid value for enum {$enumClass}",
                $value,
                $field
            ));
        }

        return $result;
    }

    /**
     * Soft version of {@see enum()} returning null when validation fails.
     *
     * @template T of BackedEnum
     * @param class-string<T> $enumClass Enum class name.
     * @param mixed $value Value to cast.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return T|null Enum case or null.
     */
    public static function tryEnum(string $enumClass, mixed $value, ?string $field = null): ?BackedEnum
    {
        try {
            return self::enum($enumClass, $value, $field);
        } catch (CastException) {
            return null;
        }
    }

    /**
     * @template T of BackedEnum
     *
     * Nullable variant of {@see enum()} that treats null/whitespace strings as null.
     * Example: `$status = Caster::nullableEnum(Status::class, $payload['status'] ?? null); // ?Status`
     *
     * @param class-string<T> $enumClass Fully-qualified enum class that implements {@see BackedEnum}.
     * @param mixed $value Nullable enum case instance or scalar backing value (int|string).
     * @param string|null $field Optional field identifier added to exception messages.
     * @return T|null Enum case or null for empty inputs.
     */
    public static function nullableEnum(string $enumClass, mixed $value, ?string $field = null): ?BackedEnum
    {
        if (self::isEmptyInput($value)) {
            return null;
        }

        return self::enum($enumClass, $value, $field);
    }

    // ==========================
    //        MASSIVES
    // ==========================

    /**
     * @template T
     *
     * Applies the provided caster to every item of an array, reindexing the result sequentially.
     * Example: `$ids = Caster::listOf($payload['ids'], static fn ($v) => Caster::int($v)); // array<int,int>`
     *
     * @param mixed $value Input expected to be an array with numeric keys or sequential semantics.
     * @param callable(mixed):T $caster Callback applied to every element in the input array.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return array<int,T> Sequential array of casted values.
     *
     * @throws CastException When the provided value is not an array.
     */
    public static function listOf(mixed $value, callable $caster, ?string $field = null): array
    {
        if (!is_array($value)) {
            throw self::invalidType('array', $value, $field);
        }

        $result = [];
        foreach ($value as $item) {
            $result[] = $caster($item);
        }

        return $result;
    }

    /**
     * @template T
     *
     * Nullable variant of {@see listOf()}, returning null when the input is null/whitespace.
     * Example:
     * `$ids = Caster::nullableListOf($payload['ids'] ?? null, static fn ($v) => Caster::int($v));`
     * `// ?array<int,int>`
     *
     * @param mixed $value Array or null/whitespace to treat as empty.
     * @param callable(mixed):T $caster Callback applied to every element in the input array.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return array<int,T>|null Sequential array or null.
     */
    public static function nullableListOf(mixed $value, callable $caster, ?string $field = null): ?array
    {
        if (self::isEmptyInput($value)) {
            return null;
        }

        return self::listOf($value, $caster, $field);
    }

    /**
     * Soft version of {@see listOf()} returning null when input is invalid.
     *
     * @template T
     * @param mixed $value Input expected to be an array.
     * @param callable(mixed):T $caster Callback applied to each item.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return array<int,T>|null Sequential array or null.
     */
    public static function tryListOf(mixed $value, callable $caster, ?string $field = null): ?array
    {
        try {
            return self::listOf($value, $caster, $field);
        } catch (CastException) {
            return null;
        }
    }

    /**
     * @template T
     *
     * Applies the provided caster to every item of an associative array, preserving keys.
     * Example: `$typedMap = Caster::arrayOf($map, static fn ($v) => Caster::string($v)); // array<string,string>`
     *
     * @param mixed $value Input expected to be an array with string/int keys.
     * @param callable(mixed):T $caster Callback applied to each value in the array.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return array<string|int,T> Key-preserving array with casted values.
     *
     * @throws CastException When the provided value is not an array.
     */
    public static function arrayOf(mixed $value, callable $caster, ?string $field = null): array
    {
        if (!is_array($value)) {
            throw self::invalidType('array', $value, $field);
        }

        $result = [];
        foreach ($value as $k => $v) {
            $result[$k] = $caster($v);
        }

        return $result;
    }

    /**
     * @template T
     *
     * Nullable variant of {@see arrayOf()}, returning null when the input is null/whitespace.
     * Example:
     * `$map = Caster::nullableArrayOf($payload['meta'] ?? null, static fn ($v) => Caster::string($v));`
     * `// ?array<string,string>`
     *
     * @param mixed $value Array or null/whitespace to treat as empty.
     * @param callable(mixed):T $caster Callback applied to each value in the array.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return array<string|int,T>|null Key-preserving array or null.
     */
    public static function nullableArrayOf(mixed $value, callable $caster, ?string $field = null): ?array
    {
        if (self::isEmptyInput($value)) {
            return null;
        }

        return self::arrayOf($value, $caster, $field);
    }

    /**
     * Soft version of {@see arrayOf()} returning null when input is invalid.
     *
     * @template T
     * @param mixed $value Input expected to be an array.
     * @param callable(mixed):T $caster Callback applied to each value.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return array<string|int,T>|null Key-preserving array or null.
     */
    public static function tryArrayOf(mixed $value, callable $caster, ?string $field = null): ?array
    {
        try {
            return self::arrayOf($value, $caster, $field);
        } catch (CastException) {
            return null;
        }
    }

    // ==========================
    //         SERVICE
    // ==========================

    /**
     * Ensures a value is present (non-null), typically to guard external payload fields.
     * Example: `$email = Caster::required($payload['email'] ?? null, 'email'); // string`
     *
     * @param mixed $value Value that must not be null (content is not modified).
     * @param string $field Human-readable field identifier for exception messages.
     * @return mixed Original value when it is present.
     *
     * @throws MissingValueException When the value is missing.
     */
    public static function required(mixed $value, string $field): mixed
    {
        if ($value === null) {
            throw MissingValueException::forField($field);
        }

        return $value;
    }

    /**
     * Ensures the value is a non-empty string (after optional trimming).
     * Example: `$name = Caster::requiredNonEmptyString($payload['name'] ?? null, 'name'); // string`
     *
     * @param mixed $value Value that should represent a non-empty string.
     * @param string $field Field identifier for exception messages.
     * @param bool $trim Whether to trim before validating.
     * @return string Non-empty (optionally trimmed) string.
     *
     * @throws MissingValueException When the value is null.
     * @throws CastException When the value is empty or not stringable.
     */
    public static function requiredNonEmptyString(mixed $value, string $field, bool $trim = false): string
    {
        $required = self::required($value, $field);

        return $trim
            ? self::nonEmptyTrimmedString($required, $field)
            : self::nonEmptyString($required, $field);
    }

    /**
     * Ensures the value is a non-empty array and applies the provided caster to each element.
     * Example:
     * `$tags = Caster::requiredNonEmptyList($payload['tags'] ?? null, 'tags', static fn ($v) => Caster::string($v));`
     *
     * @template T
     * @param mixed $value Value expected to be an array.
     * @param string $field Field identifier for exception messages.
     * @param callable(mixed):T $caster Caster applied to each element.
     * @return array<int,T> Non-empty sequential array.
     *
     * @throws MissingValueException When the value is null.
     * @throws CastException When the value is not an array or becomes empty after casting.
     */
    public static function requiredNonEmptyList(mixed $value, string $field, callable $caster): array
    {
        $required = self::required($value, $field);
        $list = self::listOf($required, $caster, $field);

        if ($list === []) {
            throw new CastException("Expected non-empty list for '{$field}'.");
        }

        return $list;
    }

    /**
     * Ensures the value is a non-empty associative array and applies the provided caster to each value.
     * Example:
     * `$meta = Caster::requiredNonEmptyArray($payload['meta'] ?? null, 'meta', static fn ($v) => Caster::string($v));`
     *
     * @template T
     * @param mixed $value Value expected to be an array.
     * @param string $field Field identifier for exception messages.
     * @param callable(mixed):T $caster Caster applied to each value.
     * @return array<string|int,T> Non-empty associative array.
     *
     * @throws MissingValueException When the value is null.
     * @throws CastException When the value is not an array or becomes empty after casting.
     */
    public static function requiredNonEmptyArray(mixed $value, string $field, callable $caster): array
    {
        $required = self::required($value, $field);
        $array = self::arrayOf($required, $caster, $field);

        if ($array === []) {
            throw new CastException("Expected non-empty array for '{$field}'.");
        }

        return $array;
    }

    /**
     * Helper for building consistent CastException messages.
     *
     * @param string $expected Human-readable description of the expected value.
     * @param mixed $actual Actual value received.
     * @param string|null $field Optional field identifier added to exception messages.
     * @return CastException Consistent exception instance.
     */
    private static function invalidType(string $expected, mixed $actual, ?string $field): CastException
    {
        return new CastException(self::formatMessage(
            "Expected {$expected}",
            $actual,
            $field
        ));
    }

    /**
     * Formats the error message with the failing field and PHP debug type.
     *
     * @param string $base Base message text (e.g. "Expected int").
     * @param mixed $value Actual value that failed validation.
     * @param string|null $field Optional field identifier appended to the message.
     * @return string Human-readable message.
     */
    private static function formatMessage(string $base, mixed $value, ?string $field): string
    {
        $debugType = get_debug_type($value);
        $fieldPart = $field !== null ? " for '{$field}'" : '';

        return "{$base}{$fieldPart}, got {$debugType}.";
    }

    /**
     * Treats null and whitespace-only strings as "empty" inputs for nullable casters.
     *
     * @param mixed $value Input to inspect.
     * @return bool True when the input should be treated as missing.
     */
    private static function isEmptyInput(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value) && trim($value) === '') {
            return true;
        }

        return false;
    }

    // ==========================
    //      ARRAY PAYLOAD
    // ==========================

    /**
     * Reads a required field from an array payload and casts it using the provided caster.
     * Example: `$id = Caster::fromArray($payload, 'id', static fn ($v) => Caster::positiveInt($v));`
     *
     * @template T
     * @param array<string|int,mixed> $payload Source payload.
     * @param string|int $key Field key to read.
     * @param callable(mixed):T $caster Caster applied to the extracted value.
     * @param string|null $field Optional override for error messages.
     * @return T Casted value.
     *
     * @throws MissingValueException When the field is absent.
     * @throws CastException When the caster fails.
     */
    public static function fromArray(
        array $payload,
        string|int $key,
        callable $caster,
        ?string $field = null
    ): mixed {
        if (!array_key_exists($key, $payload)) {
            throw MissingValueException::forField($field ?? (string) $key);
        }

        return $caster($payload[$key]);
    }

    /**
     * Reads an optional field from an array payload and casts it when present.
     * Example: `$name = Caster::fromArrayNullable($payload, 'name', [Caster::class, 'nonEmptyTrimmedString']);`
     *
     * @template T
     * @param array<string|int,mixed> $payload Source payload.
     * @param string|int $key Field key to read.
     * @param callable(mixed):T $caster Caster applied to the extracted value.
     * @param string|null $field Optional override for error messages.
     * @return T|null Casted value or null when the field is missing/empty.
     *
     * @throws CastException When the caster fails.
     */
    public static function fromArrayNullable(
        array $payload,
        string|int $key,
        callable $caster,
        ?string $field = null
    ): mixed {
        if (!array_key_exists($key, $payload)) {
            return null;
        }

        $value = $payload[$key];
        if (self::isEmptyInput($value)) {
            return null;
        }

        return $caster($value);
    }
}
