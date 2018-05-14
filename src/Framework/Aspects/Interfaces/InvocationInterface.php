<?php declare(strict_types=1);
namespace Onion\Framework\Aspects\Interfaces;

interface InvocationInterface
{
    public function continue();
    public function getTarget();
    public function getMethodName();
    public function getParameters();

    public function exit();
}
