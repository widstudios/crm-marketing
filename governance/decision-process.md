# Processo decisionale

> Come si prende, si registra e si supera una decisione tecnica nella Factory.

---

## Indice

1. [Descrizione](#descrizione)
2. [Quando una decisione va registrata](#quando-una-decisione-va-registrata)
3. [Il ciclo di una ADR](#il-ciclo-di-una-adr)
4. [Come si valuta un'alternativa](#come-si-valuta-unalternativa)
5. [Decisioni reversibili e irreversibili](#decisioni-reversibili-e-irreversibili)
6. [Superare una decisione](#superare-una-decisione)
7. [Esempi](#esempi)
8. [Best practice](#best-practice)
9. [Errori comuni](#errori-comuni)
10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

Il costo di una decisione tecnica non è nel prenderla: è nel **doverla ricostruire** due anni dopo,
quando chi l'ha presa non c'è più e il contesto è cambiato. Una decisione registrata male produce
uno di questi due esiti, entrambi costosi:

- la si rispetta senza capirla, anche quando non ha più senso;
- la si viola senza saperlo, e il sistema diverge.

Il processo qui descritto ha un solo obiettivo: rendere ogni decisione **ricostruibile**.

---

## Quando una decisione va registrata

Test rapido — serve una ADR se **almeno una** risposta è sì:

1. Esiste un'alternativa ragionevole che una persona competente potrebbe preferire?
2. La decisione vincola altre decisioni future?
3. Cambiarla in seguito costerebbe più di qualche giorno di lavoro?
4. Impatta più di un'area della Factory o più di un progetto?
5. Qualcuno, tra un anno, potrebbe chiedere «perché lo facciamo così?»

Non serve ADR per: scelte estetiche locali, correzioni, applicazioni di regole già decise.

---

## Il ciclo di una ADR

```
   Proposta ──────> In discussione ──────> Accettata ──────> Superata
      │                    │                   │                 ▲
      │                    │                   │                 │
      └──> Ritirata        └──> Rifiutata      └────> Deprecata ─┘
```

| Stato | Significato | Chi lo imposta |
|---|---|---|
| `Proposta` | Scritta, non ancora discussa | chiunque, agenti inclusi |
| `In discussione` | Aperta al confronto, con scadenza | Area Owner |
| `Accettata` | Vincolante da questo momento | Factory Owner |
| `Rifiutata` | Valutata e scartata, con motivazione | Factory Owner |
| `Ritirata` | L'autore la ritira prima della discussione | l'autore |
| `Deprecata` | Non più applicabile, senza sostituto | Factory Owner |
| `Superata` | Sostituita da una ADR successiva, che va linkata | Factory Owner |

**Le ADR non si cancellano e non si riscrivono.** Una decisione sbagliata resta a documentare
perché era sembrata giusta: è quella l'informazione di valore.

### Tempi

| Tipo di decisione | Finestra di discussione |
|---|---|
| Locale d'area | 2 giorni lavorativi |
| Strutturale | 5 giorni lavorativi |
| Cambio di stack | 10 giorni lavorativi |

Scaduta la finestra senza obiezioni motivate, la ADR si intende approvabile.

---

## Come si valuta un'alternativa

Ogni ADR confronta almeno **due** opzioni oltre allo status quo, su questi assi:

| Asse | Domanda |
|---|---|
| Costo iniziale | Quanto lavoro serve per adottarla? |
| Costo di uscita | Quanto costa tornare indietro tra un anno? |
| Impatto sui progetti esistenti | Quanti progetti devono cambiare? |
| Competenze richieste | Il team la sa mantenere senza formazione straordinaria? |
| Maturità | La tecnologia è stabile? Chi la mantiene? Da quanto? |
| Coerenza | Si sposa con lo stack esistente o introduce un secondo modo di fare la stessa cosa? |
| Verificabilità | Si può controllare in pipeline che venga rispettata? |

Il confronto va **scritto**, non solo pensato: la tabella comparativa è parte della ADR.

---

## Decisioni reversibili e irreversibili

Non tutte le decisioni meritano lo stesso rigore.

| Tipo | Caratteristica | Approccio |
|---|---|---|
| **Reversibile** | tornare indietro costa poco (una libreria interna, una convenzione di naming) | decidere in fretta, sperimentare, correggere |
| **Irreversibile** | tornare indietro costa moltissimo (strategia di multitenancy, formato dei dati, scelta del DB) | analisi approfondita, prototipo, ADR con alternative reali |

L'errore più frequente è trattare le reversibili come irreversibili (paralisi) e le irreversibili
come reversibili (debito strutturale permanente).

Domanda di controllo: *«Se tra sei mesi scopriamo che è sbagliata, quanto costa cambiarla?»*
Se la risposta è «un giorno», si decide subito. Se è «riscrivere le migration di tutti i tenant»,
si prototipa prima.

---

## Superare una decisione

Una ADR accettata si supera così:

1. Si scrive una **nuova** ADR con numero successivo.
2. La nuova ADR dichiara `Supera: ADR-000X` e ne spiega il motivo: cosa è cambiato nel contesto.
3. La vecchia ADR viene marcata `Superata da ADR-000Y` — il testo originale non si tocca.
4. Se la nuova decisione è breaking, si aggiunge la guida in [`migrations/`](migrations/README.md).
5. Si aggiornano i documenti operativi (`rules/`, `architecture/`) che citavano la vecchia.

---

## Esempi

### Esempio 1 — decisione irreversibile ben gestita

*Problema:* scegliere la strategia di isolamento dei tenant.

Alternative valutate: (a) database separato per tenant, (b) schema condiviso con colonna
`tenant_id`, (c) schema separato nello stesso database.

Valutazione: (b) ha costo iniziale minimo ma costo di uscita altissimo e rischio di data leak
permanente; (a) ha costo operativo maggiore ma isolamento per costruzione e backup per cliente;
(c) è un compromesso che MySQL non supporta con la stessa pulizia di PostgreSQL.

Decisione: (a), documentata in `ADR-0002`, con le conseguenze operative esplicitate (numero di
connessioni, migration N volte, costo dei backup).

### Esempio 2 — decisione reversibile trattata bene

*Problema:* quale libreria usare per generare i PDF.

Costo di uscita: basso, se l'uso passa da un'interfaccia `PdfRenderer` della Foundation.

Decisione: si sceglie l'opzione più semplice, si isola dietro il contratto, si annota che la
sostituzione è a basso costo. ADR breve, senza prototipo.

### Esempio 3 — decisione mal gestita (da non imitare)

Un progetto introduce una libreria di code diversa da quella standard perché «era più comoda».
Nessuna ADR. Sei mesi dopo, il progetto non riceve gli aggiornamenti della Foundation, i runbook
operativi non si applicano e nessuno ricorda il motivo della scelta.

---

## Best practice

- Scrivere la ADR **prima** dell'implementazione, non per giustificarla dopo.
- Documentare anche le alternative **scartate**: sono la parte più utile tra due anni.
- Includere le conseguenze negative accettate. Una decisione senza svantaggi è una decisione non
  analizzata.
- Numerazione progressiva senza buchi, mai riusare un numero.
- Linkare la ADR dai documenti operativi che ne discendono.
- Tenere la ADR corta ma completa: una pagina densa vale più di dieci pagine vaghe.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| ADR scritta dopo l'implementazione | Diventa una giustificazione, non una decisione | Scriverla prima, in stato `Proposta` |
| Nessuna alternativa considerata | Impossibile rivalutare in futuro | Minimo due alternative reali |
| Riscrivere una ADR accettata | Si perde la storia | Nuova ADR che supera la vecchia |
| Decisione presa in chat e mai registrata | Il team la scopre violandola | ADR obbligatoria per le strutturali |
| Nessuna conseguenza negativa dichiarata | Analisi superficiale | Sezione «Conseguenze» con i costi accettati |
| Trattare tutto come irreversibile | Paralisi decisionale | Classificare prima di analizzare |

---

## Checklist

- [ ] Ho verificato con il test rapido che serva davvero una ADR.
- [ ] Ho classificato la decisione come reversibile o irreversibile.
- [ ] Ho confrontato almeno due alternative sugli assi previsti.
- [ ] Ho dichiarato le conseguenze, incluse quelle negative.
- [ ] La ADR ha numero progressivo, data e stato.
- [ ] Se supera una ADR precedente, entrambe sono collegate.
- [ ] I documenti operativi impattati sono stati aggiornati.

---

## Riferimenti

- [Indice delle ADR](../architecture/decisions/README.md)
- [Template ADR](../architecture/decisions/template.md)
- [Governance](README.md) · [Ownership](ownership.md) · [Versionamento](versioning.md)
