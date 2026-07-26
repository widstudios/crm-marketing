# ADR-0002 — Strategia di isolamento dei tenant

> Ogni tenant ha un database dedicato: l'isolamento è una proprietà della struttura, non il
> risultato dell'attenzione di chi scrive le query.

| | |
|---|---|
| **Stato** | Accettata |
| **Data** | 2026-07-25 |
| **Decisore** | Factory Owner |
| **Impatto** | architettura, foundation, operations, tutti i progetti |
| **Reversibilità** | **irreversibile** (comporta migrazione di dati) |

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

Tutti i software prodotti da WidStudios servono più clienti sulla stessa installazione. I domini
applicativi previsti comprendono:

| Dominio | Natura dei dati | Vincolo |
|---|---|---|
| Magazzino sanitario | dispositivi medici, lotti, tracciabilità | conservazione decennale, ispezioni |
| CAF | dati fiscali e personali | normativa fiscale, GDPR |
| Gestione documentale | documenti riservati dei clienti | riservatezza contrattuale |
| Help desk | segnalazioni, talvolta con dati personali | GDPR |
| CRM | anagrafiche e trattative | riservatezza commerciale |

Tre requisiti emergono da questo contesto:

1. **Una fuga di dati tra clienti non è recuperabile.** In ambito sanitario e fiscale ha
   conseguenze legali e contrattuali che possono compromettere l'azienda.
2. **Il ripristino per singolo cliente è un'esigenza reale.** Un cliente che cancella per errore un
   anno di dati deve poter essere ripristinato senza toccare gli altri.
3. **Esportazione e cancellazione sono obblighi.** Il diritto alla portabilità e alla cancellazione
   richiede procedure verificabili, non estrazioni selettive di cui nessuno può garantire la
   completezza.

La domanda architetturale è quindi: **quanto in profondità isolare?**

---

## Decisione

Si adotta l'isolamento a **database separati**: un database dedicato per ogni tenant, più un
database `landlord` per i dati di piattaforma.

Conseguenze prescrittive:

- Ogni entità di dominio vive nel database del tenant.
- **Nessuna tabella tenant ha una colonna `tenant_id`.**
- Il landlord contiene solo ciò che *descrive* il cliente, mai ciò che *è* del cliente.
- Le migration sono divise in due cartelle: `landlord/` e `tenant/`.
- Ogni tenant ha un utente MySQL dedicato, con privilegi limitati al proprio database.
- Nessuna query attraversa i tenant, in nessuna circostanza, nemmeno per diagnostica.
- I dati aggregati verso il landlord contengono solo valori numerici, mai identificativi.

---

## Alternative valutate

### Alternativa A — colonna discriminante (`tenant_id`)

Tutti i tenant nelle stesse tabelle, filtrati da una colonna e da un global scope.

**Scartata** per una ragione dirimente: l'isolamento dipende dal fatto che *ogni* query, per
sempre, applichi il filtro. Una query grezza, un report, un job, un'importazione scritta in fretta
— basta una dimenticanza per esporre i dati di tutti i clienti.

Il rischio non è mitigabile con la disciplina o con la revisione: cresce con il numero di query, di
sviluppatori e di anni. In un dominio in cui una fuga è irrecuperabile, non è accettabile.

Secondariamente: backup e ripristino per singolo cliente diventano impraticabili, e la cancellazione
completa non è verificabile.

### Alternativa B — schema separato nello stesso database

Uno schema per tenant.

**Scartata** perché in MySQL, che è il database imposto dal contesto dei clienti
([ADR-0001](0001-stack-tecnologico.md)), «schema» e «database» sono sinonimi: l'alternativa
coincide con la scelta adottata, senza aggiungere nulla. Avrebbe senso con PostgreSQL, che però è
stato scartato per ragioni di infrastruttura e competenze.

### Confronto

