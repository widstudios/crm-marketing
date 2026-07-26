# ADR-NNNN — Titolo breve e descrittivo

> Riga di sintesi: la decisione in una frase.

| | |
|---|---|
| **Stato** | Proposta / In discussione / Accettata / Rifiutata / Ritirata / Deprecata / Superata da ADR-NNNN |
| **Data** | AAAA-MM-GG |
| **Decisore** | ruolo (mai nome di persona) |
| **Proponente** | ruolo o «agente AI» |
| **Impatto** | quali aree e quali progetti |
| **Reversibilità** | reversibile / irreversibile |
| **Supera** | ADR-NNNN (se applicabile) |
| **Migrazione** | link alla guida (se breaking) |

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

Il problema **concreto** che ha portato a questa decisione. Non teorico: cosa è accaduto, cosa non
funzionava, quale vincolo è emerso.

Includere, quando disponibili: numeri misurati, casi reali, vincoli esterni (normativi,
contrattuali, infrastrutturali).

Chi legge tra due anni deve poter capire **se il contesto è ancora quello**.

---

## Decisione

Cosa si è deciso, in modo prescrittivo e senza ambiguità.

> Si adotta X. Ogni progetto Y deve Z.

Se la decisione ha eccezioni ammesse, elencarle qui: un'eccezione non scritta diventa una
violazione tacita.

---

## Alternative valutate

Almeno due, oltre allo status quo.

### Alternativa A — nome

Descrizione. Perché è stata scartata.

### Alternativa B — nome

Descrizione. Perché è stata scartata.

### Confronto

| Asse | Scelta adottata | Alternativa A | Alternativa B |
|---|---|---|---|
| Costo iniziale | | | |
| Costo di uscita | | | |
| Impatto sui progetti esistenti | | | |
| Competenze richieste | | | |
| Maturità della tecnologia | | | |
| Coerenza con lo stack | | | |
| Verificabilità | | | |

Gli assi sono quelli di [`governance/decision-process.md`](../../governance/decision-process.md).

---

## Conseguenze

### Positive

- …

### Negative (accettate consapevolmente)

- …

Questa sottosezione **non può restare vuota**. Ogni decisione tecnica ha un costo: se non lo si
riesce a individuare, l'analisi non è completa.

### Impatto operativo

Cosa cambia per chi sviluppa, per chi rilascia, per chi mantiene.

| Area | Effetto |
|---|---|
| Sviluppo | |
| Test | |
| Deploy | |
| Esercizio | |
| Documentazione | |

---

## Soglia di rivalutazione

Le condizioni **misurabili** al verificarsi delle quali la decisione va riconsiderata.

> Questa decisione va rivalutata se: …

Senza soglia, una decisione diventa un dogma: nessuno sa più se è ancora appropriata e nessuno si
sente autorizzato a metterla in discussione.

---

## Verifica

Come si controlla che la decisione sia rispettata nel codice e nei progetti.

| Verifica | Strumento | Automatica |
|---|---|---|
| | | sì / no |

Una decisione senza verifica si erode: non per cattiva volontà, ma perché sotto scadenza la
scorciatoia è sempre attraente.

---

## Riferimenti

- Documenti della Factory impattati
- ADR collegate
- Fonti esterne consultate
- Guida di migrazione, se breaking
