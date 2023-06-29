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

use Closure;
use Exception;
use Google\Protobuf\Internal\Message;
use Hyperf\Context\ApplicationContext;
use Hyperf\Di\MethodDefinitionCollectorInterface;
use InvalidArgumentException;
use Netsvr\Cmd;
use Netsvr\Router;
use Netsvr\Transfer;
use NetsvrBusiness\Contract\MiddlewareHandlerInterface;
use NetsvrBusiness\Contract\RouterDataInterface;
use NetsvrBusiness\Contract\RouterInterface;
use NetsvrBusiness\Contract\DispatcherInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class Dispatcher implements DispatcherInterface
{
    /**
     * @var array 收集的路由信息
     */
    protected array $routes = [];
    /**
     * @var array 组路由配置的中间件
     */
    public array $temporaryGroupMiddleware = [];

    /**
     * 添加一个路由
     * @param int $cmd
     * @param array $handler 数组第一个元素是类名称，第二个元素是方法名称
     * @param array|string $middleware 中间件
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function addRoute(int $cmd, array $handler, array|string $middleware = []): void
    {
        $container = ApplicationContext::getContainer();
        //判断类和方法是否正确
        if (!isset($handler[0])) {
            throw new InvalidArgumentException('Controller class required.');
        }
        if (!class_exists($handler[0])) {
            throw new InvalidArgumentException(sprintf('Controller class %s not found.', $handler[0]));
        }
        if (!isset($handler[1])) {
            throw new InvalidArgumentException('Controller method required.');
        }
        $controller = $container->get($handler[0]);
        if (!method_exists($controller, $handler[1])) {
            throw new InvalidArgumentException(sprintf('Invalid controller %s, it has to provide a %s() method.', $handler[0], $handler[1]));
        }
        //将传入的中间件改刀成数组形式
        if (is_string($middleware)) {
            $middleware = $middleware === '' ? [] : [$middleware];
        }
        //合并组路由的中间件
        $middlewareArr = [];
        foreach ($this->temporaryGroupMiddleware as $item) {
            array_push($middlewareArr, ...$item);
        }
        array_push($middlewareArr, ...$middleware);
        //判断中间件是否正确
        foreach ($middlewareArr as $mdr) {
            if (!method_exists($container->get($mdr), 'process')) {
                throw new InvalidArgumentException(sprintf('Invalid middleware %s, it has to provide a process() method.', $mdr));
            }
            $definitions = $container->get(MethodDefinitionCollectorInterface::class)->getParameters($mdr, 'process');
            $ok = false;
            foreach ($definitions as $definition) {
                if ((class_exists($definition->getName()) && is_subclass_of($definition->getName(), MiddlewareHandlerInterface::class)) || MiddlewareHandlerInterface::class === $definition->getName()) {
                    $ok = true;
                }
            }
            if (!$ok) {
                throw new InvalidArgumentException(sprintf('Invalid middleware %s, method process() must receive parameter %s.', $mdr, MiddlewareHandlerInterface::class));
            }
        }
        //构造数据
        $info = [
            'class' => $handler[0],
            'method' => $handler[1],
            RouterDataInterface::class => null,
            'netSvrObj' => null,
            'middlewares' => $middlewareArr,
        ];
        //识别参数中的特别参数
        $methodDefinitionCollector = $container->get(MethodDefinitionCollectorInterface::class);
        $definitions = $methodDefinitionCollector->getParameters($info['class'], $info['method']);
        foreach ($definitions as $definition) {
            //业务数据路由的编码解码接口
            if (is_subclass_of($definition->getName(), RouterDataInterface::class)) {
                $info[RouterDataInterface::class] = $definition->getName();
                continue;
            }
            //网关组件下的具体对象
            if (str_starts_with($definition->getName(), 'Netsvr\\') && is_subclass_of($definition->getName(), Message::class)) {
                if (method_exists($definition->getName(), 'mergeFromString')) {
                    $info['netSvrObj'] = $definition->getName();
                }
            }
        }
        $this->routes[$cmd] = $info;
    }

    /**
     * 添加一组路由
     * 路由配置的时候不允许并发配置，否则因为跨协程的原因会导致组路由的配置错乱
     * @param array|string $middleware 中间件
     * @param Closure $closure
     * @return void
     */
    public function addRouteGroup(array|string $middleware, Closure $closure): void
    {
        $this->temporaryGroupMiddleware[] = (array)$middleware;
        $closure($this);
        $this->temporaryGroupMiddleware = array_slice($this->temporaryGroupMiddleware, 0, count($this->temporaryGroupMiddleware) - 1);
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
            $clientRouter = \Hyperf\Support\make(RouterInterface::class);
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
        if ($router->getCmd() === Cmd::Transfer) {
            //网关转发的客户消息，需要解码出客户消息携带的数据
            if (isset($clientRouter) && !is_null($handler[RouterDataInterface::class])) {
                /**
                 * @var $clientData RouterDataInterface
                 */
                $clientData = \Hyperf\Support\make($handler[RouterDataInterface::class]);
                $clientData->decode($clientRouter->getData());
                $arguments[$clientData::class] = $clientData;
                $arguments[RouterDataInterface::class] = $clientData;
            }
        } else {
            //网关转发的非客户消息，需要解码出网关组件下的具体对象
            if (!is_null($handler['netSvrObj'])) {
                /**
                 * @var $netSvrObj Message
                 */
                $netSvrObj = \Hyperf\Support\make($handler['netSvrObj']);
                $netSvrObj->mergeFromString($router->getData());
                $arguments[$handler['netSvrObj']] = $netSvrObj;
            }
        }
        $arguments[Router::class] = $router;
        //参数准备完毕，构造一个中间件穿透类，让数据在到达目标控制器与方法之前先流淌过整个中间件集合
        (new MiddlewareHandler($arguments, $handler['middlewares'], $handler['class'], $handler['method']))->handle();
    }
}
