# Regole — Eventi e listener

> Nomi al passato, payload con identificatori, emissione dopo il commit, listener idempotenti.

---

## Indice

1. [Descrizione](#descrizione)
2. [Regole degli eventi](#regole-degli-eventi)
3. [Regole dei listener](#regole-dei-listener)
4. [Sincrono o asincrono](#sincrono-o-asincrono)
5. [Eventi tra moduli](#eventi-tra-moduli)
6. [Esempi](#esempi)
7. [Best practice](#best-practice)
8. [Errori comuni](#errori-comuni)
9. [Checklist](#checklist)
10. [Riferimenti](#riferimenti)

---

## Descrizione

Gli eventi disaccoppiano: chi compie un'operazione non deve conoscerne le conseguenze. Il costo è
che il flusso non è più leggibile in un unico punto, quindi le regole servono a mantenerlo
prevedibile.

---

## Regole degli eventi

**R1.** Gli eventi di dominio vivono in `Domain/<Context>/Events/`.
*Verifica:* test di architettura.

**R2.** Il nome è al **participio passato**: `MovementRegistered`, non `RegisterMovement`.
*Motivo:* un evento descrive un fatto accaduto; un imperativo descrive un comando.
*Verifica:* revisione.

**R3.** Ogni evento è `final readonly`.
*Motivo:* un fatto accaduto non cambia. *Verifica:* test di architettura.

**R4.** Il payload contiene **identificatori e valori scalari**, mai model o entità.

```php
// ✓ Identificatori
final readonly class MovementRegistered
{
    public function __construct(
        public int $movementId,
        public int $batchId,
        public CarbonImmutable $occurredAt,
    ) {}
}

// ✗ Model serializzato: il listener asincrono lavora su dati obsoleti
public function __construct(public StockMovement $movement) {}
```

*Motivo:* tra emissione ed esecuzione lo stato può cambiare; il listener deve rileggerlo.
*Verifica:* revisione. *Livello: vincolante.*

**R5.** Nessuna logica dentro l'evento: è un messaggio.
*Verifica:* revisione.

**R6.** L'evento si emette **dopo** il commit della transazione.
*Motivo:* un listener che parte prima del commit non trova i dati.
*Verifica:* revisione, test. *Livello: vincolante.*

**R7.** Gli eventi emessi da un modulo sono dichiarati nel suo `module.json`, campo `emits`.
*Motivo:* il payload è un contratto pubblico per gli altri moduli.
*Verifica:* script di verifica.

**R8.** Cambiare il payload di un evento dichiarato è una modifica **MAJOR** del modulo.
*Verifica:* revisione in fase di rilascio.

---

## Regole dei listener

**R9.** Un listener, **una** conseguenza.
*Motivo:* fallimenti isolati, ritentativi indipendenti. *Verifica:* revisione.

**R10.** Il listener **rilegge** lo stato dal database, non si fida del payload.
*Verifica:* revisione.

**R11.** Il listener tollera l'assenza dell'entità.

```php
public function handle(MovementRegistered $event): void
{
    $batch = Batch::find($event->batchId);

    if ($batch === null) {
        return;   // rimosso tra emissione ed esecuzione
    }

    // …
}
```

*Verifica:* revisione, test.

**R12.** Il listener è **idempotente**: eseguirlo due volte produce lo stesso risultato.
*Motivo:* ritentativi e duplicati sono la norma. *Verifica:* test.

**R13.** I listener asincroni implementano `ShouldQueue` e usano il trait `TenantAware`.
*Motivo:* senza, girano nel contesto landlord. *Verifica:* test di architettura.
*Livello: assoluto.*

**R14.** Un listener non emette altri eventi, salvo quando rappresenta un passaggio di stato reale.
Massimo **due** livelli di catena.
*Motivo:* le catene lunghe non sono diagnosticabili. *Verifica:* revisione, mappa degli eventi.

**R15.** Il nome del listener è `<Verbo><Oggetto>On<Evento>`: `RecalculateStockOnMovement`.
*Verifica:* revisione.

**R16.** Il listener non presuppone l'ordine di esecuzione rispetto ad altri listener.
*Motivo:* l'ordine non è garantito per i listener asincroni.
*Verifica:* revisione.

---

## Sincrono o asincrono

| Conseguenza | Modalità | Motivo |
|---|---|---|
| Invalidazione della cache | **sincrona** | deve precedere la prossima lettura |
| Scrittura dell'audit | sincrona (o in coda se il volume è alto) | completezza |
| Aggiornamento di un contatore in transazione | sincrona | coerenza |
| Notifica | asincrona | dipendenza esterna |
| Ricalcolo di aggregati | asincrona | può interessare molte righe |
| Chiamata a servizio esterno | asincrona | latenza e guasti |
| Webhook in uscita | asincrona | il destinatario può non rispondere |

**R17.** Un listener è sincrono **solo se** il suo fallimento deve far fallire l'operazione.
*Verifica:* revisione.

---

## Eventi tra moduli

**R18.** La comunicazione tra moduli avviene per eventi o contratti, mai per accesso diretto ai
model.
*Verifica:* test di architettura. *Livello: vincolante.*

**R19.** Un modulo che ascolta un evento di un altro modulo lo dichiara in `listens`.
*Verifica:* script di verifica.

**R20.** Se il modulo che ascolta non è installato, l'evento viene emesso e nulla accade: chi emette
non verifica la presenza di ascoltatori.
*Verifica:* suite eseguita senza i moduli facoltativi.

---

## Esempi

### Esempio 1 — evento e listener conformi

```php
// Domain/Inventory/Events/MovementRegistered.php
final readonly class MovementRegistered
{
    public function __construct(
        public int $movementId,
        public int $batchId,
        public CarbonImmutable $occurredAt,
    ) {}
}
```

```php
// Listeners/RecalculateStockOnMovement.php
final class RecalculateStockOnMovement implements ShouldQueue
{
    use TenantAware;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];

    public function handle(MovementRegistered $event): void
    {
        $batch = Batch::find($event->batchId);

        if ($batch === null) {
            return;
        }

        // Ricalcolo completo dalla sorgente: idempotente
        $batch->update([
            'quantity' => $batch->movements()->sum(
                DB::raw('quantity * CASE type WHEN "inbound" THEN 1 WHEN "outbound" THEN -1 ELSE 0 END')
            ),
        ]);
    }
}
```

### Esempio 2 — violazioni

```php
// ✗ R2: nome imperativo, ✗ R3: mutabile, ✗ R4: model nel payload
class UpdateStock
{
    public StockMovement $movement;
}

// ✗ R6: emesso dentro la transazione
DB::transaction(function (): void {
    $movement = StockMovement::create([...]);
    UpdateStock::dispatch($movement);
});

// ✗ R9: tre conseguenze, ✗ R12: non idempotente, ✗ R13: nessun contesto tenant
class HandleStockUpdate
{
    public function handle(UpdateStock $event): void
    {
        $event->movement->batch->increment('quantity', $event->movement->quantity);  // non idempotente
        Mail::to('qualita@example.com')->send(new StockChanged());
        Http::post('https://erp.example.com/sync', [...]);
    }
}
```

---

## Best practice

- Emettere gli eventi al termine dell'Action, fuori dalla transazione.
- Un listener per conseguenza, anche quando sembra più semplice unirle.
- Rendere idempotente il listener ricalcolando dalla sorgente invece di incrementare.
- Generare la mappa degli eventi (`php artisan events:map`) per capire il flusso.
- Documentare gli eventi pubblicati come si documenta un'interfaccia.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Nome all'imperativo | Confusione tra evento e comando | Participio passato |
| Model nel payload | Dati obsoleti nel listener | Identificatori |
| Emissione in transazione | Listener che non trova i dati | Dopo il commit |
| Listener con più conseguenze | Un fallimento ne blocca tutte | Un listener per conseguenza |
| Listener non idempotente | Dati errati sui ritentativi | Ricalcolo dalla sorgente |
| Listener asincrono senza `TenantAware` | Esecuzione nel contesto sbagliato | Trait obbligatorio |
| Catena lunga di eventi | Flusso non diagnosticabile | Massimo due livelli |
| Dipendenza dall'ordine dei listener | Difetti intermittenti | Verificare lo stato |
| Payload cambiato in minor | Ascoltatori di altri moduli rotti | MAJOR |

---

## Checklist

- [ ] Gli eventi hanno nome al passato e sono `final readonly`.
- [ ] Il payload contiene solo identificatori e scalari.
- [ ] L'emissione avviene dopo il commit.
- [ ] Ogni listener ha una sola conseguenza.
- [ ] I listener rileggono lo stato e tollerano l'assenza dell'entità.
- [ ] I listener sono idempotenti.
- [ ] I listener asincroni usano `ShouldQueue` e `TenantAware`.
- [ ] Nessuna catena oltre due livelli.
- [ ] Eventi emessi e ascoltati dichiarati nel manifesto del modulo.

---

## Riferimenti

- [Eventi e messaggistica](../architecture/21-events-and-messaging.md)
- [Queue](queue.md) · [Action Pattern](action-pattern.md)
- [Contratto di modulo](../architecture/11-module-contract.md)
- [Template Event e Listener](../templates/infrastructure/README.md)
