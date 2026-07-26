<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Tenancy;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use WidStudios\Foundation\Contracts\Tenancy\Tenant;
use WidStudios\Foundation\Contracts\Tenancy\TenantBootstrapper;
use WidStudios\Foundation\Contracts\Tenancy\TenantRepository;
use WidStudios\Foundation\Exceptions\TenantNotActive;
use WidStudios\Foundation\Tenancy\Events\TenancyEnded;
use WidStudios\Foundation\Tenancy\Events\TenancyInitialized;

/**
 * Apre e chiude il contesto tenant.
 *
 * E' l'unico punto del sistema autorizzato a riconfigurare database, cache,
 * storage e code per un cliente diverso. Averne uno solo e' cio' che rende
 * verificabile l'isolamento: se fosse possibile farlo altrove, ogni punto del
 * codice diventerebbe un punto da controllare.
 */
final class TenantManager
{
    /** @var list<TenantBootstrapper>|null */
    private ?array $bootstrappers = null;

    public function __construct(
        private readonly Container $container,
        private readonly TenantContext $context,
        private readonly TenantRepository $tenants,
        private readonly Dispatcher $events,
    ) {}

    /**
     * Apre il contesto per il tenant indicato.
     *
     * Idempotente: reinizializzare sullo stesso tenant non fa nulla, cosi' che
     * un middleware e un listener di coda possano invocarlo entrambi senza
     * coordinarsi.
     */
    public function initialize(Tenant $tenant): void
    {
        if ($this->context->is($tenant)) {
            return;
        }

        if ($this->context->has()) {
            $this->end();
        }

        $this->context->set($tenant);

        foreach ($this->resolveBootstrappers() as $bootstrapper) {
            $bootstrapper->bootstrap($tenant);
        }

        $this->events->dispatch(new TenancyInitialized($tenant->getSlug()));
    }

    /**
     * Come initialize(), ma rifiuta i tenant che non possono ricevere traffico.
     *
     * @throws TenantNotActive
     */
    public function initializeForRequest(Tenant $tenant): void
    {
        if (! $tenant->getStatus()->allowsAccess()) {
            throw TenantNotActive::for($tenant);
        }

        $this->initialize($tenant);
    }

    public function initializeBySlug(string $slug): void
    {
        $this->initialize($this->tenants->findBySlugOrFail($slug));
    }

    /**
     * Chiude il contesto e riporta il framework allo stato di piattaforma.
     *
     * I bootstrapper si annullano in ordine inverso rispetto all'apertura:
     * l'ordine conta, perche' alcuni dipendono dallo stato lasciato dai
     * precedenti.
     */
    public function end(): void
    {
        if (! $this->context->has()) {
            return;
        }

        $slug = $this->context->slug();

        foreach (array_reverse($this->resolveBootstrappers()) as $bootstrapper) {
            $bootstrapper->revert();
        }

        $this->context->forget();

        if ($slug !== null) {
            $this->events->dispatch(new TenancyEnded($slug));
        }
    }

    /**
     * Esegue il callback nel contesto del tenant, ripristinando lo stato
     * precedente anche in caso di eccezione.
     *
     * @template TReturn
     *
     * @param  Closure(Tenant): TReturn  $callback
     * @return TReturn
     */
    public function run(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->context->current();

        $this->initialize($tenant);

        try {
            return $callback($tenant);
        } finally {
            $previous !== null ? $this->initialize($previous) : $this->end();
        }
    }

    /**
     * Esegue il callback per ogni tenant, a lotti.
     *
     * Il fallimento su un tenant non interrompe gli altri: il risultato riporta
     * gli slug falliti con la relativa eccezione, perche' un comando su N
     * clienti che si ferma al terzo lascia il sistema in uno stato peggiore di
     * quello da cui e' partito.
     *
     * @param  Closure(Tenant): void  $callback
     * @return array<string, \Throwable> falliti, indicizzati per slug
     */
    public function runForEach(Closure $callback, ?int $chunkSize = null): array
    {
        $failures = [];
        $chunkSize ??= (int) config('foundation.tenancy.chunk_size', 50);

        $this->tenants->chunk(max(1, $chunkSize), function (iterable $tenants) use ($callback, &$failures): void {
            foreach ($tenants as $tenant) {
                if (! $tenant->getStatus()->allowsProcessing()) {
                    continue;
                }

                try {
                    $this->run($tenant, $callback);
                } catch (\Throwable $exception) {
                    $failures[$tenant->getSlug()] = $exception;
                }
            }
        });

        $this->end();

        return $failures;
    }

    /**
     * @return list<TenantBootstrapper>
     */
    private function resolveBootstrappers(): array
    {
        if ($this->bootstrappers !== null) {
            return $this->bootstrappers;
        }

        /** @var list<class-string<TenantBootstrapper>> $classes */
        $classes = config('foundation.tenancy.bootstrappers', []);

        return $this->bootstrappers = array_map(
            fn (string $class): TenantBootstrapper => $this->container->make($class),
            $classes,
        );
    }
}
