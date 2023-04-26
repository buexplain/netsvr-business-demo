<?php

declare(strict_types=1);

namespace App\Controller;

use NetsvrBusiness\Contract\WorkerSocketManagerInterface;
use NetsvrBusiness\Contract\RouterInterface;
use Netsvr\Broadcast;
use Netsvr\Cmd;
use Netsvr\ConnClose;
use Netsvr\ConnOpen;
use Netsvr\Router;
use Netsvr\Transfer;

class WebsocketTestController
{
    /**
     * 处理用户连接打开信息
     * @param WorkerSocketManagerInterface $manager
     * @param ConnOpen $connOpen
     * @return void
     */
    public function onOpen(WorkerSocketManagerInterface $manager, ConnOpen $connOpen): void
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
        $manager->send($data);
        echo '连接打开：' . $connOpen->serializeToJsonString(), PHP_EOL;
    }

    /**
     * 处理用户发来的信息
     * @param WorkerSocketManagerInterface $manager 与网关服务的连接的管理器
     * @param Transfer $transfer 网关转发客户数据时，使用的对象
     * @param RouterInterface $clientRouter 客户发送业务数据时，使用路由
     * @return void
     */
    public function onMessage(WorkerSocketManagerInterface $manager, Transfer $transfer, RouterInterface $clientRouter): void
    {
        $clientRouter->setData($transfer->getUniqId() . '：' . $clientRouter->getData());
        $broadcast = new Broadcast();
        $broadcast->setData($clientRouter->encode());
        $router = new Router();
        $router->setCmd(Cmd::Broadcast);
        $router->setData($broadcast->serializeToString());
        $data = $router->serializeToString();
        $manager->send($data);
        echo '收到消息：' . $clientRouter->getCmd() . ' --> ' . $clientRouter->getData(), PHP_EOL;
    }

    /**
     * 处理用户连接关闭的信息
     * @param WorkerSocketManagerInterface $manager
     * @param ConnClose $connClose
     * @return void
     */
    public function onClose(WorkerSocketManagerInterface $manager, ConnClose $connClose): void
    {
        $broadcast = new Broadcast();
        $broadcast->setData("有用户退出 --> " . $connClose->getUniqId());
        $router = new Router();
        $router->setCmd(Cmd::Broadcast);
        $router->setData($broadcast->serializeToString());
        $data = $router->serializeToString();
        $manager->send($data);
        echo '连接关闭：' . $connClose->serializeToJsonString(), PHP_EOL;
    }
}