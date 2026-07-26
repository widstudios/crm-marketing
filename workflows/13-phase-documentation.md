# Fase 12 — Documentazione

> Produrre la documentazione che permette a chi arriva dopo di capire il sistema senza chiedere a chi l'ha costruito.

| | |
|---|---|
| **Agente** | [Documentation Agent](../agents/10-documentation-agent.md) |
| **Gate** | [`checklists/documentation-checklist.md`](../checklists/documentation-checklist.md) |
| **Durata indicativa** | 2-4 ore |
| **Fase precedente** | [Fase 11 — Refactoring](12-phase-refactoring.md) |
| **Fase successiva** | [Fase 13 — Deploy](14-phase-deploy.md) |

---

## Indice

1. [Obiettivo](#obiettivo) 2. [Input](#input) 3. [Attività](#attività) 4. [Output](#output)
5. [Quality gate](#quality-gate) 6. [Fermate possibili](#fermate-possibili) 7. [Esempi](#esempi)
8. [Best practice](#best-practice) 9. [Errori comuni](#errori-comuni) 10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Obiettivo

Produrre la documentazione che permette a chi arriva dopo di capire il sistema senza chiedere a chi l'ha costruito.

La fase è conclusa quando il quality gate è verde **e** le eventuali domande aperte sono state
registrate per il committente.

---

## Input

| Artefatto | Origine | Obbligatorio |
|---|---|---|
| Requisiti e casi d'uso | fase 1 | sì |
| Architettura e ADR | fase 2 | sì |
| Codice completo | fasi 3-11 | sì |
| Debito tecnico registrato | fase 11 | sì |
| Deroghe attive | tutte le fasi | sì |

Se un artefatto di input non esiste, **la fase non può iniziare**.

---

## Attività

1. Verifica di ciò che è stato **effettivamente implementato**.
2. README di progetto, con procedura di avvio **provata da zero**.
3. README di ogni modulo.
4. OpenAPI: ogni endpoint, con esempi, errori e limiti.
5. Manuale utente per ruolo, con ogni procedura **eseguita** durante la scrittura.
6. Verifica e completamento delle ADR di progetto.
7. Changelog in linguaggio dell'utente.
8. `CLAUDE.md` con deroghe attive e scadenze.
9. Documentazione delle integrazioni, con comportamento degradato.
10. Verifica dei link interni.

---

## Output

README di progetto e di modulo, OpenAPI, manuale utente, ADR complete, changelog, documentazione
delle integrazioni.

Più il **rapporto di fase** nel formato di [`agents/00-agent-protocol.md`](../agents/00-agent-protocol.md#il-rapporto-di-fase).

---

## Quality gate

[`checklists/documentation-checklist.md`](../checklists/documentation-checklist.md)

Il gate è **bloccante**: si verifica voce per voce, eseguendo davvero i comandi indicati.
In caso di fallimento: rework **chirurgico**, massimo tre tentativi.

---

## Fermate possibili

| Caso | Quando |
|---|---|
| Comportamento documentato non implementato | si segnala, non si documenta l'intenzione |
| Motivazione di una decisione non ricostruibile | si segnala come lacuna, non si inventa |

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
| Documentare l'intenzione invece dell'implementato | La documentazione mente e viene creduta | Verificare contro il codice |
| Procedura di avvio non provata | Non funziona su una macchina pulita | Prova da zero |
| Manuale organizzato per schermata | L'utente non trova ciò che cerca | Struttura per attività |
| Motivazioni inventate nelle ADR | Ragionamento falso tramandato | Segnalare la lacuna |
| Changelog in linguaggio tecnico | Il cliente non lo legge | Linguaggio dell'utente |
| Debito tecnico omesso | Sorpresa per il committente | Documentarlo |

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
