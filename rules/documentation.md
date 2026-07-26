# Regole — Documentazione

> Quali documenti sono obbligatori, quando si aggiornano, e quale struttura devono avere.

---

## Indice

1. [Descrizione](#descrizione)
2. [Documenti obbligatori](#documenti-obbligatori)
3. [Struttura dei documenti](#struttura-dei-documenti)
4. [Quando si aggiorna](#quando-si-aggiorna)
5. [Documentazione nel codice](#documentazione-nel-codice)
6. [ADR](#adr)
7. [Esempi](#esempi)
8. [Best practice](#best-practice)
9. [Errori comuni](#errori-comuni)
10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

La documentazione separata dal codice diverge: non «può divergere», diverge, ed è questione di mesi.
L'unica difesa è aggiornarla **nello stesso commit** della modifica che la rende obsoleta.

Per questo la documentazione è parte della definizione di «fatto», non un'attività successiva.

---

## Documenti obbligatori

### In un progetto generato

| Documento | Contenuto | Aggiornato |
|---|---|---|
| `README.md` | scopo, avvio, comandi, struttura | ad ogni cambio di procedura |
| `CLAUDE.md` | Factory di riferimento, deroghe, peculiarità di dominio | ad ogni deroga |
| `CHANGELOG.md` | modifiche visibili all'utente | ad ogni rilascio |
| `docs/project-brief.md` | requisiti, entità, regole di business | ad ogni chiarimento |
| `docs/decisions/` | ADR di progetto | ad ogni decisione strutturale |
| `docs/manual/` | manuale utente | ad ogni funzionalità visibile |
| Documentazione API (OpenAPI) | endpoint, parametri, risposte, errori | ad ogni modifica di API |

### In un modulo

| Documento | Contenuto |
|---|---|
| `README.md` | scopo, entità, operazioni, permessi, dipendenze, installazione |
| `docs/overview.md` | modello di dominio, stati, decisioni prese |
| `docs/checklist.md` | verifica di completezza del modulo |

**R1.** Un modulo senza documentazione non è completo.
*Verifica:* checklist di modulo. *Livello: vincolante.*

**R2.** Un endpoint API non documentato non è rilasciabile.
*Verifica:* checklist di rilascio. *Livello: vincolante.*

---

## Struttura dei documenti

**R3.** Ogni documento `.md` ha questa ossatura:

```markdown
# Titolo

> Riga di sintesi: che cos'è e a chi serve.

## Indice
## Descrizione
   …sezioni specifiche…
## Esempi
## Best practice
## Errori comuni
## Checklist
## Riferimenti
```

*Verifica:* `php tooling/scripts/check-docs.php --sections`. *Livello: vincolante.*

**R3.1.** Tre classi di documento hanno un'ossatura **propria**, perché hanno un contratto proprio
già dichiarato altrove. L'elenco è chiuso: un documento che non rientra in queste tre segue R3.

| Profilo | Documenti | Sezioni obbligatorie |
|---|---|---|
| `adr` | `architecture/decisions/0NNN-*.md` | Indice, Contesto, Decisione, Alternative, Conseguenze, Riferimenti |
| `agent` | `agents/NN-*.md`, escluso il protocollo | Indice, Identità, Responsabilità, Input, Output, Limiti, Workflow, Quality gate, Prompt completo, Errori comuni, Riferimenti |
| `repository` | `README.md` e `CONTRIBUTING.md` di primo livello | Riferimenti |

*Motivo:* una ADR è una **decisione datata**, non una guida: non ha «best practice», ha alternative
valutate e conseguenze accettate; e una checklist dentro una ADR suggerirebbe che la decisione sia
qualcosa da applicare passo passo invece che da rispettare. La specifica di un agente ha sezioni
obbligatorie che nessun altro documento ha — «Limiti» e «Prompt completo» — definite nel
[protocollo agenti](../agents/00-agent-protocol.md).

*Il profilo si deduce dalla posizione del file, mai da un'intestazione dichiarata dall'autore:* un
profilo dichiarabile sarebbe un profilo scelto per comodità il giorno in cui una sezione costa
fatica.

*Verifica:* `php tooling/scripts/check-docs.php --sections`. *Livello: vincolante.*

**R4.** Le prescrizioni usano «deve» o «può», mai «dovrebbe».
*Verifica:* ricerca in CI.

**R5.** Ogni link interno è **relativo** e valido.
*Verifica:* verifica dei link in CI. *Livello: vincolante.*

**R6.** Ogni documento è raggiungibile dall'indice della sua cartella.
*Motivo:* un file non linkato, per la Factory, non esiste.
*Verifica:* verifica dei link.

**R7.** Un documento oltre le ~500 righe si divide in più file collegati, senza accorciare il
contenuto.
*Verifica:* revisione.

**R8.** Gli esempi di codice sono eseguibili o realistici: nessun `// ...` al posto della logica che
l'esempio dimostra.
*Verifica:* revisione.

---

## Quando si aggiorna

**R9.** La documentazione si aggiorna **nello stesso commit** della modifica.
*Verifica:* revisione: una PR che cambia un contratto pubblico senza toccare la documentazione viene
respinta. *Livello: vincolante.*

| Modifica | Documento da aggiornare |
|---|---|
| Nuova operazione | README del modulo, manuale utente |
| Nuovo permesso | README del modulo, manifesto |
| Modifica di API | documentazione OpenAPI |
| Nuova impostazione | riferimento di configurazione, manifesto |
| Decisione strutturale | ADR |
| Modifica visibile all'utente | changelog |
| Nuovo comando | riferimento dei comandi |
| Deroga a una regola | `CLAUDE.md` di progetto |

---

## Documentazione nel codice

**R10.** I commenti spiegano il **perché**, non il cosa.

```php
// ✗ Ripete il codice
// Incrementa il contatore
$counter++;

// ✓ Spiega una scelta non ovvia
// La scadenza si calcola dalla consegna e non dalla produzione: lo richiede la
// procedura di qualità del cliente (vedi ADR-P0003).
$expiry = $delivery->date->addMonths($article->shelf_life_months);
```

*Verifica:* revisione.

**R11.** I commenti sono in **italiano**; gli identificatori e i tag PHPDoc in inglese.
*Verifica:* revisione.

**R12.** PHPDoc solo quando aggiunge informazione ai tipi: generici, eccezioni, spiegazioni.
*Motivo:* un PHPDoc che ripete la firma è rumore da mantenere.
*Verifica:* revisione.

**R13.** Ogni classe pubblica della Foundation ha un PHPDoc che ne descrive lo scopo.
*Verifica:* revisione.

**R14.** Le classi interne sono marcate `@internal`.
*Verifica:* revisione.

---

## ADR

**R15.** Ogni decisione strutturale ha una ADR, scritta **prima** dell'implementazione.
*Verifica:* revisione. *Livello: vincolante.*

**R16.** Le ADR non si cancellano né si riscrivono: si superano con una nuova.
*Verifica:* storia del repository.

**R17.** Ogni ADR dichiara le alternative valutate, le conseguenze negative accettate e la soglia di
rivalutazione.
*Verifica:* template ADR.

**R18.** Le ADR di progetto non contraddicono quelle della Factory: possono solo restringere o
documentare un'eccezione motivata con scadenza.
*Verifica:* revisione.

---

## Esempi

### Esempio 1 — sintesi efficace

```markdown
✗  > Questo documento parla della cache.
✓  > Come si costruiscono le chiavi di cache in un'applicazione multitenant e come si invalidano
     senza svuotare la cache degli altri tenant.
```

### Esempio 2 — voce di checklist

```markdown
✗  - [ ] Il codice è ben scritto.
✓  - [ ] Ogni classe in `Actions/` è `final` e ha un solo metodo pubblico.
```

La prima non è verificabile: due revisori danno risposte diverse.

---

## Best practice

- Scrivere l'indice dopo il contenuto, poi verificarne i link.
- Rileggere chiedendosi: un agente potrebbe eseguire questo senza fare domande?
- Datare le affermazioni che invecchiano: versioni, soglie, numeri.
- Aggiornare la documentazione mentre si modifica il codice, non in un momento dedicato che non
  arriva.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Documentazione aggiornata «dopo» | Diverge in pochi mesi | Stesso commit |
| «dovrebbe» in una regola | Nessuno sa se è obbligatorio | «deve» o «può» |
| Link assoluti al repository | Si rompono al primo cambio di host | Link relativi |
| Documento non linkato | Invisibile, muore | Aggiornare l'indice |
| Esempi con `// ...` | Non copiabili, non verificabili | Codice completo |
| Commenti che ripetono il codice | Rumore da mantenere | Commentare il perché |
| ADR scritta dopo l'implementazione | Diventa una giustificazione | Scriverla prima |
| API non documentata | Consumatori che dipendono da comportamenti non intenzionali | OpenAPI obbligatorio |

---

## Checklist

- [ ] Tutti i documenti obbligatori del progetto e dei moduli sono presenti.
- [ ] Ogni documento ha le sezioni obbligatorie.
- [ ] Prescrizioni con «deve» o «può».
- [ ] Link relativi e validi; documento linkato dal proprio indice.
- [ ] Documenti oltre 500 righe divisi in file collegati.
- [ ] Esempi eseguibili o realistici.
- [ ] Documentazione aggiornata nello stesso commit del codice.
- [ ] Commenti che spiegano il perché, in italiano.
- [ ] Classi interne marcate `@internal`.
- [ ] Decisioni strutturali registrate in ADR prima dell'implementazione.
- [ ] Documentazione OpenAPI aggiornata.

---

## Riferimenti

- [Stile della documentazione](../docs/02-conventions/01-documentation-style.md)
- [Guida al contributo](../CONTRIBUTING.md)
- [ADR](../architecture/decisions/README.md)
- [Checklist documentazione](../checklists/documentation-checklist.md)
