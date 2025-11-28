<?php

declare(strict_types=1);

namespace Test;

use DateTime;
use DateTimeImmutable;
use Nitier\Caster\Caster;
use Nitier\Caster\Exception\CastException;
use Nitier\Caster\Exception\MissingValueException;
use PHPUnit\Framework\TestCase;

enum TestStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}

final class CasterTest extends TestCase
{
    public function testIntAcceptsNumericStrings(): void
    {
        self::assertSame(42, Caster::int('42'));
    }

    public function testIntRejectsInvalidStrings(): void
    {
        $this->expectException(CastException::class);
        Caster::int('nope');
    }

    public function testIntHandlesNegativeStrings(): void
    {
        self::assertSame(-7, Caster::int('-7'));
    }

    public function testIntCastsBooleans(): void
    {
        self::assertSame(1, Caster::int(true));
        self::assertSame(0, Caster::int(false));
    }

    public function testIntAcceptsWholeFloat(): void
    {
        self::assertSame(10, Caster::int(10.0));
    }

    public function testIntRejectsNonIntegralFloat(): void
    {
        $this->expectException(CastException::class);
        Caster::int(10.5);
    }

    public function testNullableIntReturnsNullForEmptyValue(): void
    {
        self::assertNull(Caster::nullableInt(''));
    }

    public function testNullableIntAllowsZeroString(): void
    {
        self::assertSame(0, Caster::nullableInt('0'));
    }

    public function testNullableIntTreatsWhitespaceAsNull(): void
    {
        self::assertNull(Caster::nullableInt("  \n  "));
    }

    public function testTryIntReturnsNullOnInvalidInput(): void
    {
        self::assertNull(Caster::tryInt('oops'));
    }

    public function testTryIntReturnsValueOnSuccess(): void
    {
        self::assertSame(15, Caster::tryInt('15'));
    }

    public function testPositiveIntCastsString(): void
    {
        self::assertSame(5, Caster::positiveInt('5'));
    }

    public function testPositiveIntRejectsZero(): void
    {
        $this->expectException(CastException::class);
        Caster::positiveInt(0);
    }

    public function testNonNegativeIntAllowsZero(): void
    {
        self::assertSame(0, Caster::nonNegativeInt('0'));
    }

    public function testNonNegativeIntRejectsNegative(): void
    {
        $this->expectException(CastException::class);
        Caster::nonNegativeInt(-1);
    }

    public function testIntBetweenValidatesBounds(): void
    {
        self::assertSame(10, Caster::intBetween('10', 5, 15));
    }

    public function testIntBetweenRejectsValueOutsideRange(): void
    {
        $this->expectException(CastException::class);
        Caster::intBetween(3, 5, 10);
    }

    public function testFloatCastsNumericString(): void
    {
        self::assertSame(1.25, Caster::float('1.25'));
    }

    public function testFloatCastsBoolean(): void
    {
        self::assertSame(1.0, Caster::float(true));
        self::assertSame(0.0, Caster::float(false));
    }

    public function testFloatRejectsAlphabeticString(): void
    {
        $this->expectException(CastException::class);
        Caster::float('abc');
    }

    public function testFloatUnderstandsInfinityTokens(): void
    {
        self::assertSame(INF, Caster::float('INF'));
        self::assertSame(-INF, Caster::float(' -inf '));
    }

    public function testFloatUnderstandsNanToken(): void
    {
        self::assertTrue(is_nan(Caster::float('NaN')));
    }

    public function testNullableFloatHandlesNull(): void
    {
        self::assertNull(Caster::nullableFloat(null));
    }

    public function testNullableFloatTreatsWhitespaceAsNull(): void
    {
        self::assertNull(Caster::nullableFloat("\t "));
    }

    public function testTryFloatReturnsNullOnInvalid(): void
    {
        self::assertNull(Caster::tryFloat('foo'));
    }

    public function testTryFloatReturnsValueOnSuccess(): void
    {
        self::assertSame(2.5, Caster::tryFloat('2.5'));
    }

    public function testBoolUnderstandsCommonStrings(): void
    {
        self::assertTrue(Caster::bool('yes'));
        self::assertFalse(Caster::bool('off'));
    }

    public function testBoolAcceptsUpperCaseInput(): void
    {
        self::assertTrue(Caster::bool('TRUE'));
    }

    public function testBoolRejectsUnknownString(): void
    {
        $this->expectException(CastException::class);
        Caster::bool('maybe');
    }

    public function testNullableBoolTreatsWhitespaceAsNull(): void
    {
        self::assertNull(Caster::nullableBool("   "));
    }

    public function testTryBoolReturnsNullOnInvalid(): void
    {
        self::assertNull(Caster::tryBool('maybe'));
    }

