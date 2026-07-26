# Regole — Middleware

> La catena di elaborazione della richiesta: ordine, responsabilità, e il middleware che non si
> dimentica mai.

---

## Indice

1. [Descrizione](#descrizione)
2. [Ordine della catena](#ordine-della-catena)
3. [Regole](#regole)
4. [Middleware standard](#middleware-standard)
5. [Middleware di progetto](#middleware-di-progetto)
6. [Esempi](#esempi)
7. [Best practice](#best-practice)
8. [Errori comuni](#errori-comuni)
9. [Checklist](#checklist)
10. [Riferimenti](#riferimenti)

---

## Descrizione

Il middleware è il posto giusto per ciò che riguarda **tutte** le richieste di un gruppo: risoluzione
del contesto, sicurezza, tracciamento. È il posto sbagliato per la logica di business.

In questa architettura il middleware ha un ruolo critico: la risoluzione del tenant.

---

## Ordine della catena

```
1. Sicurezza di trasporto      HTTPS, header di sicurezza
2. Tracciamento                identificatore di richiesta, contesto di log
3. Sessione                    avvio, cookie
4. Protezione CSRF             per le rotte web
5. Risoluzione del tenant      contesto e bootstrap dei servizi
6. Autenticazione              guardia del contesto
7. Verifica dello stato        tenant attivo, utente abilitato
8. Limitazione del traffico    per token e per tenant
9. Localizzazione              lingua dell'utente o del tenant
```

**R1.** La risoluzione del tenant precede l'autenticazione.
*Motivo:* gli utenti vivono nel database del tenant: senza contesto non è possibile autenticarli.
*Verifica:* configurazione, test. *Livello: vincolante.*

**R2.** La verifica dello stato del tenant precede l'autenticazione.
*Motivo:* un tenant sospeso non deve nemmeno permettere il tentativo di accesso.
*Verifica:* test.

**R3.** Il tracciamento precede tutto il resto.
*Motivo:* anche gli errori nei middleware successivi devono essere correlabili.
*Verifica:* configurazione.

---

## Regole

**R4.** Un middleware, una responsabilità.
*Verifica:* revisione.

**R5.** Nessuna logica di business nel middleware.
*Motivo:* non è invocabile da CLI e code, e non è testabile in isolamento.
*Verifica:* revisione. *Livello: vincolante.*

**R6.** Nessuna query costosa nel middleware.
*Motivo:* viene eseguito ad ogni richiesta. *Verifica:* test sul numero di query.

**R7.** Il middleware non modifica il corpo della risposta, salvo quando è il suo scopo dichiarato
(compressione, header).
*Verifica:* revisione.

**R8.** Ogni middleware di progetto ha un test di feature.
*Verifica:* copertura.

**R9.** Ogni rotta che tocca dati di dominio ha il middleware `tenant`.
*Verifica:* script di verifica. *Livello: assoluto.*

**R10.** I gruppi di middleware sono dichiarati una volta e riusati; nessuna elencazione ripetuta
rotta per rotta.
*Verifica:* revisione.

---

## Middleware standard

| Middleware | Gruppo | Scopo |
|---|---|---|
| `AssignRequestId` | tutti | identificatore di richiesta e contesto di log |
| `ResolveTenant` | `tenant` | risoluzione del contesto e bootstrap dei servizi |
| `EnsureTenantIsActive` | `tenant` | blocca tenant sospesi o in dismissione |
| `SetLocale` | tutti | lingua da utente o tenant |
| `SecurityHeaders` | `web` | CSP, HSTS, X-Frame-Options |
| `ThrottleByTenant` | `api` | limitazione per tenant oltre che per token |
| `LogSlowRequests` | tutti | registra le richieste oltre soglia |

---

## Middleware di progetto

```php
final class EnsureTenantIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = TenantContext::current();

        if ($tenant === null) {
            return $next($request);   // contesto landlord
        }

        return match ($tenant->status) {
            TenantStatus::Active => $next($request),
            TenantStatus::Suspended => response()->view('errors.tenant-suspended', [
                'reason' => $tenant->suspension_reason,
            ], 403),
            TenantStatus::Migrating => response()->view('errors.tenant-migrating', [], 503)
                ->header('Retry-After', '300'),
            TenantStatus::Terminating => response()->view('errors.tenant-terminating', [], 403),
            default => abort(404),
        };
    }
}
```

**R11.** I middleware di stato restituiscono pagine esplicative, non errori generici.
*Motivo:* l'utente deve sapere perché non può accedere. *Verifica:* revisione.

**R12.** I middleware che possono negare l'accesso registrano il motivo.
*Verifica:* revisione.

---

## Esempi

### Esempio 1 — gruppi conformi

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->web(append: [
        AssignRequestId::class,
        SecurityHeaders::class,
        SetLocale::class,
    ]);

    $middleware->group('tenant', [
        ResolveTenant::class,
        EnsureTenantIsActive::class,
    ]);

    $middleware->api(append: [
        AssignRequestId::class,
        ThrottleByTenant::class,
    ]);
})
```

```php
// routes/tenant.php
Route::middleware(['web', 'tenant', 'auth:tenant'])->group(function (): void {
    // rotte del contesto tenant
});
```

### Esempio 2 — violazioni

```php
final class CheckStockLevels                      // ✗ R5: logica di business
{
    public function handle(Request $request, Closure $next): Response
    {
        // ✗ R6: query costosa ad ogni richiesta
        $expiring = Batch::where('expiry_date', '<', now()->addDays(30))->count();

        if ($expiring > 0) {
            session()->flash('warning', "{$expiring} lotti in scadenza");
        }

        return $next($request);
    }
}
```

L'informazione appartiene a un widget di dashboard, non a un middleware eseguito su ogni richiesta.

---

## Best practice

- Verificare l'ordine della catena con un test che simuli un tenant sospeso.
- Tenere i middleware brevi: oltre venti righe, probabilmente contengono logica.
- Registrare il motivo di ogni rifiuto: semplifica la diagnosi delle segnalazioni.
- Usare i gruppi invece di elencare i middleware rotta per rotta.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Autenticazione prima della risoluzione del tenant | Utenti non trovati | Ordine corretto |
| Rotta di dominio senza `tenant` | Accesso fuori contesto, rischio di fuga | Middleware obbligatorio |
| Logica di business nel middleware | Non invocabile da CLI, non testabile | Spostare nel dominio |
| Query costosa nel middleware | Ogni richiesta rallentata | Spostare dove serve |
| Errore generico su tenant sospeso | L'utente non capisce | Pagina esplicativa |
| Middleware elencati rotta per rotta | Dimenticanze | Gruppi |
| Middleware senza test | Comportamento non verificato | Test di feature |

---

## Checklist

- [ ] Ordine della catena conforme.
- [ ] Risoluzione del tenant prima dell'autenticazione.
- [ ] Verifica dello stato del tenant prima dell'autenticazione.
- [ ] Ogni rotta di dominio ha il middleware `tenant`.
- [ ] Un middleware, una responsabilità.
- [ ] Nessuna logica di business, nessuna query costosa.
- [ ] Pagine esplicative sui rifiuti di stato.
- [ ] Ogni middleware di progetto ha un test.
- [ ] Middleware organizzati in gruppi.

---

## Riferimenti

- [Risoluzione del tenant](../architecture/06-tenant-resolution.md)
- [Livello di presentazione](../architecture/15-presentation-layer.md)
- [Laravel](laravel.md) · [Sicurezza](security.md) · [i18n](i18n.md)
