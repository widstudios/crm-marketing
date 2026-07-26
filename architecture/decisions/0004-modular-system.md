# ADR-0004 — Sistema modulare

> Monolite modulare con moduli disinstallabili che comunicano per eventi e contratti, non
> microservizi.

| | |
|---|---|
| **Stato** | Accettata |
| **Data** | 2026-07-25 |
| **Decisore** | Architecture Owner |
| **Impatto** | struttura di tutti i progetti |
| **Reversibilità** | reversibile con costo medio |

---

## Indice

1. [Contesto](#contesto)
2. [Decisione](#decisione)
3. [Alternative valutate](#alternative-valutate)
4. [Conseguenze](#conseguenze)
5. [Soglia di rivalutazione](#soglia-di-rivalutazione)
6. [Verifica](#verifica)
7. [Riferimenti](#riferimenti)

---

## Contesto

I gestionali crescono per aggiunta: un magazzino sanitario nasce con articoli e movimenti, e dopo
due anni ha lotti, ubicazioni, inventari, ordini, tracciabilità, reportistica.

Nei progetti precedenti questa crescita ha prodotto un effetto ricorrente: dopo il terzo o quarto
ambito funzionale, ogni aggiunta richiedeva di toccare parti apparentemente non correlate.
Aggiungere gli ordini richiedeva modifiche al codice dei movimenti; disattivare una funzionalità
per un cliente era impossibile.

Serve una struttura che permetta di **aggiungere senza toccare** e di **disattivare senza
rompere**. Un secondo requisito, commerciale: clienti diversi acquistano insiemi diversi di
funzionalità, e la piattaforma deve poterli comporre.

---

## Decisione

Si adotta un **monolite modulare**: un solo deploy, una sola base di codice, moduli con confini
dichiarati e verificati.

Ogni modulo:

- ha un manifesto `module.json` con dipendenze, permessi, eventi emessi e ascoltati;
- replica al proprio interno la stratificazione dell'applicazione;
- ha migration, traduzioni, test e documentazione propri;
- comunica con gli altri **solo** per eventi o contratti pubblicati;
- si installa e si disinstalla senza rompere gli altri.

Tre tipi di modulo:

| Tipo | Dove vive | Riuso |
|---|---|---|
| Core | Foundation | tutti i progetti |
| Catalogo | Factory | più progetti |
| Dominio | repository del progetto | un progetto |

Criterio di indipendenza, verificabile: **se disinstallando un modulo la suite degli altri
fallisce, quel modulo non è indipendente**.

---

## Alternative valutate

### Alternativa A — monolite non modulare

Struttura per livello, senza confini interni ulteriori.

**Scartata** perché non risolve il problema della crescita: dopo il quarto ambito funzionale, tutto
dipende da tutto. Inoltre non permette la composizione commerciale delle funzionalità.

### Alternativa B — microservizi

Un servizio per ambito funzionale, comunicazione via rete.

**Scartata** per sproporzione tra costi e benefici nel nostro contesto:

| Costo dei microservizi | Nel nostro contesto |
|---|---|
| Coerenza distribuita | un movimento di magazzino deve essere transazionale: con servizi separati servirebbero saghe |
| Osservabilità distribuita | tracciamento tra servizi, con team piccolo |
| Deploy multipli | N pipeline, N versioni compatibili tra loro |
| Latenza di rete | ogni join diventa una chiamata |
| Complessità operativa | orchestrazione, service mesh, scoperta dei servizi |
| Multitenancy moltiplicata | ogni servizio deve risolvere il tenant |

I benefici (scalatura indipendente, tecnologie diverse per servizio, isolamento dei guasti) non
corrispondono a esigenze reali: i nostri volumi sono serviti da un monolite, lo stack è unico per
decisione, e l'isolamento dei guasti si ottiene con le code.

### Alternativa C — pacchetti Composer separati

Ogni modulo come pacchetto versionato indipendentemente.

**Scartata** per i moduli di dominio: il costo di gestione (versioni, rilasci, compatibilità
incrociata) supera il beneficio quando i moduli evolvono insieme nello stesso progetto. **Adottata**
invece per i moduli di catalogo, che sono condivisi tra progetti.

### Confronto

| Asse | Monolite modulare (adottata) | Monolite semplice | Microservizi |
|---|---|---|---|
| Costo iniziale | medio | basso | **alto** |
| Crescita per aggiunta | **buona** | scarsa | buona |
| Transazionalità | **nativa** | nativa | saghe |
| Complessità operativa | bassa | bassa | **alta** |
| Composizione commerciale | **sì** | no | sì |
| Scalatura indipendente | no | no | sì |
| Adatto al nostro team | **sì** | sì | no |

---

## Conseguenze

### Positive

- Le funzionalità si aggiungono senza toccare quelle esistenti.
- Le transazioni restano native: nessuna coerenza eventuale dove serve atomicità.
- Un deploy, una pipeline, un insieme di runbook.
- I moduli di catalogo si riusano tra progetti come dipendenze versionate.
- La composizione commerciale è possibile: un cliente attiva ciò che ha acquistato.

### Negative (accettate consapevolmente)

- **Comunicazione indiretta.** Un evento è meno leggibile di una chiamata diretta: serve la mappa
  degli eventi per capire il flusso.
- **Piccole duplicazioni tra moduli.** A volte è preferibile duplicare un dato invece di creare una
  dipendenza.
- **Nessuna scalatura indipendente.** Se un ambito funzionale richiedesse risorse molto diverse,
  non è separabile senza ripensare l'architettura.
- **Disciplina necessaria.** I confini si erodono facilmente: da qui l'obbligo dei test di
  architettura.
- **Costo di manutenzione dei manifesti.** Vanno tenuti allineati al codice.

### Impatto operativo

| Area | Effetto |
|---|---|
| Sviluppo | ogni funzionalità in un modulo, con manifesto |
| Test | test di architettura sulle dipendenze; test di disinstallazione |
| Deploy | migration per modulo, su tutti i tenant |
| Esercizio | moduli attivabili per tenant secondo il piano |

---

## Soglia di rivalutazione

1. **Un ambito funzionale con esigenze di risorse radicalmente diverse** (per esempio telemetria a
   volumi molto elevati): valutare l'estrazione di quel singolo servizio, non la conversione
   generale.
2. **Oltre 25 moduli in un progetto**: valutare se i confini sono troppo fini.
3. **Team oltre le 15 persone su un singolo prodotto**: la separazione dei deploy potrebbe iniziare
   a ripagare il proprio costo.
4. **Tempo di esecuzione della suite oltre i 20 minuti**: valutare la separazione per accelerare i
   cicli.

---

## Verifica

| Verifica | Strumento | Automatica |
|---|---|---|
| Nessun accesso diretto ai model di altri moduli | Pest Arch | sì |
| Nessuna dipendenza circolare | analisi dei manifesti | sì |
| Permessi usati tutti dichiarati | script di verifica | sì |
| Il modulo funziona senza i moduli facoltativi | suite senza di essi | sì |
| Migration reversibili | rollback in CI | sì |
| La disinstallazione non rompe gli altri | test di disinstallazione | sì |

---

## Riferimenti

- [Sistema modulare](../10-modular-system.md) · [Contratto di modulo](../11-module-contract.md)
- [Eventi e messaggistica](../21-events-and-messaging.md)
- [Blueprint di modulo](../../modules/_blueprint/README.md) · [Catalogo](../../modules/README.md)
- [ADR-0003 — Architettura a livelli](0003-layered-architecture.md)
