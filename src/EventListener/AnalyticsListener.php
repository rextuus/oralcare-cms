<?php

namespace App\EventListener;

use App\Entity\AnalyticsHit;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Component\Webspace\Analyzer\Attributes\RequestAttributes;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[AsEventListener(event: 'kernel.request', method: 'onKernelRequest')]
readonly class AnalyticsListener
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->getRequestType() !== HttpKernelInterface::MAIN_REQUEST) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Ignoriere Admin-Bereich und Profiler/Debug-Routen
        if (str_starts_with($path, '/admin') || str_starts_with($path, '/_')) {
            return;
        }

        // Ignoriere statische Dateien
        if (preg_match('/\.(js|css|png|jpg|jpeg|gif|ico|svg|webp|webmanifest|xml|txt)$/i', $path)
            || str_contains($path, '/media/')
            || str_contains($path, '/uploads/')
            || str_contains($path, '/js/')
            || str_contains($path, '/css/')
        ) {
            return;
        }

        $hit = new AnalyticsHit();
        $hit->setUrl($request->getUri());
        $hit->setUserAgent($request->headers->get('User-Agent'));
        $hit->setReferer($request->headers->get('referer'));

        // Bestimme die Herkunft (Marketing/Referer)
        $origin = $request->query->get('utm_source');
        if (!$origin) {
            $referer = $request->headers->get('referer');
            if ($referer) {
                $origin = parse_url($referer, PHP_URL_HOST);
            }
        }
        $hit->setOrigin($origin);

        // ==========================================
        // NEU: Bestimmung des Herkunftslandes (ISO-Code)
        // ==========================================
        $country = null;

        // 1. Option: Cloudflare / Reverse Proxy Header (Bestes Ergebnis im Live-Betrieb)
        if ($request->headers->has('cf-ipcountry')) {
            $country = $request->headers->get('cf-ipcountry');
        }

        // 2. Option: Aus der Sulu-Lokalisierung (falls im Webspace de_de, de_at, etc. aktiv ist)
        if (empty($country) && $request->attributes->has('_sulu')) {
            /** @var RequestAttributes $suluAttributes */
            $suluAttributes = $request->attributes->get('_sulu');
            $localization = $suluAttributes->getAttribute('localization');

            if ($localization && method_exists($localization, 'getCountry') && $localization->getCountry()) {
                $country = strtoupper($localization->getCountry());
            }
        }

        // 3. Option: Schätzung über die Browser-Sprache (z.B. "de-DE" -> "DE")
        if (empty($country)) {
            $languages = $request->getLanguages(); // Liefert z.B. ['de_DE', 'de', 'en_US']
            foreach ($languages as $lang) {
                if (str_contains($lang, '_') || str_contains($lang, '-')) {
                    $parts = preg_split('/[_-]/', $lang);
                    if (isset($parts[1]) && strlen($parts[1]) === 2) {
                        $country = strtoupper($parts[1]);
                        break;
                    }
                }
            }
        }

        // 4. Option (Optional für die Zukunft im Live-Betrieb): Echte IP-Adresse via GeoIP2
        // Wenn du eine GeoIP-Datenbank (z.B. MaxMind) nutzt, würde das so aussehen:
        // if (empty($country) && $request->getClientIp()) {
        //     $country = $this->geoIpService->getCountryCode($request->getClientIp());
        // }

        // Land in Entität speichern (wenn ermittelt und nicht 'XX' für unbekannt bei Cloudflare)
        if ($country && $country !== 'XX') {
            $hit->setCountry($country);
        }
        // ==========================================

        $this->entityManager->persist($hit);
        $this->entityManager->flush();
    }
}
