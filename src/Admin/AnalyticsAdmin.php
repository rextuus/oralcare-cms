<?php

declare(strict_types=1);

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
    public const STATS_COUNTRIES_VIEW = 'app.analytics_countries_list';
    public const STATS_DAILY_VIEW = 'app.analytics_daily_list';
    public const EDIT_FORM_VIEW = 'app.analytics_hit_edit_form';
    public const SECURITY_CONTEXT = 'sulu.modules.analytics';

    public function __construct(private readonly ViewBuilderFactoryInterface $viewBuilderFactory)
    {
    }

    public function configureNavigationItems(NavigationItemCollection $navigationItemCollection): void
    {
        $analyticsItem = new NavigationItem('app.analytics');
        $analyticsItem->setLabel('Analytics');
        $analyticsItem->setIcon('su-chart');

        $logsItem = new NavigationItem('app.analytics_hits');
        $logsItem->setLabel('Logs');
        $logsItem->setView(self::LIST_VIEW);
        $analyticsItem->addChild($logsItem);

        $urlsItem = new NavigationItem('app.analytics_urls');
        $urlsItem->setLabel('Top URLs');
        $urlsItem->setView(self::STATS_URLS_VIEW);
        $analyticsItem->addChild($urlsItem);

        $originsItem = new NavigationItem('app.analytics_origins');
        $originsItem->setLabel('Top Herkunft');
        $originsItem->setView(self::STATS_ORIGINS_VIEW);
        $analyticsItem->addChild($originsItem);

        $countriesItem = new NavigationItem('app.analytics_countries');
        $countriesItem->setLabel('Top Länder');
        $countriesItem->setView(self::STATS_COUNTRIES_VIEW);
        $analyticsItem->addChild($countriesItem);

        $dailyItem = new NavigationItem('app.analytics_daily');
        $dailyItem->setLabel('Traffic nach Tagen');
        $dailyItem->setView(self::STATS_DAILY_VIEW);
        $analyticsItem->addChild($dailyItem);

        $navigationItemCollection->add($analyticsItem);
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        $listView = $this->viewBuilderFactory->createListViewBuilder(self::LIST_VIEW, '/analytics/logs')
            ->setResourceKey('analytics_hits')
            ->setListKey('analytics_hits')
            ->setTitle('Analytics Logs')
            ->addListAdapters(['table'])
            ->setEditView(self::EDIT_FORM_VIEW);
        $viewCollection->add($listView);

        $urlsView = $this->viewBuilderFactory->createListViewBuilder(self::STATS_URLS_VIEW, '/analytics/urls')
            ->setResourceKey('analytics_urls')
            ->setListKey('analytics_urls')
            ->setTitle('Top 10 URLs')
            ->addListAdapters(['table']);
        $viewCollection->add($urlsView);

        $originsView = $this->viewBuilderFactory->createListViewBuilder(self::STATS_ORIGINS_VIEW, '/analytics/origins')
            ->setResourceKey('analytics_origins')
            ->setListKey('analytics_origins')
            ->setTitle('Top 10 Herkunft')
            ->addListAdapters(['table']);
        $viewCollection->add($originsView);

        $countriesView = $this->viewBuilderFactory->createListViewBuilder(self::STATS_COUNTRIES_VIEW, '/analytics/countries')
            ->setResourceKey('analytics_countries')
            ->setListKey('analytics_countries')
            ->setTitle('Top 10 Länder')
            ->addListAdapters(['table']);
        $viewCollection->add($countriesView);

        $dailyView = $this->viewBuilderFactory->createListViewBuilder(self::STATS_DAILY_VIEW, '/analytics/daily')
            ->setResourceKey('analytics_daily')
            ->setListKey('analytics_daily')
            ->setTitle('Traffic Verlauf (Letzte 30 Tage)')
            ->addListAdapters(['table']);
        $viewCollection->add($dailyView);

        $editFormView = $this->viewBuilderFactory->createResourceTabViewBuilder(self::EDIT_FORM_VIEW, '/analytics/logs/:id')
            ->setResourceKey('analytics_hits')
            ->setBackView(self::LIST_VIEW);
        $viewCollection->add($editFormView);

        $editDetailsFormView = $this->viewBuilderFactory->createFormViewBuilder(self::EDIT_FORM_VIEW . '.details', '/details')
            ->setResourceKey('analytics_hits')
            ->setFormKey('analytics_hit_details')
            ->setTabTitle('Details')
            ->addToolbarActions([])
            ->setParent(self::EDIT_FORM_VIEW);
        $viewCollection->add($editDetailsFormView);
    }

    public function getSecurityContexts(): array
    {
        return [
            'Sulu' => [
                'Custom' => [
                    self::SECURITY_CONTEXT => [
                        'view',
                    ],
                ],
            ],
        ];
    }
}
