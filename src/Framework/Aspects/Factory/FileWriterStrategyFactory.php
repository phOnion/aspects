<?php declare(strict_types=1);
namespace Onion\Framework\Aspects\Factory;

use Onion\Framework\Dependency\Interfaces\FactoryInterface;
use ProxyManager\Configuration;
use ProxyManager\GeneratorStrategy\GeneratorStrategyInterface;
use ProxyManager\GeneratorStrategy\FileWriterGeneratorStrategy;
use ProxyManager\FileLocator\FileLocator;

class FileWriterStrategyFactory implements FactoryInterface
{
    public function build(\Psr\Container\ContainerInterface $container): GeneratorStrategyInterface
    {
        return new FileWriterGeneratorStrategy(
            new FileLocator($container->get('application.proxy.save_dir'))
        );
    }
}
