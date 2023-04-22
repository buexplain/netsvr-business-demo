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

interface ClientRouterInterface
{
    /**
     * 编码
     * @return string
     */
    public function serializeToString(): string;

    /**
     * 解码
     * @param string $param
     * @return void
     */
    public function mergeFromString(string $data): void;

    /**
     * 获取命令
     * @return int
     */
    public function getCmd(): int;

    /**
     * 设置命令
     * @param int $cmd
     * @return void
     */
    public function setCmd(int $cmd): void;

    /**
     * 获取命令携带的数据
     * @return string
     */
    public function getData(): string;

    /**
     * 设置命令携带的数据
     * @param string $data
     * @return void
     */
    public function setData(string $data): void;
}