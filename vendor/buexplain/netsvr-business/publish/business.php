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

return [
    //虽然是protobuf编解码，却用的是json格式的字符串的输入、输出，因为protobuf支持方法：serializeToJsonString、mergeFromJsonString
    'protobufAsJSON' => false,
    //如果一个网关服务承载不了业务的websocket连接数，可以再启动一个网关服务，这里支持配置多个网关服务，处理多个网关服务的websocket消息
    'netsvrWorkers' => [
        [
            //网关服务的worker服的地址
            'host' => (string)env('NETSVR_WORKER_HOST', '127.0.0.1'),
            //网关服务的worker服务的端口
            'port' => (int)env('NETSVR_WORKER_PORT', 6061),
            //连接到网关服务的超时时间，单位秒
            'connectTimeout' => 1,
            //网关服务的唯一编号，该值必须与网关服务的配置一致，并且多个网关服务之间的值不能重复，如果配置错误，网关会拒绝business的注册请求，并关返回注册失败的错误
            'serverId' => (int)env('NETSVR_SERVER_ID', 0),
            //当前业务进程的服务编号，取值区间是：[1,999]，业务层自己规划安排
            //所有发给网关的消息，如果需要当前业务进程处理，则必须是以该配置开头，因为网关是根据这个workerId来转发客户数据到业务进程的
            //客户发送的数据示例：001{"cmd":1,"data":"我的好朋友，你在吃什么？"}，其中001就是workerId，不足三位，前面补0
            'workerId' => (int)env('BUSINESS_WORKER_ID', 1),
            //该参数表示接下来，需要网关服务的worker服务器开启多少协程来处理本连接的请求
            'processCmdGoroutineNum' => 10,
            //保持与网关服务的连接的活跃状态的心跳间隔，单位秒
            'heartbeatInterval' => 60,
            //限制与网关服务的连接的tcp数据包大小的最大值
            'packageMaxLength' => 1024 * 1024 * 5,
        ],
    ]
];