    public function testTryBoolReturnsValueOnSuccess(): void
    {
        self::assertTrue(Caster::tryBool('true'));
    }

    public function testStringCastsScalar(): void
    {
        self::assertSame('1', Caster::string(true));
    }

    public function testStringRejectsArrays(): void
    {
        $this->expectException(CastException::class);
        Caster::string(['foo']);
    }

    public function testTryStringReturnsNullOnInvalid(): void
    {
        self::assertNull(Caster::tryString(['bar']));
    }

    public function testStringCastsInfinityFloatsToTokens(): void
    {
        self::assertSame('INF', Caster::string(INF));
        self::assertSame('-INF', Caster::string(-INF));
    }

    public function testStringCastsNanFloatToToken(): void
    {
        $result = Caster::string(NAN);
        self::assertSame('NAN', $result);
    }

    public function testTrimmedStringRemovesWhitespace(): void
    {
        self::assertSame('foo', Caster::trimmedString("  foo \n"));
    }

    public function testNonEmptyTrimmedStringRejectsWhitespaceOnly(): void
    {
        $this->expectException(CastException::class);
        Caster::nonEmptyTrimmedString("\t");
    }

    public function testTryNonEmptyTrimmedStringReturnsNullOnWhitespace(): void
    {
        self::assertNull(Caster::tryNonEmptyTrimmedString("\t"));
    }

    public function testLowerStringUsesLowercase(): void
    {
        self::assertSame('abc', Caster::lowerString('ABC'));
    }

    public function testUpperStringUsesUppercase(): void
    {
        self::assertSame('XYZ', Caster::upperString('xyz'));
    }

    public function testJsonDecodesAssociativeByDefault(): void
    {
        $decoded = Caster::json('{"a":1,"b":true}');
        self::assertSame(['a' => 1, 'b' => true], $decoded);
    }

    public function testJsonThrowsOnInvalidPayload(): void
    {
        $this->expectException(CastException::class);
        Caster::json('{"broken":');
    }

    public function testTryJsonReturnsNullOnInvalidPayload(): void
    {
        self::assertNull(Caster::tryJson('{"broken":'));
    }

    public function testNonEmptyStringRejectsEmpty(): void
    {
        $this->expectException(CastException::class);
        Caster::nonEmptyString('');
    }

    public function testTryNonEmptyStringReturnsNullOnEmpty(): void
    {
        self::assertNull(Caster::tryNonEmptyString(''));
    }

    public function testNullableStringReturnsNullForEmpty(): void
    {
        self::assertNull(Caster::nullableString(''));
    }

    public function testNullableStringTreatsWhitespaceAsNull(): void
    {
        self::assertNull(Caster::nullableString("\n"));
    }

    public function testNullableDateTimeImmutableReturnsNullForEmptyValue(): void
    {
        self::assertNull(Caster::nullableDateTimeImmutable(''));
    }

    public function testNullableDateTimeImmutableTreatsWhitespaceAsNull(): void
    {
        self::assertNull(Caster::nullableDateTimeImmutable("  "));
    }

    public function testDateTimeImmutableAcceptsMutableInstance(): void
    {
        $mutable = new DateTime('2024-01-01 00:00:00');
        $immutable = Caster::dateTimeImmutable($mutable);
        self::assertInstanceOf(DateTimeImmutable::class, $immutable);
        self::assertSame('2024-01-01 00:00:00', $immutable->format('Y-m-d H:i:s'));
    }

    public function testDateTimeImmutableWithFormat(): void
    {
        $value = Caster::dateTimeImmutable('2024-08-10 12:34:00', null, 'Y-m-d H:i:s');
        self::assertInstanceOf(DateTimeImmutable::class, $value);
        self::assertSame('2024-08-10 12:34:00', $value->format('Y-m-d H:i:s'));
    }

    public function testDateTimeImmutableRejectsInvalid(): void
    {
        $this->expectException(CastException::class);
        Caster::dateTimeImmutable('invalid date');
    }

    public function testTryDateTimeImmutableReturnsNullOnInvalid(): void
    {
        self::assertNull(Caster::tryDateTimeImmutable('invalid date'));
    }

    public function testEnumCastsScalarToEnumCase(): void
    {
        self::assertSame(TestStatus::Draft, Caster::enum(TestStatus::class, 'draft'));
    }

    public function testNullableEnumReturnsNullForEmpty(): void
    {
        self::assertNull(Caster::nullableEnum(TestStatus::class, ''));
    }

    public function testNullableEnumTreatsWhitespaceAsNull(): void
    {
        self::assertNull(Caster::nullableEnum(TestStatus::class, " \n "));
    }

    public function testTryEnumReturnsNullOnInvalid(): void
    {
        self::assertNull(Caster::tryEnum(TestStatus::class, 'archived'));
    }

