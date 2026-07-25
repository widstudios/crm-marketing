<?php

namespace CRMMarketing\Modules\Contacts;

class Module
{
    private string $name = 'contacts';
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
        $router->get('/contacts', [$this, 'listContacts']);
        $router->get('/contacts/:id', [$this, 'showContact']);
        $router->post('/contacts', [$this, 'createContact']);
        $router->put('/contacts/:id', [$this, 'updateContact']);
        $router->delete('/contacts/:id', [$this, 'deleteContact']);
    }

    public function listContacts(): array
    {
        return ['status' => 200, 'data' => 'Contacts list'];
    }

    public function showContact(): array
    {
        return ['status' => 200, 'data' => 'Contact details'];
    }

    public function createContact(): array
    {
        return ['status' => 201, 'data' => 'Contact created'];
    }

    public function updateContact(): array
    {
        return ['status' => 200, 'data' => 'Contact updated'];
    }

    public function deleteContact(): array
    {
        return ['status' => 204, 'data' => 'Contact deleted'];
    }
}