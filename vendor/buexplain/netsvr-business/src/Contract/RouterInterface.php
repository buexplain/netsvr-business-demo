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

/**
 * 客户数据的路由的编解码接口
 * 这个接口
 */
interface RouterInterface
{
    /**
     * 编码
     * @return string
     */
    public function encode(): string;

    /**
     * 解码
     * @param string $data
     */
    public function decode(string $data): void;

    /**
     * 获取命令
     *
     * @return int
     */
    public function getCmd();

    /**
     * 设置命令
     *
     * @param int $var
     */
    public function setCmd(int $var);

    /**
     * 获取命令携带的数据
     *
     * @return string
     */
    public function getData();

    /**
     * 设置命令携带的数据
     *
     * @param string $var
     */
    public function setData(string $var);
}