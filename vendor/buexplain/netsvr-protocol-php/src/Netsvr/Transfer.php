<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: transfer.proto

namespace Netsvr;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 *worker转发客户发送的信息到business
 *
 * Generated from protobuf message <code>netsvr.transfer.Transfer</code>
 */
class Transfer extends \Google\Protobuf\Internal\Message
{
    /**
     *当前发消息的客户的uniqId
     *
     * Generated from protobuf field <code>string uniqId = 1;</code>
     */
    protected $uniqId = '';
    /**
     *当前发消息的客户的session
     *
     * Generated from protobuf field <code>string session = 2;</code>
     */
    protected $session = '';
    /**
     *客户发送的信息
     *
     * Generated from protobuf field <code>bytes data = 3;</code>
     */
    protected $data = '';

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $uniqId
     *          当前发消息的客户的uniqId
     *     @type string $session
     *          当前发消息的客户的session
     *     @type string $data
     *          客户发送的信息
     * }
     */
    public function __construct($data = NULL) {
        \Netsvr\GPBMetadata\Transfer::initOnce();
        parent::__construct($data);
    }

    /**
     *当前发消息的客户的uniqId
     *
     * Generated from protobuf field <code>string uniqId = 1;</code>
     * @return string
     */
    public function getUniqId()
    {
        return $this->uniqId;
    }

    /**
     *当前发消息的客户的uniqId
     *
     * Generated from protobuf field <code>string uniqId = 1;</code>
     * @param string $var
     * @return $this
     */
    public function setUniqId($var)
    {
        GPBUtil::checkString($var, True);
        $this->uniqId = $var;

        return $this;
    }

    /**
     *当前发消息的客户的session
     *
     * Generated from protobuf field <code>string session = 2;</code>
     * @return string
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     *当前发消息的客户的session
     *
     * Generated from protobuf field <code>string session = 2;</code>
     * @param string $var
     * @return $this
     */
    public function setSession($var)
    {
        GPBUtil::checkString($var, True);
        $this->session = $var;

        return $this;
    }

    /**
     *客户发送的信息
     *
     * Generated from protobuf field <code>bytes data = 3;</code>
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     *客户发送的信息
     *
     * Generated from protobuf field <code>bytes data = 3;</code>
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

