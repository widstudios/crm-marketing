# Fase 11 — Refactoring

> Migliorare la struttura **senza cambiare il comportamento**, e registrare il debito che si sceglie di non affrontare.

| | |
|---|---|
| **Agente** | [Refactoring Agent](../agents/15-refactoring-agent.md) |
| **Gate** | suite verde senza modifiche ai test funzionali |
| **Durata indicativa** | 1-2 ore |
| **Fase precedente** | [Fase 10 — Prestazioni](11-phase-performance.md) |
| **Fase successiva** | [Fase 12 — Documentazione](13-phase-documentation.md) |

---

## Indice

1. [Obiettivo](#obiettivo) 2. [Input](#input) 3. [Attività](#attività) 4. [Output](#output)
5. [Quality gate](#quality-gate) 6. [Fermate possibili](#fermate-possibili) 7. [Esempi](#esempi)
8. [Best practice](#best-practice) 9. [Errori comuni](#errori-comuni) 10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Obiettivo

Migliorare la struttura **senza cambiare il comportamento**, e registrare il debito che si sceglie di non affrontare.

La fase è conclusa quando il quality gate è verde **e** le eventuali domande aperte sono state
registrate per il committente.

---

## Input

| Artefatto | Origine | Obbligatorio |
|---|---|---|
| Codice completo | fasi 3-10 | sì |
| Rapporti di revisione | fase 9 | sì |
| Rapporto prestazioni | fase 10 | sì |
| Suite di test | fase 8 | sì |

Se un artefatto di input non esiste, **la fase non può iniziare**.

---

## Attività

1. Verifica della copertura sulle aree candidate: senza test, si scrivono prima i test caratterizzanti.
2. Individuazione delle duplicazioni significative.
3. Individuazione delle classi con responsabilità confuse e dei metodi troppo lunghi.
4. Individuazione del codice morto e dei nomi fuorvianti.
5. Refactoring, **uno per volta**, con suite verde prima e dopo ciascuno.
6. Registrazione del debito tecnico non affrontato, con costo e rientro previsti.
7. Individuazione dei candidati alla promozione nella Foundation.
8. Verifica finale: suite verde **senza alcuna modifica ai test funzionali**.

---

## Output

Codice ristrutturato, `docs/quality/technical-debt.md`, `docs/quality/promotion-candidates.md`.

Più il **rapporto di fase** nel formato di [`agents/00-agent-protocol.md`](../agents/00-agent-protocol.md#il-rapporto-di-fase).

---

## Quality gate

suite verde senza modifiche ai test funzionali

Il gate è **bloccante**: si verifica voce per voce, eseguendo davvero i comandi indicati.
In caso di fallimento: rework **chirurgico**, massimo tre tentativi.

---

## Fermate possibili

| Caso | Quando |
|---|---|
| Un refactoring richiederebbe di cambiare il comportamento | non è refactoring: compete a un'altra fase |
| Un candidato alla promozione è evidente | la promozione è una decisione di governance |

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
| Rifattorizzare senza test | Regressioni silenziose | Test caratterizzanti prima |
| Modificare i test per farli passare | Il comportamento è cambiato senza dichiararlo | Suite invariata |
| Refactoring e correzione insieme | Impossibile isolare una regressione | Commit separati |
| Riscrivere codice stabile | Si perdono anni di prova sul campo | Non toccare ciò che funziona |
| Debito non registrato | Nessuno sa cosa è stato rimandato | Registro con costo e rientro |
| Promozione autonoma nella Foundation | Decisione di governance saltata | Proporre candidati |

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
