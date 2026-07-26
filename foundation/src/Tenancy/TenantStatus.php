<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Tenancy;

/**
 * Stati ammessi per un tenant, con le transizioni possibili.
 *
 * Lo stato non e' una stringa in una colonna: e' una macchina a stati. Il
 * metodo canTransitionTo() e' l'unico posto in cui la sequenza e' scritta, e
 * di conseguenza l'unico da modificare quando cambia.
 */
enum TenantStatus: string
{
    /** Database in creazione: nessun accesso, nessuna elaborazione. */
    case Provisioning = 'provisioning';

    /** Operativo. */
    case Active = 'active';

    /** Accesso sospeso, dati intatti: morosita', richiesta del cliente, incidente. */
    case Suspended = 'suspended';

    /** Fuori servizio, conservato per il periodo di ritenzione dichiarato. */
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Provisioning => 'In creazione',
            self::Active => 'Attivo',
            self::Suspended => 'Sospeso',
            self::Archived => 'Archiviato',
        };
    }

    /**
     * Se il tenant puo' ricevere traffico applicativo.
     */
    public function allowsAccess(): bool
    {
        return $this === self::Active;
    }

    /**
     * Se il tenant deve essere incluso in migration, backup ed elaborazioni
     * pianificate.
     *
     * Un tenant sospeso continua a essere migrato e salvato: la sospensione
     * riguarda l'accesso, non i dati.
     */
    public function allowsProcessing(): bool
    {
        return $this === self::Active || $this === self::Suspended;
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Provisioning => [self::Active, self::Archived],
            self::Active => [self::Suspended, self::Archived],
            self::Suspended => [self::Active, self::Archived],
            self::Archived => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), strict: true);
    }
}
