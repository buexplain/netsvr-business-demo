<?php

declare(strict_types=1);

namespace App\Controller;

use App\Protocol\Cmd;
use App\Protocol\Proto\Protobuf\BroadcastProtocol;
use App\Protocol\Proto\Protobuf\SingleCastProtocol;

//只要在dependencies.php文件中，将\NetsvrBusiness\Contract\RouterInterface:class的实现替换成\NetsvrBusiness\Router\JsonRouter::class
//就可以使用下面两个已经注释掉的json版的协议
//use App\Protocol\Json\BroadcastProtocol;
//use App\Protocol\Json\SingleCastProtocol;
use Netsvr\ConnClose;
use Netsvr\ConnInfoUpdate;
use Netsvr\ConnOpen;
use Netsvr\Transfer;
use NetsvrBusiness\Contract\RouterInterface;
use NetsvrBusiness\NetBus;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Throwable;

class WebsocketController
{
    /**
     * 处理用户连接打开信息
     * @param ConnOpen $connOpen
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    public function onOpen(ConnOpen $connOpen): void
    {
        //先将连接信息广播给所有在线的人
        $broadcast = \Hyperf\Support\make(RouterInterface::class)
            ->setData("有新用户进来 --> " . $connOpen->getUniqId() . '，当前在线人数是：' . array_sum(array_column(NetBus::uniqIdCount(), 'count')))
            ->setCmd(Cmd::PUBLIC_WELCOME);
        NetBus::broadcast($broadcast->encode());
        //再往当前连接里面存储一些数据进去
        $info = new ConnInfoUpdate();
        //设置需要被修改的用户连接的uniqId
        $info->setUniqId($connOpen->getUniqId());
        //设置修改后需要下发给用户的信息
        $info->setData(\Hyperf\Support\make(RouterInterface::class)->setData("欢迎你的到来！")->setCmd(Cmd::PRIVATE_WELCOME)->encode());
        //设置session，这个一般校验账号密码后从数据库读出来的用户信息
        $info->setNewSession("名字：王某贵，userId：" . $connOpen->getUniqId());
        //让连接订阅一些主题，业务上讲，这些主题可以是用户加入的群的id、也可以是用户订阅的一些消息频道
        $info->setNewTopics(["订阅一个主题", "再订阅一个主题"]);
        NetBus::connInfoUpdate($info);
        echo '连接打开：' . $connOpen->serializeToJsonString(), PHP_EOL;
    }

    /**
     * 处理用户连接关闭的信息
     * @param ConnClose $connClose
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function onClose(ConnClose $connClose): void
    {
        NetBus::broadcast("有用户退出 --> " . $connClose->getSession());
        echo '连接关闭：' . $connClose->serializeToJsonString(), PHP_EOL;
    }

    /**
     * 广播消息
     * @param Transfer $transfer
     * @param RouterInterface $clientRouter
     * @param BroadcastProtocol $clientData
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function broadcast(Transfer $transfer, RouterInterface $clientRouter, BroadcastProtocol $clientData): void
    {
        //设置广播的用户是谁
        $clientData->setFromUser($transfer->getUniqId());
        //将业务数据重新格式化给客户数据的路由
        $clientRouter->setData($clientData->encode());
        //向网关发送广播数据
        NetBus::broadcast($clientRouter->encode());
        echo '收到广播消息：' . $clientData->getMessage(), PHP_EOL;
    }

    /**
     * 单播消息给某个用户
     * @param Transfer $transfer
     * @param RouterInterface $clientRouter
     * @param SingleCastProtocol $clientData
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function singleCast(Transfer $transfer, RouterInterface $clientRouter, SingleCastProtocol $clientData): void
    {
        $clientData->setFromUser($transfer->getUniqId());
        $clientRouter->setData($clientData->encode());
        NetBus::singleCast($clientData->getToUser(), $clientRouter->encode());
        echo '收到单播消息：from --> ' . $transfer->getUniqId() . ' to --> ' . $clientData->getToUser() . ' --> ' . $clientData->getMessage(), PHP_EOL;
    }
}