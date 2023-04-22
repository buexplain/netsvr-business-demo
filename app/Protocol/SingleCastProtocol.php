<?php

namespace App\Protocol;

use NetsvrBusiness\Contract\ClientDataInterface;
use NetsvrBusiness\Exception\ClientDataDecodeException;

/**
 * 单播消息给某个用户，客户端发送时的格式示例：{"cmd":2, "data":"{\"message\": \"你好\",\"toUser\":\"016443D1DF1BC6C722\"}"}
 */
class SingleCastProtocol implements ClientDataInterface
{
    public const CMD = 2;

    /**
     * 发送的消息
     * @var string
     */
    protected string $message = '';
    /**
     * 目标用户
     * @var string
     */
    protected string $toUser = '';

    /**
     * 发送消息的用户
     * @var string
     */
    protected string $fromUser = '';

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message)
    {
        $this->message = $message;
    }

    public function getToUser(): string
    {
        return $this->toUser;
    }

    public function setToUser(string $uniqId)
    {
        $this->toUser = $uniqId;
    }

    public function getFromUser(): string
    {
        return $this->fromUser;
    }

    public function setFromUser(string $uniqId)
    {
        $this->fromUser = $uniqId;
    }

    public function serializeToString(): string
    {
        return json_encode(['message' => $this->message, 'toUser' => $this->toUser, 'fromUser' => $this->fromUser], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function mergeFromString(string $data): void
    {
        $tmp = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ClientDataDecodeException(json_last_error_msg(), 1);
        }
        if (!is_array($tmp) || !isset($tmp['message']) || !isset($tmp['toUser'])) {
            throw new ClientDataDecodeException('expected package format is: {"message":"string","toUser":"string"}', 2);
        }
        $this->message = $tmp['message'];
        $this->toUser = $tmp['toUser'];
    }
}