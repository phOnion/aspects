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
use ProxyManager\Factory\AccessInterceptorValueHolderFactory;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;
use ProxyManager\Proxy\LazyLoadingInterface;
use Psr\Container\ContainerInterface;
use Onion\Framework\Aspects\PropertyAccess;

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
     * @var AccessInterceptorValueHolderFactory
     */
    protected $dependencyProxyFactory;

    /**
     * @var LazyLoadingValueHolderFactory;
     */
    private $lazyFactory;

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
        $this->dependencyProxyFactory = new AccessInterceptorValueHolderFactory($configuration);
        $this->lazyFactory = new LazyLoadingValueHolderFactory($configuration);
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
            if (class_exists($id) || interface_exists($id)) {
                return $this->lazyFactory->createProxy(
                    $id,
                    function(
                        &$wrappedObject,
                        LazyLoadingInterface $proxy,
                        $method,
                        array $parameters,
                        &$initializer
                    ) use ($id) {
                        $initializer = null;
                        $reflection = new \ReflectionClass($id);
                        if ($this->isAnnotated($reflection)) {
                            $wrappedObject = $this->createDependencyProxy(
                                $this->getWrappedContainer()->get($id),
                                $reflection
                            );
                        } else {
                            $wrappedObject = $this->getWrappedContainer()->get($id);
                        }

                        return true;
                    }
                );
            }

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

            throw new \RuntimeException(
                "Error while processing annotations for '{$id}': {$ex->getMessage()}",
                $ex->getCode(),
                $ex
            );
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
                try {
                    $aspects[] = [$annotation, $this->retrieveAspects($annotation)];
                } catch (Exception\UnknownDependency $ex) {
                    // Unable to find aspect for annotation, we ok to continue
                }
            }

            $preCallbacks[$method->getName()] = $this->getPreMethodCallback($aspects);
            $postCallbacks[$method->getName()] = $this->getPostMethodCallback($aspects);
        }

        $propCallbacks = [];
        foreach ($reflection->getProperties() as $property) {
            $annotations = $this->reader->getPropertyAnnotations($property);
            /**
             * @var AspectInterface[] $aspects
             */
            $aspects = [];
            foreach ($annotations as $annotation) {
                $aspects[] = [$annotation, $this->retrieveAspects($annotation)];
            }

            $pre = $this->getPreMethodCallback($aspects, PropertyAccess::class);
            $post = $this->getPostMethodCallback($aspects, PropertyAccess::class);

            $propCallbacks[$property->getName()] = [$pre, $post];
        }

        $pre =  function ($proxy, $instance, $methodName, $params, &$early) use ($propCallbacks) {
            if (isset($propCallbacks[$params['name']])) {
                return ($propCallbacks[$params['name']])[0]($proxy, $instance, $methodName, $params, $early);
            }
        };

        $post =  function ($proxy, $instance, $methodName, $params, $return, &$early) use ($propCallbacks) {
            if (isset($propCallbacks[$params['name']])) {
                return ($propCallbacks[$params['name']])[1]($proxy, $instance, $methodName, $params, $return, $early);
            }
        };

        $preCallbacks['__get'] = $pre;
        $preCallbacks['__set'] = $pre;
        $preCallbacks['__isset'] = $pre;
        $preCallbacks['__unset'] = $pre;

        $postCallbacks['__get'] = $post;
        $postCallbacks['__set'] = $post;
        $postCallbacks['__isset'] = $post;
        $postCallbacks['__unset'] = $post;


        return $this->dependencyProxyFactory->createProxy($dependency, $preCallbacks, $postCallbacks);
    }

    private function getPreMethodCallback(array $aspects, string $invocationClass = Invocation::class): callable
    {
        $aspects = array_filter($aspects, function ($aspect) {
            return ($aspect[1] instanceof PreAspectInterface);
        });

        return function ($proxy, $instance, $methodName, $params, &$early) use ($aspects, $invocationClass) {
            $callbacks = [];
            foreach ($aspects as $aspect) {
                list($annotation, $aspect) = $aspect;

                $callbacks[] = function (InvocationInterface $invocation) use ($aspect, $annotation, &$early) {
                    $value = $aspect->before(clone $annotation, $invocation);
                    if ($early) {
                        return $value;
                    }

                    $invocation->continue();
                };
            }

            return (new \ReflectionClass($invocationClass))->newInstanceArgs([
                [$instance, $methodName],
                $params,
                $callbacks,
                &$early
            ])->continue();
        };
    }

    private function getPostMethodCallback(array $aspects, string $invocationClass = Invocation::class): callable
    {
        $aspects = array_filter(array_reverse($aspects), function ($aspect) {
            return ($aspect[1] instanceof PostAspectInterface);
        });

        return function ($proxy, $instance, $methodName, $params, $return, &$early) use ($aspects, $invocationClass) {
            $callbacks = [];
            foreach ($aspects as $aspect) {
                list($annotation, $aspect)=$aspect;

                $callbacks[] = function (InvocationInterface $invocation) use ($aspect, $annotation, &$early) {
                    $value = $aspect->after($annotation, $invocation);
                    if ($early) {
                        return $value;
                    }

                    $invocation->continue();
                };
            }

            return (new \ReflectionClass($invocationClass))->newInstanceArgs([
                [$instance, $methodName],
                $params,
                $callbacks,
                &$early,
                $return
            ])->continue();
        };
    }

    protected function retrieveAspects(AnnotationInterface $annotation): object
    {
        $annotation = get_class($annotation);
        if ($this->has('aspects')) {
            $aspects = $this->getWrappedContainer()->get('aspects');
            return $this->getWrappedContainer()->get(
                ($aspects instanceof ContainerInterface ? $aspects->get($annotation) : $aspects[$annotation] ?? '')
            );
        }

        throw new Exception\UnknownDependency(sprintf(
            'Unable to retrieve aspects for annotation "%s" from container',
            $annotation
        ));
    }
}
