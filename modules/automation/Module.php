<?php

namespace CRMMarketing\Modules\Automation;

class Module
{
    private string $name = 'automation';
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
        $router->get('/automations', [$this, 'listAutomations']);
        $router->get('/automations/:id', [$this, 'showAutomation']);
        $router->post('/automations', [$this, 'createAutomation']);
        $router->put('/automations/:id', [$this, 'updateAutomation']);
        $router->delete('/automations/:id', [$this, 'deleteAutomation']);
    }

    public function listAutomations(): array
    {
        return ['status' => 200, 'data' => 'Automations list'];
    }

    public function showAutomation(): array
    {
        return ['status' => 200, 'data' => 'Automation details'];
    }

    public function createAutomation(): array
    {
        return ['status' => 201, 'data' => 'Automation created'];
    }

    public function updateAutomation(): array
    {
        return ['status' => 200, 'data' => 'Automation updated'];
    }

    public function deleteAutomation(): array
    {
        return ['status' => 204, 'data' => 'Automation deleted'];
    }
}
