<?php declare(strict_types=1);
namespace Onion\Framework\Aspects\Method\Interfaces;

use Onion\Framework\Annotations\Interfaces\AnnotationInterface;
use Onion\Framework\Aspects\Interfaces\InvocationInterface;

interface PreAspectInterface
{
    public function before(AnnotationInterface $annotation, InvocationInterface $invocation);
}
