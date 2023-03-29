<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: topicUniqIdCountResp.proto

namespace Netsvr;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 *worker响应business，返回网关中的主题包含的连接数
 *
 * Generated from protobuf message <code>netsvr.topicUniqIdCountResp.TopicUniqIdCountResp</code>
 */
class TopicUniqIdCountResp extends \Google\Protobuf\Internal\Message
{
    /**
     *worker原样回传给business
     *
     * Generated from protobuf field <code>bytes ctxData = 1;</code>
     */
    protected $ctxData = '';
    /**
     *key是topic，value是该主题的连接数
     *如果topic没找到，则items中不会有该topic
     *
     * Generated from protobuf field <code>map<string, int32> items = 2;</code>
     */
    private $items;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $ctxData
     *          worker原样回传给business
     *     @type array|\Google\Protobuf\Internal\MapField $items
     *          key是topic，value是该主题的连接数
     *          如果topic没找到，则items中不会有该topic
     * }
     */
    public function __construct($data = NULL) {
        \Netsvr\GPBMetadata\TopicUniqIdCountResp::initOnce();
        parent::__construct($data);
    }

    /**
     *worker原样回传给business
     *
     * Generated from protobuf field <code>bytes ctxData = 1;</code>
     * @return string
     */
    public function getCtxData()
    {
        return $this->ctxData;
    }

    /**
     *worker原样回传给business
     *
     * Generated from protobuf field <code>bytes ctxData = 1;</code>
     * @param string $var
     * @return $this
     */
    public function setCtxData($var)
    {
        GPBUtil::checkString($var, False);
        $this->ctxData = $var;

        return $this;
    }

    /**
     *key是topic，value是该主题的连接数
     *如果topic没找到，则items中不会有该topic
     *
     * Generated from protobuf field <code>map<string, int32> items = 2;</code>
     * @return \Google\Protobuf\Internal\MapField
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     *key是topic，value是该主题的连接数
     *如果topic没找到，则items中不会有该topic
     *
     * Generated from protobuf field <code>map<string, int32> items = 2;</code>
     * @param array|\Google\Protobuf\Internal\MapField $var
     * @return $this
     */
    public function setItems($var)
    {
        $arr = GPBUtil::checkMapField($var, \Google\Protobuf\Internal\GPBType::STRING, \Google\Protobuf\Internal\GPBType::INT32);
        $this->items = $arr;

        return $this;
    }

}

