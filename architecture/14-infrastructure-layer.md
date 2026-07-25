# Livello di infrastruttura

> Le implementazioni tecniche: repository, integrazioni esterne, filesystem, canali. Il livello
> sostituibile senza toccare il dominio.

---

## Indice

1. [Descrizione](#descrizione)
2. [Che cosa contiene](#che-cosa-contiene)
3. [Repository](#repository)
4. [Integrazioni esterne](#integrazioni-esterne)
5. [Filesystem e documenti](#filesystem-e-documenti)
6. [Registrazione dei binding](#registrazione-dei-binding)
7. [Gestione degli errori tecnici](#gestione-degli-errori-tecnici)
8. [Testabilità](#testabilità)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

L'infrastruttura è il livello che parla con il mondo esterno: database, servizi remoti, file,
posta, stampanti, lettori RFID. È l'unico livello che si riscrive quando cambia una tecnologia.

Il criterio di appartenenza: *se cambiassimo fornitore o tecnologia, questa classe si
riscriverebbe?* Se sì, è infrastruttura.

---

## Che cosa contiene

```
Infrastructure/
├── Repositories/     implementazioni dei contratti di persistenza
├── External/         client di servizi remoti
├── Storage/          filesystem, generazione documenti
├── Notifications/    canali di recapito
└── Search/           motori di ricerca
```

**Cosa non contiene mai:** regole di business. Se un'implementazione contiene una decisione di
dominio, quella decisione è nel posto sbagliato e verrà duplicata alla prima implementazione
alternativa.

---

## Repository

Implementa un contratto dichiarato nel dominio.

```php
namespace App\Infrastructure\Repositories;

final readonly class EloquentBatchRepository implements BatchRepository
{
    public function findById(BatchId $id): ?Batch
    {
        return Batch::query()->find($id->value);
    }

    public function findOrFail(BatchId $id): Batch
    {
        return Batch::query()->findOr($id->value, fn () => throw BatchNotFound::withId($id));
    }

    public function expiringWithin(int $days): Collection
    {
        return Batch::query()
            ->where('expiry_date', '<=', now()->addDays($days))
            ->where('quantity', '>', 0)
            ->orderBy('expiry_date')
            ->get();
    }

    public function save(Batch $batch): void
    {
        $batch->save();
    }
}
```

| Regola | Motivo |
|---|---|
| Implementa sempre un contratto del dominio | sostituibilità |
| Ritorna entità o aggregati, non array arbitrari | il confine resta netto |
| Nessuna regola di business | appartiene al dominio |
| Solleva eccezioni di dominio, non tecniche | il chiamante non conosce Eloquent |
| Nessuna paginazione o filtro di presentazione | quello è compito delle Query |

Non serve filtrare per tenant: la connessione attiva è già quella del tenant corrente.

---

## Integrazioni esterne

Ogni servizio remoto è dietro un contratto del dominio.

```php
// Dominio: dichiara il bisogno
namespace App\Domain\Shared\Contracts;

interface VatValidator
{
    /** @throws VatValidationUnavailable */
    public function isValid(VatNumber $vatNumber): bool;
}
```

```php
// Infrastruttura: implementa
namespace App\Infrastructure\External;

final readonly class ViesVatValidator implements VatValidator
{
    public function __construct(private PendingRequest $client) {}

    public function isValid(VatNumber $vatNumber): bool
    {
        try {
            return $this->client
                ->timeout(5)
                ->retry(2, 200)
                ->get('/check', ['vat' => (string) $vatNumber])
                ->throw()
                ->json('valid', false);
        } catch (RequestException $e) {
            throw VatValidationUnavailable::becauseOf($e);
        }
    }
}
```

| Requisito | Perché |
|---|---|
| Timeout esplicito | un servizio lento non deve bloccare l'applicazione |
| Ritentativi con attesa crescente | i guasti transitori sono la norma |
| Interruttore di circuito sui servizi critici | evita di insistere su un servizio fermo |
| Traduzione degli errori tecnici in eccezioni di dominio | il chiamante non conosce HTTP |
| Registrazione delle chiamate | diagnosi delle integrazioni |
| Nessun segreto nel codice | configurazione |
| Comportamento degradato previsto | cosa fa il sistema se il servizio non risponde |

L'ultimo punto va deciso **prima**: se il validatore di partite IVA non risponde, si blocca la
creazione del fornitore o si accetta con verifica differita? È una decisione di dominio, non
tecnica.

---

## Filesystem e documenti

```php
final readonly class TenantDocumentStorage implements DocumentStorage
{
    public function store(UploadedFile $file, string $category): StoredDocument
    {
        $path = Storage::disk('tenant')->putFile(
            "documents/{$category}",
            $file,
            'private',
        );

        return new StoredDocument(
            path: $path,
            originalName: $file->getClientOriginalName(),
            mimeType: $file->getMimeType(),
            size: $file->getSize(),
        );
    }

    public function download(StoredDocument $document): StreamedResponse
    {
        return Storage::disk('tenant')->download($document->path, $document->originalName);
    }
}
```

| Regola | Motivo |
|---|---|
| Disco `tenant`, mai `public` | i file di un cliente non sono pubblici |
| Nome rigenerato | il nome fornito dall'utente è input non fidato |
| Visibilità `private` | l'accesso passa da un controller che autorizza |
| Percorso mai costruito a mano | il disco del tenant è già isolato |
| Tipo verificato dal contenuto, non dall'estensione | l'estensione è modificabile |

---

## Registrazione dei binding

```php
final class InfrastructureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BatchRepository::class, EloquentBatchRepository::class);
        $this->app->bind(DocumentStorage::class, TenantDocumentStorage::class);

        $this->app->bind(VatValidator::class, function (Application $app): VatValidator {
            return config('services.vies.enabled')
                ? new ViesVatValidator($app->make(PendingRequest::class))
                : new AlwaysValidVatValidator();   // ambienti senza accesso esterno
        });
    }
}
```

Il binding condizionale è ciò che permette agli ambienti di sviluppo e di test di funzionare senza
accesso ai servizi esterni, **senza** che il dominio se ne accorga.

---

## Gestione degli errori tecnici

| Errore tecnico | Diventa | Comportamento |
|---|---|---|
| Timeout HTTP | `ServiceUnavailable` di dominio | ritenta o degrada |
| `404` da un servizio | `ResourceNotFound` di dominio | gestito dal chiamante |
| Errore di autenticazione remota | `IntegrationMisconfigured` | allarme, non ritentare |
| Errore SQL di vincolo | eccezione di dominio specifica | messaggio comprensibile |
| Disco pieno | `StorageUnavailable` | allarme critico |

Il chiamante non deve mai vedere `RequestException` o `QueryException`: sono dettagli tecnici che,
se propagati, legano il dominio alla tecnologia.

---

## Testabilità

| Componente | Come si testa |
|---|---|
| Repository | test di integrazione con database |
| Client esterno | `Http::fake()` con risposte realistiche, compresi gli errori |
| Storage | `Storage::fake('tenant')` |
| Canali di notifica | `Notification::fake()` |
| Binding | verifica della risoluzione dal contenitore |

```php
it('traduce l\'indisponibilità del servizio in eccezione di dominio', function (): void {
    Http::fake(['*/check' => Http::response(status: 503)]);

    expect(fn () => app(VatValidator::class)->isValid(new VatNumber('IT12345678901')))
        ->toThrow(VatValidationUnavailable::class);
});
```

Testare il **percorso di errore** è più importante che testare quello felice: il servizio esterno
fallirà, ed è lì che il comportamento va definito.

---

## Esempi

### Esempio 1 — sostituzione di un fornitore

Il servizio di firma digitale cambia. Il dominio dichiara `DigitalSigner`; si scrive una nuova
implementazione, si cambia il binding, si eseguono i test.

Dominio, Action e test di dominio: invariati.

### Esempio 2 — regola di dominio nel posto sbagliato

```php
// ✗ La regola «solo i lotti non scaduti» è nel repository:
//   ogni implementazione alternativa dovrà ricordarsene.
public function findForPicking(BatchId $id): ?Batch
{
    return Batch::query()->where('expiry_date', '>', now())->find($id->value);
}

// ✓ Il repository recupera, il dominio decide
public function findById(BatchId $id): ?Batch
{
    return Batch::query()->find($id->value);
}
// e nell'Action: throw_if($batch->isExpired(), BatchExpired::withId($id));
```

---

## Best practice

- Ogni dipendenza esterna dietro un contratto del dominio.
- Timeout, ritentativi e comportamento degradato definiti esplicitamente.
- Tradurre gli errori tecnici in eccezioni di dominio.
- Binding condizionali per gli ambienti senza accesso esterno.
- Testare i percorsi di errore delle integrazioni.
- Storage sempre privato e per tenant.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Regole di business nel repository | Duplicate ad ogni implementazione | Regole nel dominio |
| Client esterno senza contratto | Sostituzione impossibile | Contratto nel dominio |
| Nessun timeout | Un servizio lento blocca l'applicazione | Timeout esplicito |
| Eccezioni tecniche propagate | Dominio legato alla tecnologia | Traduzione |
| File su disco `public` | Accessibili senza autorizzazione | Disco `tenant`, privato |
| Nome del file dall'utente | Superficie d'attacco | Nome rigenerato |
| Solo il percorso felice testato | Il comportamento in errore non è definito | Testare gli errori |

---

## Checklist

- [ ] Ogni repository implementa un contratto del dominio.
- [ ] Nessuna regola di business nell'infrastruttura.
- [ ] Ogni servizio esterno è dietro un contratto.
- [ ] Timeout, ritentativi e degradazione definiti.
- [ ] Errori tecnici tradotti in eccezioni di dominio.
- [ ] Storage privato, su disco del tenant, con nomi rigenerati.
- [ ] Binding registrati in un service provider.
- [ ] Percorsi di errore delle integrazioni testati.

---

## Riferimenti

- [Livelli](02-layers.md) · [Dominio](12-domain-layer.md) · [Applicativo](13-application-layer.md)
- [Repository Pattern](../rules/repository-pattern.md) · [Dependency Injection](../rules/dependency-injection.md)
- [Storage e media](19-storage-media.md)
- [Template infrastruttura](../templates/infrastructure/README.md)
