# Foundation — Audit

> Che cosa si registra, che cosa non si registra mai, e perché la scrittura sta nell'Action e non in
> un observer.

---

## Indice

1. [Descrizione](#descrizione)
2. [Il contratto](#il-contratto)
3. [Registrare una voce](#registrare-una-voce)
4. [Perché non un observer](#perché-non-un-observer)
5. [Che cosa non registrare mai](#che-cosa-non-registrare-mai)
6. [Audit e activity log](#audit-e-activity-log)
7. [Esempi](#esempi)
8. [Best practice](#best-practice)
9. [Errori comuni](#errori-comuni)
10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

L'audit risponde a una domanda che arriva sempre dopo: *chi ha fatto questa cosa, e quando?*

Nei domini in cui operiamo — sanitario, fiscale, documentale — la domanda non arriva da un collega
curioso: arriva da un cliente che deve rispondere a un ente, e la risposta «non lo sappiamo» ha
conseguenze contrattuali.

La Foundation definisce il **contratto**; l'implementazione che scrive davvero arriva dal modulo
`audit`. Un progetto senza requisiti di tracciamento non si porta dietro le tabelle, e il codice
applicativo non cambia.

---

## Il contratto

```php
interface AuditLogger
{
    public function record(AuditEntry $entry): void;
}
```

```php
final readonly class AuditEntry extends BaseData
{
    public function __construct(
        public string $event,
        public int|string|null $subjectId = null,
        public array $context = [],          // solo scalari
        public int|string|null $actorId = null,
        public ?string $ipAddress = null,
        public ?DateTimeImmutable $occurredAt = null,
    ) {}
}
```

In assenza del modulo, il binding è `NullAuditLogger`, che non scrive nulla. La scelta è **esplicita
e visibile in configurazione**: l'alternativa — un contratto senza implementazione — avrebbe
prodotto un errore di risoluzione al primo utilizzo, in un punto lontano dalla causa.

Il contesto ammette solo scalari, deliberatamente: impedisce di passare un model intero e di finire
per registrare, insieme all'evento, dati sanitari o fiscali che nel registro non devono comparire.

---

## Registrare una voce

```php
final class ArchiveDocumentAction extends BaseAction
{
    use RecordsAudit;

    public function execute(ArchiveDocumentData $data): void
    {
        $this->transaction(function () use ($data): void {
            $document = $this->documents->findOrFail($data->documentId);
            $document->archive($data->reason);
            $this->documents->save($document);

            $this->audit('document.archived', $document->getKey(), [
                'reason_code' => $data->reason->value,
                'previous_status' => $document->getOriginal('status'),
            ]);
        });
    }
}
```

Il nome dell'evento è `<risorsa>.<azione-al-passato>`: `document.archived`,
`movement.registered`, `tenant.suspended`. È al passato perché registra un fatto avvenuto, non
un'intenzione.

---

## Perché non un observer

È la scelta di progetto più discutibile di quest'area, e ha una motivazione precisa.

Un observer sul model saprebbe che una riga è cambiata, e lo saprebbe **sempre**, senza che nessuno
debba ricordarsene. È il suo vantaggio, ed è reale.

Il problema è che non sa **perché** è cambiata:

```
✗  documents.status: 'active' → 'archived'
✓  L'utente Rossi ha archiviato il documento 4821, motivo: superato il periodo di conservazione
```

La prima riga è un registro delle modifiche, e non risponde a nessuna delle domande per cui l'audit
esiste. La seconda sì, e solo l'Action ha le informazioni per scriverla: conosce l'intenzione,
perché è l'intenzione ad averla invocata.

Il costo di questa scelta è che una registrazione può essere dimenticata. Si paga con una voce nella
[checklist di sicurezza](../../checklists/security-checklist.md) e con la revisione, che è meno
comodo di un observer — ma un audit comodo e inutile non vale il costo delle sue tabelle.

---

## Che cosa non registrare mai

| Mai nel contesto | Perché |
|---|---|
| Password, token, chiavi | il registro diventa il posto più pericoloso del sistema |
| Dati sanitari | il registro non è soggetto alle stesse restrizioni di accesso dei dati |
| Codici fiscali, dati di pagamento | minimizzazione: non servono a sapere chi ha fatto cosa |
| Contenuto dei documenti | l'audit dice che il documento è stato aperto, non cosa conteneva |
| Corpi di richiesta completi | contengono tutto quanto sopra, senza che nessuno lo verifichi |

Il criterio: **il contesto serve a identificare l'oggetto e la natura dell'azione**, non a
ricostruirne il contenuto. Se qualcuno deve sapere cosa conteneva il documento, ha il documento.

L'audit è anche **immutabile**: nessun percorso di codice lo modifica o lo cancella. Un registro
modificabile non dimostra nulla, e la sua sola esistenza dà una falsa sicurezza.

---

## Audit e activity log

Sono due cose diverse, e confonderle produce un registro che non serve a nessuno dei due scopi.

| | Audit | Activity log |
|---|---|---|
| A che domanda risponde | chi ha fatto cosa, per la conformità | cosa è successo su questa entità |
| Chi lo legge | tenant admin, auditor, ente | utente operativo |
| Che cosa registra | mutazioni sensibili | attività ordinarie, anche letture rilevanti |
| Modificabile | mai | può essere ripulito secondo la politica di conservazione |
| Conservazione | secondo obbligo, tipicamente anni | mesi |

---

## Esempi

### Registrare un accesso a dati sensibili

Non tutte le voci di audit riguardano mutazioni: in ambito sanitario, **anche la lettura** va
tracciata.

```php
final class ViewPatientRecordAction extends BaseAction
{
    use RecordsAudit;

    public function execute(int $recordId): PatientRecord
    {
        $record = $this->records->findOrFail($recordId);

        $this->audit('patient_record.viewed', $record->getKey(), [
            'department_id' => $record->department_id,
        ]);

        return $record;
    }
}
```

Nel contesto c'è il reparto, non la diagnosi.

### Registrare la generazione di un URL firmato

```php
$this->audit('document.share_link_generated', $document->getKey(), [
    'expires_in_seconds' => $ttl,
]);
```

Un URL firmato è una **condivisione di dati verso l'esterno**: se non viene registrata, il registro
di chi ha visto cosa ha un buco che nessuno nota.

---

## Best practice

- Registrare dall'Action, dove l'intenzione è nota.
- Nomi degli eventi `<risorsa>.<azione-al-passato>`, coerenti in tutto il progetto.
- Nel contesto solo identificativi e valori di stato: mai contenuti.
- Tracciare anche le letture, dove il dominio lo richiede.
- Tracciare la generazione di URL firmati: è una condivisione, non un accesso interno.
- Verificare che nessun percorso di codice modifichi o cancelli l'audit.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Audit da observer | Registra le colonne, non le intenzioni | Dall'Action |
| Model intero nel contesto | Dati sensibili nel registro | Solo scalari |
| Contenuto del documento registrato | Il registro diventa una seconda copia dei dati | Solo identificativi |
| Audit modificabile | Non dimostra nulla | Immutabile |
| Letture non tracciate dove serve | Buco nella conformità | Tracciare anche gli accessi |
| URL firmato non registrato | Condivisioni invisibili | Voce dedicata |
| Audit e activity log confusi | Nessuno dei due serve al suo scopo | Due registri distinti |

---

## Checklist

- [ ] Ogni mutazione su dati sensibili ha una voce di audit.
- [ ] La voce dice chi, cosa, quando, da dove.
- [ ] Il contesto contiene solo identificativi e valori di stato.
- [ ] Nessun contenuto sensibile nel registro.
- [ ] Gli accessi in lettura sono tracciati dove il dominio lo richiede.
- [ ] L'audit è immutabile: nessun percorso lo modifica o lo cancella.
- [ ] Audit e activity log sono distinti.

---

## Riferimenti

- [Audit e activity log](../../architecture/20-audit-activity-log.md)
- [Regole di sicurezza](../../rules/security.md) · [Logging](../../rules/logging.md)
- [Checklist di sicurezza](../../checklists/security-checklist.md)
