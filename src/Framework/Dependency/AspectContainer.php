<?php
/**
 * PHP Version 5.6.0
 *
 * @category Unknown Category
 * @package  Onion\Framework\Dependency
 * @author   Dimitar Dimitrov <daghostman.dd@gmail.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/phOnion/framework
 */
namespace Onion\Framework\Dependency;

use Doctrine\Common\Annotations\Reader;
use Onion\Framework\Annotations\Annotated;
use Onion\Framework\Aspects\EarlyInvocation;
use Onion\Framework\Aspects\Interfaces\AspectInterface;
use Onion\Framework\Aspects\Interfaces\InvocationInterface;
use Onion\Framework\Aspects\Interfaces\PostMethodAspectInterface;
use Onion\Framework\Aspects\Invocation;
use Onion\Framework\Aspects\LateInvocation;
use Onion\Framework\Aspects\Method\Interfaces\AroundAspectInterface;
use Onion\Framework\Aspects\Method\Interfaces\PostAspectInterface;
use Onion\Framework\Aspects\Method\Interfaces\PreAspectInterface;
use Onion\Framework\Aspects\Property\Interfaces\PostAssignAspectInterface;
use Onion\Framework\Aspects\Property\Interfaces\PostDeleteAspectInterface;
use Onion\Framework\Aspects\Property\Interfaces\PostFetchAspectInterface;
use Onion\Framework\Aspects\Property\Interfaces\PreAssignAspectInterface;
use Onion\Framework\Aspects\Property\Interfaces\PreDeleteAspectInterface;
use Onion\Framework\Aspects\Property\Interfaces\PreFetchAspectInterface;
use Onion\Framework\Aspects\PropertyAccess;
use Onion\Framework\Dependency\Exception;
use ProxyManager\Configuration;
use ProxyManager\Factory\AccessInterceptorScopeLocalizerFactory;
use ProxyManager\Factory\AccessInterceptorValueHolderFactory;
use Psr\Container\ContainerInterface;

class AspectContainer implements ContainerInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var Reader
     */
    protected $reader;

    /**
     * @var AccessInterceptorValueHolderFactory
     */
    protected $dependencyProxyFactory;

    /**
     * AspectContainer constructor.
     *
     * @param array         $definitions List of standard definitions array
     * @param Reader        $reader      The reader to use when reading the annotations
     * @param Configuration $configuration Optional, configuration for the proxy factory
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(ContainerInterface $container, Reader $reader, Configuration $configuration = null)
    {
        $this->container = $container;
        $this->reader = $reader;
        $this->dependencyProxyFactory = new AccessInterceptorScopeLocalizerFactory($configuration);
    }

    /**
     * @return Reader
     */
    protected function getAnnotationReader()
    {
        return $this->reader;
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @throws NotFoundException  No entry was found for this identifier.
     * @throws ContainerException Error while retrieving the entry.
     *
     * @return mixed Entry.
     * @throws \Onion\Framework\Dependency\Exception\UnknownDependency
     * @throws \Onion\Framework\Dependency\Exception\ContainerErrorException
     */
    public function get($id)
    {
        $dependency = $this->container->get($id);

        if (is_object($dependency)) {
            $reader = $this->getAnnotationReader();
            $dependencyReflection = new \ReflectionClass($dependency);
            if (($annotation = $reader->getClassAnnotation($dependencyReflection, Annotated::class)) !== null) {
                /**
                 * @var Annotated $annotation
                 */
                $dependency = $this->createDependencyProxy(
                    $dependency,
                    $annotation->getMethods(),
                    $annotation->getProperties()
                );
            }
        }

        return $dependency;
    }

    public function has($id)
    {
        return $this->container->has($id);
    }

    public function createDependencyProxy(object $dependency, array $methods = [], array $properties = []): object
    {
        $reader = $this->getAnnotationReader();
        $preCallbacks = [];
        $postCallbacks = [];
        foreach ($methods as $method) {
            $annotations = $reader->getMethodAnnotations(new \ReflectionMethod($dependency, $method));
            /**
             * @var AspectInterface[] $aspects
             */
            $aspects = [];
            foreach ($annotations as $annotation) {
                $aspects[] = [$annotation, $this->retrieveAspects(get_class($annotation))];
            }

            $preCallbacks[$method] = $this->getPreMethodCallback($aspects);
            $postCallbacks[$method] = $this->getPostMethodCallback($aspects);
        }

        return $this->dependencyProxyFactory->createProxy($dependency, $preCallbacks, $postCallbacks);
    }

    private function getPreMethodCallback(array $aspects): callable
    {
        $aspects = array_filter($aspects, function ($aspect) {
            return ($aspect[1] instanceof PreAspectInterface);
        });

        return function ($proxy, $instance, $methodName, $params, &$returnEarly) use ($aspects) {
            $callbacks = [];
            foreach ($aspects as $aspect) {
                list($annotation, $aspect) = $aspect;

                $callbacks[] = function (InvocationInterface $invocation) use ($aspect, $annotation, &$returnEarly) {
                    $value = $aspect->before($annotation, $invocation);
                    if ($returnEarly) {
                        return $value;
                    }

                    $invocation->continue();
                };
            }

            return (new Invocation(
                [$instance, $methodName],
                $params,
                $callbacks,
                $returnEarly
            ))->continue();
        };
    }

    private function getPostMethodCallback(array $aspects): callable
    {
        $aspects = array_filter(array_reverse($aspects), function ($aspect) {
            return ($aspect[1] instanceof PostAspectInterface);
        });

        return function ($proxy, $instance, $methodName, $params, $returnValue, &$returnEarly) use ($aspects) {
            $callbacks = [];
            foreach ($aspects as $aspect) {
                list($annotation, $aspect)=$aspect;

                $callbacks[] = function (InvocationInterface $invocation) use ($aspect, $annotation, &$returnEarly) {
                    $value = $aspect->after($annotation, $invocation);
                    if ($returnEarly) {
                        return $value;
                    }

                    $invocation->continue();
                };
            }

            return (new Invocation(
                [$instance, $methodName],
                $params,
                $callbacks,
                $returnEarly,
                $returnValue
            ))->continue();
        };
    }

    /**
     * @param $annotation
     *
     * @return AspectInterface[]
     * @throws \Onion\Framework\Dependency\Exception\ContainerErrorException
     * @throws \Interop\Container\Exception\NotFoundException
     * @throws \Interop\Container\Exception\ContainerException
     * @throws Exception\UnknownDependency
     */
    protected function retrieveAspects($annotation)
    {
        if ($this->container->has('aspects')) {
            return $this->container->get(
                $this->container->get('aspects')[$annotation]
            );
        }

        throw new Exception\UnknownDependency(sprintf(
            'Unable to retrieve aspects for annotation "%s" from container',
            $annotation
        ));
    }
}
