# Examples

> Casi d'uso completi e frammenti di codice. **Autorità: nulla** — gli esempi mostrano, non
> prescrivono.

---

## Indice

1. [Descrizione](#descrizione)
2. [Perché gli esempi non vincolano](#perché-gli-esempi-non-vincolano)
3. [Il contenuto](#il-contenuto)
4. [Come si legge un esempio](#come-si-legge-un-esempio)
5. [Aggiungere un esempio](#aggiungere-un-esempio)
6. [Esempi](#esempi)
7. [Best practice](#best-practice)
8. [Errori comuni](#errori-comuni)
9. [Checklist](#checklist)
10. [Riferimenti](#riferimenti)

---

## Descrizione

Le regole dicono che cosa è obbligatorio; l'architettura dice come sono fatte le cose; la Foundation
è il comportamento eseguibile. Manca una cosa: **vedere il tutto insieme, su un caso reale**.

È ciò che sta qui. Un walkthrough completo mostra come le quattordici fasi si concatenano davvero su
un progetto: che cosa produce ogni agente, dove si ferma, che cosa chiede a un umano, e come un
requisito ambiguo del committente diventa un'entità con i suoi vincoli.

---

## Perché gli esempi non vincolano

È l'ultima posizione nella
[gerarchia delle fonti di verità](../CLAUDE.md#gerarchia-delle-fonti-di-verità), ed è deliberato.

Un esempio è **una** soluzione, in **un** contesto, con **quei** vincoli. Trattarlo come normativo
significa replicare scelte che dipendevano da circostanze che non valgono altrove: il numero di
tenant previsti, i volumi, i requisiti normativi del settore, ciò che il committente aveva già.

Il rischio non è teorico. Un walkthrough su un magazzino sanitario mostra la tracciabilità dei lotti
con vincoli di conservazione a norma. Copiarla in un CRM produce un sistema che registra molto più
di quanto serva, con costi di conservazione e obblighi che nessuno aveva chiesto.

> Quando un esempio e una regola sembrano in conflitto, **vince la regola**. Se il conflitto è
> reale, l'esempio è un difetto da correggere.

---

## Il contenuto

| Cartella | Contenuto |
|---|---|
| [`walkthroughs/`](walkthroughs/README.md) | percorsi completi, dal brief al rilascio |
| [`code/`](code/README.md) | frammenti che mostrano un pattern in un contesto reale |

### I walkthrough

| Walkthrough | Verticale | Che cosa mostra |
|---|---|---|
| [Magazzino Sanitario](walkthroughs/01-magazzino-sanitario.md) | sanità | il percorso completo delle quattordici fasi, incluse due fermate reali |

### I frammenti di codice

| Frammento | Pattern |
|---|---|
| [Dal requisito all'entità](code/01-dal-requisito-allentita.md) | come una frase del committente diventa dominio |
| [Un'operazione da tre ingressi](code/02-operazione-tre-ingressi.md) | Action invocata da controller, importazione e comando |
| [I cinque punti dell'isolamento](code/03-cinque-punti-isolamento.md) | come si rompe, e come si verifica |

---

## Come si legge un esempio

1. **Prima le regole**, poi l'esempio. Un esempio letto senza le regole insegna la forma e non il
   motivo, e la forma senza motivo non sopravvive alla prima scadenza stretta.
2. **Cercare le decisioni, non le righe.** La parte utile di un walkthrough non è il codice: è il
   punto in cui qualcuno ha scelto tra due strade e ha scritto perché.
3. **Guardare le fermate.** I passaggi in cui il processo si è fermato per chiedere a un umano sono
   i più istruttivi: mostrano quali ambiguità un agente non deve risolvere da solo.
4. **Non copiare il dominio.** Le entità di un esempio appartengono al suo verticale.

---

## Aggiungere un esempio

| Criterio | Perché |
|---|---|
| Mostra qualcosa che le regole non possono mostrare | altrimenti è una regola scritta due volte |
| È **completo**: nessun `// …` al posto della logica | un esempio incompleto genera codice incompleto |
| Ogni scelta non ovvia è motivata | senza il perché, si replica anche ciò che non serviva |
| È coerente con la Factory **attuale** | un esempio obsoleto insegna la versione sbagliata |
| Non contiene dati di persone reali né segreti | gli esempi vengono copiati per definizione |

Un esempio va aggiornato quando la Factory cambia. Un esempio non aggiornato è peggio di un esempio
assente: sembra autorevole e insegna qualcosa che non è più vero.

---

## Esempi

### Un uso corretto

> *«Devo capire dove finisce la validazione e dove comincia il dominio.»*

[`code/01-dal-requisito-allentita.md`](code/01-dal-requisito-allentita.md) mostra la stessa regola
del committente — «un lotto scaduto non si può movimentare» — collocata in tre posti diversi, con le
conseguenze di ognuno. Il lettore ne ricava un criterio, non un frammento da incollare.

### Un uso scorretto

> *«Il walkthrough del magazzino usa un enum a cinque stati: lo copio nel mio progetto di ticket.»*

I cinque stati rispondono alla tracciabilità dei lotti sanitari. Un sistema di ticket ha un ciclo di
vita diverso, e adottare quello sbagliato costa una migrazione di dati quando la differenza emerge.

L'esempio mostra **come** si costruisce una macchina a stati, non **quale**.

---

## Best practice

- Leggere le regole prima dell'esempio.
- Cercare le decisioni e le fermate, non le righe di codice.
- Trattare ogni divergenza esempio/regola come un difetto dell'esempio.
- Aggiornare gli esempi nello stesso commit della modifica che li rende obsoleti.
- Preferire un esempio completo a tre parziali.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Esempio trattato come normativo | Si replicano scelte contestuali | Autorità nulla |
| Dominio dell'esempio copiato | Entità che non appartengono al progetto | Copiare il metodo, non il dominio |
| Esempio letto senza le regole | Si impara la forma, non il motivo | Prima le regole |
| Esempio non aggiornato | Sembra autorevole e insegna il falso | Stesso commit |
| Esempio con `// …` al posto della logica | Genera codice incompleto | Completo o assente |
| Dati realistici di persone | Finiscono copiati in ambienti veri | Dati inventati |

---

## Checklist

- [ ] Ho letto le regole pertinenti prima dell'esempio.
- [ ] Sto copiando un metodo, non un dominio.
- [ ] Ho verificato che l'esempio sia coerente con la Factory attuale.
- [ ] Se ho trovato una divergenza con una regola, l'ho segnalata.

---

## Riferimenti

- [Walkthrough](walkthroughs/README.md) · [Frammenti di codice](code/README.md)
- [Gerarchia delle fonti di verità](../CLAUDE.md) · [Struttura del repository](../docs/00-introduction/05-repository-structure.md)
- [Master workflow](../workflows/00-master-workflow.md) · [`loop crea`](../prompts/loop-crea.md)
