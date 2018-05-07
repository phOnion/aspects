<?php declare(strict_types=1);
namespace Onion\Framework\Aspects;

use Onion\Framework\Aspects\Interfaces\PropertyAccessInterface;

class PropertyAccess implements PropertyAccessInterface
{
    private $target;
    private $name;
    private $value;
    private $newValue;

    private $earlyReturn;

    public function __construct(object $target, string $name, $value, $newValue)
    {
        $this->target = $target;
        $this->name = $name;
        $this->value = $value;
        $this->newValue = $newValue;
    }

    public function getTarget(): object
    {
        return $this->target;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCurrentValue()
    {
        return $this->value;
    }

    public function getNewValue()
    {
        return $this->newValue;
    }

    public function isReturnEarly(): bool
    {
        return (bool) $this->earlyReturn;
    }

    public function setEarlyReturn(bool $earlyReturn = true): void
    {
        $this->earlyReturn = $earlyReturn;
    }
}
