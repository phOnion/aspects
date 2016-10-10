<?php
/**
 * PHP Version 5.6.0
 *
 * @category Unknown Category
 * @package  Onion\Framework\Aspects\Interfaces
 * @author   Dimitar Dimitrov <daghostman.dd@gmail.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/phOnion/framework
 */

namespace Onion\Framework\Aspects\Interfaces;


use Onion\Framework\Annotations\Interfaces\AnnotationInterface;

interface AspectInterface
{
    public function beforeMethod(AnnotationInterface $annotation, InvocationInterface $invocation);
    public function afterMethod(AnnotationInterface $annotation, InvocationInterface $invocation);
}
