<?php declare(strict_types=1);
namespace Onion\Framework\Aspects\Interfaces;

interface InvocationInterface
{
    public function getTarget(): object;
    public function getMethodName(): string;
    public function getParameters(): array;
    public function getReturnValue();

    public function continue();
    public function exit(): void;
}
