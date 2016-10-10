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

interface InvocationInterface
{
    public function getObject();
    public function getMethodName();
    public function getArguments();
    public function getReturnValue();

    public function isReturnEarly();

    public function setReturnEarly($state);

}
