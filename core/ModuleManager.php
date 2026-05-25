<?php

namespace CRMMarketing;

class ModuleManager
{
    private array $modules = [];
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function loadModules(string $modulesPath): void
    {
        if (!is_dir($modulesPath)) {
            throw new \RuntimeException("Modules path not found: $modulesPath");
        }

        foreach (scandir($modulesPath) as $module) {
            if ($module === '.' || $module === '..') {
                continue;
            }

            $modulePath = $modulesPath . DIRECTORY_SEPARATOR . $module;
            if (!is_dir($modulePath)) {
                continue;
            }

            if (!isset($this->config['modules'][$module]['enabled']) || !$this->config['modules'][$module]['enabled']) {
                continue;
            }

            $this->loadModule($module, $modulePath);
        }
    }

    private function loadModule(string $name, string $path): void
    {
        $moduleFile = $path . DIRECTORY_SEPARATOR . 'Module.php';
        if (!file_exists($moduleFile)) {
            throw new \RuntimeException("Module file not found: $moduleFile");
        }

        require_once $moduleFile;
        $className = 'CRMMarketing\\Modules\\' . ucfirst($name) . '\\Module';
        
        if (!class_exists($className)) {
            throw new \RuntimeException("Module class not found: $className");
        }

        $this->modules[$name] = new $className();
    }

    public function getModule(string $name): ?object
    {
        return $this->modules[$name] ?? null;
    }

    public function getModules(): array
    {
        return $this->modules;
    }
}