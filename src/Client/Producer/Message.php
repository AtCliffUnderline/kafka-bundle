<?php

declare(strict_types=1);

namespace Sts\KafkaBundle\Client\Producer;

class Message
{
    private string $payload;
    private ?string $key;

    public function __construct(string $payload, ?string $key)
    {
        $this->payload = $payload;
        $this->key = $key;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }
}
