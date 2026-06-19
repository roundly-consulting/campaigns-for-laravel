<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use RoundlyConsulting\Campaigns\CampaignsServiceProvider;
use RoundlyConsulting\Campaigns\Managers\InMemoryManager;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            CampaignsServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('mail.default', 'array');

        InMemoryManager::flush();
    }
}
