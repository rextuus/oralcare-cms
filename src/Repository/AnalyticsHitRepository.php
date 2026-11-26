<?php

declare(strict_types=1);

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

    /**
     * Aufrufe je Tag (Y-m-d) ab $from, aufsteigend.
     *
     * @return list<array{date: string, count: int}>
     */
    public function countByDay(\DateTimeInterface $from): array
    {
        /** @var list<array{date: mixed, count: mixed}> $rows */
        $rows = $this->createQueryBuilder('h')
            ->select('SUBSTRING(h.createdAt, 1, 10) as date', 'COUNT(h.id) as count')
            ->where('h.createdAt >= :from')
            ->setParameter('from', $from)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getResult();

        return \array_map(static fn (array $row): array => [
            'date' => self::toString($row['date']) ?? '',
            'count' => self::toInt($row['count']),
        ], $rows);
    }

    /**
     * @return list<array{url: string, hits: int}>
     */
    public function getMostVisitedUrls(int $limit = 10, int $offset = 0): array
    {
        return \array_map(static fn (array $row): array => [
            'url' => self::toString($row['value']) ?? '',
            'hits' => $row['hits'],
        ], $this->topBy('url', $limit, $offset));
    }

    public function countUrls(): int
    {
        return $this->countDistinct('url');
    }

    /**
     * Herkunft null = direkter Aufruf (kein Referer, kein utm_source).
     *
     * @return list<array{origin: ?string, hits: int}>
     */
    public function getMostVisitedOrigins(int $limit = 10, int $offset = 0): array
    {
        return \array_map(static fn (array $row): array => [
            'origin' => self::toString($row['value']),
            'hits' => $row['hits'],
        ], $this->topBy('origin', $limit, $offset));
    }

    /** Ohne direkte Aufrufe: COUNT(DISTINCT) zählt NULL nicht mit. */
    public function countOrigins(): int
    {
        return $this->countDistinct('origin');
    }

    /**
     * Land null = unbekannt.
     *
     * @return list<array{country: ?string, hits: int}>
     */
    public function getMostVisitedCountries(int $limit = 10, int $offset = 0): array
    {
        return \array_map(static fn (array $row): array => [
            'country' => self::toString($row['value']),
            'hits' => $row['hits'],
        ], $this->topBy('country', $limit, $offset));
    }

    /** Ohne unbekannte Länder: COUNT(DISTINCT) zählt NULL nicht mit. */
    public function countCountries(): int
    {
        return $this->countDistinct('country');
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

    /**
     * Häufigste Werte einer Spalte, absteigend nach Aufrufen.
     *
     * @param 'url'|'origin'|'country' $column
     *
     * @return list<array{value: mixed, hits: int}>
     */
    private function topBy(string $column, int $limit, int $offset): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            "SELECT {$column} AS value, COUNT(id) AS hits
                FROM app_analytics_hit
                GROUP BY {$column}
                ORDER BY hits DESC
                LIMIT :limit OFFSET :offset",
            ['limit' => $limit, 'offset' => $offset],
            ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER],
        );

        return \array_map(static fn (array $row): array => [
            'value' => $row['value'] ?? null,
            'hits' => self::toInt($row['hits'] ?? 0),
        ], $rows);
    }

    /** @param 'url'|'origin'|'country' $column */
    private function countDistinct(string $column): int
    {
        return self::toInt(
            $this->getEntityManager()->getConnection()->fetchOne("SELECT COUNT(DISTINCT {$column}) FROM app_analytics_hit"),
        );
    }

    private static function toInt(mixed $value): int
    {
        return \is_numeric($value) ? (int) $value : 0;
    }

    private static function toString(mixed $value): ?string
    {
        return \is_scalar($value) ? (string) $value : null;
    }
}
