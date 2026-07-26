# Prompt — aggiungere un modulo

> Un nuovo ambito funzionale su un progetto esistente: percorso completo, ma limitato al modulo.

| | |
|---|---|
| **Versione** | 1.0.0 |
| **Agenti** | Architect → Database → Backend → Filament/Frontend → Security → Testing → Documentation |

---

## Indice

1. [Descrizione](#descrizione) 2. [Quando si usa](#quando-si-usa) 2. [Prerequisiti](#prerequisiti) 3. [Sequenza](#sequenza)
4. [Il prompt](#il-prompt) 5. [Definizione di «fatto»](#definizione-di-fatto) 6. [Esempi](#esempi)
7. [Best practice](#best-practice) 8. [Errori comuni](#errori-comuni) 9. [Checklist](#checklist)
10. [Riferimenti](#riferimenti)

---

## Descrizione

Un modulo si distingue da una funzionalità per una sola proprietà, ed è verificabile: si può
togliere. Tutto il resto — le cartelle, il provider, il registro — esiste per rendere possibile
quella proprietà.

Questo prompt guida la costruzione di un modulo conforme e si chiude sulla verifica che conta:
`module:disable` seguito da `composer test`. Se la suite non resta verde, il lavoro non è finito, e
non importa quanto sia completo il modulo: ciò che è stato costruito è una cartella con un nome
altisonante.

---

## Quando si usa

| Copre | Non copre |
|---|---|
| Nuovo ambito funzionale con entità proprie | operazione su entità esistenti → `add-feature.md` |
| Installazione di un modulo di catalogo | nuovo progetto → `loop crea` |
| Estrazione di un ambito da un modulo troppo grande | correzione → `fix-bug.md` |

---

## Prerequisiti

- [ ] L'ambito è stato descritto: entità, casi d'uso, attori, regole di business.
- [ ] È stato verificato che il **catalogo** non copra già l'ambito.
- [ ] Il progetto è allineato: `composer qa` verde.

---

## Sequenza

```
1. Architect        confine del modulo, dipendenze, contratti, eventi, manifesto
2. Database         migration, seeder, factory del modulo
3. Backend          dominio, Action, Query, repository
4. Filament         resource, se amministrativo
   Frontend         componenti, se rivolto all'utente finale
5. Security         permessi, Policy, test di isolamento
6. Testing          suite del modulo
7. Documentation    README, overview, checklist del modulo
```

---

## Il prompt

```markdown
Aggiungi un modulo al progetto «{{ NOME_PROGETTO }}», seguendo `prompts/library/add-module.md`.

## Modulo richiesto

Nome: {{ NOME_MODULO }}
Ambito: {{ DESCRIZIONE }}
Entità: {{ ENTITÀ }}
Casi d'uso: {{ CASI_USO }}
Attori: {{ ATTORI }}
Regole di business: {{ REGOLE }}

## Passo preliminare — verifica il catalogo

Prima di costruire, verifica se [`modules/README.md`](../../modules/README.md) contiene già un
modulo che copre l'ambito.

Se esiste, **installalo** invece di costruirne uno nuovo: adattalo con la configurazione, non con la
riscrittura. Dichiara nel rapporto la valutazione fatta.

## Sequenza

### 1. Architettura del modulo

- Confine: quali entità appartengono al modulo e quali no.
- Dipendenze: **minime** in `requires`, il resto in `optional` con integrazione a eventi.
- Contratti pubblicati, se altri moduli dovranno usarlo.
- Eventi emessi e ascoltati.
- Grado di purezza, con motivazione.
- Manifesto `module.json` completo: nome, versione, provider, dipendenze, database, permessi,
  `provides`, `emits`, `listens`, `settings`.

Vincoli: nessuna dipendenza circolare; un modulo di catalogo non dipende da uno di dominio.

### 2. Database

Migration del modulo, in `modules/{{ NOME_MODULO }}/database/migrations/tenant/`.
Valgono tutte le regole di `rules/sql.md` e `rules/database.md`: nessun `tenant_id`, `decimal` per
quantità e importi, `down()` provato, indici derivati dai filtri dichiarati.

### 3. Backend

Struttura stratificata dentro il modulo: `src/Domain/`, `src/Application/`, `src/Infrastructure/`.
Dall'interno verso l'esterno.

**Nessun accesso diretto ai model di altri moduli**: solo eventi o contratti pubblicati, verificando
la disponibilità con `app()->bound()`.

### 4. Interfaccia

Resource Filament in `src/Filament/`, o componenti in `resources/`, secondo la natura del modulo.

### 5. Sicurezza

Permessi dichiarati **nel manifesto** e seminati dal seeder del modulo.
Policy per ogni model del modulo. Test di isolamento per ogni entità nuova.

### 6. Test

`tests/` dentro il modulo. La suite del modulo deve poter girare **senza** i moduli facoltativi:
è la verifica dell'indipendenza.

### 7. Documentazione

`README.md`, `docs/overview.md`, `docs/checklist.md` del modulo.

## Verifica di indipendenza

Prima di consegnare, esegui:

    php artisan module:disable {{ NOME_MODULO }}
    composer test        # la suite degli altri moduli deve restare verde
    php artisan module:enable {{ NOME_MODULO }}
    composer test        # tutto verde

Se disabilitando il modulo la suite degli altri fallisce, il modulo **non è indipendente**: correggi
le dipendenze prima di consegnare.

## Output

Il modulo completo, più il rapporto con: valutazione del catalogo, dipendenze scelte e perché,
esito della verifica di indipendenza.
```

---

## Definizione di «fatto»

- [ ] Catalogo verificato; costruzione motivata se non si riusa.
- [ ] Manifesto completo e valido.
- [ ] Nessuna dipendenza circolare; dipendenze obbligatorie minime.
- [ ] Nessun accesso diretto ai model di altri moduli.
- [ ] Migration reversibili, indici derivati dai filtri.
- [ ] Permessi dichiarati e seminati.
- [ ] Policy per ogni model; test di isolamento per ogni entità.
- [ ] Suite del modulo verde anche senza i moduli facoltativi.
- [ ] Verifica di indipendenza superata.
- [ ] README, overview e checklist del modulo presenti.

---

## Esempi

### Esempio 1 — riuso invece di costruzione

Richiesta: «serve la gestione dei documenti allegati alle pratiche».

Verifica del catalogo: il modulo `documents` copre l'ambito. Si installa e si configura, invece di
costruire. Tempo: ore invece di giorni.

### Esempio 2 — integrazione facoltativa fatta bene

Il modulo `inventory` vuole notificare i lotti in scadenza, ma deve funzionare anche senza
`notifications`.

`inventory` emette `BatchExpiringSoon` e non verifica chi ascolta.
`notifications`, se installato, registra il listener nel proprio provider.

Senza `notifications`, l'evento viene emesso e nulla accade: nessun errore, nessuna dipendenza.

---

## Best practice

- Verificare il catalogo per primo: costruire ciò che esiste è il costo più evitabile.
- Ridurre al minimo `requires`: ogni voce riduce l'indipendenza.
- Provare la disinstallazione: è la verifica dell'indipendenza, non un formalismo.
- Se il modulo sarebbe utile in tre progetti, proporlo per il catalogo.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Costruire invece di riusare | Duplicazione, manutenzione doppia | Verifica del catalogo |
| Import diretto di model di altri moduli | Modulo non disinstallabile | Eventi o contratti |
| Troppe dipendenze obbligatorie | Nessuna indipendenza reale | `optional` + eventi |
| Permessi non dichiarati nel manifesto | Non vengono seminati | Manifesto completo |
| Verifica di indipendenza saltata | Il modulo sembra indipendente e non lo è | Prova di disinstallazione |

---

## Checklist

- [ ] Catalogo verificato.
- [ ] Manifesto completo.
- [ ] Sequenza completa eseguita.
- [ ] Verifica di indipendenza superata.
- [ ] Documentazione del modulo presente.

---

## Riferimenti

- [Libreria](README.md) · [Blueprint di modulo](../../modules/_blueprint/README.md)
- [Sistema modulare](../../architecture/10-modular-system.md) · [Contratto di modulo](../../architecture/11-module-contract.md)
- [Workflow modulo](../../workflows/20-module-workflow.md)
