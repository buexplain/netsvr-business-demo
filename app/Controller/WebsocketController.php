<?php

declare(strict_types=1);

namespace App\Controller;

use App\Protocol\Cmd;
use App\Protocol\Proto\Protobuf\BroadcastProtocol;
use App\Protocol\Proto\Protobuf\GroupChatForAttachProtocol;
use App\Protocol\Proto\Protobuf\GroupChatForDetachProtocol;
use App\Protocol\Proto\Protobuf\GroupChatForSendProtocol;
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

    /**
     * 群聊之加入某个群
     * websocket在线测试工具发送：001{"cmd":5,"data":"{\"groupChatId\":\"测试群\"}"}
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function groupChatForAttach(Transfer $transfer, RouterInterface $clientRouter, GroupChatForAttachProtocol $clientData): void
    {
        //这里使用go的websocket服务器提供的订阅接口实现群聊功能，加入一个群相当于订阅了该群的消息
        //详细请看：https://github.com/buexplain/netsvr-protocol
        //注意，基于主题实现的群聊，最好控制在一万个群以内，因为go端的实现是用map存储的主题与用户的关系，如果主题太多，容易引起go的gc抖动
        //所以，如果要实现大规模的群，最好还是用组播（NetBus::multicast()）的方式去发送消息，思路是先找用户的群，然后再找群里的在线用户，最后组播给这些在线用户
        $clientRouter->setData("加入群：“" . $clientData->getGroupChatId() . "”成功");
        NetBus::topicSubscribe($transfer->getUniqId(), $clientData->getGroupChatId(), $clientRouter->encode());
    }

    /**
     * 群聊之退出某个群
     * websocket在线测试工具发送：001{"cmd":6,"data":"{\"groupChatId\":\"测试群\"}"}
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function groupChatForDetach(Transfer $transfer, RouterInterface $clientRouter, GroupChatForDetachProtocol $clientData): void
    {
        //退出一个群，相当于不再订阅该主题的消息
        $clientRouter->setData("退出群：“" . $clientData->getGroupChatId() . "”成功");
        NetBus::topicUnsubscribe($transfer->getUniqId(), $clientData->getGroupChatId(), $clientRouter->encode());
    }

    /**
     * 群聊之往某个群发送消息
     * websocket在线测试工具发送：001{"cmd":7,"data":"{\"groupChatId\":\"测试群\",\"message\":\"各位群友好！\"}"}
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function groupChatForSend(Transfer $transfer, RouterInterface $clientRouter, GroupChatForSendProtocol $clientData): void
    {
        //发群消息，相当于往该主题发布消息
        $clientData->setFromUser($transfer->getUniqId());
        $clientRouter->setData($clientData->encode());
        NetBus::topicPublish($clientData->getGroupChatId(), $clientRouter->encode());
    }
}