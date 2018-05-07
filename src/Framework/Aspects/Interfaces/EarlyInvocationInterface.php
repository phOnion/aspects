<?php declare(strict_types=1);
namespace Onion\Framework\Aspects\Interfaces;

interface EarlyInvocationInterface
{
    public function getTarget(): object;
    public function getMethodName(): string;
    public function getArguments(): iterable;

    public function setReturnEarly(bool $returnEarly = true): void;
}
