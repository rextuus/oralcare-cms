<?php

namespace App\Repository;

use App\Entity\AnalyticsHit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AnalyticsHit>
 */
class AnalyticsHitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalyticsHit::class);
    }

    public function countByDay(\DateTimeInterface $from): array
    {
        $qb = $this->createQueryBuilder('h')
            ->select('SUBSTRING(h.createdAt, 1, 10) as date', 'COUNT(h.id) as count')
            ->where('h.createdAt >= :from')
            ->setParameter('from', $from)
            ->groupBy('date')
            ->orderBy('date', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function getMostVisitedUrls(int $limit = 10, int $offset = 0): array
    {
        return $this->createQueryBuilder('h')
            ->select('h.url', 'COUNT(h.id) as hits')
            ->groupBy('h.url')
            ->orderBy('hits', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
