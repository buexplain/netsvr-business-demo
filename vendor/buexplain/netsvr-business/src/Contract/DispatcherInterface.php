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

namespace NetsvrBusiness\Contract;

use Netsvr\Router;
use Closure;

/**
 * 调度器，根据路由文件的配置指令与控制器方法的映射关系，将网关转发的数据调度到具体的控制器的方法
 */
interface DispatcherInterface
{
    /**
     * 添加一个路由
     * @param int $cmd
     * @param array $handler 数组第一个元素是类名称，第二个元素是方法名称
     * @param array|string $middleware 中间件
     * @return void
     */
    public function addRoute(int $cmd, array $handler, array|string $middleware = []): void;

    /**
     * 添加一组路由
     * @param array|string $middleware 中间件
     * @param Closure $closure
     * @return void
     */
    public function addRouteGroup(array|string $middleware, Closure $closure): void;

    /**
     * 调度一个路由到目标控制器与方法
     * @param Router $router
     * @return void
     */
    public function dispatch(Router $router): void;
}
