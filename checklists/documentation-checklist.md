# Checklist — Documentazione

> Verifica che la documentazione consegnata sia completa, navigabile, veritiera e priva di link
> rotti.

| | |
|---|---|
| **Fase** | 12 — Documentation |
| **Agente** | [Documentation Agent](../agents/10-documentation-agent.md) |
| **Natura** | gate bloccante |

---

## Indice

1. [Descrizione](#descrizione) 2. [Verifiche](#verifiche) 3. [Comandi di verifica](#comandi-di-verifica)
4. [Esempi](#esempi) 5. [Best practice](#best-practice) 6. [Errori comuni](#errori-comuni)
7. [Checklist](#checklist) 8. [Riferimenti](#riferimenti)

---

## Descrizione

La documentazione ha un solo criterio di successo: qualcuno che non ha partecipato al lavoro deve
poter operare sul risultato. Tutto il resto — completezza formale, numero di pagine, presenza delle
sezioni — serve a quello.

Il rischio specifico di questa fase è la documentazione **veritiera al momento della scrittura e
falsa una settimana dopo**. Per questo ogni affermazione verificabile va verificata sul codice, non
sui report delle fasi precedenti.

Ogni voce si verifica in modo oggettivo. Le voci si riportano **una per una**, con l'esito reale.

---

## Verifiche

### Struttura di ogni documento

- [ ] Ogni documento ha: titolo, riga di sintesi, indice, descrizione, contenuto, esempi, best
      practice, errori comuni, checklist, riferimenti.
- [ ] L'indice corrisponde ai titoli effettivamente presenti.
- [ ] Ogni documento è raggiungibile da almeno un indice di cartella (`README.md`).
- [ ] Nessun documento è orfano.
- [ ] I documenti oltre le ~600 righe sono stati suddivisi, con i collegamenti tra le parti.

### Collegamenti

- [ ] Tutti i link interni puntano a file esistenti.
- [ ] Tutte le ancore interne (`#sezione`) puntano a titoli esistenti.
- [ ] Nessun link è stato rotto dalle rinomine di questa fase.
- [ ] I riferimenti incrociati sono **bidirezionali** dove ha senso: se A cita B come
      approfondimento, B cita A come contesto.
- [ ] I link esterni sono raggiungibili e non puntano a risorse dismesse.

### Veridicità

- [ ] Ogni comando riportato è stato **eseguito** e produce l'esito descritto.
- [ ] Ogni esempio di codice è sintatticamente valido e coerente con la Foundation attuale.
- [ ] Ogni percorso di file citato esiste.
- [ ] Ogni nome di classe, metodo, comando e variabile d'ambiente corrisponde al codice reale.
- [ ] Nessuna funzionalità documentata che non esiste.
- [ ] Nessuna funzionalità esistente e rilevante che non è documentata.

### Contenuto del progetto generato

- [ ] `README.md`: che cos'è il software, per chi, come si avvia in locale.
- [ ] `CLAUDE.md` derivato dallo stub, con le regole locali del progetto.
- [ ] Documentazione del **dominio**: entità, stati, regole di business, glossario.
- [ ] Documentazione dei **casi d'uso**, con attore, precondizioni, flusso, esiti.
- [ ] Documentazione dell'**architettura**: livelli, moduli, confini, decisioni.
- [ ] Le ADR delle decisioni prese durante il progetto.
- [ ] Documentazione delle **API**, allineata alle rotte reali.
- [ ] Documentazione dei **permessi**: elenco, significato, assegnazione ai ruoli predefiniti.
- [ ] Guida all'**installazione e al provisioning di un tenant**.
- [ ] Guida all'**esercizio**: backup, ripristino, log, code, manutenzione.
- [ ] Guida alla **risoluzione dei problemi**, con i casi realmente incontrati.
- [ ] `CHANGELOG.md` aggiornato per la versione in uscita.

### Lingua e forma

- [ ] La documentazione è in italiano; gli identificatori di codice restano in inglese.
- [ ] I termini di dominio corrispondono al glossario, senza sinonimi introdotti.
- [ ] Nessun segnaposto lasciato: `TODO`, `TBD`, `da completare`, `lorem ipsum`.
- [ ] Nessun esempio con `// ...` al posto della logica significativa.
- [ ] Nessun segreto o dato realistico di persone reali negli esempi.
- [ ] I blocchi di codice dichiarano il linguaggio.

### Documentazione delle decisioni

- [ ] Ogni decisione strutturale presa nel progetto ha una ADR.
- [ ] Le ADR riportano contesto, decisione, alternative scartate **con la motivazione**, conseguenze.
- [ ] Nessuna ADR accettata è stata riscritta: le decisioni superate sono **superate**, non
      modificate.
- [ ] Le assunzioni dichiarate durante il lavoro sono raccolte in un punto solo e ancora vere.
- [ ] Le domande rimaste aperte sono elencate esplicitamente, con l'indicazione di chi deve
      rispondere.

---

## Comandi di verifica

```bash
php tooling/scripts/check-docs.php               # link interni, ancore, documenti orfani
php tooling/scripts/check-docs.php --sections    # sezioni obbligatorie mancanti
php tooling/scripts/check-docs.php --placeholders
```

Verifica manuale obbligatoria, che nessuno script sostituisce: eseguire davvero i comandi
documentati, su un ambiente pulito, seguendo **solo** ciò che il documento dice. È l'unico modo per
scoprire i passaggi impliciti — quelli che chi ha scritto conosceva e non ha annotato.

---

## Esempi

### Esito conforme

```markdown
### Quality gate
- checklists/documentation-checklist.md: N/N soddisfatte.
- `check-docs.php`: 0 link rotti, 0 ancore non risolte, 0 documenti orfani, 0 segnaposto.
- Guida all'installazione eseguita da ambiente pulito: completata senza passaggi impliciti.
- Voci non soddisfatte: nessuna.
```

### Esito con voci non soddisfatte

```markdown
### Quality gate
- checklists/documentation-checklist.md: N-2/N soddisfatte.
- `check-docs.php`: 3 link rotti.

Voci non soddisfatte:
- Link interni validi: `docs/api/movimenti.md` cita `../domain/lotti.md`, rinominato in
  `../domain/batches.md` nella fase 11. Altri 2 casi analoghi.
  Correzione: aggiornare i 3 riferimenti.
- Comandi eseguiti: la guida all'installazione omette `php artisan tenants:migrate`.
  Seguendola alla lettera, il primo tenant risulta senza tabelle.
  Correzione: aggiungere il passaggio dopo `migrate --database=landlord`.

Richiedo rework su questi punti.
```

---

## Best practice

- Scrivere per chi arriva dopo, non per chi c'era: ciò che è ovvio oggi non lo sarà tra un anno.
- Eseguire davvero ogni comando documentato, su un ambiente pulito.
- Documentare il **perché** delle decisioni: il come si legge dal codice, il perché no.
- Preferire un documento approfondito a tre superficiali sullo stesso argomento.
- Collegare invece di duplicare: il contenuto duplicato diverge sempre.
- Aggiornare la documentazione **nello stesso commit** della modifica che la rende obsoleta.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Documentazione scritta dai report | Descrive ciò che si intendeva fare, non ciò che è | Verificare sul codice |
| Comandi mai eseguiti | La guida non funziona per chi la segue | Eseguirli davvero |
| Passaggi impliciti nella guida | Chi non c'era resta bloccato | Prova da ambiente pulito |
| Link rotti dopo una rinomina | La navigazione si spezza | `check-docs.php` in CI |
| Contenuto duplicato | Le copie divergono | Collegare l'originale |
| Documento orfano | Esiste ma nessuno lo trova | Collegarlo da un indice |
| Segnaposto lasciati | Documento apparentemente completo | Nessun `TODO` in consegna |
| Solo il «come», mai il «perché» | La decisione viene rifatta e ribaltata | ADR |
| ADR riscritta invece che superata | Si perde la storia delle decisioni | Nuova ADR che supera |
| Documentazione aggiornata dopo | Non viene aggiornata affatto | Stesso commit |

---

## Checklist

- [ ] Ho verificato ogni affermazione sul codice reale.
- [ ] Ho eseguito ogni comando documentato.
- [ ] Ho seguito la guida all'installazione da un ambiente pulito.
- [ ] `check-docs.php` è verde su link, sezioni e segnaposto.
- [ ] Ogni nuovo documento è collegato da un indice.
- [ ] Le domande aperte sono elencate esplicitamente.
- [ ] Se il gate è rosso, l'ho dichiarato.

---

## Riferimenti

- [Fase 12](../workflows/13-phase-documentation.md) · [Documentation Agent](../agents/10-documentation-agent.md)
- [Regole di documentazione](../rules/documentation.md)
- [ADR — template](../architecture/decisions/template.md)
- [Guida al contributo](../CONTRIBUTING.md)
