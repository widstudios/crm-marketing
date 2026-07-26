# Moduli

> Il catalogo delle funzionalità riutilizzabili, e il criterio per cui qualcosa è un modulo invece
> di essere parte del software.

---

## Indice

1. [Descrizione](#descrizione)
2. [Che cos'è un modulo](#che-cosè-un-modulo)
3. [Moduli di base e moduli opzionali](#moduli-di-base-e-moduli-opzionali)
4. [Il catalogo](#il-catalogo)
5. [Attivare un modulo](#attivare-un-modulo)
6. [Costruire un modulo nuovo](#costruire-un-modulo-nuovo)
7. [Esempi](#esempi)
8. [Best practice](#best-practice)
9. [Errori comuni](#errori-comuni)
10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

I moduli sono la risposta a una tensione concreta: ogni gestionale ha bisogno di documenti,
notifiche, tracciamento e reportistica, ma **non tutti ne hanno bisogno allo stesso modo** e
qualcuno non ne ha bisogno affatto.

Mettere tutto nella Foundation significherebbe imporre a ogni progetto le tabelle e la complessità
di funzionalità che non usa. Lasciare tutto ai progetti significherebbe riscrivere ogni volta la
stessa gestione documentale, in una forma leggermente diversa.

Il modulo è la via di mezzo, ed è verificabile: **un modulo è una funzionalità che si può togliere.**

Decisione di riferimento: [ADR-0004](../architecture/decisions/0004-modular-system.md).

---

## Che cos'è un modulo

Non è una cartella con dentro del codice correlato. È un'unità la cui rimozione lascia il software
funzionante, salvo per ciò che il modulo forniva.

La differenza è misurabile, ed è l'unica definizione utile:

```bash
php artisan module:disable documents
composer test
```

La suite deve restare **verde**, salvo i test del modulo stesso. Se fallisce qualcos'altro, esiste
una dipendenza non dichiarata: qualcuno usa il modulo senza che il modulo lo sappia. In quel caso
non era un modulo, era una cartella.

Questa verifica gira in pipeline, un modulo per volta. Farla a mano significa non farla.

---

## Moduli di base e moduli opzionali

Il catalogo contiene due categorie, e la distinzione conta.

### Moduli di base

Sono **sempre attivi** e non disattivabili: senza di loro il software non esiste. Compaiono nel
catalogo perché un progetto deve sapere che cosa gli forniscono — tabelle, permessi, comandi,
eventi — non perché si possano togliere.

| Modulo | Fornisce |
|---|---|
| [tenancy](catalog/tenancy.md) | tenant, domini, provisioning, ciclo di vita |
| [auth](catalog/auth.md) | autenticazione, ruoli, permessi, secondo fattore |

Sono nel catalogo e non nella Foundation per una ragione precisa: hanno **tabelle e migration
proprie**, e la Foundation non impone tabelle a nessuno. La Foundation definisce i contratti; questi
moduli forniscono l'implementazione predefinita che quasi tutti i progetti useranno.

### Moduli opzionali

Si attivano in configurazione, e si possono disattivare.

| Modulo | Quando serve |
|---|---|
| [audit](catalog/audit.md) | requisiti di conformità o tracciabilità |
| [documents](catalog/documents.md) | il software gestisce file dei clienti |
| [notifications](catalog/notifications.md) | comunicazioni multicanale con preferenze |
| [cms](catalog/cms.md) | landing page e contenuti editoriali |
| [reporting](catalog/reporting.md) | report pianificati ed esportazioni |

---

## Il catalogo

| Modulo | Categoria | Dipende da | Descrizione |
|---|---|---|---|
| [tenancy](catalog/tenancy.md) | base | — | Tenant, domini, provisioning, sospensione, archiviazione |
| [auth](catalog/auth.md) | base | tenancy | Guardie separate, ruoli, permessi, 2FA, token API |
| [audit](catalog/audit.md) | opzionale | — | Registro immutabile delle mutazioni sensibili |
| [documents](catalog/documents.md) | opzionale | — | Archiviazione, versioni, URL firmati, antivirus |
| [notifications](catalog/notifications.md) | opzionale | — | Posta, banca dati, canali esterni, preferenze |
| [cms](catalog/cms.md) | opzionale | documents | Pagine, sezioni, media, SEO, landing per tenant |
| [reporting](catalog/reporting.md) | opzionale | documents | Report pianificati, esportazioni asincrone |

Ogni scheda documenta: che cosa fornisce, che cosa **non** fa, tabelle, permessi, comandi, eventi,
configurazione, punti di integrazione e checklist di adozione.

---

## Attivare un modulo

```php
// config/foundation.php
'modules' => [
    'enabled' => ['tenancy', 'auth', 'audit', 'documents'],
],
```

```bash
php artisan tenants:migrate                     # migration del modulo
php artisan db:seed --class=System\\PermissionSeeder
```

Un modulo presente ma non elencato è **registrato e inattivo**: il registro lo conosce, ma il suo
provider non carica nulla — niente rotte, niente traduzioni, niente migration. È la condizione che
rende verificabile l'indipendenza.

Le dipendenze non soddisfatte fermano l'applicazione **all'avvio**, con un messaggio che dice cosa
fare:

```
Il modulo 'reporting' richiede 'documents', che non risulta attivo.
Aggiungerlo a config('foundation.modules.enabled') o disattivare 'reporting'.
```

All'avvio, e non a runtime, perché è il momento in cui il messaggio può ancora essere utile.

---

## Costruire un modulo nuovo

Il criterio di ammissione è lo stesso della Foundation, con un'aggiunta:

> «Questa funzionalità servirebbe **identica** in almeno tre software diversi, e si può **togliere**
> senza rompere gli altri due?»

Se la risposta alla prima parte è sì e alla seconda no, il posto giusto è la Foundation. Se è no
alla prima, il posto giusto è il progetto.

Il percorso è in [`_blueprint/README.md`](_blueprint/README.md), e il processo completo in
[`workflows/20-module-workflow.md`](../workflows/20-module-workflow.md).

---

## Esempi

### Uso opzionale dal codice applicativo

```php
// ✓ Il codice funziona con e senza il modulo
if ($this->modules->isEnabled('notifications')) {
    NotifyExpiringBatchesJob::dispatch()->afterCommit();
}
```

Un `if` di questo tipo è ammesso e utile. Ciò che non è ammesso è usare le classi di un modulo senza
verificarne l'attivazione: il codice funziona finché il modulo c'è, e si rompe con un errore di
classe non trovata il giorno in cui qualcuno lo disattiva.

### Integrazione tramite contratto

```php
// La Foundation definisce il contratto
interface AuditLogger { public function record(AuditEntry $entry): void; }

// Il modulo lo implementa
$this->app->bind(AuditLogger::class, DatabaseAuditLogger::class);

// Senza il modulo, l'implementazione è quella nulla
'audit' => ['logger' => NullAuditLogger::class],
```

Il codice applicativo invoca sempre il contratto e non cambia mai. È la forma corretta di
integrazione tra un modulo e il resto: il modulo **fornisce** un'implementazione, non impone la
propria presenza.

---

## Best practice

- Verificare l'indipendenza con `module:disable`, non presumerla.
- Tenere le dipendenze tra moduli al minimo: una dipendenza costa più di una duplicazione.
- Integrare tramite contratti definiti dalla Foundation, non tramite classi concrete del modulo.
- Dichiarare tutti i permessi nel modulo: sono l'unico modo perché la funzionalità sia visibile.
- Attivare solo i moduli che servono: ogni modulo attivo è tabelle, migration e superficie in più.
- Prima di creare un modulo, verificare che non esista già in forma leggermente diversa.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Modulo che non si può disattivare | Non è un modulo, ed è chiamato così | Verificare con `module:disable` |
| Registrazioni fuori da `bootModule()` | «Disattivato» non significa nulla | Tutto dentro `bootModule()` |
| Dipendenza non dichiarata | Errore incomprensibile a runtime | Dichiararla in `dependsOn` |
| Dipendenze circolari | Ordine di caricamento impossibile | Grafo aciclico |
| Classi del modulo usate senza verifica | Errore di classe non trovata alla disattivazione | `isEnabled()` |
| Permessi non dichiarati | Funzionalità invisibile dopo il deploy | Elenco nel modulo |
| Logica verticale in un modulo del catalogo | Contamina tutti i progetti | Nel progetto |
| Troppe dipendenze tra moduli | Monolite con le cartelle ordinate | Ridurre l'accoppiamento |
| Modulo attivato «per sicurezza» | Tabelle e superficie che nessuno usa | Solo ciò che serve |

---

## Checklist

- [ ] Ho verificato che il modulo esista già prima di proporne uno nuovo.
- [ ] Il modulo dichiara nome, versione, descrizione, dipendenze e permessi.
- [ ] Le registrazioni stanno tutte dentro `bootModule()`.
- [ ] `module:disable` seguito da `composer test` lascia la suite verde.
- [ ] La verifica di indipendenza è in pipeline.
- [ ] L'integrazione avviene tramite contratti, non classi concrete.
- [ ] La scheda di catalogo è aggiornata e collegata da questo indice.

---

## Riferimenti

- [ADR-0004](../architecture/decisions/0004-modular-system.md)
- [Sistema modulare](../architecture/10-modular-system.md) · [Contratto di modulo](../architecture/11-module-contract.md)
- [Blueprint](_blueprint/README.md) · [Workflow di modulo](../workflows/20-module-workflow.md)
- [Foundation — Moduli](../foundation/docs/05-moduli.md)
- [Primo modulo](../docs/01-getting-started/03-first-module.md)
