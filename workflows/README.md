# Workflow

> Il processo della Factory: fasi, quality gate, transizioni. Chi fa cosa, quando, e cosa deve
> essere vero per proseguire.

---

## Indice

1. [Descrizione](#descrizione) 2. [Indice dei workflow](#indice-dei-workflow)
3. [Struttura di un documento di fase](#struttura-di-un-documento-di-fase)
4. [I quality gate](#i-quality-gate) 5. [Esempi](#esempi) 6. [Best practice](#best-practice)
7. [Errori comuni](#errori-comuni) 8. [Checklist](#checklist) 9. [Riferimenti](#riferimenti)

---

## Descrizione

Il workflow definisce le **transizioni**, non solo le attività: cosa deve essere vero perché una
fase possa considerarsi conclusa e la successiva possa iniziare.

È questo che distingue un processo da un elenco di cose da fare.

---

## Indice dei workflow

### Master workflow

| Documento | Contenuto |
|---|---|
| [00-master-workflow.md](00-master-workflow.md) | le quattordici fasi, le dipendenze, i gate |

### Fasi

| # | Fase | Documento | Agente |
|---|---|---|---|
| 0 | Fondazione | [01-phase-foundation.md](01-phase-foundation.md) | Foundation |
| 1 | Analisi | [02-phase-analysis.md](02-phase-analysis.md) | Business Analyst |
| 2 | Architettura | [03-phase-architecture.md](03-phase-architecture.md) | Architect |
| 3 | Database | [04-phase-database.md](04-phase-database.md) | Database |
| 4 | Backend | [05-phase-backend.md](05-phase-backend.md) | Backend |
| 5 | Amministrazione | [06-phase-filament.md](06-phase-filament.md) | Filament |
| 6 | Frontend | [07-phase-frontend.md](07-phase-frontend.md) | Frontend |
| 7 | Sicurezza | [08-phase-security.md](08-phase-security.md) | Security |
| 8 | Testing | [09-phase-testing.md](09-phase-testing.md) | Testing |
| 9 | Revisione | [10-phase-review.md](10-phase-review.md) | Reviewer + Claude Reviewer |
| 10 | Prestazioni | [11-phase-performance.md](11-phase-performance.md) | Performance |
| 11 | Refactoring | [12-phase-refactoring.md](12-phase-refactoring.md) | Refactoring |
| 12 | Documentazione | [13-phase-documentation.md](13-phase-documentation.md) | Documentation |
| 13 | Deploy | [14-phase-deploy.md](14-phase-deploy.md) | Deploy |

### Workflow speciali

| Documento | Quando |
|---|---|
| [20-module-workflow.md](20-module-workflow.md) | aggiungere un modulo a un progetto esistente |
| [21-hotfix-workflow.md](21-hotfix-workflow.md) | correzione urgente in produzione |
| [22-release-workflow.md](22-release-workflow.md) | rilascio di una versione |

---

## Struttura di un documento di fase

| Sezione | Contenuto |
|---|---|
| Obiettivo | cosa deve essere vero al termine |
| Agente | chi la esegue |
| Input | artefatti richiesti, con la fase che li produce |
| Attività | i passaggi, in ordine |
| Output | artefatti prodotti |
| Quality gate | la checklist bloccante |
| Fermate possibili | quando la fase si interrompe e chiede |
| Durata indicativa | ordine di grandezza |

---

## I quality gate

Ogni fase termina con un gate **bloccante**: non si passa alla successiva con un gate rosso.

```
FASE N ──▶ QUALITY GATE ──┬── verde ──▶ FASE N+1
                          │
                          └── rosso ──▶ REWORK (max 3) ──▶ FERMATA
```

| Principio | Motivo |
|---|---|
| Il gate è bloccante | un difetto che passa costa un ordine di grandezza in più a valle |
| Il rework è chirurgico | un rework generico fa rigenerare anche ciò che era corretto |
| Massimo tre rework | al terzo, il problema è a monte |
| Il gate si verifica voce per voce | un gate spuntato senza verifica rende inutile il processo |

---

## Esempi

### Esempio 1 — perché i gate sono bloccanti

Uno schema con un indice mancante passa la fase 3. Le fasi 4-8 costruiscono sopra: Action, Query,
resource, test.

Il problema emerge alla fase 10, con dati realistici: l'elenco impiega otto secondi. La correzione
ora richiede una migration, la revisione delle Query, la modifica dei test.

Con il gate rispettato, sarebbe stata una riga nella migration.

### Esempio 2 — rework chirurgico

```
Il gate della fase 3 è fallito su due voci:
- voce 7: `create_movements_table` non implementa down()
- voce 11: manca l'indice su movements.batch_id

Correggi **solo** questi due punti. Non rigenerare le altre migration.
```

---

## Best practice

- Verificare il gate voce per voce, eseguendo davvero i comandi indicati.
- Non saltare una fase perché «piccola»: la fase 7 (sicurezza) è quella che si salta più spesso, ed
  è quella che protegge dai difetti più gravi.
- Registrare gli esiti: sono la base delle metriche di processo.
- Al terzo rework, fermarsi e diagnosticare la fase precedente.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Gate saltato per fretta | Difetto amplificato a valle | Gate bloccanti |
| Gate spuntato senza verifica | Il processo diventa inaffidabile | Verifica voce per voce |
| Rework generico | Si perde ciò che era corretto | Rework chirurgico |
| Quarto rework | Si accumula il problema | Fermata e diagnosi |
| Fase iniziata senza input | L'agente inventa il contesto | Verifica degli input |

---

## Checklist

- [ ] Ho identificato la fase corrente e i suoi input.
- [ ] Gli artefatti di input esistono.
- [ ] Ho verificato il gate voce per voce.
- [ ] I rework sono chirurgici e non superano i tre tentativi.
- [ ] Gli esiti sono registrati.

---

## Riferimenti

- [Master workflow](00-master-workflow.md) · [Contratto `loop crea`](../prompts/loop-crea.md)
- [Indice degli agenti](../agents/README.md) · [Checklist](../checklists/README.md)
- [Orchestrator Agent](../agents/16-orchestrator-agent.md)
