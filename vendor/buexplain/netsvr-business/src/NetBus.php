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

namespace NetsvrBusiness;

use ErrorException;
use Google\Protobuf\Internal\RepeatedField;
use Hyperf\Context\ApplicationContext;
use Netsvr\Broadcast;
use Netsvr\CheckOnlineReq;
use Netsvr\CheckOnlineResp;
use Netsvr\Cmd;
use Netsvr\ConnInfoDelete;
use Netsvr\ConnInfoReq;
use Netsvr\ConnInfoResp;
use Netsvr\ConnInfoRespItem;
use Netsvr\ConnInfoUpdate;
use Netsvr\ForceOffline;
use Netsvr\ForceOfflineGuest;
use Netsvr\LimitReq;
use Netsvr\LimitResp;
use Netsvr\LimitRespItem;
use Netsvr\MetricsResp;
use Netsvr\MetricsRespItem;
use Netsvr\Multicast;
use Netsvr\Router;
use Netsvr\SingleCast;
use Netsvr\SingleCastBulk;
use Netsvr\TopicCountResp;
use Netsvr\TopicDelete;
use Netsvr\TopicListResp;
use Netsvr\TopicPublish;
use Netsvr\TopicSubscribe;
use Netsvr\TopicUniqIdCountReq;
use Netsvr\TopicUniqIdCountResp;
use Netsvr\TopicUniqIdListReq;
use Netsvr\TopicUniqIdListResp;
use Netsvr\TopicUniqIdListRespItem;
use Netsvr\TopicUnsubscribe;
use Netsvr\UniqIdCountResp;
use Netsvr\UniqIdListResp;
use NetsvrBusiness\Contract\MainSocketManagerInterface;
use NetsvrBusiness\Contract\SocketInterface;
use NetsvrBusiness\Contract\ServerIdConvertInterface;
use NetsvrBusiness\Contract\TaskSocketInterface;
use NetsvrBusiness\Contract\TaskSocketPoolMangerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Swoole\Coroutine;
use Throwable;

/**
 * 网络总线类，主打的就是与网关服务交互
 */
