# Modulo — notifications

> Comunicare con le persone senza che smettano di leggere.

| | |
|---|---|
| **Nome** | `notifications` |
| **Categoria** | opzionale |
| **Dipende da** | — |
| **Contratti implementati** | `NotificationDispatcher` |

---

## Indice

1. [Descrizione](#descrizione) 2. [Che cosa fornisce](#che-cosa-fornisce)
3. [Che cosa non fa](#che-cosa-non-fa) 4. [Preferenze e canali](#preferenze-e-canali)
5. [Raggruppamento](#raggruppamento) 6. [Configurazione](#configurazione)
7. [Integrazione](#integrazione) 8. [Adozione](#adozione) 9. [Esempi](#esempi)
10. [Best practice](#best-practice) 11. [Errori comuni](#errori-comuni) 12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Laravel sa già inviare notifiche. Questo modulo esiste per ciò che Laravel non fa e che ogni
progetto finisce per riscrivere: le **preferenze del destinatario**, il **raggruppamento** e la
**tracciabilità della consegna**.

Il problema che risolve non è tecnico. Una notifica che arriva troppo spesso, o per qualcosa su cui
il destinatario non può fare nulla, viene ignorata — e con lei vengono ignorate quelle che
contavano. Un sistema di notifiche senza preferenze e senza raggruppamento produce, in pochi mesi,
una regola di posta che sposta tutto in una cartella che nessuno apre.

---

## Che cosa fornisce

### Tabelle — tenant

| Tabella | Contenuto |
|---|---|
| `notifications` | notifiche in banca dati, con stato di lettura |
| `notification_preferences` | scelte per utente e per tipo |
| `notification_deliveries` | esito per canale: inviata, consegnata, fallita |
| `notification_digests` | raggruppamenti in attesa di invio |

### Permessi

| Permesso | Consente |
|---|---|
| `notification.view` | vedere le proprie notifiche |
| `notification.manage_preferences` | modificare le proprie preferenze |
| `notification.broadcast` | inviare comunicazioni a tutti gli utenti del tenant |

L'ultimo è separato di proposito: inviare a tutti è un'operazione che merita un permesso proprio.

### Comandi

| Comando | Quando |
|---|---|
| `notifications:send-digests` | pianificato: invia i raggruppamenti maturi |
| `notifications:prune --older-than=90` | pulizia periodica |
| `notifications:retry-failed` | dopo un disservizio di un canale |

### Eventi

| Evento | Emesso quando |
|---|---|
| `NotificationDelivered` · `NotificationFailed` | esito per canale |
| `NotificationRead` | lettura |

---

## Che cosa non fa

| Non fa | Dove va cercato |
|---|---|
| Decidere **quando** notificare | progetto: è una decisione di dominio |
| Scrivere i testi | progetto: file di traduzione |
| Fornire i canali | integra Laravel; i fornitori esterni si configurano |
| Notifiche push su app native | non previsto: richiede un'app |
| Campagne di marketing | fuori ambito: è uno strumento diverso, con altre regole |
| Chat o messaggistica | fuori ambito |
| Consenso al trattamento | progetto: dipende dalla normativa applicabile |

**Campagne di marketing.** Sembra la stessa cosa e non lo è: una campagna ha segmentazione,
tracciamento delle aperture, gestione delle disiscrizioni e obblighi normativi propri. Usare un
modulo di notifiche operative per fare marketing produce due risultati, entrambi cattivi: le
comunicazioni operative finiscono nella cartella indesiderata insieme alle altre, e gli obblighi
sulle disiscrizioni non vengono rispettati.

---

## Preferenze e canali

| Canale | Predefinito | Note |
|---|---|---|
| `database` | sempre attivo | non disattivabile: è lo storico consultabile |
| `mail` | attivo | rispetta le preferenze |
| `slack`, `webhook` | disattivo | per integrazioni, configurato per tenant |

La preferenza è per **tipo di notifica**, non globale: «non voglio le notifiche di scadenza» è utile,
«non voglio notifiche» significa disattivare anche quelle che servono.

Alcune notifiche sono **non disattivabili**: cambio di password, accesso di assistenza ai dati,
sospensione dell'account. Sono comunicazioni di sicurezza, e la loro disattivazione andrebbe a
vantaggio di chi ha compromesso l'account.

---

## Raggruppamento

Il raggruppamento è la funzionalità che tiene in vita il sistema. Senza, cinquanta lotti in scadenza
producono cinquanta messaggi, e il cinquantunesimo non viene letto.

```
immediata    l'evento richiede azione ora: approvazione, errore bloccante
oraria       eventi frequenti ma non urgenti
giornaliera  riepiloghi: scadenze, attività assegnate
settimanale  andamento, statistiche
```

La scelta è del **destinatario**, per tipo di notifica. Chi riceve dieci notifiche di scadenza al
giorno le vuole raggruppate; chi ne riceve una a settimana le vuole subito.

---

## Configurazione

```php
// config/notifications.php
return [
    'channels' => [
        'database' => ['enabled' => true, 'always' => true],
        'mail' => ['enabled' => true],
        'slack' => ['enabled' => false],
    ],

    'digests' => [
        'enabled' => true,
        'default_frequency' => 'daily',
        'send_at' => '08:00',
        // Sotto questa soglia il raggruppamento non ha senso: si invia subito.
        'min_items' => 3,
    ],

    // Notifiche di sicurezza: le preferenze non si applicano.
    'always_notify' => [
        'security.password_changed',
        'security.platform_access_granted',
        'account.suspended',
    ],

    'retention_days' => 90,

    'rate_limit' => [
        'per_user_per_hour' => 20,
    ],
];
```

---

## Integrazione

```php
if ($this->modules->isEnabled('notifications')) {
    $user->notify(new BatchExpiringNotification($batch->getKey()));
}
```

Oppure, preferibile, attraverso il contratto:

```php
$this->notifier->send($user, 'batch.expiring', ['batch_id' => $batch->getKey()]);
```

La seconda forma non richiede al chiamante di conoscere le classi del modulo, e funziona con un
`NullNotificationDispatcher` quando il modulo non è attivo.

---

## Adozione

```bash
# config/foundation.php → 'modules' => ['enabled' => [..., 'notifications']]

php artisan tenants:migrate
php artisan tenants:artisan "auth:sync-permissions"
```

```php
// routes/console.php
Schedule::command('tenants:artisan "notifications:send-digests"')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
```

---

## Esempi

### Notifica utile e notifica inutile

```
✗  «Il lotto LOT-2026-0187 è stato aggiornato.»
   Il destinatario non sa cosa è cambiato né cosa dovrebbe fare.

✓  «3 lotti scadono entro 7 giorni. Il più vicino: LOT-2026-0187, il 14/03.»
   Dice quanti, quando, e porta a un punto in cui si può agire.
```

Il criterio prima di aggiungere una notifica: **il destinatario deve poter fare qualcosa in
conseguenza**. Se no, è un log, non una notifica.

### Il record in banca dati contiene identificatori

```php
public function toArray(object $notifiable): array
{
    return [
        'batch_id' => $this->batchId,
        'type' => 'batch.expiring',
        'expires_at' => $this->expiresAt->toDateString(),
    ];
}
```

Non testo: così la notifica si può ritradurre se l'utente cambia lingua, e non conserva dati che
potrebbero essere sensibili.

---

## Best practice

- Aggiungere una notifica solo se il destinatario può agire.
- Raggruppare tutto ciò che non richiede azione immediata.
- Oggetto specifico: «Notifica dal sistema» finisce ignorato.
- Un'azione sola per notifica: due pulsanti significano una scelta, e la maggior parte non sceglie.
- Non far uscire posta verso indirizzi reali dagli ambienti non di produzione.
- Registrare l'esito per canale: un canale che fallisce in silenzio è peggio di uno assente.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Notifica su cui non si può agire | Tutte vengono ignorate | Solo se il destinatario può agire |
| Nessun raggruppamento | Cinquanta messaggi, nessuno letto | Digest per tipo |
| Preferenza globale invece che per tipo | Si disattiva tutto | Preferenza per tipo |
| Notifiche di sicurezza disattivabili | Vantaggio per chi ha compromesso l'account | `always_notify` |
| Testo nel record in banca dati | Non ritraducibile, dati conservati | Identificatori |
| Oggetto generico | Ignorato | Oggetto specifico |
| Invio sincrono | La richiesta attende il server SMTP | Sempre in coda |
| Posta reale da ambienti di prova | Messaggi ai clienti da staging | Catturatore locale |
| Esito non registrato | Fallimenti silenziosi | `notification_deliveries` |
| Marketing sul canale operativo | Le comunicazioni operative finiscono nell'indesiderata | Strumento separato |

---

## Checklist

- [ ] Ogni notifica ha un'azione possibile per il destinatario.
- [ ] Le preferenze sono per tipo, non globali.
- [ ] Le notifiche di sicurezza non sono disattivabili.
- [ ] Il raggruppamento è attivo per i tipi frequenti.
- [ ] I record in banca dati contengono identificatori, non testo.
- [ ] L'invio è sempre in coda.
- [ ] Gli ambienti non di produzione non inviano a indirizzi reali.
- [ ] L'esito per canale è registrato e monitorato.

---

## Riferimenti

- [Notifiche](../../architecture/24-notifications.md) · [Code e scheduler](../../architecture/18-queue-scheduler.md)
- [Queue](../../rules/queue.md) · [i18n](../../rules/i18n.md)
- [Template Notification](../../templates/infrastructure/Notification.php.stub)
