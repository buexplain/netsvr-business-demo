<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: registerReq.proto

namespace Netsvr;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 *business向worker请求，注册自己
 *注册逻辑：检查注册条件后，会给business的连接异步写入注册成功的信息、将business的连接注册到管理器，让business的连接接收网关转发的客户数据，如果注册失败，会返回失败的信息
 *如果不想接收来自客户的信息，只是与网关交互，可以不发起注册指令
 *
 * Generated from protobuf message <code>netsvr.registerReq.RegisterReq</code>
 */
class RegisterReq extends \Google\Protobuf\Internal\Message
{
    /**
     *workerId，取值区间是：[1,999]
     *业务层可以自己随意安排，如果多个business共用一个workerId，则网关在数据转发的过程中是轮询转发给business的
     *
     * Generated from protobuf field <code>int32 workerId = 1;</code>
     */
    protected $workerId = 0;
    /**
     *该参数表示接下来，需要worker服务器开启多少协程来处理本business的请求
     *如果本business，非常频繁的与worker交互,并且是那种组播、广播的耗时操作
     *可以考虑开大一点，但是也不能无限大，开太多也许不能解决问题，因为发送消息到客户连接是会被阻塞的，建议5~100条左右即可
     *请根据业务，实际压测一下试试，找到最佳的数量
     *请注意worker默认已经开启了一条协程来处理本business的请求，所以该值只有在大于1的时候才会开启更多协程
     *
     * Generated from protobuf field <code>uint32 processCmdGoroutineNum = 2;</code>
     */
    protected $processCmdGoroutineNum = 0;
    /**
     *网关唯一编号，如果该值与网关配置的值对不上号，网关会返回失败的信息
     *
     * Generated from protobuf field <code>uint32 serverId = 3;</code>
     */
    protected $serverId = 0;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type int $workerId
     *          workerId，取值区间是：[1,999]
     *          业务层可以自己随意安排，如果多个business共用一个workerId，则网关在数据转发的过程中是轮询转发给business的
     *     @type int $processCmdGoroutineNum
     *          该参数表示接下来，需要worker服务器开启多少协程来处理本business的请求
     *          如果本business，非常频繁的与worker交互,并且是那种组播、广播的耗时操作
     *          可以考虑开大一点，但是也不能无限大，开太多也许不能解决问题，因为发送消息到客户连接是会被阻塞的，建议5~100条左右即可
     *          请根据业务，实际压测一下试试，找到最佳的数量
     *          请注意worker默认已经开启了一条协程来处理本business的请求，所以该值只有在大于1的时候才会开启更多协程
     *     @type int $serverId
     *          网关唯一编号，如果该值与网关配置的值对不上号，网关会返回失败的信息
     * }
     */
    public function __construct($data = NULL) {
        \Netsvr\GPBMetadata\RegisterReq::initOnce();
        parent::__construct($data);
    }

    /**
     *workerId，取值区间是：[1,999]
     *业务层可以自己随意安排，如果多个business共用一个workerId，则网关在数据转发的过程中是轮询转发给business的
     *
     * Generated from protobuf field <code>int32 workerId = 1;</code>
     * @return int
     */
    public function getWorkerId()
    {
        return $this->workerId;
    }

    /**
     *workerId，取值区间是：[1,999]
     *业务层可以自己随意安排，如果多个business共用一个workerId，则网关在数据转发的过程中是轮询转发给business的
     *
     * Generated from protobuf field <code>int32 workerId = 1;</code>
     * @param int $var
     * @return $this
     */
    public function setWorkerId($var)
    {
        GPBUtil::checkInt32($var);
        $this->workerId = $var;

        return $this;
    }

    /**
     *该参数表示接下来，需要worker服务器开启多少协程来处理本business的请求
     *如果本business，非常频繁的与worker交互,并且是那种组播、广播的耗时操作
     *可以考虑开大一点，但是也不能无限大，开太多也许不能解决问题，因为发送消息到客户连接是会被阻塞的，建议5~100条左右即可
     *请根据业务，实际压测一下试试，找到最佳的数量
     *请注意worker默认已经开启了一条协程来处理本business的请求，所以该值只有在大于1的时候才会开启更多协程
     *
     * Generated from protobuf field <code>uint32 processCmdGoroutineNum = 2;</code>
     * @return int
     */
    public function getProcessCmdGoroutineNum()
    {
        return $this->processCmdGoroutineNum;
    }

    /**
     *该参数表示接下来，需要worker服务器开启多少协程来处理本business的请求
     *如果本business，非常频繁的与worker交互,并且是那种组播、广播的耗时操作
     *可以考虑开大一点，但是也不能无限大，开太多也许不能解决问题，因为发送消息到客户连接是会被阻塞的，建议5~100条左右即可
     *请根据业务，实际压测一下试试，找到最佳的数量
     *请注意worker默认已经开启了一条协程来处理本business的请求，所以该值只有在大于1的时候才会开启更多协程
     *
     * Generated from protobuf field <code>uint32 processCmdGoroutineNum = 2;</code>
     * @param int $var
     * @return $this
     */
    public function setProcessCmdGoroutineNum($var)
    {
        GPBUtil::checkUint32($var);
        $this->processCmdGoroutineNum = $var;

        return $this;
    }

    /**
     *网关唯一编号，如果该值与网关配置的值对不上号，网关会返回失败的信息
     *
     * Generated from protobuf field <code>uint32 serverId = 3;</code>
     * @return int
     */
    public function getServerId()
    {
        return $this->serverId;
    }

    /**
     *网关唯一编号，如果该值与网关配置的值对不上号，网关会返回失败的信息
     *
     * Generated from protobuf field <code>uint32 serverId = 3;</code>
     * @param int $var
     * @return $this
     */
    public function setServerId($var)
    {
        GPBUtil::checkUint32($var);
        $this->serverId = $var;

        return $this;
    }

}

