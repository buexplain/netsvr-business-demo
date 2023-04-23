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

use Exception;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\NormalizerInterface;
use Hyperf\Di\MethodDefinitionCollectorInterface;
use Hyperf\Di\ReflectionType;
use InvalidArgumentException;
use Netsvr\Cmd;
use Netsvr\Router;
use Netsvr\Transfer;
use NetsvrBusiness\Contract\DataInterface;
use NetsvrBusiness\Contract\RouterInterface;
use NetsvrBusiness\Contract\DispatcherInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class Dispatcher implements DispatcherInterface
{
    protected array $routes = [];

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function addRoute(int $cmd, array $handler): void
    {
        if (!isset($handler[0])) {
            throw new InvalidArgumentException('Controller class required.');
        }
        if (!class_exists($handler[0])) {
            throw new InvalidArgumentException(sprintf('Controller class %s not found.', $handler[0]));
        }
        if (!isset($handler[1])) {
            throw new InvalidArgumentException('Controller method required.');
        }
        $controller = ApplicationContext::getContainer()->get($handler[0]);
        if (!method_exists($controller, $handler[1])) {
            throw new InvalidArgumentException(sprintf('Controller method %s::%s not found.', $handler[0], $handler[1]));
        }
        $this->routes[$cmd] = $handler;
    }

    /**
     * @param ContainerInterface $container
     * @param array|ReflectionType[] $definitions
     * @param string $callableName
     * @param array $arguments
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function getInjections(ContainerInterface $container, array $definitions, string $callableName, array $arguments): array
    {
        $injections = [];
        foreach ($definitions as $pos => $definition) {
            $value = $arguments[$pos] ?? $arguments[$definition->getName()] ?? $arguments[$definition->getMeta('name')] ?? null;
            if ($value === null) {
                if ($definition->getMeta('defaultValueAvailable')) {
                    $injections[] = $definition->getMeta('defaultValue');
                } elseif ($definition->allowsNull()) {
                    $injections[] = null;
                } elseif ($container->has($definition->getName())) {
                    $injections[] = $container->get($definition->getName());
                } else {
                    throw new InvalidArgumentException("Parameter '{$definition->getMeta('name')}' " . "of $callableName should not be null");
                }
            } else {
                /**
                 * @var $normalizer NormalizerInterface
                 */
                $normalizer = $container->get(NormalizerInterface::class);
                $injections[] = $normalizer->denormalize($value, $definition->getName());
            }
        }
        return $injections;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Exception
     */
    public function dispatch(Router $router): void
    {
        $arguments = [];
        if ($router->getCmd() == Cmd::Transfer) {
            //网关转发过来的客户消息，需要解码出客户消息的cmd
            $tf = new Transfer();
            $tf->mergeFromString($router->getData());
            /**
             * @var $clientRouter RouterInterface
             */
            $clientRouter = make(RouterInterface::class);
            $clientRouter->decode($tf->getData());
            $cmd = $clientRouter->getCmd();
            $arguments[RouterInterface::class] = $clientRouter;
            $arguments[$clientRouter::class] = $clientRouter;
            $arguments[Transfer::class] = $tf;
        } else {
            //网关转发的非客户消息
            $cmd = $router->getCmd();
        }
        if (!isset($this->routes[$cmd])) {
            throw new InvalidArgumentException(sprintf('Router dispatch failed, unknown cmd: %d, check the router file has been configured this cmd.', $cmd), $cmd);
        }
        $handler = $this->routes[$cmd];
        $container = ApplicationContext::getContainer();
        $methodDefinitionCollector = $container->get(MethodDefinitionCollectorInterface::class);
        $definitions = $methodDefinitionCollector->getParameters($handler[0], $handler[1]);
        if ($router->getCmd() === Cmd::Transfer) {
            //网关转发的客户消息，需要解码出客户消息携带的数据
            foreach ($definitions as $definition) {
                if (isset($clientRouter) && is_subclass_of($definition->getName(), DataInterface::class)) {
                    /**
                     * @var $clientData DataInterface
                     */
                    $clientData = make($definition->getName());
                    $clientData->decode($clientRouter->getData());
                    $arguments[$clientData::class] = $clientData;
                    $arguments[DataInterface::class] = $clientData;
                    break;
                }
            }
        } else {
            //网关转发的非客户消息，需要解码出网关组件下的具体对象
            foreach ($definitions as $definition) {
                if (str_starts_with($definition->getName(), 'Netsvr\\') && class_exists($definition->getName())) {
                    $netSvrObj = make($definition->getName());
                    if (method_exists($definition->getName(), 'mergeFromString')) {
                        $netSvrObj->mergeFromString($router->getData());
                        $arguments[$definition->getName()] = $netSvrObj;
                        break;
                    }
                }
            }
        }
        $arguments[Router::class] = $router;
        $parameters = $this->getInjections($container, $definitions, "$handler[0]::$handler[1]", $arguments);
        $container->get($handler[0])->{$handler[1]}(...$parameters);
    }
}