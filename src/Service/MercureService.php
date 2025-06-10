<?php

namespace App\Service;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Psr\Log\LoggerInterface;

class MercureService
{
    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger
    ) {
        // Injection du hub Mercure et du logger pour gérer la publication et les erreurs
    }

    /**
     * Publier un nouveau message de chat sur un topic Mercure spécifique.
     *
     * Le topic est commun entre l'expéditeur et le destinataire, ce qui permet
     * aux deux utilisateurs de recevoir en temps réel les messages échangés.
     *
     * @param int $senderId Identifiant de l'expéditeur du message
     * @param int $receiverId Identifiant du destinataire du message
     * @param array $messageData Données du message à publier (contenu, auteur, date...)
     */
    public function publishChatMessage(int $senderId, int $receiverId, array $messageData): void
    {
        // Construction du topic unique entre deux utilisateurs (ordre min/max pour éviter les doublons)
        $topic = sprintf('chat/%d/%d', min($senderId, $receiverId), max($senderId, $receiverId));

        try {
            // Création d'une mise à jour Mercure avec le message encodé en JSON
            $update = new Update(
                $topic,
                json_encode([
                    'message' => $messageData,
                    'timestamp' => time(),
                ]),
                true // Le message est privé (requiert authentification)
            );

            // Publication de la mise à jour sur le hub Mercure
            $this->hub->publish($update);
        } catch (\Throwable $e) {
            // En cas d'erreur, logguer l'exception avec le topic concerné
            $this->logger->error('Erreur lors de la publication Mercure', [
                'topic' => $topic,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Publier une mise à jour des notifications (nombre de messages non lus) pour un utilisateur donné.
     *
     * Cette notification est envoyée sur un topic dédié à l'utilisateur pour
     * informer en temps réel de tout changement dans le nombre de messages non lus.
     *
     * @param int $userId Identifiant de l'utilisateur destinataire de la notification
     * @param array $counts Tableau contenant les décomptes des messages non lus par expéditeur
     * @param int|null $senderId Identifiant optionnel de l'expéditeur à l'origine de la mise à jour
     */
    public function publishNotificationUpdate(int $userId, array $counts, ?int $senderId = null): void
    {
        // Topic dédié aux notifications de l'utilisateur
        $topic = "user/{$userId}/notifications";

        // Création de la mise à jour avec le type, les counts et éventuellement l'expéditeur
        $update = new Update(
            $topic,
            json_encode([
                'type' => 'unread_update',
                'counts' => $counts,
                'senderId' => $senderId
            ]),
            true // Mise à jour privée
        );

        // Publication sur le hub Mercure
        $this->hub->publish($update);
    }
}
