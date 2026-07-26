# Fase 8 — Testing

> Completare la suite fino alle soglie e verificare che i test abbiano **valore**, non solo copertura.

| | |
|---|---|
| **Agente** | [Testing Agent](../agents/13-testing-agent.md) |
| **Gate** | [`checklists/testing-checklist.md`](../checklists/testing-checklist.md) |
| **Durata indicativa** | 2-4 ore |
| **Fase precedente** | [Fase 7 — Sicurezza](08-phase-security.md) |
| **Fase successiva** | [Fase 9 — Revisione](10-phase-review.md) |

---

## Indice

1. [Descrizione](#descrizione) 2. [Input](#input) 3. [Attività](#attività) 4. [Output](#output)
5. [Quality gate](#quality-gate) 6. [Fermate possibili](#fermate-possibili) 7. [Esempi](#esempi)
8. [Best practice](#best-practice) 9. [Errori comuni](#errori-comuni) 10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

Completare la suite fino alle soglie e verificare che i test abbiano **valore**, non solo copertura.

La fase è conclusa quando il quality gate è verde **e** le eventuali domande aperte sono state
registrate per il committente.

---

## Input

| Artefatto | Origine | Obbligatorio |
|---|---|---|
| Requisiti e criteri di accettazione | fase 1 | sì |
| Regole di business numerate | fase 1 | sì |
| Codice completo | fasi 3-6 | sì |
| Policy e test di isolamento | fase 7 | sì |
| Factory | fase 3 | sì |

Se un artefatto di input non esiste, **la fase non può iniziare**: un agente che immagina il
contesto produce lavoro da buttare.

---

## Attività

1. Rilevazione della copertura **per namespace**, non solo complessiva.
2. Test unitari mancanti su enum, value object, calcoli, politiche di dominio.
3. Per ogni operazione: i tre test (corretto, regola violata, autorizzazione negata).
4. Verifica che ogni regola di business della fase 1 abbia un test.
5. Verifica dei test di isolamento: uno per entità.
6. Test degli effetti collaterali: eventi, job, audit.
7. Test dei percorsi di errore delle integrazioni.
8. Completamento dei test di architettura.
9. **Falsificazione a campione**: rompere il codice e verificare che i test falliscano.
10. Esecuzione su SQLite e MySQL, e in ordine casuale.
11. Misurazione della durata.

---

## Output

Suite completa, rapporto di copertura per namespace, esito della falsificazione.

Più il **rapporto di fase** nel formato di [`agents/00-agent-protocol.md`](../agents/00-agent-protocol.md#il-rapporto-di-fase).

---

## Quality gate

[`checklists/testing-checklist.md`](../checklists/testing-checklist.md)

Il gate è **bloccante**: si verifica voce per voce, eseguendo davvero i comandi indicati.
Un gate spuntato senza verifica reale rende inutile l'intero processo.

In caso di fallimento: rework **chirurgico** sui soli punti respinti, massimo tre tentativi.

---

## Fermate possibili

| Caso | Quando |
|---|---|
| Un test rivela un difetto | si segnala all'agente competente, non si adatta il test |
| Comportamento atteso non specificato | serve il committente |

Una fermata non è un fallimento: è il funzionamento corretto del processo su una decisione che non
compete a un agente.

---

## Esempi

Esempi di invocazione, di output e di violazioni sono nel file dell'agente:
[Testing Agent](../agents/13-testing-agent.md).

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
| Modificare il codice per far passare il test | Il difetto resta, mascherato | Segnalare |
| Test che non possono fallire | Copertura senza valore | Falsificazione a campione |
| Copertura raggiunta su codice banale | Aree critiche scoperte | Soglia doppia |
| Test di autorizzazione negata omesso | I difetti più gravi passano | Tre test per operazione |
| Percorsi di errore non verificati | Comportamento indefinito sui guasti esterni | `Http::fake()` con errori |
| Test dipendenti dall'ordine | Fallimenti intermittenti | Esecuzione casuale |

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
- [Testing Agent](../agents/13-testing-agent.md) · [Checklist](../checklists/testing-checklist.md)
- [Contratto `loop crea`](../prompts/loop-crea.md)
