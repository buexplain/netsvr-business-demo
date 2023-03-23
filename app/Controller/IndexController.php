<?php
declare(strict_types=1);

namespace App\Controller;

use App\Patch\AsyncSocket;
use Netsvr\Broadcast;
use Netsvr\Cmd;
use Netsvr\ConnOpen;
use Netsvr\ConnClose;
use Netsvr\Router;
use Netsvr\Transfer;

class IndexController
{
    /**
     * 处理用户连接打开信息
     * @param AsyncSocket $socket
     * @param ConnOpen $connOpen
     * @return void
     */
    public static function onOpen(AsyncSocket $socket, ConnOpen $connOpen): void
    {
        //构造一个广播对象
        $broadcast = new Broadcast();
        $broadcast->setData("有新用户进来 --> " . $connOpen->getUniqId());
        //构造一个路由对象
        $router = new Router();
        //设置命令为广播
        $router->setCmd(Cmd::Broadcast);
        //将广播对象序列化到路由对象上
        $router->setData($broadcast->serializeToString());
        //将路由对象序列化后发给网关
        $data = $router->serializeToString();
        $socket->send($data);
        echo '连接打开：' . $connOpen->serializeToJsonString(), PHP_EOL;
    }

    /**
     * 处理用户发来的信息
     * @param AsyncSocket $socket
     * @param Transfer $transfer
     * @return void
     */
    public static function onMessage(AsyncSocket $socket, Transfer $transfer): void
    {
        $broadcast = new Broadcast();
        $broadcast->setData($transfer->getUniqId() . '：' . $transfer->getData());
        $router = new Router();
        $router->setCmd(Cmd::Broadcast);
        $router->setData($broadcast->serializeToString());
        $data = $router->serializeToString();
        $socket->send($data);
        echo '收到消息：' . $transfer->getData(), PHP_EOL;
    }

    /**
     * 处理用户连接关闭的信息
     * @param AsyncSocket $socket
     * @param ConnClose $connClose
     * @return void
     */
    public static function onClose(AsyncSocket $socket, ConnClose $connClose): void
    {
        $broadcast = new Broadcast();
        $broadcast->setData("有用户退出 --> " . $connClose->getUniqId());
        $router = new Router();
        $router->setCmd(Cmd::Broadcast);
        $router->setData($broadcast->serializeToString());
        $data = $router->serializeToString();
        $socket->send($data);
        echo '连接关闭：' . $connClose->serializeToJsonString(), PHP_EOL;
    }
}