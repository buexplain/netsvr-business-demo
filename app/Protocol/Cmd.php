<?php

namespace App\Protocol;

/**
 * 业务侧自定义的各种命令，类似于http接口的url path地址
 */
class Cmd
{
    /**
     * 广播
     */
    const BROADCAST = 1;

    /**
     * 单播
     */
    const SINGLE_CAST = 2;

    /**
     * 用户连接成功后的公开的欢迎命令
     */
    const PUBLIC_WELCOME = 3;

    /**
     * 用户连接成功后的私有的欢迎命令
     */
    const PRIVATE_WELCOME = 4;

    /**
     * 群聊之加入某个群
     */
    const GROUP_CHAT_FOR_ATTACH = 5;

    /**
     * 群聊之退出某个群
     */
    const GROUP_CHAT_FOR_DETACH = 6;

    /**
     * 群聊之往某个群发送消息
     */
    const GROUP_CHAT_FOR_SEND = 7;
}