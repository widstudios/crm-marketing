# Notifiche

> Come si avvisano gli utenti: canali, preferenze, recapito affidabile e rispetto della volontà
> del destinatario.

---

## Indice

1. [Descrizione](#descrizione)
2. [Canali](#canali)
3. [Anatomia di una notifica](#anatomia-di-una-notifica)
4. [Preferenze dell'utente](#preferenze-dellutente)
5. [Notifiche in archivio](#notifiche-in-archivio)
6. [Raggruppamento e limitazione](#raggruppamento-e-limitazione)
7. [Affidabilità del recapito](#affidabilità-del-recapito)
8. [Notifiche e multitenancy](#notifiche-e-multitenancy)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Le notifiche sono il punto in cui l'applicazione parla all'utente fuori dall'interfaccia. Sono
anche il punto in cui è più facile perdere la fiducia di chi le riceve: troppe notifiche producono
utenti che le ignorano tutte, comprese quelle importanti.

Il criterio per ogni notifica: *il destinatario deve fare qualcosa, o gli serve saperlo adesso?*
Se la risposta è no, non è una notifica: è un dato da consultare.

---

## Canali

| Canale | Uso | Recapito |
|---|---|---|
| `database` | notifiche in archivio, visibili nell'interfaccia | immediato |
| `mail` | comunicazioni che richiedono azione o tracciabilità | asincrono |
| `broadcast` | aggiornamenti in tempo reale nell'interfaccia | immediato |
| `webhook` | integrazioni del cliente | asincrono, con ritentativi |
| `sms` | solo emergenze, con costo per messaggio | asincrono |

Canale predefinito: `database` **più** `mail` per le notifiche che richiedono azione, solo
`database` per le informative.

L'SMS non si usa come canale ordinario: ha un costo per messaggio e viene percepito come intrusivo.

---

## Anatomia di una notifica

```php
final class BatchExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $batchId,
        public readonly int $daysLeft,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $notifiable->notificationChannelsFor('batch.expiring');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $batch = Batch::findOrFail($this->batchId);

        return (new MailMessage())
            ->subject(__('inventory.notification.expiring.subject', ['number' => $batch->number]))
            ->line(__('inventory.notification.expiring.line', ['days' => $this->daysLeft]))
            ->action(__('common.view'), route('filament.admin.resources.batches.view', $batch))
            ->line(__('common.automatic_message'));
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'batch_id' => $this->batchId,
            'days_left' => $this->daysLeft,
            'type' => 'batch.expiring',
        ];
    }
}
```

| Regola | Motivo |
|---|---|
| `ShouldQueue` sempre | il recapito non deve rallentare l'operazione |
| Trasporta identificatori | rilegge lo stato corrente al recapito |
| `via()` rispetta le preferenze | l'utente decide come essere raggiunto |
| Ogni testo tradotto | la seconda lingua arriva sempre |
| Collegamento all'azione | una notifica senza azione è solo rumore |
| Identificatore di tipo | permette preferenze e raggruppamento per tipo |

---

## Preferenze dell'utente

```php
public function notificationChannelsFor(string $type): array
{
    $preference = $this->notificationPreferences()
        ->where('type', $type)
        ->first();

    // Default sicuro: se non c'è preferenza, solo l'archivio interno.
    return $preference?->channels ?? ['database'];
}
```

| Tipo di notifica | L'utente può disattivarla |
|---|---|
| Informativa (scadenze, riepiloghi) | sì, tutti i canali |
| Operativa (assegnazione, richiesta di approvazione) | può scegliere i canali, non disattivarla |
| Di sicurezza (accesso da nuovo dispositivo, cambio password) | **no** |
| Amministrativa (sospensione del tenant) | **no** |

Le notifiche di sicurezza non sono disattivabili: la loro funzione è avvisare di qualcosa che
l'utente potrebbe non aver fatto.

Le preferenze vivono nel database del **tenant**: sono dati dell'utente.

---

## Notifiche in archivio

Il canale `database` produce l'elenco visibile nell'interfaccia.

| Funzionalità | Nota |
|---|---|
| Contatore delle non lette | aggiornato in tempo reale |
| Segna come letta | singola e massiva |
| Filtro per tipo | quando i tipi sono molti |
| Collegamento all'entità | la notifica porta dove serve agire |
| Conservazione | 90 giorni, poi rimozione automatica |
| Paginazione | sempre |

La conservazione limitata è deliberata: un archivio che cresce indefinitamente diventa inutile e
pesa sulle query.

---

## Raggruppamento e limitazione

Il problema pratico più comune: cinquanta lotti in scadenza producono cinquanta notifiche, e
l'utente le ignora tutte.

| Tecnica | Effetto |
|---|---|
| Raggruppamento | una notifica «50 lotti in scadenza» invece di cinquanta |
| Riepilogo periodico | notifiche informative accorpate in un digest giornaliero |
| Limitazione per tipo | massimo N notifiche dello stesso tipo per ora |
| Soppressione dei duplicati | non ripetere la stessa notifica entro un intervallo |
| Preferenza di frequenza | immediata, giornaliera, settimanale |

```php
final class SendExpiryDigestJob implements ShouldQueue
{
    use TenantAware;

    public function handle(): void
    {
        $expiring = app(ExpiringBatchesQuery::class)->execute(days: 30);

        if ($expiring->isEmpty()) {
            return;   // nessuna notifica se non c'è nulla da dire
        }

        User::query()
            ->permission('batch.view')
            ->each(fn (User $user) => $user->notify(new ExpiryDigestNotification($expiring->count())));
    }
}
```

L'ultima riga della condizione è importante: un riepilogo che arriva ogni giorno anche quando è
vuoto insegna all'utente a ignorarlo.

---

## Affidabilità del recapito

| Aspetto | Regola |
|---|---|
| Sempre in coda | il fallimento del recapito non annulla l'operazione |
| Ritentativi | 3 tentativi con attesa crescente |
| Fallimenti registrati | con destinatario e motivo |
| Indirizzi non recapitabili | segnati, escluse dai tentativi successivi |
| Disiscrizione | rispettata per le notifiche informative |
| Mittente | dominio configurato con SPF, DKIM, DMARC |
| Anteprima | in locale via Mailpit, mai indirizzi reali |

Senza SPF, DKIM e DMARC configurati, le notifiche finiscono nella posta indesiderata e l'intero
sistema di notifica smette di funzionare senza che nessuno se ne accorga.

---

## Notifiche e multitenancy

| Aspetto | Nota |
|---|---|
| Destinatari | utenti del tenant, nel database del tenant |
| Job di notifica | `TenantAware`, come tutti |
| Mittente | può essere personalizzato per tenant (dominio del cliente) |
| Modelli di messaggio | personalizzabili per tenant |
| Preferenze | nel database del tenant |
| Notifiche di piattaforma | separate, ai `platform_users` nel landlord |

Un tenant non può inviare notifiche agli utenti di un altro: non esiste un percorso applicativo che
lo consenta, perché i destinatari si risolvono nel database corrente.

---

## Esempi

### Esempio 1 — notifica utile

```
Oggetto: 3 lotti in scadenza entro 7 giorni

Il lotto LOT-0042 (Garze sterili) scade il 01/08/2026.
Altri 2 lotti scadono entro la stessa data.

[Vedi i lotti in scadenza]
```

Dice cosa, quando, quanti, e porta dove agire.

### Esempio 2 — notifica da non inviare

```
Oggetto: Movimento registrato

È stato registrato un movimento di scarico sul lotto LOT-0042.
```

L'utente ha appena registrato il movimento: la notifica non aggiunge nulla e insegna a ignorare le
notifiche successive. Questa informazione appartiene all'activity log, non a una notifica.

---

## Best practice

- Una notifica solo se il destinatario deve agire o gli serve saperlo adesso.
- Sempre in coda, con identificatori nel payload.
- Rispettare le preferenze, con default conservativo.
- Raggruppare invece di moltiplicare.
- Nessun riepilogo vuoto.
- Collegamento all'azione in ogni notifica.
- Notifiche di sicurezza non disattivabili.
- SPF, DKIM e DMARC configurati.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Notifica per ogni evento | L'utente le ignora tutte | Solo se serve un'azione |
| Notifiche non raggruppate | Cinquanta messaggi per un solo fatto | Raggruppamento e digest |
| Recapito sincrono | Operazione lenta, fallimento propagato | Sempre in coda |
| Preferenze non rispettate | Perdita di fiducia, disiscrizioni | `via()` dalle preferenze |
| Notifiche di sicurezza disattivabili | Accessi anomali non segnalati | Non disattivabili |
| Testi scritti nel codice | Seconda lingua impossibile | Traduzioni |
| Nessun collegamento all'azione | L'utente non sa cosa fare | Azione sempre presente |
| SPF/DKIM non configurati | Notifiche nella posta indesiderata | Configurazione del dominio |
| Riepilogo inviato anche se vuoto | Insegna a ignorare | Nessuna notifica senza contenuto |

---

## Checklist

- [ ] Ogni notifica ha un destinatario che deve agire o sapere.
- [ ] Tutte le notifiche implementano `ShouldQueue`.
- [ ] Il payload contiene identificatori.
- [ ] `via()` rispetta le preferenze dell'utente.
- [ ] Le notifiche di sicurezza non sono disattivabili.
- [ ] Le notifiche massive sono raggruppate.
- [ ] Nessun riepilogo viene inviato vuoto.
- [ ] Ogni notifica ha un collegamento all'azione.
- [ ] Tutti i testi sono tradotti.
- [ ] SPF, DKIM e DMARC configurati.
- [ ] Le preferenze e i destinatari vivono nel database del tenant.

---

## Riferimenti

- [Code e scheduler](18-queue-scheduler.md) · [Eventi](21-events-and-messaging.md)
- [Regole Queue](../rules/queue.md)
- [Modulo notifications](../modules/catalog/notifications.md)
- [Template Notification](../templates/infrastructure/README.md)
