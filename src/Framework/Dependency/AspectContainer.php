<?php declare(strict_types=1);
namespace Onion\Framework\Dependency;

use Doctrine\Common\Annotations\AnnotationException;
use Doctrine\Common\Annotations\Reader;
use Onion\Framework\Annotations\Annotated;
use Onion\Framework\Annotations\Interfaces\AnnotationInterface;
use Onion\Framework\Aspects\Interfaces\AspectInterface;
use Onion\Framework\Aspects\Interfaces\InvocationInterface;
use Onion\Framework\Aspects\Invocation;
use Onion\Framework\Aspects\Method\Interfaces\PostAspectInterface;
use Onion\Framework\Aspects\Method\Interfaces\PreAspectInterface;
use Onion\Framework\Common\Collection\Collection;
use Onion\Framework\Common\Dependency\Traits\WrappingContainerTrait;
use Onion\Framework\Dependency\Exception;
use Onion\Framework\Dependency\Interfaces\WrappingContainerInterface;
use ProxyManager\Configuration;
use ProxyManager\Factory\AccessInterceptorScopeLocalizerFactory;
use Psr\Container\ContainerInterface;

class AspectContainer implements ContainerInterface, WrappingContainerInterface
{
    use WrappingContainerTrait;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var Reader
     */
    protected $reader;

    /**
     * @var AccessInterceptorScopeLocalizerFactory
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
    public function __construct(Reader $reader, Configuration $configuration = null)
    {
        $this->reader = $reader;
        $this->dependencyProxyFactory = new AccessInterceptorScopeLocalizerFactory($configuration);
    }

    /**
     * @return Reader
     */
    protected function getAnnotationReader(): Reader
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
        try {
            $dependency = $this->getWrappedContainer()->get($id);

            if (is_object($dependency)) {
                $dependencyReflection = new \ReflectionClass($dependency);
                if ($this->isAnnotated($dependencyReflection)) {
                    $dependency = $this->createDependencyProxy($dependency, $dependencyReflection);
                }
            }
        } catch (AnnotationException $ex) {
            if (stripos($ex->getMessage(), '[Type Error]') === 0) {
                throw new \TypeError(trim(substr($ex->getMessage(), 12)), $ex->getCode(), $ex);
            }

            if (stripos($ex->getMessage(), '[Syntax Error]') === 0) {
                if (class_exists(\CompileError::class)) {
                    throw new \CompileError(trim(substr($ex->getMessage(), 14)), $ex->getCode(), $ex);
                }

                throw new \ParseError(trim(substr($ex->getMessage(), 14)), $ex->getCode(), $ex);
            }

            if (stripos($ex->getMessage(), '[Semantical Error]') === 0) {
                throw new \ParseError(trim(substr($ex->getMessage(), 18)), $ex->getCode(), $ex);
            }

            throw new \RuntimeException("Error while processing annotations for '{$id}'");
            throw new \Error($ex->getMessage(), $ex->getCode(), $ex);
        }

        return $dependency;
    }

    public function has($id)
    {
        return $this->getWrappedContainer()->has($id);
    }

    private function isAnnotated(\ReflectionClass $class): bool
    {
        $annotated = $this->getAnnotationReader()->getClassAnnotation($class, Annotated::class);
        if (!$annotated && $class->getParentClass()) {
            return $this->isAnnotated($class->getParentClass());
        }

        return $annotated !== null;
    }

    private function getClassAnnotations(\ReflectionClass $class)
    {
        $annotations = $this->getAnnotationReader()->getClassAnnotations($class);
        if ($class->getParentClass()) {
            $annotations = array_merge($annotations, $this->getClassAnnotations($class->getParentClass()));
        }

        return $annotations;
    }

    private function createDependencyProxy(object $dependency, \ReflectionClass $reflection): object
    {
        $preCallbacks = [];
        $postCallbacks = [];
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $annotations = (new Collection(array_merge(
                $this->getClassAnnotations($reflection),
                $this->getAnnotationReader()->getMethodAnnotations($method)
            )))->unique()
                ->filter(function (AnnotationInterface $annotation) {
                    $annotation = get_class($annotation);
                    return $this->getWrappedContainer()->has($annotation) && $annotation !== Annotated::class;
                });

            /**
             * @var AspectInterface[] $aspects
             */
            $aspects = [];
            foreach ($annotations as $annotation) {
                $aspects[] = [$annotation, $this->retrieveAspects($annotation)];
            }

            $preCallbacks[$method->getName()] = $this->getPreMethodCallback($aspects);
            $postCallbacks[$method->getName()] = $this->getPostMethodCallback($aspects);
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
                    $value = $aspect->before(clone $annotation, $invocation);
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
    protected function retrieveAspects(AnnotationInterface $annotation)
    {
        $annotation = get_class($annotation);
        if ($this->getWrappedContainer()->has('aspects')) {
            $aspects = $this->getWrappedContainer()->get('aspects');
            return $this->getWrappedContainer()->get(
                ($aspects instanceof ContainerInterface ? $aspects->get($annotation) : $aspects[$annotation])
            );
        }

        throw new Exception\UnknownDependency(sprintf(
            'Unable to retrieve aspects for annotation "%s" from container',
            $annotation
        ));
    }
}
