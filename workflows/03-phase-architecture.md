# Fase 2 — Architettura

> Definire la struttura: bounded context, moduli, contratti, eventi, collocazione dei dati, permessi.

| | |
|---|---|
| **Agente** | [Architect Agent](../agents/03-architect-agent.md) |
| **Gate** | [`checklists/architecture-checklist.md`](../checklists/architecture-checklist.md) |
| **Durata indicativa** | 1-2 ore |
| **Fase precedente** | [Fase 1 — Analisi](02-phase-analysis.md) |
| **Fase successiva** | [Fase 3 — Database](04-phase-database.md) |

---

## Indice

1. [Descrizione](#descrizione) 2. [Input](#input) 3. [Attività](#attività) 4. [Output](#output)
5. [Quality gate](#quality-gate) 6. [Fermate possibili](#fermate-possibili) 7. [Esempi](#esempi)
8. [Best practice](#best-practice) 9. [Errori comuni](#errori-comuni) 10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

Definire la struttura: bounded context, moduli, contratti, eventi, collocazione dei dati, permessi.

La fase è conclusa quando il quality gate è verde **e** le eventuali domande aperte sono state
registrate per il committente.

---

## Input

| Artefatto | Origine | Obbligatorio |
|---|---|---|
| Requisiti, entità, attori, regole | fase 1 | sì |
| Vincoli normativi e volumi | fase 1 | sì |
| Catalogo dei moduli della Factory | Factory | sì |

Se un artefatto di input non esiste, **la fase non può iniziare**: un agente che immagina il
contesto produce lavoro da buttare.

---

## Attività

1. Individuazione dei bounded context, dove cambia il significato dei termini.
2. Raggruppamento delle entità per contesto.
3. **Confronto con il catalogo**: cosa si riusa, cosa si costruisce, con motivazione.
4. Definizione dei moduli e dei manifesti.
5. Dipendenze: obbligatorie al minimo, facoltative tramite eventi.
6. Contratti pubblicati ed eventi di integrazione.
7. Scelta del grado di purezza per contesto, con motivazione.
8. Collocazione landlord/tenant per ogni entità.
9. Derivazione dei permessi da attori e casi d'uso.
10. Traduzione dei vincoli normativi in requisiti architetturali.
11. ADR di progetto per le decisioni non ovvie.

---

## Output

Documenti di architettura in `docs/architecture/`, ADR di progetto, manifesti dei moduli.

Più il **rapporto di fase** nel formato di [`agents/00-agent-protocol.md`](../agents/00-agent-protocol.md#il-rapporto-di-fase).

---

## Quality gate

[`checklists/architecture-checklist.md`](../checklists/architecture-checklist.md)

Il gate è **bloccante**: si verifica voce per voce, eseguendo davvero i comandi indicati.
Un gate spuntato senza verifica reale rende inutile l'intero processo.

In caso di fallimento: rework **chirurgico** sui soli punti respinti, massimo tre tentativi.

---

## Fermate possibili

| Caso | Quando |
|---|---|
| Conflitto tra requisito e regola vincolante | serve una ADR o una deroga |
| Ambito non coperto dal catalogo né chiaramente delimitato | serve una decisione del committente |
| Vincolo normativo con impatto architetturale dubbio | serve conferma legale |

Una fermata non è un fallimento: è il funzionamento corretto del processo su una decisione che non
compete a un agente.

---

## Esempi

Esempi di invocazione, di output e di violazioni sono nel file dell'agente:
[Architect Agent](../agents/03-architect-agent.md).

---

## Best practice

- Verificare gli input prima di iniziare.
- Fornire all'agente il contesto **pertinente**, non l'intero repository.
- Leggere per prime le sezioni «assunzioni» e «domande aperte» del rapporto.
- Verificare il gate voce per voce.
- Registrare tempo ed esito: servono alle metriche di processo.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Contesti su criteri tecnici | Confini che non corrispondono al dominio | Linguaggio del dominio |
| Modulo costruito invece che riusato | Duplicazione, manutenzione doppia | Confronto con il catalogo |
| Troppe dipendenze obbligatorie | Moduli non indipendenti | `optional` + eventi |
| Dipendenza circolare | Ordine di caricamento impossibile | Ripensare i confini |
| Entità di dominio nel landlord | Isolamento compromesso | Criterio di collocazione |
| Decisioni non registrate | Motivazione perduta | ADR di progetto |

---

## Checklist

- [ ] Gli artefatti di input esistono.
- [ ] L'invocazione contiene identità, regole, contesto, compito e gate.
- [ ] Il rapporto di fase è completo, con tutte le sezioni.
- [ ] Assunzioni e domande aperte lette e registrate.
- [ ] Gate verificato voce per voce.
- [ ] Esito e durata registrati nel registro di esecuzione.

---

## Riferimenti

- [Master workflow](00-master-workflow.md) · [Workflow](README.md)
- [Architect Agent](../agents/03-architect-agent.md) · [Checklist](../checklists/architecture-checklist.md)
- [Contratto `loop crea`](../prompts/loop-crea.md)
