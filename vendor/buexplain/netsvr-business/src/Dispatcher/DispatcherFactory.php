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

namespace NetsvrBusiness\Dispatcher;

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\StdoutLoggerInterface;
use NetsvrBusiness\Contract\DispatcherFactoryInterface;
use NetsvrBusiness\Contract\DispatcherInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class DispatcherFactory implements DispatcherFactoryInterface
{
    /**
     * @var array|string[]
     */
    protected array $routes = [BASE_PATH . '/config/routes-websocket.php'];

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct()
    {
        $logger = ApplicationContext::getContainer()->get(StdoutLoggerInterface::class);
        foreach ($this->routes as $route) {
            if (file_exists($route)) {
                require_once $route;
            } else {
                $logger->error('Router file ' . $route . ' does not exist');
            }
        }
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function get(): DispatcherInterface
    {
        return ApplicationContext::getContainer()->get(DispatcherInterface::class);
    }
}