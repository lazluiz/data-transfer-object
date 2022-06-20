<?php

namespace Spatie\DataTransferObject\Casters;

use LogicException;
use Spatie\DataTransferObject\Caster;

class EnumCaster implements Caster
{
    public function __construct(
        private array $types,
        private string $enumType
    ) {
    }

    public function cast(mixed $value): mixed
    {
        if (! is_subclass_of($this->enumType, 'BackedEnum')) {
            throw new LogicException("Caster [EnumCaster] may only be used to cast backed enums. Received [$this->enumType].");
        }

        // check if value is already of the enum type
        if (is_object($value) && enum_exists($value::class)) {
            $castedValue = $value;
        } else {
            $castedValue = $this->enumType::tryFrom($value);
        }

        if ($castedValue === null) {
            throw new LogicException("Couldn't cast enum [$this->enumType] with value [$value]");
        }

        return $castedValue;
    }
}
