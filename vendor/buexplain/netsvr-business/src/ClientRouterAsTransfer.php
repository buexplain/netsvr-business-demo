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

use NetsvrBusiness\Contract\ClientRouterInterface;
use Netsvr\Cmd;
use Netsvr\Transfer;

/**
 * 客户端发消息的路由，这个路由实现是直接透传的，不解析
 */
class ClientRouterAsTransfer implements ClientRouterInterface
{
    protected int $cmd = Cmd::Transfer;
    protected string $data = '';

    public function serializeToString(): string
    {
        return $this->data;
    }

    public function mergeFromString(string $data): void
    {
        $this->data = $data;
    }

    public function getCmd(): int
    {
        return $this->cmd;
    }

    public function setCmd(int $cmd): void
    {
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function setData(string $data): void
    {
        $this->data = $data;
    }
}