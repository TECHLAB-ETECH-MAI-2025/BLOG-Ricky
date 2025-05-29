<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use App\Form\MessageForm;
use App\Repository\UserRepository;
use App\Repository\MessageRepository;
use App\Service\MercureService;
use App\Service\JWTService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class ChatController extends AbstractController
{
    private MercureService $mercureService;
    private EntityManagerInterface $entityManager;
    private JWTService $jwtService;

    public function __construct(
        MercureService $mercureService,
        EntityManagerInterface $entityManager,
        JWTService $jwtService
    ) {
        $this->mercureService = $mercureService;
        $this->entityManager = $entityManager;
        $this->jwtService = $jwtService;
    }

    #[Route('/chat/', name: 'chat_list')]
    public function list(UserRepository $userRepository): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser instanceof UserInterface) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        $users = $userRepository->createQueryBuilder('u')
            ->where('u != :currentUser')
            ->setParameter('currentUser', $currentUser)
            ->getQuery()
            ->getResult();

        return $this->render('chat/list.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/chat/{receiverId}', name: 'chat_index', requirements: ['receiverId' => '\d+'])]
    public function index(
        int $receiverId,
        MessageRepository $messageRepository,
        Request $request
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser instanceof UserInterface) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        $receiver = $this->entityManager->getRepository(User::class)->find($receiverId);
        if (!$receiver) {
            throw $this->createNotFoundException('Utilisateur non trouvé.');
        }

        $messages = $messageRepository->findConversation($currentUser->getId(), $receiverId);

        $message = new Message();
        $form = $this->createForm(MessageForm::class, $message);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $message->setSender($currentUser);
            $message->setReceiver($receiver);
            $message->setCreatedAt(new \DateTimeImmutable());
            $this->entityManager->persist($message);
            $this->entityManager->flush();

            return $this->redirectToRoute('chat_index', ['receiverId' => $receiverId]);
        }

        return $this->render('chat/index.html.twig', [
            'messages' => $messages,
            'receiver' => $receiver,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/chat/messages', name: 'chat_messages', methods: ['GET'])]
    public function messages(
        Request $request,
        MessageRepository $messageRepository
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser instanceof UserInterface) {
            return $this->json([
                'success' => false,
                'error' => 'Non autorisé',
            ], 403);
        }

        $receiverId = (int) $request->query->get('receiver');
        if ($receiverId <= 0) {
            return $this->json([
                'success' => false,
                'error' => 'Paramètre invalide',
            ], 400);
        }

        $receiver = $this->entityManager->getRepository(User::class)->find($receiverId);
        if (!$receiver) {
            return $this->json([
                'success' => false,
                'error' => 'Utilisateur introuvable',
            ], 404);
        }

        $messages = $messageRepository->findConversation($currentUser->getId(), $receiverId);

        return $this->render('chat/_messages.html.twig', [
            'messages' => $messages,
            'receiver' => $receiver,
        ]);
    }

    #[Route('/api/chat/send', name: 'chat_send', methods: ['POST'])]
    public function sendMessage(Request $request): JsonResponse
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

            $message = new Message();
            $message->setSender($currentUser);
            $message->setReceiver($receiver);
            $message->setContent($content);
            $message->setCreatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($message);
            $this->entityManager->flush();

            // Envoi via MercureService (publication privée sécurisée)
            $this->mercureService->publishChatMessage(
                $currentUser->getId(),
                $receiverId,
                [
                    'id' => $message->getId(),
                    'content' => $message->getContent(),
                    'userId' => $currentUser->getId(),
                    'username' => $currentUser->getFullName(),
                    'createdAt' => $message->getCreatedAt()->format(DATE_ATOM),
                ]
            );

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

    #[Route('/api/mercure-token', name: 'chat_token', methods: ['GET'])]
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
