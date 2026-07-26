# ADR-0008 — Orchestrazione degli agenti

> Agenti specializzati con responsabilità ristrette, coordinati da un orchestratore, con quality
> gate bloccanti tra le fasi e revisione indipendente.

| | |
|---|---|
| **Stato** | Accettata |
| **Data** | 2026-07-25 |
| **Decisore** | Factory Owner |
| **Impatto** | processo di produzione, agenti, prompt, workflow |
| **Reversibilità** | reversibile |

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

L'obiettivo della Factory è che `loop crea "Nome Progetto"` produca un'applicazione conforme. La
domanda architetturale è **come organizzare l'automazione**.

Osservazioni preliminari, dalla sperimentazione su singole attività:

1. **Un esecutore generico produce output non ripetibile.** La stessa richiesta, formulata in due
   momenti, dà risultati strutturalmente diversi.
2. **Il contesto eccessivo peggiora il risultato.** Fornire l'intero repository diluisce
   l'attenzione e porta a seguire dettagli irrilevanti.
3. **Senza verifica intermedia, i difetti si amplificano.** Un errore nello schema del database si
   propaga a backend, interfacce e test, moltiplicando il lavoro di correzione.
4. **La revisione dello stesso esecutore che ha prodotto il codice è debole.** Tende a confermare le
   proprie scelte.

---

## Decisione

### Agenti specializzati

**Sedici agenti**, ognuno con: responsabilità, input dichiarati, output dichiarati, **limiti
espliciti**, workflow, prompt completo.

| Fase | Agente | Fase | Agente |
|---|---|---|---|
| 0 | Foundation | 7 | Security |
| 1 | Business Analyst | 8 | Testing |
| 2 | Architect | 9 | Reviewer + Claude Reviewer |
| 3 | Database | 10 | Performance |
| 4 | Backend | 11 | Refactoring |
| 5 | Filament | 12 | Documentation |
| 6 | Frontend | 13 | Deploy |
| — | Orchestrator (coordina) | | |

La sezione «limiti» è obbligatoria: un agente che può fare tutto non è affidabile; uno che fa una
cosa e sa quando fermarsi lo è.

### Quality gate bloccanti

Ogni fase termina con una checklist verificabile. **Il gate è bloccante**: non si passa alla fase
successiva con un gate rosso.

Massimo **tre** cicli di rework per fase. Al terzo fallimento l'orchestratore si ferma e richiede
una diagnosi umana: il problema è a monte, non nella fase.

### Revisione indipendente

Il **Claude Reviewer** è un agente distinto dal Reviewer, con prompt e contesto propri, che rilegge
gli artefatti senza aver partecipato alla loro produzione.

### Contesto selettivo

Ogni invocazione riceve: identità dell'agente, regole applicabili, artefatti delle fasi precedenti,
compito, gate di uscita. **Non** l'intero repository.

### Fermate su decisioni di dominio

L'orchestratore si ferma e chiede quando incontra: ambiguità di dominio, regola di business non
specificata, conflitto tra requisito e regola della Factory, requisito normativo dubbio.

La domanda è **specifica**, con opzioni e conseguenze, non «cosa faccio?».

---

## Alternative valutate

### Alternativa A — un agente generico

Un solo esecutore con l'intero contesto della Factory.

**Scartata** per le osservazioni 1 e 2 del contesto: output non ripetibile e attenzione diluita.
Inoltre non permette la revisione indipendente, né la verifica per fase.

### Alternativa B — agenti senza gate intermedi

Sedici agenti in sequenza, verifica solo alla fine.

**Scartata** per l'osservazione 3: un difetto nello schema scoperto alla fine richiede di rifare
backend, interfacce e test. Il costo di correzione cresce di un ordine di grandezza per fase
attraversata.

### Alternativa C — agenti in parallelo

Più agenti che lavorano contemporaneamente su parti diverse.

