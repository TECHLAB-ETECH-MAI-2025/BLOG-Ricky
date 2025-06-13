<?php

namespace App\Controller\Api;

use App\Entity\ArticleLike;
use App\Entity\Comment;
use App\Form\CommentForm;
use App\Repository\ArticleLikeRepository;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/article', name: 'api_article_')]
final class ArticleController extends AbstractController
{
    // Retourne une liste d’articles pour l’affichage dans un DataTable
    #[Route('/list', name: 'list', methods: ['GET'])]
    public function list(Request $request, ArticleRepository $articleRepository): JsonResponse
    {
        $draw = (int) $request->query->get('draw', 0);
        $start = (int) $request->query->get('start', 0);
        $length = (int) $request->query->get('length', 10);
        $search = $request->query->all('search')['value'] ?? null;
        $orders = $request->query->all('order') ?? [];

        $columns = [
            0 => 'a.id',
            1 => 'a.title',
            2 => 'categories',
            3 => 'commentsCount',
            4 => 'likesCount',
            5 => 'a.createdAt',
        ];

        $orderColumn = $columns[$orders[0]['column'] ?? 0] ?? 'a.id';
        $orderDir = $orders[0]['dir'] ?? 'desc';

        $results = $articleRepository->findForDataTable($start, $length, $search, $orderColumn, $orderDir);

        $data = [];
        foreach ($results['data'] as $article) {
            $categoryNames = array_map(fn($category) => $category->getTitle(), $article->getCategories()->toArray());

            $data[] = [
                'id' => $article->getId(),
                'title' => sprintf(
                    '<a href="%s">%s</a>',
                    $this->generateUrl('app_article_show', ['id' => $article->getId()]),
                    $article->getTitle()
                ),
                'categories' => implode(', ', $categoryNames),
                'commentsCount' => $article->getComments()->count(),
                'likesCount' => $article->getLikes()->count(),
                'createdAt' => $article->getCreatedAt()->format('d/m/Y H:i'),
                'actions' => $this->renderView('article/_actions.html.twig', ['article' => $article])
            ];
        }

        return new JsonResponse([
            'draw' => $draw,
            'recordsTotal' => $results['totalCount'],
            'recordsFiltered' => $results['filteredCount'],
            'data' => $data
        ], Response::HTTP_OK);
    }

    // Recherche des articles par titre
    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request, ArticleRepository $articleRepository): JsonResponse
    {
        $query = $request->query->get('q', '');

        if (strlen($query) < 2) {
            return new JsonResponse([
                'success' => false,
                'error' => 'La requête doit contenir au moins 2 caractères.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $articles = $articleRepository->searchByTitle($query, 10);

        $results = [];
        foreach ($articles as $article) {
            $results[] = [
                'id' => $article->getId(),
                'title' => $article->getTitle(),
                'categories' => array_map(fn($c) => $c->getTitle(), $article->getCategories()->toArray())
            ];
        }

        return new JsonResponse(['results' => $results], Response::HTTP_OK);
    }

    // Ajoute un commentaire à un article spécifique
    #[Route('/{id}/comment', name: 'comment', methods: ['POST'])]
    public function addComment(int $id, Request $request, EntityManagerInterface $entityManager, ArticleRepository $articleRepository): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Vous devez être connecté pour commenter.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $article = $articleRepository->find($id);
        if (!$article) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Article non trouvé.'
            ], Response::HTTP_NOT_FOUND);
        }

        $comment = new Comment();
        $comment->setArticle($article);
        $comment->setCreatedAt(new \DateTimeImmutable());

        $form = $this->createForm(CommentForm::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $comment->setUser($user);

            try {
                $entityManager->persist($comment);
                $entityManager->flush();
            } catch (\Throwable $e) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Une erreur est survenue lors de l\'enregistrement.'
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return new JsonResponse([
                'success' => true,
                'commentHtml' => $this->renderView('comment/_comment.html.twig', [
                    'comment' => $comment
                ]),
                'commentsCount' => $article->getComments()->count()
            ], Response::HTTP_OK);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }

        return new JsonResponse([
            'success' => false,
            'error' => $errors[0] ?? 'Formulaire invalide'
        ], Response::HTTP_BAD_REQUEST);
    }

    // Permet de liker ou unliker un article
    #[Route('/{id}/like', name: 'like', methods: ['POST'])]
    public function likeArticle(
        int $id,
        EntityManagerInterface $entityManager,
        ArticleRepository $articleRepository,
        ArticleLikeRepository $likeRepository
    ): JsonResponse {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Vous devez être connecté pour liker un article.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $article = $articleRepository->find($id);
        if (!$article) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Article non trouvé.'
            ], Response::HTTP_NOT_FOUND);
        }

        $existingLike = $likeRepository->findOneBy([
            'article' => $article,
            'user' => $user
        ]);

        try {
            if ($existingLike) {
                $entityManager->remove($existingLike);
                $entityManager->flush();

                return new JsonResponse([
                    'success' => true,
                    'liked' => false,
                    'likesCount' => $article->getLikes()->count()
                ], Response::HTTP_OK);
            }

            $like = new ArticleLike();
            $like->setArticle($article);
            $like->setUser($user);
            $like->setCreatedAt(new \DateTimeImmutable());

            $entityManager->persist($like);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'liked' => true,
                'likesCount' => $article->getLikes()->count()
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Une erreur est survenue lors de l\'opération.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
