<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: connOpen.proto

namespace Netsvr;

use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Google\Protobuf\Internal\GPBUtil;

/**
 *worker转发客户连接打开的信息到business
 *
 * Generated from protobuf message <code>netsvr.connOpen.ConnOpen</code>
 */
class ConnOpen extends \Google\Protobuf\Internal\Message
{
    /**
     *网关分配给连接的唯一id，格式是：网关服务编号(2个字符)+时间戳(8个字符)+自增值(8个字符)，共18个16进制的字符
     *
     * Generated from protobuf field <code>string uniqId = 1;</code>
     */
    protected $uniqId = '';
    /**
     *连接携带的GET参数
     *
     * Generated from protobuf field <code>string rawQuery = 2;</code>
     */
    protected $rawQuery = '';
    /**
     *连接的websocket子协议信息
     *
     * Generated from protobuf field <code>repeated string subProtocol = 3;</code>
     */
    private $subProtocol;
    /**
     *X-Forwarded-For，如果网关没有从header中拿到X-Forwarded-For的数据，则会赋值与本网关进程直连的客户端ip
     *
     * Generated from protobuf field <code>string xForwardedFor = 4;</code>
     */
    protected $xForwardedFor = '';

    /**
     * Constructor.
     *
     * @param array $data {
     *     Optional. Data for populating the Message object.
     *
     *     @type string $uniqId
     *          网关分配给连接的唯一id，格式是：网关服务编号(2个字符)+时间戳(8个字符)+自增值(8个字符)，共18个16进制的字符
     *     @type string $rawQuery
     *          连接携带的GET参数
     *     @type array<string>|\Google\Protobuf\Internal\RepeatedField $subProtocol
     *          连接的websocket子协议信息
     *     @type string $xForwardedFor
     *          X-Forwarded-For，如果网关没有从header中拿到X-Forwarded-For的数据，则会赋值与本网关进程直连的客户端ip
     * }
     */
    public function __construct($data = NULL) {
        \Netsvr\GPBMetadata\ConnOpen::initOnce();
        parent::__construct($data);
    }

    /**
     *网关分配给连接的唯一id，格式是：网关服务编号(2个字符)+时间戳(8个字符)+自增值(8个字符)，共18个16进制的字符
     *
     * Generated from protobuf field <code>string uniqId = 1;</code>
     * @return string
     */
    public function getUniqId()
    {
        return $this->uniqId;
    }

    /**
     *网关分配给连接的唯一id，格式是：网关服务编号(2个字符)+时间戳(8个字符)+自增值(8个字符)，共18个16进制的字符
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
     *连接携带的GET参数
     *
     * Generated from protobuf field <code>string rawQuery = 2;</code>
     * @return string
     */
    public function getRawQuery()
    {
        return $this->rawQuery;
    }

    /**
     *连接携带的GET参数
     *
     * Generated from protobuf field <code>string rawQuery = 2;</code>
     * @param string $var
     * @return $this
     */
    public function setRawQuery($var)
    {
        GPBUtil::checkString($var, True);
        $this->rawQuery = $var;

        return $this;
    }

    /**
     *连接的websocket子协议信息
     *
     * Generated from protobuf field <code>repeated string subProtocol = 3;</code>
     * @return \Google\Protobuf\Internal\RepeatedField
     */
    public function getSubProtocol()
    {
        return $this->subProtocol;
    }

    /**
     *连接的websocket子协议信息
     *
     * Generated from protobuf field <code>repeated string subProtocol = 3;</code>
     * @param array<string>|\Google\Protobuf\Internal\RepeatedField $var
     * @return $this
     */
    public function setSubProtocol($var)
    {
        $arr = GPBUtil::checkRepeatedField($var, \Google\Protobuf\Internal\GPBType::STRING);
        $this->subProtocol = $arr;

        return $this;
    }

    /**
     *X-Forwarded-For，如果网关没有从header中拿到X-Forwarded-For的数据，则会赋值与本网关进程直连的客户端ip
     *
     * Generated from protobuf field <code>string xForwardedFor = 4;</code>
     * @return string
     */
    public function getXForwardedFor()
    {
        return $this->xForwardedFor;
    }

    /**
     *X-Forwarded-For，如果网关没有从header中拿到X-Forwarded-For的数据，则会赋值与本网关进程直连的客户端ip
     *
     * Generated from protobuf field <code>string xForwardedFor = 4;</code>
     * @param string $var
     * @return $this
     */
    public function setXForwardedFor($var)
    {
        GPBUtil::checkString($var, True);
        $this->xForwardedFor = $var;

        return $this;
    }

}

