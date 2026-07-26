# ADR-0007 — Strategia di testing

> Pest come strumento unico, copertura doppia (complessiva 80%, Action e Policy 100%), test di
> architettura e di isolamento tenant obbligatori.

| | |
|---|---|
| **Stato** | Accettata |
| **Data** | 2026-07-25 |
| **Decisore** | Standards Owner |
| **Impatto** | qualità di tutti i progetti |
| **Reversibilità** | reversibile |

---

## Indice

1. [Contesto](#contesto)
2. [Decisione](#decisione)
3. [Alternative valutate](#alternative-valutate)
4. [Conseguenze](#conseguenze)
5. [Soglia di rivalutazione](#soglia-di-rivalutazione)
6. [Verifica](#verifica)
7. [Riferimenti](#riferimenti)

---

## Contesto

Nei progetti precedenti la copertura dei test oscillava tra il 20% e il 60% secondo il periodo e le
scadenze. Le conseguenze osservate:

1. **Paura di modificare.** Il codice senza test diventava intoccabile: si aggiungeva accanto invece
   di correggere.
2. **Regressioni ricorrenti.** Bug corretti che tornavano dopo qualche mese.
3. **Difetti di isolamento non rilevati.** Un caso di query senza scope tenant scoperto in
   produzione da una segnalazione del cliente.
4. **Erosione strutturale.** Convenzioni architetturali disattese senza che nessuno lo notasse.

Con l'introduzione di agenti AI il problema cambia natura: il codice generato è formalmente
corretto ma non necessariamente giusto, e la verifica automatica diventa l'unico controllo che
scala.

---

## Decisione

### Strumento

**Pest 3** su PHPUnit 11, come strumento unico. Nessuna suite parallela con altri framework.

### Soglie di copertura

Doppia, deliberatamente:

| Ambito | Soglia |
|---|---|
| Complessiva | ≥ **80%** |
| Action | **100%** |
| Policy | **100%** |
| Value object ed enum di dominio | **100%** |
| Controller | ≥ 70% |
| Filament Resource | non conteggiata |
| Migration, config, provider | escluse |

La soglia doppia esiste perché una copertura media alta può nascondere un buco esattamente dove
conta: 92% complessivo con Action al 55% è un risultato peggiore di 78% ben distribuito.

### Categorie obbligatorie

| Categoria | Obbligatoria | Cartella |
|---|---|---|
| Unit (dominio, senza database) | sì | `tests/Unit/` |
| Feature (end-to-end) | sì | `tests/Feature/` |
| **Isolamento tenant** | **una per entità** | `tests/Tenant/` |
| **Architettura** | sì | `tests/Architecture/` |

Per ogni operazione servono almeno **tre** test: percorso corretto, violazione di una regola di
dominio, autorizzazione negata.

### Esecuzione

La suite gira **due volte** in pipeline: su SQLite (rapida) e su MySQL (rappresentativa).

### Regressioni

Ogni bug produce **prima** un test rosso, poi la correzione.

---

## Alternative valutate

### Alternativa A — PHPUnit senza Pest

**Scartata** per leggibilità: la sintassi di Pest rende i test più brevi e i nomi descrittivi più
naturali, e i test di architettura (Pest Arch) non hanno equivalente diretto. Pest gira su PHPUnit,
quindi non si perde nulla.

### Alternativa B — copertura unica al 100%

**Scartata** perché produce test senza valore su codice banale (getter, configurazione, viste), che
poi vanno mantenuti. L'obiettivo non è la copertura ma l'intercettazione dei difetti.

### Alternativa C — copertura unica al 60%, senza soglie per ambito

**Scartata** perché non garantisce che il codice critico sia coperto: la media si raggiunge coprendo
ciò che è facile.

### Alternativa D — test end-to-end su browser come categoria principale

**Scartata** per costo e fragilità: lenti, instabili, difficili da diagnosticare. Ammessi per i
percorsi critici, non come base della strategia.

### Confronto

| Asse | Soglia doppia (adottata) | 100% ovunque | 60% unica | E2E come base |
|---|---|---|---|---|
| Difetti intercettati sul codice critico | **alta** | alta | media | media |
| Costo di scrittura | medio | **alto** | basso | alto |
| Costo di manutenzione | medio | **alto** | basso | **alto** |
| Velocità della suite | buona | media | buona | **scarsa** |
| Stabilità | alta | alta | alta | **bassa** |

---

## Conseguenze

### Positive

- Il codice critico (mutazioni e autorizzazioni) è verificato integralmente.
- I difetti di isolamento tenant sono intercettati prima della produzione.
- L'erosione architetturale viene rilevata automaticamente.
- Il refactoring è possibile senza timore.
- Il codice generato dagli agenti passa dagli stessi controlli.
- Le differenze tra SQLite e MySQL emergono in pipeline, non in produzione.

### Negative (accettate consapevolmente)

- **Tempo di sviluppo maggiore nell'immediato.** Ogni operazione richiede tre test.
- **Suite da mantenere.** I test sono codice: invecchiano e vanno curati.
- **Doppia esecuzione in pipeline.** Il tempo di CI raddoppia sulla parte di test.
- **Vincolo del 100% percepito come rigido.** Su un'Action banale, il test sembra superfluo.
- **Test di isolamento più lenti.** Creano tenant reali: sono la parte lenta della suite.

### Impatto operativo

| Area | Effetto |
|---|---|
| Sviluppo | test scritto insieme al codice, non dopo |
| Revisione | la checklist verifica le tre categorie di test per operazione |
| Pipeline | soglie bloccanti; doppia esecuzione |
| Correzione dei bug | test di regressione obbligatorio prima della correzione |

---

## Soglia di rivalutazione

1. Se la suite completa supera i **20 minuti**: valutare parallelizzazione o riduzione dei test di
   isolamento (mantenendone almeno uno per aggregato).
2. Se il vincolo del 100% su Action e Policy viene sistematicamente aggirato con test vuoti:
   il problema è culturale e la soglia non serve.
3. Se le differenze SQLite/MySQL non producono più alcun fallimento per due anni: valutare la
   riduzione della doppia esecuzione ai soli test che toccano lo schema.

---

## Verifica

| Verifica | Strumento | Automatica |
|---|---|---|
| Copertura complessiva ≥ 80% | pipeline | sì |
| Copertura Action e Policy = 100% | pipeline con filtro | sì |
| Test di architettura presenti e verdi | pipeline | sì |
| Un test di isolamento per entità | script di verifica | sì |
| Suite eseguita su SQLite e MySQL | pipeline | sì |
| Tre test per operazione | revisione | no |
| Ogni bug ha il suo test di regressione | revisione | no |

---

## Riferimenti

- [Strategia di testing](../../docs/04-quality/01-testing-strategy.md)
- [Regole di testing](../../rules/testing.md)
- [Agente di testing](../../agents/13-testing-agent.md)
- [Template di test](../../templates/testing/README.md)
- [Metriche di qualità](../../governance/quality-metrics.md)
