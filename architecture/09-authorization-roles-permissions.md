# Autorizzazione, ruoli e permessi

> Il modello di accesso: permessi granulari, ruoli come raggruppamento, Policy come decisione,
> deny by default ovunque.

---

## Indice

1. [Descrizione](#descrizione)
2. [I tre livelli del modello](#i-tre-livelli-del-modello)
3. [Permessi](#permessi)
4. [Ruoli](#ruoli)
5. [Policy](#policy)
6. [Il super admin](#il-super-admin)
7. [Permessi e stato](#permessi-e-stato)
8. [Verifica nei diversi punti di ingresso](#verifica-nei-diversi-punti-di-ingresso)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Il modello di autorizzazione ha un obiettivo pratico: rendere **impossibile dimenticare** un
controllo di accesso. Non «difficile»: impossibile per costruzione, o almeno rilevabile da un test.

Il principio guida è deny by default: ciò che non è esplicitamente permesso è negato. Il modello
opposto — permesso salvo divieto — produce una falla ogni volta che qualcuno dimentica un
controllo, e qualcuno dimentica sempre.

---

## I tre livelli del modello

```
PERMESSO          l'unità atomica: `supplier.archive`
    │             assegnabile, verificabile, stabile nel tempo
    ▼
RUOLO             raggruppamento di permessi: `warehouse_manager`
    │             comodità di assegnazione, non entità di dominio
    ▼
POLICY            la decisione: permesso + stato + contesto
                  «può archiviare questo fornitore, adesso?»
```

| Livello | Domanda | Dove vive |
|---|---|---|
| Permesso | l'utente ha il diritto in generale? | tabella `permissions` nel tenant |
| Ruolo | quali permessi ha questo profilo? | tabella `roles` nel tenant |
| Policy | l'operazione è ammessa su **questa** risorsa, **adesso**? | `app/Policies/` |

L'errore concettuale ricorrente è controllare il **ruolo** nel codice (`if ($user->hasRole('admin'))`).
I ruoli cambiano — un cliente ne vuole uno nuovo, un altro li chiama diversamente — mentre i
permessi restano stabili.

---

## Permessi

Convenzione di nome: `<risorsa>.<azione>`, in inglese, minuscolo.

| Permesso | Significato |
|---|---|
| `supplier.view` | vedere i fornitori |
| `supplier.create` | crearne di nuovi |
| `supplier.update` | modificarli |
| `supplier.archive` | archiviarli |
| `movement.create` | registrare movimenti |
| `movement.delete` | eliminare movimenti |
| `report.stock` | accedere al report giacenze |
| `settings.manage` | modificare la configurazione del tenant |

Regole:

- I permessi sono definiti in `config/permissions.php` e seminati da un seeder idempotente.
- Ogni nuova funzionalità **aggiunge il proprio permesso nello stesso commit**, e lo assegna al
  ruolo amministratore.
- I permessi non si rimuovono: si deprecano, altrimenti le assegnazioni esistenti diventano
  orfane.
- La granularità segue le operazioni reali, non le tabelle: se nessuno distingue «modificare» da
  «archiviare», un permesso solo.

---

## Ruoli

| Ruolo standard | Ambito | Contenuto |
|---|---|---|
| `tenant_admin` | tenant | tutti i permessi del tenant |
| `manager` | tenant | operatività completa, senza configurazione |
| `operator` | tenant | operatività quotidiana |
| `viewer` | tenant | sola lettura |
| `super_admin` | landlord | piattaforma |
| `support` | landlord | assistenza, accesso tracciato ai tenant |

I ruoli sono **modificabili dal tenant**: un cliente può crearne di propri combinando i permessi
esistenti. Per questo il codice non deve mai dipendere dal nome di un ruolo.

`tenant_admin` riceve automaticamente i permessi nuovi introdotti da ogni rilascio: è la riga di
seeder che, se manca, rende invisibili le nuove funzionalità.

---

## Policy

La Policy è il punto in cui si decide. Combina permesso, stato della risorsa e contesto.

```php
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

    public function archive(User $user, Supplier $supplier): bool
    {
        return $user->can('supplier.archive')
            && $supplier->status->canTransitionTo(SupplierStatus::Archived);
    }
}
```

| Regola | Motivo |
|---|---|
| Ogni model ha la sua Policy | nessuna risorsa senza regole |
| Nessun metodo ritorna `true` senza permesso esplicito | deny by default |
| La Policy considera lo stato, non solo il permesso | «può in generale» ≠ «può adesso» |
| Copertura di test al **100%** | è la superficie di sicurezza |
| Nessuna query costosa dentro la Policy | viene invocata molte volte per richiesta |

Non serve verificare l'appartenenza al tenant: la risorsa proviene dal database del tenant
corrente, quindi appartiene già a chi sta chiedendo.

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

Tre vincoli su questa scorciatoia:

1. Vale **solo** per il super admin di piattaforma, mai per il `tenant_admin`.
2. Ogni uso è registrato nell'audit.
3. Non si estende alle operazioni distruttive, che richiedono comunque conferma esplicita.

`Gate::before` è potente e comodo: senza registro, diventerebbe il modo in cui i controlli si
aggirano senza lasciare traccia.

---

## Permessi e stato

La distinzione che rende il modello utile:

| Domanda | Risponde |
|---|---|
| «Questo utente può archiviare fornitori?» | il **permesso** |
| «Questo fornitore può essere archiviato adesso?» | lo **stato** (enum di dominio) |
| «Questo utente può archiviare questo fornitore adesso?» | la **Policy**, che combina i due |

Tenere separate le tre domande evita due errori speculari: mettere le regole di stato nei permessi
(che diventano ingestibili) o mettere i permessi nel dominio (che diventa dipendente
dall'autenticazione).

---

## Verifica nei diversi punti di ingresso

| Punto di ingresso | Come si verifica | Se manca |
|---|---|---|
| Controller | `$this->authorize('update', $supplier)` | operazione aperta |
| Form Request | `authorize()` | validazione senza controllo di accesso |
| Filament Resource | Policy automatica | — |
| Azione Filament | `->authorize(...)` **esplicito** | **azione aperta a chiunque veda la pagina** |
| Componente Livewire | verifica nei metodi pubblici | ogni metodo pubblico è un endpoint |
| Comando Artisan | verifica esplicita o esecuzione riservata | comando senza controlli |
| Job | eredita dal chiamante | nessuna verifica autonoma |
| API | `authorize()` + abilità del token | operazione aperta |

Le due righe evidenziate sono le fonti più comuni di falle: sono i punti in cui il framework
**non** applica automaticamente una Policy.

---

## Esempi

### Esempio 1 — permesso contro ruolo

```php
// ✗ Dipende dal nome del ruolo, che il cliente può cambiare
if ($user->hasRole('warehouse_manager')) {
    // …
}

// ✓ Dipende dal permesso, stabile
if ($user->can('movement.create')) {
    // …
}
```

Nel primo caso, un cliente che rinomina il ruolo in «Responsabile Magazzino» rompe la funzionalità.

### Esempio 2 — permesso nuovo introdotto correttamente

Una nuova funzionalità «esporta movimenti» richiede, nello stesso commit:

```php
// config/permissions.php
'movement.export',
```

```php
// Policy
public function export(User $user): bool
{
    return $user->can('movement.export');
}
```

```php
// Azione Filament
Action::make('export')->authorize(fn (): bool => auth()->user()->can('movement.export'));
```

```php
// Test
it('nega l\'esportazione senza permesso', function (): void {
    $this->actingAs(userWithoutPermissions())
        ->post('/admin/movements/export')
        ->assertForbidden();
});
```

Il seeder assegna il nuovo permesso a `tenant_admin`: senza, la funzionalità è invisibile a tutti
dopo il deploy.

---

## Best practice

- Verificare i permessi, mai i nomi dei ruoli.
- Ogni funzionalità porta il proprio permesso nello stesso commit.
- La Policy combina permesso e stato.
- Copertura di test al 100% sulle Policy.
- Autorizzazione esplicita nelle azioni Filament e nei metodi Livewire.
- Registrare ogni uso della scorciatoia del super admin.
- Deprecare i permessi, non rimuoverli.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Controllo sul nome del ruolo | Si rompe quando il cliente lo rinomina | Verificare il permesso |
| Policy che ritorna `true` per default | Autorizzazione permissiva | Deny by default |
| Azione Filament senza `authorize()` | Aperta a chiunque veda la pagina | Autorizzazione esplicita |
| Metodo Livewire senza verifica | Endpoint aperto | Verifica nei metodi pubblici |
| Permesso non seminato | Funzionalità invisibile dopo il deploy | Seeder nello stesso commit |
| `Gate::before` senza audit | Controlli aggirati senza traccia | Registro obbligatorio |
| Regole di stato dentro i permessi | Permessi ingestibili | Stato nel dominio |
| Nessun test di autorizzazione negata | La falla non emerge | Test obbligatorio |

---

## Checklist

- [ ] Ogni model ha una Policy con deny by default.
- [ ] Nessun controllo dipende dal nome di un ruolo.
- [ ] Ogni funzionalità ha il proprio permesso, seminato e assegnato.
- [ ] Le Policy considerano permesso **e** stato.
- [ ] Copertura al 100% sulle Policy.
- [ ] Ogni azione Filament ha `->authorize()`.
- [ ] Ogni metodo pubblico Livewire verifica l'autorizzazione.
- [ ] `Gate::before` registra ogni uso.
- [ ] Esiste un test di autorizzazione negata per ogni operazione.

---

## Riferimenti

- [Autenticazione](08-authentication.md)
- [Regole Policies](../rules/policies.md) · [Sicurezza](../rules/security.md)
- [Guida alla sicurezza](../docs/04-quality/05-security-guide.md)
- [ADR-0006 — Modello dei permessi](decisions/0006-permission-model.md)
- [Modulo auth](../modules/catalog/auth.md)
