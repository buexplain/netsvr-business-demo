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
 * 客户端发消息的路由，这个路由实现是解析json，json格式为：{"cmd":int, "data":mixed}
 */
class ClientRouterAsJson implements ClientRouterInterface
{
    protected int $cmd = 0;
    protected mixed $data = null;

    /**
     * 返回客户发送过来的消息携带的cmd
     * @return int
     */
    public function getCmd(): int
    {
        return $this->cmd;
    }

    /**
     * 解析客户发送过来的消息
     * @param Transfer $transfer
     * @return void
     */
    public function decode(Transfer $transfer): void
    {
        $tmp = json_decode($transfer->getData(), true);
        if (is_array($tmp) && isset($tmp['cmd']) && is_int($tmp['cmd'])) {
            $this->cmd = $tmp['cmd'];
            if (isset($tmp['data'])) {
                $this->data = $tmp['data'];
            }
            return;
        }
        if (json_last_error() === JSON_ERROR_NONE) {
            throw new ClientRouterDecodeException(json_last_error_msg(), 1);
        }
        throw new ClientRouterDecodeException('Unable to find the cmd field, expected package format is: {"cmd":int, "data":mixed}', 1);
    }

    public function getData(): mixed
    {
        return $this->data;
    }
}