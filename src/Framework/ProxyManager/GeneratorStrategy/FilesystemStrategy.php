<?php
/**
 * PHP Version 5.6.0
 *
 * @category Unknown Category
 * @package  Onion\Framework\ProxyManager\GeneratorStrategy
 * @author   Dimitar Dimitrov <daghostman.dd@gmail.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/phOnion/framework
 */
namespace Onion\Framework\ProxyManager\GeneratorStrategy;

use ProxyManager\GeneratorStrategy\FileWriterGeneratorStrategy;
class FilesystemStrategy extends FileWriterGeneratorStrategy
{
    public function __sleep()
    {
        return array_diff(array_keys(get_object_vars($this)), ['emptyErrorHandler']);
    }
}
