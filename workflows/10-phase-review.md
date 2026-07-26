# Fase 9 — Revisione

> Verificare la conformità alle regole e, con una seconda lettura indipendente, ciò che una verifica di conformità non trova.

| | |
|---|---|
| **Agente** | [Reviewer Agent](../agents/11-reviewer-agent.md) + [Claude Reviewer](../agents/12-claude-reviewer.md) |
| **Gate** | [`checklists/code-review-checklist.md`](../checklists/code-review-checklist.md) |
| **Durata indicativa** | 1-2 ore |
| **Fase precedente** | [Fase 8 — Testing](09-phase-testing.md) |
| **Fase successiva** | [Fase 10 — Prestazioni](11-phase-performance.md) |

---

## Indice

1. [Obiettivo](#obiettivo) 2. [Input](#input) 3. [Attività](#attività) 4. [Output](#output)
5. [Quality gate](#quality-gate) 6. [Fermate possibili](#fermate-possibili) 7. [Esempi](#esempi)
8. [Best practice](#best-practice) 9. [Errori comuni](#errori-comuni) 10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Obiettivo

Verificare la conformità alle regole e, con una seconda lettura indipendente, ciò che una verifica di conformità non trova.

La fase è conclusa quando il quality gate è verde **e** le eventuali domande aperte sono state
registrate per il committente.

---

## Input

| Artefatto | Origine | Obbligatorio |
|---|---|---|
| Tutti gli artefatti delle fasi 0-8 | fasi precedenti | sì |
| Rapporti di fase, con assunzioni e domande | fasi precedenti | sì |
| Requisiti e regole di business | fase 1 | sì |
| Deroghe attive | `CLAUDE.md` di progetto | sì |

Se un artefatto di input non esiste, **la fase non può iniziare**.

---

## Attività

La fase ha **due passaggi**, in quest'ordine.

### 9a — Reviewer Agent (conformità)

1. Matrice requisiti → artefatti: ogni requisito è implementato?
2. Verifica per categoria, in ordine di gravità: sicurezza e isolamento, correttezza, violazioni di
   regole, prestazioni, test, manutenibilità, documentazione.
3. Verifica della coerenza tra le fasi.
4. Classificazione delle violazioni, con correzione e agente competente per ciascuna.

### 9b — Claude Reviewer (indipendente)

Legge il codice **senza** i rapporti intermedi, e cerca ciò che la conformità non trova:

1. Fraintendimenti del dominio.
2. Assunzioni non dichiarate.
3. Codice plausibile ma inutile.
4. Conformità formale senza sostanza.
5. Test privi di valore.
6. Incoerenze tra schema, dominio, interfaccia e API.
7. Casi limite del dominio reale.
8. Rischi non evidenti.

**Solo alla fine** legge il rapporto del Reviewer Agent e produce il confronto: cosa ha trovato lui,
cosa ha trovato l'altro, **cosa nessuno dei due ha verificato**.

L'ultima voce indica dove il processo di revisione ha un punto cieco.

---

## Output

`docs/quality/review-report.md` e `docs/quality/independent-review.md`, con le violazioni
classificate e assegnate.

Più il **rapporto di fase** nel formato di [`agents/00-agent-protocol.md`](../agents/00-agent-protocol.md#il-rapporto-di-fase).

---

## Quality gate

[`checklists/code-review-checklist.md`](../checklists/code-review-checklist.md)

Il gate è **bloccante**: si verifica voce per voce, eseguendo davvero i comandi indicati.
In caso di fallimento: rework **chirurgico**, massimo tre tentativi.

---

## Fermate possibili

| Caso | Quando |
|---|---|
| Violazione di sicurezza o isolamento | blocco: correzione prima di proseguire |
| Requisito non implementato | blocco: manca funzionalità richiesta |
| Fraintendimento del dominio | serve conferma del committente |

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
| Correggere invece di segnalare | Le fasi si sovrappongono | Solo rilevazione |
| Segnalare questioni di stile | Rumore che nasconde i difetti | Lo stile lo fa Pint |
| Non citare la regola | La segnalazione sembra un'opinione | Regola e numero |
| Claude Reviewer che legge prima l'altro rapporto | Attenzione orientata, si trova meno | Analisi indipendente prima |
| Tutto classificato come bloccante | Rapporto inutilizzabile | Classificazione per gravità |
| Nessuna matrice requisiti | I requisiti dimenticati non emergono | Matrice obbligatoria |

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
