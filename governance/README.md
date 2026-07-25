# Governance della AI Factory

> Chi decide cosa, come evolve la Factory nel tempo, e come i progetti già generati restano
> allineati senza rotture improvvise.

---

## Indice

1. [Descrizione](#descrizione)
2. [Documenti di governance](#documenti-di-governance)
3. [Modello di responsabilità](#modello-di-responsabilità)
4. [Ciclo di vita di una modifica](#ciclo-di-vita-di-una-modifica)
5. [Rapporto tra Factory e progetti](#rapporto-tra-factory-e-progetti)
6. [Esempi](#esempi)
7. [Best practice](#best-practice)
8. [Errori comuni](#errori-comuni)
9. [Checklist](#checklist)
10. [Riferimenti](#riferimenti)

---

## Descrizione

Una piattaforma interna muore in due modi: **irrigidendosi** (nessuno può cambiarla, i progetti la
aggirano) oppure **sfaldandosi** (chiunque cambia tutto, i progetti divergono). La governance serve
a evitare entrambi: rende il cambiamento possibile, tracciato e propagabile.

---

## Documenti di governance

| Documento | Contenuto |
|---|---|
| [`versioning.md`](versioning.md) | Come si versiona la Factory e cosa significa una modifica breaking |
| [`roadmap.md`](roadmap.md) | Stato di avanzamento delle aree e prossimi passi |
| [`ownership.md`](ownership.md) | Chi è responsabile di quale area, e con quale autorità |
| [`decision-process.md`](decision-process.md) | Come si prende una decisione strutturale (ADR) |
| [`quality-metrics.md`](quality-metrics.md) | Le metriche con cui si misura la salute della Factory |
| [`migrations/`](migrations/README.md) | Guide di migrazione per le modifiche breaking |

---

## Modello di responsabilità

Tre livelli, con autorità decrescente sull'*eccezione*:

1. **Factory Owner** — custodisce coerenza e stack. Approva ADR e modifiche breaking.
2. **Area Owner** — responsabile di un'area (regole, foundation, agenti, deployment).
   Approva contributi nella propria area.
3. **Contributore** — chiunque, umano o agente, proponga una modifica.

Un agente AI opera sempre come **contributore**: può proporre e implementare, non può approvare
la propria modifica né derogare a una regola vincolante.

---

## Ciclo di vita di una modifica

```
proposta ──> valutazione ammissibilità ──> [serve ADR?] ──sì──> ADR proposta ──> discussione
                                                │                                    │
                                                no                              accettata/rifiutata
                                                │                                    │
                                                └──────────> implementazione <────────┘
                                                                   │
                                                            revisione (1-2)
                                                                   │
                                                    aggiornamento indici + CHANGELOG
                                                                   │
                                                              rilascio versione
                                                                   │
                                                 comunicazione ai progetti + guida migrazione
```

---

## Rapporto tra Factory e progetti

- I progetti **consumano** la Factory: la Foundation come dipendenza Composer versionata, gli altri
  asset come riferimento documentale.
- Un progetto **non** modifica la Factory al volo: apre un contributo qui.
- Un progetto può **restringere** una regola (es. copertura test più alta), mai allentarla.
- Le deroghe locali si scrivono nel `CLAUDE.md` del progetto, con motivazione e scadenza.

### Flusso di promozione del codice

Il codice nasce nel progetto e sale nella Factory quando dimostra di essere generico:

```
progetto A: risolve un problema
      │
      ├── progetto B: stesso problema, soluzione simile   ──> segnale
      │
      └── progetto C: stesso problema                     ──> promozione alla Foundation
```

Regola pratica: **alla terza occorrenza si promuove**. Prima è coincidenza, poi è un pattern.

---

## Esempi

### Esempio 1 — modifica non breaking

Aggiunta di `rules/i18n.md`. Nessun progetto esistente si rompe: la regola vale per il nuovo
codice. Versione della Factory: incremento *minor*. Nessuna guida di migrazione.

### Esempio 2 — modifica breaking

Cambio della firma di un contratto della Foundation. Serve ADR, incremento *major*, guida in
`governance/migrations/`, finestra di compatibilità di almeno una minor con deprecazione.

---

## Best practice

- Ogni decisione strutturale lascia una traccia scritta e datata.
- Le modifiche breaking arrivano sempre con la loro guida di migrazione, mai dopo.
- Le deroghe hanno una **scadenza**: senza scadenza diventano lo standard di fatto.
- Le metriche si guardano periodicamente, non solo quando qualcosa va male.
- Chi introduce una regola scrive anche **come si verifica**.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Modifica breaking senza guida | I progetti restano indietro per sempre | Guida obbligatoria prima del merge |
| Deroga permanente non tracciata | Lo standard perde autorità | Registrare deroga + scadenza |
| Regole senza owner | Nessuno le aggiorna | Assegnare l'area in `ownership.md` |
| Promozione prematura alla Foundation | Astrazione sbagliata, difficile da correggere | Attendere la terza occorrenza |
| Versionamento «a sentimento» | Impossibile capire l'impatto di un aggiornamento | Applicare `versioning.md` |

---

## Checklist

- [ ] La modifica ha un owner identificato.
- [ ] Se è strutturale, esiste la ADR collegata.
- [ ] Se è breaking, esiste la guida di migrazione.
- [ ] `CHANGELOG.md` aggiornato.
- [ ] Versione incrementata secondo `versioning.md`.
- [ ] Gli indici delle cartelle toccate sono aggiornati.

---

## Riferimenti

- [CONTRIBUTING.md](../CONTRIBUTING.md)
- [ADR](../architecture/decisions/README.md)
- [Roadmap](roadmap.md) · [Versionamento](versioning.md) · [Ownership](ownership.md)
