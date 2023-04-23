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

namespace NetsvrBusiness\Router;

use Exception;
use NetsvrBusiness\Contract\RouterInterface;
use NetsvrBusiness\Router\Proto\Router;

/**
 * 客户端发消息的路由，这个路由实现是protobuf，proto格式见router.proto文件
 */
class ProtoBufRouter implements RouterInterface
{
    protected Router $router;

    public function __construct()
    {
        $this->router = new Router();
    }

    public function encode(): string
    {
        return $this->router->serializeToString();
    }

    /**
     * @throws Exception
     */
    public function decode(string $data): void
    {
        $this->router->mergeFromString($data);
    }

    public function getCmd(): int
    {
        return $this->router->getCmd();
    }

    public function setCmd(int $cmd): void
    {
        $this->router->setCmd($cmd);
    }

    public function getData(): string
    {
        return $this->router->getData();
    }

    public function setData(string $data): void
    {
        $this->router->setData($data);
    }
}