# Modulo — documents

> Archiviare i file dei clienti in modo che nessuno possa leggere quelli di un altro.

| | |
|---|---|
| **Nome** | `documents` |
| **Categoria** | opzionale |
| **Dipende da** | — |
| **Contratti implementati** | `DocumentStore` |

---

## Indice

1. [Descrizione](#descrizione) 2. [Che cosa fornisce](#che-cosa-fornisce)
3. [Che cosa non fa](#che-cosa-non-fa) 4. [Il percorso di un file](#il-percorso-di-un-file)
5. [Accesso ai file](#accesso-ai-file) 6. [Configurazione](#configurazione)
7. [Integrazione](#integrazione) 8. [Adozione](#adozione) 9. [Esempi](#esempi)
10. [Best practice](#best-practice) 11. [Errori comuni](#errori-comuni) 12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Ogni gestionale finisce per gestire file: allegati, scansioni, contratti, referti, fatture. È una
funzionalità che sembra semplice e che contiene la maggior parte dei modi in cui si perdono dati
altrui.

I file sono il punto in cui l'isolamento tra clienti è **più fragile**. Il database è protetto dalla
struttura; un file è un percorso su un disco, e un percorso sbagliato lo rende leggibile a chiunque
lo indovini. Non c'è nulla, nel filesystem, che impedisca a un tenant di leggere la cartella di un
altro: lo impedisce solo il codice.

---

## Che cosa fornisce

### Tabelle — tenant

| Tabella | Contenuto |
|---|---|
| `documents` | metadati: nome originale, tipo verificato, dimensione, impronta |
| `document_versions` | versioni successive, ognuna con il proprio file |
| `document_links` | associazione polimorfa a qualunque entità del progetto |
| `document_shares` | URL firmati generati, con scadenza e destinatario |

Il file non sta nel database: sul disco privato del tenant sta il **contenuto**, nella tabella
stanno i metadati e il riferimento.

### Permessi

| Permesso | Consente |
|---|---|
| `document.view` · `document.upload` · `document.delete` | operazioni di base |
| `document.share` | generare un URL firmato |
| `document.restore_version` | ripristinare una versione precedente |

### Comandi

| Comando | Quando |
|---|---|
| `documents:scan` | analisi antivirus dei file in attesa |
| `documents:prune-orphans --dry-run` | file senza riferimento |
| `documents:verify-integrity` | confronto tra impronta registrata e file su disco |
| `documents:migrate-disk --to=` | spostamento tra dischi |

### Eventi

| Evento | Emesso quando |
|---|---|
| `DocumentUploaded` · `DocumentVersionCreated` | caricamento |
| `DocumentShared` | URL firmato generato |
| `DocumentScanRejected` | antivirus positivo |

---

## Che cosa non fa

| Non fa | Dove va cercato |
|---|---|
| Anteprime e conversioni di formato | non previsto: dipende da binari esterni con proprie vulnerabilità |
| Ricerca full-text nel contenuto | [reporting](reporting.md), o un servizio di ricerca |
| Firma digitale | non previsto: richiede un fornitore qualificato |
| Conservazione a norma | non previsto: è un servizio, non una funzionalità |
| Editing collaborativo | fuori ambito |
| Motore antivirus | fornisce l'integrazione; il motore è un servizio esterno |
| Politica di conservazione del dominio | progetto: quanto conservare una fattura è una decisione del committente |

**Anteprime e conversioni.** Generarle richiede binari che elaborano file forniti dagli utenti —
ImageMagick, LibreOffice, ghostscript — ognuno con la propria storia di vulnerabilità sfruttabili
con un file costruito ad arte. Farlo bene richiede un processo isolato con risorse limitate, che è
un componente di infrastruttura e non una funzionalità di modulo. Chi ne ha bisogno lo aggiunge nel
progetto, consapevolmente.

---

## Il percorso di un file

```
caricamento
   │
   ├─ dimensione entro il limite?          no → rifiuto
   ├─ tipo verificato dal CONTENUTO?       no → rifiuto
   │     (mai dall'estensione: si cambia con una rinomina)
   │
   ├─ nome rigenerato                      l'originale resta un metadato
   │     (un nome fornito dall'utente può contenere ../ o caratteri
   │      che il filesystem interpreta)
   │
   ├─ scritto sul disco PRIVATO del tenant
   ├─ impronta calcolata e registrata
   │
   └─ stato: in_scansione
              │
              ├─ antivirus negativo → disponibile
              └─ antivirus positivo → in quarantena, evento, file rimosso
```

Il file è **inaccessibile finché la scansione non è conclusa**. È l'unico ordine corretto: renderlo
disponibile durante la scansione significa che l'unico momento in cui conta è anche l'unico in cui
non è protetto.

---

## Accesso ai file

Due strade, e nessuna terza.

**1. Attraverso un controller che autorizza.** Il controller verifica la Policy e restituisce il
file in streaming. È la strada predefinita.

**2. Con un URL firmato a scadenza breve.** Serve quando il file deve essere raggiungibile da chi
non ha una sessione — un destinatario esterno, un allegato di posta. La generazione dell'URL è
**registrata nell'activity log**, perché è una condivisione di dati verso l'esterno: se non viene
registrata, il registro di chi ha visto cosa ha un buco che nessuno nota.

Nessun file di cliente su disco pubblico, mai. Non è una raccomandazione: un file su disco pubblico
è raggiungibile da chiunque ne indovini il percorso, e i percorsi generati sono più prevedibili di
quanto sembri.

---

## Configurazione

```php
// config/documents.php
return [
    'disk' => env('FOUNDATION_TENANT_DISK', 'tenant'),

    'max_size_kb' => 20480,

    // Lista bianca. Un elenco di tipi vietati è sempre incompleto; questo è
    // finito e noto.
    'allowed_mime_types' => [
        'application/pdf',
        'image/jpeg', 'image/png',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ],

    'antivirus' => [
        'enabled' => true,
        // Con l'antivirus disattivato il file resta comunque in quarantena:
        // disattivarlo è una decisione, non una scorciatoia.
        'block_on_unavailable' => true,
    ],

    'signed_url_ttl' => 300,

    'versions' => [
        'enabled' => true,
        'keep' => 10,
    ],
];
```

---

## Integrazione

```php
// Il progetto lavora sul contratto, non sulle classi del modulo
interface DocumentStore
{
    public function store(UploadedFile $file, string $collection): DocumentReference;
    public function get(DocumentReference $reference): StreamInterface;
    public function signedUrl(DocumentReference $reference, int $ttl): string;
}
```

Un'entità del progetto associa documenti senza conoscere il modulo:

```php
$this->documents->attach($batch, $file, collection: 'technical_sheets');
```

Se il modulo non è attivo, il binding non esiste e la funzionalità non è disponibile — in modo
esplicito, non con un errore di classe non trovata.

---

## Adozione

```bash
# config/foundation.php → 'modules' => ['enabled' => [..., 'documents']]

php artisan tenants:migrate
php artisan tenants:artisan "auth:sync-permissions"
php artisan tenants:artisan "documents:verify-integrity"
```

Verificare che il disco per tenant esista, sia **privato** e sia scrivibile, e che sia incluso nel
backup: un backup del database senza i file ripristina un sistema che mostra documenti inesistenti.

---

## Esempi

### Verifica del tipo dal contenuto

```php
// ✗ L'estensione si cambia con una rinomina
if ($file->getClientOriginalExtension() === 'pdf') { … }

// ✓ Il tipo si legge dai byte iniziali del file
$mime = $file->getMimeType();

if (! in_array($mime, config('documents.allowed_mime_types'), strict: true)) {
    throw UnsupportedDocumentType::for($mime);
}
```

### Nome rigenerato

```php
// Il nome fornito dall'utente non tocca mai il filesystem: può contenere ../,
// caratteri interpretati dal sistema, o una lunghezza che rompe il percorso.
$storedName = Str::ulid().'.'.$extension;

Document::create([
    'original_name' => $file->getClientOriginalName(),   // metadato
    'stored_name' => $storedName,
    'mime_type' => $mime,
    'checksum' => hash_file('sha256', $file->getRealPath()),
]);
```

---

## Best practice

- Verificare il tipo dal contenuto, sempre.
- Rigenerare il nome; conservare l'originale come metadato.
- Tenere il file inaccessibile fino a scansione conclusa.
- Registrare la generazione di ogni URL firmato.
- Includere i dischi dei tenant nel backup e **provare il ripristino** insieme al database.
- Ridimensionare le immagini caricate lato server: le scansioni non ridimensionate riempiono il
  disco in settimane.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| File su disco pubblico | Accessibili a chiunque indovini il percorso | Disco privato per tenant |
| Tipo dedotto dall'estensione | File dannosi caricati | Verifica dal contenuto |
| Nome dell'utente sul filesystem | Attraversamento di percorso | Nome rigenerato |
| File accessibile durante la scansione | Protetto tranne quando conta | Inaccessibile fino a esito |
| URL firmato senza scadenza | Condivisione permanente | TTL breve |
| Generazione dell'URL non registrata | Condivisioni invisibili | Activity log |
| Backup senza i file | Ripristino che mostra documenti inesistenti | Backup di database **e** dischi |
| Immagini non ridimensionate | Il disco si riempie in settimane | Ridimensionamento lato server |
| Lista nera dei tipi | Sempre incompleta | Lista bianca |

---

## Checklist

- [ ] Il disco del tenant è privato e verificato.
- [ ] Il tipo dei file è verificato dal contenuto.
- [ ] I nomi sono rigenerati, gli originali sono metadati.
- [ ] I file sono inaccessibili fino a scansione conclusa.
- [ ] L'accesso passa da un controller che autorizza, o da un URL firmato a scadenza.
- [ ] Ogni generazione di URL firmato è registrata.
- [ ] I dischi dei tenant sono nel backup, con ripristino provato.
- [ ] Le immagini sono ridimensionate lato server.

---

## Riferimenti

- [Storage e media](../../architecture/19-storage-media.md)
- [Sicurezza](../../rules/security.md) · [Validazione](../../rules/validation.md)
- [Backup e ripristino](../../docs/05-operations/04-backup-and-restore.md)
- [Checklist di sicurezza](../../checklists/security-checklist.md)
