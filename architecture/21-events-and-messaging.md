# Eventi e messaggistica

> Come le parti del sistema comunicano senza conoscersi: eventi di dominio, listener, e i confini
> di ciò che l'asincronia può risolvere.

---

## Indice

1. [Descrizione](#descrizione)
2. [Eventi di dominio](#eventi-di-dominio)
3. [Listener](#listener)
4. [Sincrono o asincrono](#sincrono-o-asincrono)
5. [Eventi tra moduli](#eventi-tra-moduli)
6. [Ordine e affidabilità](#ordine-e-affidabilità)
7. [Cosa gli eventi non risolvono](#cosa-gli-eventi-non-risolvono)
8. [Mappa degli eventi](#mappa-degli-eventi)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Gli eventi servono a **disaccoppiare**: chi compie un'operazione non deve sapere quali conseguenze
questa avrà. L'Action che registra un movimento non sa che qualcuno ricalcolerà la giacenza,
notificherà un responsabile e aggiornerà un aggregato.

Il beneficio: aggiungere una conseguenza non richiede di modificare l'operazione. Il costo: il
flusso non è più leggibile in un unico punto, e serve una mappa.

---

## Eventi di dominio

```php
final readonly class MovementRegistered
{
    public function __construct(
        public int $movementId,
        public int $batchId,
        public MovementType $type,
        public CarbonImmutable $occurredAt,
    ) {}
}
```

| Regola | Motivo |
|---|---|
| Nome al **passato** | descrive un fatto accaduto, non un comando |
| `readonly` | un fatto non cambia |
| Trasporta **identificatori**, non model | i listener asincroni rileggono lo stato corrente |
| Nessuna logica | è un messaggio |
| Emesso **dopo il commit** | i listener trovano i dati |
| Documentato nel manifesto del modulo | è un contratto pubblico |

Nomi corretti: `MovementRegistered`, `BatchExpired`, `SupplierArchived`, `TenantProvisioned`.
Nomi errati: `RegisterMovement` (comando), `MovementEvent` (non dice nulla),
`UpdateStock` (imperativo).

---

## Listener

```php
final class RecalculateStockOnMovement implements ShouldQueue
{
    use TenantAware;

    public int $tries = 3;

    public function handle(MovementRegistered $event): void
    {
        // Rilettura dello stato corrente, non del payload
        $batch = Batch::find($event->batchId);

        if ($batch === null) {
            return;   // il lotto è stato rimosso: nulla da fare
        }

        app(RecalculateStockAction::class)->execute($batch);
    }
}
```

| Regola | Motivo |
|---|---|
| Un listener, una conseguenza | responsabilità isolata, fallimenti isolati |
| Rilegge lo stato dal database | il payload può essere obsoleto |
| Tollera l'assenza dell'entità | tra emissione ed esecuzione può cambiare tutto |
| Idempotente | può essere eseguito più volte |
| `TenantAware` se asincrono | altrimenti gira nel contesto sbagliato |
| Non emette altri eventi a catena | catene lunghe sono impossibili da diagnosticare |

L'ultima regola ammette un'eccezione: un listener può emettere un evento se rappresenta un
passaggio di stato reale del processo. Oltre due livelli di catena, il flusso va ripensato.

---

## Sincrono o asincrono

| Conseguenza | Modalità | Motivo |
|---|---|---|
| Invalidazione della cache | sincrona | deve avvenire prima della prossima lettura |
| Scrittura dell'audit | sincrona (o in coda se il volume è alto) | completezza |
| Aggiornamento di un contatore in transazione | sincrona | coerenza |
| Notifica a un utente | **asincrona** | dipendenza esterna |
| Ricalcolo di aggregati | **asincrona** | può interessare molte righe |
| Chiamata a un servizio esterno | **asincrona** | latenza e guasti |
| Generazione di documenti | **asincrona** | uso intensivo di CPU |
| Webhook in uscita | **asincrona** | il destinatario può non rispondere |

Regola: **sincrono solo se il fallimento deve far fallire l'operazione**. Se una notifica non
inviata non deve annullare un movimento, quella notifica è asincrona.

---

## Eventi tra moduli

Sono il meccanismo principale di comunicazione modulare.

```php
// modules/inventory — emette, senza sapere chi ascolta
BatchExpiringSoon::dispatch($batch->id, $daysLeft);
```

```php
// modules/notifications — ascolta, se installato
Event::listen(BatchExpiringSoon::class, SendExpiryNotification::class);
```

| Proprietà | Effetto |
|---|---|
| Chi emette non conosce chi ascolta | accoppiamento nullo |
| Se il modulo che ascolta non è installato, nulla accade | moduli indipendenti |
| Un evento può avere più ascoltatori | estensibilità |
| Il payload è un contratto pubblico | cambiarlo è una modifica MAJOR |

Il payload di un evento pubblicato ha lo stesso peso contrattuale di un'interfaccia: gli
ascoltatori di altri moduli dipendono da esso.

---

## Ordine e affidabilità

Gli eventi asincroni **non garantiscono l'ordine**. Progettare come se lo garantissero produce
difetti intermittenti.

| Problema | Soluzione |
|---|---|
| Due listener che devono eseguire in sequenza | un solo listener che coordina, o catena esplicita |
| Evento che dipende da un altro | verificare lo stato, non l'ordine di arrivo |
| Eventi duplicati | listener idempotenti |
| Evento perso per fallimento del worker | coda persistente, ritentativi, coda dei falliti |
| Evento emesso prima del commit | `afterCommit()` |

```php
// ✗ Presuppone che l'aggregato sia già stato ricalcolato
public function handle(MovementRegistered $event): void
{
    $batch = Batch::find($event->batchId);
    $this->notifyIfBelowThreshold($batch->current_quantity);   // può essere ancora il valore vecchio
}

// ✓ Calcola ciò di cui ha bisogno
public function handle(MovementRegistered $event): void
{
    $available = app(StockCalculator::class)->available($event->batchId);
    $this->notifyIfBelowThreshold($available);
}
```

---

## Cosa gli eventi non risolvono

| Situazione | Gli eventi non bastano | Cosa usare |
|---|---|---|
| Operazione che deve essere atomica | l'asincronia rompe l'atomicità | una transazione, nella stessa Action |
| Sequenza rigida di passaggi | l'ordine non è garantito | un Service che orchestra |
| Necessità di un risultato immediato | il listener non ritorna nulla | invocazione diretta |
| Coerenza forte tra due aggregati | coerenza eventuale | ripensare i confini degli aggregati |
| Flusso che l'utente deve seguire passo per passo | invisibile | processo esplicito con stato |

L'errore ricorrente è usare gli eventi per orchestrare un processo: si ottiene un flusso
distribuito su sei listener, di cui nessuno conosce l'insieme. Un processo con passaggi obbligati
va scritto in un Service, con lo stato persistito.

---

## Mappa degli eventi

I campi `emits` e `listens` dei manifesti producono la mappa delle integrazioni dell'applicazione.

```bash
php artisan events:map
```

```
MovementRegistered (inventory)
  ├── RecalculateStockOnMovement      (inventory)     async
  ├── WriteAuditEntry                 (audit)         sync
  ├── InvalidateStockCache            (inventory)     sync
  └── NotifyIfBelowThreshold          (notifications) async

BatchExpiringSoon (inventory)
  └── SendExpiryNotification          (notifications) async
```

Questa mappa è la documentazione che serve sempre e che nessuno scrive a mano: va generata, non
mantenuta.

---

## Esempi

### Esempio 1 — aggiungere una conseguenza senza toccare l'operazione

Richiesta: notificare il responsabile qualità quando un lotto viene messo in quarantena.

Modifica necessaria: un nuovo listener su `BatchQuarantined`, registrato nel modulo
`notifications`. L'Action che mette in quarantena **non si tocca**.

### Esempio 2 — evento usato dove serviva una transazione

```php
// ✗ Il trasferimento non è atomico: se il secondo listener fallisce,
//   la merce è uscita da un magazzino e non è entrata nell'altro.
StockTransferRequested::dispatch($data);
// listener 1: registra lo scarico
// listener 2: registra il carico
```

```php
// ✓ Un Service, una transazione
DB::transaction(function () use ($data): void {
    $this->registerMovement->execute($data->toOutbound());
    $this->registerMovement->execute($data->toInbound());
});
```

---

## Best practice

- Nomi al passato, payload con identificatori.
- Emissione dopo il commit.
- Un listener, una conseguenza.
- Listener idempotenti che rileggono lo stato.
- Sincrono solo se il fallimento deve far fallire l'operazione.
- Documentare gli eventi pubblicati nel manifesto del modulo.
- Generare la mappa degli eventi, non mantenerla a mano.
- Per i processi con passaggi obbligati usare un Service, non una catena di eventi.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Nome all'imperativo | Confonde evento e comando | Nome al passato |
| Model nel payload | Dati obsoleti nel listener | Identificatori |
| Emissione dentro la transazione | Listener che non trova i dati | `afterCommit()` |
| Listener che presuppone l'ordine | Difetti intermittenti | Verificare lo stato |
| Listener non idempotente | Dati errati sui ritentativi | Idempotenza |
| Catena lunga di eventi | Flusso non diagnosticabile | Massimo due livelli |
| Eventi per orchestrare un processo | Nessuno conosce il flusso completo | Service con stato |
| Payload cambiato in minor | Ascoltatori di altri moduli rotti | MAJOR |

---

## Checklist

- [ ] Gli eventi hanno nomi al passato e sono `readonly`.
- [ ] I payload contengono identificatori, non model.
- [ ] L'emissione avviene dopo il commit.
- [ ] Ogni listener ha una sola responsabilità.
- [ ] I listener sono idempotenti e rileggono lo stato.
- [ ] I listener asincroni usano `TenantAware`.
- [ ] Nessuna catena oltre due livelli.
- [ ] Gli eventi pubblicati sono dichiarati nel manifesto.
- [ ] La mappa degli eventi è generabile.

---

## Riferimenti

- [Regole Events](../rules/events.md) · [Queue](../rules/queue.md)
- [Sistema modulare](10-modular-system.md) · [Contratto di modulo](11-module-contract.md)
- [Livello applicativo](13-application-layer.md)
- [Code e scheduler](18-queue-scheduler.md)
