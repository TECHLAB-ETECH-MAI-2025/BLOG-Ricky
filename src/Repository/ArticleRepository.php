<?php

namespace App\Repository;

use App\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    /**
     * Récupère les articles avec pagination, recherche et tri pour DataTables.
     */
    public function findForDataTable(
        int $start,
        int $length,
        ?string $search,
        string $orderColumn,
        string $orderDir
    ): array {
        // Colonnes autorisées pour le tri
        $validColumns = ['id', 'title', 'createdAt', 'commentsCount', 'likesCount', 'categories'];
        if (!in_array($orderColumn, $validColumns, true)) {
            $orderColumn = 'id';
        }

        // Requête principale avec jointures
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.categories', 'c')
            ->leftJoin('a.comments', 'com')
            ->leftJoin('a.likes', 'l')
            ->addSelect('c')
            ->addSelect('COUNT(DISTINCT com.id) AS HIDDEN commentsCount')
            ->addSelect('COUNT(DISTINCT l.id) AS HIDDEN likesCount')
            ->groupBy('a.id');

        // Appliquer la recherche si nécessaire
        if ($search) {
            $qb->andWhere('a.title LIKE :search OR c.title LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        // Gestion du tri
        switch ($orderColumn) {
            case 'commentsCount':
                $qb->orderBy('commentsCount', $orderDir);
                break;
            case 'likesCount':
                $qb->orderBy('likesCount', $orderDir);
                break;
            case 'categories':
                $qb->orderBy('c.title', $orderDir);
                break;
            case 'id':
            case 'title':
            case 'createdAt':
                $qb->orderBy('a.' . $orderColumn, $orderDir);
                break;
        }

        // Pagination
        $qb->setFirstResult($start)
            ->setMaxResults($length);

        $data = $qb->getQuery()->getResult();

        // Total général
        $totalCount = (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Total filtré
        $filteredQb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id)')
            ->leftJoin('a.categories', 'c');

        if ($search) {
            $filteredQb->andWhere('a.title LIKE :search OR c.title LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $filteredCount = (int) $filteredQb->getQuery()->getSingleScalarResult();

        return [
            'data' => $data,
            'totalCount' => $totalCount,
            'filteredCount' => $filteredCount,
        ];
    }

    /**
     * Recherche d'articles par titre.
     */
    public function searchByTitle(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.categories', 'c')
            ->addSelect('c')
            ->where('a.title LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
