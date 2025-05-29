<?php

namespace App\Message;

/**
 * Message utilisé pour envoyer des données de chat via Mercure
 * de façon asynchrone (avec Symfony Messenger).
 */
final class MercureChatMessage
{
    private int $senderId;
    private int $receiverId;
    private array $data;

    public function __construct(int $senderId, int $receiverId, array $data)
    {
        $this->senderId = $senderId;
        $this->receiverId = $receiverId;
        $this->data = $data;
    }

    public function getSenderId(): int
    {
        return $this->senderId;
    }

    public function getReceiverId(): int
    {
        return $this->receiverId;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
