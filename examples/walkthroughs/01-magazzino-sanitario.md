# Walkthrough 01 — Magazzino Sanitario

> Il percorso completo delle quattordici fasi su un progetto reale: due fermate, un rework, una ADR
> di progetto. **Autorità: nulla** — illustra, non prescrive.

| | |
|---|---|
| **Verticale** | Magazzino sanitario (dispositivi medici e materiale di consumo) |
| **Comando** | `loop crea "Magazzino Sanitario"` |
| **Fasi eseguite** | 14 su 14 |
| **Fermate** | 2 |
| **Rework** | 1 (fase 3, gate di sicurezza) |
| **Esito** | `completato` |

---

## Indice

1. [Descrizione](#descrizione)
2. [Il brief](#il-brief)
3. [Fase per fase](#fase-per-fase)
4. [Le fermate](#le-fermate)
5. [Le decisioni](#le-decisioni)
6. [Il rework](#il-rework)
7. [Il risultato](#il-risultato)
8. [Che cosa si sarebbe potuto fare meglio](#che-cosa-si-sarebbe-potuto-fare-meglio)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Un magazzino sanitario è un buon primo walkthrough perché contiene, in forma piccola, quasi tutte le
difficoltà che la Factory è costruita per gestire: dati sensibili, obblighi di tracciabilità, una
regola di dominio che sembra semplice e non lo è, e un committente che descrive il proprio lavoro
con parole che significano cose precise senza dirlo.

Questo documento riporta il percorso **come è andato**, comprese le due volte in cui si è fermato e
la volta in cui è tornato indietro.

---

## Il brief

Riportato come è arrivato, non ripulito. La differenza conta: un brief ripulito nasconde
esattamente le ambiguità che il processo deve intercettare.

```
Ci serve un gestionale per il magazzino della struttura. Gestiamo dispositivi
medici e materiale di consumo. Ogni articolo arriva in lotti, ogni lotto ha una
scadenza. Dobbiamo sapere in ogni momento quanto ne abbiamo e dove.

I lotti scaduti non si possono usare. Serve un avviso quando un lotto sta per
scadere.

Ogni movimento deve essere tracciato: chi, cosa, quando. Ce lo chiede la ASL.

Abbiamo tre reparti che prelevano dal magazzino centrale. I responsabili di
reparto devono vedere solo il proprio.

Vorremmo anche una pagina pubblica con i nostri riferimenti.
```

Cinque paragrafi, e almeno tre punti che non si possono implementare senza chiedere. Il processo ne
ha intercettati due; il terzo è emerso in fase 4, ed è nella sezione
[che cosa si sarebbe potuto fare meglio](#che-cosa-si-sarebbe-potuto-fare-meglio).

---

## Fase per fase

### Fase 0 — Foundation

**Prodotto**: scaffolding Laravel, `widstudios/foundation` installato, tre connessioni, due cartelle
di migration, pipeline con i sette gate, `CLAUDE.md` di progetto derivato dallo stub.

**Gate**: verde. `composer qa` su progetto vuoto, provisioning di un tenant di prova riuscito.

*Durata: 20 minuti.*

### Fase 1 — Analisi

**Prodotto**: glossario, sei entità, dodici casi d'uso, quattro regole di business, tre ruoli.

Il glossario è la parte che ha dato più valore, e non era ovvio in anticipo:

| Termine | Significato per il committente | Identificatore |
|---|---|---|
| articolo | ciò che si acquista, indipendente dalla consegna | `Article` |
| lotto | quantità ricevuta in una consegna, con scadenza propria | `Batch` |
| movimento | ogni variazione di giacenza, in entrata o uscita | `StockMovement` |
| prelievo | movimento in uscita verso un reparto | `StockMovement` con `MovementType::Outbound` |
| giacenza | quantità disponibile in questo momento | `Batch::$quantity` |
| ubicazione | dove il lotto si trova fisicamente | `Location` |

«Prelievo» e «movimento in uscita» erano la stessa cosa, e il committente usava entrambi. Senza il
glossario sarebbero diventate due entità.

**Gate**: rosso alla prima verifica — due domande aperte. Vedi [Le fermate](#le-fermate).

*Durata: 2 ore, più 1 giorno di attesa per le risposte.*

### Fase 2 — Architettura

**Prodotto**: due bounded context (`Warehouse`, `Procurement`), quattro moduli attivi (`tenancy`,
`auth`, `audit`, `cms`), tre ADR di progetto.

Decisione non ovvia: `Procurement` — fornitori e ordini — è stato tenuto **separato** da `Warehouse`
pur essendo piccolo. Motivo dichiarato: il committente ha detto «per ora gli ordini li facciamo per
telefono», e una funzionalità che oggi non esiste ma è nominata nel brief arriverà. Separarla dopo
costa una migrazione di dati; separarla ora costa una cartella.

**Gate**: verde.

*Durata: 3 ore.*

### Fase 3 — Database

**Prodotto**: 4 tabelle landlord, 11 tenant, 23 indici, factory per ogni model.

**Gate**: **rosso**. Vedi [Il rework](#il-rework).

*Durata: 4 ore, più 1 ora di rework.*

### Fase 4 — Backend

**Prodotto**: 6 entità con comportamento di dominio, 3 enum con macchina a stati, 2 value object
(`Quantity`, `BatchNumber`), 9 Action, 6 Query object, 4 repository, 3 job.

La regola che il brief esprimeva in cinque parole — «i lotti scaduti non si possono usare» — è
diventata:

```php
public function assertUsable(Clock $clock): void
{
    if ($this->status === BatchStatus::Quarantined) {
        throw BatchNotUsable::quarantined($this);
    }

    if ($this->expiry_date < $clock->today()) {
        throw BatchNotUsable::expired($this);
    }

    if ($this->quantity <= 0) {
        throw BatchNotUsable::depleted($this);
    }
}
```

Tre condizioni dove il committente ne aveva nominata una. Le altre due sono emerse dalle domande
della fase 1.

**Gate**: verde. Copertura Action 100%, value object 100%.

*Durata: 2 giorni.*

### Fase 5 — Filament

**Prodotto**: pannello tenant con 6 Resource, pannello di piattaforma con 2, 4 widget, 3 azioni
personalizzate.

Le tre azioni personalizzate — `quarantine`, `release`, `adjust` — sono state il punto di attenzione:
sono quelle che l'autorizzazione automatica non copre. Tutte e tre con `->authorize()` e delega a
un'Action.

**Gate**: verde.

*Durata: 1 giorno e mezzo.*

### Fase 6 — Frontend

**Prodotto**: landing page del modulo `cms`, tre sezioni tipizzate, due componenti Livewire per la
ricerca rapida.

Il quinto paragrafo del brief — «una pagina pubblica con i nostri riferimenti» — è stato risolto
attivando `cms` invece di costruire pagine su misura. Costo: zero righe di codice applicativo.

**Gate**: verde.

*Durata: 1 giorno.*

### Fase 7 — Security

**Prodotto**: 6 Policy, 24 permessi, 3 ruoli, verifica dei cinque punti dell'isolamento.

**Gate**: verde alla seconda lettura. La prima aveva rilevato un widget con una chiave di cache
scritta a mano — corretto in dieci minuti, senza rework di fase perché non toccava il gate della
fase 5.

*Durata: 4 ore.*

### Fase 8 — Testing

**Prodotto**: 187 test. Copertura complessiva 86%, Action 100%, Policy 100%, value object ed enum
100%.

| Categoria | Test | Durata |
|---|---|---|
| `Unit` | 61 | 3 s |
| `Feature` | 94 | 71 s |
| `Tenant` | 24 | 38 s |
| `Architecture` | 8 | < 1 s |

Suite completa: 1 minuto e 53 secondi, verde su SQLite e MySQL.

**Gate**: verde.

*Durata: 2 giorni.*

### Fase 9 — Review

**Prodotto**: 14 rilievi dal Reviewer, 6 dal Claude Reviewer — di cui **4 non presenti nel primo
elenco**.

I quattro rilievi aggiuntivi vengono dal fatto che il Claude Reviewer legge il codice **prima** dei
report delle fasi precedenti. Il più significativo: `RegisterMovementAction` verificava la scadenza
del lotto **prima** del lock pessimistico, e non dopo. Sotto concorrenza, due prelievi simultanei
potevano superare entrambi la verifica.

Nessuno dei report di fase lo segnalava, perché nessuno dei report descriveva l'ordine delle
operazioni.

**Gate**: rosso, poi verde dopo le correzioni.

*Durata: 6 ore.*

### Fase 10 — Performance

**Prodotto**: 5 indici aggiunti, 2 query riscritte, 3 widget messi in cache.

Volumi di prova: 120.000 lotti, 800.000 movimenti, 3.000 articoli.

| Percorso | Prima | Dopo |
|---|---|---|
| Elenco lotti (50 righe) | 161 query, 2.4 s | 11 query, 180 ms |
| Dettaglio lotto | 24 query, 410 ms | 7 query, 90 ms |
| Dashboard | 31 query, 1.9 s | 9 query, 310 ms |

Il primo caso era un N+1 classico: `$batch->article->name` in una colonna, senza eager loading. Non
si vedeva con i 40 lotti dei dati di prova.

**Gate**: verde.

*Durata: 1 giorno.*

### Fase 11 — Refactoring

**Prodotto**: 2 duplicazioni promosse a metodi di dominio, 1 servizio diviso in due.

Il servizio diviso era `StockService`, che aveva accumulato calcolo delle giacenze e generazione
degli avvisi di scadenza. Due responsabilità che non condividevano nulla: il criterio per dividerlo
non è stata la lunghezza, ma il fatto che nessun metodo dell'una usasse i dati dell'altra.

**Gate**: verde, con la suite invariata — nessun test modificato, che è la definizione di
refactoring.

*Durata: 4 ore.*

### Fase 12 — Documentazione

**Prodotto**: 23 documenti, 3 ADR, documentazione API di 14 endpoint, guida all'esercizio.

**Gate**: rosso alla prima verifica — la guida di installazione ometteva `php artisan
tenants:migrate`. Scoperto eseguendola su un ambiente pulito: il primo tenant risultava senza
tabelle.

*Durata: 1 giorno.*

### Fase 13 — Deploy

**Prodotto**: immagine, pipeline, ambienti, quattro runbook, piano di rilascio con ritorno provato.

Durata delle migration misurata su un tenant con volumi reali: 4.2 s. Stima su 3 tenant: sotto il
minuto.

**Gate**: verde.

*Durata: 6 ore.*

---

## Le fermate

### Fermata 1 — fase 1: «i lotti scaduti non si possono usare»

**La domanda**

> «Non si possono usare» significa che non si possono **scaricare**, oppure che non si possono
> nemmeno **vedere** negli elenchi operativi? E un lotto che scade mentre è già impegnato in un
> prelievo in corso: il prelievo si completa o si blocca?

**Perché non era decidibile**

Le due letture producono software diversi ed entrambi sembrano corretti a chi non conosce il
dominio. La seconda domanda ha conseguenze operative immediate: bloccare un prelievo in corso
significa che un infermiere si trova senza materiale a metà di una procedura.

È il criterio che distingue un'assunzione da una domanda aperta: **se una scelta sbagliata
produrrebbe software plausibile e sbagliato, non si sceglie.**

**La risposta**

> I lotti scaduti restano **visibili** — servono per l'inventario e per la resa al fornitore — ma
> non sono **prelevabili**. Un prelievo già iniziato si completa: fermarlo creerebbe un problema
> peggiore. La scadenza si valuta al momento della registrazione del movimento, non a quello
> dell'apertura della schermata.

**Conseguenza**: lo stato `Expired` non nasconde il lotto; la verifica sta in `assertUsable()`,
invocata dall'Action al momento della registrazione. Un lotto può scadere tra l'apertura della
schermata e la conferma, ed è corretto che il prelievo passi.

### Fermata 2 — fase 1: «i responsabili di reparto devono vedere solo il proprio»

**La domanda**

> Il reparto è una **suddivisione dentro il magazzino del cliente**, oppure ogni reparto è un
> cliente separato con il proprio magazzino?

**Perché non era decidibile**

È la domanda che decide l'architettura dei dati, e sbagliarla costa una migrazione completa. Se i
reparti fossero tenant separati servirebbero tre database, tre provisioning e nessuna visione
d'insieme; se sono una suddivisione, servono un'entità `Department` e un filtro di autorizzazione.

Il brief supportava entrambe le letture: «tre reparti che prelevano dal magazzino centrale»
suggerisce la suddivisione, «devono vedere solo il proprio» suggerisce la separazione.

**La risposta**

> Un cliente solo: la struttura. I reparti sono una suddivisione interna. La direzione deve vedere
> tutto, i responsabili solo il proprio reparto.

**Conseguenza**: `Department` è un'entità del dominio, non un tenant. La visibilità si esprime con
un permesso — `movement.view.own_department` contro `movement.view.all` — verificato nella Policy.
Nessuna colonna `tenant_id`, nessun tenant aggiuntivo.

Se la risposta fosse stata l'opposta, la fase 3 avrebbe prodotto uno schema completamente diverso.
Il costo della domanda: un giorno di attesa. Il costo di indovinare male: la riscrittura delle fasi
3 e 4.

---

## Le decisioni

Tre ADR di progetto, oltre a quelle ereditate dalla Factory.

### ADR-P01 — Giacenza calcolata o memorizzata

**Contesto.** La giacenza di un lotto può essere ricalcolata dalla somma dei movimenti, oppure
mantenuta come colonna.

**Decisione.** Colonna `quantity` mantenuta, ricalcolabile da comando.

**Alternativa scartata.** Somma dei movimenti a ogni lettura: corretta per costruzione, e con
800.000 movimenti rende l'elenco dei lotti inutilizzabile. Provata: 2.4 secondi con dati reali.

**Conseguenze.** La colonna può divergere dalla verità. Mitigazione: ogni scrittura passa da
un'Action con lock pessimistico, esiste `stock:recalculate` idempotente, e un controllo notturno
segnala le divergenze invece di correggerle in silenzio — una divergenza è un difetto, e correggerla
senza segnalarla lo nasconde.

### ADR-P02 — Reparti come entità, non come tenant

**Contesto.** Esito della [fermata 2](#fermata-2--fase-1-i-responsabili-di-reparto-devono-vedere-solo-il-proprio).

**Decisione.** `Department` è un'entità di dominio; la visibilità si esprime con i permessi.

**Alternativa scartata.** Un tenant per reparto: tre database, nessuna visione d'insieme per la
direzione, e un'aggregazione cross-tenant che le regole vietano.

**Soglia di rivalutazione.** Se la struttura acquisisse un secondo presidio con magazzino
autonomo, quello sarebbe un tenant.

### ADR-P03 — Tracciabilità con il modulo audit

**Contesto.** «Ce lo chiede la ASL» è un requisito normativo, non una preferenza.

**Decisione.** Modulo `audit` attivo, con registrazione anche degli accessi in lettura alle schede
dei dispositivi impiantabili.

**Alternativa scartata.** Registrazione da observer: registra le colonne cambiate, non l'intenzione.
`documents.status: active → archived` non risponde a nessuna domanda che un'ispezione porrebbe.

**Conseguenze.** Ogni Action che tocca dati tracciabili scrive una voce, e la registrazione può
essere dimenticata. Mitigazione: voce nella checklist di sicurezza e verifica in revisione.

---

## Il rework

**Fase 3, gate di sicurezza: rosso.**

Due voci non soddisfatte:

1. **`movements.quantity` dichiarata `float`.** Su un magazzino sanitario le quantità sono spesso
   frazionarie — millilitri, grammi — e gli errori di arrotondamento si accumulano nei totali. Non
   sono recuperabili: una volta scritti, non si sa più quale fosse il valore giusto.

2. **Nessun indice su `batches.expiry_date`.** Il caso d'uso «lotti in scadenza» era dichiarato in
   fase 1 e sarebbe stato una scansione completa su 120.000 righe, a ogni caricamento della
   dashboard.

**Correzione**: `decimal(12,3)`, indice composto `(status, expiry_date)` nell'ordine
uguaglianza-intervallo.

**Costo**: 1 ora, a fase 3 non ancora consegnata.

**Costo se fosse passato**: il primo difetto si sarebbe manifestato come totali che non tornano,
mesi dopo, senza modo di ricostruire i valori corretti. Il secondo come una dashboard lenta, con la
causa attribuita al numero di widget.

È il tipo di difetto che un gate esiste per intercettare: nessuno dei due produce un errore, ed
entrambi diventano molto costosi con il tempo.

---

## Il risultato

| | |
|---|---|
| **Durata totale** | 11 giorni lavorativi, di cui 1 di attesa sulle fermate |
| **Entità di dominio** | 6 |
| **Action** | 9 |
| **Endpoint API** | 14 |
| **Filament Resource** | 8 |
| **Test** | 187, suite in 1m53s |
| **Copertura** | 86% complessiva; 100% su Action, Policy, VO ed enum |
| **Moduli attivi** | tenancy, auth, audit, cms |
| **ADR di progetto** | 3 |
| **Documenti** | 23 |
| **Rework** | 1 |
| **Righe di codice applicativo** | ~4.200 |

Le ~4.200 righe non comprendono ciò che è arrivato dalla Foundation e dai moduli: tenancy,
autenticazione, permessi, audit, CMS e infrastruttura di test. È la differenza che la Factory
produce, ed è misurabile.

---

## Che cosa si sarebbe potuto fare meglio

Questa sezione è obbligatoria in ogni walkthrough, ed è la più utile.

**1. La terza ambiguità non è stata intercettata.** «Dobbiamo sapere in ogni momento quanto ne
abbiamo **e dove**» conteneva un requisito di ubicazione che nessuno ha notato in fase 1. È emerso
in fase 4, quando il committente ha visto il primo prototipo. Costo: una tabella e una relazione
aggiunte a fase 4 avviata, con una migration in più.

*Lezione*: le congiunzioni nei brief nascondono requisiti. «E dove» era un secondo requisito
travestito da precisazione del primo.

**2. Il gate di performance è arrivato troppo tardi.** L'N+1 sull'elenco dei lotti esisteva dalla
fase 5 e si è scoperto in fase 10. Correggerlo ha richiesto di toccare il Resource, cioè un
artefatto di una fase già chiusa.

*Lezione*: il rilevamento N+1 va tenuto **attivo in sviluppo**, non usato come strumento di fase.
Costa nulla e intercetta il difetto nel momento in cui viene scritto.

**3. Il glossario non è stato riletto dal committente.** È stato costruito dalle sue parole e dato
per buono. In fase 12 è emerso che «resa» significava due cose diverse — restituzione al fornitore e
rientro da un reparto — e nel codice era una sola.

*Lezione*: il glossario si fa **rileggere**, non si deduce. Costa mezz'ora e chiude una classe
intera di malintesi.

**4. La documentazione dell'esercizio è stata scritta senza provare le procedure.** Il gate della
fase 12 ha intercettato l'omissione nella guida di installazione, ma solo perché qualcuno l'ha
eseguita davvero. I runbook non sono stati provati, e restano da verificare.

*Lezione*: un runbook non provato è una bozza, e va dichiarato come tale.

---

## Esempi

### Come una frase è diventata tre condizioni

```
Brief:      «I lotti scaduti non si possono usare.»
Fermata:    non si possono scaricare, o non si possono vedere?
Risposta:   visibili ma non prelevabili; prelievo in corso si completa
Dominio:    Batch::assertUsable(Clock $clock) — tre condizioni
Test:       tre test, uno per condizione, più il caso limite del prelievo in corso
```

Cinque parole del committente, un giorno di attesa, tre condizioni e quattro test. Senza la fermata:
una condizione, e un sistema che blocca i prelievi in corso.

---

## Best practice

- Riportare il brief **come è arrivato**: un brief ripulito nasconde le ambiguità da intercettare.
- Fermarsi quando una scelta sbagliata produrrebbe software plausibile e sbagliato.
- Far rileggere il glossario al committente, non dedurlo.
- Tenere attivo il rilevamento N+1 in sviluppo, non rimandarlo alla fase di prestazioni.
- Registrare ogni rework con il costo pagato e il costo evitato: è ciò che giustifica i gate.
- Scrivere la sezione «che cosa si sarebbe potuto fare meglio» prima di considerare chiuso il
  progetto.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Ambiguità decisa dall'agente | Software plausibile e sbagliato | Fermata |
| Congiunzione nel brief non esaminata | Requisito scoperto a fase avviata | Ogni «e» va interrogato |
| Glossario dedotto e non riletto | Un termine per due concetti | Rilettura con il committente |
| N+1 rimandato alla fase 10 | Correzioni su artefatti di fasi chiuse | Rilevamento attivo in sviluppo |
| `float` per quantità | Errori di arrotondamento non recuperabili | `decimal` |
| Indice mancante su un filtro dichiarato | Scansione completa a ogni caricamento | Indice dal caso d'uso |
| Verifica prima del lock | Due operazioni superano entrambe il controllo | Lock, poi verifica |
| Runbook non provati | Non funzionano quando servono | Prova su staging |
| Walkthrough senza sezione onesta | Materiale pubblicitario | Sezione obbligatoria |

---

## Checklist

- [ ] Ho letto le fermate prima del resto.
- [ ] Sto adottando il metodo, non il dominio del magazzino.
- [ ] Ho letto «che cosa si sarebbe potuto fare meglio».
- [ ] Ho verificato che il walkthrough sia coerente con la Factory attuale.

---

## Riferimenti

- [Walkthrough](README.md) · [Examples](../README.md)
- [Master workflow](../../workflows/00-master-workflow.md) · [`loop crea`](../../prompts/loop-crea.md)
- [Protocollo agenti](../../agents/00-agent-protocol.md) · [Orchestratore](../../agents/16-orchestrator-agent.md)
- [Modello di brief](../../docs/06-reference/01-project-brief-template.md)
- [Frammenti di codice](../code/README.md)
