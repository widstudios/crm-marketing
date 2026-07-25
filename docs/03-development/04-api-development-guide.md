# Sviluppare una API

> Progettare, esporre, autenticare, documentare e versionare una API REST in un contesto
> multitenant.

---

## Indice

1. [Descrizione](#descrizione)
2. [Progettare le risorse](#progettare-le-risorse)
3. [Struttura degli endpoint](#struttura-degli-endpoint)
4. [Autenticazione e contesto tenant](#autenticazione-e-contesto-tenant)
5. [Richiesta e validazione](#richiesta-e-validazione)
6. [Risposta e serializzazione](#risposta-e-serializzazione)
7. [Errori](#errori)
8. [Paginazione, filtri, ordinamento](#paginazione-filtri-ordinamento)
9. [Limitazione del traffico](#limitazione-del-traffico)
10. [Documentazione](#documentazione)
11. [Esempi](#esempi)
12. [Best practice](#best-practice)
13. [Errori comuni](#errori-comuni)
14. [Checklist](#checklist)
15. [Riferimenti](#riferimenti)

---

## Descrizione

Una API ha consumatori che non aggiorniamo noi: sistemi del cliente, integrazioni di terze parti,
applicazioni mobili. Ogni scelta è più difficile da correggere rispetto all'interfaccia web, dove
basta rilasciare.

Da qui due conseguenze operative: si progetta con più cura, e si versiona da subito.

---

## Progettare le risorse

Le risorse REST rappresentano **concetti di dominio**, non tabelle né schermate.

| Buona risorsa | Cattiva risorsa | Perché |
|---|---|---|
| `/suppliers` | `/supplier_table` | il concetto, non la tabella |
| `/batches/{id}/movements` | `/get-movements-by-batch` | il verbo sta nel metodo HTTP |
| `/orders/{id}/confirmation` | `/confirm-order` | l'operazione come sottorisorsa |
| `/reports/stock-levels` | `/data?type=stock` | risorsa esplicita |

Per le operazioni che non sono CRUD, due strade ammesse:

1. **Sottorisorsa di stato**: `POST /suppliers/{id}/suspension` crea una sospensione.
2. **Azione esplicita**: `POST /suppliers/{id}/actions/suspend` quando la prima è forzata.

La prima è preferibile: rende l'operazione una risorsa interrogabile (`GET .../suspension`).

---

## Struttura degli endpoint

| Metodo | Percorso | Scopo | Risposta |
|---|---|---|---|
| `GET` | `/api/v1/suppliers` | elenco paginato | `200` |
| `GET` | `/api/v1/suppliers/{id}` | dettaglio | `200` / `404` |
| `POST` | `/api/v1/suppliers` | creazione | `201` + `Location` |
| `PATCH` | `/api/v1/suppliers/{id}` | modifica parziale | `200` |
| `PUT` | `/api/v1/suppliers/{id}` | sostituzione completa | `200` |
| `DELETE` | `/api/v1/suppliers/{id}` | cancellazione | `204` |
| `POST` | `/api/v1/suppliers/{id}/suspension` | operazione | `200` / `422` |

`PATCH` è la norma per le modifiche: `PUT` richiede al consumatore di conoscere e reinviare tutti
i campi, e produce perdite di dati quando il modello si estende.

---

## Autenticazione e contesto tenant

Le API tenant vivono in `routes/api-tenant.php`, sotto tre middleware:

```php
Route::middleware(['api', 'auth:sanctum', 'tenant'])
    ->prefix('v1')
    ->group(function (): void {
        Route::apiResource('suppliers', SupplierController::class);
    });
```

Il tenant si risolve **dal token**, non da un parametro. Un parametro `?tenant=` sarebbe una via
diretta per accedere a dati altrui semplicemente cambiando un valore.

| Aspetto | Regola |
|---|---|
| Token | Sanctum, con abilità (`abilities`) che rispecchiano i permessi |
| Scadenza | obbligatoria; i token perpetui non esistono |
| Revoca | possibile dal pannello del tenant |
| Contesto | derivato dal token, mai da input del client |
| Rotazione | supportata, con periodo di sovrapposizione |

Ogni endpoint autorizza esplicitamente, anche quando sembra ovvio:

```php
$this->authorize('viewAny', Supplier::class);
```

---

## Richiesta e validazione

La validazione sta nel Form Request; le regole di dominio restano nell'Action.

```php
final class StoreSupplierRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'vat_number' => ['required', 'string', 'regex:/^IT[0-9]{11}$/'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function toData(): SupplierData
    {
        return SupplierData::fromArray($this->validated());
    }
}
```

La validazione HTTP verifica la **forma**; l'Action verifica le **regole di business**
(unicità nel tenant, stato ammesso, precondizioni). La duplicazione tra i due livelli è
apparente: proteggono da cose diverse e da percorsi diversi.

---

## Risposta e serializzazione

Sempre tramite API Resource, mai ritornando il model.

```php
final class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'vat_number' => $this->vat_number,
            'email' => $this->email,
            'status' => $this->status->value,
            'created_at' => $this->created_at->toIso8601String(),
            'links' => [
                'self' => route('api.suppliers.show', $this->resource),
            ],
        ];
    }
}
```

Ritornare il model direttamente espone ogni colonna aggiunta in futuro, comprese quelle interne:
è il modo più comune di far trapelare dati non previsti.

Convenzioni di formato:

| Elemento | Formato |
|---|---|
| Date | ISO 8601 con fuso (`2026-07-25T14:30:00+02:00`) |
| Decimali | stringa quando la precisione conta, per evitare arrotondamenti JSON |
| Enum | il valore, non l'etichetta tradotta |
| Chiavi | `snake_case` |
| Booleani | veri booleani, non `0`/`1` |
| Valori assenti | `null`, mai stringa vuota |

---

## Errori

Formato unico per tutte le API, conforme a RFC 7807:

```json
{
    "type": "https://api.example.com/problems/insufficient-stock",
    "title": "Giacenza insufficiente",
    "status": 422,
    "detail": "Il lotto LOT-0042 ha una disponibilità di 5 unità, richieste 10.",
    "instance": "/api/v1/batches/42/movements",
    "errors": {
        "quantity": ["La quantità supera la disponibilità."]
    }
}
```

| Codice | Quando |
|---|---|
| `400` | richiesta malformata |
| `401` | token assente o non valido |
| `403` | autenticato ma non autorizzato |
| `404` | risorsa inesistente **o non visibile a questo tenant** |
| `409` | conflitto di stato |
| `422` | validazione fallita |
| `429` | limite di traffico superato |
| `500` | errore non gestito (mai con dettagli interni) |

La risorsa di un altro tenant ritorna `404`, non `403`: `403` confermerebbe che quella risorsa
esiste da qualche parte.

---

## Paginazione, filtri, ordinamento

```
GET /api/v1/suppliers?filter[status]=active&sort=-created_at&page[size]=50&page[number]=2
```

| Aspetto | Regola |
|---|---|
| Paginazione | sempre, anche su collezioni oggi piccole |
| Dimensione pagina | default 25, massimo 100 |
| Ordinamento | lista bianca di campi ammessi |
| Filtri | lista bianca; nessun filtro arbitrario sulle colonne |
| Metadati | totale, pagina corrente, ultima pagina, link |

Una collezione senza paginazione funziona fino al giorno in cui un tenant ha 200.000 righe, e
quel giorno l'API smette di rispondere per tutti.

---

## Limitazione del traffico

```php
Route::middleware(['throttle:api-tenant'])->group(/* … */);
```

| Ambito | Limite di riferimento |
|---|---|
| Per token | 300 richieste/minuto |
| Per tenant | 1.000 richieste/minuto |
| Endpoint costosi (report, esportazioni) | 10 richieste/minuto |
| Autenticazione | 5 tentativi/minuto per indirizzo |

Il limite è **per tenant** oltre che per token: un tenant non deve poter degradare il servizio
degli altri.

---

## Documentazione

Ogni API è documentata in OpenAPI 3.1, generata dal codice quando possibile e versionata nel
repository.

Contenuto minimo per endpoint: scopo, autenticazione richiesta, permessi, parametri, esempio di
richiesta, esempio di risposta, errori possibili, limiti di traffico.

Una API non documentata **non è rilasciabile**: il consumatore la scoprirebbe per tentativi, e
dipenderebbe da comportamenti non intenzionali.

---

## Esempi

### Esempio 1 — operazione come sottorisorsa

```http
POST /api/v1/suppliers/42/suspension
Content-Type: application/json

{ "reason": "Documentazione fiscale scaduta" }
```

```http
HTTP/1.1 200 OK

{ "id": 42, "status": "suspended", "suspended_at": "2026-07-25T14:30:00+02:00" }
```

### Esempio 2 — isolamento e codici di stato

Il tenant `acme` richiede `GET /api/v1/suppliers/99`, che appartiene a `globex`.

Risposta corretta: `404`. La query gira nel database di `acme`, dove l'identificatore 99 non
esiste: l'isolamento non è una verifica applicativa, è una conseguenza dell'architettura.

---

## Best practice

- Progettare le risorse sui concetti di dominio.
- Versionare dal primo endpoint pubblicato.
- Derivare il tenant dal token, mai da input del client.
- Paginare sempre, con lista bianca di filtri e ordinamenti.
- Usare API Resource, mai ritornare il model.
- Formato di errore unico su tutte le API.
- Documentare in OpenAPI prima del rilascio.
- Aggiungere campi è compatibile; rimuoverli o rinominarli richiede una nuova versione.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Tenant da parametro | Accesso a dati altrui cambiando un valore | Tenant dal token |
| Ritornare il model | Esposizione di colonne interne | API Resource |
| Nessuna paginazione | L'API smette di rispondere quando i dati crescono | Paginazione obbligatoria |
| Filtri arbitrari sulle colonne | Query costose e superficie d'attacco | Lista bianca |
| `403` per risorse di altri tenant | Conferma l'esistenza della risorsa | `404` |
| Formati di errore diversi per endpoint | Consumatori costretti a casi speciali | Formato unico |
| Modifica incompatibile senza nuova versione | Integrazioni rotte senza preavviso | Versionamento |
| API non documentata | Dipendenze da comportamenti non intenzionali | OpenAPI obbligatorio |

---

## Checklist

- [ ] Risorse modellate su concetti di dominio.
- [ ] Versione nel percorso fin dal primo endpoint.
- [ ] Middleware `auth:sanctum` e `tenant` su tutte le rotte tenant.
- [ ] Autorizzazione esplicita in ogni metodo.
- [ ] Validazione nel Form Request, regole di business nell'Action.
- [ ] Serializzazione tramite API Resource.
- [ ] Paginazione, filtri e ordinamenti con lista bianca.
- [ ] Formato di errore RFC 7807 uniforme.
- [ ] Limitazione del traffico per token e per tenant.
- [ ] Documentazione OpenAPI aggiornata.
- [ ] Test di feature per ogni endpoint, compresi errori e autorizzazione.

---

## Riferimenti

- [Regole REST API](../../rules/rest-api.md) · [Validazione](../../rules/validation.md) · [Sicurezza](../../rules/security.md)
- [Architettura delle API](../../architecture/16-api-architecture.md)
- [Versionamento dei progetti](../02-conventions/04-project-versioning.md)
- [Checklist API](../../checklists/api-checklist.md)
