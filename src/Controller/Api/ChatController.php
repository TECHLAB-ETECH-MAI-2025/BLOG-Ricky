<?php

namespace App\Controller\Api;

use App\Entity\Message;
use App\Entity\User;
use App\Message\MercureChatMessage;
use App\Service\JWTService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Service\NotificationService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Route('/api/chat', name: 'api_chat_')]
final class ChatController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JWTService $jwtService,
        private readonly ParameterBagInterface $params
    ) {
    }

    // Envoi d'un message via l'API
    #[Route('/send', name: 'send', methods: ['POST'])]
    public function sendMessage(Request $request, MessageBusInterface $bus): JsonResponse
    {
        try {
            /** @var User $currentUser */
            $currentUser = $this->getUser();
            if (!$currentUser instanceof UserInterface) {
                return $this->json(['success' => false, 'error' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
            }

            $content = trim((string) $request->request->get('content', ''));
            $receiverId = (int) $request->request->get('receiver', 0);

            if ($receiverId <= 0) {
                return $this->json(['success' => false, 'error' => 'ID de destinataire invalide.'], Response::HTTP_BAD_REQUEST);
            }

            if ($receiverId === $currentUser->getId()) {
                return $this->json(['success' => false, 'error' => 'Vous ne pouvez pas vous envoyer un message.'], Response::HTTP_BAD_REQUEST);
            }

            if (empty($content)) {
                return $this->json(['success' => false, 'error' => 'Le message est vide.'], Response::HTTP_BAD_REQUEST);
            }

            $receiver = $this->entityManager->getRepository(User::class)->find($receiverId);
            if (!$receiver) {
                return $this->json(['success' => false, 'error' => 'Destinataire introuvable.'], Response::HTTP_NOT_FOUND);
            }

            // Création et persistance du message
            $message = new Message();
            $message->setSender($currentUser);
            $message->setReceiver($receiver);
            $message->setContent($content);
            $message->setCreatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($message);
            $this->entityManager->flush();

            // Dispatch du message Mercure de façon asynchrone via Symfony Messenger
            $bus->dispatch(new MercureChatMessage(
                $currentUser->getId(),
                $receiverId,
                [
                    'id' => $message->getId(),
                    'content' => $message->getContent(),
                    'userId' => $currentUser->getId(),
                    'username' => $currentUser->getFullName(),
                    'createdAt' => $message->getCreatedAt()->format(DATE_ATOM),
                ]
            ));

            return $this->json([
                'success' => true,
                'message' => 'Message envoyé.',
                'data' => [
                    'id' => $message->getId(),
                    'createdAt' => $message->getCreatedAt()->format(DATE_ATOM),
                ]
            ], Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => 'Une erreur inattendue est survenue.',
                'details' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Génération d'un token Mercure
    #[Route('/mercure-token', name: 'token', methods: ['GET'])]
    public function getMercureToken(Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser instanceof UserInterface) {
            return $this->json(['success' => false, 'error' => 'Non autorisé'], Response::HTTP_UNAUTHORIZED);
        }

        $partnerId = (int) $request->query->get('partner', 0);

        if ($partnerId <= 0) {
            return $this->json(['success' => false, 'error' => 'ID du partenaire requis.'], Response::HTTP_BAD_REQUEST);
        }

        $token = $this->jwtService->generateChatToken($currentUser->getId(), $partnerId);

        return $this->json(['success' => true, 'token' => $token]);
    }

    // Génère un token Mercure pour l'utilisateur afin de s'abonner à ses notifications
    #[Route('/notifications-stream', name: 'notifications_stream', methods: ['GET'])]
    public function notificationsStream(Request $request, JWTService $jwtService): JsonResponse
    {
        try {
            $userId = $request->query->get('userId');
            if (empty($userId) || !is_numeric($userId)) {
                return $this->json(['success' => false, 'error' => 'ID utilisateur invalide ou manquant.'], Response::HTTP_BAD_REQUEST);
            }

            /** @var User $currentUser */
            $currentUser = $this->getUser();
            if (!$currentUser instanceof UserInterface) {
                return $this->json(['success' => false, 'error' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
            }

            $token = $jwtService->generateMercureToken(
                ["user/{$userId}/notifications"],
                [],
                $userId
            );

            $mercureUrl = rtrim($this->params->get('mercure_public_url'), '/');

            return $this->json([
                'success' => true,
                'token' => $token,
                'url' => $mercureUrl . '?topic=' . rawurlencode("user/{$userId}/notifications")
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => 'Une erreur inattendue est survenue.',
                'details' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Récupère le nombre de messages non lus par expéditeur pour l'utilisateur connecté
    #[Route('/unread-counts', name: 'unread_counts', methods: ['GET'])]
    public function getUnreadCounts(NotificationService $notificationService): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            if (!$user instanceof UserInterface) {
                return $this->json(['success' => false, 'error' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
            }

            $counts = $notificationService->getUnreadCounts($user->getId());

            return $this->json([
                'success' => true,
                'counts' => $counts
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => 'Une erreur inattendue est survenue.',
                'details' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Marque la conversation comme lue et met à jour les notifications
    #[Route('/mark-as-read', name: 'mark_as_read', methods: ['POST'])]
    public function markAsRead(Request $request, NotificationService $notificationService): JsonResponse
    {
        try {
            /** @var User $currentUser */
            $currentUser = $this->getUser();
            if (!$currentUser instanceof UserInterface) {
                return $this->json(['success' => false, 'error' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
            }

            $senderId = (int) $request->request->get('senderId', 0);
            if ($senderId <= 0) {
                return $this->json(['success' => false, 'error' => 'ID de l’expéditeur invalide.'], Response::HTTP_BAD_REQUEST);
            }

            $notificationService->markAsRead($senderId, $currentUser->getId());

            return $this->json(['success' => true]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors du marquage comme lu.',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
