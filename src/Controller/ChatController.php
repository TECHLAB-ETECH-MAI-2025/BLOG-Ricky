<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use App\Form\MessageForm;
use App\Repository\UserRepository;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('/chat', name: 'chat_')]
final class ChatController extends AbstractController
{
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
        EntityManagerInterface $entityManager,
        Request $request
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser instanceof UserInterface) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        $receiver = $entityManager->getRepository(User::class)->find($receiverId);
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
            $entityManager->persist($message);
            $entityManager->flush();

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
        EntityManagerInterface $entityManager,
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

        $receiver = $entityManager->getRepository(User::class)->find($receiverId);
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
    public function sendMessage(Request $request, EntityManagerInterface $entityManager): JsonResponse
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

        $receiver = $entityManager->getRepository(User::class)->find($receiverId);
        if (!$receiver) {
            return new JsonResponse(['error' => 'Destinataire introuvable'], 404);
        }

        $message = new Message();
        $message->setSender($currentUser);
        $message->setReceiver($receiver);
        $message->setContent($content);
        $message->setCreatedAt(new \DateTimeImmutable());

        $entityManager->persist($message);
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }
}
