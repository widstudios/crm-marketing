<?php

namespace CRMMarketing;

class Kernel
{
    private array $config;
    private Database $database;
    private ModuleManager $moduleManager;
    private Router $router;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->database = new Database($config['database']['path']);
        $this->moduleManager = new ModuleManager($config);
        $this->router = new Router($config['api']['prefix']);
    }

    public function boot(): void
    {
        $modulesPath = __DIR__ . '/../modules';
        $this->moduleManager->loadModules($modulesPath);
        
        $this->registerModuleRoutes();
    }

    private function registerModuleRoutes(): void
    {
        foreach ($this->moduleManager->getModules() as $module) {
            if (method_exists($module, 'registerRoutes')) {
                $module->registerRoutes($this->router);
            }
        }
    }

    public function getDatabase(): Database
    {
        return $this->database;
    }

    public function getModuleManager(): ModuleManager
    {
        return $this->moduleManager;
    }

    public function getRouter(): Router
    {
        return $this->router;
    }
}