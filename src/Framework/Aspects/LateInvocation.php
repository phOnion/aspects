<?php declare(strict_types=1);
namespace Onion\Framework\Aspects;

use Onion\Framework\Aspects\Interfaces\LateInvocationInterface;

class LateInvocation extends EarlyInvocation implements LateInvocationInterface
{
    private $returnValue;
    public function __construct(
        object $target,
        string $method,
        iterable $arguments,
        bool $returnEarly,
        $returnValue
    ) {
        parent::__construct($target, $method, $arguments, $returnEarly);
        $this->returnValue = $returnValue;
    }

    public function getReturnValue()
    {
        return $this->returnValue;
    }
}
