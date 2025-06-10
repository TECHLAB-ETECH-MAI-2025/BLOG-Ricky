<?php

namespace App\Handler;

use App\Message\MercureChatMessage;
use App\Service\MercureService;
use App\Service\NotificationService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class MercureChatMessageHandler
{
    private MercureService $mercureService;
    private NotificationService $notificationService;

    public function __construct(MercureService $mercureService, NotificationService $notificationService)
    {
        $this->mercureService = $mercureService;
        $this->notificationService = $notificationService;
    }

    public function __invoke(MercureChatMessage $message): void
    {
        try {
            // Publication du message dans la conversation (topic chat/{minId}/{maxId})
            $this->mercureService->publishChatMessage(
                $message->getSenderId(),
                $message->getReceiverId(),
                $message->getData()
            );

            // Récupérer le nouveau compte des messages non lus pour le destinataire
            $counts = $this->notificationService->getUnreadCounts($message->getReceiverId());

            // Publier la notification de mise à jour des comptes non lus sur le topic user/{receiverId}/notifications
            $this->mercureService->publishNotificationUpdate($message->getReceiverId(), $counts, $message->getSenderId());
        } catch (\Throwable $e) {
            error_log('[MercureChatMessageHandler] Erreur lors de la publication : ' . $e->getMessage());
        }
    }
}
