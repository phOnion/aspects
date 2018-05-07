<?php declare(strict_types=1);
namespace Onion\Framework\Aspects;

use Onion\Framework\Aspects\Interfaces\EarlyInvocationInterface;

class EarlyInvocation implements EarlyInvocationInterface
{
    private $target;
    private $arguments = [];
    private $method;

    private $returnEarly;

    public function __construct(object $target, string $method, iterable $arguments, bool $returnEarly)
    {
        $this->target = $target;
        $this->method = $method;
        $this->arguments = $arguments;

        $this->returnEarly = $returnEarly;
    }

    public function getTarget(): object
    {
        return $this->target;
    }

    public function getArguments(): iterable
    {
        return $this->arguments ?? [];
    }

    public function getMethodName(): string
    {
        return $this->method;
    }

    public function isEarlyReturn(): bool
    {
        return (bool) $this->returnEarly;
    }

    public function setReturnEarly(bool $returnEarly = true): void
    {
        $this->returnEarly = $returnEarly;
    }
}
