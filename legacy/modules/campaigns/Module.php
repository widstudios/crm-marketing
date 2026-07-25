<?php

namespace CRMMarketing\Modules\Campaigns;

class Module
{
    private string $name = 'campaigns';
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
        $router->get('/campaigns', [$this, 'listCampaigns']);
        $router->get('/campaigns/:id', [$this, 'showCampaign']);
        $router->post('/campaigns', [$this, 'createCampaign']);
        $router->put('/campaigns/:id', [$this, 'updateCampaign']);
        $router->delete('/campaigns/:id', [$this, 'deleteCampaign']);
    }

    public function listCampaigns(): array
    {
        return ['status' => 200, 'data' => 'Campaigns list'];
    }

    public function showCampaign(): array
    {
        return ['status' => 200, 'data' => 'Campaign details'];
    }

    public function createCampaign(): array
    {
        return ['status' => 201, 'data' => 'Campaign created'];
    }

    public function updateCampaign(): array
    {
        return ['status' => 200, 'data' => 'Campaign updated'];
    }

    public function deleteCampaign(): array
    {
        return ['status' => 204, 'data' => 'Campaign deleted'];
    }
}