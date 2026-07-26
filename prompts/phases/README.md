# Prompt di fase

> I prompt operativi delle quattordici fasi vivono nei file degli agenti. Questo indice li mappa.

---

## Indice

1. [Descrizione](#descrizione) 2. [Mappa fase → prompt](#mappa-fase--prompt)
3. [Perché non sono duplicati qui](#perché-non-sono-duplicati-qui) 4. [Composizione](#composizione)
5. [Esempi](#esempi) 6. [Best practice](#best-practice) 7. [Errori comuni](#errori-comuni)
8. [Checklist](#checklist) 9. [Riferimenti](#riferimenti)

---

## Descrizione

Ogni fase del master workflow ha un prompt operativo completo. Quel prompt vive nella sezione
«Prompt completo» del file dell'agente responsabile, insieme a responsabilità, input, output, limiti
e quality gate della stessa fase.

Questo documento è la mappa che li collega.

---

## Mappa fase → prompt

| Fase | Nome | Prompt |
|---|---|---|
| 0 | Fondazione | [`agents/01-foundation-agent.md`](../../agents/01-foundation-agent.md#prompt-completo) |
| 1 | Analisi | [`agents/02-business-analyst-agent.md`](../../agents/02-business-analyst-agent.md#prompt-completo) |
| 2 | Architettura | [`agents/03-architect-agent.md`](../../agents/03-architect-agent.md#prompt-completo) |
| 3 | Database | [`agents/04-database-agent.md`](../../agents/04-database-agent.md#prompt-completo) |
| 4 | Backend | [`agents/06-backend-agent.md`](../../agents/06-backend-agent.md#prompt-completo) |
| 5 | Amministrazione | [`agents/07-filament-agent.md`](../../agents/07-filament-agent.md#prompt-completo) |
| 6 | Frontend | [`agents/05-frontend-agent.md`](../../agents/05-frontend-agent.md#prompt-completo) |
| 7 | Sicurezza | [`agents/08-security-agent.md`](../../agents/08-security-agent.md#prompt-completo) |
| 8 | Testing | [`agents/13-testing-agent.md`](../../agents/13-testing-agent.md#prompt-completo) |
| 9a | Revisione | [`agents/11-reviewer-agent.md`](../../agents/11-reviewer-agent.md#prompt-completo) |
| 9b | Revisione indipendente | [`agents/12-claude-reviewer.md`](../../agents/12-claude-reviewer.md#prompt-completo) |
| 10 | Prestazioni | [`agents/09-performance-agent.md`](../../agents/09-performance-agent.md#prompt-completo) |
| 11 | Refactoring | [`agents/15-refactoring-agent.md`](../../agents/15-refactoring-agent.md#prompt-completo) |
| 12 | Documentazione | [`agents/10-documentation-agent.md`](../../agents/10-documentation-agent.md#prompt-completo) |
| 13 | Deploy | [`agents/14-deploy-agent.md`](../../agents/14-deploy-agent.md#prompt-completo) |
| — | Orchestrazione | [`agents/16-orchestrator-agent.md`](../../agents/16-orchestrator-agent.md#prompt-completo) |

---

## Perché non sono duplicati qui

Un prompt duplicato in due posti diverge: si corregge in uno e non nell'altro, e dopo tre mesi
nessuno sa quale sia quello valido.

Il prompt vive **accanto** al resto della definizione dell'agente — responsabilità, limiti, gate —
perché è coerente con essi e va modificato insieme.

---

## Composizione

Un'invocazione completa è composta da:

```
prompts/system/00-base-system-prompt.md      identità, fonti di verità, regole non negoziabili
prompts/system/01-guardrails.md              divieti assoluti
prompts/system/02-output-format.md           formato del rapporto
prompts/snippets/*.md                        solo quelli pertinenti alla fase
agents/NN-*.md § Prompt completo             il compito della fase
+ contesto                                   artefatti di input, con percorso
```

---

## Esempi

### Composizione per la fase 3 (Database)

```
system/00-base-system-prompt.md
system/01-guardrails.md
system/02-output-format.md
snippets/tenant-context.md
snippets/uncertainty.md
snippets/quality-gate.md
agents/04-database-agent.md § Prompt completo
+ docs/requirements/02-entities.md
+ docs/requirements/04-use-cases.md
+ docs/architecture/06-data-placement.md
```

### Composizione per la fase 12 (Documentazione)

```
system/00-base-system-prompt.md
system/01-guardrails.md
system/02-output-format.md
snippets/quality-gate.md
agents/10-documentation-agent.md § Prompt completo
+ tutto il codice e i rapporti delle fasi precedenti
```

`tenant-context.md` è omesso: la fase non tocca dati.

---

## Best practice

- Comporre l'invocazione seguendo l'ordine indicato: sistema, guardrail, formato, snippet, fase.
- Includere solo gli snippet pertinenti.
- Fornire gli artefatti di contesto **come file**, non riassunti a parole.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Prompt duplicato in due posti | Divergono, nessuno sa quale vale | Un solo posto |
| Prompt di sistema omesso | Regole non negoziabili ignorate | Anteporlo sempre |
| Tutti gli snippet inclusi | Attenzione diluita | Solo i pertinenti |
| Contesto riassunto a parole | Dettagli persi | Artefatti come file |

---

## Checklist

- [ ] Ho identificato la fase e il file dell'agente.
- [ ] Ho composto l'invocazione nell'ordine corretto.
- [ ] Ho incluso solo gli snippet pertinenti.
- [ ] Ho fornito gli artefatti di contesto come file.

---

## Riferimenti

- [Prompt](../README.md) · [Snippet](../snippets/README.md)
- [Indice degli agenti](../../agents/README.md)
- [Master workflow](../../workflows/00-master-workflow.md)
