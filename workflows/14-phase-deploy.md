# Fase 13 — Deploy

> Rendere il progetto rilasciabile: immagini, pipeline, procedure di rilascio e rollback, backup, monitoraggio, runbook.

| | |
|---|---|
| **Agente** | [Deploy Agent](../agents/14-deploy-agent.md) |
| **Gate** | [`checklists/release-checklist.md`](../checklists/release-checklist.md) |
| **Durata indicativa** | 2-4 ore |
| **Fase precedente** | [Fase 12 — Documentazione](13-phase-documentation.md) |
| **Fase successiva** | — (ultima fase) |

---

## Indice

1. [Descrizione](#descrizione) 2. [Input](#input) 3. [Attività](#attività) 4. [Output](#output)
5. [Quality gate](#quality-gate) 6. [Fermate possibili](#fermate-possibili) 7. [Esempi](#esempi)
8. [Best practice](#best-practice) 9. [Errori comuni](#errori-comuni) 10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

Rendere il progetto rilasciabile: immagini, pipeline, procedure di rilascio e rollback, backup, monitoraggio, runbook.

La fase è conclusa quando il quality gate è verde **e** le eventuali domande aperte sono state
registrate per il committente.

---

## Input

| Artefatto | Origine | Obbligatorio |
|---|---|---|
| Progetto completo e documentato | fasi 0-12 | sì |
| Volumi attesi | fase 1 | sì |
| Vincoli normativi su conservazione e backup | fase 1 | sì |
| Integrazioni esterne | fase 12 | sì |

Se un artefatto di input non esiste, **la fase non può iniziare**.

---

## Attività

1. Immagine di produzione multi-stage, senza strumenti di sviluppo.
2. Compose per staging e produzione, con la stessa composizione di servizi.
3. Pipeline CI completa, dal controllo più veloce al più lento.
4. Pipeline di deploy automatizzata.
5. Script di rilascio con la sequenza vincolata.
6. Script di rollback, **provato su staging**.
7. Backup automatico, con verifica settimanale del ripristino.
8. Health check distinti: `/health`, `/health/live`, `/health/ready`.
9. Monitoraggio con metriche **per tenant** e allarmi con azione attesa.
10. Runbook operativi, ciascuno provato.
11. Irrigidimento della configurazione di produzione.
12. **Prova completa su staging**: rilascio, verifica, rollback, nuovo rilascio.

---

## Output

Immagini, compose, pipeline, script di rilascio e rollback, configurazione di backup e
monitoraggio, runbook operativi.

Più il **rapporto di fase** nel formato di [`agents/00-agent-protocol.md`](../agents/00-agent-protocol.md#il-rapporto-di-fase).

---

## Quality gate

[`checklists/release-checklist.md`](../checklists/release-checklist.md)

Il gate è **bloccante**: si verifica voce per voce, eseguendo davvero i comandi indicati.
In caso di fallimento: rework **chirurgico**, massimo tre tentativi.

---

## Fermate possibili

| Caso | Quando |
|---|---|
| Rollback non funziona su staging | blocco: la procedura non è pronta |
| Ripristino del backup fallisce | blocco: il backup non è un backup |
| Vincolo infrastrutturale non soddisfacibile | serve una decisione del committente |

---

## Esempi

Esempi di invocazione e di output sono nei file degli agenti coinvolti.

---

## Best practice

- Verificare gli input prima di iniziare.
- Fornire il contesto **pertinente**, non l'intero repository.
- Leggere per prime «assunzioni» e «domande aperte».
- Verificare il gate voce per voce.
- Registrare tempo ed esito.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Rollback mai provato | Non funziona quando serve | Prova su staging |
| Backup non verificato | Ripristino impossibile | Verifica del ripristino |
| Passaggi manuali nella sequenza | Dimenticati sotto pressione | Automazione completa |
| `queue:restart` omesso | I worker eseguono il codice vecchio | Nella sequenza |
| Migration tenant tutte insieme | Database saturato | Esecuzione a lotti |
| Allarmi senza azione attesa | Vengono ignorati | Destinatario e azione |
| Runbook non provati | Inutilizzabili durante un incidente | Prova pratica |

---

## Checklist

- [ ] Gli artefatti di input esistono.
- [ ] L'invocazione è completa.
- [ ] Il rapporto di fase ha tutte le sezioni.
- [ ] Gate verificato voce per voce.
- [ ] Esito e durata registrati.

---

## Riferimenti

- [Master workflow](00-master-workflow.md) · [Workflow](README.md)
- [Contratto `loop crea`](../prompts/loop-crea.md) · [Checklist](../checklists/README.md)
