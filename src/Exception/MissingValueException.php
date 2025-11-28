<?php

namespace Nitier\Caster\Exception;

class MissingValueException extends CastException
{
    public static function forField(string $field): self
    {
        return new self("Missing required value for '{$field}'.");
    }
}