| Asse | Database separati (adottata) | Colonna `tenant_id` | Schema separato |
|---|---|---|---|
| Isolamento | **per costruzione** | dipende da ogni query | per costruzione |
| Rischio di fuga | strutturalmente basso | **permanente e crescente** | basso |
| Costo iniziale | medio | basso | medio |
| Costo di uscita | alto | alto | alto |
| Backup per cliente | **banale** | impraticabile | possibile |
| Esportazione | **banale** | complessa e non verificabile | possibile |
| Cancellazione verificabile | **sì** | no | sì |
| Migration | N esecuzioni | 1 | N |
| Connessioni | molte | poche | molte |
| Reportistica aggregata | da progettare | immediata | da progettare |
| Tenant grande che rallenta gli altri | no | **sì** | no |
| Con MySQL | applicabile | applicabile | **non distinguibile** |

---

## Conseguenze

### Positive

- L'isolamento non dipende dalla correttezza di ogni singola query.
- Backup, ripristino, esportazione e cancellazione per singolo cliente sono operazioni banali.
- Un tenant con volumi elevati non degrada le prestazioni degli altri.
- Un cliente può essere spostato su infrastruttura dedicata senza modifiche al codice.
- L'utente MySQL dedicato fornisce una seconda linea di difesa in caso di difetto applicativo.
- Le tabelle restano di dimensione ragionevole; gli indici non devono essere sempre compositi.

### Negative (accettate consapevolmente)

- **Le migration girano N volte.** Un deploy con modifiche di schema dura minuti, non secondi, e
  richiede esecuzione a lotti con monitoraggio.
- **Nessuna join tra tenant.** La reportistica aggregata richiede aggregati asincroni nel landlord:
  lavoro aggiuntivo da progettare per ogni progetto.
- **Molte connessioni.** `max_connections` va dimensionato e monitorato.
- **Provisioning non istantaneo.** Creare un tenant richiede minuti, quindi va eseguito in coda.
- **Backup numerosi.** La finestra di backup si allunga e richiede parallelizzazione.
- **Disallineamento temporaneo dello schema.** Il codice deve tollerare due versioni di schema
  durante il deploy: da qui l'obbligo del pattern in tre rilasci.
- **Monitoraggio per tenant.** Le metriche aggregate nascondono il caso peggiore.

### Impatto operativo

| Area | Effetto |
|---|---|
| Sviluppo | due cartelle di migration; nessun `tenant_id`; sempre due tenant in locale |
| Test | test di isolamento obbligatori per ogni entità |
| Deploy | migration a lotti; codice compatibile con due schemi |
| Esercizio | backup per tenant; monitoraggio per tenant; verifica dell'allineamento |
| Documentazione | criterio di collocazione landlord/tenant in ogni progetto |

---

## Soglia di rivalutazione

Questa decisione va riconsiderata se si verifica **una** di queste condizioni:

1. **Oltre 500 tenant attivi** su una singola installazione: il costo operativo di migration,
   backup e connessioni supera il beneficio.
2. **Durata del deploy oltre 60 minuti** per le sole migration tenant, nonostante
   la parallelizzazione.
3. **Cambio di database** verso una tecnologia con isolamento a schema efficiente e strumenti di
   gestione massiva maturi.
4. **Requisito di reportistica cross-tenant in tempo reale** che gli aggregati asincroni non
   riescono a soddisfare.

Al verificarsi di una condizione: nuova ADR, con analisi dell'impatto e piano di migrazione. Non
si deroga per progetto.

---

## Verifica

| Verifica | Strumento | Automatica |
|---|---|---|
| Nessuna colonna `tenant_id` nelle tabelle tenant | analisi delle migration | sì |
| I model di dominio non usano la connessione landlord | test di architettura | sì |
| Un tenant non vede i dati di un altro | test di isolamento per entità | sì |
| Le chiavi di cache sono tenant-scoped | test di isolamento della cache | sì |
| I job ripristinano il contesto | test sui job | sì |
| Gli aggregati nel landlord contengono solo numeri | revisione | no |
| Ogni tenant ha un utente MySQL dedicato | verifica infrastrutturale | sì |

---

## Riferimenti

- [Multitenancy: panoramica](../03-multitenancy-overview.md)
- [Database landlord](../04-landlord-database.md) · [Database tenant](../05-tenant-databases.md)
- [Risoluzione del tenant](../06-tenant-resolution.md) · [Ciclo di vita](../07-tenant-lifecycle.md)
- [ADR-0001 — Stack tecnologico](0001-stack-tecnologico.md)
- [Backup e ripristino](../../docs/05-operations/04-backup-and-restore.md)
