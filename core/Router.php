<?php

namespace CRMMarketing;

class Router
{
    private array $routes = [];
    private string $prefix;

    public function __construct(string $prefix = '/api/v1')
    {
        $this->prefix = $prefix;
    }

    public function register(string $method, string $path, callable $handler): void
    {
        $fullPath = $this->prefix . $path;
        $this->routes[$method][$fullPath] = $handler;
    }

    public function get(string $path, callable $handler): void
    {
        $this->register('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->register('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): void
    {
        $this->register('PUT', $path, $handler);
    }

    public function delete(string $path, callable $handler): void
    {
        $this->register('DELETE', $path, $handler);
    }

    public function dispatch(string $method, string $path): array
    {
        if (!isset($this->routes[$method][$path])) {
            return ['status' => 404, 'message' => 'Route not found'];
        }

        $handler = $this->routes[$method][$path];
        return $handler();
    }
}