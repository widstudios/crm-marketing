# Regole — REST API

> Risorse, versioni, risposte, errori e limiti. Le API hanno consumatori che non aggiorniamo noi.

---

## Indice

1. [Descrizione](#descrizione)
2. [Risorse e percorsi](#risorse-e-percorsi)
3. [Metodi e codici di stato](#metodi-e-codici-di-stato)
4. [Versionamento](#versionamento)
5. [Formato delle risposte](#formato-delle-risposte)
6. [Errori](#errori)
7. [Paginazione, filtri, ordinamento](#paginazione-filtri-ordinamento)
8. [Autenticazione](#autenticazione)
9. [Limiti](#limiti)
10. [Documentazione](#documentazione)
11. [Esempi](#esempi)
12. [Best practice](#best-practice)
13. [Errori comuni](#errori-comuni)
14. [Checklist](#checklist)
15. [Riferimenti](#riferimenti)

---

## Descrizione

Una modifica incompatibile a un'API non è un fastidio: è un'integrazione del cliente che smette di
funzionare, spesso di notte. Il principio guida è: **le API si estendono, non si modificano**.

---

## Risorse e percorsi

**R1.** Le risorse rappresentano concetti di **dominio**, non tabelle né schermate.
*Verifica:* revisione.

**R2.** I percorsi usano sostantivi al **plurale**, in `kebab-case`. Il verbo è il metodo HTTP.

```
POST /api/v1/suppliers            ✓
POST /api/v1/create-supplier      ✗
```

*Verifica:* revisione.

**R3.** Le operazioni non CRUD si modellano come **sottorisorsa di stato**.

```
POST /api/v1/suppliers/42/suspension
```

*Motivo:* rende l'operazione una risorsa interrogabile. *Verifica:* revisione.

**R4.** Massimo un livello di annidamento.
*Verifica:* revisione.

**R5.** Ogni rotta ha un nome, con prefisso `api.v<versione>.`.
*Verifica:* revisione.

---

## Metodi e codici di stato

| Metodo | Uso | Successo | Errori tipici |
|---|---|---|---|
| `GET` | lettura | `200` | `404` |
| `POST` | creazione, operazione | `201` + `Location`, `200` | `422`, `409` |
| `PATCH` | modifica parziale | `200` | `422`, `404`, `409` |
| `PUT` | sostituzione completa | `200` | `422`, `404` |
| `DELETE` | cancellazione | `204` | `404`, `409` |

**R6.** `PATCH` è la norma per le modifiche; `PUT` solo dove la sostituzione completa è
intenzionale.
*Motivo:* `PUT` richiede al consumatore di reinviare tutti i campi e produce perdite di dati quando
il modello si estende. *Verifica:* revisione.

**R7.** `GET` non ha effetti collaterali.
*Verifica:* revisione.

**R8.** Una risorsa non visibile al tenant corrente ritorna `404`, non `403`.
*Verifica:* test. *Livello: vincolante.*

---

## Versionamento

**R9.** La versione è nel percorso: `/api/v1/`.
*Verifica:* struttura delle rotte.

**R10.** Una nuova versione serve **solo** per modifiche incompatibili.

| Modifica | Nuova versione |
|---|---|
| Aggiunta di un campo in risposta | no |
| Aggiunta di un parametro facoltativo | no |
| Nuovo endpoint | no |
| Rimozione o rinomina di un campo | **sì** |
| Cambio di tipo di un campo | **sì** |
| Nuovo parametro obbligatorio | **sì** |
| Cambio di un codice di stato | **sì** |

*Verifica:* revisione in fase di rilascio.

**R11.** Le versioni convivono per almeno **12 mesi**.
*Verifica:* piano di dismissione.

**R12.** La deprecazione usa gli header `Deprecation` e `Sunset`, **più** una comunicazione attiva
ai consumatori.
*Motivo:* gli header li legge chi controlla i log, cioè quasi nessuno.
*Verifica:* processo di rilascio.

---

## Formato delle risposte

**R13.** Serializzazione sempre tramite API Resource, con **campi espliciti**.
*Motivo:* ritornare il model espone ogni colonna aggiunta in futuro.
*Verifica:* test di architettura. *Livello: vincolante.*

**R14.** Convenzioni di formato:

| Elemento | Formato |
|---|---|
| Chiavi | `snake_case` |
| Date | ISO 8601 con fuso |
| Decimali | stringa quando la precisione conta |
| Enum | il valore, non l'etichetta tradotta |
| Booleani | veri booleani |
| Valori assenti | `null`, mai stringa vuota |
| Collezioni | in `data`, con `meta` e `links` |

**R15.** Le relazioni si includono con `whenLoaded()`, mai caricate incondizionatamente.
*Verifica:* test sul numero di query.

---

## Errori

**R16.** Formato unico su tutte le API, conforme a RFC 7807.

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

*Verifica:* test su tutti gli endpoint. *Livello: vincolante.*

**R17.** Gli errori `500` non espongono dettagli interni.
*Verifica:* test in ambiente con `APP_DEBUG=false`.

**R18.** La traduzione delle eccezioni di dominio in codici HTTP è centralizzata nel gestore, non
ripetuta nei controller.
*Verifica:* revisione.

---

## Paginazione, filtri, ordinamento

**R19.** Paginazione obbligatoria su ogni collezione, anche se oggi è piccola.
*Verifica:* revisione. *Livello: vincolante.*

**R20.** Dimensione pagina: default 25, massimo 100.
*Verifica:* revisione.

**R21.** Filtri e ordinamenti a **lista bianca**.
*Verifica:* revisione.

**R22.** I metadati includono totale, pagina corrente, ultima pagina e collegamenti.
*Verifica:* test.

---

## Autenticazione

**R23.** Tutte le rotte tenant hanno `auth:sanctum`, `tenant` e `throttle`.
*Verifica:* script di verifica. *Livello: vincolante.*

**R24.** Il tenant deriva dal **token**, mai da un parametro.
*Verifica:* test. *Livello: assoluto.*

**R25.** Ogni endpoint verifica sia le abilità del token sia i permessi dell'utente.
*Verifica:* test.

---

## Limiti

| Ambito | Limite |
|---|---|
| Per token | 300 richieste/minuto |
| Per tenant | 1.000 richieste/minuto |
| Endpoint costosi | 10 richieste/minuto |
| Autenticazione | 5 tentativi/minuto per indirizzo |

**R26.** Il limite è anche **per tenant**, non solo per token.
*Motivo:* un cliente con dieci integrazioni non deve poter degradare il servizio degli altri.
*Verifica:* configurazione, test.

**R27.** Header `X-RateLimit-*` sempre presenti; `Retry-After` sul `429`.
*Verifica:* test.

---

## Documentazione

**R28.** Ogni API è documentata in OpenAPI 3.1, versionata nel repository.
*Verifica:* presenza e validazione dello schema in CI. *Livello: vincolante.*

**R29.** Un endpoint non documentato non è rilasciabile.
*Motivo:* il consumatore lo scoprirebbe per tentativi, dipendendo da comportamenti non
intenzionali. *Verifica:* checklist di rilascio.

---

## Esempi

### Esempio 1 — controller conforme

```php
final class SupplierController extends Controller
{
    public function index(Request $request, SupplierListQuery $query): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Supplier::class);

        return SupplierResource::collection($query->execute(
            status: $request->enum('status', SupplierStatus::class),
            perPage: min($request->integer('per_page', 25), 100),
        ));
    }
}
```

### Esempio 2 — violazioni

```php
Route::get('/api/suppliers', function (Request $request) {   // ✗ R9: nessuna versione
    $tenant = Tenant::find($request->input('tenant_id'));   // ✗ R24
    return Supplier::all();                                 // ✗ R13, R19
});
```

---

## Best practice

- Progettare le risorse sui concetti di dominio, non sulle schermate.
- Versionare dal primo endpoint pubblicato.
- Aggiungere campi è sempre compatibile: preferirlo alla modifica.
- Documentare in OpenAPI contestualmente allo sviluppo.
- Provare gli endpoint con un client esterno prima del rilascio.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Tenant da parametro | Accesso ai dati altrui | Dal token |
| Ritornare il model | Colonne interne esposte | API Resource |
| Nessuna paginazione | L'API smette di rispondere con la crescita | Paginazione obbligatoria |
| Formati di errore diversi | Consumatori con casi speciali | Formato unico |
| Verbo nel percorso | Non è REST, percorsi che proliferano | Metodo HTTP |
| `403` per risorse di altri tenant | Conferma l'esistenza | `404` |
| Modifica incompatibile senza versione | Integrazioni rotte | Nuova versione |
| API non documentata | Dipendenze da comportamenti non intenzionali | OpenAPI |

---

## Checklist

- [ ] Risorse su concetti di dominio, percorsi al plurale.
- [ ] Operazioni non CRUD come sottorisorse.
- [ ] Versione nel percorso.
- [ ] `auth:sanctum`, `tenant`, `throttle` su tutte le rotte tenant.
- [ ] Tenant dal token.
- [ ] Abilità e permessi verificati.
- [ ] API Resource con campi espliciti.
- [ ] Paginazione, filtri e ordinamenti a lista bianca.
- [ ] Formato di errore RFC 7807 uniforme.
- [ ] Limiti per token e per tenant, con header.
- [ ] Documentazione OpenAPI aggiornata.
- [ ] Test per risposta corretta, validazione, autorizzazione, inesistenza.

---

## Riferimenti

- [Architettura delle API](../architecture/16-api-architecture.md)
- [Sviluppare una API](../docs/03-development/04-api-development-guide.md)
- [Validazione](validation.md) · [Sicurezza](security.md)
- [Checklist API](../checklists/api-checklist.md)
