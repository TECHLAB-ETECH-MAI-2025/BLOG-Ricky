<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use App\Form\MessageForm;
use App\Repository\UserRepository;
use App\Repository\MessageRepository;
use App\Service\MercureService;
use Doctrine\ORM\EntityManagerInterface;
use Firebase\JWT\JWT;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('/chat', name: 'chat_')]
final class ChatController extends AbstractController
{
    private MercureService $mercureService;
    private EntityManagerInterface $entityManager;

    public function __construct(MercureService $mercureService, EntityManagerInterface $entityManager)
    {
        $this->mercureService = $mercureService;
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'list')]
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

    #[Route('/{receiverId}', name: 'index', requirements: ['receiverId' => '\d+'])]
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

    #[Route('/messages', name: 'messages', methods: ['GET'])]
    public function messages(
        Request $request,
        MessageRepository $messageRepository
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser instanceof UserInterface) {
            return new Response('Non autorisé', 403);
        }

        $receiverId = (int) $request->query->get('receiver');
        if ($receiverId === 0) {
            return new Response('Paramètre invalide', 400);
        }

        $receiver = $this->entityManager->getRepository(User::class)->find($receiverId);
        if (!$receiver) {
            return new Response('Utilisateur introuvable', 404);
        }

        $messages = $messageRepository->findConversation($currentUser->getId(), $receiverId);

        return $this->render('chat/_messages.html.twig', [
            'messages' => $messages,
            'receiver' => $receiver,
        ]);
    }

    #[Route('/send', name: 'send', methods: ['POST'])]
    public function sendMessage(Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser instanceof UserInterface) {
            return new JsonResponse(['error' => 'Non autorisé'], 403);
        }

        $content = trim((string) $request->request->get('content'));
        $receiverId = (int) $request->request->get('receiver');

        if ($content === '' || $receiverId === 0) {
            return new JsonResponse(['error' => 'Contenu ou destinataire manquant'], 400);
        }

        $receiver = $this->entityManager->getRepository(User::class)->find($receiverId);
        if (!$receiver) {
            return new JsonResponse(['error' => 'Destinataire introuvable'], 404);
        }

        $message = new Message();
        $message->setSender($currentUser);
        $message->setReceiver($receiver);
        $message->setContent($content);
        $message->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        // Publier le message via Mercure
        $this->mercureService->publishChatMessage($currentUser->getId(), $receiverId, [
            'id' => $message->getId(),
            'content' => $message->getContent(),
            'userId' => $currentUser->getId(),
            'username' => $currentUser->getFullName(),
            'createdAt' => $message->getCreatedAt()->format('c'),
        ]);

        return new JsonResponse(['success' => true]);
    }

    #[Route('/mercure-token', name: 'mercure_token', methods: ['GET'])]
    public function getMercureToken(): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser instanceof UserInterface) {
            return new JsonResponse(['error' => 'Non autorisé'], 403);
        }

        $token = $this->generateMercureToken([
            "user/{$currentUser->getId()}",
            "chat/{$currentUser->getId()}/*",
            "chat/*",
        ]);

        return new JsonResponse(['token' => $token]);
    }

    private function generateMercureToken(array $subscribe = [], array $publish = []): string
    {
        $payload = [
            'mercure' => [
                'subscribe' => $subscribe,
                'publish' => $publish,
            ],
            // exp: time() + 3600, // optionnel, expiration du token dans 1h
        ];

        return JWT::encode(
            $payload,
            $this->getParameter('mercure.jwt_secret'),
            'HS256'
        );
    }
}
