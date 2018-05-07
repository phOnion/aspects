<?php declare(strict_types=1);
namespace Onion\Framework\Aspects\Interfaces;

interface LateInvocationInterface extends EarlyInvocationInterface
{
    public function getReturnValue();
}
