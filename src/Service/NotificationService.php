<?php

namespace App\Service;

use App\Entity\Message;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MercureService $mercureService
    ) {
    }

    /**
     * Récupère le nombre de messages non lus groupés par expéditeur pour un utilisateur donné.
     */
    public function getUnreadCounts(int $userId): array
    {
        return $this->entityManager->getRepository(Message::class)
            ->getUnreadCountsPerSender($userId);
    }

    /**
     * Marque comme lus tous les messages dans une conversation entre un expéditeur et un destinataire.
     */
    public function markAsRead(int $senderId, int $receiverId): void
    {
        $this->entityManager->getRepository(Message::class)
            ->markConversationAsRead($senderId, $receiverId);

        $counts = $this->getUnreadCounts($receiverId);

        $this->mercureService->publishNotificationUpdate($receiverId, $counts, $senderId);
    }
}
