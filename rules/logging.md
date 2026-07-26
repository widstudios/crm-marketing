# Regole — Logging

> Log strutturati, contesto obbligatorio con il tenant, nessun dato sensibile.

---

## Indice

1. [Descrizione](#descrizione)
2. [Livelli](#livelli)
3. [Formato e contesto](#formato-e-contesto)
4. [Cosa non si registra](#cosa-non-si-registra)
5. [Canali](#canali)
6. [Conservazione](#conservazione)
7. [Esempi](#esempi)
8. [Best practice](#best-practice)
9. [Errori comuni](#errori-comuni)
10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

I log servono a rispondere, dopo, alla domanda «cosa è successo». In un contesto multitenant una
riga di log senza l'indicazione del tenant non risponde a nulla: «errore nell'elenco dei lotti» non
dice quale cliente è coinvolto.

---

## Livelli

| Livello | Uso | Esempio |
|---|---|---|
| `emergency` | sistema inutilizzabile | database irraggiungibile |
| `alert` | intervento immediato | tutti i job falliscono |
| `critical` | condizione critica | integrazione di pagamento non risponde |
| `error` | errore gestito | job fallito dopo tutti i tentativi |
| `warning` | anomalia non bloccante | tentativo di accesso non autorizzato |
| `notice` | evento notevole | tenant creato |
| `info` | evento ordinario | movimento registrato |
| `debug` | diagnostica | solo locale e staging |

**R1.** In produzione si registra da `info` in su su file, da `warning` in su sul servizio esterno.
*Verifica:* configurazione.

**R2.** `debug` non è attivo in produzione.
*Verifica:* configurazione.

**R3.** Il livello riflette l'**azione richiesta**, non la gravità percepita: `error` significa che
qualcuno deve intervenire.
*Verifica:* revisione.

---

## Formato e contesto

**R4.** I log sono **strutturati** (JSON), non testo libero.
*Motivo:* sono destinati a essere interrogati. *Verifica:* configurazione.

**R5.** Ogni riga di log applicativo porta il contesto obbligatorio.

```php
Log::withContext([
    'tenant' => tenant()?->slug,
    'request_id' => $requestId,
    'user_id' => auth()->id(),
    'release' => config('app.release'),
    'environment' => config('app.env'),
]);
```

| Campo | Perché |
|---|---|
| `tenant` | senza, l'errore non è attribuibile |
| `request_id` | correla le righe della stessa richiesta |
| `user_id` | chi ha compiuto l'azione |
| `release` | quale versione ha prodotto l'errore |
| `environment` | distingue staging da produzione |

*Verifica:* middleware, revisione. *Livello: vincolante.*

**R6.** Il messaggio è in **inglese**, breve e stabile; i dati variabili stanno nel contesto.

```php
// ✗ Messaggio variabile: impossibile aggregare
Log::info("Movimento {$movement->id} registrato sul lotto {$batch->number}");

// ✓ Messaggio stabile, dati nel contesto
Log::info('Movement registered', ['movement_id' => $movement->id, 'batch_id' => $batch->id]);
```

*Motivo:* i messaggi stabili si possono contare e confrontare nel tempo.
*Verifica:* revisione.

**R7.** Le eccezioni si registrano con il loro contesto, non solo con il messaggio.
*Verifica:* revisione.

---

## Cosa non si registra

| Mai | Motivo |
|---|---|
| Password, anche cifrate | nessun uso legittimo |
| Token, chiavi, segreti | credenziali |
| Numeri di carta | conformità |
| Dati sanitari | minimizzazione, normativa |
| Codici fiscali e dati personali non necessari | minimizzazione |
| Contenuto di documenti | volume e riservatezza |
| Corpo completo delle richieste | contiene tutto quanto sopra |

**R8.** `Log::info($request->all())` è vietato.
*Verifica:* ricerca in CI. *Livello: assoluto.*

**R9.** I campi sensibili si mascherano nel contesto, quando il loro nome è necessario alla
diagnosi.
*Verifica:* revisione.

---

## Canali

| Canale | Contenuto | Conservazione |
|---|---|---|
| `daily` | log applicativo | 30 giorni |
| `errors` | da `error` in su | 90 giorni |
| `audit` | audit e activity log | secondo norma |
| `security` | accessi, tentativi falliti, cambi di privilegio | 12 mesi |
| `external` | servizio di aggregazione | secondo il servizio |

**R10.** L'audit **non** passa dai canali di log ordinari: ha un archivio dedicato e immutabile.
*Verifica:* configurazione.

---

## Conservazione

**R11.** Ogni canale ha una politica di conservazione dichiarata e applicata automaticamente.
*Motivo:* conservare indefinitamente è un costo e un rischio sulla privacy.
*Verifica:* configurazione, verifica periodica.

---

## Esempi

### Esempio 1 — conforme

```php
try {
    $movement = $action->execute($data);

    Log::info('Movement registered', [
        'movement_id' => $movement->id,
        'batch_id' => $movement->batch_id,
        'type' => $movement->type->value,
    ]);
} catch (InsufficientStock $e) {
    Log::warning('Movement rejected: insufficient stock', [
        'batch_id' => $data->batchId,
        'requested' => $data->quantity,
    ]);

    throw $e;
}
```

### Esempio 2 — violazioni

```php
Log::info('Richiesta ricevuta', $request->all());          // ✗ R8
Log::error("Errore per l'utente {$user->email}");          // ✗ R6, dato personale
Log::debug('Token: ' . $user->api_token);                  // ✗ segreto nei log
Log::info('Operazione completata');                        // nessun contesto utile
```

---

## Best practice

- Impostare il contesto una volta in un middleware, non ripeterlo ad ogni chiamata.
- Messaggi stabili e dati nel contesto: permette di contare le occorrenze.
- Registrare i rifiuti (validazione, autorizzazione) a livello `warning`: rivelano usi impropri.
- Verificare periodicamente che nei log non finiscano dati sensibili.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Log senza `tenant` | Errori non attribuibili | Contesto obbligatorio |
| `Log::info($request->all())` | Dati personali e segreti nei log | Log selettivi |
| Messaggi con dati interpolati | Impossibile aggregare | Messaggio stabile + contesto |
| Log in testo libero | Non interrogabili | JSON |
| `debug` attivo in produzione | Volume enorme, dati sensibili | Solo locale e staging |
| Audit nei log ordinari | Non immutabile, conservazione errata | Archivio dedicato |
| Nessuna politica di conservazione | Costi e rischi crescenti | Politica per canale |

---

## Checklist

- [ ] Log in formato strutturato.
- [ ] Contesto con `tenant`, `request_id`, `user_id`, `release`, `environment`.
- [ ] Messaggi stabili in inglese, dati variabili nel contesto.
- [ ] Nessun dato sensibile registrato.
- [ ] `debug` disattivo in produzione.
- [ ] Canali separati per applicativo, errori, audit, sicurezza.
- [ ] Audit su archivio dedicato e immutabile.
- [ ] Politica di conservazione applicata per canale.

---

## Riferimenti

- [Osservabilità](../architecture/25-observability.md) · [Audit e activity log](../architecture/20-audit-activity-log.md)
- [Monitoraggio e log](../docs/05-operations/03-monitoring-and-logging.md)
- [Sicurezza](security.md)
