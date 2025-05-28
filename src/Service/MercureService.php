<?php

namespace App\Service;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class MercureService
{
    private HubInterface $hub;

    public function __construct(HubInterface $hub)
    {
        $this->hub = $hub;
    }

    /**
     * Publier un nouveau message de chat à un topic spécifique
     */
    public function publishChatMessage(int $senderId, int $receiverId, array $messageData): void
    {
        // Le topic est commun entre l'expéditeur et le destinataire
        $topic = sprintf('chat/%d/%d', min($senderId, $receiverId), max($senderId, $receiverId));

        $update = new Update(
            $topic,
            json_encode([
                'message' => $messageData,
                'timestamp' => time(),
            ])
        );

        $this->hub->publish($update);
    }
}
