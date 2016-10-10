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

use Onion\Framework\Aspects\Interfaces\InvocationInterface;

class Invocation implements InvocationInterface
{
    private $instance;
    private $methodName;
    private $arguments;
    private $returnValue;
    private $returnEarly = false;

    public function __construct($instance, $methodName, $arguments, $returnValue)
    {
        $this->instance = $instance;
        $this->methodName = $methodName;
        $this->arguments = $arguments;
        $this->returnValue = $returnValue;
    }

    public function getObject()
    {
        return $this->instance;
    }

    public function getMethodName()
    {
        return $this->methodName;
    }

    public function getArguments()
    {
        return $this->arguments;
    }

    public function getReturnValue()
    {
        return $this->returnValue;
    }

    public function isReturnEarly()
    {
        return (bool) $this->returnEarly;
    }

    public function setReturnEarly($state)
    {
        $this->returnEarly = $state;
    }
}
