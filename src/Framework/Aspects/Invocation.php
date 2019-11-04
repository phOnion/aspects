<?php declare(strict_types=1);
namespace Onion\Framework\Aspects;

use Onion\Framework\Aspects\Interfaces\InvocationInterface;
use Onion\Framework\Annotations\Interfaces\AnnotationInterface;

class Invocation implements InvocationInterface
{
    /** @var \Iterator */
    private $callbacks = [];

    private $instance;
    private $method;
    private $params;
    private $earlyReturn;
    private $returnValue;

    public function __construct(array $target, array $params, array $callbacks, &$earlyReturn, $returnValue = null)
    {
        list($this->instance, $this->method) = $target;
        $this->params = $params;

        $this->callbacks = $callbacks;
        $this->earlyReturn = &$earlyReturn;
        $this->returnValue = $returnValue;
    }

    public function getMethodName(): string
    {
        return $this->method;
    }

    public function getTarget(): object
    {
        return $this->instance;
    }

    public function getParameters(): array
    {
        return $this->params;
    }

    public function getReturnValue()
    {
        return $this->returnValue;
    }

    public function exit(): void
    {
        $this->earlyReturn = true;
    }

    public function continue()
    {
        if (!empty($this->callbacks)) {
            return (array_shift($this->callbacks))($this);
        }
    }
}
