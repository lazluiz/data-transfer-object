<?php

namespace Spatie\DataTransferObject;

use ReflectionClass;
use ReflectionProperty;
use Spatie\DataTransferObject\Attributes\CastWith;
use Spatie\DataTransferObject\Attributes\MapTo;
use Spatie\DataTransferObject\Casters\DataTransferObjectCaster;
use Spatie\DataTransferObject\Exceptions\UnknownProperties;
use Spatie\DataTransferObject\Reflection\DataTransferObjectClass;

#[CastWith(DataTransferObjectCaster::class)]
abstract class DataTransferObject
{
    protected array $exceptKeys = [];

    protected array $onlyKeys = [];

    public function __construct(...$args)
    {
        if (is_array($args[0] ?? null)) {
            $args = $args[0];
        }

        $class = new DataTransferObjectClass($this);

        foreach ($class->getProperties() as $property) {
            // if args doesn't have the property name, try getting from the variable name
            $value = Arr::get($args, $property->name, Arr::get($args, $property->getReflectionPropName(), $property->getDefaultValue()));
            $property->setValue($value);          

            $args = Arr::forget($args, $property->name);
        }

        if ($class->isStrict() && count($args)) {
            throw UnknownProperties::new(static::class, array_keys($args));
        }

        $class->validate();
    }

    public static function arrayOf(array $arrayOfParameters): array
    {
        return array_map(
            fn (mixed $parameters) => new static($parameters),
            $arrayOfParameters
        );
    }

    public function all(bool $keepOriginalKeys = false): array
    {
        $data = [];

        $class = new ReflectionClass(static::class);

        $properties = $class->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $mapToAttribute = $property->getAttributes(MapTo::class);
            $name = count($mapToAttribute) && ! $keepOriginalKeys ? $mapToAttribute[0]->newInstance()->name : $property->getName();

            $data[$name] = $property->getValue($this);
        }

        return $data;
    }

    public function only(string ...$keys): static
    {
        $dataTransferObject = clone $this;

        $dataTransferObject->onlyKeys = [...$this->onlyKeys, ...$keys];

        return $dataTransferObject;
    }

    public function except(string ...$keys): static
    {
        $dataTransferObject = clone $this;

        $dataTransferObject->exceptKeys = [...$this->exceptKeys, ...$keys];

        return $dataTransferObject;
    }

    public function clone(...$args): static
    {
        return new static(...array_merge($this->toArray(keepOriginalKeys: true), $args));
    }

    public function toArray(bool $keepOriginalKeys = false): array
    {
        if (count($this->onlyKeys)) {
            $array = Arr::only($this->all($keepOriginalKeys), $this->onlyKeys);
        } else {
            $array = Arr::except($this->all($keepOriginalKeys), $this->exceptKeys);
        }

        $array = $this->parseArray($array);

        return $array;
    }

    protected function parseArray(array $array): array
    {
        foreach ($array as $key => $value) {
            if ($value instanceof DataTransferObject) {
                $array[$key] = $value->toArray();

                continue;
            }

            if (! is_array($value)) {
                continue;
            }

            $array[$key] = $this->parseArray($value);
        }

        return $array;
    }
}
