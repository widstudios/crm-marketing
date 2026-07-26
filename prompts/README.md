# Prompt

> I prompt operativi della Factory: di sistema, di fase, componibili. Versionati come il codice,
> perché lo sono.

---

## Indice

1. [Descrizione](#descrizione)
2. [Struttura](#struttura)
3. [Indice dei prompt](#indice-dei-prompt)
4. [Anatomia di un prompt](#anatomia-di-un-prompt)
5. [Versionamento](#versionamento)
6. [Come si corregge un prompt](#come-si-corregge-un-prompt)
7. [Esempi](#esempi)
8. [Best practice](#best-practice)
9. [Errori comuni](#errori-comuni)
10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

I prompt sono **codice sorgente**: determinano il comportamento del sistema di produzione, hanno
versioni, si correggono quando producono difetti, e le loro modifiche vanno revisionate.

Sono separati dai file degli agenti perché uno stesso frammento può servire a più agenti e va
versionato una volta sola.

---

## Struttura

```
prompts/
├── loop-crea.md          contratto del comando principale
├── system/               prompt di sistema, comuni a tutti gli agenti
├── phases/               prompt di fase, uno per fase del master workflow
├── snippets/             frammenti componibili
└── library/              prompt per attività ricorrenti fuori dal workflow
```

---

## Indice dei prompt

### Contratto

| Documento | Contenuto |
|---|---|
| [loop-crea.md](loop-crea.md) | sintassi, precondizioni, fasi, postcondizioni, fermate |

### Sistema

| Documento | Contenuto |
|---|---|
| [system/00-base-system-prompt.md](system/00-base-system-prompt.md) | identità, contesto e vincoli comuni |
| [system/01-guardrails.md](system/01-guardrails.md) | divieti e comportamenti di sicurezza |
| [system/02-output-format.md](system/02-output-format.md) | formato del rapporto di fase e degli artefatti |

### Snippet

| Documento | Uso |
|---|---|
| [snippets/README.md](snippets/README.md) | indice e regole di composizione |
| [snippets/tenant-context.md](snippets/tenant-context.md) | vincoli di isolamento, da includere in ogni prompt che tocca dati |
| [snippets/quality-gate.md](snippets/quality-gate.md) | istruzioni per la verifica del gate |
| [snippets/report-format.md](snippets/report-format.md) | formato del rapporto di fase |
| [snippets/uncertainty.md](snippets/uncertainty.md) | come distinguere assunzione e domanda |

### Libreria

| Documento | Uso |
|---|---|
| [library/README.md](library/README.md) | indice |
| [library/add-feature.md](library/add-feature.md) | aggiungere una feature a un progetto esistente |
| [library/fix-bug.md](library/fix-bug.md) | correggere un difetto con test di regressione |
| [library/add-module.md](library/add-module.md) | aggiungere un modulo |
| [library/upgrade-foundation.md](library/upgrade-foundation.md) | aggiornare la Foundation |

---

## Anatomia di un prompt

Ogni prompt operativo contiene, nell'ordine:

| Blocco | Contenuto | Obbligatorio |
|---|---|---|
| **Identità** | quale agente, secondo quale file | sì |
| **Contesto** | artefatti disponibili, con percorso | sì |
| **Compito** | cosa produrre, in quale forma | sì |
| **Metodo** | come procedere, quando l'ordine conta | se rilevante |
| **Regole vincolanti** | numerate, citabili in revisione | sì |
| **Vincoli di ambito** | cosa **non** fare | sì |
| **Verifica** | comandi da eseguire prima di consegnare | se applicabile |
| **Output** | artefatti attesi | sì |
| **Gate di uscita** | checklist da verificare | sì |

Un prompt senza «vincoli di ambito» produce sovrapposizioni tra fasi: è l'omissione più frequente.

---

## Versionamento

Ogni prompt dichiara la propria versione nel front matter del file dell'agente:

```markdown
| **Versione prompt** | 1.2.0 |
```

| Incremento | Quando |
|---|---|
| MAJOR | cambia il contratto di output (artefatti diversi, formato diverso) |
| MINOR | si aggiungono vincoli o si estende il compito |
| PATCH | si chiarisce una formulazione senza cambiare il comportamento atteso |

Le modifiche ai prompt compaiono nel `CHANGELOG.md` della Factory: un progetto generato con la
versione 1.0 di un prompt non è identico a uno generato con la 2.0, e va saputo.

---

## Come si corregge un prompt

| Sintomo | Correzione |
|---|---|
| Errore isolato su un progetto | si corregge l'**output**, non il prompt |
| Stesso errore su progetti diversi | si corregge il **prompt** |
| L'agente ignora una regola | la regola non era nel blocco «regole vincolanti» |
| L'agente esce dal proprio ambito | i «vincoli di ambito» sono deboli |
| L'agente inventa requisiti | manca il richiamo alla gestione dell'incertezza |
| Output diverso a parità di input | il contesto fornito non è deterministico |

La procedura:

1. Riprodurre il difetto con lo stesso input.
2. Individuare il blocco del prompt carente.
3. Correggere, aggiungendo se serve un esempio di violazione.
4. Verificare su un caso reale.
5. Incrementare la versione, aggiornare il changelog.
6. Aggiungere la voce alla checklist di fase, se la verifica manuale è utile.

---

## Esempi

### Esempio 1 — correzione efficace

Il Backend Agent produce Action che ricevono `Request` invece di DTO, su tre progetti.

Correzione nel prompt:

```markdown
1. **Ogni mutazione è un'Action** `final`, con un solo metodo pubblico `execute()`, che riceve un
   **DTO** (mai una `Request`, mai un array).
```

Più un esempio di violazione nella sezione «errori comuni» del file dell'agente. Il difetto non si
ripresenta.

### Esempio 2 — correzione inefficace

«Presta più attenzione alla qualità del codice.»

Non è verificabile, non indica cosa cambiare, non impedisce nulla.

---

## Best practice

- Trattare i prompt come codice: revisione, versione, changelog.
- Correggere il prompt alla seconda occorrenza dello stesso difetto, non alla decima.
- Aggiungere esempi di **violazione**, non solo di conformità: si riconosce meglio uno schema
  avendo visto entrambe le forme.
- Mantenere i vincoli numerati: rende possibile citarli in revisione.
- Riusare gli snippet invece di ripetere gli stessi vincoli in più prompt.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Prompt senza vincoli di ambito | Sovrapposizione tra fasi | Blocco obbligatorio |
| Vincoli non numerati | Non citabili in revisione | Numerazione |
| Correzione generica | Non cambia il comportamento | Vincolo specifico |
| Prompt non versionati | Impossibile sapere come è stato generato un progetto | Versione dichiarata |
| Stessi vincoli ripetuti in più prompt | Divergono nel tempo | Snippet condivisi |
| Correggere sempre l'output | Costo ripetuto ad ogni progetto | Correggere il prompt |

---

## Checklist

- [ ] Il prompt contiene tutti i blocchi obbligatori.
- [ ] I vincoli sono numerati e citabili.
- [ ] I vincoli di ambito dichiarano cosa non fare.
- [ ] Il gate di uscita è indicato.
- [ ] La versione è dichiarata e coerente con la modifica.
- [ ] Gli snippet condivisi sono riusati, non duplicati.
- [ ] Le modifiche sono nel changelog.

---

## Riferimenti

- [Contratto `loop crea`](loop-crea.md)
- [Indice degli agenti](../agents/README.md) · [Protocollo agenti](../agents/00-agent-protocol.md)
- [Master workflow](../workflows/00-master-workflow.md)
- [Guida al contributo](../CONTRIBUTING.md)
