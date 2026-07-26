# Regole — Policy e autorizzazione

> Deny by default, permessi granulari, stato della risorsa. Le Policy sono la superficie di
> sicurezza dell'applicazione.

---

## Indice

1. [Descrizione](#descrizione)
2. [Regole](#regole)
3. [Anatomia di una Policy](#anatomia-di-una-policy)
4. [Dove si autorizza](#dove-si-autorizza)
5. [Il super admin](#il-super-admin)
6. [Test obbligatori](#test-obbligatori)
7. [Esempi](#esempi)
8. [Best practice](#best-practice)
9. [Errori comuni](#errori-comuni)
10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

Una Policy risponde a una domanda precisa: *questo utente può compiere questa azione su questa
risorsa, adesso?* Le tre parti contano tutte: l'utente (permesso), l'azione, la risorsa nel suo
stato attuale.

Decisione di riferimento: [ADR-0006](../architecture/decisions/0006-permission-model.md).

---

## Regole

**R1.** Ogni model ha una Policy registrata.
*Motivo:* nessuna risorsa senza regole di accesso. *Verifica:* script di verifica.
*Livello: vincolante.*

**R2.** Ogni metodo di Policy **nega** in assenza di un permesso esplicito. Nessun `return true`
finale.
*Motivo:* la dimenticanza di un controllo deve produrre un rifiuto, non un accesso.
*Verifica:* revisione, test di rifiuto. *Livello: assoluto.*

**R3.** La Policy verifica **permessi**, mai nomi di ruolo.
*Motivo:* i ruoli sono modificabili dal cliente. *Verifica:* ricerca in CI su `hasRole`.

**R4.** La Policy considera anche lo **stato** della risorsa quando il dominio lo prevede.

```php
public function update(User $user, Supplier $supplier): bool
{
    return $user->can('supplier.update')
        && $supplier->status !== SupplierStatus::Archived;
}
```

*Motivo:* «può modificare in generale» non significa «può modificare questo, adesso».
*Verifica:* revisione.

**R5.** La Policy non verifica l'appartenenza al tenant.
*Motivo:* la risorsa proviene dal database del tenant corrente: appartiene già a chi chiede.
*Verifica:* revisione.

**R6.** Nessuna query costosa dentro una Policy.
*Motivo:* viene invocata molte volte per richiesta, anche per riga di un elenco.
*Verifica:* test sul numero di query negli elenchi.

**R7.** Le transizioni di stato ammesse stanno nell'**enum di dominio**, non nella Policy.

```php
public function archive(User $user, Supplier $supplier): bool
{
    return $user->can('supplier.archive')
        && $supplier->status->canTransitionTo(SupplierStatus::Archived);   // regola nel dominio
}
```

*Verifica:* revisione.

**R8.** Copertura di test al **100%**, con almeno un caso di rifiuto per ogni metodo.
*Verifica:* pipeline con filtro sul namespace `Policies`.

**R9.** I metodi seguono i nomi standard di Laravel (`viewAny`, `view`, `create`, `update`,
`delete`, `restore`, `forceDelete`) più quelli specifici del dominio.
*Verifica:* revisione.

**R10.** Le Policy non contengono logica di business oltre alla verifica di stato.
*Verifica:* revisione.

---

## Anatomia di una Policy

```php
<?php

declare(strict_types=1);

namespace App\Policies;

final readonly class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('supplier.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->can('supplier.view');
    }

    public function create(User $user): bool
    {
        return $user->can('supplier.create');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->can('supplier.update')
            && $supplier->status !== SupplierStatus::Archived;
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->can('supplier.delete')
            && ! $supplier->hasMovements();     // metodo di dominio, non query nella Policy
    }

    public function archive(User $user, Supplier $supplier): bool
    {
        return $user->can('supplier.archive')
            && $supplier->status->canTransitionTo(SupplierStatus::Archived);
    }
}
```

Nota su `hasMovements()`: è un metodo di dominio sull'entità, che usa una relazione già caricata o
un contatore denormalizzato. Se richiedesse una query per riga, violerebbe R6.

---

## Dove si autorizza

| Punto di ingresso | Come | Automatico |
|---|---|---|
| Controller | `$this->authorize('update', $supplier)` | no |
| Form Request | metodo `authorize()` | no |
| Filament Resource (CRUD) | Policy applicata dal framework | **sì** |
| Filament, azione personalizzata | `->authorize(...)` | **no** |
| Componente Livewire | verifica in ogni metodo pubblico | **no** |
| API | `authorize()` + `tokenCan()` | no |
| Comando Artisan | verifica esplicita o esecuzione riservata | no |
| Job | eredita dal chiamante | — |

**R11.** Ogni azione personalizzata di Filament ha `->authorize()`.
*Motivo:* non eredita alcun controllo: senza, è accessibile a chiunque veda la pagina.
*Verifica:* script di verifica. *Livello: vincolante.*

**R12.** Ogni metodo pubblico di un componente Livewire autorizza.
*Motivo:* ogni metodo pubblico è un endpoint raggiungibile dal browser.
*Verifica:* revisione.

---

## Il super admin

```php
Gate::before(function (User $user, string $ability): ?bool {
    if (! $user->isSuperAdmin()) {
        return null;   // prosegue con le Policy
    }

    Log::channel('audit')->info('Super admin bypass', [
        'user_id' => $user->id,
        'ability' => $ability,
        'tenant' => tenant()?->slug,
    ]);

    return true;
});
```

**R13.** `Gate::before` vale solo per il super admin di **piattaforma**, mai per il `tenant_admin`.
*Verifica:* revisione.

**R14.** Ogni uso della scorciatoia è registrato nell'audit.
*Motivo:* senza registro, i controlli si aggirano senza traccia.
*Verifica:* revisione. *Livello: vincolante.*

**R15.** La scorciatoia non si applica alle operazioni distruttive, che richiedono comunque
conferma esplicita.
*Verifica:* revisione.

---

## Test obbligatori

Per ogni metodo di Policy servono almeno due test:

```php
it('permette la modifica con il permesso', function (): void {
    $user = userWithPermission('supplier.update');
    $supplier = Supplier::factory()->create(['status' => SupplierStatus::Active]);

    expect($user->can('update', $supplier))->toBeTrue();
});

it('nega la modifica senza il permesso', function (): void {
    $user = userWithoutPermissions();
    $supplier = Supplier::factory()->create();

    expect($user->can('update', $supplier))->toBeFalse();
});

it('nega la modifica di un fornitore archiviato', function (): void {
    $user = userWithPermission('supplier.update');
    $supplier = Supplier::factory()->archived()->create();

    expect($user->can('update', $supplier))->toBeFalse();
});
```

Il terzo test verifica R4: è quello che si dimentica, e quello che protegge dalle operazioni su
risorse in stato non ammesso.

---

## Esempi

### Esempio 1 — Policy permissiva da correggere

```php
// ✗ Chi non rientra nei casi previsti può fare tutto
public function update(User $user, Supplier $supplier): bool
{
    if ($supplier->status === SupplierStatus::Archived) {
        return false;
    }

    return true;      // ✗ R2
}
```

```php
// ✓ Serve un permesso esplicito
public function update(User $user, Supplier $supplier): bool
{
    return $user->can('supplier.update')
        && $supplier->status !== SupplierStatus::Archived;
}
```

### Esempio 2 — azione Filament senza autorizzazione

```php
// ✗ Accessibile a chiunque veda la pagina
Action::make('exportAll')
    ->action(fn () => app(ExportMovementsAction::class)->execute());

// ✓ Autorizzazione esplicita
Action::make('exportAll')
    ->authorize(fn (): bool => auth()->user()->can('movement.export'))
    ->action(fn () => app(ExportMovementsAction::class)->execute());
```

---

## Best practice

- Scrivere la Policy **insieme** al model, non dopo.
- Aggiungere il permesso al seeder e al ruolo amministratore nello stesso commit.
- Scrivere per primo il test di rifiuto.
- Tenere le Policy brevi: se serve logica, sta nel dominio.
- Verificare il numero di query prodotte dalle Policy sugli elenchi.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| `return true` finale | Autorizzazione permissiva | Deny by default |
| Controllo sul nome del ruolo | Si rompe quando il cliente lo rinomina | Verificare il permesso |
| Nessuna verifica di stato | Operazioni su risorse in stato non ammesso | Permesso + stato |
| Query dentro la Policy | N+1 sugli elenchi | Metodi di dominio o contatori |
| Azione Filament senza `authorize()` | Operazione aperta a tutti | Autorizzazione esplicita |
| Metodo Livewire senza verifica | Endpoint aperto | Verifica in ogni metodo pubblico |
| Verifica del tenant nella Policy | Ridondante, segnala fraintendimento | Il tenant è il database |
| `Gate::before` senza audit | Controlli aggirati senza traccia | Registro obbligatorio |
| Nessun test di rifiuto | La falla non emerge | Test obbligatorio |

---

## Checklist

- [ ] Ogni model ha una Policy registrata.
- [ ] Nessun metodo ritorna `true` senza permesso esplicito.
- [ ] Nessun controllo su nomi di ruolo.
- [ ] Lo stato della risorsa è considerato dove il dominio lo prevede.
- [ ] Le transizioni ammesse stanno nell'enum di dominio.
- [ ] Nessuna query costosa dentro le Policy.
- [ ] Ogni azione Filament personalizzata ha `->authorize()`.
- [ ] Ogni metodo pubblico Livewire autorizza.
- [ ] Copertura al 100%, con test di rifiuto per ogni metodo.
- [ ] `Gate::before` registra ogni uso.

---

## Riferimenti

- [ADR-0006](../architecture/decisions/0006-permission-model.md)
- [Autorizzazione, ruoli e permessi](../architecture/09-authorization-roles-permissions.md)
- [Sicurezza](security.md) · [Testing](testing.md) · [Filament](filament.md)
- [Template Policy](../templates/backend/README.md)
