# ADR-0003 — Architettura a livelli

> Quattro livelli con dipendenze dirette verso il dominio, che non conosce il framework.

| | |
|---|---|
| **Stato** | Accettata |
| **Data** | 2026-07-25 |
| **Decisore** | Architecture Owner |
| **Impatto** | tutto il codice applicativo |
| **Reversibilità** | reversibile con costo alto |

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

I nostri gestionali vivono 5-10 anni e attraversano più aggiornamenti major del framework. Nei
progetti precedenti, costruiti con la struttura predefinita di Laravel (`app/Models`,
`app/Http/Controllers`, `app/Services`), sono emersi tre problemi ricorrenti:

1. **Logica di business dispersa.** La stessa regola compariva in un controller, in un observer e
   in un comando, con implementazioni divergenti nel tempo.
2. **Test lenti e fragili.** Ogni verifica di una regola richiedeva database e framework avviati:
   suite da minuti, test che si rompevano ad ogni refactoring.
3. **Aggiornamenti costosi.** Il codice di dominio era intrecciato con le API del framework, quindi
   ogni major richiedeva interventi diffusi.

La collocazione per **tipo tecnico** (`Models/`, `Services/`, `Helpers/`) non comunica quali
dipendenze siano ammesse: nulla impedisce a un model di chiamare un servizio HTTP, o a un helper di
contenere una regola di business.

---

## Decisione

Si adotta una struttura per **livello architetturale**, con direzione delle dipendenze verificata
automaticamente.

```
Presentazione (Http, Filament, Console)
        │ usa
        ▼
Applicazione (Actions, Queries, Data, Services)
        │ usa
        ▼
Dominio (entità, VO, enum, eventi, contratti)  ◀── implementa ── Infrastruttura
```

Regole vincolanti:

- `Domain/` non importa da `Illuminate/`, `Application/`, `Infrastructure/`, `Http/`.
- `Application/` non importa da `Illuminate\Http` né da `Infrastructure/`.
- `Http/` e `Filament/` non importano da `Infrastructure/`.
- I contratti stanno nel dominio; le implementazioni nell'infrastruttura.

Si ammettono **due gradi di purezza**, scelti per bounded context e dichiarati nel README del
modulo:

| Grado | Quando | Entità |
|---|---|---|
| Pragmatico | CRUD con poche regole | model Eloquent con metodi di dominio |
| Puro | logica ricca, invarianti, macchine a stati | classi PHP pure, mappate da repository |

Restano vincolanti in entrambi i gradi: enum per gli stati, value object per i dati validati,
Action per le mutazioni, contratti per le dipendenze esterne.

---

## Alternative valutate

### Alternativa A — struttura predefinita di Laravel

`app/Models`, `app/Http`, `app/Services`, con logica nei model o nei service.

**Scartata** perché è la struttura che ha prodotto i tre problemi descritti nel contesto. Non
esprime vincoli di dipendenza, quindi non è verificabile automaticamente.

### Alternativa B — architettura esagonale integrale

Dominio completamente puro, porte e adattatori per ogni interazione, mappatura obbligatoria tra
entità e model in ogni caso.

**Scartata** perché il costo è sproporzionato per la quota di CRUD semplice presente in ogni
gestionale. Un'anagrafica di comuni in sola lettura non ha bisogno di entità, repository, mapper e
porte: la cerimonia rallenta senza prevenire nulla.

Il grado «puro» della decisione adottata è disponibile per i contesti che lo giustificano: si
ottiene il beneficio dove serve, senza pagarlo dove non serve.

### Confronto

| Asse | Livelli con due gradi (adottata) | Laravel predefinito | Esagonale integrale |
|---|---|---|---|
| Costo iniziale | medio | basso | **alto** |
| Testabilità del dominio | alta dove serve | bassa | massima |
| Verificabilità automatica | **sì** | no | sì |
| Costo sul CRUD semplice | basso | basso | **alto** |
| Resistenza agli aggiornamenti | alta | bassa | massima |
| Curva di apprendimento | media | bassa | alta |
| Rischio di applicazione parziale | medio | — | **alto** (si abbandona) |

L'ultima riga è decisiva: un'architettura troppo onerosa viene abbandonata nella pratica, e il
risultato è peggiore di una meno ambiziosa applicata davvero.

---

## Conseguenze

### Positive

- La logica di business ha un solo posto in cui vivere.
- I test di dominio girano senza database, in millisecondi.
- Gli aggiornamenti di framework toccano l'infrastruttura, non il dominio.
- I vincoli sono verificati da test, non dalla disciplina.
- Ogni punto di ingresso (HTTP, Filament, CLI) invoca la stessa logica.

### Negative (accettate consapevolmente)

- **Più classi e più file.** Un'operazione semplice coinvolge Action, DTO, eventualmente eventi.
- **Curva di apprendimento.** Chi arriva da Laravel «standard» deve imparare la collocazione.
- **Mappature nel grado puro.** Tradurre tra entità e model è codice aggiuntivo.
- **Ambiguità del confine tra i due gradi.** Serve una decisione esplicita per contesto, che
  qualcuno deve prendere e documentare.
- **Tentazione della scorciatoia.** Sotto scadenza, mettere la logica nel controller è più rapido:
  da qui la necessità dei test di architettura.

### Impatto operativo

| Area | Effetto |
|---|---|
| Sviluppo | collocazione esplicita per ogni artefatto |
| Test | test di architettura obbligatori dal primo giorno |
| Revisione | la checklist verifica il livello di appartenenza |
| Documentazione | ogni modulo dichiara il proprio grado di purezza |

---

## Soglia di rivalutazione

1. Se i test di architettura vengono **sistematicamente aggirati** o disabilitati: significa che
   la struttura è troppo onerosa e va semplificata.
2. Se il grado «puro» non viene usato in nessun progetto per due anni: la distinzione è inutile e
   va rimossa.
3. Se un aggiornamento major del framework tocca comunque il dominio: la separazione non sta
   funzionando come previsto.

---

## Verifica

| Verifica | Strumento | Automatica |
|---|---|---|
| `Domain/` non dipende da `Illuminate/` | Pest Arch | sì |
| `Application/` non conosce HTTP | Pest Arch | sì |
| `Http/` e `Filament/` non usano `Infrastructure/` | Pest Arch | sì |
| I contratti stanno nel dominio | Pest Arch | sì |
| Il grado di purezza è dichiarato | revisione | no |
| Corpo dei controller sotto le 10 righe | Pest Arch | sì |

---

## Riferimenti

- [Livelli architetturali](../02-layers.md)
- [Dominio](../12-domain-layer.md) · [Applicativo](../13-application-layer.md) · [Infrastruttura](../14-infrastructure-layer.md) · [Presentazione](../15-presentation-layer.md)
- [ADR-0005 — Action Pattern](0005-action-pattern.md)
- [Struttura di progetto](../../docs/02-conventions/02-project-layout.md)
