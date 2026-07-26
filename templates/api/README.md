# Templates — API

> Gli stub del contratto verso l'esterno: controller, validazione, forma della risposta.

---

## Indice

1. [Descrizione](#descrizione) 2. [Gli stub](#gli-stub) 3. [Dove vanno i file](#dove-vanno-i-file)
4. [Il contratto](#il-contratto) 5. [Esempi](#esempi) 6. [Best practice](#best-practice)
7. [Errori comuni](#errori-comuni) 8. [Checklist](#checklist) 9. [Riferimenti](#riferimenti)

---

## Descrizione

La differenza tra un'API e una schermata è che la schermata si può cambiare, l'API no. Dall'altra
parte c'è un integratore che non si aggiorna quando lo decidiamo noi, e che scoprirà la modifica nel
momento peggiore.

Per questo gli stub di questa cartella sono quelli con il maggior numero di vincoli espliciti: ogni
scorciatoia presa qui diventa permanente. `return parent::toArray($request)` è comodo per cinque
minuti e vincolante per anni.

---

## Gli stub

| Stub | Quando | Vincolo che conta |
|---|---|---|
| [ApiController.php.stub](ApiController.php.stub) | ogni risorsa esposta | autorizza sempre, pagina sempre, `404` non `403` |
| [FormRequest.php.stub](FormRequest.php.stub) | ogni endpoint che riceve dati | formato, non dominio; lista bianca sui parametri |
| [ApiResource.php.stub](ApiResource.php.stub) | ogni risposta | campi espliciti, mai `parent::toArray()` |

---

## Dove vanno i file

```
app/Http/
├── Controllers/Api/V1/{{ Entity }}Controller.php   ApiController.php.stub
├── Requests/Api/V1/Store{{ Entity }}Request.php    FormRequest.php.stub
└── Resources/Api/V1/{{ Entity }}Resource.php       ApiResource.php.stub

routes/api.php     → prefisso /api/v1, gruppo di middleware del tenant
```

---

## Il contratto

| Aspetto | Regola |
|---|---|
| Versione | nel percorso: `/api/v1/…` |
| Risorse | nomi al plurale, `kebab-case`: `/api/v1/stock-movements` |
| Verbi | nel metodo HTTP, mai nel percorso |
| Date | ISO 8601, UTC |
| Decimali | stringhe, mai float |
| Importi | con la valuta accanto |
| Elenchi | sempre paginati, `per_page` con massimo dichiarato |
| Errori | formato unico su tutti gli endpoint |
| Deprecazioni | header `Deprecation` e `Sunset`, con data |

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
      "status": "available"
    }
  ],
  "meta": { "current_page": 1, "per_page": 25, "total": 1183 },
  "links": { "next": "https://acme.example.test/api/v1/batches?page=2" }
}
```

### Modifica non retrocompatibile mascherata da miglioria

```diff
- "quantity": "48.500",
+ "quantity": { "value": "48.500", "unit": "pcs" },
```

Ogni integratore che legge `quantity` come stringa smette di funzionare. La forma corretta è
aggiungere `quantity_unit` accanto, e introdurre la struttura nella versione successiva.

---

## Best practice

- Progettare la risposta dal punto di vista di chi la consuma, non dalle tabelle.
- Aggiungere, mai cambiare.
- Prendere gli esempi della documentazione dai test: restano veri per costruzione.
- Dichiarare un massimo su `per_page`.
- Rispondere `202` con un endpoint di stato invece di far attendere una richiesta lunga.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| `parent::toArray()` come risposta | Una colonna aggiunta domani entra nel contratto | Campi espliciti |
| Campo rinominato «per chiarezza» | Ogni integratore si rompe | Aggiungere, poi deprecare |
| Rotta protetta dal solo `auth` | Chiunque autenticato accede a tutto | `authorize()` esplicito |
| Solo abilità del token verificate | Permessi revocati ma token valido | Verificare entrambi |
| `403` su risorse di altri tenant | Conferma l'esistenza | `404` |
| Elenco non paginato | Risposta enorme, memoria esaurita | Paginazione obbligatoria |
| `per_page` senza massimo | Una richiesta satura il server | Limite dichiarato |
| Decimali come float | Precisione persa lato client | Stringhe |
| Formato di errore diverso per endpoint | L'integratore gestisce N casi | Formato unico |

---

## Checklist

- [ ] Il percorso è versionato, le risorse sono plurali in `kebab-case`.
- [ ] Ogni endpoint autorizza esplicitamente e verifica token e permessi.
- [ ] Gli elenchi sono paginati, con massimo su `per_page`.
- [ ] La risposta passa da una Resource con campi espliciti.
- [ ] `404` per le risorse di altri tenant, con test dedicato.
- [ ] I quattro test per endpoint: corretto, validazione, autorizzazione, inesistente.
- [ ] Le modifiche sono additive, o introducono una nuova versione.

---

## Riferimenti

- [Regole REST API](../../rules/rest-api.md) · [Validazione](../../rules/validation.md) · [Sicurezza](../../rules/security.md)
- [Architettura delle API](../../architecture/16-api-architecture.md)
- [Checklist API](../../checklists/api-checklist.md)
- [Guida allo sviluppo API](../../docs/03-development/04-api-development-guide.md)
