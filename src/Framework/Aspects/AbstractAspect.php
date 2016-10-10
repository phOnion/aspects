<?php
/**
 * PHP Version 5.6.0
 *
 * @category Unknown Category
 * @package  Onion\Framework\Aspects
 * @author   Dimitar Dimitrov <daghostman.dd@gmail.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/phOnion/framework
 */
namespace Onion\Framework\Aspects;

use Onion\Framework\Annotations\Interfaces\AnnotationInterface;
use Onion\Framework\Aspects\Interfaces\AspectInterface;
use Onion\Framework\Aspects\Interfaces\InvocationInterface;

abstract class AbstractAspect implements AspectInterface
{
    public function beforeMethod(AnnotationInterface $annotation, InvocationInterface $invocation)
    {
        return null;
    }

    public function afterMethod(AnnotationInterface $annotation, InvocationInterface $invocation)
    {
        return null;
    }
}
