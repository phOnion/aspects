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
use Interop\Container\Exception\ContainerException;
use Interop\Container\Exception\NotFoundException;
use Onion\Framework\Aspects\Invocation;
use Onion\Framework\Dependency\Exception;
use Onion\Framework\Annotations\Annotated;
use Onion\Framework\Aspects\Interfaces\AspectInterface;
use ProxyManager\Configuration;
use ProxyManager\Factory\AccessInterceptorValueHolderFactory;

class AspectContainer extends Container
{
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
    public function __construct(array $definitions, Reader $reader, Configuration $configuration = null)
    {
        parent::__construct($definitions);
        $this->reader = $reader;
        $this->dependencyProxyFactory = new AccessInterceptorValueHolderFactory($configuration);
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
        $dependency = null;
        if ($this->has($id)) {
            if ($this->isShared($id)) {
                $dependency = $this->retrieveShared($id);
            }

            if ($dependency === null) {
                $dependency = $this->retrieveFromFactory($id);
            }

            if ($dependency === null) {
                $dependency = $this->retrieveInvokable($id);
            }

            if (!$dependency instanceof $id && (class_exists($id) || interface_exists($id))) {
                throw new Exception\ContainerErrorException(
                    sprintf(
                        'Resolved dependency "%s" is not instance of "%s"',
                        get_class($dependency),
                        $id
                    )
                );
            }

            $reader = $this->getAnnotationReader();
            $dependencyReflection = new \ReflectionClass($dependency);
            if (($annotation = $reader->getClassAnnotation($dependencyReflection, Annotated::class)) !== null) {
                /**
                 * @var $annotation Annotated
                 */
                $dependency = $this->createDependencyProxy($dependency, $annotation->getMethods());
            }

            return $dependency;
        }

        throw new Exception\UnknownDependency(
            sprintf('"%s" not registered with the container', $id)
        );
    }

    public function createDependencyProxy($dependency, array $methods = [])
    {
        $reader = $this->getAnnotationReader();
        $preCallbacks = [];
        $postCallbacks = [];
        foreach ($methods as $method) {
            $annotations = $reader->getMethodAnnotations(new \ReflectionMethod($dependency, $method));
            /**
             * @var $aspects AspectInterface[][]
             */
            $aspects = [];
            foreach ($annotations as $annotation) {
                $aspects[get_class($annotation)] = $this->retrieveAspects(get_class($annotation));
            }

            $preCallbacks[$method] = function($proxy, $instance, $methodName, $params, &$returnEarly) use ($aspects) {
                $returnValue = null;
                $annotations = $this->getAnnotationReader()->getMethodAnnotations(new \ReflectionMethod($instance, $methodName));
                foreach ($annotations as $annotation) {
                    $invocation = new Invocation($instance, $methodName, $params, $returnValue);
                    foreach ($aspects[get_class($annotation)] as $aspect) {
                        $returnValue = $aspect->beforeMethod($annotation, $invocation);

                        if ($invocation->isReturnEarly()) {
                            $invocation = new Invocation($instance, $methodName, $params, $returnValue);
                            $returnEarly = true;
                        }
                    }
                }

                return $returnValue;
            };

            $postCallbacks[$method] = function($proxy, $instance, $methodName, $params, $returnValue, &$returnEarly) use ($aspects) {
                $annotations = $this->getAnnotationReader()->getMethodAnnotations(new \ReflectionMethod($instance, $methodName));
                foreach ($annotations as $annotation) {
                    $invocation = new Invocation($instance, $methodName, $params, $returnValue);
                    foreach ($aspects[get_class($annotation)] as $aspect) {
                        $returnValue = $aspect->afterMethod($annotation, $invocation);

                        if ($invocation->isReturnEarly()) {
                            $invocation = new Invocation($instance, $methodName, $params, $returnValue);
                            $returnEarly = true;
                        }
                    }
                }


                return $returnValue;
            };
        }

        return $this->dependencyProxyFactory->createProxy($dependency, $preCallbacks, $postCallbacks);
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
        if (array_key_exists($annotation, $this->definitions['aspects'])) {
            $aspects = (array) $this->definitions['aspects'][$annotation];

            foreach ($aspects as $index => &$aspect) {
                if (!is_object($aspect)) {
                    $aspect = $this->get($aspect);
                }
            }

            return $aspects;
        }

        throw new Exception\UnknownDependency(sprintf(
            'Unable to retrieve aspects for annotation "%s" from container',
            $annotation
        ));
    }
}
