# Storage e media

> Come si conservano i file dei clienti: dischi separati, accesso autorizzato, documenti,
> conservazione e cancellazione.

---

## Indice

1. [Descrizione](#descrizione)
2. [I dischi](#i-dischi)
3. [Isolamento per tenant](#isolamento-per-tenant)
4. [Caricamento](#caricamento)
5. [Accesso ai file](#accesso-ai-file)
6. [Organizzazione dei percorsi](#organizzazione-dei-percorsi)
7. [Media e derivate](#media-e-derivate)
8. [Conservazione e cancellazione](#conservazione-e-cancellazione)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

I file dei clienti — documenti, allegati, immagini, esportazioni — richiedono lo stesso isolamento
dei dati del database. Un file accessibile da un URL indovinabile è una fuga di dati, con
l'aggravante che spesso i documenti contengono più informazioni di una riga di tabella.

---

## I dischi

| Disco | Contenuto | Visibilità | Ambito |
|---|---|---|---|
| `tenant` | file dei clienti | privata | per tenant, riconfigurato a runtime |
| `landlord` | asset di piattaforma, contenuti CMS | mista | globale |
| `public` | asset dell'applicazione (CSS, JS, immagini del sito) | pubblica | globale |
| `local` | file temporanei di elaborazione | privata | globale |
| `backups` | archivi di backup | privata, cifrata | globale |

**Nessun file di un cliente sta mai su `public`.** È la regola che elimina l'intera classe di
difetti legati agli URL indovinabili.

---

## Isolamento per tenant

```php
final readonly class FilesystemBootstrapper implements TenantBootstrapper
{
    public function bootstrap(Tenant $tenant): void
    {
        config([
            'filesystems.disks.tenant' => [
                'driver' => 's3',
                'bucket' => config('filesystems.tenant_bucket'),
                'root' => "tenants/{$tenant->slug}",   // radice isolata
                'visibility' => 'private',
                'throw' => true,
            ],
        ]);

        Storage::forgetDisk('tenant');
    }
}
```

`Storage::forgetDisk()` è indispensabile: senza, l'istanza precedente resta in cache e il tenant
successivo scriverebbe nella radice del precedente. È l'equivalente di `DB::purge()` per il
filesystem.

Il codice applicativo non conosce il tenant: scrive su `Storage::disk('tenant')` e la radice è già
quella corretta.

---

## Caricamento

```php
final readonly class StoreDocumentAction
{
    public function execute(UploadedFile $file, DocumentData $data): Document
    {
        // 1. Tipo verificato dal contenuto, non dall'estensione
        $mime = $file->getMimeType();
        throw_unless(
            in_array($mime, config('documents.allowed_mime_types'), true),
            UnsupportedFileType::forMime($mime),
        );

        // 2. Dimensione
        throw_if($file->getSize() > config('documents.max_size'), FileTooLarge::forSize($file->getSize()));

        // 3. Nome rigenerato, mai quello dell'utente
        $path = $file->store("documents/{$data->category}", 'tenant');

        return Document::create([
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),   // conservato come metadato
            'mime_type' => $mime,
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
        ]);
    }
}
```

| Controllo | Perché |
|---|---|
| Tipo dal contenuto | l'estensione è modificabile dall'utente |
| Lista bianca dei tipi | un elenco di divieti è sempre incompleto |
| Dimensione massima per categoria | esaurimento delle risorse |
| Nome rigenerato | il nome originale è input non fidato |
| Checksum | rilevamento di alterazioni, deduplicazione |
| Scansione antivirus | dove il dominio lo richiede |

---

## Accesso ai file

**Mai un URL diretto.** L'accesso passa sempre da un controller che autorizza.

```php
Route::get('/documents/{document}/download', function (Document $document) {
    Gate::authorize('view', $document);

    return Storage::disk('tenant')->download($document->path, $document->original_name);
})->middleware(['auth:tenant', 'tenant'])->name('documents.download');
```

Per i casi in cui serve un URL condivisibile (invio a un fornitore esterno):

```php
$url = URL::temporarySignedRoute(
    'documents.download',
    now()->addMinutes(15),
    ['document' => $document->id],
);
```

L'URL firmato ha scadenza breve e non è indovinabile. Ogni generazione va registrata nell'audit:
è a tutti gli effetti una condivisione di dati verso l'esterno.

---

## Organizzazione dei percorsi

```
tenants/<slug>/
├── documents/
│   ├── invoices/2026/07/
│   ├── contracts/
│   └── attachments/
├── media/
│   ├── originals/
│   └── conversions/
├── exports/          temporanei, con scadenza
└── imports/          temporanei, con scadenza
```

| Regola | Motivo |
|---|---|
| Suddivisione per anno/mese sui volumi alti | evita cartelle con centinaia di migliaia di file |
| Cartelle temporanee separate | pulizia automatica |
| Nessun dato personale nei nomi | i percorsi finiscono nei log |
| Percorsi mai costruiti concatenando input | traversal |

---

## Media e derivate

Per immagini e documenti che richiedono anteprime.

| Aspetto | Regola |
|---|---|
| Originale | conservato sempre, mai sovrascritto |
| Derivate | generate in coda, non durante il caricamento |
| Formati | dimensioni definite per uso (anteprima, elenco, dettaglio) |
| Formati moderni | WebP o AVIF, con ripiego |
| Rigenerazione | possibile dall'originale, senza perdita |
| Pulizia | derivate rimosse con l'originale |

La generazione in coda evita che il caricamento di un documento di venti pagine blocchi la
richiesta HTTP per trenta secondi.

---

## Conservazione e cancellazione

| Tipo | Conservazione | Cancellazione |
|---|---|---|
| Documenti di dominio | secondo norma (spesso 10 anni) | solo con procedura |
| Allegati | ciclo di vita dell'entità collegata | con l'entità |
| Esportazioni | 7 giorni | automatica |
| Importazioni | 30 giorni | automatica |
| Derivate | come l'originale | con l'originale |
| File orfani | — | pulizia periodica |

```bash
php artisan tenants:artisan "storage:prune" --tenant=acme
```

Alla dismissione di un tenant, l'intera radice `tenants/<slug>/` viene rimossa: è il vantaggio
pratico dell'isolamento per radice.

**Cancellazione sicura**: i documenti soggetti a obblighi normativi non si cancellano su richiesta
generica. La procedura di cancellazione verifica prima i vincoli di conservazione.

---

## Esempi

### Esempio 1 — file esposto per errore

```php
// ✗ Il file finisce in public/, accessibile a chiunque ne indovini il nome
$path = $file->store('documents', 'public');
```

```php
// ✓ Disco del tenant, privato, accesso tramite controller autorizzato
$path = $file->store('documents', 'tenant');
```

Il primo caso è una fuga di dati che nessun test funzionale rileva: il file si scarica
correttamente, semplicemente lo può fare chiunque.

### Esempio 2 — condivisione controllata

Un documento va inviato a un consulente esterno che non ha accesso al sistema.

```php
$url = URL::temporarySignedRoute('documents.download', now()->addHours(24), ['document' => $doc->id]);

activity()->performedOn($doc)->log('document.shared_externally');
```

URL con scadenza, condivisione registrata nell'audit: se il documento circola, resta traccia di
chi lo ha condiviso e quando.

---

## Best practice

- Nessun file di cliente su disco pubblico.
- `forgetDisk()` ad ogni cambio di contesto.
- Tipo verificato dal contenuto, con lista bianca.
- Nome del file sempre rigenerato.
- Accesso tramite controller che autorizza.
- URL firmati con scadenza breve, registrati.
- Derivate generate in coda.
- Pulizia automatica dei file temporanei.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| File su disco `public` | Accessibili senza autorizzazione | Disco `tenant` privato |
| `forgetDisk()` dimenticato | Un tenant scrive nella radice di un altro | Nel bootstrapper |
| Tipo dall'estensione | File dannosi caricati | Verifica del contenuto |
| Nome dell'utente conservato come percorso | Traversal, collisioni | Nome rigenerato |
| URL diretto ai file | Nessuna autorizzazione | Controller |
| URL firmato senza scadenza | Condivisione permanente | Scadenza breve |
| Derivate generate in linea | Caricamenti lentissimi | Generazione in coda |
| Nessuna pulizia dei temporanei | Disco pieno | Pulizia schedulata |

---

## Checklist

- [ ] I file dei clienti stanno solo sul disco `tenant`, privato.
- [ ] Il bootstrapper esegue `forgetDisk()`.
- [ ] Tipo verificato dal contenuto, con lista bianca.
- [ ] Dimensione massima per categoria.
- [ ] Nomi rigenerati; nome originale solo come metadato.
- [ ] Accesso tramite controller che autorizza.
- [ ] URL firmati con scadenza e registrazione.
- [ ] Derivate generate in coda e rimosse con l'originale.
- [ ] Pulizia automatica dei file temporanei.
- [ ] La dismissione rimuove l'intera radice del tenant.

---

## Riferimenti

- [Multitenancy](03-multitenancy-overview.md) · [Infrastruttura](14-infrastructure-layer.md)
- [Regole di sicurezza](../rules/security.md)
- [Backup e ripristino](../docs/05-operations/04-backup-and-restore.md)
- [Modulo documents](../modules/catalog/documents.md)
