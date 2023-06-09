<?php

namespace App\Protocol\Json;

use NetsvrBusiness\Contract\RouterDataInterface;
use NetsvrBusiness\Exception\DataDecodeException;

/**
 * 广播消息，客户端发送时的格式示例：001{"cmd":1, "data":"{\"message\": \"大家好\"}"}
 */
class BroadcastProtocol implements RouterDataInterface
{
    protected string $message = '';

    /**
     * 发送消息的用户
     * @var string
     */
    protected string $fromUser = '';

    public function getFromUser(): string
    {
        return $this->fromUser;
    }

    public function setFromUser(string $uniqId)
    {
        $this->fromUser = $uniqId;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message)
    {
        $this->message = $message;
    }

    public function encode(): string
    {
        return json_encode(['message' => $this->message, 'fromUser' => $this->fromUser], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function decode(string $data): self
    {
        $tmp = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new DataDecodeException(json_last_error_msg(), 1);
        }
        if (!is_array($tmp) || !isset($tmp['message'])) {
            throw new DataDecodeException('expected package format is: {"message":"string"}', 2);
        }
        $this->message = $tmp['message'];
        return $this;
    }
}