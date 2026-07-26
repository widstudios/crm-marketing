# Snippet — contesto tenant

> I vincoli di isolamento da includere in ogni prompt che tocca dati.

| | |
|---|---|
| **Versione** | 1.0.0 |
| **Usato da** | Database, Backend, Filament, Frontend, Security, Performance, Deploy |

---

## Indice

1. [Descrizione](#descrizione) 2. [Lo snippet](#lo-snippet) 3. [Esempi](#esempi)
4. [Best practice](#best-practice) 5. [Errori comuni](#errori-comuni) 6. [Checklist](#checklist)
7. [Riferimenti](#riferimenti)

---

## Descrizione

L'isolamento tra tenant è garantito dalla struttura (database separati) in tutto tranne cinque punti,
dove dipende dal codice. Questo snippet li rende espliciti a ogni agente che potrebbe toccarli.

---

## Lo snippet

```markdown
## Contesto multitenant

Questa applicazione serve più clienti sulla stessa installazione, con **un database per cliente**.
Il landlord contiene i dati di piattaforma; ogni tenant ha il proprio database con tutti i suoi dati
di dominio.

### Conseguenze sul tuo lavoro

1. **Nessuna colonna `tenant_id` nelle tabelle tenant.** Il tenant *è* il database. Una colonna del
   genere non aggiunge sicurezza: occupa spazio, richiede indici e induce a scrivere query filtrate
   che danno una falsa impressione di isolamento.

2. **Non serve filtrare per tenant** nelle query, nelle Policy, nelle Resource: la connessione attiva
   è già quella del tenant corrente. Se ti trovi a doverlo fare, il middleware non sta funzionando, ed
   è quello il problema da segnalare.

3. **Nessuna query cross-tenant**, in nessuna circostanza, nemmeno diagnostica. Se serve un dato
   aggregato su più clienti, la soluzione è un job schedulato che gira **nel contesto di ciascun
   tenant** e scrive un aggregato **numerico** nel landlord.

### I cinque punti da presidiare

L'isolamento del database è strutturale. Questi cinque punti dipendono invece dal codice:

| # | Punto | Vincolo |
|---|---|---|
| 1 | **Cache** | ogni chiave passa da `TenantCacheKey::for()`, mai costruita a mano |
| 2 | **Code** | ogni job usa il trait `TenantAware` |
| 3 | **Storage** | disco `tenant`, privato; percorsi mai costruiti concatenando input |
| 4 | **Comandi** | eseguiti con `tenant:artisan`, mai direttamente |
| 5 | **Aggregati verso il landlord** | solo valori numerici, mai identificativi |

Una violazione di uno di questi cinque punti non produce un errore visibile: produce una fuga di
dati che nessuno rileva finché un cliente non segnala di vedere qualcosa che non riconosce.

### Verifiche attese

Per ogni entità che tocchi, deve esistere un test di isolamento con **due tenant reali** che
verifica l'invisibilità reciproca. Se stai producendo un'entità nuova, il test è parte del tuo
output.

### In caso di dubbio

Se un requisito sembra richiedere una query cross-tenant, **non implementarlo**: segnala il
conflitto e proponi la soluzione con aggregati asincroni.
```

---

## Esempi

### Esempio 1 — richiesta che attiva il vincolo

Requisito: «il pannello di assistenza deve mostrare tutti i lotti in scadenza di tutti i clienti».

Risposta corretta: un aggregato nel landlord con il **conteggio** per tenant. Il dettaglio dei lotti
non esce dal database del cliente.

### Esempio 2 — fraintendimento tipico

```php
// ✗ Chi scrive questo non ha compreso il modello
Schema::create('suppliers', function (Blueprint $table): void {
    $table->foreignId('tenant_id');
    $table->string('name');
});
```

---

## Best practice

- Includere questo snippet in ogni prompt che tocca dati, cache, code, storage o comandi.
- Non ripetere i cinque punti nel prompt di fase: sono qui.
- Verificare i cinque punti prima di ogni rilascio maggiore.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Colonna `tenant_id` | Fraintendimento del modello | Il tenant è il database |
| Filtro per tenant nelle query | Sintomo di middleware non funzionante | Segnalare il problema reale |
| Chiave di cache costruita a mano | Fuga di dati tra clienti | Helper obbligatorio |
| Job senza `TenantAware` | Scrittura nel database sbagliato | Trait obbligatorio |
| Identificativi negli aggregati | Fuga verso il landlord | Solo numeri |

---

## Checklist

- [ ] Nessuna colonna `tenant_id` introdotta.
- [ ] Nessuna query cross-tenant.
- [ ] Chiavi di cache tramite l'helper.
- [ ] Job con `TenantAware`.
- [ ] Storage su disco tenant, privato.
- [ ] Aggregati con soli valori numerici.
- [ ] Test di isolamento per ogni entità nuova.

---

## Riferimenti

- [Multitenancy](../../architecture/03-multitenancy-overview.md)
- [Risoluzione del tenant](../../architecture/06-tenant-resolution.md)
- [Cache](../../rules/cache.md) · [Queue](../../rules/queue.md) · [Sicurezza](../../rules/security.md)
