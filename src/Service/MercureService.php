<?php

namespace App\Service;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Psr\Log\LoggerInterface;

class MercureService
{
    private HubInterface $hub;
    private LoggerInterface $logger;

    public function __construct(HubInterface $hub, LoggerInterface $logger)
    {
        $this->hub = $hub;
        $this->logger = $logger;
    }

    /**
     * Publier un nouveau message de chat à un topic spécifique sécurisé.
     */
    public function publishChatMessage(int $senderId, int $receiverId, array $messageData): void
    {
        // Le topic est commun entre l'expéditeur et le destinataire
        $topic = sprintf('chat/%d/%d', min($senderId, $receiverId), max($senderId, $receiverId));

        try {
            $update = new Update(
                $topic,
                json_encode([
                    'message' => $messageData,
                    'timestamp' => time(),
                ]),
                true // message privé
            );

            $this->hub->publish($update);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur lors de la publication Mercure', [
                'topic' => $topic,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
