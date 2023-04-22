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
use Netsvr\Transfer;
use NetsvrBusiness\Exception\ClientRouterDecodeException;

/**
 * 客户端发消息的路由，这个路由实现是解析json，json格式为：{"cmd":int, "data":"string"}
 */
class ClientRouterAsJson implements ClientRouterInterface
{
    protected int $cmd = 0;
    protected string $data = '';

    public function serializeToString(): string
    {
        return json_encode(['cmd' => $this->cmd, 'data' => $this->data], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function mergeFromString(string $data): void
    {
        $tmp = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ClientRouterDecodeException(json_last_error_msg(), 1);
        }
        if (!is_array($tmp) || !isset($tmp['cmd']) || !is_int($tmp['cmd']) || !isset($tmp['data']) || !is_string($tmp['data'])) {
            throw new ClientRouterDecodeException('expected package format is: {"cmd":int, "data":"string"}', 2);
        }
        $this->cmd = $tmp['cmd'];
        $this->data = $tmp['data'];
    }

    public function getCmd(): int
    {
        return $this->cmd;
    }

    public function setCmd(int $cmd): void
    {
        $this->cmd = $cmd;
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