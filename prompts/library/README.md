# Libreria di prompt

> Prompt per le attività ricorrenti **fuori** dal master workflow: manutenzione, evoluzione,
> correzione di progetti già generati.

---

## Indice

1. [Descrizione](#descrizione) 2. [Indice della libreria](#indice-della-libreria)
3. [Struttura comune](#struttura-comune) 4. [Esempi](#esempi) 5. [Best practice](#best-practice)
6. [Errori comuni](#errori-comuni) 7. [Checklist](#checklist) 8. [Riferimenti](#riferimenti)

---

## Descrizione

Il master workflow copre la **creazione** di un progetto. La vita di un'applicazione è però fatta
soprattutto di manutenzione: nuove feature, correzioni, moduli aggiuntivi, aggiornamenti della
Foundation.

La libreria contiene i prompt per queste attività, che seguono le stesse regole ma un percorso più
breve.

---

## Indice della libreria

| Prompt | Attività | Agenti coinvolti | Durata tipica |
|---|---|---|---|
| [add-feature.md](add-feature.md) | aggiungere una funzionalità | Backend, Filament, Security, Testing | ore |
| [fix-bug.md](fix-bug.md) | correggere un difetto | Backend, Testing | ore |
| [add-module.md](add-module.md) | aggiungere un modulo completo | Architect, Database, Backend, Filament, Security, Testing, Documentation | giorni |
| [upgrade-foundation.md](upgrade-foundation.md) | aggiornare la Foundation | Foundation, Testing | ore |

---

## Struttura comune

Ogni prompt della libreria contiene:

| Blocco | Contenuto |
|---|---|
| Quando si usa | il caso che copre, e quello che non copre |
| Prerequisiti | cosa deve essere vero prima |
| Sequenza | quali agenti, in quale ordine |
| Vincoli | ciò che vale sempre, anche nel percorso breve |
| Definizione di «fatto» | quando l'attività è conclusa |
| Gate | quali checklist verificare |

---

## Esempi

### Esempio 1 — quando usare la libreria

| Situazione | Percorso |
|---|---|
| Nuovo progetto | master workflow (`loop crea`) |
| Nuova funzionalità su progetto esistente | `library/add-feature.md` |
| Difetto in produzione | `library/fix-bug.md` |
| Nuovo ambito funzionale | `library/add-module.md` |
| Aggiornamento minor della Foundation | `library/upgrade-foundation.md` |
| Aggiornamento major della Foundation | guida di migrazione + `upgrade-foundation.md` |

### Esempio 2 — cosa resta invariato

Anche nel percorso breve restano vincolanti: le dieci regole assolute, la copertura al 100% su
Action e Policy, i test di isolamento per le entità nuove, il rapporto di fase, la documentazione
aggiornata nello stesso insieme di modifiche.

Il percorso è più corto; gli standard sono gli stessi.

---

## Best practice

- Usare il prompt della libreria invece di improvvisare: contiene i passaggi che si dimenticano.
- Non saltare la fase di sicurezza per le funzionalità «piccole»: la dimensione non riduce il rischio.
- Aggiungere un prompt alla libreria quando la stessa attività si ripete per la terza volta.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Improvvisare invece di usare il prompt | Passaggi dimenticati | Usare la libreria |
| Saltare la sicurezza sulle modifiche piccole | Le falle non dipendono dalla dimensione | Sequenza completa |
| Usare il master workflow per una feature | Costo sproporzionato | Percorso breve |
| Non aggiornare la documentazione | Diverge in pochi mesi | Stesso insieme di modifiche |

---

## Checklist

- [ ] Ho scelto il percorso corretto (master workflow o libreria).
- [ ] Ho seguito la sequenza prevista dal prompt.
- [ ] I vincoli assoluti sono rispettati anche nel percorso breve.
- [ ] La definizione di «fatto» è soddisfatta.

---

## Riferimenti

- [Prompt](../README.md) · [Contratto `loop crea`](../loop-crea.md)
- [Workflow](../../workflows/README.md)
- [Ciclo di vita dello sviluppo](../../docs/03-development/01-development-lifecycle.md)