class NetBus
{
    /**
     * 更新客户在网关存储的信息
     * @param ConnInfoUpdate $connInfoUpdate
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function connInfoUpdate(ConnInfoUpdate $connInfoUpdate): void
    {
        $router = new Router();
        $router->setCmd(Cmd::ConnInfoUpdate);
        $router->setData($connInfoUpdate->serializeToString());
        self::sendToSocketOfMainOrTaskByUniqId($connInfoUpdate->getUniqId(), $router);
    }

    /**
     * 删除客户在网关存储的信息
     * @param ConnInfoDelete $connInfoDelete
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function connInfoDelete(ConnInfoDelete $connInfoDelete): void
    {
        $router = new Router();
        $router->setCmd(Cmd::ConnInfoDelete);
        $router->setData($connInfoDelete->serializeToString());
        self::sendToSocketOfMainOrTaskByUniqId($connInfoDelete->getUniqId(), $router);
    }

    /**
     * 广播
     * @param string $data 需要发送的数据
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    public static function broadcast(string $data): void
    {
        $broadcast = new Broadcast();
        $broadcast->setData($data);
        $router = new Router();
        $router->setCmd(Cmd::Broadcast);
        $router->setData($broadcast->serializeToString());
        self::sendToSocketsOfMainOrTask($router);
    }

    /**
     * 组播
     * @param array|string|string[] $uniqIds 目标客户的网关uniqId
     * @param string $data 需要发送的数据
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function multicast(array|string $uniqIds, string $data): void
    {
        $uniqIds = (array)$uniqIds;
        //网关是单点部署或者是只有一个待发送的uniqId，则直接发送
        if (self::isSinglePoint() || count($uniqIds) == 1) {
            $multicast = new Multicast();
            $multicast->setUniqIds($uniqIds);
            $multicast->setData($data);
            $router = (new Router())->setCmd(Cmd::Multicast)->setData($multicast->serializeToString());
            self::sendToSocketOfMainOrTaskByUniqId($uniqIds[0], $router);
            return;
        }
        //对uniqId按所属网关进行分组
        $serverGroup = self::uniqIdsGroupByServerId($uniqIds);
        //循环发送给各个网关
        foreach ($serverGroup as $serverId => $currentUniqIds) {
            $multicast = (new Multicast())->setData($data)->setUniqIds($currentUniqIds)->serializeToString();
            $router = (new Router())->setCmd(Cmd::Multicast);
            $router->setData($multicast);
            self::sendToSocketOfMainOrTaskByServerId($serverId, $router);
        }
    }

    /**
     * 单播
     * @param string $uniqId 目标客户的网关uniqId
     * @param string $data 需要发送的数据
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function singleCast(string $uniqId, string $data): void
    {
        $singleCast = new SingleCast();
        $singleCast->setUniqId($uniqId);
        $singleCast->setData($data);
        $router = new Router();
        $router->setCmd(Cmd::SingleCast);
        $router->setData($singleCast->serializeToString());
        self::sendToSocketOfMainOrTaskByUniqId($uniqId, $router);
    }

    /**
     * 批量单播，一次性给多个用户发送不同的消息
     * @param array $uniqIdDataMap key是用户的uniqId，value是发给用户的数据
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function singleCastBulk(array $uniqIdDataMap): void
    {
        if (count($uniqIdDataMap) == 0) {
            return;
        }
        //网关是单机部署或者是只给一个用户发消息，则直接构造批量单播对象发送
        if (self::isSinglePoint() || count($uniqIdDataMap) == 1) {
            $uniqIds = array_keys($uniqIdDataMap);
            $singleCastBulk = new SingleCastBulk();
            $singleCastBulk->setUniqIds($uniqIds);
            $singleCastBulk->setData(array_values($uniqIdDataMap));
            $router = new Router();
            $router->setCmd(Cmd::SingleCastBulk);
            $router->setData($singleCastBulk->serializeToString());
            self::sendToSocketOfMainOrTaskByUniqId($uniqIds[0], $router);
            return;
        }
        //网关是多机器部署，需要迭代每一个uniqId，并根据所在网关进行分组，然后再迭代每一个组，并把数据发送到对应网关
        $serverIdConvert = ApplicationContext::getContainer()->get(ServerIdConvertInterface::class);
        $bulks = [];
        foreach ($uniqIdDataMap as $uniqId => $data) {
            $serverId = $serverIdConvert->single($uniqId);
            $bulks[$serverId]['uniqIds'][] = $uniqId;
            $bulks[$serverId]['data'][] = $data;
        }
        foreach ($bulks as $serverId => $bulk) {
            $singleCastBulk = new SingleCastBulk();
            $singleCastBulk->setUniqIds($bulk['uniqIds']);
            $singleCastBulk->setData($bulk['data']);
            $router = new Router();
            $router->setCmd(Cmd::SingleCastBulk);
            $router->setData($singleCastBulk->serializeToString());
            self::sendToSocketOfMainOrTaskByServerId($serverId, $router);
        }
    }

    /**
     * 订阅若干个主题
     * @param string $uniqId 需要订阅主题的客户的uniqId
     * @param array|string|string[] $topics 需要订阅的主题，包含具体主题的索引数组，主题必须是string类型
     * @param string $data 订阅成功后需要发给客户的数据
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function topicSubscribe(string $uniqId, array|string $topics, string $data = ''): void
    {
        $topicSubscribe = new TopicSubscribe();
        $topicSubscribe->setUniqId($uniqId);
        $topicSubscribe->setTopics((array)$topics);
        $topicSubscribe->setData($data);
        $router = new Router();
        $router->setCmd(Cmd::TopicSubscribe);
        $router->setData($topicSubscribe->serializeToString());
        self::sendToSocketOfMainOrTaskByUniqId($topicSubscribe->getUniqId(), $router);
    }

    /**
     * 取消若干个已订阅的主题
     * @param string $uniqId 需要取消已订阅主的题的客户的uniqId
     * @param array|string|string[] $topics 取消订阅的主题，包含具体主题的索引数组，主题必须是string类型
     * @param string $data 取消订阅成功后需要发给客户的数据
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function topicUnsubscribe(string $uniqId, array|string $topics, string $data = ''): void
    {
        $topicUnsubscribe = new TopicUnsubscribe();
        $topicUnsubscribe->setUniqId($uniqId);
        $topicUnsubscribe->setTopics((array)$topics);
        $topicUnsubscribe->setData($data);
        $router = new Router();
        $router->setCmd(Cmd::TopicUnsubscribe);
        $router->setData($topicUnsubscribe->serializeToString());
        self::sendToSocketOfMainOrTaskByUniqId($topicUnsubscribe->getUniqId(), $router);
    }

    /**
     * 删除主题
     * @param array|string|string[] $topics 需要删除的主题
     * @param string $data 删除主题后，需要发送给订阅过该主题的客户的数据
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    public static function topicDelete(array|string $topics, string $data = ''): void
    {
        $topicDelete = new TopicDelete();
        $topicDelete->setTopics((array)$topics);
        $topicDelete->setData($data);
        $router = new Router();
        $router->setCmd(Cmd::TopicDelete);
        $router->setData($topicDelete->serializeToString());
        self::sendToSocketsOfMainOrTask($router);
    }

    /**
     * @param array|string|string[] $topics 需要发布数据的主题
     * @param string $data 需要发给客户的数据
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    public static function topicPublish(array|string $topics, string $data): void
    {
        $topicPublish = new TopicPublish();
        $topicPublish->setTopics((array)$topics);
        $topicPublish->setData($data);
        $router = new Router();
        $router->setCmd(Cmd::TopicPublish);
        $router->setData($topicPublish->serializeToString());
        self::sendToSocketsOfMainOrTask($router);
    }

    /**
     * 强制关闭某几个连接
     * @param array|string|string[] $uniqIds 需要强制下线的客户的uniqId
     * @param string $data 下线操作前需要发给客户的信息
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function forceOffline(array|string $uniqIds, string $data = ''): void
    {
        $uniqIds = (array)$uniqIds;
        if (self::isSinglePoint() || count($uniqIds) == 1) {
            $forceOffline = new ForceOffline();
            $forceOffline->setUniqIds($uniqIds);
            $forceOffline->setData($data);
            $router = (new Router())->setCmd(Cmd::ForceOffline)->setData($forceOffline->serializeToString());
            self::sendToSocketOfMainOrTaskByUniqId($uniqIds[0], $router);
            return;
        }
        $serverGroup = self::uniqIdsGroupByServerId($uniqIds);
        foreach ($serverGroup as $serverId => $currentUniqIds) {
            $forceOffline = new ForceOffline();
            $forceOffline->setUniqIds($currentUniqIds);
            $forceOffline->setData($data);
            $router = (new Router())->setCmd(Cmd::ForceOffline)->setData($forceOffline->serializeToString());
            self::sendToSocketOfMainOrTaskByServerId($serverId, $router);
        }
    }

    /**
     * 强制关闭某几个空session值的连接
     * @param array|string|string[] $uniqIds 需要强制下线的客户的uniqId，这个客户在网关存储的session必须是空字符串，如果不是，则不予处理
     * @param string $data 需要发给客户的数据，有这个数据，则转发给该连接，并在3秒倒计时后强制关闭连接，反之，立马关闭连接
     * @param int $delay 延迟多少秒执行，如果是0，立刻执行，否则就等待该秒数后，再根据uniqId获取连接，并判断连接是否存在session，没有就关闭连接，有就忽略
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function forceOfflineGuest(array|string $uniqIds, string $data = '', int $delay = 0): void
    {
        $uniqIds = (array)$uniqIds;
        if (self::isSinglePoint() || count($uniqIds) == 1) {
            $forceOfflineGuest = new ForceOfflineGuest();
            $forceOfflineGuest->setUniqIds($uniqIds);
            $forceOfflineGuest->setData($data);
            $forceOfflineGuest->setDelay($delay);
            $router = (new Router())->setCmd(Cmd::ForceOfflineGuest)->setData($forceOfflineGuest->serializeToString());
            self::sendToSocketOfMainOrTaskByUniqId($uniqIds[0], $router);
            return;
        }
        $serverGroup = self::uniqIdsGroupByServerId($uniqIds);
        foreach ($serverGroup as $serverId => $currentUniqIds) {
            $forceOfflineGuest = new ForceOfflineGuest();
            $forceOfflineGuest->setUniqIds($currentUniqIds);
            $forceOfflineGuest->setData($data);
            $forceOfflineGuest->setDelay($delay);
            $router = (new Router())->setCmd(Cmd::ForceOfflineGuest)->setData($forceOfflineGuest->serializeToString());
            self::sendToSocketOfMainOrTaskByServerId($serverId, $router);
        }
    }

    /**
     * 检查是否在线
     * @param array|string|string[] $uniqIds 包含uniqId的索引数组，或者是单个uniqId
     * @return array 包含当前在线的uniqId的索引数组
     * @throws ContainerExceptionInterface
     * @throws ErrorException
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    public static function checkOnline(array|string $uniqIds): array
    {
        $uniqIds = (array)$uniqIds;
        //网关单点部署，或者是只有一个待检查的uniqId，则直接获取与网关的socket连接进行操作
        if (self::isSinglePoint() || count($uniqIds) == 1) {
            $socket = self::getTaskSocketByUniqId($uniqIds[0]);
            if (!$socket instanceof TaskSocketInterface) {
                return [];
            }
            $checkOnlineReq = (new CheckOnlineReq())->setUniqIds($uniqIds)->serializeToString();
            $req = (new Router())->setCmd(Cmd::CheckOnline)->setData($checkOnlineReq)->serializeToString();
            try {
                $socket->send($req);
                $router = $socket->receive();
                if ($router === false) {
                    throw new ErrorException('call Cmd::CheckOnline failed because the connection to the netsvr was disconnected');
                }
                $resp = new CheckOnlineResp();
                $resp->mergeFromString($router->getData());
                return self::repeatedFieldToArray($resp->getUniqIds());
            } finally {
                $socket->release();
            }
        }
        //网关是多机器部署的，先对uniqId按所属网关进行分组
        $serverGroup = self::uniqIdsGroupByServerId($uniqIds);
        if (empty($serverGroup)) {
            return [];
        }
        //再并发的向每个网关请求
        $retCh = new Coroutine\Channel(count($serverGroup));
        foreach ($serverGroup as $serverId => $currentUniqIds) {
            Coroutine::create(function () use ($serverId, $currentUniqIds, $retCh) {
                try {
                    $socket = self::getTaskSocketByServerId($serverId);
                    if (!$socket instanceof TaskSocketInterface) {
                        //无效的serverId，即使拿不到与之对应的网关socket连接，也push一个结果进channel，否则接下来的读取channel会被阻塞住
                        $retCh->push(new CheckOnlineResp());
                    } else {
                        //拿到了severId对应的网关socket
                        try {
                            //构造请求参数
                            $checkOnlineReq = (new CheckOnlineReq())->setUniqIds($currentUniqIds)->serializeToString();
                            //构造路由
                            $req = (new Router())->setCmd(Cmd::CheckOnline)->setData($checkOnlineReq)->serializeToString();
                            //发送请求
                            $socket->send($req);
                            //接收响应
                            $router = $socket->receive();
                            if ($router === false) {
                                throw new ErrorException('call Cmd::CheckOnline failed because the connection to the netsvr was disconnected');
                            }
                            //解析响应
                            $resp = new CheckOnlineResp();
                            $resp->mergeFromString($router->getData());
                            //写入接收结果的channel
                            $retCh->push($resp);
                        } finally {
                            //将socket连接归还给连接池
                            $socket->release();
                        }
                    }
                } catch (Throwable $throwable) {
                    $retCh->push($throwable);
                }
            });
        }
        //从接收结果的channel中读取每个网关的返回结果
        $ret = [];
        try {
            for ($i = count($serverGroup); $i > 0; $i--) {
                $resp = $retCh->pop();
                //报错了，直接抛出异常
                if ($resp instanceof Throwable) {
                    throw $resp;
                }
                //响应了具体的在线的uniqId
                if ($resp instanceof CheckOnlineResp) {
                    array_push($ret, ...self::repeatedFieldToArray($resp->getUniqIds()));
                }
            }
        } finally {
            //这里一定要关闭掉这个channel，否则可能会导致，因为没有消费者了，还未返回结果的被协程阻塞住，从而出现协程泄漏的情况
            $retCh->close();
        }
        return $ret;
    }

    /**
     * 获取网关中的uniqId
     * @return array[]
     * @throws ContainerExceptionInterface
     * @throws ErrorException
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    public static function uniqIdList(): array
    {
        $sockets = self::getTaskSockets();
        if (empty($sockets)) {
            return [];
        }
        $req = (new Router())->setCmd(Cmd::UniqIdList)->serializeToString();
        //单机部署的网关，直接请求
        if (count($sockets) === 1) {
            try {
                $socket = $sockets[0];
                $socket->send($req);
                $router = $socket->receive();
                if ($router === false) {
                    throw new ErrorException('call Cmd::UniqIdList failed because the connection to the netsvr was disconnected');
                }
                $resp = new UniqIdListResp();
                $resp->mergeFromString($router->getData());
                return [[
                    'serverId' => $resp->getServerId(),
                    'uniqIds' => self::repeatedFieldToArray($resp->getUniqIds()),
                ]];
            } finally {
                /**
                 * @var $socket TaskSocketInterface
                 */
                $socket->release();
            }
        }
        //多机器部署的网关，并发请求
        $retCh = new Coroutine\Channel(count($sockets));
        foreach ($sockets as $socket) {
            Coroutine::create(function () use ($socket, $req, $retCh) {
                try {
                    $socket->send($req);
                    $router = $socket->receive();
                    if ($router === false) {
                        throw new ErrorException('call Cmd::UniqIdList failed because the connection to the netsvr was disconnected');
                    }
                    $resp = new UniqIdListResp();
                    $resp->mergeFromString($router->getData());
                    $retCh->push($resp);
                } catch (Throwable $throwable) {
                    $retCh->push($throwable);
                } finally {
                    /**
                     * @var $socket TaskSocketInterface
                     */
                    $socket->release();
                }
            });
        }
        //接收响应结果
        $ret = [];
        try {
            for ($i = count($sockets); $i > 0; $i--) {
                $resp = $retCh->pop();
                if ($resp instanceof Throwable) {
                    throw $resp;
                }
                if ($resp instanceof UniqIdListResp) {
                    $ret[] = [
                        'serverId' => $resp->getServerId(),
                        'uniqIds' => self::repeatedFieldToArray($resp->getUniqIds()),
                    ];
                }
            }
        } finally {
            $retCh->close();
        }
        return $ret;
    }

    /**
     * 统计网关的在线连接数
     * @return array[]
     * @throws ContainerExceptionInterface
     * @throws ErrorException
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    public static function uniqIdCount(): array
    {
        $sockets = self::getTaskSockets();
        if (empty($sockets)) {
            return [];
        }
        $req = (new Router())->setCmd(Cmd::UniqIdCount)->serializeToString();
        //网关是单体架构部署的，直接请求
        if (count($sockets) === 1) {
            try {
                $socket = $sockets[0];
                $socket->send($req);
                $router = $socket->receive();
                if ($router === false) {
                    throw new ErrorException('call Cmd::UniqIdCount failed because the connection to the netsvr was disconnected');
                }
                $resp = new UniqIdCountResp();
                $resp->mergeFromString($router->getData());
                return [[
                    'serverId' => $resp->getServerId(),
                    'count' => $resp->getCount(),
                ]];
            } finally {
                /**
                 * @var $socket TaskSocketInterface
                 */
                $socket->release();
            }
        }
        //网关是多机器部署的，并发请求
        $retCh = new Coroutine\Channel(count($sockets));
        foreach ($sockets as $socket) {
            Coroutine::create(function () use ($socket, $req, $retCh) {
                try {
                    $socket->send($req);
                    $router = $socket->receive();
                    if ($router === false) {
                        throw new ErrorException('call Cmd::UniqIdCount failed because the connection to the netsvr was disconnected');
                    }
                    $resp = new UniqIdCountResp();
                    $resp->mergeFromString($router->getData());
                    $retCh->push($resp);
                } catch (Throwable $throwable) {
                    $retCh->push($throwable);
                } finally {
                    /**
                     * @var $socket TaskSocketInterface
                     */
                    $socket->release();
                }
            });
        }
        //接收结果
        $ret = [];
        try {
            for ($i = count($sockets); $i > 0; $i--) {
                $resp = $retCh->pop();
                if ($resp instanceof Throwable) {
                    throw $resp;
                }
                if ($resp instanceof UniqIdCountResp) {
                    $ret[] = [
                        'serverId' => $resp->getServerId(),
                        'count' => $resp->getCount(),
                    ];
                }
            }
        } finally {
            $retCh->close();
        }
        return $ret;
    }

    /**
     * 统计网关的主题数量
     * @return array[]
     * @throws ContainerExceptionInterface
     * @throws ErrorException
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    public static function topicCount(): array
    {
        $sockets = self::getTaskSockets();
        if (empty($sockets)) {
            return [];
        }
        $req = (new Router())->setCmd(Cmd::TopicCount)->serializeToString();
        //直接请求单个网关
        if (count($sockets) === 1) {
            try {
                $socket = $sockets[0];
                $socket->send($req);
                $router = $socket->receive();
                if ($router === false) {
                    throw new ErrorException('call Cmd::TopicCount failed because the connection to the netsvr was disconnected');
                }
                $resp = new TopicCountResp();
                $resp->mergeFromString($router->getData());
                return [[
                    'serverId' => $resp->getServerId(),
                    'count' => $resp->getCount(),
                ]];
            } finally {
                /**
                 * @var $socket TaskSocketInterface
                 */
                $socket->release();
            }
        }
        //并发请求多个网关
        $retCh = new Coroutine\Channel(count($sockets));
        foreach ($sockets as $socket) {
            Coroutine::create(function () use ($socket, $req, $retCh) {
                try {
                    $socket->send($req);
                    $router = $socket->receive();
                    if ($router === false) {
                        throw new ErrorException('call Cmd::TopicCount failed because the connection to the netsvr was disconnected');
                    }
                    $resp = new TopicCountResp();
                    $resp->mergeFromString($router->getData());
                    $retCh->push($resp);
                } catch (Throwable $throwable) {
                    $retCh->push($throwable);
                } finally {
                    /**
                     * @var $socket TaskSocketInterface
                     */
                    $socket->release();
                }
            });
        }
        //接收响应
        $ret = [];
        try {
            for ($i = count($sockets); $i > 0; $i--) {
                $resp = $retCh->pop();
                if ($resp instanceof Throwable) {
                    throw $resp;
                }
                if ($resp instanceof TopicCountResp) {
                    $ret[] = [
                        'serverId' => $resp->getServerId(),
                        'count' => $resp->getCount(),
                    ];
                }
            }
        } finally {
            $retCh->close();
        }
        return $ret;
    }

    /**
     * 获取网关的全部主题
     * @return array[]
     * @throws ContainerExceptionInterface
     * @throws ErrorException
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    public static function topicList(): array
    {
        $sockets = self::getTaskSockets();
        if (empty($sockets)) {
            return [];
        }
        $req = (new Router())->setCmd(Cmd::TopicList)->serializeToString();
        //直接请求单个网关
        if (count($sockets) === 1) {
            try {
                $socket = $sockets[0];
                $socket->send($req);
                $router = $socket->receive();
                if ($router === false) {
                    throw new ErrorException('call Cmd::TopicList failed because the connection to the netsvr was disconnected');
                }
                $resp = new TopicListResp();
                $resp->mergeFromString($router->getData());
                return [[
                    'serverId' => $resp->getServerId(),
                    'topics' => self::repeatedFieldToArray($resp->getTopics()),
                ]];
            } finally {
                /**
                 * @var $socket TaskSocketInterface
                 */
                $socket->release();
            }
        }
        //并发请求多个网关
        $retCh = new Coroutine\Channel(count($sockets));
        foreach ($sockets as $socket) {
            Coroutine::create(function () use ($socket, $req, $retCh) {
                try {
                    $socket->send($req);
                    $router = $socket->receive();
                    if ($router === false) {
                        throw new ErrorException('call Cmd::TopicList failed because the connection to the netsvr was disconnected');
                    }
                    $resp = new TopicListResp();
                    $resp->mergeFromString($router->getData());
                    $retCh->push($resp);
                } catch (Throwable $throwable) {
                    $retCh->push($throwable);
                } finally {
                    /**
                     * @var $socket TaskSocketInterface
                     */
                    $socket->release();
                }
            });
        }
        //接收响应
        $ret = [];
        try {
            for ($i = count($sockets); $i > 0; $i--) {
                $resp = $retCh->pop();
                if ($resp instanceof Throwable) {
                    throw $resp;
                }
                if ($resp instanceof TopicListResp) {
                    $ret[] = [
                        'serverId' => $resp->getServerId(),
                        'topics' => self::repeatedFieldToArray($resp->getTopics()),
                    ];
                }
            }
        } finally {
            $retCh->close();
        }
        return $ret;
    }

    /**
     * 获取网关中某几个主题包含的uniqId
     * @param array|string|string[] $topics
     * @return array[] 注意一个uniqId可能订阅了多个主题
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    public static function topicUniqIdList(array|string $topics): array
    {
        $sockets = self::getTaskSockets();
        if (empty($sockets)) {
            return [];
        }
        $topicUniqIdListReq = (new TopicUniqIdListReq())->setTopics((array)$topics)->serializeToString();
        $req = (new Router())->setCmd(Cmd::TopicUniqIdList)->setData($topicUniqIdListReq)->serializeToString();
        //网关是单机部署的，直接请求
        if (count($sockets) === 1) {
            try {
                $socket = $sockets[0];
                $socket->send($req);
                $router = $socket->receive();
                if ($router === false) {
                    throw new ErrorException('call Cmd::TopicUniqIdList failed because the connection to the netsvr was disconnected');
                }
                $resp = new TopicUniqIdListResp();
                $resp->mergeFromString($router->getData());
                $ret = [];
                foreach ($resp->getItems() as $topic => $uniqIds) {
                    /**
                     * @var $uniqIds TopicUniqIdListRespItem
                     */
                    $ret[] = [
                        'topic' => $topic,
                        'uniqIds' => self::repeatedFieldToArray($uniqIds->getUniqIds()),
                    ];
                }
                return $ret;
            } finally {
                /**
                 * @var $socket TaskSocketInterface
                 */
                $socket->release();
            }
        }
        //网关是多机器部署的，并发请求每个网关机器
        $retCh = new Coroutine\Channel(count($sockets));
        foreach ($sockets as $socket) {
            Coroutine::create(function () use ($socket, $req, $retCh) {
                try {
                    $socket->send($req);
                    $router = $socket->receive();
                    if ($router === false) {
                        throw new ErrorException('call Cmd::TopicUniqIdList failed because the connection to the netsvr was disconnected');
                    }
                    $resp = new TopicUniqIdListResp();
                    $resp->mergeFromString($router->getData());
                    $retCh->push($resp);
                } catch (Throwable $throwable) {
                    $retCh->push($throwable);
                } finally {
                    /**
                     * @var $socket TaskSocketInterface
                     */
                    $socket->release();
                }
            });
        }
        //接收结果
        $ret = [];
        try {
            for ($i = count($sockets); $i > 0; $i--) {
                $resp = $retCh->pop();
                if ($resp instanceof Throwable) {
                    throw $resp;
                }
                if ($resp instanceof TopicUniqIdListResp) {
                    foreach ($resp->getItems() as $topic => $uniqIds) {
                        /**
                         * @var $uniqIds TopicUniqIdListRespItem
                         */
                        if (isset($ret[$topic])) {
                            array_push($ret[$topic]['uniqIds'], ...self::repeatedFieldToArray($uniqIds->getUniqIds()));
                        } else {
                            $ret[$topic] = [
                                'topic' => $topic,
                                'uniqIds' => self::repeatedFieldToArray($uniqIds->getUniqIds()),
                            ];
                        }
                    }
                }
            }
        } finally {
            $retCh->close();
        }
        return array_values($ret);
    }

    /**
     * 统计网关中某几个主题包含的连接数
     * @param array|string|string[] $topics 需要统计连接数的主题
     * @param bool $allTopic 是否统计全部主题的连接数
     * @return array[]
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    public static function topicUniqIdCount(array|string $topics, bool $allTopic = false): array
    {
        $sockets = self::getTaskSockets();
        if (empty($sockets)) {
            return [];
        }
        $topicUniqIdCountReq = (new TopicUniqIdCountReq())->setTopics((array)$topics)->setCountAll($allTopic);
        $req = (new Router())->setCmd(Cmd::TopicUniqIdCount)->setData($topicUniqIdCountReq->serializeToString())->serializeToString();
        if (count($sockets) === 1) {
            try {
                $socket = $sockets[0];
                $socket->send($req);
                $router = $socket->receive();
                if ($router === false) {
                    throw new ErrorException('call Cmd::TopicUniqIdCount failed because the connection to the netsvr was disconnected');
                }
                $resp = new TopicUniqIdCountResp();
                $resp->mergeFromString($router->getData());
                $ret = [];
                foreach ($resp->getItems() as $currentTopic => $count) {
                    $ret[] = [
                        'topic' => $currentTopic,
                        'count' => $count,
                    ];
                }
                return $ret;
            } finally {
                /**
                 * @var $socket TaskSocketInterface
                 */
                $socket->release();
            }
        }
        $retCh = new Coroutine\Channel(count($sockets));
        foreach ($sockets as $socket) {
            Coroutine::create(function () use ($socket, $req, $retCh) {
                try {
                    $socket->send($req);
                    $router = $socket->receive();
                    if ($router === false) {
                        throw new ErrorException('call Cmd::TopicUniqIdCount failed because the connection to the netsvr was disconnected');
                    }
                    $resp = new TopicUniqIdCountResp();
                    $resp->mergeFromString($router->getData());
                    $retCh->push($resp);
                } catch (Throwable $throwable) {
                    $retCh->push($throwable);
                } finally {
                    /**
                     * @var $socket TaskSocketInterface
                     */
                    $socket->release();
                }
            });
        }
        $ret = [];
        try {
            for ($i = count($sockets); $i > 0; $i--) {
                $resp = $retCh->pop();
                if ($resp instanceof Throwable) {
                    throw $resp;
                }
                if ($resp instanceof TopicUniqIdCountResp) {
                    foreach ($resp->getItems() as $currentTopic => $count) {
                        if (!isset($ret[$currentTopic])) {
                            $ret[$currentTopic] = [
                                'topic' => $currentTopic,
                                'count' => $count,
                            ];
                            continue;
                        }
                        $ret[$currentTopic]['count'] += $count;
                    }
                }
            }
        } finally {
            $retCh->close();
        }
        return array_values($ret);
    }

    /**
     * 获取目标uniqId在网关中存储的信息
     * @param array|string|string[] $uniqIds
     * @return array[]
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    public static function connInfo(array|string $uniqIds): array
    {
        $uniqIds = (array)$uniqIds;
        if (self::isSinglePoint() || count($uniqIds) == 1) {
            $socket = self::getTaskSocketByUniqId($uniqIds[0]);
            if (!$socket instanceof TaskSocketInterface) {
                return [];
            }
            $connInfoReq = (new ConnInfoReq())->setUniqIds($uniqIds);
            $req = (new Router())->setCmd(Cmd::ConnInfo)->setData($connInfoReq->serializeToString())->serializeToString();
            try {
                $socket->send($req);
                $router = $socket->receive();
                if ($router === false) {
                    throw new ErrorException('call Cmd::ConnInfo failed because the connection to the netsvr was disconnected');
                }
                $resp = new ConnInfoResp();
                $resp->mergeFromString($router->getData());
                $ret = [];
                foreach ($resp->getItems() as $currentUniqId => $info) {
                    /**
                     * @var $info ConnInfoRespItem
                     */
                    $ret[] = [
                        'uniqId' => $currentUniqId,
                        'topics' => self::repeatedFieldToArray($info->getTopics()),
                        'session' => $info->getSession(),
                    ];
                }
                return $ret;
            } finally {
                $socket->release();
            }
        }
        $serverGroup = self::uniqIdsGroupByServerId($uniqIds);
        if (empty($serverGroup)) {
            return [];
        }
        $retCh = new Coroutine\Channel(count($serverGroup));
        foreach ($serverGroup as $serverId => $currentUniqIds) {
            Coroutine::create(function () use ($serverId, $currentUniqIds, $retCh) {
                try {
                    $socket = self::getTaskSocketByServerId($serverId);
                    if (!$socket instanceof TaskSocketInterface) {
                        $retCh->push(new ConnInfoResp());
                    } else {
                        try {
                            $connInfoReq = (new ConnInfoReq())->setUniqIds($currentUniqIds)->serializeToString();
                            $req = (new Router())->setCmd(Cmd::ConnInfo)->setData($connInfoReq)->serializeToString();
                            $socket->send($req);
                            $router = $socket->receive();
                            if ($router === false) {
                                throw new ErrorException('call Cmd::ConnInfo failed because the connection to the netsvr was disconnected');
                            }
                            $resp = new ConnInfoResp();
                            $resp->mergeFromString($router->getData());
                            $retCh->push($resp);
                        } finally {
                            $socket->release();
                        }
                    }
                } catch (Throwable $throwable) {
                    $retCh->push($throwable);
                }
            });
        }
        $ret = [];
        try {
            for ($i = count($serverGroup); $i > 0; $i--) {
                $resp = $retCh->pop();
                if ($resp instanceof Throwable) {
                    throw $resp;
                }
                if ($resp instanceof ConnInfoResp) {
                    foreach ($resp->getItems() as $currentUniqId => $info) {
                        /**
                         * @var $info ConnInfoRespItem
                         */
                        $ret[] = [
                            'uniqId' => $currentUniqId,
                            'topics' => self::repeatedFieldToArray($info->getTopics()),
                            'session' => $info->getSession(),
                        ];
                    }
                }
            }
        } finally {
            $retCh->close();
        }
        return $ret;
    }

    /**
     * @param int $precision 统计值的保留小数位
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    public static function metrics(int $precision = 3): array
    {
        $sockets = self::getTaskSockets();
        if (empty($sockets)) {
            return [];
        }
        $req = (new Router())->setCmd(Cmd::Metrics)->serializeToString();
        $retCh = new Coroutine\Channel(count($sockets));
        foreach ($sockets as $socket) {
            Coroutine::create(function () use ($socket, $req, $retCh) {
                try {
                    $socket->send($req);
                    $router = $socket->receive();
                    if ($router === false) {
                        throw new ErrorException('call Cmd::Metrics failed because the connection to the netsvr was disconnected');
                    }
                    $resp = new MetricsResp();
                    $resp->mergeFromString($router->getData());
                    $retCh->push($resp);
                } catch (Throwable $throwable) {
                    $retCh->push($throwable);
                } finally {
                    /**
                     * @var $socket TaskSocketInterface
                     */
                    $socket->release();
                }
            });
        }
        $ret = [];
        try {
            for ($i = count($sockets); $i > 0; $i--) {
                $resp = $retCh->pop();
                if ($resp instanceof Throwable) {
                    throw $resp;
                }
                if ($resp instanceof MetricsResp) {
                    foreach ($resp->getItems() as $metricsItem => $metricsValue) {
                        /**
                         * @var $metricsValue MetricsRespItem
                         */
                        $ret[] = [
                            //网关服务的severId
                            'serverId' => $resp->getServerId(),
                            //统计的服务状态项，具体含义请移步：https://github.com/buexplain/netsvr/blob/main/internal/metrics/metrics.go
                            'item' => $metricsItem,
                            //总数
                            'count' => $metricsValue->getCount(),
                            //每秒速率
                            'meanRate' => round($metricsValue->getMeanRate(), $precision),
                            //每秒速率的最大值
                            'meanRateMax' => round($metricsValue->getMeanRateMax(), $precision),
                            //每1分钟速率
                            'rate1' => round($metricsValue->getRate1(), $precision),
                            //每1分钟速率的最大值
                            'rate1Max' => round($metricsValue->getRate1Max(), $precision),
                            //每5分钟速率
                            'rate5' => round($metricsValue->getRate5(), $precision),
                            //每5分钟速率的最大值
                            'rate5Max' => round($metricsValue->getRate5Max(), $precision),
                            //每15分钟速率
                            'rate15' => round($metricsValue->getRate15(), $precision),
                            //每15分钟速率的最大值
                            'rate15Max' => round($metricsValue->getRate15Max(), $precision),
                        ];
                    }
                }
            }
        } finally {
            $retCh->close();
        }
        return $ret;
    }

    /**
     * 设置或读取网关针对business的每秒转发数量的限制的配置
     * @param LimitReq|null $limitReq
     * @param int $serverId 需要变更的目标网关，如果是-1则表示与所有网关进行交互
     * @return array[]
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    public static function limit(LimitReq $limitReq = null, int $serverId = -1): array
    {
        if ($serverId === -1) {
            $sockets = self::getTaskSockets();
        } else {
            $resp = self::getTaskSocketByServerId($serverId);
            if (!empty($resp)) {
                $sockets = [$resp];
            } else {
                $sockets = [];
            }
        }
        if (empty($sockets)) {
            return [];
        }
        if ($limitReq === null) {
            $limitReq = new LimitReq();
        }
        $req = (new Router())->setCmd(Cmd::Limit)->setData($limitReq->serializeToString())->serializeToString();
        $retCh = new Coroutine\Channel(count($sockets));
        foreach ($sockets as $socket) {
            Coroutine::create(function () use ($socket, $req, $retCh) {
                try {
                    $socket->send($req);
                    $router = $socket->receive();
                    if ($router === false) {
                        throw new ErrorException('call Cmd::Limit failed because the connection to the netsvr was disconnected');
                    }
                    $resp = new LimitResp();
                    $resp->mergeFromString($router->getData());
                    $retCh->push($resp);
                } catch (Throwable $throwable) {
                    $retCh->push($throwable);
                } finally {
                    /**
                     * @var $socket TaskSocketInterface
                     */
                    $socket->release();
                }
            });
        }
        $ret = [];
        try {
            for ($i = count($sockets); $i > 0; $i--) {
                $resp = $retCh->pop();
                if ($resp instanceof Throwable) {
                    throw $resp;
                }
                if ($resp instanceof LimitResp) {
                    foreach ($resp->getItems() as $item) {
                        /**
                         * @var $item LimitRespItem
                         */
                        $ret[] = [
                            //网关服务的severId
                            'serverId' => $resp->getServerId(),
                            //限流器的名字
                            'name' => $item->getName(),
                            //使用该限流器的workerId，就是business向网关发起注册的时候填写的workerId
                            'workerIds' => self::repeatedFieldToArray($item->getWorkerIds()),
                            //当前这些workerId每秒最多接收到的网关转发数据次数，注意是这些workerId的每秒接收次数，不是单个workerId的每秒接收次数
                            'concurrency' => $item->getConcurrency(),
                        ];
                    }
                }
            }
        } finally {
            $retCh->close();
        }
        return $ret;
    }

    /**
     * @param Router $router
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    protected static function sendToSocketsOfMainOrTask(Router $router): void
    {
        //优先通过主socket发送
        $sockets = self::getMainSockets();
        if (!empty($sockets)) {
            $data = $router->serializeToString();
            foreach ($sockets as $socket) {
                $socket->send($data);
            }
            return;
        }
        //主socket不存在，再通过任务socket发送
        //如果是在http服务器内，通过网关下发消息给客户，则主socket是不存在的
        //主socket必须是通过 php bin/hyperf.php business:start 启动后才会存在
        /**
         * @var $sockets TaskSocketInterface[]
         */
        $sockets = self::getTaskSockets();
        if (empty($sockets)) {
            return;
        }
        $data = $router->serializeToString();
        //如果是单机部署的网关，则直接发送，不必走下面的多协程并发发送的逻辑
        if (count($sockets) === 1) {
            try {
                $sockets[0]->send($data);
            } catch (Throwable) {
            } finally {
                $sockets[0]->release();
            }
            return;
        }
        //多机器部署的网关服务，开启协程并发发送
        foreach ($sockets as $socket) {
            Coroutine::create(function () use ($data, $socket) {
                try {
                    $socket->send($data);
                } catch (Throwable) {
                } finally {
                    $socket->release();
                }
            });
        }
    }

    /**
     * @param string $uniqId
     * @param Router $router
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected static function sendToSocketOfMainOrTaskByUniqId(string $uniqId, Router $router): void
    {
        $socket = self::getMainSocketByUniqId($uniqId);
        if (!empty($socket)) {
            $socket->send($router->serializeToString());
            return;
        }
        $socket = self::getTaskSocketByUniqId($uniqId);
        if (!$socket instanceof TaskSocketInterface) {
            return;
        }
        try {
            $socket->send($router->serializeToString());
        } finally {
            $socket->release();
        }
    }

    /**
     * @param int $serverId
     * @param Router $router
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected static function sendToSocketOfMainOrTaskByServerId(int $serverId, Router $router): void
    {
        $socket = self::getMainSocketByServerId($serverId);
        if (!empty($socket)) {
            $socket->send($router->serializeToString());
            return;
        }
        $socket = self::getTaskSocketByServerId($serverId);
        if ($socket instanceof TaskSocketInterface) {
            Coroutine::create(function () use ($router, $socket) {
                try {
                    $socket->send($router->serializeToString());
                } catch (Throwable) {
                } finally {
                    $socket->release();
                }
            });
        }
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    public static function getTaskSockets(): array
    {
        return ApplicationContext::getContainer()->get(TaskSocketPoolMangerInterface::class)->getSockets();
    }

    /**
     * @param int $serverId
     * @return TaskSocketInterface|null
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected static function getTaskSocketByServerId(int $serverId): ?TaskSocketInterface
    {
        return ApplicationContext::getContainer()->get(TaskSocketPoolMangerInterface::class)->getSocketByServerId($serverId);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected static function getTaskSocketByUniqId(string $uniqId): ?TaskSocketInterface
    {
        $container = ApplicationContext::getContainer();
        $serverId = $container->get(ServerIdConvertInterface::class)->single($uniqId);
        return $container->get(TaskSocketPoolMangerInterface::class)->getSocketByServerId($serverId);
    }

    /**
     * @return array|SocketInterface[]
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    protected static function getMainSockets(): array
    {
        return ApplicationContext::getContainer()->get(MainSocketManagerInterface::class)->getSockets();
    }

    /**
     * @param int $serverId
     * @return SocketInterface|null
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected static function getMainSocketByServerId(int $serverId): ?SocketInterface
    {
        return ApplicationContext::getContainer()->get(MainSocketManagerInterface::class)->getSocketByServerId($serverId);
    }

    /**
     * @param string $uniqId
     * @return SocketInterface|null
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function getMainSocketByUniqId(string $uniqId): ?SocketInterface
    {
        $container = ApplicationContext::getContainer();
        $serverId = $container->get(ServerIdConvertInterface::class)->single($uniqId);
        return $container->get(MainSocketManagerInterface::class)->getSocketByServerId($serverId);
    }

    /**
     * 判断网关是否为单点部署
     * @return bool
     */
    protected static function isSinglePoint(): bool
    {
        return count((array)\Hyperf\Config\config('business.netsvrWorkers', [])) == 1;
    }

    /**
     * 将uniqId进行分组，同一网关的归到一组
     * @param array $uniqIds 包含uniqId的数组
     * @return array key是serverId，value是包含uniqId的数组
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected static function uniqIdsGroupByServerId(array $uniqIds): array
    {
        $serverIdConvert = ApplicationContext::getContainer()->get(ServerIdConvertInterface::class);
        $convert = $serverIdConvert->bulk($uniqIds);
        $serverIdUniqIds = [];
        foreach ($convert as $uniqId => $serverId) {
            $serverIdUniqIds[$serverId][] = $uniqId;
        }
        return $serverIdUniqIds;
    }

    protected static function repeatedFieldToArray(RepeatedField $repeatedField): array
    {
        $ret = [];
        foreach ($repeatedField as $item) {
            $ret[] = $item;
        }
        return $ret;
    }
}
