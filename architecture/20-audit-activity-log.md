# Audit e activity log

> Tracciabilità: cosa si registra, con quale immutabilità, per quanto tempo, e come si distingue
> l'audit normativo dal registro delle attività.

---

## Indice

1. [Descrizione](#descrizione)
2. [Due registri distinti](#due-registri-distinti)
3. [Audit log](#audit-log)
4. [Activity log](#activity-log)
5. [Immutabilità](#immutabilità)
6. [Cosa non si registra](#cosa-non-si-registra)
7. [Conservazione](#conservazione)
8. [Consultazione](#consultazione)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Nei domini in cui operiamo la tracciabilità è un **requisito normativo**, non una comodità
diagnostica. Un magazzino sanitario deve poter dimostrare chi ha movimentato un lotto e quando; un
CAF deve poter ricostruire chi ha modificato una pratica.

Requisito normativo significa: il registro deve essere completo, immutabile e conservato per il
periodo previsto, e la sua assenza è una non conformità.

---

## Due registri distinti

| | Audit log | Activity log |
|---|---|---|
| Registra | modifiche ai **dati** | **azioni** degli utenti |
| Contenuto | valori prima e dopo | evento e contesto |
| Origine | automatica, dal model | esplicita, dal codice |
| Immutabile | **sì** | sì |
| Conservazione | secondo norma (fino a 10 anni) | 12-24 mesi |
| Scopo | conformità, contestazioni | comprensione del comportamento |
| Esempio | «quantità: 100 → 90» | «ha esportato l'elenco movimenti» |

Un'azione che non modifica dati (una consultazione, un'esportazione, un accesso) non produce audit
ma produce activity. Una modifica produce entrambi.

---

## Audit log

```php
final class Batch extends Model
{
    use Auditable;

    protected array $auditExclude = ['updated_at', 'search_vector'];
}
```

Struttura del record:

| Campo | Contenuto |
|---|---|
| `auditable_type`, `auditable_id` | entità interessata |
| `event` | `created`, `updated`, `deleted`, `restored` |
| `old_values` | valori precedenti (solo i campi cambiati) |
| `new_values` | valori successivi |
| `user_id`, `user_type` | autore |
| `ip_address` | origine |
| `url` | percorso della richiesta |
| `created_at` | momento |
| `context` | dati aggiuntivi (motivo, riferimento, origine) |

Il campo `context` è ciò che rende l'audit **utile** invece che solo completo: sapere che la
quantità è passata da 100 a 90 dice poco; sapere che è avvenuto per «rettifica inventariale del
25/07, verbale 12» dice tutto.

```php
audit()->withContext(['reason' => 'Rettifica inventariale', 'document' => 'VRB-12'])
    ->perform(fn () => $batch->update(['quantity' => 90]));
```

L'audit vive nel database del **tenant**: riguarda i dati del cliente.

---

## Activity log

```php
activity()
    ->performedOn($batch)
    ->causedBy($user)
    ->withProperties(['days' => 30, 'format' => 'xlsx'])
    ->log('batch.expiry_report_exported');
```

Cosa registrare:

| Categoria | Esempi |
|---|---|
| Accessi | login, logout, tentativi falliti, cambio password |
| Consultazioni sensibili | apertura di un documento riservato, ricerca su dati personali |
| Esportazioni | qualsiasi estrazione di dati |
| Condivisioni | generazione di URL firmati |
| Operazioni amministrative | creazione utenti, modifica ruoli e permessi |
| Configurazione | modifiche alle impostazioni del tenant |
| Accessi di assistenza | ogni azione del personale WidStudios |

Le esportazioni sono la categoria più importante: sono il modo in cui i dati escono dal sistema, e
spesso l'unico modo in cui un abuso interno diventa rilevabile.

---

## Immutabilità

L'audit log non è modificabile da alcun percorso applicativo.

| Livello | Misura |
|---|---|
| Applicativo | nessuna Action modifica o cancella audit |
| Model | `updating` e `deleting` sollevano eccezione |
| Database | permessi dell'utente applicativo limitati a `INSERT` e `SELECT` |
| Backup | inclusi in ogni backup del tenant |
| Verifica | catena di checksum sui record consecutivi |

```php
final class AuditLog extends Model
{
    protected static function booted(): void
    {
        static::updating(fn () => throw new AuditLogImmutable());
        static::deleting(fn () => throw new AuditLogImmutable());
    }
}
```

La rimozione avviene solo per **scadenza della conservazione**, tramite un processo dedicato che
opera a livello di database e registra l'operazione.

---

## Cosa non si registra

| Mai | Motivo |
|---|---|
| Password, anche cifrate | nessun uso legittimo |
| Token e chiavi API | credenziali |
| Numeri di carta | conformità PCI |
| Contenuto integrale dei documenti | volume e riservatezza |
| Dati sanitari nel `context` | minimizzazione |
| Query complete con parametri | possono contenere dati personali |

Il principio è la **minimizzazione**: si registra ciò che serve a rispondere alla domanda «chi ha
fatto cosa, quando», non tutto ciò che è tecnicamente disponibile.

---

## Conservazione

| Registro | Conservazione | Base |
|---|---|---|
| Audit su dati sanitari | 10 anni | normativa di settore |
| Audit su dati fiscali | 10 anni | normativa fiscale |
| Audit su dati generici | 5 anni | prassi contrattuale |
| Activity log | 24 mesi | esigenza operativa |
| Log di accesso | 12 mesi | sicurezza |

La conservazione si configura per progetto in `config/audit.php`: dipende dal dominio, e va
verificata con il committente, non decisa dal team tecnico.

Dopo la dismissione di un tenant, l'audit sopravvive in un archivio separato per il periodo
previsto: è un obbligo che sopravvive al rapporto commerciale.

---

## Consultazione

| Utente | Vede | Dove |
|---|---|---|
| Tenant Admin | tutto l'audit del proprio tenant | pannello Tenant Admin |
| Utente | le proprie azioni | profilo personale |
| Super Admin | audit di piattaforma, non quello dei tenant | pannello Super Admin |
| Assistenza | audit del tenant solo durante un accesso autorizzato | tracciato |

Il Super Admin **non** ha accesso libero all'audit dei tenant: quei dati appartengono al cliente.
L'accesso avviene tramite la procedura tracciata, e la consultazione stessa produce una voce
nell'audit del tenant.

Funzionalità necessarie: filtro per entità, per utente, per periodo, per tipo di evento;
esportazione firmata per uso probatorio.

---

## Esempi

### Esempio 1 — audit che risponde a una contestazione

Un cliente contesta una giacenza. L'audit ricostruisce:

```
2026-07-20 09:14  quantity: 100 → 90   utente: m.rossi   contesto: prelievo ODL-4821
2026-07-22 16:02  quantity: 90 → 85    utente: g.bianchi contesto: prelievo ODL-4890
2026-07-25 11:30  quantity: 85 → 100   utente: a.verdi   contesto: rettifica inventariale VRB-12
```

Senza il campo `context`, la terza riga sarebbe inspiegabile.

### Esempio 2 — activity che rileva un comportamento anomalo

```
2026-07-25 02:14  m.rossi  export.movements  {"rows": 45000, "format": "csv"}
2026-07-25 02:19  m.rossi  export.suppliers  {"rows": 1200, "format": "csv"}
2026-07-25 02:23  m.rossi  export.articles   {"rows": 8900, "format": "csv"}
```

Tre esportazioni massive alle due di notte da un utente che opera normalmente in orario diurno.
L'audit non lo avrebbe rilevato: nessun dato è stato modificato.

---

## Best practice

- Distinguere audit (dati) da activity (azioni).
- Usare il campo `context` per il motivo dell'operazione.
- Registrare **sempre** le esportazioni.
- Immutabilità a più livelli, database compreso.
- Conservazione decisa con il committente, non dal team.
- Audit in coda quando il volume di scritture è alto.
- Rendere l'audit consultabile dal Tenant Admin.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Un solo registro per tutto | Ricerche impossibili, conservazione errata | Due registri distinti |
| Audit modificabile | Valore probatorio nullo | Immutabilità a più livelli |
| Nessun contesto sulle modifiche | Registro completo ma inutile | Campo `context` |
| Esportazioni non registrate | Le fughe di dati non lasciano traccia | Activity obbligatoria |
| Dati sensibili nel registro | Il registro diventa un rischio | Minimizzazione |
| Audit sincrono su alto volume | Scritture rallentate | Esecuzione in coda |
| Audit non consultabile dal cliente | Il cliente non può verificare | Pannello dedicato |
| Conservazione decisa dal team | Non conformità normativa | Verifica con il committente |

---

## Checklist

- [ ] Audit e activity log sono distinti.
- [ ] Le entità sensibili sono marcate come `Auditable`.
- [ ] L'audit registra valori precedenti e successivi, autore, momento, contesto.
- [ ] L'audit è immutabile a livello applicativo e di database.
- [ ] Le esportazioni producono una voce di activity.
- [ ] Nessun dato sensibile finisce nei registri.
- [ ] La conservazione è configurata secondo il dominio.
- [ ] Il Tenant Admin può consultare ed esportare l'audit.
- [ ] Gli accessi di assistenza sono registrati nell'audit del tenant.

---

## Riferimenti

- [Regole di logging](../rules/logging.md) · [Sicurezza](../rules/security.md)
- [Autenticazione](08-authentication.md)
- [Modulo audit](../modules/catalog/audit.md)
- [Guida alla sicurezza](../docs/04-quality/05-security-guide.md)
