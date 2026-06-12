<?php

namespace App\Repository;

use App\Entity\AnalyticsHit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
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
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT url, COUNT(id) as hits
                FROM app_analytics_hit
                GROUP BY url
                ORDER BY hits DESC
                LIMIT :limit OFFSET :offset';

        return $conn->fetchAllAssociative($sql, [
            'limit' => $limit,
            'offset' => $offset,
        ], [
            'limit' => ParameterType::INTEGER,
            'offset' => ParameterType::INTEGER,
        ]);
    }

    public function countUrls(): int
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT COUNT(DISTINCT url) FROM app_analytics_hit';
        return (int) $conn->fetchOne($sql);
    }

    public function getMostVisitedOrigins(int $limit = 10, int $offset = 0): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT origin, COUNT(id) as hits
                FROM app_analytics_hit
                GROUP BY origin
                ORDER BY hits DESC
                LIMIT :limit OFFSET :offset';

        return $conn->fetchAllAssociative($sql, [
            'limit' => $limit,
            'offset' => $offset,
        ], [
            'limit' => ParameterType::INTEGER,
            'offset' => ParameterType::INTEGER,
        ]);
    }

    public function countOrigins(): int
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT COUNT(DISTINCT origin) FROM app_analytics_hit';
        return (int) $conn->fetchOne($sql);
    }

    public function getMostVisitedCountries(int $limit = 10, int $offset = 0): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT country, COUNT(id) as hits
                FROM app_analytics_hit
                GROUP BY country
                ORDER BY hits DESC
                LIMIT :limit OFFSET :offset';

        return $conn->fetchAllAssociative($sql, [
            'limit' => $limit,
            'offset' => $offset,
        ], [
            'limit' => ParameterType::INTEGER,
            'offset' => ParameterType::INTEGER,
        ]);
    }

    public function countCountries(): int
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT COUNT(DISTINCT country) FROM app_analytics_hit';
        return (int) $conn->fetchOne($sql);
    }

    public function deleteImages(): int
    {
        return $this->createQueryBuilder('h')
            ->delete()
            ->where('h.url LIKE :webp OR h.url LIKE :media OR h.url LIKE :jpg OR h.url LIKE :jpeg OR h.url LIKE :png OR h.url LIKE :gif')
            ->setParameter('webp', '%.webp%')
            ->setParameter('media', '%/media/%')
            ->setParameter('jpg', '%.jpg%')
            ->setParameter('jpeg', '%.jpeg%')
            ->setParameter('png', '%.png%')
            ->setParameter('gif', '%.gif%')
            ->getQuery()
            ->execute();
    }
}
