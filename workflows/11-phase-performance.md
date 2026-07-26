# Fase 10 — Prestazioni

> Portare l'applicazione entro gli obiettivi di prestazione, misurando prima e dopo, sul tenant più grande.

| | |
|---|---|
| **Agente** | [Performance Agent](../agents/09-performance-agent.md) |
| **Gate** | [`checklists/performance-checklist.md`](../checklists/performance-checklist.md) |
| **Durata indicativa** | 1-3 ore |
| **Fase precedente** | [Fase 9 — Revisione](10-phase-review.md) |
| **Fase successiva** | [Fase 11 — Refactoring](12-phase-refactoring.md) |

---

## Indice

1. [Obiettivo](#obiettivo) 2. [Input](#input) 3. [Attività](#attività) 4. [Output](#output)
5. [Quality gate](#quality-gate) 6. [Fermate possibili](#fermate-possibili) 7. [Esempi](#esempi)
8. [Best practice](#best-practice) 9. [Errori comuni](#errori-comuni) 10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Obiettivo

Portare l'applicazione entro gli obiettivi di prestazione, misurando prima e dopo, sul tenant più grande.

La fase è conclusa quando il quality gate è verde **e** le eventuali domande aperte sono state
registrate per il committente.

---

## Input

| Artefatto | Origine | Obbligatorio |
|---|---|---|
| Volumi attesi | fase 1 | sì |
| Filtri e ordinamenti dichiarati | fase 1 | sì |
| Codice completo | fasi 3-8 | sì |
| Rapporti di revisione | fase 9 | sì |

Se un artefatto di input non esiste, **la fase non può iniziare**.

---

## Attività

1. Popolamento di un tenant con volumi **realistici**, non simbolici.
2. Misurazione iniziale: tempi, query per richiesta, memoria, per ogni schermata principale.
3. Diagnosi delle sei cause ricorrenti: N+1, indice mancante, dati non paginati, aggregati
   ricalcolati, operazioni sincrone lente, serializzazione eccessiva.
4. Correzione, una causa per volta, con misurazione dopo ciascuna.
5. Test di regressione sul numero di query.
6. Verifica dell'**invarianza**: la suite funzionale passa senza modifiche ai test.
7. Misurazione finale, confrontabile.

---

## Output

Ottimizzazioni, indici aggiuntivi, test di regressione, rapporto con le misurazioni prima e dopo.

Più il **rapporto di fase** nel formato di [`agents/00-agent-protocol.md`](../agents/00-agent-protocol.md#il-rapporto-di-fase).

---

## Quality gate

[`checklists/performance-checklist.md`](../checklists/performance-checklist.md)

Il gate è **bloccante**: si verifica voce per voce, eseguendo davvero i comandi indicati.
In caso di fallimento: rework **chirurgico**, massimo tre tentativi.

---

## Fermate possibili

| Caso | Quando |
|---|---|
| Obiettivi non raggiungibili senza modifiche architetturali | serve una decisione dell'Architect |
| Denormalizzazione necessaria | serve valutare il meccanismo di verifica della coerenza |

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
| Ottimizzare senza misurare | Modifiche senza beneficio dimostrato | Misurazione preliminare |
| Misurare su dati simbolici | I problemi non emergono | Volumi del tenant più grande |
| Cache al posto dell'indice | Sintomo curato, causa intatta | Indice prima |
| Denormalizzare senza verifica | Disallineamento silenzioso | Ricalcolo periodico |
| Nessun test di regressione | L'N+1 torna al prossimo refactoring | Test sul numero di query |
| Comportamento cambiato | Non è un'ottimizzazione | Suite invariata |

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
