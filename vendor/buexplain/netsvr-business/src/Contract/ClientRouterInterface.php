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

use Netsvr\Transfer;

interface ClientRouterInterface
{
    /**
     * 返回客户发送过来的消息携带的cmd
     * @return int
     */
    public function getCmd(): int;

    /**
     * 解析客户发送过来的消息
     * @param Transfer $transfer
     */
    public function decode(Transfer $transfer);
}