<?php

namespace CRMMarketing\Modules\Analytics;

class Module
{
    private string $name = 'analytics';
    private string $version = '1.0.0';

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function registerRoutes($router): void
    {
        $router->get('/analytics/dashboard', [$this, 'dashboard']);
        $router->get('/analytics/campaigns', [$this, 'campaignStats']);
        $router->get('/analytics/contacts', [$this, 'contactStats']);
        $router->get('/analytics/emails', [$this, 'emailStats']);
    }

    public function dashboard(): array
    {
        return ['status' => 200, 'data' => 'Dashboard analytics'];
    }

    public function campaignStats(): array
    {
        return ['status' => 200, 'data' => 'Campaign statistics'];
    }

    public function contactStats(): array
    {
        return ['status' => 200, 'data' => 'Contact statistics'];
    }

    public function emailStats(): array
    {
        return ['status' => 200, 'data' => 'Email statistics'];
    }
}