<?php

namespace App\Controller\Admin;

use App\Entity\AnalyticsHit;
use App\Admin\AnalyticsAdmin;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\View\ViewHandlerInterface;
use Sulu\Component\Rest\AbstractRestController;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptor;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Rest\RestHelperInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class AnalyticsHitController extends AbstractRestController
{
    private EntityManagerInterface $entityManager;
    private DoctrineListBuilderFactoryInterface $listBuilderFactory;
    private RestHelperInterface $restHelper;

    public function __construct(
        EntityManagerInterface $entityManager,
        DoctrineListBuilderFactoryInterface $listBuilderFactory,
        RestHelperInterface $restHelper,
        ViewHandlerInterface $viewHandler,
        TokenStorageInterface $tokenStorage
    ) {
        parent::__construct($viewHandler, $tokenStorage);
        $this->entityManager = $entityManager;
        $this->listBuilderFactory = $listBuilderFactory;
        $this->restHelper = $restHelper;
    }

    #[Route('/admin/api/analytics_hits', name: 'app.get_analytics_hits', methods: ['GET'])]
    public function cgetAction(Request $request): Response
    {
        $fieldDescriptors = [
            'id' => new DoctrineFieldDescriptor('id', 'id', AnalyticsHit::class, 'sulu_admin.id'),
            'url' => new DoctrineFieldDescriptor('url', 'url', AnalyticsHit::class, 'sulu_admin.url'),
            'userAgent' => new DoctrineFieldDescriptor('userAgent', 'userAgent', AnalyticsHit::class, 'app.user_agent'),
            'referer' => new DoctrineFieldDescriptor('referer', 'referer', AnalyticsHit::class, 'app.referer'),
            'origin' => new DoctrineFieldDescriptor('origin', 'origin', AnalyticsHit::class, 'app.origin'),
            'createdAt' => new DoctrineFieldDescriptor('createdAt', 'createdAt', AnalyticsHit::class, 'sulu_admin.created'),
        ];

        $listBuilder = $this->listBuilderFactory->create(AnalyticsHit::class);
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);

        if (!$request->query->has('sortColumn')) {
            $listBuilder->sort($fieldDescriptors['id'], 'desc');
        }

        $list = $listBuilder->execute();

        $listRepresentation = new PaginatedRepresentation(
            $list,
            'analytics_hits',
            (int) $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            (int) $listBuilder->count()
        );

        return $this->handleView($this->view($listRepresentation, Response::HTTP_OK));
    }

    #[Route('/admin/api/analytics_urls', name: 'app.get_analytics_urls', methods: ['GET'])]
    public function getStatsUrlsAction(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $offset = ($page - 1) * $limit;

        $repo = $this->entityManager->getRepository(AnalyticsHit::class);
        $data = $repo->getMostVisitedUrls($limit, $offset);

        $formattedData = [];
        $i = $offset + 1;
        foreach ($data as $item) {
            $formattedData[] = [
                'id' => $i++,
                'url' => $item['url'],
                'hits' => (int) $item['hits'],
            ];
        }

        $total = count($repo->getMostVisitedUrls(1000, 0));

        $listRepresentation = new PaginatedRepresentation(
            $formattedData,
            'analytics_urls',
            $page,
            $limit,
            $total
        );

        return $this->handleView($this->view($listRepresentation, Response::HTTP_OK));
    }

    #[Route('/admin/api/analytics_daily', name: 'app.get_analytics_daily', methods: ['GET'])]
    public function getStatsDailyAction(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 30);

        $from = new \DateTime('-30 days');
        $repo = $this->entityManager->getRepository(AnalyticsHit::class);
        $data = $repo->countByDay($from);

        $formattedData = [];
        $i = 1;
        foreach ($data as $item) {
            $formattedData[] = [
                'id' => $i++,
                'date' => $item['date'],
                'count' => (int) $item['count'],
            ];
        }

        $total = count($formattedData);

        $listRepresentation = new PaginatedRepresentation(
            $formattedData,
            'analytics_daily',
            $page,
            $limit,
            $total
        );

        return $this->handleView($this->view($listRepresentation, Response::HTTP_OK));
    }

    #[Route('/admin/api/analytics_origins', name: 'app.get_analytics_origins', methods: ['GET'])]
    public function getStatsOriginsAction(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $offset = ($page - 1) * $limit;

        $repo = $this->entityManager->getRepository(AnalyticsHit::class);
        $data = $repo->getMostVisitedOrigins($limit, $offset);

        $formattedData = [];
        $i = $offset + 1;
        foreach ($data as $item) {
            $formattedData[] = [
                'id' => $i++,
                'origin' => $item['origin'] ?? 'Direkt / Unbekannt',
                'hits' => (int) $item['hits'],
            ];
        }

        $total = count($repo->getMostVisitedOrigins(1000, 0));

        $listRepresentation = new PaginatedRepresentation(
            $formattedData,
            'analytics_origins',
            $page,
            $limit,
            $total
        );

        return $this->handleView($this->view($listRepresentation, Response::HTTP_OK));
    }

    #[Route('/admin/api/analytics_hits/{id}', name: 'app.get_analytics_hit', methods: ['GET'])]
    public function getAction(int $id): Response
    {
        $hit = $this->entityManager->getRepository(AnalyticsHit::class)->find($id);
        if (!$hit) {
            throw new NotFoundHttpException();
        }

        return $this->handleView($this->view($hit, Response::HTTP_OK));
    }

    #[Route('/admin/api/analytics_hits/{id}', name: 'app.delete_analytics_hit', methods: ['DELETE'])]
    public function deleteAction(int $id): Response
    {
        $hit = $this->entityManager->getRepository(AnalyticsHit::class)->find($id);
        if ($hit) {
            $this->entityManager->remove($hit);
            $this->entityManager->flush();
        }

        return $this->handleView($this->view(null, Response::HTTP_NO_CONTENT));
    }

    #[Route('/admin/api/analytics_hits_cleanup', name: 'app.cleanup_analytics_hits', methods: ['DELETE'])]
    public function cleanupAction(): Response
    {
        $repo = $this->entityManager->getRepository(AnalyticsHit::class);
        $count = $repo->deleteImages();

        return $this->handleView($this->view(['count' => $count], Response::HTTP_OK));
    }

    public function getSecurityContext(): string
    {
        return AnalyticsAdmin::SECURITY_CONTEXT;
    }
}
