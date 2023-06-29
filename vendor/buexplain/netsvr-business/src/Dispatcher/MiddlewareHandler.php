<?php
/**
 * Copyright 2023 buexplain@qq.com
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace NetsvrBusiness\Dispatcher;

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\NormalizerInterface;
use Hyperf\Di\MethodDefinitionCollectorInterface;
use Hyperf\Di\ReflectionType;
use NetsvrBusiness\Contract\MiddlewareHandlerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use InvalidArgumentException;

class MiddlewareHandler implements MiddlewareHandlerInterface
{
    protected int $offset = -1;
    /**
     * @var array
     */
    protected array $middlewares = [];
    protected ContainerInterface $container;
    protected string $handlerClass;
    protected string $handlerMethod;
    protected array $arguments;

    public function __construct(array $arguments, array $middlewares, string $handlerClass, string $handlerMethod)
    {
        $this->arguments = $arguments;
        $this->arguments[MiddlewareHandlerInterface::class] = $this;
        $this->arguments[self::class] = $this;
        $this->middlewares = array_values($middlewares);
        $this->container = ApplicationContext::getContainer();
        $this->handlerClass = $handlerClass;
        $this->handlerMethod = $handlerMethod;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function handle(): void
    {
        ++$this->offset;
        if (isset($this->middlewares[$this->offset])) {
            $handler = $this->middlewares[$this->offset];
            $definitions = $this->container->get(MethodDefinitionCollectorInterface::class)->getParameters($handler, 'process');
            $parameters = $this->getInjections($definitions, "$handler::process", $this->arguments);
            $this->container->get($handler)->process(...$parameters);
        } else {
            $definitions = $this->container->get(MethodDefinitionCollectorInterface::class)->getParameters($this->handlerClass, $this->handlerMethod);
            $parameters = $this->getInjections($definitions, "$this->handlerClass::$this->handlerMethod", $this->arguments);
            $this->container->get($this->handlerClass)->{$this->handlerMethod}(...$parameters);
        }
    }

    /**
     * @param array|ReflectionType[] $definitions
     * @param string $callableName
     * @param array $arguments
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function getInjections(array $definitions, string $callableName, array $arguments): array
    {
        $injections = [];
        foreach ($definitions as $pos => $definition) {
            $value = $arguments[$pos] ?? $arguments[$definition->getName()] ?? $arguments[$definition->getMeta('name')] ?? null;
            if ($value === null) {
                if ($definition->getMeta('defaultValueAvailable')) {
                    $injections[] = $definition->getMeta('defaultValue');
                } elseif ($definition->allowsNull()) {
                    $injections[] = null;
                } elseif ($this->container->has($definition->getName())) {
                    $injections[] = $this->container->get($definition->getName());
                } else {
                    throw new InvalidArgumentException("Parameter '{$definition->getMeta('name')}' " . "of $callableName should not be null");
                }
            } else {
                /**
                 * @var $normalizer NormalizerInterface
                 */
                $normalizer = $this->container->get(NormalizerInterface::class);
                $injections[] = $normalizer->denormalize($value, $definition->getName());
            }
        }
        return $injections;
    }
}
