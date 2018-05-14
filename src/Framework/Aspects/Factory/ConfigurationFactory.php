<?php declare(strict_types=1);
namespace Onion\Framework\Aspects\Factory;

use Onion\Framework\Dependency\Interfaces\FactoryInterface;
use ProxyManager\Configuration;
use ProxyManager\GeneratorStrategy\GeneratorStrategyInterface;

class ConfigurationFactory implements FactoryInterface
{
    public function build(\Psr\Container\ContainerInterface $container): Configuration
    {
        $config = new Configuration;

        if ($container->has(GeneratorStrategyInterface::class)) {
            $config->setGeneratorStrategy($container->get(GeneratorStrategyInterface::class));
        }

        if ($container->has('application.proxy.save_dir')) {
            $config->setProxiesTargetDir($container->get('application.proxy.save_dir'));
        }

        if ($container->has('application.proxy.namespace')) {
            $config->setProxiesNamespace($container->get('application.proxy.namespace'));
        }
        spl_autoload_register($config->getProxyAutoloader(), true, true);

        return $config;
    }
}
