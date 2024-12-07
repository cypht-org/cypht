<?php

namespace Services\Traits;

use ReflectionClass;
use ReflectionProperty;

trait Hm_Serializes
{
    /**
     * Prepare the instance for serialization.
     *
     * @return array
     */
    public function __sleep()
    {
        $properties = (new ReflectionClass($this))->getProperties();

        $values = [];

        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $property->setAccessible(true);
            $name = $property->getName();

            // Avoid serializing if it has a default value
            if ($property->hasDefaultValue() && $property->getValue($this) === $property->getDefaultValue()) {
                continue;
            }

            $values[$name] = $property->getValue($this);
        }

        return array_keys($values);
    }

    /**
     * Restore the model after serialization.
     *
     * @return void
     */
    public function __wakeup()
    {
        foreach ((new ReflectionClass($this))->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $property->setAccessible(true);
            // Restore the property using its current value
            $property->setValue($this, $this->getRestoredPropertyValue(
                $this->getPropertyValue($property)
            ));
        }
    }

    /**
     * Restore the model after serialization (PHP 7+).
     *
     * @return array
     */
    public function __serialize(): array
    {
        $values = [];
        $properties = (new ReflectionClass($this))->getProperties();

        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $property->setAccessible(true);

            if ($property->isInitialized($this)) {
                $values[$property->getName()] = $property->getValue($this);
            }
        }

        return $values;
    }

    public function __unserialize(array $data): void
    {
        $properties = (new ReflectionClass($this))->getProperties();

        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $property->setAccessible(true);

            if (array_key_exists($property->getName(), $data)) {
                $property->setValue($this, $data[$property->getName()]);
            }
        }
    }

    /**
     * Get the property value for the given property.
     *
     * @param ReflectionProperty $property
     * @return mixed
     */
    protected function getPropertyValue(ReflectionProperty $property)
    {
        $property->setAccessible(true);
        return $property->getValue($this);
    }

    /**
     * Placeholder method for restoring property value.
     * You can customize this based on your needs.
     *
     * @param mixed $value
     * @return mixed
     */
    protected function getRestoredPropertyValue($value)
    {
        // Implement any custom restoration logic if needed
        return $value;
    }
}
