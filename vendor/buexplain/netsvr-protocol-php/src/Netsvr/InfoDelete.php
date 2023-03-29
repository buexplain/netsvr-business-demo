<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: infoDelete.proto

namespace Netsvr;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 *删除连接的info信息
 *
 * Generated from protobuf message <code>netsvr.infoDelete.InfoDelete</code>
 */
class InfoDelete extends \Google\Protobuf\Internal\Message
{
    /**
     *目标uniqId
     *
     * Generated from protobuf field <code>string uniqId = 1;</code>
     */
    protected $uniqId = '';
    /**
     *是否删除uniqId，true：重新随机生成一个uniqId，false：不处理
     *
     * Generated from protobuf field <code>bool delUniqId = 2;</code>
     */
    protected $delUniqId = false;
    /**
     *是否删除session，true：设置session为空字符串，false：不处理
     *
     * Generated from protobuf field <code>bool delSession = 3;</code>
     */
    protected $delSession = false;
    /**
     *是否删除topic，true：设置topic为空[]string，false：不处理
     *
     * Generated from protobuf field <code>bool delTopic = 4;</code>
     */
    protected $delTopic = false;
    /**
     *需要发给客户的数据，传递了则转发给客户
     *
     * Generated from protobuf field <code>bytes data = 5;</code>
     */
    protected $data = '';

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $uniqId
     *          目标uniqId
     *     @type bool $delUniqId
     *          是否删除uniqId，true：重新随机生成一个uniqId，false：不处理
     *     @type bool $delSession
     *          是否删除session，true：设置session为空字符串，false：不处理
     *     @type bool $delTopic
     *          是否删除topic，true：设置topic为空[]string，false：不处理
     *     @type string $data
     *          需要发给客户的数据，传递了则转发给客户
     * }
     */
    public function __construct($data = NULL) {
        \Netsvr\GPBMetadata\InfoDelete::initOnce();
        parent::__construct($data);
    }

    /**
     *目标uniqId
     *
     * Generated from protobuf field <code>string uniqId = 1;</code>
     * @return string
     */
    public function getUniqId()
    {
        return $this->uniqId;
    }

    /**
     *目标uniqId
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
     *是否删除uniqId，true：重新随机生成一个uniqId，false：不处理
     *
     * Generated from protobuf field <code>bool delUniqId = 2;</code>
     * @return bool
     */
    public function getDelUniqId()
    {
        return $this->delUniqId;
    }

    /**
     *是否删除uniqId，true：重新随机生成一个uniqId，false：不处理
     *
     * Generated from protobuf field <code>bool delUniqId = 2;</code>
     * @param bool $var
     * @return $this
     */
    public function setDelUniqId($var)
    {
        GPBUtil::checkBool($var);
        $this->delUniqId = $var;

        return $this;
    }

    /**
     *是否删除session，true：设置session为空字符串，false：不处理
     *
     * Generated from protobuf field <code>bool delSession = 3;</code>
     * @return bool
     */
    public function getDelSession()
    {
        return $this->delSession;
    }

    /**
     *是否删除session，true：设置session为空字符串，false：不处理
     *
     * Generated from protobuf field <code>bool delSession = 3;</code>
     * @param bool $var
     * @return $this
     */
    public function setDelSession($var)
    {
        GPBUtil::checkBool($var);
        $this->delSession = $var;

        return $this;
    }

    /**
     *是否删除topic，true：设置topic为空[]string，false：不处理
     *
     * Generated from protobuf field <code>bool delTopic = 4;</code>
     * @return bool
     */
    public function getDelTopic()
    {
        return $this->delTopic;
    }

    /**
     *是否删除topic，true：设置topic为空[]string，false：不处理
     *
     * Generated from protobuf field <code>bool delTopic = 4;</code>
     * @param bool $var
     * @return $this
     */
    public function setDelTopic($var)
    {
        GPBUtil::checkBool($var);
        $this->delTopic = $var;

        return $this;
    }

    /**
     *需要发给客户的数据，传递了则转发给客户
     *
     * Generated from protobuf field <code>bytes data = 5;</code>
     * @return string
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     *需要发给客户的数据，传递了则转发给客户
     *
     * Generated from protobuf field <code>bytes data = 5;</code>
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

