<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Tenancy\Bootstrappers;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Filesystem\FilesystemManager;
use WidStudios\Foundation\Contracts\Tenancy\Tenant;
use WidStudios\Foundation\Contracts\Tenancy\TenantBootstrapper;

/**
 * Punta il disco applicativo alla cartella privata del tenant.
 *
 * Come per il database, la riconfigurazione da sola non basta: il
 * FilesystemManager conserva le istanze gia' risolte, quindi serve
 * forgetDisk() perche' la nuova radice abbia effetto.
 *
 * La visibilita' e' 'private' e non e' configurabile per tenant: un file di un
 * cliente su disco pubblico e' accessibile a chiunque ne indovini il percorso
 * (rules/security.md R32).
 */
final class FilesystemBootstrapper implements TenantBootstrapper
{
    public function __construct(
        private readonly FilesystemManager $filesystem,
        private readonly Config $config,
    ) {}

    public function bootstrap(Tenant $tenant): void
    {
        $disk = $this->disk();
        $root = rtrim((string) $this->config->get('foundation.storage.root'), '/');

        $this->config->set("filesystems.disks.{$disk}", [
            'driver' => 'local',
            'root' => $root.'/'.$tenant->getSlug(),
            'visibility' => 'private',
            'throw' => true,
            'serve' => false,
        ]);

        $this->filesystem->forgetDisk($disk);
    }

    public function revert(): void
    {
        $this->filesystem->forgetDisk($this->disk());
    }

    private function disk(): string
    {
        return (string) $this->config->get('foundation.storage.disk');
    }
}
