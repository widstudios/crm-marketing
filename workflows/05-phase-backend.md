# Fase 4 — Backend

> Implementare dominio e livello applicativo: al termine ogni operazione è invocabile e testata, **senza interfaccia**.

| | |
|---|---|
| **Agente** | [Backend Agent](../agents/06-backend-agent.md) |
| **Gate** | [`checklists/backend-checklist.md`](../checklists/backend-checklist.md) |
| **Durata indicativa** | 4-8 ore |
| **Fase precedente** | [Fase 3 — Database](04-phase-database.md) |
| **Fase successiva** | [Fase 5 — Amministrazione](06-phase-filament.md) e [Fase 6 — Frontend](07-phase-frontend.md) |

---

## Indice

1. [Obiettivo](#obiettivo) 2. [Input](#input) 3. [Attività](#attività) 4. [Output](#output)
5. [Quality gate](#quality-gate) 6. [Fermate possibili](#fermate-possibili) 7. [Esempi](#esempi)
8. [Best practice](#best-practice) 9. [Errori comuni](#errori-comuni) 10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Obiettivo

Implementare dominio e livello applicativo: al termine ogni operazione è invocabile e testata, **senza interfaccia**.

La fase è conclusa quando il quality gate è verde **e** le eventuali domande aperte sono state
registrate per il committente.

---

## Input

| Artefatto | Origine | Obbligatorio |
|---|---|---|
| Requisiti, casi d'uso, regole di business | fase 1 | sì |
| Architettura, moduli, grado di purezza, contratti | fase 2 | sì |
| Schema, migration, factory | fase 3 | sì |

Se un artefatto di input non esiste, **la fase non può iniziare**: un agente che immagina il
contesto produce lavoro da buttare.

---

## Attività

Costruzione **dall'interno verso l'esterno**, per bounded context:

1. Enum di dominio con transizioni e capacità.
2. Value object per i dati con vincoli.
3. Eccezioni di dominio con costruttori nominati.
4. Entità, secondo il grado di purezza dichiarato.
5. Eventi di dominio.
6. Contratti dei repository e loro implementazioni.
7. DTO.
8. Action, una per mutazione dei casi d'uso.
9. Query object per le letture complesse.
10. Service, solo dove serve coordinamento.
11. Job e listener per gli effetti collaterali.
12. Form Request, API Resource, controller.
13. Test unitari e di feature, scritti **insieme** a ciascun artefatto.

---

## Output

Livelli `Domain/`, `Application/`, `Infrastructure/`, punti di ingresso HTTP, job, listener e i
test corrispondenti.

Più il **rapporto di fase** nel formato di [`agents/00-agent-protocol.md`](../agents/00-agent-protocol.md#il-rapporto-di-fase).

---

## Quality gate

[`checklists/backend-checklist.md`](../checklists/backend-checklist.md)

Il gate è **bloccante**: si verifica voce per voce, eseguendo davvero i comandi indicati.
Un gate spuntato senza verifica reale rende inutile l'intero processo.

In caso di fallimento: rework **chirurgico** sui soli punti respinti, massimo tre tentativi.

---

## Fermate possibili

| Caso | Quando |
|---|---|
| Regola di business non specificata | il comportamento non è deducibile dai requisiti |
| Schema insufficiente | serve una modifica che compete alla fase 3 |
| Conflitto tra requisito e regola vincolante | serve una ADR |

Una fermata non è un fallimento: è il funzionamento corretto del processo su una decisione che non
compete a un agente.

---

## Esempi

Esempi di invocazione, di output e di violazioni sono nel file dell'agente:
[Backend Agent](../agents/06-backend-agent.md).

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
| Partire dal controller | Dominio modellato su una schermata | Dall'interno verso l'esterno |
| Action che riceve `Request` | Inutilizzabile da CLI, coda, importazioni | DTO |
| Regole sparse nelle Action | Duplicate quando l'entità serve altrove | Regole nel dominio |
| Autorizzazione dentro l'Action | Non invocabile da processi di sistema | Nel punto di ingresso |
| Evento dentro la transazione | Listener che non trova i dati | Dopo il commit |
| Job senza `TenantAware` | Scrittura nel database sbagliato | Trait obbligatorio |
| Test scritti alla fine | Casi limite dimenticati | Test insieme al codice |
| Schema modificato in autonomia | Sovrapposizione con la fase 3 | Segnalare la necessità |

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
- [Backend Agent](../agents/06-backend-agent.md) · [Checklist](../checklists/backend-checklist.md)
- [Contratto `loop crea`](../prompts/loop-crea.md)
