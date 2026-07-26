# Checklist

> I quality gate operativi: liste verificabili, non promemoria. Se una voce non è controllabile in
> modo oggettivo, non appartiene a una checklist.

---

## Indice

1. [Descrizione](#descrizione) 2. [Indice delle checklist](#indice-delle-checklist)
3. [Come si usa una checklist](#come-si-usa-una-checklist) 4. [Come si scrive una voce](#come-si-scrive-una-voce)
5. [Esempi](#esempi) 6. [Best practice](#best-practice) 7. [Errori comuni](#errori-comuni)
8. [Checklist](#checklist) 9. [Riferimenti](#riferimenti)

---

## Descrizione

Le checklist sono la controparte **eseguibile** delle regole: dove `rules/` dice cosa è obbligatorio,
le checklist dicono come si verifica che lo sia.

Sono il meccanismo che rende bloccanti i quality gate del workflow.

---

## Indice delle checklist

### Quality gate di fase

| Checklist | Fase | Agente |
|---|---|---|
| [foundation-checklist.md](foundation-checklist.md) | 0 | Foundation |
| [analysis-checklist.md](analysis-checklist.md) | 1 | Business Analyst |
| [architecture-checklist.md](architecture-checklist.md) | 2 | Architect |
| [database-checklist.md](database-checklist.md) | 3 | Database |
| [backend-checklist.md](backend-checklist.md) | 4 | Backend |
| [filament-checklist.md](filament-checklist.md) | 5 | Filament |
| [frontend-checklist.md](frontend-checklist.md) | 6 | Frontend |
| [security-checklist.md](security-checklist.md) | 7 | Security |
| [testing-checklist.md](testing-checklist.md) | 8 | Testing |
| [code-review-checklist.md](code-review-checklist.md) | 9 | Reviewer |
| [performance-checklist.md](performance-checklist.md) | 10 | Performance |
| [documentation-checklist.md](documentation-checklist.md) | 12 | Documentation |
| [release-checklist.md](release-checklist.md) | 13 | Deploy |

### Trasversali

| Checklist | Quando |
|---|---|
| [definition-of-done.md](definition-of-done.md) | prima di considerare conclusa una modifica |
| [api-checklist.md](api-checklist.md) | prima di pubblicare o modificare un endpoint |
| [operations-checklist.md](operations-checklist.md) | verifiche periodiche di esercizio |

---

## Come si usa una checklist

**R1.** Si verifica **voce per voce**, eseguendo davvero i comandi indicati.
Non esiste una terza possibilità tra «verificata» e «non verificata». «Presumibilmente a posto» è
«non verificata».

**R2.** Si riporta l'esito **reale**, non quello atteso.

**R3.** Le voci non applicabili si dichiarano, con la motivazione.

**R4.** Una checklist con voci non soddisfatte è un **gate rosso**: si dichiara e si torna alla fase.

**R5.** Una checklist spuntata senza verifica reale è una violazione grave del protocollo: rende
inutile l'intero processo, perché fa credere che il controllo sia stato fatto.

---

## Come si scrive una voce

Una voce è ammissibile se due persone diverse, verificandola sullo stesso artefatto, arrivano allo
stesso esito.

```markdown
✗  - [ ] Il codice è ben scritto.
✗  - [ ] Le prestazioni sono adeguate.
✗  - [ ] La sicurezza è stata considerata.

✓  - [ ] Ogni classe in `Actions/` è `final` e ha un solo metodo pubblico.
✓  - [ ] L'elenco dei lotti produce meno di 15 query con 50 righe.
✓  - [ ] Ogni model ha una Policy registrata che nega in assenza di permesso.
```

Le voci che richiedono un comando lo riportano:

```markdown
- [ ] Suite verde su MySQL: `php artisan test --env=testing-mysql`
```

---

## Esempi

### Esempio 1 — esito riportato correttamente

```markdown
### Quality gate
- checklists/database-checklist.md: 16/18 soddisfatte.

Voci non soddisfatte:
- voce 7 (`down()` implementato): `create_movements_table` non lo implementa.
- voce 11 (indice sui filtri): manca l'indice su `movements.batch_id`.

Voci non applicabili:
- voce 15 (seeder di prova): questa fase non ne produce.
```

### Esempio 2 — voce riscritta per essere verificabile

```markdown
✗  - [ ] La cache è usata correttamente.
✓  - [ ] Ogni chiamata a `Cache::` passa da `TenantCacheKey::for()`.
✓  - [ ] Il test di isolamento della cache tra tenant è presente e verde.
```

---

## Best practice

- Verificare durante il lavoro, non solo alla fine: le correzioni costano meno.
- Riportare l'esito voce per voce, non un giudizio complessivo.
- Aggiungere una voce quando lo stesso difetto sfugge per la seconda volta.
- Rimuovere le voci che nessuno riesce a verificare in modo oggettivo.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Voce non verificabile | Due revisori, due esiti | Riformularla in modo oggettivo |
| Checklist spuntata senza verifica | Il processo diventa inaffidabile | Verifica reale |
| Esito atteso al posto di quello reale | Difetti che emergono a valle | Esito reale |
| Voci non applicabili omesse | Non si distingue da «non verificata» | Dichiararle |
| Checklist troppo lunga | Nessuno la legge davvero | Solo ciò che intercetta difetti reali |
| Gate rosso non dichiarato | L'orchestratore avanza su basi false | Dichiararlo |

---

## Checklist

- [ ] Ho identificato la checklist della fase.
- [ ] Ho verificato ogni voce, eseguendo i comandi indicati.
- [ ] Ho riportato l'esito reale, voce per voce.
- [ ] Ho dichiarato le voci non applicabili con la motivazione.
- [ ] Se il gate è rosso, l'ho dichiarato invece di correggerlo in silenzio.

---

## Riferimenti

- [Workflow](../workflows/README.md) · [Master workflow](../workflows/00-master-workflow.md)
- [Indice delle regole](../rules/README.md) · [Protocollo agenti](../agents/00-agent-protocol.md)
- [Snippet quality gate](../prompts/snippets/quality-gate.md)
