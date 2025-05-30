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
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('/api/chat', name: 'api_chat_')]
final class ChatController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JWTService $jwtService
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
                return $this->json(['success' => false, 'error' => 'Authentification requise.'], 403);
            }

            $content = trim((string) $request->request->get('content'));
            $receiverId = (int) $request->request->get('receiver');

            if ($receiverId <= 0) {
                return $this->json(['success' => false, 'error' => 'ID de destinataire invalide.'], 400);
            }

            if ($receiverId === $currentUser->getId()) {
                return $this->json(['success' => false, 'error' => 'Vous ne pouvez pas vous envoyer un message.'], 400);
            }

            if (empty($content)) {
                return $this->json(['success' => false, 'error' => 'Le message est vide.'], 400);
            }

            $receiver = $this->entityManager->getRepository(User::class)->find($receiverId);
            if (!$receiver) {
                return $this->json(['success' => false, 'error' => 'Destinataire introuvable.'], 404);
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
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => 'Une erreur inattendue est survenue.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    // Génération d'un token Mercure
    #[Route('/mercure-token', name: 'token', methods: ['GET'])]
    public function getMercureToken(Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser instanceof UserInterface) {
            return $this->json(['success' => false, 'error' => 'Non autorisé'], 403);
        }

        $partnerId = (int) $request->query->get('partner');

        if ($partnerId <= 0) {
            return $this->json(['success' => false, 'error' => 'ID du partenaire requis.'], 400);
        }

        $token = $this->jwtService->generateChatToken($currentUser->getId(), $partnerId);

        return $this->json(['success' => true, 'token' => $token]);
    }
}
