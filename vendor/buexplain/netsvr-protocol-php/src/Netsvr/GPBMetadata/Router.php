<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: router.proto

namespace Netsvr\GPBMetadata;

class Router
{
    public static $is_initialized = false;

    public static function initOnce() {
        $pool = \Google\Protobuf\Internal\DescriptorPool::getGeneratedPool();

        if (static::$is_initialized == true) {
          return;
        }
        $pool->internalAddGeneratedFile(
            '
�
router.protonetsvr.router"7
Router
cmd (2.netsvr.router.Cmd
data (*�
Cmd
Placeholder 
ConnOpen��*
	ConnClose��*
Transfer��*
RegisterѤ�*

InfoUpdateҤ�*

InfoDeleteӤ�*
	BroadcastԤ�*
	Multicastդ�*

SingleCast֤�*
TopicSubscribeפ�*
TopicUnsubscribeؤ�*
TopicDelete���
TopicPublishڤ�*
ForceOfflineۤ�*
ForceOfflineGuestܤ�*

Unregister���*
CheckOnline���*

UniqIdList���*
UniqIdCount���*

TopicCount���*
	TopicList���*
TopicUniqIdList���*
TopicUniqIdCount���*
Info���*
Metrics����
Limitì�*B\'Znetsvr/�Netsvr�Netsvr\\GPBMetadatabproto3'
        , true);

        static::$is_initialized = true;
    }
}

