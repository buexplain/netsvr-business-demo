<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: groupChatForSendProtocol.proto

namespace App\Protocol\Proto\Protobuf;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 *群聊之往某个群发送消息
 *
 * Generated from protobuf message <code>netsvrBusinessDemo.groupChatForSendProtocol.GroupChatForSendProtocol</code>
 */
class GroupChatForSendProtocol extends \Google\Protobuf\Internal\Message implements \NetsvrBusiness\Contract\RouterDataInterface
{
    use \NetsvrBusiness\Contract\RouterAndDataForProtobufTrait;
    /**
     *群号
     *
     * Generated from protobuf field <code>string groupChatId = 1;</code>
     */
    protected $groupChatId = '';
    /**
     *发送方
     *
     * Generated from protobuf field <code>string fromUser = 2;</code>
     */
    protected $fromUser = '';
    /**
     *消息
     *
     * Generated from protobuf field <code>string message = 3;</code>
     */
    protected $message = '';

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $groupChatId
     *          群号
     *     @type string $fromUser
     *          发送方
     *     @type string $message
     *          消息
     * }
     */
    public function __construct($data = NULL) {
        \App\Protocol\Proto\Protobuf\GPBMetadata\GroupChatForSendProtocol::initOnce();
        parent::__construct($data);
    }

    /**
     *群号
     *
     * Generated from protobuf field <code>string groupChatId = 1;</code>
     * @return string
     */
    public function getGroupChatId()
    {
        return $this->groupChatId;
    }

    /**
     *群号
     *
     * Generated from protobuf field <code>string groupChatId = 1;</code>
     * @param string $var
     * @return $this
     */
    public function setGroupChatId($var)
    {
        GPBUtil::checkString($var, True);
        $this->groupChatId = $var;

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
     *消息
     *
     * Generated from protobuf field <code>string message = 3;</code>
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     *消息
     *
     * Generated from protobuf field <code>string message = 3;</code>
     * @param string $var
     * @return $this
     */
    public function setMessage($var)
    {
        GPBUtil::checkString($var, True);
        $this->message = $var;

        return $this;
    }

}
