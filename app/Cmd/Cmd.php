<?php

namespace App\Cmd;

/**
 * 这里定义的命令，要么是网关发过来的，要么是用户发过来的
 * 网关发过来的命令请看：https://github.com/buexplain/netsvr-protocol
 * 用户发过来的需要自己按业务需要去定义，但是不要处于网关定义的命令范围内，网关定义的命令是从 90001001 开始的
 */
class Cmd
{
    const ConnOpen = \Netsvr\Cmd::ConnOpen;
    const ConnClose = \Netsvr\Cmd::ConnClose;
    //广播消息，格式示例：{"cmd":1, "data":"大家好"}
    const Broadcast = 1;
    //单播消息给某个用户，格式示例：{"cmd":2, "data":{"message":"你好", "to":"00643DFB901C5FD554"}}，to字段是用户在网关中的唯一id
    const SingleCast = 2;
}