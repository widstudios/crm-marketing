# Monitoraggio e log

> Cosa si osserva, cosa si registra, cosa fa scattare un allarme, e come si mantiene tutto questo
> utile invece che rumoroso.

---

## Indice

1. [Descrizione](#descrizione)
2. [I quattro segnali](#i-quattro-segnali)
3. [Metriche per livello](#metriche-per-livello)
4. [Logging](#logging)
5. [Contesto obbligatorio](#contesto-obbligatorio)
6. [Allarmi](#allarmi)
7. [Conservazione](#conservazione)
8. [Osservabilità multitenant](#osservabilità-multitenant)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Il monitoraggio serve a due cose: **accorgersi di un problema prima del cliente** e **capire cosa
è successo dopo**. Tutto ciò che non serve a uno di questi due scopi è rumore, e il rumore rende
inutile il resto.

Il criterio per ogni metrica e ogni allarme: *se scatta, qualcuno sa cosa fare?* Se la risposta è
no, va rimosso o riformulato.

---

## I quattro segnali

| Segnale | Domanda | Metrica principale | Soglia |
|---|---|---|---|
| **Latenza** | quanto è lento? | tempo di risposta, mediana e 95° percentile | > 500 ms / > 2 s |
| **Traffico** | quanto è usato? | richieste al minuto, per tenant | variazione > 3× rispetto alla norma |
| **Errori** | quanto fallisce? | tasso di errori 5xx | > 1% |
| **Saturazione** | quanto è pieno? | CPU, memoria, connessioni, coda | > 80% |

Con questi quattro si diagnostica la maggior parte degli incidenti. Le metriche di dominio si
aggiungono dopo, non prima.

---

## Metriche per livello

| Livello | Metriche |
|---|---|
| Infrastruttura | CPU, memoria, disco, rete, container attivi |
| Database | connessioni attive, query lente, lock, dimensione, ritardo di replica |
| Redis | memoria, tasso di hit, chiavi rimosse per limite |
| Code | job in attesa, job falliti, durata media, worker attivi |
| Applicazione | tempo di risposta, tasso di errore, query per richiesta |
| Business | tenant attivi, utenti collegati, operazioni al minuto |

Le metriche di business sono spesso le prime a segnalare un problema: un crollo delle operazioni
registrate significa che qualcosa non funziona, anche se tecnicamente tutto risponde.

---

## Logging

| Livello | Uso | Esempio |
|---|---|---|
| `emergency` | sistema inutilizzabile | database irraggiungibile |
| `alert` | intervento immediato | tutti i job falliscono |
| `critical` | condizione critica | integrazione di pagamento non risponde |
| `error` | errore gestito | job fallito dopo tutti i tentativi |
| `warning` | anomalia non bloccante | tentativo di accesso non autorizzato |
| `notice` | evento notevole | tenant creato |
| `info` | evento ordinario | movimento registrato |
| `debug` | diagnostica | solo in locale e staging |

In produzione si registra da `warning` in su sul servizio esterno, da `info` in su su file.

**Mai nei log**: password, token, dati sanitari, codici fiscali, contenuti di documenti, corpi di
richiesta completi.

```php
// ✗ Registra tutto, compresi dati personali e segreti
Log::info('Richiesta', $request->all());

// ✓ Solo ciò che serve
Log::info('Movimento registrato', [
    'movement_id' => $movement->id,
    'batch_id' => $movement->batch_id,
    'quantity' => $movement->quantity,
]);
```

---

## Contesto obbligatorio

Ogni riga di log applicativo porta con sé:

| Campo | Perché |
|---|---|
| `tenant` | senza, l'errore non è attribuibile a nessuno |
| `request_id` | correla le righe della stessa richiesta |
| `user_id` | chi ha compiuto l'azione |
| `environment` | distingue staging da produzione |
| `release` | quale versione ha prodotto l'errore |

Si imposta una volta, in un middleware:

```php
Log::withContext([
    'tenant' => tenant()?->id,
    'request_id' => $request->header('X-Request-Id', Str::uuid()->toString()),
    'user_id' => auth()->id(),
    'release' => config('app.release'),
]);
```

Il campo `tenant` è quello che trasforma un log inutile in un log diagnostico: senza, «errore
nell'elenco dei lotti» non dice a quale cliente si riferisce.

---

## Allarmi

| Condizione | Gravità | Destinatario | Azione attesa |
|---|---|---|---|
| Applicazione non risponde | critica | reperibile, immediato | intervento entro 15 min |
| Tasso di errore > 5% | critica | reperibile, immediato | diagnosi immediata |
| Database irraggiungibile | critica | reperibile, immediato | intervento immediato |
| Tasso di errore > 1% | alta | canale del team | diagnosi entro 1 h |
| Coda oltre 1.000 in attesa | alta | canale del team | verifica dei worker |
| Job falliti > 50 in un'ora | alta | canale del team | analisi |
| Disco oltre l'85% | media | canale del team | pianificare |
| Certificato in scadenza < 14 giorni | media | canale del team | rinnovo |
| Backup fallito | **critica** | reperibile | ripetere e verificare |
| Violazione di isolamento tenant | **critica** | reperibile + responsabile | blocco e analisi |

Regola contro il rumore: un allarme che scatta più di una volta a settimana senza richiedere
azione va **corretto o rimosso**. Gli allarmi ignorati per abitudine sono peggio della loro
assenza, perché danno l'illusione della copertura.

---

## Conservazione

| Dato | Conservazione | Motivo |
|---|---|---|
| Log applicativi | 30 giorni | diagnosi |
| Log di errore | 90 giorni | analisi di ricorrenza |
| Log di accesso | 12 mesi | sicurezza |
| Activity log | 12-24 mesi | tracciabilità funzionale |
| Audit log | secondo norma (fino a 10 anni) | obbligo legale |
| Metriche ad alta risoluzione | 15 giorni | costo |
| Metriche aggregate | 13 mesi | confronto anno su anno |

---

## Osservabilità multitenant

| Aspetto | Regola |
|---|---|
| Metriche | raccolte per tenant, oltre che aggregate |
| Allarmi | possono scattare per un singolo tenant |
| Log | sempre con il campo `tenant` |
| Dashboard | filtrabili per tenant |
| Anomalie | confrontate con la norma **di quel tenant** |

La media aggregata nasconde il caso peggiore: se un tenant su cinquanta ha tempi di risposta
decuplicati, la media resta accettabile e quel cliente è fermo.

---

## Esempi

### Esempio 1 — l'allarme che ha funzionato

Alle 03:00 il job notturno di ricalcolo fallisce su un tenant per esaurimento di memoria.
L'allarme «job falliti» scatta, il reperibile verifica, aumenta il limite per quella coda e
riesegue. Alle 08:00 gli utenti trovano i dati corretti.

Senza l'allarme, il problema sarebbe emerso da una segnalazione a metà mattina, dopo che diversi
utenti avevano già lavorato su dati incompleti.

### Esempio 2 — l'allarme da correggere

«Tempo di risposta > 500 ms» scatta ogni notte durante il backup, da mesi. Nessuno interviene
più, e l'allarme viene ignorato anche quando è reale.

Correzione: sospensione dell'allarme nella finestra di backup, oppure spostamento del backup su
una replica.

---

## Best practice

- Quattro segnali prima delle metriche di dominio.
- Contesto obbligatorio in ogni log, `tenant` incluso.
- Un allarme, un'azione attesa.
- Correggere o rimuovere gli allarmi rumorosi.
- Metriche anche per tenant, non solo aggregate.
- Verificare periodicamente che gli allarmi funzionino davvero.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Log senza `tenant` | Errori non attribuibili | Contesto obbligatorio |
| Registrare l'intera richiesta | Dati personali e segreti nei log | Log selettivi |
| Allarmi rumorosi | Vengono ignorati, anche quando reali | Correggere o rimuovere |
| Solo metriche aggregate | Il tenant in difficoltà è invisibile | Metriche per tenant |
| Nessun allarme sul backup | Si scopre il problema quando serve il ripristino | Allarme critico |
| Telescope in produzione | Dati sensibili, disco pieno | Disabilitato |
| Conservazione indefinita | Costi e obblighi sulla privacy | Politica per tipo |

---

## Checklist

- [ ] I quattro segnali sono monitorati.
- [ ] Ogni log applicativo ha `tenant`, `request_id`, `user_id`, `release`.
- [ ] Nessun dato sensibile finisce nei log.
- [ ] Ogni allarme ha un destinatario e un'azione attesa.
- [ ] Gli allarmi rumorosi sono stati corretti o rimossi.
- [ ] Le metriche sono disponibili per singolo tenant.
- [ ] La politica di conservazione è applicata.
- [ ] Il backup ha un allarme critico in caso di fallimento.

---

## Riferimenti

- [Regole di logging](../../rules/logging.md)
- [Osservabilità](../../architecture/25-observability.md)
- [Gestione degli incidenti](05-incident-management.md)
- [Guida al debug](../03-development/07-debugging-guide.md)
- [Runbook operativi](../../deployment/runbooks/README.md)
