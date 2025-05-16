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
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.categories', 'c')
            ->leftJoin('a.comments', 'com')
            ->leftJoin('a.likes', 'l')
            ->addSelect('c')
            ->addSelect('com')
            ->addSelect('l')
            ->groupBy('a.id');

        // Appliquer la recherche si nécessaire
        if ($search) {
            $qb->andWhere('a.title LIKE :search OR c.title LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        // Total sans filtre
        $totalCount = (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Total avec filtre
        $filteredCountQb = clone $qb;
        $filteredCount = (int) $filteredCountQb
            ->select('COUNT(DISTINCT a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Gestion du tri
        if ($orderColumn === 'commentsCount') {
            $qb->addSelect('COUNT(com.id) AS HIDDEN commentsCount')
                ->orderBy('commentsCount', $orderDir);
        } elseif ($orderColumn === 'likesCount') {
            $qb->addSelect('COUNT(l.id) AS HIDDEN likesCount')
                ->orderBy('likesCount', $orderDir);
        } elseif ($orderColumn === 'categories') {
            $qb->orderBy('c.title', $orderDir);
        } else {
            $qb->orderBy($orderColumn, $orderDir);
        }

        // Pagination
        $qb->setFirstResult($start)
            ->setMaxResults($length);

        return [
            'data' => $qb->getQuery()->getResult(),
            'totalCount' => $totalCount,
            'filteredCount' => $filteredCount
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