    public function testEnumThrowsWhenValueUnknown(): void
    {
        $this->expectException(CastException::class);
        Caster::enum(TestStatus::class, 'archived');
    }

    public function testEnumThrowsWhenClassMissing(): void
    {
        $this->expectException(CastException::class);
        /** @phpstan-ignore-next-line */
        Caster::enum('Missing\\Enum\\Class', 'draft');
    }

    public function testListOfAppliesCasterToAllItems(): void
    {
        $result = Caster::listOf(['1', '2', '3'], static fn (mixed $value): int => Caster::int($value));
        self::assertSame([1, 2, 3], $result);
    }

    public function testListOfThrowsWhenValueIsNotArray(): void
    {
        $this->expectException(CastException::class);
        Caster::listOf('not-array', static fn (mixed $value): int => Caster::int($value));
    }

    public function testTryListOfReturnsNullWhenValueNotArray(): void
    {
        self::assertNull(Caster::tryListOf('not-array', static fn ($value) => $value));
    }

    public function testArrayOfPreservesKeys(): void
    {
        $result = Caster::arrayOf(
            ['a' => '1', 'b' => '2'],
            static fn (mixed $value): int => Caster::int($value)
        );

        self::assertSame(['a' => 1, 'b' => 2], $result);
    }

    public function testArrayOfNestedCaster(): void
    {
        $result = Caster::arrayOf(
            ['x' => 'true', 'y' => 'false'],
            static fn (mixed $value): bool => Caster::bool($value)
        );

        self::assertSame(['x' => true, 'y' => false], $result);
    }

    public function testTryArrayOfReturnsNullWhenValueNotArray(): void
    {
        self::assertNull(Caster::tryArrayOf('oops', static fn ($value) => $value));
    }

    public function testNullableListOfTreatsWhitespaceAsNull(): void
    {
        self::assertNull(Caster::nullableListOf("  ", static fn ($v) => $v));
    }

    public function testNullableArrayOfTreatsNullAsNull(): void
    {
        self::assertNull(Caster::nullableArrayOf(null, static fn ($v) => $v));
    }

    public function testRequiredThrowsForNull(): void
    {
        $this->expectException(MissingValueException::class);
        Caster::required(null, 'field');
    }

    public function testRequiredReturnsOriginalValue(): void
    {
        $value = 'foo';
        self::assertSame($value, Caster::required($value, 'field'));
    }

    public function testRequiredNonEmptyStringTrimsWhenRequested(): void
    {
        self::assertSame('foo', Caster::requiredNonEmptyString(" foo ", 'name', true));
    }

    public function testRequiredNonEmptyStringThrowsOnEmpty(): void
    {
        $this->expectException(CastException::class);
        Caster::requiredNonEmptyString('', 'name');
    }

    public function testRequiredNonEmptyListCastsElements(): void
    {
        $result = Caster::requiredNonEmptyList(['1', '2'], 'ids', static fn ($v) => Caster::int($v));
        self::assertSame([1, 2], $result);
    }

    public function testRequiredNonEmptyListThrowsOnEmpty(): void
    {
        $this->expectException(CastException::class);
        Caster::requiredNonEmptyList([], 'ids', static fn ($v) => $v);
    }

    public function testRequiredNonEmptyArrayCastsValues(): void
    {
        $result = Caster::requiredNonEmptyArray(['a' => '1'], 'meta', static fn ($v) => Caster::int($v));
        self::assertSame(['a' => 1], $result);
    }

    public function testRequiredNonEmptyArrayThrowsOnEmpty(): void
    {
        $this->expectException(CastException::class);
        Caster::requiredNonEmptyArray([], 'meta', static fn ($v) => $v);
    }

    public function testFromArrayCastsValue(): void
    {
        $payload = ['age' => '42'];
        $result = Caster::fromArray($payload, 'age', static fn (mixed $value): int => Caster::int($value));
        self::assertSame(42, $result);
    }

    public function testFromArrayThrowsWhenKeyMissing(): void
    {
        $this->expectException(MissingValueException::class);
        Caster::fromArray([], 'missing', static fn (): int => 1);
    }

    public function testFromArrayNullableReturnsNullWhenMissingOrEmpty(): void
    {
        $payload = ['name' => ''];
        self::assertNull(Caster::fromArrayNullable([], 'name', static fn ($v) => $v));
        self::assertNull(Caster::fromArrayNullable($payload, 'name', static fn ($v) => $v));
    }

    public function testFromArrayNullableCastsWhenValuePresent(): void
    {
        $payload = ['name' => 'John'];
        $result = Caster::fromArrayNullable($payload, 'name', static fn (mixed $value): string => Caster::string($value));
        self::assertSame('John', $result);
    }
}
