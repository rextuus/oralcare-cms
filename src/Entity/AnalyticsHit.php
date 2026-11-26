<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AnalyticsHitRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnalyticsHitRepository::class)]
#[ORM\Table(name: 'app_analytics_hit')]
#[ORM\Index(name: 'idx_url_hits', columns: ['url', 'id'])]
#[ORM\Index(name: 'idx_origin_hits', columns: ['origin', 'id'])]
#[ORM\Index(name: 'idx_country_hits', columns: ['country', 'id'])]
#[ORM\Index(name: 'idx_created_at', columns: ['created_at'])]
class AnalyticsHit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $url = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $referer = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $origin = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $country = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $this->fit($url, 255);

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $this->fit($userAgent, 255);

        return $this;
    }

    public function getReferer(): ?string
    {
        return $this->referer;
    }

    public function setReferer(?string $referer): self
    {
        $this->referer = $this->fit($referer, 255);

        return $this;
    }

    public function getOrigin(): ?string
    {
        return $this->origin;
    }

    public function setOrigin(?string $origin): self
    {
        $this->origin = $this->fit($origin, 255);

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): self
    {
        $this->country = $this->fit($country, 10);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Auf die Spaltenlänge kürzen: MySQL bricht im Strict-Mode sonst mit „Data too long“ ab – und der Listener
     * schreibt bei jedem Seitenaufruf, die Seite wäre dann ein 500er.
     *
     * @return ($value is null ? null : string)
     */
    private function fit(?string $value, int $length): ?string
    {
        return null === $value ? null : \mb_substr($value, 0, $length);
    }
}
