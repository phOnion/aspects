<?php declare(strict_types=1);
namespace Onion\Framework\Aspects;

use Onion\Framework\Aspects\Interfaces\PropertyAccessInvocationInterface as InvocationInterface;

class PropertyAccess extends Invocation implements InvocationInterface
{
    public function getPropertyName(): string
    {
        return $this->getParameters()['name'];
    }

    public function getOperation(): string
    {
        switch (strtolower($this->getMethodName())) {
            default:
            case '__get':
                $operation = self::ACCESS_GET;
                break;
            case '__set':
                $operation = self::ACCESS_SET;
                break;
            case '__unset':
                $operation = self::ACCESS_UNSET;
                break;
            case '__isset':
                $operation = self::ACCESS_ISSET;
                break;
        }

        return $operation;
    }

    public function getCurrentValue()
    {
        return $this->getTarget()->{
            $this->getPropertyName()
        };
    }

    public function getNewValue()
    {
        return $this->getParameters()['value'] ?? null;
    }
}
