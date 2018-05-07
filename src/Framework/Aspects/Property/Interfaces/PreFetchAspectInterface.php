<?php declare(strict_types=1);
namespace Onion\Framework\Aspects\Property\Interfaces;

use Onion\Framework\Annotations\Interfaces\AnnotationInterface;
use Onion\Framework\Aspects\Interfaces\PropertyAccessInterface;

interface PreFetchAspectInterface
{
    public function before(AnnotationInterface $annotation, PropertyAccessInterface $access);
}
