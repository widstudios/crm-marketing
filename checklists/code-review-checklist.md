# Checklist — Revisione del codice

> Che cosa si guarda, in quale ordine, e che cosa non è compito della revisione umana o di un
> agente revisore.

| | |
|---|---|
| **Fase** | 9 — Review |
| **Agente** | [Reviewer Agent](../agents/11-reviewer-agent.md) · [Claude Reviewer](../agents/12-claude-reviewer.md) |
| **Natura** | gate bloccante |

---

## Indice

1. [Descrizione](#descrizione) 2. [Verifiche](#verifiche) 3. [Comandi di verifica](#comandi-di-verifica)
4. [Esempi](#esempi) 5. [Best practice](#best-practice) 6. [Errori comuni](#errori-comuni)
7. [Checklist](#checklist) 8. [Riferimenti](#riferimenti)

---

## Descrizione

La revisione non ripete ciò che gli strumenti già verificano. Formattazione, tipi mancanti, helper
di debug residui: li intercettano Pint, PHPStan e i test di architettura, e in CI, prima che una
persona apra il diff.

La revisione si occupa di ciò che nessuno strumento vede: se il codice fa la cosa **giusta**, se
sarà comprensibile fra due anni, e se ciò che è stato costruito corrisponde a ciò che era stato
chiesto.

Il [Claude Reviewer](../agents/12-claude-reviewer.md) applica questa stessa checklist, ma legge il
codice **prima** dei report delle fasi precedenti: è la sua indipendenza che rende la verifica utile.

Ogni voce si verifica in modo oggettivo. Le voci si riportano **una per una**, con l'esito reale.

---

## Verifiche

### Correttezza rispetto ai requisiti

- [ ] Ogni requisito della fase 1 ha un artefatto corrispondente.
- [ ] Ogni regola di business dichiarata è implementata **dove dichiarato**, non altrove.
- [ ] Nessuna funzionalità presente nel codice ma assente dal brief.
- [ ] Le assunzioni dichiarate dagli agenti sono ancora vere alla luce del codice consegnato.
- [ ] Nessuna domanda aperta è stata decisa in silenzio da un agente.

### Collocazione della logica

- [ ] Nessuna logica nei controller, nei model, nei Filament Resource, nei Livewire component.
- [ ] Le regole di dominio stanno nel dominio, non nelle Action.
- [ ] Le Action orchestrano, non decidono le regole.
- [ ] Nessuna regola duplicata in due punti: se compare due volte, appartiene al dominio.
- [ ] Le dipendenze puntano **verso l'interno**: il dominio non conosce il framework.

### Struttura

- [ ] Ogni classe ha una responsabilità dichiarabile in una frase, senza «e».
- [ ] Ogni Action è `final` con un solo metodo pubblico `execute()`.
- [ ] Ogni Action riceve un DTO, mai una `Request`.
- [ ] I DTO sono `final readonly` con proprietà tipizzate.
- [ ] Nessun metodo supera le ~20 righe senza una ragione visibile.
- [ ] Nessun parametro booleano che seleziona due comportamenti diversi.
- [ ] Nessuna astrazione introdotta per un solo caso d'uso.

### Nomi e leggibilità

- [ ] I nomi dicono **che cosa**, non **come**: nessun `data`, `info`, `manager`, `helper`, `utils`.
- [ ] Gli identificatori di codice sono in inglese; la documentazione e i messaggi utente in
      italiano.
- [ ] I nomi del dominio corrispondono al glossario della fase 1, senza sinonimi introdotti.
- [ ] I commenti spiegano **perché**, mai **cosa**: nessun commento che ripete il codice.
- [ ] Nessun codice commentato lasciato nel diff.
- [ ] Nessun `TODO` o `FIXME` senza riferimento a un elemento tracciato.

### Robustezza

- [ ] I casi limite del dominio sono gestiti: valori nulli, quantità zero, insiemi vuoti.
- [ ] Gli errori producono eccezioni di dominio, non `Exception` generiche.
- [ ] Nessun `catch` silenzioso: ogni eccezione intercettata è registrata o rilanciata.
- [ ] Nessuna dipendenza da un ordine di esecuzione implicito.
- [ ] Le scritture multiple sono in transazione; nessuna chiamata esterna dentro la transazione.
- [ ] Nessuna condizione di corsa sulle risorse contese senza lock.

### Sicurezza — verifica di secondo livello

- [ ] Ogni punto di ingresso introdotto autorizza esplicitamente.
- [ ] Nessun input variabile usato senza lista bianca.
- [ ] Nessun dato sensibile nei log introdotti.
- [ ] Nessun segreto nel diff.

*Il gate di sicurezza resta quello della fase 7: qui si intercetta ciò che è stato introdotto
**dopo**.*

### Prestazioni — verifica di secondo livello

- [ ] Nessuna query dentro un ciclo introdotta dal diff.
- [ ] Ogni relazione usata in un elenco è caricata in anticipo.
- [ ] Nessun caricamento in memoria di insiemi non limitati.

### Test

- [ ] I test del diff verificano il **comportamento**, non l'implementazione.
- [ ] Ogni ramo di decisione introdotto ha almeno un test.
- [ ] Nessun test modificato per farlo passare invece di correggere il codice.

### Coerenza con la Factory

- [ ] Nessun pattern nuovo introdotto senza ADR.
- [ ] Nessuna dipendenza nuova senza giustificazione scritta.
- [ ] Nessun codice duplicato da un altro progetto invece di essere promosso nella Foundation.
- [ ] Ogni deroga a una regola cita la regola per numero, la motivazione e la data di scadenza.

---

## Comandi di verifica

```bash
git diff --stat main...HEAD          # dimensione e distribuzione del diff
composer qa                          # lint + analisi statica + test
composer test:arch
php tooling/scripts/check-authorization.php   # punti di ingresso senza autorizzazione
```

Ordine di lettura consigliato, dal più costoso da correggere al meno:

1. **modello di dominio** — un errore qui si propaga ovunque;
2. **Action e regole** — è dove vive il comportamento;
3. **schema e migration** — costa dati, non codice;
4. **punti di ingresso** — autorizzazione e validazione;
5. **presentazione** — è la parte più facile da correggere.

---

## Esempi

### Rilievo formulato bene

```markdown
- `RegisterMovementAction:34` — la verifica «lotto non scaduto» è nell'Action.
  Regola: rules/action-pattern.md R7 (le regole di dominio stanno nel dominio).
  Conseguenza: la stessa verifica manca nell'importazione massiva, che scrive movimenti
  su lotti scaduti.
  Correzione: spostare in `Batch::assertUsable()` e invocarla da entrambi i percorsi.
```

### Rilievo formulato male

```markdown
- L'Action non mi convince, forse andrebbe rivista.
```

Senza posizione, regola violata, conseguenza e correzione, un rilievo non è verificabile e non può
essere risolto da chi lo riceve.

### Esito con voci non soddisfatte

```markdown
### Quality gate
- checklists/code-review-checklist.md: N-2/N soddisfatte.

Voci non soddisfatte:
- Regole di dominio nel dominio: vedi rilievo su `RegisterMovementAction:34`.
- Nessun `catch` silenzioso: `ImportSuppliersAction:88` intercetta `Throwable` e prosegue,
  senza registrare nulla. Le righe scartate spariscono senza traccia.

Richiedo rework su questi punti.
```

---

## Best practice

- Leggere prima il modello di dominio, poi il resto: l'ordine cambia ciò che si nota.
- Formulare ogni rilievo con posizione, regola, conseguenza e correzione proposta.
- Distinguere il rilievo **bloccante** dal suggerimento, e dirlo esplicitamente.
- Motivare ogni rilievo con una regola, non con una preferenza personale.
- Se un rilievo si ripete su progetti diversi, non ripeterlo: proporre una regola o un test di
  architettura che lo intercetti da solo.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Rivedere ciò che gli strumenti già verificano | Tempo speso, difetti reali non visti | Delegare a Pint, PHPStan, arch test |
| Rilievo senza regola citata | Diventa una discussione di gusti | Citare la regola per numero |
| Rilievo senza correzione proposta | Chi riceve non sa cosa fare | Proporre la correzione |
| Bloccante e suggerimento mescolati | Il rework colpisce le cose sbagliate | Separarli esplicitamente |
| Revisione a partire dai report | Si eredita l'errore di chi ha scritto | Leggere prima il codice |
| Approvare per non ritardare la consegna | Il difetto arriva in produzione | Gate rosso dichiarato |
| Riscrivere il codice invece di segnalarlo | Nessuno impara, il rilievo torna | Segnalare, non riscrivere |

---

## Checklist

- [ ] Ho letto il codice prima dei report delle fasi precedenti.
- [ ] Ho verificato ogni voce, non un'impressione complessiva.
- [ ] Ogni rilievo cita posizione, regola, conseguenza e correzione.
- [ ] Ho distinto i rilievi bloccanti dai suggerimenti.
- [ ] Ho verificato la corrispondenza tra brief e codice consegnato.
- [ ] Se il gate è rosso, l'ho dichiarato.

---

## Riferimenti

- [Fase 9](../workflows/10-phase-review.md) · [Reviewer Agent](../agents/11-reviewer-agent.md) · [Claude Reviewer](../agents/12-claude-reviewer.md)
- [Regole di revisione](../rules/code-review.md) · [Definition of Done](definition-of-done.md)
- [Guida alla revisione](../docs/04-quality/02-code-review-guide.md)
