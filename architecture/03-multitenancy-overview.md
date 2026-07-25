# Multitenancy: panoramica

> Il modello di isolamento adottato, perché è stato scelto, quanto costa e quali conseguenze
> impone a ogni parte del sistema.

---

## Indice

1. [Descrizione](#descrizione)
2. [Le tre strategie possibili](#le-tre-strategie-possibili)
3. [La scelta: database separati](#la-scelta-database-separati)
4. [Cosa comporta, in concreto](#cosa-comporta-in-concreto)
5. [I cinque punti da presidiare](#i-cinque-punti-da-presidiare)
6. [Limiti del modello](#limiti-del-modello)
7. [Reportistica aggregata](#reportistica-aggregata)
8. [Verifiche obbligatorie](#verifiche-obbligatorie)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Multitenancy significa che una sola installazione serve più clienti, ciascuno con i propri dati,
utenti e configurazioni, senza che nessuno possa vedere quelli degli altri.

La domanda architetturale non è *se* isolare, ma **quanto in profondità**. La risposta determina
il costo operativo, il rischio residuo e la difficoltà di ogni modifica futura: è la decisione più
impattante dell'intera architettura.

---

## Le tre strategie possibili

### A. Colonna discriminante (`tenant_id`)

Tutti i tenant nella stessa tabella, filtrati da una colonna.

| Vantaggi | Svantaggi |
|---|---|
| Una sola migration | **Una query dimenticata espone i dati di tutti** |
| Un solo backup | Backup e ripristino per cliente impossibili |
| Reportistica aggregata immediata | Esportazione dei dati complessa |
| Poche connessioni | Nessuna separazione fisica: rischio permanente |
| Costo iniziale minimo | Tabelle enormi, indici sempre composti |

### B. Schema separato per tenant

Uno schema per tenant nello stesso database.

| Vantaggi | Svantaggi |
|---|---|
| Isolamento logico buono | MySQL non distingue schema e database |
| Un solo server | Vantaggio reale solo con PostgreSQL |
| Backup selettivo possibile | Con MySQL equivale all'opzione C senza i suoi benefici |

### C. Database separato per tenant

Un database per cliente, sullo stesso server o su server diversi.

| Vantaggi | Svantaggi |
|---|---|
| **Isolamento per costruzione** | N migration ad ogni modifica |
| Backup e ripristino per cliente | Più connessioni da gestire |
| Esportazione dei dati banale | Reportistica aggregata da progettare |
| Cancellazione completa e verificabile | Costo operativo maggiore |
| Un tenant grande non rallenta gli altri | Provisioning più complesso |
| Possibilità di spostare un cliente | Monitoraggio da fare per tenant |

---

## La scelta: database separati

La Factory adotta l'opzione **C**. La motivazione completa è in
[ADR-0002](decisions/0002-tenant-isolation-strategy.md); in sintesi:

1. **Il rischio dell'opzione A non è mitigabile.** Con la colonna discriminante, l'isolamento
   dipende dal fatto che *ogni* query, per sempre, applichi il filtro. Basta una dimenticanza —
   in una query grezza, in un report, in un job — per esporre i dati di tutti. Nei nostri domini
   (sanitario, fiscale, documentale) questo rischio non è accettabile.

2. **Backup e ripristino per cliente sono un requisito reale.** Un cliente che cancella per errore
   un anno di dati deve poter essere ripristinato senza toccare gli altri.

3. **L'esportazione e la cancellazione sono obblighi.** Con database separati sono operazioni
   banali e verificabili; con la colonna discriminante richiedono estrazioni selettive di cui
   nessuno può garantire la completezza.

4. **Il costo aggiuntivo è gestibile e automatizzabile.** Migration a lotti, provisioning
   automatico, backup schedulati: tutto risolto una volta nella Foundation.

**Soglia di rivalutazione:** oltre 500 tenant attivi per installazione, i costi operativi
crescono al punto da richiedere una nuova valutazione. Fino a lì, la decisione resta.

---

## Cosa comporta, in concreto

| Area | Conseguenza |
|---|---|
| Connessioni | due configurate: `landlord` e `tenant`; la seconda riconfigurata a runtime |
| Migration | due cartelle separate; le tenant girano N volte |
| Model | i model di dominio usano la connessione `tenant`, mai il landlord |
| Query | **nessuna colonna `tenant_id`**: sarebbe un fraintendimento |
| Cache | ogni chiave prefissata dal tenant |
| Code | ogni job porta con sé il tenant e lo ripristina |
| Storage | un disco per tenant |
| Sessioni | separate per dominio |
| Log | campo `tenant` obbligatorio |
| Backup | uno per tenant |
| Metriche | raccolte per tenant |
| Test | almeno due tenant, sempre |

---

## I cinque punti da presidiare

L'isolamento del database è garantito dalla struttura. Restano cinque punti in cui una
disattenzione può ancora produrre una fuga.

| # | Punto | Rischio | Contromisura |
|---|---|---|---|
| 1 | **Cache** | chiave condivisa tra tenant | prefisso obbligatorio via `TenantCacheKey` |
| 2 | **Code** | job eseguito nel tenant sbagliato | trait di contesto obbligatorio |
| 3 | **Storage** | percorsi sovrapposti | disco per tenant, mai percorsi costruiti a mano |
| 4 | **Comandi CLI** | eseguiti senza contesto | `tenant:artisan`, mai comandi diretti |
| 5 | **Aggregati nel landlord** | dati identificativi che escono dal tenant | solo numeri, mai dati personali |

Questi cinque punti sono l'oggetto principale della revisione di sicurezza di ogni progetto.

---

## Limiti del modello

Un'architettura onesta dichiara ciò che non può fare.

| Limite | Conseguenza | Come si affronta |
|---|---|---|
| Nessuna join tra tenant | reportistica aggregata non immediata | aggregati asincroni nel landlord |
| Migration N volte | deploy più lungo | esecuzione a lotti, monitorata |
| Connessioni numerose | limite del server | pool dimensionato, connessioni riciclate |
| Ricerca globale impossibile | nessuna ricerca «su tutti i clienti» | per progettazione, non è un difetto |
| Provisioning lento | nuovo cliente non istantaneo | esecuzione in coda, minuti |
| Backup numerosi | finestra più lunga | parallelizzazione, backup incrementali |
| Aggiornamento dello schema non atomico | tenant temporaneamente disallineati | codice compatibile con due schemi |

L'ultimo limite è quello che determina il pattern in tre rilasci: senza, ogni modifica di schema
richiederebbe un fermo del servizio.

---

## Reportistica aggregata

Il caso «voglio un dato su tutti i clienti» si presenta sempre. La soluzione corretta:

```
Job schedulato ──▶ per ogni tenant, nel suo contesto:
                        calcola gli aggregati
                        scrive una riga in landlord.tenant_metrics
                            (solo numeri, nessun dato identificativo)
                   ──▶ il pannello di piattaforma legge solo il landlord
```

```php
final class CollectTenantMetricsJob implements ShouldQueue
{
    public function handle(): void
    {
        Tenant::query()->active()->each(function (Tenant $tenant): void {
            tenancy()->run($tenant, function () use ($tenant): void {
                TenantMetric::onLandlord()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'date' => today()],
                    [
                        'active_users' => User::query()->active()->count(),
                        'movements' => StockMovement::query()->whereDate('occurred_at', today())->count(),
                        'storage_bytes' => Storage::disk('tenant')->size(''),
                    ],
                );
            });
        });
    }
}
```

Ciò che **non** è ammesso: iterare sulle connessioni tenant da una richiesta HTTP del pannello di
piattaforma. Sarebbe lento, fragile, e soprattutto stabilirebbe il precedente che le query
cross-tenant sono accettabili.

---

## Verifiche obbligatorie

Ogni progetto ha, senza eccezioni:

| Verifica | Frequenza |
|---|---|
| Test di isolamento per ogni entità | ad ogni esecuzione della suite |
| Test di architettura sulle connessioni | ad ogni esecuzione |
| Test di isolamento della cache | ad ogni esecuzione |
| Test sul contesto tenant nei job | ad ogni esecuzione |
| Verifica manuale con due tenant | prima di ogni rilascio |
| Revisione dei cinque punti | prima di ogni rilascio maggiore |

---

## Esempi

### Esempio 1 — il modello che regge sotto pressione

Richiesta urgente: «serve una schermata che mostri tutti i lotti in scadenza di tutti i clienti,
per il servizio di assistenza».

Risposta corretta: un aggregato nel landlord con il **conteggio** dei lotti in scadenza per
tenant. L'assistenza vede quali clienti hanno una situazione critica e, se serve intervenire,
accede al singolo tenant con la procedura tracciata.

Il dettaglio dei lotti — che contiene dati del cliente — non esce mai dal suo database.

### Esempio 2 — il fraintendimento più comune

```php
// ✗ Il tenant è già il database: questa colonna non serve e confonde
Schema::create('suppliers', function (Blueprint $table): void {
    $table->foreignId('tenant_id');   // ← errore concettuale
    $table->string('name');
});
```

Chi scrive questa migration non ha compreso il modello. La colonna non aggiunge sicurezza, occupa
spazio, richiede indici e induce a scrivere query filtrate che danno una falsa impressione di
isolamento.

---

## Best practice

- Trattare i cinque punti come l'unico rischio residuo, e presidiarli con test.
- Nessuna colonna `tenant_id` nelle tabelle tenant.
- Far viaggiare gli aggregati in modo asincrono, con soli numeri.
- Sviluppare e testare sempre con almeno due tenant.
- Automatizzare provisioning, migration, backup: a mano non regge oltre pochi clienti.
- Monitorare per tenant, non solo in aggregato.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Colonna `tenant_id` nelle tabelle tenant | Fraintendimento del modello | Il tenant è il database |
| Query cross-tenant «solo per diagnostica» | Precedente che erode il principio | Aggregati asincroni |
| Chiave di cache senza prefisso | Un tenant legge i dati di un altro | `TenantCacheKey::for()` |
| Job senza ripristino del contesto | Scrittura nel database sbagliato | Trait della Foundation |
| Sviluppo con un solo tenant | I difetti di isolamento non emergono | Due tenant sempre |
| Migration tenant tutte insieme | Saturazione del database | Esecuzione a lotti |
| Dati identificativi negli aggregati | Fuga di dati verso il landlord | Solo numeri |

---

## Checklist

- [ ] Nessuna tabella tenant ha una colonna `tenant_id`.
- [ ] I model di dominio usano la connessione `tenant`.
- [ ] Tutte le chiavi di cache sono tenant-scoped.
- [ ] Tutti i job ripristinano il contesto.
- [ ] Lo storage usa un disco per tenant.
- [ ] Gli aggregati verso il landlord contengono solo numeri.
- [ ] Esistono test di isolamento per ogni entità.
- [ ] Lo sviluppo e i test usano almeno due tenant.

---

## Riferimenti

- [ADR-0002 — Strategia di isolamento](decisions/0002-tenant-isolation-strategy.md)
- [Database landlord](04-landlord-database.md) · [Database tenant](05-tenant-databases.md)
- [Risoluzione del tenant](06-tenant-resolution.md) · [Ciclo di vita](07-tenant-lifecycle.md)
- [Strategia di caching](22-caching-strategy.md) · [Code](18-queue-scheduler.md)
- [Modulo tenancy](../modules/catalog/tenancy.md)
