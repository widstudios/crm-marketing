# Fase 0 — Fondazione

> Trasformare un repository vuoto in un progetto Laravel multitenant funzionante e conforme, **senza alcuna logica di dominio**.

| | |
|---|---|
| **Agente** | [Foundation Agent](../agents/01-foundation-agent.md) |
| **Gate** | [`checklists/foundation-checklist.md`](../checklists/foundation-checklist.md) |
| **Durata indicativa** | 30-60 minuti |
| **Fase precedente** | — (prima fase) |
| **Fase successiva** | [Fase 1 — Analisi](02-phase-analysis.md) |

---

## Indice

1. [Obiettivo](#obiettivo) 2. [Input](#input) 3. [Attività](#attività) 4. [Output](#output)
5. [Quality gate](#quality-gate) 6. [Fermate possibili](#fermate-possibili) 7. [Esempi](#esempi)
8. [Best practice](#best-practice) 9. [Errori comuni](#errori-comuni) 10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Obiettivo

Trasformare un repository vuoto in un progetto Laravel multitenant funzionante e conforme, **senza alcuna logica di dominio**.

La fase è conclusa quando il quality gate è verde **e** le eventuali domande aperte sono state
registrate per il committente.

---

## Input

| Artefatto | Origine | Obbligatorio |
|---|---|---|
| Nome del progetto | comando `loop crea` | sì |
| Project Brief | committente | sì |
| Versione della Factory | tag del repository | sì |
| Vincoli infrastrutturali noti | brief | no |

Se un artefatto di input non esiste, **la fase non può iniziare**: un agente che immagina il
contesto produce lavoro da buttare.

---

## Attività

1. Verifica dei prerequisiti: ambiente, accesso al registro Composer, versione della Factory.
2. Inizializzazione di Laravel LTS e installazione della Foundation.
3. Creazione della struttura a livelli, comprese le cartelle che resteranno vuote.
4. Configurazione delle connessioni `landlord` e `tenant`.
5. Ambiente Docker completo, con la stessa immagine per tutti i servizi PHP.
6. Strumenti di qualità: Pint, PHPStan livello 8, Pest, script Composer.
7. Test di architettura di base.
8. Pannelli Filament vuoti: Super Admin e Tenant Admin.
9. Migration landlord della Foundation e seeder di sistema.
10. Provisioning di **due** tenant di sviluppo.
11. Pipeline CI.
12. `CLAUDE.md` e `README.md` di progetto.
13. Verifica completa da zero (`docker compose down -v` e ricostruzione).

---

## Output

Scheletro del progetto: struttura a livelli, configurazioni, ambiente Docker, pipeline CI,
pannelli vuoti, due tenant di sviluppo, documentazione di avvio.

Più il **rapporto di fase** nel formato di [`agents/00-agent-protocol.md`](../agents/00-agent-protocol.md#il-rapporto-di-fase).

---

## Quality gate

[`checklists/foundation-checklist.md`](../checklists/foundation-checklist.md)

Il gate è **bloccante**: si verifica voce per voce, eseguendo davvero i comandi indicati.
Un gate spuntato senza verifica reale rende inutile l'intero processo.

In caso di fallimento: rework **chirurgico** sui soli punti respinti, massimo tre tentativi.

---

## Fermate possibili

| Caso | Quando |
|---|---|
| Precondizione non soddisfatta | ambiente non funzionante, registro Composer non raggiungibile |
| Vincolo infrastrutturale in conflitto con lo stack | il committente impone una tecnologia fuori standard |

Una fermata non è un fallimento: è il funzionamento corretto del processo su una decisione che non
compete a un agente.

---

## Esempi

Esempi di invocazione, di output e di violazioni sono nel file dell'agente:
[Foundation Agent](../agents/01-foundation-agent.md).

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
| Struttura per tipo tecnico | Nessun vincolo di dipendenza verificabile | Struttura per livello |
| Cartelle vuote omesse | Le fasi successive collocano male gli artefatti | Struttura completa |
| Un solo tenant di sviluppo | I difetti di isolamento non emergono | Due tenant |
| Baseline PHPStan generata subito | Debito dal primo giorno | Nessuna baseline |
| README non verificato da zero | La procedura non funziona altrove | Prova su ambiente pulito |
| Logica di dominio anticipata | Sovrapposizione con le fasi 1-4 | Restare nell'ambito |

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
- [Foundation Agent](../agents/01-foundation-agent.md) · [Checklist](../checklists/foundation-checklist.md)
- [Contratto `loop crea`](../prompts/loop-crea.md)
