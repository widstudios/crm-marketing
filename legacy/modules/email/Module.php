<?php

namespace CRMMarketing\Modules\Email;

class Module
{
    private string $name = 'email';
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
        $router->get('/emails', [$this, 'listEmails']);
        $router->get('/emails/:id', [$this, 'showEmail']);
        $router->post('/emails', [$this, 'sendEmail']);
        $router->put('/emails/:id/status', [$this, 'updateStatus']);
    }

    public function listEmails(): array
    {
        return ['status' => 200, 'data' => 'Emails list'];
    }

    public function showEmail(): array
    {
        return ['status' => 200, 'data' => 'Email details'];
    }

    public function sendEmail(): array
    {
        return ['status' => 200, 'data' => 'Email sent'];
    }

    public function updateStatus(): array
    {
        return ['status' => 200, 'data' => 'Status updated'];
    }
}