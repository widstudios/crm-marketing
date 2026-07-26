# Checklist — API

> Prima di pubblicare o modificare un endpoint: un'API pubblicata è un contratto che non si può
> ritirare.

| | |
|---|---|
| **Quando** | prima di pubblicare un endpoint nuovo o modificarne uno esistente |
| **Chi la applica** | Backend Agent, con verifica del Reviewer e del Security Agent |
| **Natura** | condizione di pubblicazione |

---

## Indice

1. [Descrizione](#descrizione) 2. [Verifiche](#verifiche) 3. [Comandi di verifica](#comandi-di-verifica)
4. [Esempi](#esempi) 5. [Best practice](#best-practice) 6. [Errori comuni](#errori-comuni)
7. [Checklist](#checklist) 8. [Riferimenti](#riferimenti)

---

## Descrizione

La differenza tra un'API e una schermata è che la schermata si può cambiare, l'API no: dall'altra
parte c'è un integratore che non si aggiorna quando lo decidiamo noi.

Questa checklist si applica **prima** della pubblicazione, perché dopo le uniche opzioni sono
mantenere il comportamento o rompere qualcuno.

Ogni voce si verifica in modo oggettivo. Le voci si riportano **una per una**, con l'esito reale.

---

## Verifiche

### Contratto

- [ ] L'endpoint è versionato nel percorso: `/api/v1/…`.
- [ ] Le risorse sono nomi al plurale, in `kebab-case`: `/api/v1/stock-movements`.
- [ ] Nessun verbo nel percorso: l'azione è il metodo HTTP.
- [ ] I metodi rispettano la semantica: `GET` non muta nulla, `PUT` è idempotente, `DELETE` è
      idempotente.
- [ ] I codici di stato sono corretti: `200`, `201` con `Location`, `204`, `422`, `401`, `403`,
      `404`, `409`, `429`.
- [ ] Le date sono ISO 8601 in UTC.
- [ ] I decimali viaggiano come stringhe o come numeri con precisione dichiarata, mai come float
      ambigui.
- [ ] Gli importi hanno la valuta accanto.

### Modifiche a un endpoint esistente

- [ ] La modifica è **additiva**: campi nuovi facoltativi, nessun campo rimosso o rinominato.
- [ ] Nessun campo ha cambiato tipo o significato.
- [ ] Nessun valore di enum è stato rimosso.
- [ ] Nessun vincolo di validazione è stato reso più stretto su un campo esistente.
- [ ] Se la modifica **non** è retrocompatibile, è stata introdotta una nuova versione, non
      modificata quella in uso.
- [ ] Le deprecazioni sono annunciate con header `Deprecation` e `Sunset`, e una data di rimozione.

### Autorizzazione

- [ ] L'endpoint autorizza esplicitamente: nessuna rotta protetta dal solo middleware di
      autenticazione.
- [ ] Sono verificate **sia** le abilità del token **sia** i permessi dell'utente.
- [ ] Il tenant si deriva da dominio o token, mai da un parametro della richiesta.
- [ ] Una risorsa di un altro tenant produce `404`, non `403`.
- [ ] La limitazione di frequenza è dichiarata, con header `X-RateLimit-*` e `429` con
      `Retry-After`.

### Input

- [ ] La validazione sta in una Form Request dedicata, non nel controller.
- [ ] Filtri, ordinamenti e campi di ricerca sono a **lista bianca**.
- [ ] I parametri di paginazione hanno un massimo dichiarato (`per_page` limitato).
- [ ] Nessun parametro accetta espressioni interpretate lato server.
- [ ] I messaggi di validazione sono in italiano, con chiave del campo in inglese.
- [ ] Gli errori di validazione seguono un formato unico, documentato.

### Output

- [ ] La risposta passa da una API Resource con campi **espliciti**: nessun `toArray()` del model.
- [ ] Nessun campo interno esposto: chiavi tecniche, colonne di stato interno, percorsi di file.
- [ ] Le relazioni si includono su richiesta (`include`), non sempre.
- [ ] Gli elenchi sono **sempre** paginati, con metadati di paginazione.
- [ ] Il formato degli errori è coerente su tutti gli endpoint.
- [ ] Nessun messaggio di errore rivela l'esistenza di risorse non visibili.
- [ ] Nessuna traccia di stack nelle risposte, in nessun ambiente non locale.

### Prestazioni

- [ ] Il numero di query è **costante** rispetto al numero di elementi restituiti.
- [ ] Le relazioni incluse sono caricate in anticipo.
- [ ] La risposta di lettura resta sotto i **200 ms** sui volumi dichiarati.
- [ ] Le operazioni lunghe rispondono `202` e proseguono in coda, con un endpoint di stato.

### Test

- [ ] Ogni endpoint ha i quattro test: risposta corretta, validazione fallita, autorizzazione
      negata, risorsa inesistente.
- [ ] Esiste il test che verifica il `404` sulle risorse di un altro tenant.
- [ ] Esiste un test sulla limitazione di frequenza.
- [ ] Esiste un test che vincola il numero di query.

### Documentazione

- [ ] L'endpoint è documentato: percorso, metodo, permessi richiesti, parametri, risposta, errori.
- [ ] Gli esempi di richiesta e risposta sono **reali**, presi dai test.
- [ ] Gli esempi non contengono dati di persone reali né segreti.
- [ ] Le modifiche sono riportate nel `CHANGELOG.md` con la versione dell'API.
- [ ] La documentazione è aggiornata nello stesso commit dell'endpoint.

---

## Comandi di verifica

```bash
php artisan route:list --path=api/v1
php artisan test --filter=Api
php tooling/scripts/check-api.php          # rotte senza autorizzazione, senza Resource, senza test
php tooling/scripts/check-api.php --diff   # modifiche non retrocompatibili rispetto alla versione pubblicata
```

Il confronto con la versione pubblicata è la verifica più importante di questa checklist: è l'unica
che intercetta la rottura **prima** che raggiunga un integratore.

---

## Esempi

### Risposta conforme

```json
{
  "data": [
    {
      "id": 1042,
      "number": "LOT-2026-0187",
      "expiry_date": "2027-03-14",
      "quantity": "48.500",
      "status": "available",
      "article": { "id": 12, "code": "ART-0012", "name": "Garza sterile 10x10" }
    }
  ],
  "meta": { "current_page": 1, "per_page": 25, "total": 1183 },
  "links": { "next": "https://acme.example.test/api/v1/batches?page=2" }
}
```

### Errore di validazione conforme

```json
{
  "message": "I dati inviati non sono validi.",
  "errors": {
    "quantity": ["La quantità deve essere maggiore di zero."],
    "expiry_date": ["La data di scadenza deve essere futura."]
  }
}
```

### Modifica non retrocompatibile mascherata da miglioria

```diff
- "quantity": "48.500",
+ "quantity": { "value": "48.500", "unit": "pcs" },
```

È una rottura del contratto, non un arricchimento: ogni integratore che legge `quantity` come
stringa smette di funzionare. La forma corretta è aggiungere `quantity_unit` accanto, e introdurre
la struttura nella versione successiva.

---

## Best practice

- Progettare la risposta dal punto di vista di chi la consuma, non dalle tabelle.
- Aggiungere, mai cambiare: un campo nuovo non rompe nessuno, un campo modificato sì.
- Prendere gli esempi della documentazione dai test: restano veri per costruzione.
- Dichiarare un massimo su ogni parametro di paginazione: senza, `per_page=100000` è un attacco.
- Rispondere `202` con un endpoint di stato invece di far attendere una richiesta lunga.
- Trattare la versione dell'API come un impegno, non come un numero.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| `toArray()` del model come risposta | Una colonna aggiunta domani finisce nell'API | Resource con campi espliciti |
| Campo rinominato «per chiarezza» | Ogni integratore si rompe | Aggiungere, poi deprecare |
| Rotta protetta dal solo `auth` | Chiunque autenticato accede a tutto | Autorizzazione esplicita |
| Solo abilità del token verificate | Permessi revocati ma token ancora valido | Verificare entrambi |
| `403` su risorse di altri tenant | Conferma l'esistenza della risorsa | `404` |
| Elenco non paginato | Risposta enorme, memoria esaurita | Paginazione obbligatoria |
| `per_page` senza massimo | Richiesta che satura il server | Limite dichiarato |
| Relazioni caricate senza eager loading | N+1 sotto carico | `with()` in base a `include` |
| Formato di errore diverso per endpoint | L'integratore gestisce N casi | Formato unico |
| Documentazione scritta a mano | Diverge dal codice in una settimana | Esempi presi dai test |
| Deprecazione senza data | Non si rimuove mai nulla | Header `Sunset` e data |

---

## Checklist

- [ ] Ho verificato che la modifica sia additiva, o che introduca una nuova versione.
- [ ] Ho confrontato il contratto con la versione pubblicata.
- [ ] Ogni endpoint autorizza esplicitamente e verifica token e permessi.
- [ ] La risposta passa da una Resource con campi espliciti.
- [ ] Gli elenchi sono paginati, con massimo dichiarato.
- [ ] Ho i quattro test per ogni endpoint, più isolamento e numero di query.
- [ ] La documentazione è aggiornata nello stesso commit, con esempi presi dai test.

---

## Riferimenti

- [Regole REST API](../rules/rest-api.md) · [Validazione](../rules/validation.md) · [Sicurezza](../rules/security.md)
- [Architettura delle API](../architecture/16-api-architecture.md)
- [Guida allo sviluppo API](../docs/03-development/04-api-development-guide.md)
- [Definition of Done](definition-of-done.md) · [Sicurezza](security-checklist.md)
- [Versionamento](../governance/versioning.md)