**Scartata** per le dipendenze: il backend richiede lo schema, le interfacce richiedono il backend.
Il parallelismo è ammesso solo dove le dipendenze lo consentono (per esempio documentazione e
performance sulla stessa base), e resta un'ottimizzazione, non la struttura.

### Alternativa D — supervisione umana continua

Un umano approva ogni passaggio.

**Scartata** come modello di riferimento perché annulla il beneficio dell'automazione. È però la
modalità del **livello 2 di maturità** ([visione](../../docs/00-introduction/01-vision.md)): si
adotta durante la validazione della Factory, non come stato finale.

### Confronto

| Asse | Agenti + gate (adottata) | Agente generico | Senza gate | Supervisione continua |
|---|---|---|---|---|
| Ripetibilità | **alta** | bassa | media | alta |
| Costo di correzione dei difetti | **basso** | alto | **molto alto** | basso |
| Revisione indipendente | **sì** | no | sì | sì |
| Autonomia | alta | alta | alta | **nulla** |
| Complessità del processo | media | bassa | bassa | bassa |
| Costo di manutenzione dei prompt | **alto** | basso | alto | basso |

---

## Conseguenze

### Positive

- Output ripetibile: la stessa fase, con gli stessi input, produce artefatti equivalenti.
- I difetti sono intercettati nella fase in cui nascono.
- La revisione indipendente rileva ciò che il produttore non vede.
- I limiti espliciti rendono prevedibile ciò che un agente farà e non farà.
- Le metriche per fase indicano quale prompt va migliorato.
- Il processo funziona identico se eseguito da persone.

### Negative (accettate consapevolmente)

- **Sedici prompt da mantenere.** Ogni cambio di regola può richiedere l'aggiornamento di più
  prompt.
- **Processo più lungo.** I gate aggiungono passaggi rispetto a un'esecuzione diretta.
- **Costo maggiore per progetto.** Più invocazioni, più contesto ripetuto, più revisioni.
- **Rischio di gate formali.** Una checklist spuntata senza verifica reale è peggio dell'assenza di
  checklist.
- **Handoff da progettare.** Ogni passaggio tra agenti richiede artefatti dichiarati e stabili.

### Impatto operativo

| Area | Effetto |
|---|---|
| Processo | 14 fasi con gate, rework limitato a tre cicli |
| Manutenzione | prompt versionati, corretti quando un errore ricorre |
| Metriche | tracciamento per fase: gate superati al primo tentativo, interventi umani |
| Formazione | il processo è documentato e utilizzabile anche senza agenti |

---

## Soglia di rivalutazione

1. Se i **gate superati al primo tentativo** scendono stabilmente sotto il **40%**: i prompt o i
   gate sono mal calibrati.
2. Se gli **interventi umani per progetto** superano stabilmente **20**: l'automazione non sta
   ripagando il proprio costo.
3. Se il **costo per progetto** cresce senza una corrispondente riduzione del tempo di consegna.
4. Se emergessero strumenti capaci di gestire l'intero processo con ripetibilità comparabile:
   riconsiderare la suddivisione in sedici agenti.

---

## Verifica

| Verifica | Strumento | Automatica |
|---|---|---|
| Ogni agente ha responsabilità, I/O, limiti, workflow, prompt | script di verifica | sì |
| Ogni fase ha una checklist di uscita | script di verifica | sì |
| I gate sono bloccanti nel workflow | revisione del processo | no |
| Le metriche per fase sono raccolte | strumentazione | sì |
| Il Claude Reviewer ha contesto indipendente | revisione dei prompt | no |
| Rework limitato a tre cicli | logica dell'orchestratore | sì |

---

## Riferimenti

- [Indice degli agenti](../../agents/README.md) · [Protocollo agenti](../../agents/00-agent-protocol.md)
- [Orchestratore](../../agents/16-orchestrator-agent.md)
- [Contratto `loop crea`](../../prompts/loop-crea.md)
- [Master workflow](../../workflows/00-master-workflow.md)
- [Metriche di qualità](../../governance/quality-metrics.md)
