# Architecture Decision Records

> Il registro delle decisioni architetturali: cosa è stato deciso, quando, perché, quali
> alternative sono state scartate e a quale costo.

---

## Indice

1. [Descrizione](#descrizione)
2. [Indice delle ADR](#indice-delle-adr)
3. [Come si legge una ADR](#come-si-legge-una-adr)
4. [Come si scrive una ADR](#come-si-scrive-una-adr)
5. [Stati](#stati)
6. [ADR di progetto](#adr-di-progetto)
7. [Esempi](#esempi)
8. [Best practice](#best-practice)
9. [Errori comuni](#errori-comuni)
10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

Le ADR sono la **fonte di verità più alta** della Factory: quando un documento contraddice una ADR
accettata, il documento è sbagliato.

Il loro valore non è nella decisione — quella si vede dal codice — ma nel **contesto**: perché
sembrava giusta, quali alternative c'erano, quali costi sono stati accettati consapevolmente.
È questa informazione che permette, due anni dopo, di capire se la decisione ha ancora senso.

---

## Indice delle ADR

| # | Titolo | Stato | Data | Impatto |
|---|---|---|---|---|
| [0001](0001-stack-tecnologico.md) | Stack tecnologico | Accettata | 2026-07-25 | tutti i progetti |
| [0002](0002-tenant-isolation-strategy.md) | Strategia di isolamento dei tenant | Accettata | 2026-07-25 | architettura, operations |
| [0003](0003-layered-architecture.md) | Architettura a livelli | Accettata | 2026-07-25 | tutto il codice |
| [0004](0004-modular-system.md) | Sistema modulare | Accettata | 2026-07-25 | struttura dei progetti |
| [0005](0005-action-pattern.md) | Action Pattern per le mutazioni | Accettata | 2026-07-25 | livello applicativo |
| [0006](0006-permission-model.md) | Modello dei permessi | Accettata | 2026-07-25 | sicurezza |
| [0007](0007-testing-strategy.md) | Strategia di testing | Accettata | 2026-07-25 | qualità |
| [0008](0008-agent-orchestration.md) | Orchestrazione degli agenti | Accettata | 2026-07-25 | processo |

Numerazione progressiva, senza buchi, mai riutilizzata.

---

## Come si legge una ADR

Ogni ADR ha la stessa struttura. Le sezioni che contano davvero:

| Sezione | Perché leggerla |
|---|---|
| **Contesto** | il problema reale che ha portato alla decisione |
| **Alternative** | cosa è stato scartato e per quale motivo |
| **Conseguenze negative** | i costi accettati consapevolmente |
| **Soglia di rivalutazione** | quando la decisione va riconsiderata |

Le prime due dicono se la decisione è ancora valida nel contesto attuale. La terza evita di
scoprire un costo credendolo un difetto. La quarta dice quando tornare a discuterne.

Una ADR senza «conseguenze negative» compilata è una ADR che non ha analizzato il problema:
ogni decisione tecnica ha un costo.

---

## Come si scrive una ADR

1. Copiare [`template.md`](template.md).
2. Assegnare il numero successivo.
3. Compilare **prima** dell'implementazione, in stato `Proposta`.
4. Confrontare almeno due alternative reali sugli assi di
   [`governance/decision-process.md`](../../governance/decision-process.md).
5. Dichiarare le conseguenze, comprese quelle negative.
6. Indicare la soglia di rivalutazione.
7. Aprire la discussione secondo i tempi previsti.
8. Se accettata: aggiornare questo indice, i documenti operativi impattati e, se breaking, la guida
   di migrazione.

Una ADR scritta dopo l'implementazione non è una decisione: è una giustificazione, e vale molto meno.

---

## Stati

| Stato | Significato |
|---|---|
| `Proposta` | scritta, non ancora discussa |
| `In discussione` | aperta al confronto, con scadenza |
| `Accettata` | vincolante da questo momento |
| `Rifiutata` | valutata e scartata, con motivazione |
| `Ritirata` | ritirata dall'autore prima della discussione |
| `Deprecata` | non più applicabile, senza sostituto |
| `Superata da ADR-XXXX` | sostituita da una decisione successiva |

**Le ADR non si cancellano e non si riscrivono.** Una decisione superata resta a documentare
perché era sembrata giusta: è quella l'informazione che evita di ripetere l'errore.

---

## ADR di progetto

Un progetto può avere ADR proprie, in `docs/decisions/` del suo repository.

| Caso | Esempio |
|---|---|
| Decisione di dominio | come si calcola la scadenza di un lotto |
| Deviazione da una regola della Factory | archivio time-series per la telemetria |
| Scelta tecnica locale | libreria per la firma digitale |
| Compromesso accettato | denormalizzazione di un aggregato |

Le ADR di progetto **non** possono contraddire quelle della Factory: possono solo restringere o
documentare un'eccezione motivata, con scadenza.

Numerazione separata (`P0001`, `P0002`) per evitare confusione con quelle di piattaforma.

---

## Esempi

### Esempio 1 — ADR che ha evitato una discussione ricorrente

Prima di [ADR-0002](0002-tenant-isolation-strategy.md), la domanda «database separati o colonna
`tenant_id`?» tornava ad ogni avvio di progetto, con esiti diversi a seconda di chi partecipava.

Dopo, la domanda si risolve in dieci minuti di lettura. Si ridiscute solo se il contesto cambia, e
la ADR dichiara esplicitamente quando: oltre 500 tenant per installazione.

### Esempio 2 — ADR superata correttamente

```markdown
# ADR-0011 — TenantId come Value Object

**Stato:** Accettata
**Supera:** ADR-0009 (identificatori come stringa)

## Contesto
La ADR-0009 stabiliva l'uso di stringhe per gli identificatori di tenant. Con la crescita del
numero di punti che li manipolano, sono emersi tre difetti da confusione tra slug e nome del
database.
```

La ADR-0009 viene marcata `Superata da ADR-0011`, ma il suo testo non si tocca.

---

## Best practice

- Scrivere la ADR prima di implementare.
- Documentare le alternative scartate: è la parte più utile a distanza di tempo.
- Dichiarare le conseguenze negative accettate.
- Indicare una soglia di rivalutazione misurabile.
- Superare una ADR con una nuova, mai riscrivere la vecchia.
- Linkare la ADR dai documenti operativi che ne discendono.
- Tenerla corta: una pagina densa vale più di dieci vaghe.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| ADR scritta dopo l'implementazione | Diventa una giustificazione | Scriverla prima |
| Nessuna alternativa considerata | Impossibile rivalutare | Minimo due alternative reali |
| Conseguenze negative non dichiarate | Analisi superficiale, costi scoperti come difetti | Sezione obbligatoria |
| Riscrivere una ADR accettata | Si perde la storia | Nuova ADR che supera la vecchia |
| Nessuna soglia di rivalutazione | La decisione diventa dogma | Soglia misurabile |
| ADR di progetto che contraddice la Factory | Divergenza | Solo restrizioni o eccezioni motivate |
| Decisione strutturale senza ADR | Motivazione perduta in mesi | ADR obbligatoria |

---

## Checklist

- [ ] La ADR ha numero progressivo, data e stato.
- [ ] È stata scritta prima dell'implementazione.
- [ ] Confronta almeno due alternative reali.
- [ ] Dichiara le conseguenze, comprese quelle negative.
- [ ] Indica una soglia di rivalutazione.
- [ ] È linkata da questo indice e dai documenti operativi impattati.
- [ ] Se supera una ADR precedente, entrambe sono collegate.
- [ ] Se è breaking, esiste la guida di migrazione.

---

## Riferimenti

- [Template ADR](template.md)
- [Processo decisionale](../../governance/decision-process.md)
- [Architettura di riferimento](../README.md)
- [Guide di migrazione](../../governance/migrations/README.md)
