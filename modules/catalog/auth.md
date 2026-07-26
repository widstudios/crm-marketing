# Modulo — auth

> Chi entra, con quale identità, e che cosa gli è consentito fare.

| | |
|---|---|
| **Nome** | `auth` |
| **Categoria** | base — sempre attivo, non disattivabile |
| **Dipende da** | `tenancy` |

---

## Indice

1. [Descrizione](#descrizione) 2. [Che cosa fornisce](#che-cosa-fornisce)
3. [Che cosa non fa](#che-cosa-non-fa) 4. [Due mondi separati](#due-mondi-separati)
5. [Il modello dei permessi](#il-modello-dei-permessi) 6. [Configurazione](#configurazione)
7. [Integrazione](#integrazione) 8. [Adozione](#adozione) 9. [Esempi](#esempi)
10. [Best practice](#best-practice) 11. [Errori comuni](#errori-comuni) 12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

L'autenticazione in un sistema multitenant ha un requisito che altrove non esiste: **due popolazioni
di utenti che non devono mai potersi scambiare di posto**.

Gli utenti di piattaforma vivono nel landlord e amministrano il servizio. Gli utenti dei clienti
vivono nel database del proprio tenant e non esistono al di fuori di esso. Un percorso di codice che
permetta a un'identità di passare da un mondo all'altro è la falla più grave che questo sistema
possa avere, ed è il motivo per cui le guardie sono separate a livello di configurazione e non di
controlli.

---

## Che cosa fornisce

### Tabelle — landlord

| Tabella | Contenuto |
|---|---|
| `platform_users` | personale che amministra il servizio |
| `platform_roles`, `platform_permissions` | ruoli e permessi di piattaforma |
| `platform_access_grants` | accessi ai dati dei tenant, con scadenza |

### Tabelle — tenant

| Tabella | Contenuto |
|---|---|
| `users` | utenti del cliente |
| `roles`, `permissions`, e le pivot | modello di autorizzazione del cliente |
| `personal_access_tokens` | token API, come hash, con scadenza |
| `two_factor_secrets` | secondo fattore, cifrato |
| `login_attempts` | tentativi, per la limitazione |

### Permessi — tenant

| Permesso | Consente |
|---|---|
| `user.view` · `user.create` · `user.update` · `user.delete` | gestione degli utenti |
| `role.view` · `role.assign` | gestione dei ruoli |
| `token.create` · `token.revoke` | token API |

### Comandi

| Comando | Quando |
|---|---|
| `auth:create-platform-user` | primo accesso di piattaforma |
| `auth:sync-permissions` | dopo un deploy che introduce permessi |
| `auth:revoke-stale-tokens --days=90` | esercizio mensile |

### Eventi

| Evento | Emesso quando |
|---|---|
| `UserInvited` · `UserActivated` · `UserDeactivated` | ciclo di vita dell'utente |
| `RoleAssigned` · `RoleRevoked` | cambio di privilegi |
| `TwoFactorEnabled` · `TwoFactorDisabled` | secondo fattore |
| `SuspiciousLoginDetected` | tentativi oltre la soglia |

---

## Che cosa non fa

| Non fa | Dove va cercato |
|---|---|
| Autorizzare le singole risorse | Policy del progetto: il modulo fornisce i permessi, non le decisioni |
| Definire i ruoli del dominio | progetto: «magazziniere» e «responsabile» sono termini del committente |
| Single sign-on, SAML, LDAP | non previsto: dipende dall'infrastruttura del cliente |
| Federazione tra tenant | deliberatamente assente: vedi sotto |
| Gestire le sessioni del pannello Filament | Filament, con le guardie configurate qui |
| Interfaccia di consultazione degli accessi | progetto, o pannello di piattaforma |

**Federazione tra tenant.** Un'identità unica che accede a più clienti sembra comoda e sarebbe il
modo più diretto di rompere l'isolamento: un errore di autorizzazione diventerebbe accesso ai dati
di un altro cliente. Chi lavora per due clienti ha due account, ed è la risposta corretta.

---

## Due mondi separati

```
landlord                                tenant (uno per cliente)
────────────────────────────            ────────────────────────────
platform_users                          users
guardia: 'platform'                     guardia: 'tenant'
sessione: dominio di piattaforma        sessione: dominio del cliente

                     nessun percorso di passaggio
```

| Aspetto | Piattaforma | Tenant |
|---|---|---|
| Guardia | `platform` | `tenant` |
| Provider | `platform_users` (landlord) | `users` (tenant) |
| Dominio | quello centrale | quello del cliente |
| Secondo fattore | obbligatorio | obbligatorio per gli amministratori |

L'accesso del personale ai dati di un cliente **non** avviene diventando un utente del cliente:
avviene con una concessione a scadenza, tracciata nell'audit del tenant e notificata al tenant
admin. La differenza è che il cliente può vederla.

---

## Il modello dei permessi

Il permesso è nella forma `<risorsa>.<azione>`: `batch.view`, `movement.create`,
`document.approve`.

**Non si controllano mai i nomi dei ruoli.** I ruoli cambiano — il committente introduce un secondo
tipo di responsabile — i permessi no. Un `if ($user->hasRole('manager'))` va riscritto quel giorno,
in tutti i punti in cui compare, e nessuno sa quanti siano.

```php
// ✗
if ($user->hasRole('manager')) { … }

// ✓
if ($user->can('batch.approve')) { … }
```

I ruoli sono **insiemi di permessi configurabili dal cliente**: sono dati, non codice.

Ruoli predefiniti creati dal seeder:

| Ruolo | Permessi |
|---|---|
| `tenant_admin` | tutti — riceve automaticamente ogni permesso nuovo |
| `operator` | operazioni quotidiane, nessuna configurazione |
| `viewer` | sola lettura |

`tenant_admin` riceve automaticamente i permessi nuovi a ogni deploy. Senza,
ogni funzionalità nuova è invisibile a tutti finché qualcuno non se ne accorge.

---

## Configurazione

```php
// config/auth-module.php
return [
    'password' => [
        // Lunghezza, non composizione forzata: la composizione forzata produce
        // password prevedibili, perché tutti risolvono il vincolo allo stesso modo.
        'min_length' => 12,
        'check_compromised' => true,
    ],

    'two_factor' => [
        'required_for_roles' => ['tenant_admin'],
        // SMS non ammesso: intercettabile con lo scambio di SIM, e la
        // vittima non se ne accorge.
        'channels' => ['totp'],
    ],

    'throttle' => [
        'per_account' => 5,
        'per_ip' => 20,
        'decay_minutes' => 15,
    ],

    'tokens' => [
        'max_lifetime_days' => 365,
        'revoke_unused_after_days' => 90,
    ],
];
```

---

## Integrazione

```php
// Le Policy del progetto verificano i permessi forniti dal modulo
final class BatchPolicy
{
    public function approve(User $user, Batch $batch): bool
    {
        return $user->can('batch.approve')
            && $batch->status->canTransitionTo(BatchStatus::Approved);
    }
}
```

I permessi di ogni modulo si dichiarano nel modulo; il seeder di sistema li raccoglie dal registro e
li assegna al ruolo amministratore.

---

## Adozione

```bash
php artisan migrate --database=landlord
php artisan tenants:migrate
php artisan auth:create-platform-user
php artisan tenants:artisan "auth:sync-permissions"
```

---

## Esempi

### Messaggi che non rivelano nulla

```php
// ✗ Dice a chi prova indirizzi quali esistono
return back()->withErrors(['email' => 'Nessun utente con questo indirizzo.']);

// ✓ Identico nei due casi
return back()->withErrors(['email' => __('auth.failed')]);
```

Vale anche per il recupero password: la risposta è la stessa che l'indirizzo esista o no.

### Verificare entrambi, sulle API

```php
// Il token può essere stato emesso prima che i permessi venissero revocati:
// verificare solo le abilità del token lascerebbe l'accesso attivo.
if (! $request->user()->tokenCan('batch:write') || ! $request->user()->can('batch.create')) {
    abort(403);
}
```

---

## Best practice

- Verificare i permessi, mai i nomi dei ruoli.
- Assegnare i permessi nuovi al ruolo amministratore nel seeder, a ogni deploy.
- Secondo fattore obbligatorio per chi amministra; TOTP, mai SMS.
- Token con scadenza, archiviati come hash, mostrati una sola volta.
- Rigenerare l'identificatore di sessione al login e a ogni cambio di privilegio.
- Riesaminare mensilmente token e accessi di piattaforma; revocare ciò che non serve.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Controllo sul nome del ruolo | Da riscrivere al primo ruolo nuovo | Verificare il permesso |
| Permessi non assegnati al ruolo admin | Funzionalità invisibile dopo il deploy | `syncPermissions` nel seeder |
| Guardie non separate | Un'identità passa da un mondo all'altro | Guardie e provider distinti |
| Messaggio che rivela l'esistenza | Enumerazione degli account | Messaggio unico |
| Composizione forzata delle password | Password prevedibili | Lunghezza e verifica dei compromessi |
| SMS come secondo fattore | Scambio di SIM, vittima ignara | TOTP |
| Token senza scadenza | Accesso permanente da un'integrazione dismessa | Scadenza e revoca |
| Solo abilità del token verificate | Permessi revocati ma token valido | Verificare entrambi |
| Sessione non rigenerata al login | Fissazione di sessione | `session()->regenerate()` |
| Accesso di assistenza non notificato | Il cliente non sa chi ha visto i suoi dati | Audit del tenant + notifica |

---

## Checklist

- [ ] Guardie e provider separati per piattaforma e tenant.
- [ ] Nessun controllo su nomi di ruolo nel codice.
- [ ] Il seeder assegna i permessi nuovi al ruolo amministratore.
- [ ] Secondo fattore obbligatorio per gli amministratori, TOTP.
- [ ] Limitazione dei tentativi attiva, per account e per IP.
- [ ] I messaggi di errore non rivelano l'esistenza degli account.
- [ ] I token hanno scadenza, sono hash e sono revocabili.
- [ ] Le API verificano abilità del token **e** permessi dell'utente.
- [ ] Gli accessi di piattaforma sono a scadenza, tracciati e notificati.

---

## Riferimenti

- [Autenticazione](../../architecture/08-authentication.md) · [Autorizzazione](../../architecture/09-authorization-roles-permissions.md)
- [ADR-0006](../../architecture/decisions/0006-permission-model.md)
- [Policies](../../rules/policies.md) · [Sicurezza](../../rules/security.md)
- [Checklist di sicurezza](../../checklists/security-checklist.md)
