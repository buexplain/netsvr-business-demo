<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: uniqIdListResp.proto

namespace Netsvr;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 *worker响应business，返回网关中全部的uniqId
 *
 * Generated from protobuf message <code>netsvr.uniqIdListResp.UniqIdListResp</code>
 */
class UniqIdListResp extends \Google\Protobuf\Internal\Message
{
    /**
     *worker原样回传给business
     *
     * Generated from protobuf field <code>bytes ctxData = 1;</code>
     */
    protected $ctxData = '';
    /**
     *网关包含的uniqId
     *
     * Generated from protobuf field <code>repeated string uniqIds = 2;</code>
     */
    private $uniqIds;

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $ctxData
     *          worker原样回传给business
     *     @type array<string>|\Google\Protobuf\Internal\RepeatedField $uniqIds
     *          网关包含的uniqId
     * }
     */
    public function __construct($data = NULL) {
        \Netsvr\GPBMetadata\UniqIdListResp::initOnce();
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
     *网关包含的uniqId
     *
     * Generated from protobuf field <code>repeated string uniqIds = 2;</code>
     * @return \Google\Protobuf\Internal\RepeatedField
     */
    public function getUniqIds()
    {
        return $this->uniqIds;
    }

    /**
     *网关包含的uniqId
     *
     * Generated from protobuf field <code>repeated string uniqIds = 2;</code>
     * @param array<string>|\Google\Protobuf\Internal\RepeatedField $var
     * @return $this
     */
    public function setUniqIds($var)
    {
        $arr = GPBUtil::checkRepeatedField($var, \Google\Protobuf\Internal\GPBType::STRING);
        $this->uniqIds = $arr;

        return $this;
    }

}

