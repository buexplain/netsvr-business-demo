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

namespace NetsvrBusiness;

use NetsvrBusiness\Router\TransferRouter;
use NetsvrBusiness\Command\StartWorkerCommand;
use NetsvrBusiness\Command\StatusWorkerCommand;
use NetsvrBusiness\Command\StopWorkerCommand;
use NetsvrBusiness\Contract\RouterInterface;
use NetsvrBusiness\Contract\DispatcherFactoryInterface;
use NetsvrBusiness\Contract\DispatcherInterface;
use NetsvrBusiness\Contract\WorkerSocketInterface;
use NetsvrBusiness\Contract\WorkerSocketManagerInterface;
use NetsvrBusiness\Dispatcher\Dispatcher;
use NetsvrBusiness\Dispatcher\DispatcherFactory;
use NetsvrBusiness\Socket\WorkerSocket;
use NetsvrBusiness\Socket\WorkerSocketManager;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                WorkerSocketInterface::class => WorkerSocket::class,
                WorkerSocketManagerInterface::class => WorkerSocketManager::class,
                DispatcherFactoryInterface::class => DispatcherFactory::class,
                DispatcherInterface::class => Dispatcher::class,
                //这里默认采用透传的路由，使用者可以自己实现RouterInterface接口，替换掉当前的配置
                RouterInterface::class => TransferRouter::class,
            ],
            'commands' => [
                StartWorkerCommand::class,
                StopWorkerCommand::class,
                StatusWorkerCommand::class,
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config of netsvr-business.',
                    'source' => __DIR__ . '/../publish/business.php',
                    'destination' => BASE_PATH . '/config/autoload/business.php',
                ],
                [
                    'id' => 'route',
                    'description' => 'The route of netsvr-business.',
                    'source' => __DIR__ . '/../publish/routes-websocket.php',
                    'destination' => BASE_PATH . '/config/routes-websocket.php',
                ],
                [
                    'id' => 'controller',
                    'description' => 'The controller of netsvr-business.',
                    'source' => __DIR__ . '/../publish/WebsocketTestController.php',
                    'destination' => BASE_PATH . '/app/Controller/WebsocketTestController.php',
                ],
            ],
        ];
    }
}
