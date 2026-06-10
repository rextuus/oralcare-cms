<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AnalyticsHit;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $urls = [
            '/',
            '/leistungen',
            '/uber-uns',
            '/kontakt',
            '/aktuelles',
        ];

        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1',
        ];

        for ($i = 0; $i < 20; ++$i) {
            $hit = new AnalyticsHit();
            $hit->setUrl('http://localhost:8080' . $urls[array_rand($urls)]);
            $hit->setUserAgent($userAgents[array_rand($userAgents)]);
            if (rand(0, 1)) {
                $hit->setReferer('https://www.google.com');
            }

            $manager->persist($hit);
        }

        $manager->flush();
    }
}
