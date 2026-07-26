# Regole — Configurazione

> `env()` solo in `config/`, default sicuri, nessun segreto nel repository.

---

## Indice

1. [Descrizione](#descrizione)
2. [Regole](#regole)
3. [Struttura](#struttura)
4. [Segreti](#segreti)
5. [Configurazione per tenant](#configurazione-per-tenant)
6. [Esempi](#esempi)
7. [Best practice](#best-practice)
8. [Errori comuni](#errori-comuni)
9. [Checklist](#checklist)
10. [Riferimenti](#riferimenti)

---

## Descrizione

La configurazione sbagliata è la causa più comune di comportamenti diversi tra sviluppo e
produzione. Due regole eliminano la maggior parte dei casi: `env()` solo in `config/`, e default
sicuri.

---

## Regole

**R1.** `env()` si usa **solo** dentro `config/`.
*Motivo:* in produzione `config:cache` è sempre attivo, e `env()` altrove ritorna `null`. Il difetto
è silenzioso: la funzionalità risulta semplicemente disattivata.
*Verifica:* ricerca in CI. *Livello: assoluto.*

**R2.** Ogni chiave di configurazione ha un **default sicuro**.
*Motivo:* l'assenza di configurazione non deve allentare nulla.
*Verifica:* revisione.

**R3.** Ogni nuova variabile d'ambiente si aggiunge a `.env.example` nello stesso commit, con un
valore segnaposto evidente.
*Verifica:* script di confronto in CI.

**R4.** Nessun segreto nel repository, nemmeno di esempio realistico.
*Verifica:* scansione dei segreti in CI. *Livello: assoluto.*

**R5.** La configurazione si legge con `config()`, mai accedendo ai file direttamente.
*Verifica:* revisione.

**R6.** Nessuna configurazione modificata a runtime, salvo i bootstrapper della tenancy.
*Motivo:* una configurazione che cambia durante la richiesta rende il comportamento imprevedibile.
*Verifica:* revisione.

**R7.** I valori booleani nell'ambiente si scrivono `true`/`false`, non `1`/`0` o `"yes"`.
*Verifica:* revisione.

**R8.** La configurazione è **documentata**: ogni chiave introdotta dalla Foundation compare nel
riferimento.
*Verifica:* revisione del riferimento.

---

## Struttura

| File | Contenuto |
|---|---|
| `config/tenancy.php` | connessioni, resolver, bootstrapper, provisioning, dismissione |
| `config/modules.php` | moduli attivi e ordine di caricamento |
| `config/permission.php` | ruoli e permessi |
| `config/audit.php` | entità sottoposte ad audit, conservazione, canale |
| `config/factory.php` | versione della Factory, convenzioni, soglie di qualità |
| `config/services.php` | integrazioni esterne |

**R9.** Le configurazioni di progetto seguono la stessa struttura di quelle della Foundation.
*Verifica:* revisione.

---

## Segreti

| Tipo | Dove |
|---|---|
| Credenziali di database | variabili d'ambiente |
| Chiavi API | variabili d'ambiente o gestore di segreti |
| Chiave applicativa | variabile d'ambiente, diversa per ambiente |
| Certificati | volume montato, mai nel repository |
| Segreti dei webhook | gestore di segreti |

**R10.** La chiave applicativa è **diversa** in ogni ambiente.
*Motivo:* con la stessa chiave, i dati cifrati di un ambiente sono leggibili in un altro.
*Verifica:* verifica di configurazione.

**R11.** I segreti si ruotano dopo ogni esposizione, anche sospetta.
*Verifica:* procedura di incidente.

**R12.** I segreti non compaiono nei log né nei messaggi di errore.
*Verifica:* revisione, scansione dei log.

---

## Configurazione per tenant

Tre livelli, con precedenza crescente:

```
config/ (piattaforma)  →  piano (landlord)  →  impostazioni del tenant (landlord)
                                            →  preferenze applicative (database tenant)
```

**R13.** Le impostazioni per tenant hanno tipo e default dichiarati nel manifesto del modulo.

```json
"settings": {
    "expiry_warning_days": { "type": "integer", "default": 30 },
    "allow_negative_stock": { "type": "boolean", "default": false }
}
```

*Verifica:* validazione del manifesto.

**R14.** I limiti di piano si verificano **nelle Action**, non solo nell'interfaccia.
*Motivo:* un limite verificato solo nell'interfaccia è aggirabile via API.
*Verifica:* test. *Livello: vincolante.*

**R15.** La lettura delle impostazioni del tenant è in cache, con chiave tenant-scoped.
*Verifica:* revisione.

---

## Esempi

### Esempio 1 — configurazione conforme

```php
// config/inventory.php
return [
    'expiry_warning_days' => (int) env('INVENTORY_EXPIRY_WARNING_DAYS', 30),
    'allow_negative_stock' => (bool) env('INVENTORY_ALLOW_NEGATIVE_STOCK', false),
    'batch_number_format' => env('INVENTORY_BATCH_FORMAT', 'LOT-{sequence}'),
];
```

```php
// Uso
if (config('inventory.allow_negative_stock')) { /* … */ }
```

Il default `false` su `allow_negative_stock` è la scelta sicura: chi vuole il comportamento
permissivo deve dichiararlo.

### Esempio 2 — l'errore più comune

```php
// ✗ In un service provider: con config:cache ritorna null, la funzionalità è disattivata
public function register(): void
{
    if (env('FEATURE_EXPIRY_ALERTS')) {
        $this->app->register(ExpiryAlertsProvider::class);
    }
}

// ✓
if (config('inventory.expiry_alerts_enabled')) { /* … */ }
```

Il difetto non produce errori: la funzionalità semplicemente non c'è, in produzione, e in locale
funziona.

---

## Best practice

- Aggiungere la chiave a `config/` e a `.env.example` nello stesso commit del codice che la usa.
- Scegliere il default più restrittivo.
- Verificare `php artisan about` dopo ogni deploy.
- Documentare ogni chiave della Foundation nel riferimento di configurazione.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| `env()` fuori da `config/` | Funzionalità disattivata in produzione | `config()` |
| `.env.example` non aggiornato | Il progetto non si avvia altrove | Aggiornare sempre |
| Default permissivo | Comportamento non sicuro senza configurazione | Default restrittivo |
| Segreto nel repository | Esposizione permanente nella storia | Variabili d'ambiente |
| Stessa chiave applicativa tra ambienti | Dati cifrati leggibili tra ambienti | Chiave per ambiente |
| Configurazione modificata a runtime | Comportamento imprevedibile | Solo bootstrapper tenancy |
| Limiti di piano solo nell'interfaccia | Aggirabili via API | Verifica nell'Action |

---

## Checklist

- [ ] Nessun `env()` fuori da `config/`.
- [ ] Ogni chiave ha un default sicuro.
- [ ] `.env.example` allineato, con segnaposto evidenti.
- [ ] Nessun segreto nel repository.
- [ ] Chiave applicativa diversa per ambiente.
- [ ] Nessuna modifica di configurazione a runtime.
- [ ] Impostazioni per tenant con tipo e default nel manifesto.
- [ ] Limiti di piano verificati nelle Action.
- [ ] Chiavi della Foundation documentate nel riferimento.

---

## Riferimenti

- [Riferimento della configurazione](../docs/06-reference/04-configuration-reference.md)
- [Laravel](laravel.md) · [Sicurezza](security.md) · [Cache](cache.md)
- [Ambienti](../docs/05-operations/01-environments.md)
