<?php

declare(strict_types=1);

namespace App\Controller;

use Netsvr\SingleCast;
use NetsvrBusiness\ClientRouterAsJson;
use NetsvrBusiness\Contract\WorkerSocketManagerInterface;
use Netsvr\Broadcast;
use Netsvr\Cmd;
use Netsvr\ConnClose;
use Netsvr\ConnOpen;
use Netsvr\Router;
use Netsvr\Transfer;

class WebsocketController
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

    /**
     * 广播消息
     * @param WorkerSocketManagerInterface $manager
     * @param Transfer $transfer
     * @param ClientRouterAsJson $clientRouter
     * @return void
     */
    public function broadcast(WorkerSocketManagerInterface $manager, Transfer $transfer, ClientRouterAsJson $clientRouter): void
    {
        $message = (string)$clientRouter->getData();
        $broadcast = new Broadcast();
        $broadcast->setData($transfer->getUniqId() . '：' . $message);
        $router = new Router();
        $router->setCmd(Cmd::Broadcast);
        $router->setData($broadcast->serializeToString());
        $manager->send($router->serializeToString());
        echo '收到广播消息：' . $transfer->getUniqId() . ' --> ' . $message, PHP_EOL;
    }

    /**
     * 单播消息给某个用户
     * @param WorkerSocketManagerInterface $manager
     * @param Transfer $transfer
     * @param ClientRouterAsJson $clientRouter
     * @return void
     */
    public function singleCast(WorkerSocketManagerInterface $manager, Transfer $transfer, ClientRouterAsJson $clientRouter): void
    {
        //获取用户发的单播的数据
        $data = (array)$clientRouter->getData();
        //发送的消息
        $message = $data['message'];
        //发送给谁
        $to = $data['to'];
        //构造一个网关服务需要的单播对象
        $singleCast = new SingleCast();
        //设置目标用户
        $singleCast->setUniqId($to);
        //设置消息
        $singleCast->setData($transfer->getUniqId() . '：' . $message);
        //构造一个网关服务需要的路由对象
        $router = new Router();
        //设置路由的命令为单播，网关收到该命令会执行单播的逻辑
        $router->setCmd(Cmd::SingleCast);
        //设置单播的数据
        $router->setData($singleCast->serializeToString());
        //根据目标用户的id，获取目标用户所在的网关服务的socket，并将数据发送给该socket
        $manager->getSocketByPrefixUniqId($to)?->send($router->serializeToString());
        echo '收到单播消息：from --> ' . $transfer->getUniqId() . ' to --> ' . $to . ' --> ' . $message, PHP_EOL;
    }
}