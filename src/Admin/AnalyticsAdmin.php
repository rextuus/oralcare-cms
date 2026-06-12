<?php

namespace App\Admin;

use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItem;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;

class AnalyticsAdmin extends Admin
{
    public const LIST_VIEW = 'app.analytics_hits_list';
    public const STATS_URLS_VIEW = 'app.analytics_urls_list';
    public const STATS_ORIGINS_VIEW = 'app.analytics_origins_list';
    public const STATS_DAILY_VIEW = 'app.analytics_daily_list';
    public const EDIT_FORM_VIEW = 'app.analytics_hit_edit_form';
    public const SECURITY_CONTEXT = 'sulu.modules.analytics';

    private ViewBuilderFactoryInterface $viewBuilderFactory;

    public function __construct(ViewBuilderFactoryInterface $viewBuilderFactory)
    {
        $this->viewBuilderFactory = $viewBuilderFactory;
    }

    public function configureNavigationItems(NavigationItemCollection $navigationItemCollection): void
    {
        $analyticsItem = new NavigationItem('app.analytics');
        $analyticsItem->setLabel('Analytics');
        $analyticsItem->setIcon('su-chart');

        $logsItem = new NavigationItem('app.analytics_hits');
        $logsItem->setLabel('Logs');
        $logsItem->setView(static::LIST_VIEW);
        $analyticsItem->addChild($logsItem);

        $urlsItem = new NavigationItem('app.analytics_urls');
        $urlsItem->setLabel('Top URLs');
        $urlsItem->setView(static::STATS_URLS_VIEW);
        $analyticsItem->addChild($urlsItem);

        $originsItem = new NavigationItem('app.analytics_origins');
        $originsItem->setLabel('Top Herkunft');
        $originsItem->setView(static::STATS_ORIGINS_VIEW);
        $analyticsItem->addChild($originsItem);

        $dailyItem = new NavigationItem('app.analytics_daily');
        $dailyItem->setLabel('Traffic nach Tagen');
        $dailyItem->setView(static::STATS_DAILY_VIEW);
        $analyticsItem->addChild($dailyItem);

        $navigationItemCollection->add($analyticsItem);
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        $listView = $this->viewBuilderFactory->createListViewBuilder(static::LIST_VIEW, '/analytics/logs')
            ->setResourceKey('analytics_hits')
            ->setListKey('analytics_hits')
            ->setTitle('Analytics Logs')
            ->addListAdapters(['table'])
            ->setEditView(static::EDIT_FORM_VIEW);
        $viewCollection->add($listView);

        $urlsView = $this->viewBuilderFactory->createListViewBuilder(static::STATS_URLS_VIEW, '/analytics/urls')
            ->setResourceKey('analytics_urls')
            ->setListKey('analytics_urls')
            ->setTitle('Top 10 URLs')
            ->addListAdapters(['table']);
        $viewCollection->add($urlsView);

        $originsView = $this->viewBuilderFactory->createListViewBuilder(static::STATS_ORIGINS_VIEW, '/analytics/origins')
            ->setResourceKey('analytics_origins')
            ->setListKey('analytics_origins')
            ->setTitle('Top 10 Herkunft')
            ->addListAdapters(['table']);
        $viewCollection->add($originsView);

        $dailyView = $this->viewBuilderFactory->createListViewBuilder(static::STATS_DAILY_VIEW, '/analytics/daily')
            ->setResourceKey('analytics_daily')
            ->setListKey('analytics_daily')
            ->setTitle('Traffic Verlauf (Letzte 30 Tage)')
            ->addListAdapters(['table']);
        $viewCollection->add($dailyView);

        $editFormView = $this->viewBuilderFactory->createResourceTabViewBuilder(static::EDIT_FORM_VIEW, '/analytics/logs/:id')
            ->setResourceKey('analytics_hits')
            ->setBackView(static::LIST_VIEW);
        $viewCollection->add($editFormView);

        $editDetailsFormView = $this->viewBuilderFactory->createFormViewBuilder(static::EDIT_FORM_VIEW . '.details', '/details')
            ->setResourceKey('analytics_hits')
            ->setFormKey('analytics_hit_details')
            ->setTabTitle('Details')
            ->addToolbarActions([])
            ->setParent(static::EDIT_FORM_VIEW);
        $viewCollection->add($editDetailsFormView);
    }

    public function getSecurityContexts(): array
    {
        return [
            'Sulu' => [
                'Custom' => [
                    static::SECURITY_CONTEXT => [
                        'view',
                    ],
                ],
            ],
        ];
    }
}
