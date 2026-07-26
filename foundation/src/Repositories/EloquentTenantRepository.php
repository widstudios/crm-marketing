<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use WidStudios\Foundation\Contracts\Tenancy\Tenant;
use WidStudios\Foundation\Contracts\Tenancy\TenantRepository;
use WidStudios\Foundation\Exceptions\TenantNotFound;
use WidStudios\Foundation\Tenancy\TenantStatus;

/**
 * Implementazione Eloquent del repository dei tenant, sul database di
 * piattaforma.
 *
 * Il progetto la estende dichiarando il proprio model:
 *
 *     final class TenantRepository extends EloquentTenantRepository
 *     {
 *         protected function model(): string { return Tenant::class; }
 *     }
 *
 * La connessione e' forzata a quella del landlord in ogni query, e non lasciata
 * alla connessione predefinita: dentro un contesto tenant la predefinita punta
 * al database del cliente, dove la tabella dei tenant non esiste. E' l'unico
 * repository della Foundation autorizzato a leggere dal landlord.
 */
abstract class EloquentTenantRepository implements TenantRepository
{
    /**
     * @return class-string<Model&Tenant>
     */
    abstract protected function model(): string;

    /**
     * @return Builder<Model&Tenant>
     */
    protected function query(): Builder
    {
        return $this->model()::on((string) config('foundation.tenancy.landlord_connection'));
    }

    public function findBySlug(string $slug): ?Tenant
    {
        return $this->query()->where('slug', $slug)->first();
    }

    public function findBySlugOrFail(string $slug): Tenant
    {
        return $this->findBySlug($slug) ?? throw TenantNotFound::forSlug($slug);
    }

    /**
     * Il dominio si cerca nella tabella dedicata: un tenant puo' avere piu'
     * domini (il proprio, un alias storico, uno di prova), e modellarli come
     * colonna renderebbe impossibile aggiungerne uno senza una migration.
     */
    public function findByDomain(string $domain): ?Tenant
    {
        return $this->query()
            ->whereHas('domains', fn (Builder $query): Builder => $query->where('domain', strtolower($domain)))
            ->first();
    }

    /**
     * @return iterable<int, Tenant>
     */
    public function all(): iterable
    {
        return $this->query()->orderBy('slug')->cursor();
    }

    /**
     * @return iterable<int, Tenant>
     */
    public function active(): iterable
    {
        return $this->query()
            ->where('status', TenantStatus::Active->value)
            ->orderBy('slug')
            ->cursor();
    }

    /**
     * @param  positive-int  $size
     * @param  callable(iterable<int, Tenant>): void  $callback
     */
    public function chunk(int $size, callable $callback): void
    {
        $this->query()
            ->orderBy('id')
            ->chunkById($size, static fn ($tenants): mixed => $callback($tenants));
    }
}
