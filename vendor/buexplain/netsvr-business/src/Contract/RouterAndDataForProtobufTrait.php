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

use Exception;

/**
 * 客户发送的业务数据的编码解码接口，这个trait是给protobuf做适配的，所有用proto生成的文件都需要用下面的shell脚本做处理
 * 目的是：
 * 1. 让协议类实现接口 \NetsvrBusiness\Contract\RouterDataInterface
 * 2. 让路由类实现接口 \NetsvrBusiness\Contract\RouterInterface
 *
 * oldStr="extends \\\\Google\\\\Protobuf\\\\Internal\\\\Message"
 * # 让所有的协议类都实现接口 \NetsvrBusiness\Contract\RouterDataInterface
 * routerDataInterface="extends \\\\Google\\\\Protobuf\\\\Internal\\\\Message implements \\\\NetsvrBusiness\\\\Contract\\\\RouterDataInterface"
 * # 路由类实现接口 \NetsvrBusiness\Contract\RouterInterface
 * routerInterface="extends \\\\Google\\\\Protobuf\\\\Internal\\\\Message implements \\\\NetsvrBusiness\\\\Contract\\\\RouterInterface"
 * # 让所有生成的协议类、路由类都引入 \NetsvrBusiness\Contract\RouterAndDataForProtobufTrait;
 * # shellcheck disable=SC2034
 * routerAndDataForProtobufTrait="    use \\\\NetsvrBusiness\\\\Contract\\\\RouterAndDataForProtobufTrait;"
 * for file in ./../Protobuf/*.php; do
 *   sed -i "/^{$/a\\$routerAndDataForProtobufTrait" "$file"
 *   if [[ "$file" == *"Router.php" ]]; then
 *     sed -i "s/$oldStr/$routerInterface/g" "$file"
 *     continue;
 *   fi
 *   sed -i "s/$oldStr/$routerDataInterface/g" "$file"
 * done
 */
trait RouterAndDataForProtobufTrait
{
    /**
     * 编码
     * @return string
     */
    public function encode(): string
    {
        if (config('business.protobufAsJSON', false)) {
            return $this->serializeToJsonString();
        }
        return $this->serializeToString();
    }

    /**
     * 解码
     * @param string $data
     * @return void
     * @throws Exception
     */
    public function decode(string $data): void
    {
        if (config('business.protobufAsJSON', false)) {
            $this->mergeFromJsonString($data);
            return;
        }
        $this->mergeFromString($data);
    }
}