# Modulo — audit

> Il registro immutabile di chi ha fatto cosa, quando, da dove.

| | |
|---|---|
| **Nome** | `audit` |
| **Categoria** | opzionale |
| **Dipende da** | — |
| **Contratti implementati** | `AuditLogger` |

---

## Indice

1. [Descrizione](#descrizione) 2. [Che cosa fornisce](#che-cosa-fornisce)
3. [Che cosa non fa](#che-cosa-non-fa) 4. [Audit e activity log](#audit-e-activity-log)
5. [Immutabilità](#immutabilità) 6. [Configurazione](#configurazione)
7. [Integrazione](#integrazione) 8. [Adozione](#adozione) 9. [Esempi](#esempi)
10. [Best practice](#best-practice) 11. [Errori comuni](#errori-comuni) 12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

L'audit risponde a una domanda che arriva sempre dopo: *chi ha fatto questa cosa, e quando?*

Nei domini in cui operiamo — sanitario, fiscale, documentale — la domanda non arriva da un collega
curioso: arriva da un cliente che deve rispondere a un ente, e la risposta «non lo sappiamo» ha
conseguenze contrattuali.

La [Foundation](../../foundation/docs/06-audit.md) definisce il contratto `AuditLogger`; questo
modulo lo implementa. In assenza del modulo il binding è `NullAuditLogger` e non viene scritto
nulla: la scelta è esplicita e visibile in configurazione, invece di essere un errore di risoluzione
al primo utilizzo.

---

## Che cosa fornisce

### Tabelle — tenant

| Tabella | Contenuto |
|---|---|
| `audit_entries` | evento, soggetto, attore, ora, indirizzo, contesto in JSON |
| `activity_log` | attività ordinarie, con politica di conservazione più corta |

Il registro vive nel **database del tenant**, non nel landlord. È il cliente il titolare di quei
dati, ed è il cliente che deve poterli consultare per rispondere a chi glieli chiede.

### Permessi

| Permesso | Consente |
|---|---|
| `audit.view` | consultare il registro |
| `audit.export` | esportare per un periodo |

### Comandi

| Comando | Quando |
|---|---|
| `audit:export --from= --to=` | richiesta di un ente o del cliente |
| `audit:prune --older-than=` | applicazione della politica di conservazione |
| `audit:verify` | verifica di integrità della catena |

`audit:prune` **non** tocca `audit_entries`: agisce solo su `activity_log`. Il registro di audit ha
un periodo di conservazione dichiarato dal committente, tipicamente di anni, e la sua riduzione non
è un'operazione di manutenzione ordinaria.

### Contratti implementati

| Contratto | Implementazione |
|---|---|
| `WidStudios\Foundation\Contracts\Audit\AuditLogger` | `DatabaseAuditLogger` |

---

## Che cosa non fa

| Non fa | Dove va cercato |
|---|---|
| Decidere che cosa registrare | progetto: la scelta è nell'Action, dove l'intenzione è nota |
| Registrare automaticamente ogni modifica | scelta deliberata: vedi sotto |
| Interfaccia di consultazione per auditor esterni | progetto, o pannello dedicato |
| Firma digitale o marca temporale certificata | non previsto: richiede un fornitore qualificato |
| Conservazione a norma | non previsto: è un servizio, non una funzionalità |
| Correlazione tra tenant | impossibile per costruzione: nessuna query cross-tenant |

**Perché non registra automaticamente ogni modifica.** Un observer sul model saprebbe che una riga è
cambiata, e lo saprebbe sempre, senza che nessuno debba ricordarsene. Ma non sa **perché**:

```
✗  documents.status: 'active' → 'archived'
✓  L'utente Rossi ha archiviato il documento 4821, motivo: superato il periodo di conservazione
```

La prima riga è un registro delle modifiche, e non risponde a nessuna delle domande per cui l'audit
esiste. Solo l'Action ha le informazioni per scrivere la seconda, perché è l'intenzione ad averla
invocata.

Il costo è che una registrazione può essere dimenticata. Si paga con una voce nella
[checklist di sicurezza](../../checklists/security-checklist.md) e con la revisione — meno comodo di
un observer, ma un audit comodo e inutile non vale il costo delle sue tabelle.

---

## Audit e activity log

Sono due cose diverse, e confonderle produce un registro che non serve a nessuno dei due scopi.

| | Audit | Activity log |
|---|---|---|
| Domanda | chi ha fatto cosa, per la conformità | cosa è successo su questa entità |
| Lettore | tenant admin, auditor, ente | utente operativo |
| Contenuto | mutazioni sensibili, accessi rilevanti | attività ordinarie |
| Modificabile | **mai** | ripulibile secondo la politica |
| Conservazione | per obbligo, tipicamente anni | mesi |

---

## Immutabilità

Un registro modificabile non dimostra nulla, e la sua sola esistenza dà una falsa sicurezza. Il
modulo lo garantisce su tre livelli, perché nessuno dei tre da solo è sufficiente:

1. **applicativo** — il model non ha `update()` né `delete()`, e la Policy nega entrambe le azioni
   a chiunque;
2. **strutturale** — nessuna migration del modulo aggiunge percorsi di modifica, e un test di
   architettura verifica che nessun codice invochi mutazioni su `AuditEntry`;
3. **crittografico** — ogni voce contiene l'impronta della precedente; `audit:verify` percorre la
   catena e segnala il punto in cui si spezza.

Il terzo livello non impedisce la manomissione: la rende **rilevabile**. Chi ha accesso diretto al
database può sempre modificare una riga; non può farlo senza che il controllo di integrità lo
mostri.

---

## Configurazione

```php
// config/audit.php
return [
    'enabled' => true,

    // Registrare l'indirizzo IP: utile per la diagnosi, ma è un dato
    // personale. La scelta appartiene al committente, non al codice.
    'record_ip' => true,

    // Chiavi che vengono rimosse dal contesto prima della scrittura, anche se
    // qualcuno le passa. Rete di sicurezza, non sostituto della disciplina.
    'redact_keys' => ['password', 'token', 'secret', 'authorization', 'fiscal_code'],

    'retention' => [
        'audit_entries' => null,        // null = nessuna cancellazione automatica
        'activity_log_days' => 180,
    ],

    'integrity_chain' => true,
];
```

---

## Integrazione

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

Il nome dell'evento è `<risorsa>.<azione-al-passato>`. È al passato perché registra un fatto
avvenuto, non un'intenzione.

Il contesto ammette **solo scalari**: impedisce di passare un model intero e di finire per
registrare, insieme all'evento, dati sanitari o fiscali che nel registro non devono comparire.

---

## Adozione

```bash
# 1. config/foundation.php → 'modules' => ['enabled' => [..., 'audit']]
# 2. config/foundation.php → 'audit' => ['logger' => DatabaseAuditLogger::class]

php artisan tenants:migrate
php artisan tenants:artisan "auth:sync-permissions"
php artisan tenants:artisan "audit:verify"
```

Su un progetto esistente il registro parte **vuoto**: le operazioni precedenti non sono
ricostruibili, e dichiararlo evita che qualcuno lo scopra quando gli serve.

---

## Esempi

### Tracciare un accesso in lettura

```php
$this->audit('patient_record.viewed', $record->getKey(), [
    'department_id' => $record->department_id,
]);
```

In ambito sanitario **anche la lettura** va tracciata. Nel contesto c'è il reparto, non la diagnosi.

### Tracciare una condivisione verso l'esterno

```php
$this->audit('document.share_link_generated', $document->getKey(), [
    'expires_in_seconds' => $ttl,
]);
```

Un URL firmato è una condivisione di dati verso l'esterno: se non viene registrata, il registro di
chi ha visto cosa ha un buco che nessuno nota.

---

## Best practice

- Registrare dall'Action, dove l'intenzione è nota.
- Nomi degli eventi `<risorsa>.<azione-al-passato>`, coerenti in tutto il progetto.
- Nel contesto solo identificativi e valori di stato: mai contenuti.
- Tracciare anche le letture, dove il dominio lo richiede.
- Eseguire `audit:verify` nelle verifiche periodiche di esercizio.
- Concordare il periodo di conservazione con il committente **prima** del rilascio.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Audit da observer | Registra le colonne, non le intenzioni | Dall'Action |
| Model intero nel contesto | Dati sensibili nel registro | Solo scalari |
| Contenuto del documento registrato | Il registro diventa una seconda copia dei dati | Solo identificativi |
| Audit modificabile | Non dimostra nulla | Immutabile su tre livelli |
| `audit:prune` sulle voci di audit | Perdita di prove | Solo su `activity_log` |
| Letture non tracciate dove serve | Buco nella conformità | Tracciare anche gli accessi |
| URL firmato non registrato | Condivisioni invisibili | Voce dedicata |
| Audit nel landlord | Il cliente non può consultare i propri dati | Nel database del tenant |
| Conservazione non concordata | Dati cancellati troppo presto, o mai | Deciso con il committente |

---

## Checklist

- [ ] Ogni mutazione su dati sensibili ha una voce di audit.
- [ ] Il contesto contiene solo identificativi e valori di stato.
- [ ] Gli accessi in lettura sono tracciati dove il dominio lo richiede.
- [ ] La generazione di URL firmati è registrata.
- [ ] Nessun percorso di codice modifica o cancella l'audit.
- [ ] `audit:verify` è nelle verifiche periodiche.
- [ ] Il periodo di conservazione è concordato e configurato.
- [ ] Audit e activity log sono distinti.

---

## Riferimenti

- [Audit e activity log](../../architecture/20-audit-activity-log.md)
- [Foundation — Audit](../../foundation/docs/06-audit.md)
- [Sicurezza](../../rules/security.md) · [Logging](../../rules/logging.md)
- [Checklist di sicurezza](../../checklists/security-checklist.md)
