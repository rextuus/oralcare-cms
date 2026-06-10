<?php

namespace App\EventListener;

use App\Entity\AnalyticsHit;
use Doctrine\ORM\EntityManagerInterface;
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

        // Ignoriere statische Dateien (Sulu Media etc. falls gewünscht, hier einfachheitshalber alles mit Endung)
        if (preg_match('/\.(js|css|png|jpg|jpeg|gif|ico|svg|webmanifest)$/i', $path)) {
            return;
        }

        $hit = new AnalyticsHit();
        $hit->setUrl($request->getUri());
        $hit->setUserAgent($request->headers->get('User-Agent'));
        $hit->setReferer($request->headers->get('referer'));

        $this->entityManager->persist($hit);
        $this->entityManager->flush();
    }
}
