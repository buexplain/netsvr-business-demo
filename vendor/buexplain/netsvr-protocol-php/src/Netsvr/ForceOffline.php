<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: forceOffline.proto

namespace Netsvr;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 *business向worker请求，将某个连接强制关闭
 *
 * Generated from protobuf message <code>netsvr.forceOffline.ForceOffline</code>
 */
class ForceOffline extends \Google\Protobuf\Internal\Message
{
    /**
     *目标uniqId
     *
     * Generated from protobuf field <code>repeated string uniqIds = 1;</code>
     */
    private $uniqIds;
    /**
     *需要发给客户的数据，有这个数据，则转发给该连接，并在3秒倒计时后强制关闭连接，反之，立马关闭连接
     *
     * Generated from protobuf field <code>bytes data = 2;</code>
     */
    protected $data = '';

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type array<string>|\Google\Protobuf\Internal\RepeatedField $uniqIds
     *          目标uniqId
     *     @type string $data
     *          需要发给客户的数据，有这个数据，则转发给该连接，并在3秒倒计时后强制关闭连接，反之，立马关闭连接
     * }
     */
    public function __construct($data = NULL) {
        \Netsvr\GPBMetadata\ForceOffline::initOnce();
        parent::__construct($data);
    }

    /**
     *目标uniqId
     *
     * Generated from protobuf field <code>repeated string uniqIds = 1;</code>
     * @return \Google\Protobuf\Internal\RepeatedField
     */
    public function getUniqIds()
    {
        return $this->uniqIds;
    }

    /**
     *目标uniqId
     *
     * Generated from protobuf field <code>repeated string uniqIds = 1;</code>
     * @param array<string>|\Google\Protobuf\Internal\RepeatedField $var
     * @return $this
     */
    public function setUniqIds($var)
    {
        $arr = GPBUtil::checkRepeatedField($var, \Google\Protobuf\Internal\GPBType::STRING);
        $this->uniqIds = $arr;

        return $this;
    }

    /**
     *需要发给客户的数据，有这个数据，则转发给该连接，并在3秒倒计时后强制关闭连接，反之，立马关闭连接
     *
     * Generated from protobuf field <code>bytes data = 2;</code>
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     *需要发给客户的数据，有这个数据，则转发给该连接，并在3秒倒计时后强制关闭连接，反之，立马关闭连接
     *
     * Generated from protobuf field <code>bytes data = 2;</code>
     * @param string $var
     * @return $this
     */
    public function setData($var)
    {
        GPBUtil::checkString($var, False);
        $this->data = $var;

        return $this;
    }

}

