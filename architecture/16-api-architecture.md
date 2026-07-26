# Architettura delle API

> Come sono strutturate le interfacce programmatiche: superfici, versioni, autenticazione,
> contratti e compatibilità.

---

## Indice

1. [Descrizione](#descrizione)
2. [Le tre superfici](#le-tre-superfici)
3. [Struttura delle rotte](#struttura-delle-rotte)
4. [Autenticazione e contesto](#autenticazione-e-contesto)
5. [Versionamento](#versionamento)
6. [Contratto di risposta](#contratto-di-risposta)
7. [Limitazione del traffico](#limitazione-del-traffico)
8. [Webhook in uscita](#webhook-in-uscita)
9. [Documentazione](#documentazione)
10. [Esempi](#esempi)
11. [Best practice](#best-practice)
12. [Errori comuni](#errori-comuni)
13. [Checklist](#checklist)
14. [Riferimenti](#riferimenti)

---

## Descrizione

Le API hanno consumatori che non aggiorniamo noi. Una modifica incompatibile non è un fastidio: è
un'integrazione del cliente che smette di funzionare, spesso di notte, spesso senza che nessuno
capisca perché.

Da qui il principio guida: **le API si estendono, non si modificano**.

---

## Le tre superfici

| Superficie | Percorso | Contesto | Consumatori |
|---|---|---|---|
| **Tenant** | `/api/v1/...` | tenant | sistemi del cliente, app mobili |
| **Piattaforma** | `/api/platform/v1/...` | landlord | strumenti interni WidStudios |
| **Pubblica** | `/api/public/v1/...` | landlord | sito, moduli di contatto |

Le tre superfici hanno autenticazione, limiti e cicli di vita **indipendenti**: mescolarle
significherebbe far dipendere il ritmo di evoluzione dell'una da quello delle altre.

---

## Struttura delle rotte

```php
// routes/api-tenant.php
Route::middleware(['api', 'auth:sanctum', 'tenant', 'throttle:api-tenant'])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('batches', BatchController::class)->only(['index', 'show']);

        Route::post('batches/{batch}/movements', [MovementController::class, 'store'])
            ->name('batches.movements.store');

        Route::post('suppliers/{supplier}/suspension', [SupplierSuspensionController::class, 'store'])
            ->name('suppliers.suspension.store');
    });
```

Convenzioni:

| Elemento | Regola |
|---|---|
| Risorse | sostantivi al plurale, `kebab-case` |
| Operazioni non CRUD | sottorisorsa di stato (`/suspension`), non verbo nel percorso |
| Annidamento | massimo un livello |
| Nomi di rotta | prefisso `api.v<versione>.` |
| Middleware | sempre `auth:sanctum`, `tenant`, `throttle` |

---

## Autenticazione e contesto

```
richiesta ──▶ token Bearer ──▶ Sanctum: utente + tenant
                                    │
                                    ▼
                          contesto tenant impostato
                                    │
                                    ▼
                  abilità del token ∩ permessi dell'utente
```

Il tenant deriva **dal token**. Non esiste alcun parametro che lo modifichi: sarebbe una porta
aperta sui dati altrui.

```php
// Doppia verifica: cosa può fare il token, cosa può fare l'utente adesso
abort_unless($request->user()->tokenCan('movement.create'), 403);
$this->authorize('create', StockMovement::class);
```

Le abilità limitano l'integrazione al momento dell'emissione; i permessi riflettono lo stato
attuale dell'utente, che può essere cambiato dopo.

---

## Versionamento

| Situazione | Nuova versione? |
|---|---|
| Aggiunta di un campo in risposta | no |
| Aggiunta di un parametro facoltativo | no |
| Aggiunta di un endpoint | no |
| Rimozione o rinomina di un campo | **sì** |
| Cambio di tipo di un campo | **sì** |
| Nuovo parametro obbligatorio | **sì** |
| Cambio del significato di un campo | **sì** |
| Cambio di un codice di stato | **sì** |

Ciclo di vita di una versione:

```
v1 attiva ──▶ v2 rilasciata ──▶ v1 deprecata (header Deprecation + Sunset)
                                      │
                                      ├── 12 mesi di convivenza minima
                                      ▼
                                 v1 dismessa
```

```http
Deprecation: Sat, 01 Aug 2026 00:00:00 GMT
Sunset: Sun, 01 Aug 2027 00:00:00 GMT
Link: <https://docs.example.com/api/v2>; rel="successor-version"
```

I consumatori vanno avvisati **attivamente**, non solo tramite header: l'header lo legge chi
controlla i log, cioè quasi nessuno.

---

## Contratto di risposta

Successo, elemento singolo:

```json
{
    "data": {
        "id": 42,
        "type": "outbound",
        "quantity": "10.000",
        "occurred_at": "2026-07-25T14:30:00+02:00"
    }
}
```

Successo, collezione:

```json
{
    "data": [ /* … */ ],
    "meta": { "current_page": 1, "per_page": 25, "total": 340, "last_page": 14 },
    "links": { "first": "…", "prev": null, "next": "…", "last": "…" }
}
```

Errore (RFC 7807):

```json
{
    "type": "https://api.example.com/problems/insufficient-stock",
    "title": "Giacenza insufficiente",
    "status": 422,
    "detail": "Il lotto LOT-0042 ha una disponibilità di 5 unità, richieste 10.",
    "instance": "/api/v1/batches/42/movements",
    "errors": { "quantity": ["La quantità supera la disponibilità."] }
}
```

Il formato è **unico** su tutte le superfici: un consumatore scrive la gestione degli errori una
volta sola.

---

## Limitazione del traffico

| Ambito | Limite | Motivo |
|---|---|---|
| Per token | 300 richieste/minuto | uso normale di un'integrazione |
| Per tenant | 1.000 richieste/minuto | un cliente non degrada gli altri |
| Endpoint costosi | 10 richieste/minuto | report ed esportazioni |
| Autenticazione | 5 tentativi/minuto per indirizzo | forza bruta |
| API pubblica | 60 richieste/minuto per indirizzo | abuso |

Header sempre presenti: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After` sul `429`.

Il limite **per tenant** oltre che per token è la protezione che conta: un cliente con dieci
integrazioni non deve poter saturare la piattaforma.

---

## Webhook in uscita

Le API non sono solo in ingresso: un tenant può registrare endpoint da notificare.

| Aspetto | Regola |
|---|---|
| Payload | evento, identificatori, momento; mai dati completi |
| Firma | HMAC-SHA256 con segreto per endpoint |
| Ritentativi | 5 tentativi con attesa crescente, fino a 24 ore |
| Disattivazione | automatica dopo fallimenti persistenti, con notifica |
| Registro | ogni consegna registrata, riprovabile a mano |
| Timeout | 5 secondi |

```json
{
    "event": "movement.registered",
    "occurred_at": "2026-07-25T14:30:00+02:00",
    "data": { "movement_id": 1842, "batch_id": 42 }
}
```

Il payload contiene identificatori, non lo stato completo: il consumatore richiama l'API per i
dettagli, e ottiene lo stato **corrente** invece di una fotografia.

---

## Documentazione

OpenAPI 3.1, versionato nel repository, generato dal codice dove possibile.

Per endpoint: scopo, autenticazione, permessi e abilità richiesti, parametri, esempio di richiesta,
esempio di risposta, errori possibili, limiti.

Una API non documentata **non è rilasciabile**: il consumatore la scoprirebbe per tentativi e
finirebbe per dipendere da comportamenti non intenzionali, che poi non possiamo cambiare.

---

## Esempi

### Esempio 1 — estensione compatibile

Si aggiunge `warehouse_id` alla risposta dei movimenti. I consumatori esistenti ignorano il campo
nuovo. Nessuna nuova versione.

### Esempio 2 — modifica incompatibile gestita

`quantity` passa da numero a stringa per evitare perdite di precisione.

```
v1  continua a restituire un numero
v2  restituisce una stringa
v1  deprecata, con header e comunicazione ai consumatori
+12 mesi  v1 dismessa
```

---

## Best practice

- Superfici separate con cicli di vita indipendenti.
- Estendere invece di modificare.
- Tenant sempre dal token.
- Verificare abilità e permessi.
- Formato di errore unico.
- Limitazione per token e per tenant.
- Webhook con identificatori e firma.
- Documentare prima di rilasciare.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Tenant da parametro | Accesso ai dati altrui | Dal token |
| Modifica incompatibile senza versione | Integrazioni rotte senza preavviso | Nuova versione |
| Formati di errore diversi | Consumatori costretti a casi speciali | Formato unico |
| Nessuna paginazione | L'API smette di rispondere con la crescita | Paginazione obbligatoria |
| Solo abilità del token verificate | Permessi revocati ma token efficace | Verificare entrambi |
| Webhook con stato completo | Dati obsoleti e possibile fuga di informazioni | Solo identificatori |
| Deprecazione solo via header | Nessuno se ne accorge | Comunicazione attiva |

---

## Checklist

- [ ] Le tre superfici sono separate.
- [ ] Ogni rotta tenant ha `auth:sanctum`, `tenant`, `throttle`.
- [ ] Il tenant deriva dal token.
- [ ] Abilità e permessi verificati entrambi.
- [ ] Formato di risposta e di errore uniformi.
- [ ] Paginazione con lista bianca di filtri e ordinamenti.
- [ ] Limitazione per token e per tenant.
- [ ] Webhook firmati, con ritentativi e registro.
- [ ] Documentazione OpenAPI aggiornata.

---

## Riferimenti

- [Regole REST API](../rules/rest-api.md)
- [Sviluppare una API](../docs/03-development/04-api-development-guide.md)
- [Autenticazione](08-authentication.md) · [Autorizzazione](09-authorization-roles-permissions.md)
- [Checklist API](../checklists/api-checklist.md)
