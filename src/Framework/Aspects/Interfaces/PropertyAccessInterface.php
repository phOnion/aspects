<?php declare(strict_types=1);
namespace Onion\Framework\Aspects\Interfaces;

interface PropertyAccessInterface
{
    public function getTarget(): object;
    public function getName(): string;
    public function getCurrentValue();
    public function getNewValue();

    public function isReturnEarly(): bool;

    public function setEarlyReturn(bool $earlyReturn = true): void;
}
