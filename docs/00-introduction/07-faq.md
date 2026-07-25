# Domande frequenti

> Le obiezioni ricorrenti alla Factory, con risposte oneste — comprese quelle che danno ragione
> a chi obietta.

---

## Indice

1. [Descrizione](#descrizione)
2. [Sulla piattaforma](#sulla-piattaforma)
3. [Sull'automazione e gli agenti AI](#sullautomazione-e-gli-agenti-ai)
4. [Sull'architettura](#sullarchitettura)
5. [Sullo stack](#sullo-stack)
6. [Sul lavoro quotidiano](#sul-lavoro-quotidiano)
7. [Sui costi e sui tempi](#sui-costi-e-sui-tempi)
8. [Best practice](#best-practice)
9. [Errori comuni](#errori-comuni)
10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

Le domande raccolte qui sono quelle che si ripresentano ad ogni nuovo progetto e ad ogni nuova
persona. Rispondere una volta bene costa meno che rispondere venti volte male.

Dove la risposta è «hai ragione, ma», è scritto così.

---

## Sulla piattaforma

### Perché non usiamo semplicemente uno starter kit?

Uno starter kit si **copia**: dal momento della copia, ogni progetto diverge. Sei mesi dopo ci
sono cinque varianti dello stesso codice e un difetto va corretto cinque volte.

La Foundation è una **dipendenza versionata**: la correzione arriva a tutti con un aggiornamento.
La differenza non è nel contenuto iniziale, è nel comportamento nel tempo.

### La Factory non è troppo pesante per un progetto piccolo?

Dipende da cosa si intende per piccolo. Un progetto che vive tre mesi e poi si butta: sì, è
sovradimensionata. Un progetto che va in produzione presso un cliente: no, perché «piccolo»
descrive il primo mese, non i cinque anni successivi.

La domanda giusta non è «quanto è grande oggi», ma «avrà utenti reali e dati reali?». Se sì,
avrà bisogno di autorizzazione, audit, backup, aggiornamenti di sicurezza e migrazioni — cioè
di tutto ciò che la Factory fornisce già.

### Chi mantiene la Factory mentre tutti lavorano sui progetti?

La Factory si mantiene **lavorando sui progetti**, non in un tempo separato. Il ciclo previsto è:
si incontra un problema sul progetto, lo si risolve, e se ricorre per la terza volta lo si
promuove.

Il tempo dedicato esplicitamente è la revisione periodica per area, prevista in
[`governance/ownership.md`](../../governance/ownership.md).

### Cosa succede se la Factory sbaglia una scelta?

Si corregge con una ADR che supera la precedente, e si scrive la guida di migrazione. La ADR
sbagliata **non si cancella**: resta a documentare perché era sembrata giusta, che è
l'informazione più utile per non ripetere l'errore.

### Posso derogare a una regola?

Sì, a tre condizioni: la deroga è **scritta** nel `CLAUDE.md` del progetto, è **motivata**, e ha
una **scadenza**. Una deroga senza scadenza diventa lo standard di fatto e svuota la regola.

Se una regola viene derogata sistematicamente da più progetti, il problema è la regola: si
corregge, non si impone con più forza.

---

## Sull'automazione e gli agenti AI

### L'AI scrive davvero tutto il codice?

No, e non è l'obiettivo. L'obiettivo è che l'AI scriva tutto il codice **infrastrutturale e
ripetitivo** — che è il 70% — con qualità costante, mentre le persone si concentrano sul dominio
e sulle decisioni.

Il codice generato passa comunque per i quality gate: test, analisi statica, revisione. Non c'è
un percorso in cui codice non verificato arriva in produzione.

### Cosa succede se l'agente sbaglia?

Il quality gate della fase fallisce e si torna indietro (*rework*). Se l'errore si ripete
sistematicamente, il difetto è nel **prompt**, non nell'esecuzione: si corregge il prompt.

La metrica «quality gate superati al primo tentativo» serve esattamente a rilevare questo.

### Come faccio a fidarmi di codice che non ho scritto?

Nello stesso modo in cui ci si fida del codice scritto da un collega: leggendolo, revisionandolo
e verificando che i test passino. La differenza è che il codice generato dalla Factory è
**più prevedibile** di quello scritto a mano, perché segue template e regole espliciti.

Il punto delicato non è la fiducia nel codice: è la tentazione di **non leggerlo** perché
«tanto è generato». Da qui l'obbligo del Reviewer indipendente.

### La Factory funziona solo con Claude?

No. Gli agenti sono definiti in Markdown con prompt espliciti: sono leggibili da qualsiasi modello
sufficientemente capace, e da qualsiasi persona. `Claude Reviewer` è il nome di un ruolo di
revisione indipendente, non un vincolo di fornitore.

Il valore della Factory — standard, foundation, template, processo — resta identico anche senza
alcun agente AI.

### Gli agenti possono modificare la Factory?

Possono **proporre**, non approvare. Un agente è sempre contributore: scrive ADR in stato
`Proposta`, implementa modifiche, segnala incoerenze. Non chiude decisioni né crea versioni.
Vedi [`governance/ownership.md`](../../governance/ownership.md#ruolo-degli-agenti-ai).

---

## Sull'architettura

### Perché multitenant anche con un solo cliente?

Perché aggiungere la multitenancy dopo è una riscrittura, mentre averla dall'inizio costa quasi
nulla: è già risolta nella Foundation. Il caso «un cliente solo» è semplicemente il caso con un
tenant.

L'esperienza comune del settore è che il secondo cliente arriva sempre — e arriva quando il
codice ha già duecento query senza scope.

### Un database per tenant non è insostenibile con molti tenant?

È il compromesso che abbiamo scelto consapevolmente, con i suoi costi: più connessioni, migration
eseguite N volte, backup numerosi.

In cambio si ottiene isolamento **per costruzione** (non per attenzione), backup e ripristino per
singolo cliente, ed esportazione dei dati banale. Nei nostri domini — sanità, fiscale, documentale
— questo vale più del costo operativo.

La soglia oltre la quale la scelta andrebbe rivalutata è documentata in
[ADR-0002](../../architecture/decisions/0002-tenant-isolation-strategy.md). Se un progetto la
supera, si apre una nuova ADR.

### Perché tutta questa cerimonia (Action, DTO, Repository) per un CRUD?

Non tutta: il principio 5 protegge la **logica di business**, non impone cerimonia sul CRUD banale.
Un'anagrafica in sola lettura non ha bisogno di entità di dominio, repository e mapper.

Le Action invece si usano sempre, anche per le operazioni semplici, perché il loro valore è
l'**inventario esplicito** delle operazioni possibili: sapere in un colpo d'occhio tutto ciò che
il sistema può modificare vale il costo di qualche classe in più.

### Non è troppa astrazione?

L'astrazione è cara e va giustificata. Per questo la regola è la **terza occorrenza**: si astrae
quando il pattern è dimostrato, non quando è previsto.

Se trovi un'astrazione nella Foundation usata da un solo progetto, quella è un errore: segnalalo.

---

## Sullo stack

### Perché non PostgreSQL, che sarebbe tecnicamente migliore?

Su diversi assi lo è. La scelta di MySQL/MariaDB dipende dall'infrastruttura dei clienti e dalla
competenza interna, non da una valutazione tecnica astratta. È una decisione **rivalutabile** con
ADR, se il contesto cambia.

Vedi [`docs/00-introduction/03-technology-stack.md`](03-technology-stack.md#alternative-valutate-e-scartate).

### Posso usare React/Vue su un progetto?

Non senza ADR. Introdurrebbe una seconda architettura frontend accanto a quella di
Filament/Livewire, da mantenere per anni.

Se un progetto ha un requisito che Livewire non copre (per esempio un'interfaccia offline-first),
quello è un problema concreto e la ADR è legittima. «Preferisco React» non lo è.

### Perché SQLite in sviluppo se in produzione c'è MySQL?

Velocità dei test e installazione locale immediata. È un compromesso con rischi reali, e per
questo la pipeline esegue la suite **due volte**: su SQLite e su MySQL. Le differenze non si
scoprono in produzione.

---

## Sul lavoro quotidiano

### Devo leggere tutta la documentazione prima di iniziare?

No. I percorsi di lettura in [`docs/README.md`](../README.md#percorsi-di-lettura) indicano il
minimo per ciascun ruolo: circa tre ore per orientarsi, due per la prima feature.

Il resto si consulta quando serve. La documentazione è organizzata per essere **consultata**, non
studiata.

### Cosa faccio se trovo un errore nella documentazione?

Lo correggi. Una correzione redazionale non richiede ADR né approvazioni particolari: branch,
commit, PR. Se invece l'errore riguarda una regola vincolante, apri una discussione prima di
cambiarla.

### E se la regola rallenta il mio lavoro?

Segnalalo con il caso concreto. Una regola che rallenta senza prevenire nulla è una regola
sbagliata, e va corretta. Una regola che rallenta perché previene un data leak resta.

La distinzione la fa il **problema che previene**: se non riesci a individuarlo leggendo la
regola, quella regola è scritta male.

### Come faccio a sapere se una cosa va nella Factory o nel progetto?

Test dei tre passaggi in
[`02-what-is-ai-factory.md`](02-what-is-ai-factory.md#il-confine-factory-o-progetto).
Nei casi dubbi: resta nel progetto. Promuovere dopo è facile, rimuovere dalla Foundation no.

---

## Sui costi e sui tempi

### Quanto tempo serve prima che la Factory ripaghi l'investimento?

Il ritorno arriva per livelli, non tutto alla fine:

| Livello | Investimento cumulato | Ritorno |
|---|---|---|
| Standardizzazione | regole + foundation iniziale | dal **primo** progetto: avvio in giorni invece che settimane |
| Assistenza guidata | agenti + prompt | dal **secondo**: le fasi ripetitive non si rifanno |
| Generazione autonoma | orchestrazione + affinamento | dal **terzo/quarto**: il costo marginale di un progetto crolla |

Il primo progetto costa **di più** del normale: è quello che valida la piattaforma. È un costo da
mettere in conto esplicitamente.

### Quanto costa mantenere la Factory?

Indicativamente: l'aggiornamento dello stack (trimestrale), la revisione periodica per area, e il
tempo di promozione del codice. Se supera stabilmente il tempo che libera sui progetti, la Factory
è sovradimensionata e va potata.

La metrica di controllo è l'efficacia sui progetti, non l'attività sulla Factory. Vedi
[`governance/quality-metrics.md`](../../governance/quality-metrics.md).

### E se l'azienda cambia tecnologia tra tre anni?

Le regole di processo, i workflow, la struttura degli agenti e la maggior parte della conoscenza
architetturale sono **indipendenti dallo stack**. Cambierebbero `foundation/`, `templates/` e le
regole specifiche del framework: una parte importante, ma non la maggioranza.

Una piattaforma che non sopravvive a un cambio di framework è costruita male, ed è un criterio da
tenere presente quando si scrive.

---

## Best practice

- Quando una domanda si ripresenta per la terza volta, aggiungerla qui.
- Rispondere anche alle obiezioni **valide**: una FAQ che dà sempre ragione alla Factory non viene
  creduta.
- Collegare ogni risposta al documento che la approfondisce.
- Mantenere le risposte brevi: chi legge una FAQ ha fretta.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Usare la FAQ come normativa | Le risposte sono divulgative, non prescrittive | La norma sta in `rules/` e nelle ADR |
| Rispondere «perché sì» | L'obiezione torna, più forte | Motivare o ammettere il compromesso |
| Non aggiornare dopo una ADR | La FAQ contraddice la norma | Revisione della FAQ ad ogni major |
| Nascondere i costi della Factory | Perdita di credibilità | Dichiarare i compromessi accettati |

---

## Checklist

- [ ] Ho cercato la risposta qui prima di chiedere.
- [ ] Se ho risposto tre volte alla stessa domanda, l'ho aggiunta.
- [ ] Le risposte che ho scritto rimandano al documento di approfondimento.
- [ ] Le risposte non contraddicono ADR o regole vigenti.

---

## Riferimenti

- [Visione](01-vision.md) · [Che cos'è la AI Factory](02-what-is-ai-factory.md)
- [Principi](04-principles.md) · [Stack](03-technology-stack.md)
- [Governance](../../governance/README.md)
- [Indice della documentazione](../README.md)
