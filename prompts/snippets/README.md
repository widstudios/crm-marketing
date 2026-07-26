# Snippet di prompt

> Frammenti componibili, riusati da più agenti. Scritti una volta, versionati una volta.

---

## Indice

1. [Descrizione](#descrizione) 2. [Indice degli snippet](#indice-degli-snippet)
3. [Regole di composizione](#regole-di-composizione) 4. [Esempi](#esempi)
5. [Best practice](#best-practice) 6. [Errori comuni](#errori-comuni) 7. [Checklist](#checklist)
8. [Riferimenti](#riferimenti)

---

## Descrizione

Gli stessi vincoli servono a più agenti: l'isolamento dei tenant riguarda il Database Agent quanto il
Backend e il Filament Agent. Ripeterli in ogni prompt li fa divergere; gli snippet li tengono in un
posto solo.

---

## Indice degli snippet

| Snippet | Contenuto | Usato da |
|---|---|---|
| [tenant-context.md](tenant-context.md) | vincoli di isolamento multitenant | Database, Backend, Filament, Frontend, Security, Performance |
| [quality-gate.md](quality-gate.md) | come si verifica il gate | tutti |
| [report-format.md](report-format.md) | formato del rapporto di fase | tutti |
| [uncertainty.md](uncertainty.md) | assunzione o domanda? | tutti |

---

## Regole di composizione

**R1.** Si includono **solo** gli snippet pertinenti alla fase.
*Motivo:* ogni blocco in più diluisce l'attenzione sugli elementi che contano.

**R2.** Gli snippet vanno **dopo** il prompt di sistema e **prima** del prompt di fase.

**R3.** Un vincolo presente in uno snippet non si ripete nel prompt di fase.

**R4.** Uno snippet non contiene istruzioni specifiche di una fase: se le contiene, appartiene al
prompt dell'agente.

**R5.** Ogni snippet dichiara quali agenti lo usano: serve a valutare l'impatto di una modifica.

---

## Esempi

### Composizione per la fase Backend

```
prompts/system/00-base-system-prompt.md
prompts/system/01-guardrails.md
prompts/system/02-output-format.md
prompts/snippets/tenant-context.md
prompts/snippets/uncertainty.md
prompts/snippets/quality-gate.md
[prompt completo di agents/06-backend-agent.md]
+ contesto: requisiti, architettura, schema
```

### Composizione per la fase Documentazione

```
prompts/system/00-base-system-prompt.md
prompts/system/01-guardrails.md
prompts/system/02-output-format.md
prompts/snippets/quality-gate.md
[prompt completo di agents/10-documentation-agent.md]
```

`tenant-context.md` non serve: la fase non tocca dati.

---

## Best practice

- Aggiungere uno snippet quando lo stesso vincolo compare in tre prompt.
- Versionare gli snippet come i prompt: una loro modifica cambia il comportamento di più agenti.
- Verificare l'impatto prima di modificare uno snippet usato da sei agenti.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Tutti gli snippet inclusi sempre | Attenzione diluita | Solo i pertinenti |
| Vincoli duplicati tra snippet e prompt di fase | Divergono nel tempo | Un solo posto |
| Istruzioni di fase dentro uno snippet | Non riusabile | Spostare nel prompt dell'agente |
| Modifica senza verificare gli utilizzatori | Effetti su agenti non previsti | Elenco degli utilizzatori |

---

## Checklist

- [ ] Gli snippet inclusi sono pertinenti alla fase.
- [ ] Nessun vincolo è duplicato tra snippet e prompt di fase.
- [ ] Ogni snippet dichiara i propri utilizzatori.
- [ ] Le modifiche sono state valutate su tutti gli utilizzatori.

---

## Riferimenti

- [Prompt](../README.md) · [Prompt di sistema](../system/00-base-system-prompt.md)
- [Indice degli agenti](../../agents/README.md)
