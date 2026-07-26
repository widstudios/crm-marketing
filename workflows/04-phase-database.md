# Fase 3 — Database

> Tradurre il modello di dominio in schema: migration, indici, vincoli, seeder, factory.

| | |
|---|---|
| **Agente** | [Database Agent](../agents/04-database-agent.md) |
| **Gate** | [`checklists/database-checklist.md`](../checklists/database-checklist.md) |
| **Durata indicativa** | 1-3 ore |
| **Fase precedente** | [Fase 2 — Architettura](03-phase-architecture.md) |
| **Fase successiva** | [Fase 4 — Backend](05-phase-backend.md) |

---

## Indice

1. [Obiettivo](#obiettivo) 2. [Input](#input) 3. [Attività](#attività) 4. [Output](#output)
5. [Quality gate](#quality-gate) 6. [Fermate possibili](#fermate-possibili) 7. [Esempi](#esempi)
8. [Best practice](#best-practice) 9. [Errori comuni](#errori-comuni) 10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Obiettivo

Tradurre il modello di dominio in schema: migration, indici, vincoli, seeder, factory.

La fase è conclusa quando il quality gate è verde **e** le eventuali domande aperte sono state
registrate per il committente.

---

## Input

| Artefatto | Origine | Obbligatorio |
|---|---|---|
| Entità con attributi e ciclo di vita | fase 1 | sì |
| Filtri e ordinamenti dei casi d'uso | fase 1 | sì |
| Volumi attesi e vincoli normativi | fase 1 | sì |
| Bounded context, moduli, collocazione dei dati | fase 2 | sì |

Se un artefatto di input non esiste, **la fase non può iniziare**: un agente che immagina il
contesto produce lavoro da buttare.

---

## Attività

1. Traduzione di ogni entità in tabella, con tipi conformi.
2. Vincoli di integrità e unicità, con comportamento esplicito in cancellazione.
3. Derivazione degli indici dai filtri e dagli ordinamenti **dichiarati**.
4. Verifica dell'ordine delle colonne negli indici compositi.
5. Scrittura delle migration, una modifica logica per file.
6. Implementazione e **prova** di `down()`.
7. Seeder di sistema idempotenti; seeder di prova separati.
8. Factory con stati nominati per i casi limite.
9. Esecuzione su SQLite **e** su MySQL.
10. Provisioning di un tenant nuovo, da zero.
11. Rollback completo e riesecuzione.

---

## Output

Migration landlord e tenant, seeder di sistema e di prova, factory, documento sullo schema.

Più il **rapporto di fase** nel formato di [`agents/00-agent-protocol.md`](../agents/00-agent-protocol.md#il-rapporto-di-fase).

---

## Quality gate

[`checklists/database-checklist.md`](../checklists/database-checklist.md)

Il gate è **bloccante**: si verifica voce per voce, eseguendo davvero i comandi indicati.
Un gate spuntato senza verifica reale rende inutile l'intero processo.

In caso di fallimento: rework **chirurgico** sui soli punti respinti, massimo tre tentativi.

---

## Fermate possibili

| Caso | Quando |
|---|---|
| Regola di business necessaria allo schema non specificata | due opzioni producono schemi diversi |
| Vincolo di conservazione non chiaro | determina soft delete, audit, archiviazione |

Una fermata non è un fallimento: è il funzionamento corretto del processo su una decisione che non
compete a un agente.

---

## Esempi

Esempi di invocazione, di output e di violazioni sono nel file dell'agente:
[Database Agent](../agents/04-database-agent.md).

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
| Colonna `tenant_id` | Fraintendimento del modello | Il tenant è il database |
| `float` per importi | Errori di arrotondamento | `decimal` |
| `down()` mancante o non provato | Rollback impossibile su N tenant | Implementarlo e provarlo |
| Trasformazione dati in migration | Deploy lentissimo, rollback impossibile | Comando dedicato |
| Indici preventivi | Scritture rallentate su N database | Solo per query dichiarate |
| Seeder non idempotente | Deploy fallito alla seconda esecuzione | `firstOrCreate` |
| Verifica solo su SQLite | Migration che falliscono in produzione | Esecuzione su MySQL |
| Provisioning non verificato | Migration che funzionano solo sugli aggiornamenti | Tenant nuovo da zero |

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
- [Database Agent](../agents/04-database-agent.md) · [Checklist](../checklists/database-checklist.md)
- [Contratto `loop crea`](../prompts/loop-crea.md)
