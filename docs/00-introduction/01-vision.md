# Visione

> Perché WidStudios costruisce una fabbrica di software invece di costruire software, e come si
> riconosce che l'obiettivo è stato raggiunto.

---

## Indice

1. [Descrizione](#descrizione)
2. [Il problema](#il-problema)
3. [L'intuizione](#lintuizione)
4. [Lo stato finale desiderato](#lo-stato-finale-desiderato)
5. [I tre livelli di maturità](#i-tre-livelli-di-maturità)
6. [Cosa cambia per chi lavora](#cosa-cambia-per-chi-lavora)
7. [Come si misura il successo](#come-si-misura-il-successo)
8. [Rischi della visione](#rischi-della-visione)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

WidStudios non produce un software: ne produce **molti**, in domini diversi — sanità, vendite,
progetti, assistenza, servizi fiscali, documentale, tracciabilità industriale. Domini diversi,
clienti diversi, ma un'infrastruttura sottostante che è, ogni volta, la stessa.

La visione della AI Factory nasce da un'osservazione elementare: in un gestionale multitenant,
la parte specifica del dominio è tra il 20% e il 30% del lavoro. Il resto — autenticazione,
tenancy, ruoli, permessi, audit, code, storage, pannelli amministrativi, API, deploy — è
**identico**, e viene riscritto ogni volta, leggermente diverso, leggermente peggio.

---

## Il problema

### Sintomi osservabili

| Sintomo | Costo reale |
|---|---|
| Ogni progetto reimplementa autenticazione e permessi | 2-4 settimane per progetto, con bug diversi ogni volta |
| Ogni progetto ha una struttura di cartelle leggermente diversa | Chi passa da un progetto all'altro perde giorni di orientamento |
| Le convenzioni vivono nella testa delle persone | Se ne va una persona, se ne va lo standard |
| Le decisioni tecniche si ridiscutono ad ogni avvio | Riunioni ricorrenti che approdano ogni volta a conclusioni simili |
| I bug corretti in un progetto restano in tutti gli altri | Il costo di un difetto si moltiplica per il numero di progetti |
| La qualità dipende da chi c'era quel giorno | Impossibile promettere uno standard al cliente |
| Il deploy è un evento artigianale | Rischio concentrato nel momento peggiore |

### La causa radice

Non è mancanza di competenza: è **mancanza di memoria istituzionale eseguibile**.

Le competenze esistono, ma vivono in forma implicita: in conversazioni, in code review, in
abitudini. Ciò che è implicito non si trasferisce, non si versiona, non si verifica e non si può
delegare — né a una nuova persona, né a un agente AI.

---

## L'intuizione

Se il 70% del lavoro è ripetibile, allora il 70% del lavoro è **automatizzabile**, a una
condizione: che sia stato prima reso **esplicito**.

Questa è la premessa della Factory. Non «l'AI scrive il codice al posto nostro», ma:

> Rendiamo esplicito, verificabile e riutilizzabile tutto ciò che oggi è implicito.
> Ciò che diventa esplicito diventa automatizzabile — da una persona junior, da uno script,
> o da un agente AI. Indifferentemente.

Il corollario è importante: **la Factory ha valore anche senza AI**. Una piattaforma di standard,
foundation e template accelera il lavoro umano allo stesso modo. L'AI è il moltiplicatore, non
il fondamento.

---

## Lo stato finale desiderato

Il traguardo è che il comando

```
loop crea "Nome Progetto"
```

produca, senza altro input, un'applicazione:

- **conforme**: rispetta ogni regola in `rules/` senza eccezioni non dichiarate;
- **multitenant**: isolamento dei dati garantito per costruzione, non per attenzione;
- **testata**: copertura ≥ 80%, 100% su Action e Policy, test di architettura verdi;
- **documentata**: README, ADR, manuale utente, documentazione API generati e coerenti;
- **rilasciabile**: immagine Docker, pipeline CI/CD, procedure di rollback pronte;
- **riconoscibile**: chi ha lavorato su un altro progetto della Factory si orienta in mezz'ora.

E che l'intervento umano si concentri dove serve davvero: **le decisioni di dominio**.

Ciò che l'AI non deve decidere da sola: cosa significa «lotto in scadenza» per un magazzino
sanitario, quali sono gli obblighi normativi di un CAF, quale flusso di approvazione vuole il
cliente. Ciò che l'AI deve fare da sola: tutto il resto.

---

## I tre livelli di maturità

La visione si raggiunge per gradi. Ogni livello ha valore autonomo: non serve arrivare al terzo
per ottenere un ritorno.

### Livello 1 — Standardizzazione

*Gli standard sono scritti, la Foundation esiste, i template sono pronti.*

Un umano avvia un progetto in un giorno invece che in due settimane. Gli agenti AI, se usati,
producono codice conforme perché hanno un riferimento esplicito da leggere.

**Indicatore**: due progetti avviati da persone diverse hanno la stessa struttura.

### Livello 2 — Assistenza guidata

*Gli agenti eseguono le fasi, l'umano supervisiona i quality gate.*

Ogni fase del workflow è coperta da un agente con prompt, input e output contrattuali. L'umano
non scrive più il codice infrastrutturale: lo revisiona.

**Indicatore**: ≥ 60% delle fasi supera il quality gate al primo tentativo.

### Livello 3 — Generazione autonoma

*`loop crea` porta il progetto fino a staging senza intervento, salvo le decisioni di dominio.*

L'orchestratore coordina gli agenti, gestisce i fallimenti, itera sui rifiuti del Reviewer, e si
ferma solo quando serve una decisione che non gli compete.

**Indicatore**: time to first deploy ≤ 3 giorni, ≤ 10 interventi umani per progetto.

---

## Cosa cambia per chi lavora

| Ruolo | Prima | Dopo |
|---|---|---|
| Sviluppatore senior | Scrive infrastruttura, poi dominio | Progetta il dominio, revisiona l'infrastruttura generata |
| Sviluppatore junior | Impara per osmosi, con esiti variabili | Ha uno standard esplicito da seguire e verificare |
| Architetto | Ripete le stesse scelte per ogni progetto | Le prende una volta, le registra in ADR, le fa applicare |
| Tech lead | Controlla la qualità a campione | Controlla i quality gate, che sono sistematici |
| DevOps | Adatta il deploy ad ogni progetto | Mantiene una pipeline sola, parametrica |
| Cliente | Riceve qualità variabile | Riceve uno standard dichiarato e verificabile |

Il timore ricorrente — «l'automazione toglie il lavoro interessante» — si verifica al contrario:
l'automazione toglie il lavoro **ripetitivo**. Nessuno considera interessante riscrivere per la
settima volta la gestione dei permessi.

---

## Come si misura il successo

La visione non è raggiunta quando la documentazione è completa: è raggiunta quando i **numeri**
sui progetti reali cambiano.

| Metrica | Baseline (senza Factory) | Obiettivo |
|---|---|---|
| Time to first deploy | 15-20 giorni | ≤ 3 giorni |
| Infrastruttura riscritta per progetto | ~70% | ≤ 30% |
| Copertura test media | variabile, spesso < 40% | ≥ 80% |
| Difetti in produzione nei primi 14 giorni | 8-15 | ≤ 2 |
| Tempo di inserimento di una persona nuova | 3-4 settimane | ≤ 1 settimana |
| Costo di un bug infrastrutturale | N progetti da correggere | 1 correzione nella Foundation |

Definizioni precise e soglie: [`governance/quality-metrics.md`](../../governance/quality-metrics.md).

---

## Rischi della visione

Una visione onesta dichiara anche come può fallire.

| Rischio | Segnale precoce | Mitigazione |
|---|---|---|
| **Astrazione prematura** | La Foundation contiene codice usato da un solo progetto | Regola della terza occorrenza prima di promuovere |
| **Rigidità** | I progetti aggirano la Factory invece di usarla | Deroghe possibili, tracciate e con scadenza |
| **Documentazione morta** | Documenti non revisionati da oltre 18 mesi | Metrica sull'età, revisione periodica per area |
| **Fiducia cieca nell'AI** | Codice generato accettato senza review | Quality gate obbligatori, Reviewer indipendente |
| **Costo di manutenzione** | La Factory assorbe più tempo di quanto ne liberi | Metriche di efficacia sui progetti, non sull'attività |
| **Divergenza silenziosa** | Ogni progetto ha «la sua versione» della Foundation | Versionamento e guide di migrazione obbligatorie |
| **Standard non verificabili** | Regole che nessuno controlla | Ogni regola dichiara il proprio criterio di verifica |

Il rischio più insidioso è l'astrazione prematura: è quello che ha ucciso la maggior parte delle
piattaforme interne. Si costruisce l'astrazione «giusta» prima di avere abbastanza casi reali,
e poi la si difende invece di correggerla.

---

## Esempi

### Esempio 1 — lo stesso bug, due mondi

*Senza Factory.* Un difetto nel controllo dei permessi permette a un utente di vedere i dati di
un altro reparto. Il bug è presente in quattro progetti, scritto quattro volte in modo diverso.
Va trovato e corretto quattro volte, con quattro test diversi, quattro rilasci, quattro rischi.

*Con Factory.* Il controllo dei permessi è nella Foundation, con i suoi test. Il bug si corregge
una volta, si rilascia una versione patch, i progetti la ricevono con un `composer update`.

### Esempio 2 — la persona nuova

*Senza Factory.* Prime tre settimane: capire dove sta la logica in questo progetto specifico,
quali convenzioni valgono, chi chiedere.

*Con Factory.* Primo giorno: legge i principi e la struttura. Secondo giorno: apre un progetto e
riconosce ogni cartella. Terzo giorno: scrive la prima Action seguendo il template e la checklist.

### Esempio 3 — la decisione che non si ridiscute

*Senza Factory.* «Usiamo un database per tenant o una colonna `tenant_id`?» Discussione di due
ore, ad ogni avvio di progetto, con esiti diversi a seconda di chi è in stanza.

*Con Factory.* La domanda è già stata affrontata in [ADR-0002](../../architecture/decisions/0002-tenant-isolation-strategy.md),
con alternative, conseguenze e motivazioni. Si legge in dieci minuti. Si ridiscute solo se il
contesto è cambiato — e allora si scrive una nuova ADR.

---

## Best practice

- Trattare la Factory come un **prodotto interno**, con owner, versioni e utenti — non come una
  cartella di appunti.
- Promuovere alla Foundation solo ciò che ha dimostrato di essere generale (terza occorrenza).
- Misurare l'effetto sui progetti, non l'attività sulla Factory.
- Permettere le deroghe, ma pretendere che siano scritte e abbiano una scadenza.
- Aggiornare la Factory **mentre** si lavora sui progetti, non in un «momento dedicato» che non
  arriva mai.
- Considerare la documentazione parte del deliverable, non un allegato.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Costruire tutta la piattaforma prima di usarla | Astrazioni sbagliate scoperte tardi | Validare ogni area su un progetto reale |
| Considerare la Factory «finita» | Invecchia e diventa un ostacolo | Revisione periodica per area |
| Vietare le deroghe | I progetti la aggirano di nascosto | Deroghe tracciate con scadenza |
| Misurare i documenti prodotti | Si ottimizza la quantità | Misurare time to deploy e difetti |
| Delegare tutto all'AI senza gate | Difetti sistematici replicati su scala | Reviewer indipendente obbligatorio |
| Copiare la Foundation dentro i progetti | Divergenza immediata | Dipendenza Composer versionata |

---

## Checklist

- [ ] So distinguere ciò che appartiene alla Factory da ciò che appartiene al progetto.
- [ ] Conosco il livello di maturità attuale della Factory.
- [ ] So quali metriche dicono se la Factory funziona.
- [ ] Conosco i rischi della visione e i segnali precoci di ciascuno.
- [ ] Quando trovo una ripetizione, so quando promuoverla (terza occorrenza).

---

## Riferimenti

- [Che cos'è la AI Factory](02-what-is-ai-factory.md)
- [I dodici principi](04-principles.md)
- [Stack tecnologico](03-technology-stack.md)
- [Roadmap](../../governance/roadmap.md) · [Metriche di qualità](../../governance/quality-metrics.md)
- [Master workflow](../../workflows/00-master-workflow.md)
- [Contratto `loop crea`](../../prompts/loop-crea.md)
