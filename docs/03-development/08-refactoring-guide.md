# Guida al refactoring

> Come si migliora il codice esistente senza cambiarne il comportamento, e come si decide se un
> refactoring vale il rischio.

---

## Indice

1. [Descrizione](#descrizione)
2. [Quando rifattorizzare](#quando-rifattorizzare)
3. [Quando non rifattorizzare](#quando-non-rifattorizzare)
4. [Il prerequisito: i test](#il-prerequisito-i-test)
5. [Catalogo dei refactoring ricorrenti](#catalogo-dei-refactoring-ricorrenti)
6. [Refactoring dello schema](#refactoring-dello-schema)
7. [Rector](#rector)
8. [Gestire il debito tecnico](#gestire-il-debito-tecnico)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Refactoring significa **cambiare la struttura senza cambiare il comportamento**. Se il
comportamento cambia, non è refactoring: è una modifica funzionale, e va trattata come tale — con
i suoi test, la sua revisione e la sua voce nel changelog.

Questa distinzione non è terminologica. Un «refactoring» che cambia il comportamento produce
regressioni che nessuno cerca, perché la PR dichiarava che nulla sarebbe cambiato.

---

## Quando rifattorizzare

| Situazione | Priorità | Motivo |
|---|---|---|
| Prima di modificare codice poco chiaro | alta | rifattorizzare per capire, poi modificare |
| Terza occorrenza di codice duplicato | alta | è il momento della promozione |
| Classe che cresce oltre le ~200 righe | media | sta accumulando responsabilità |
| Metodo oltre le ~30 righe | media | ha più di un compito |
| Test difficili da scrivere | alta | è un sintomo di accoppiamento, non un problema dei test |
| Codice che nessuno vuole toccare | alta | il costo cresce ogni mese |
| Dopo una consegna urgente | media | rientro pianificato dal debito |
| Codice che non si tocca da un anno e funziona | **nessuna** | il rischio supera il beneficio |

La regola operativa: si rifattorizza **il codice che si sta per modificare**, non il codice che
disturba esteticamente.

---

## Quando non rifattorizzare

- **Senza test.** Prima i test, poi il refactoring. Sempre.
- **Insieme a una modifica funzionale.** Due cambiamenti nello stesso commit rendono impossibile
  capire quale ha causato una regressione.
- **Su codice stabile che nessuno tocca.** Funziona, non lo si modifica: il beneficio è teorico,
  il rischio è reale.
- **Per preferenza personale.** «Lo avrei scritto diversamente» non è una motivazione.
- **Sotto scadenza.** Il refactoring fatto di fretta introduce difetti in codice che prima
  funzionava.
- **Su codice in fase di sostituzione.** Se tra un mese sparisce, non merita l'investimento.

---

## Il prerequisito: i test

Il refactoring è sicuro solo se esiste una rete che rileva il cambiamento di comportamento.

Se i test non ci sono:

1. Scrivere test **caratterizzanti**: descrivono il comportamento attuale, compresi i difetti.
2. Verificare che siano verdi sul codice esistente.
3. Rifattorizzare.
4. Verificare che siano ancora verdi.
5. Solo dopo, correggere i difetti — come modifica funzionale separata.

```php
// Test caratterizzante: documenta il comportamento attuale, non quello desiderato.
it('arrotonda per difetto (comportamento attuale, da rivedere)', function (): void {
    expect(app(StockCalculator::class)->available($batch))->toBe(4.0);
});
```

Il commento che segnala il comportamento discutibile è parte del test: senza, il prossimo lettore
penserà che l'arrotondamento per difetto sia intenzionale.

---

## Catalogo dei refactoring ricorrenti

### 1. Service tuttofare → Action

**Sintomo:** una classe `...Service` con dieci metodi pubblici che fanno cose diverse.

**Trasformazione:** una Action per operazione. Il Service resta solo se coordina più Action.

### 2. Logica nel controller → Action

**Sintomo:** metodo di controller oltre le 15 righe, con `if` sulle regole di business.

**Trasformazione:** la logica si sposta in un'Action; il controller autorizza, delega, risponde.

### 3. Logica nella Filament Resource → Action

**Sintomo:** `->action()` con condizioni di dominio.

**Trasformazione:** identica alla precedente. L'operazione diventa disponibile anche via API.

### 4. Stringhe magiche → Enum

**Sintomo:** `where('status', 'active')` sparso nel codice.

**Trasformazione:** enum con le transizioni ammesse; le stringhe spariscono dal codice chiamante.

### 5. Array associativi → DTO

**Sintomo:** array passati tra livelli, con chiavi che nessuno documenta.

**Trasformazione:** DTO `readonly` con proprietà tipizzate.

### 6. Query duplicate → Query object o scope

**Sintomo:** la stessa `where` complessa in cinque punti.

**Trasformazione:** scope sul model, o Query object se la lettura è complessa.

### 7. Condizioni annidate → guardie anticipate

```php
// ✗ Tre livelli di annidamento
if ($supplier->isActive()) {
    if ($user->can('update', $supplier)) {
        if ($data->isValid()) {
            // …
        }
    }
}

// ✓ Guardie che escono presto
throw_unless($supplier->isActive(), SupplierNotActive::class);
throw_unless($user->can('update', $supplier), AuthorizationException::class);
throw_unless($data->isValid(), ValidationException::class);
// …
```

### 8. Codice duplicato tra progetti → Foundation

**Sintomo:** la terza occorrenza dello stesso problema risolto in modo simile.

**Trasformazione:** promozione, con la procedura di [`governance/README.md`](../../governance/README.md).

---

## Refactoring dello schema

Il refactoring del database è il più rischioso, perché i dati non si possono «rifare».

Regole:

1. Sempre con il pattern in tre rilasci (espansione, migrazione, contrazione).
2. Mai in un unico rilascio, per quanto piccola sembri la modifica.
3. Sempre reversibile ad ogni passo.
4. Sempre verificato su un tenant con volumi realistici prima della produzione.
5. Mai contestualmente a un refactoring del codice: prima uno, poi l'altro.

Vedi [workflow del database](03-database-workflow.md#modifiche-che-richiedono-trasformazione-di-dati).

---

## Rector

Rector automatizza i refactoring meccanici: aggiornamenti di sintassi, tipi, modernizzazione.

```bash
composer refactor           # anteprima (--dry-run)
vendor/bin/rector process   # applica
```

| Regola | Motivo |
|---|---|
| Sempre prima in anteprima | Rector può cambiare più del previsto |
| Un insieme di regole per volta | diff rivedibili |
| Commit separato dalle modifiche manuali | permette il rollback selettivo |
| Test verdi prima e dopo | verifica dell'invarianza |
| Il diff si rivede, non si accetta al buio | l'automatismo non capisce l'intenzione |

Rector è ottimo per ciò che è meccanico e pessimo per ciò che richiede comprensione: non gli si
chiede di riorganizzare l'architettura.

---

## Gestire il debito tecnico

Il debito tecnico non è codice brutto: è **una scelta consapevole di rimandare**, con un costo
noto e crescente. Se non è consapevole, non è debito: è un difetto.

Ogni debito accettato si registra:

```markdown
## DT-012 — Calcolo giacenza non ottimizzato

**Assunto il:** 2026-05-10
**Motivo:** consegna urgente per la certificazione del cliente.
**Costo attuale:** l'elenco dei lotti impiega ~3 s con oltre 50.000 movimenti.
**Costo se rimandato:** cresce linearmente con i movimenti; oltre 200.000 diventa inutilizzabile.
**Rientro previsto:** versione 2.6.0
**Soluzione:** tabella di aggregati aggiornata da listener, con ricalcolo notturno di controllo.
```

Ritmo di rientro: una voce di debito per ogni ciclo di manutenzione. Un registro che cresce e non
si svuota mai è un registro che nessuno legge.

---

## Esempi

### Esempio 1 — refactoring con test caratterizzanti

`StockCalculator` ha 180 righe, nessun test, e va modificato per gestire i resi.

Procedura: prima si scrivono otto test che descrivono il comportamento attuale (compresi due casi
che sembrano sbagliati, annotati come tali). Poi si divide la classe in tre. Poi si aggiunge la
gestione dei resi come modifica funzionale separata, con i suoi test.

Tre commit distinti, tre revisioni possibili.

### Esempio 2 — refactoring che non andava fatto

Un modulo di importazione scritto due anni fa, mai toccato, funzionante. Viene riscritto «per
allinearlo agli standard». Introduce due regressioni su casi limite che il codice originale
gestiva senza che nessuno lo sapesse — perché non erano documentati né testati.

Il codice stabile che nessuno tocca ha, di fatto, un test in produzione lungo due anni: riscriverlo
significa buttare quella prova.

---

## Best practice

- Test prima, refactoring dopo. Senza eccezioni.
- Un commit per refactoring, separato dalle modifiche funzionali.
- Rifattorizzare ciò che si sta per modificare.
- Test caratterizzanti quando la copertura manca.
- Registrare il debito accettato, con costo e rientro previsti.
- Rector solo per il meccanico, con revisione del diff.
- Schema e codice in refactoring separati.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Refactoring senza test | Regressioni silenziose | Test caratterizzanti prima |
| Refactoring e modifica funzionale insieme | Impossibile isolare la causa di una regressione | Commit separati |
| «Refactoring» che cambia il comportamento | Regressioni che nessuno cerca | È una modifica funzionale |
| Riscrivere codice stabile | Si perdono anni di prova sul campo | Non toccare ciò che funziona |
| Rector accettato al buio | Modifiche non volute | Revisione del diff |
| Debito non registrato | Nessuno sa cosa è stato rimandato | Registro con costo e rientro |
| Refactoring sotto scadenza | Difetti in codice che funzionava | Rimandare |

---

## Checklist

- [ ] Esistono test che coprono il comportamento attuale.
- [ ] Il refactoring non cambia il comportamento osservabile.
- [ ] Il commit è separato dalle modifiche funzionali.
- [ ] I test sono verdi prima e dopo.
- [ ] `composer qa` verde, senza nuove voci in baseline.
- [ ] Se ho accettato del debito, l'ho registrato con costo e rientro.
- [ ] Il refactoring dello schema segue il pattern in tre rilasci.

---

## Riferimenti

- [Ciclo di vita dello sviluppo](01-development-lifecycle.md)
- [Strategia di testing](../04-quality/01-testing-strategy.md) · [Analisi statica](../04-quality/03-static-analysis.md)
- [Agente di refactoring](../../agents/15-refactoring-agent.md)
- [Workflow del database](03-database-workflow.md)
- [Governance](../../governance/README.md)
