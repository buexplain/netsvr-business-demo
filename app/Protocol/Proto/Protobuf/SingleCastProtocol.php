<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: singleCastProtocol.proto

namespace App\Protocol\Proto\Protobuf;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 *单播给某个用户
 *
 * Generated from protobuf message <code>netsvrBusinessDemo.singleCastProtocol.SingleCastProtocol</code>
 */
class SingleCastProtocol extends \Google\Protobuf\Internal\Message implements \NetsvrBusiness\Contract\RouterDataInterface
{
    use \NetsvrBusiness\Contract\RouterAndDataForProtobufTrait;
    /**
     *消息
     *
     * Generated from protobuf field <code>string message = 1;</code>
     */
    protected $message = '';
    /**
     *发送方
     *
     * Generated from protobuf field <code>string fromUser = 2;</code>
     */
    protected $fromUser = '';
    /**
     *接收方
     *
     * Generated from protobuf field <code>string toUser = 3;</code>
     */
    protected $toUser = '';

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $message
     *          消息
     *     @type string $fromUser
     *          发送方
     *     @type string $toUser
     *          接收方
     * }
     */
    public function __construct($data = NULL) {
        \App\Protocol\Proto\Protobuf\GPBMetadata\SingleCastProtocol::initOnce();
        parent::__construct($data);
    }

    /**
     *消息
     *
     * Generated from protobuf field <code>string message = 1;</code>
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     *消息
     *
     * Generated from protobuf field <code>string message = 1;</code>
     * @param string $var
     * @return $this
     */
    public function setMessage($var)
    {
        GPBUtil::checkString($var, True);
        $this->message = $var;

        return $this;
    }

    /**
     *发送方
     *
     * Generated from protobuf field <code>string fromUser = 2;</code>
     * @return string
     */
    public function getFromUser()
    {
        return $this->fromUser;
    }

    /**
     *发送方
     *
     * Generated from protobuf field <code>string fromUser = 2;</code>
     * @param string $var
     * @return $this
     */
    public function setFromUser($var)
    {
        GPBUtil::checkString($var, True);
        $this->fromUser = $var;

        return $this;
    }

    /**
     *接收方
     *
     * Generated from protobuf field <code>string toUser = 3;</code>
     * @return string
     */
    public function getToUser()
    {
        return $this->toUser;
    }

    /**
     *接收方
     *
     * Generated from protobuf field <code>string toUser = 3;</code>
     * @param string $var
     * @return $this
     */
    public function setToUser($var)
    {
        GPBUtil::checkString($var, True);
        $this->toUser = $var;

        return $this;
    }

}

