<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Concerns;

use WidStudios\Foundation\Tenancy\Jobs\RestoreTenantContext;
use WidStudios\Foundation\Tenancy\TenantContext;

/**
 * Rende un job consapevole del tenant per cui e' stato accodato.
 *
 * Il problema che risolve: un job viene eseguito minuti dopo, in un processo
 * che non ha mai visto la richiesta che lo ha generato. Senza qualcosa che
 * trasporti il tenant, il worker esegue il job nel contesto di piattaforma, e
 * il risultato dipende da quale tenant era attivo l'ultima volta su quel
 * worker: puo' funzionare in sviluppo per settimane e scrivere nel database
 * sbagliato al primo giorno di carico reale.
 *
 * Come funziona:
 *
 *   1. alla messa in coda, il FoundationServiceProvider intercetta l'evento
 *      JobQueueing e invoca captureTenantContext(): lo slug finisce in una
 *      proprieta' del job, che viene serializzata insieme a tutto il resto;
 *   2. all'esecuzione, il job middleware RestoreTenantContext riapre il
 *      contesto prima di handle(), e lo chiude dopo.
 *
 * Un job che usa questo trait e a cui manca lo slug fallisce subito, invece di
 * eseguire nel contesto sbagliato: e' l'unico comportamento accettabile.
 *
 * Per aggiungere altri middleware al job si sovrascrive jobMiddleware(), non
 * middleware(): sovrascrivere middleware() rimuoverebbe il ripristino del
 * contesto senza che nulla lo segnali.
 *
 *     final class RecalculateStockJob implements ShouldQueue
 *     {
 *         use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
 *         use TenantAware;
 *
 *         public int $tries = 3;
 *         public array $backoff = [30, 120, 300];
 *         public int $timeout = 120;
 *
 *         public function __construct(public int $batchId) {}
 *     }
 */
trait TenantAware
{
    /**
     * Slug del tenant per cui il job e' stato accodato.
     *
     * Pubblica perche' deve essere serializzata con il job, e leggibile da
     * failed() per registrare il contesto del fallimento.
     */
    public ?string $tenantKey = null;

    /**
     * Invocato automaticamente alla messa in coda.
     *
     * Non sovrascrive un valore gia' presente: un job puo' dichiarare
     * esplicitamente il proprio tenant, ad esempio durante il provisioning,
     * quando viene accodato dal contesto di piattaforma.
     */
    public function captureTenantContext(TenantContext $context): void
    {
        $this->tenantKey ??= $context->slug();
    }

    /**
     * Fissa il tenant esplicitamente, prima del dispatch.
     */
    public function forTenant(string $slug): static
    {
        $this->tenantKey = $slug;

        return $this;
    }

    public function tenantSlug(): ?string
    {
        return $this->tenantKey;
    }

    /**
     * @return list<object>
     */
    final public function middleware(): array
    {
        return [new RestoreTenantContext(), ...$this->jobMiddleware()];
    }

    /**
     * Middleware aggiuntivi del job. Da sovrascrivere al posto di middleware().
     *
     * @return list<object>
     */
    protected function jobMiddleware(): array
    {
        return [];
    }
}
