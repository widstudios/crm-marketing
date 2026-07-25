# Operazioni sui tenant

> Il ciclo di vita di un cliente sulla piattaforma: creazione, configurazione, sospensione,
> migrazione, esportazione e cancellazione.

---

## Indice

1. [Descrizione](#descrizione)
2. [Ciclo di vita](#ciclo-di-vita)
3. [Provisioning](#provisioning)
4. [Configurazione](#configurazione)
5. [Sospensione e riattivazione](#sospensione-e-riattivazione)
6. [Migrazione](#migrazione)
7. [Esportazione dei dati](#esportazione-dei-dati)
8. [Cancellazione](#cancellazione)
9. [Manutenzione ordinaria](#manutenzione-ordinaria)
10. [Esempi](#esempi)
11. [Best practice](#best-practice)
12. [Errori comuni](#errori-comuni)
13. [Checklist](#checklist)
14. [Riferimenti](#riferimenti)

---

## Descrizione

Ogni tenant ha un ciclo di vita che va oltre la creazione: viene configurato, talvolta sospeso,
può essere spostato su un'altra infrastruttura, e prima o poi va cancellato. Ognuna di queste
operazioni tocca database, storage, code, cache e DNS.

Automatizzarle non è un lusso: eseguite a mano producono tenant in stati inconsistenti, e uno
stato inconsistente in produzione è difficile da diagnosticare.

---

## Ciclo di vita

```
   creazione ──▶ attivo ──┬──▶ sospeso ──┬──▶ attivo
                          │              │
                          │              └──▶ in dismissione ──▶ cancellato
                          │
                          └──▶ in migrazione ──▶ attivo
```

| Stato | Significato | Accesso |
|---|---|---|
| `provisioning` | in creazione | nessuno |
| `active` | operativo | completo |
| `suspended` | sospeso (mancato pagamento, richiesta, manutenzione) | bloccato, dati conservati |
| `migrating` | in spostamento | sola lettura |
| `terminating` | in dismissione | solo esportazione |
| `terminated` | cancellato | nessuno, dati rimossi |

---

## Provisioning

```bash
php artisan tenant:create acme \
    --domain=acme.gestionale.it \
    --plan=professional \
    --admin-email=admin@acme.it \
    --locale=it
```

Cosa avviene, in ordine:

| Passo | Azione | In caso di errore |
|---|---|---|
| 1 | record del tenant nel landlord | annulla |
| 2 | creazione del database dedicato | annulla il passo 1 |
| 3 | migration tenant complete | elimina il database |
| 4 | seeder di sistema (ruoli, permessi, stati) | elimina il database |
| 5 | utente amministratore e invito | mantiene, segnala |
| 6 | disco di storage dedicato | mantiene, segnala |
| 7 | associazione del dominio | mantiene, segnala |
| 8 | configurazione iniziale dal piano | mantiene, segnala |
| 9 | verifica di integrità | segnala |
| 10 | stato `active` | — |

L'operazione è **transazionale fino al passo 4**: se fallisce prima, non lascia tracce. Dopo, si
mantiene lo stato raggiunto e si segnala, perché eliminare un database con dati già presenti
sarebbe più pericoloso di un tenant incompleto.

Il provisioning va sempre eseguito in coda: dura da secondi a minuti secondo il numero di
migration, e non deve bloccare una richiesta HTTP.

---

## Configurazione

| Livello | Dove | Esempio |
|---|---|---|
| Piattaforma | `config/` | versione, funzionalità globali |
| Piano | landlord, tabella `plans` | limiti di utenti, moduli abilitati |
| Tenant | landlord, tabella `tenants` | dominio, locale, fuso orario |
| Applicativa | database del tenant | preferenze, personalizzazioni |

I limiti di piano si verificano **all'atto dell'operazione**, non solo nell'interfaccia:

```php
throw_if(
    $tenant->users()->count() >= $tenant->plan->max_users,
    PlanLimitReached::forUsers($tenant->plan->max_users),
);
```

Un limite verificato solo nell'interfaccia è aggirabile via API.

---

## Sospensione e riattivazione

```bash
php artisan tenant:suspend acme --reason="Mancato pagamento" --notify
php artisan tenant:resume acme
```

Durante la sospensione:

| Aspetto | Comportamento |
|---|---|
| Accesso utenti | bloccato, con messaggio esplicativo |
| API | risposta `403` con motivo |
| Dati | conservati integralmente |
| Job schedulati | sospesi |
| Job in coda | completati, poi nessun nuovo accodamento |
| Backup | **continuano** |
| Notifiche | sospese, tranne quelle amministrative |

I backup continuano durante la sospensione: è il momento in cui è più probabile che il cliente
chieda l'esportazione dei propri dati.

---

## Migrazione

Spostare un tenant su un'altra infrastruttura (server più capiente, regione diversa,
installazione dedicata).

```bash
php artisan tenant:migrate-infrastructure acme --target=cluster-2
```

Sequenza:

1. Stato `migrating`, tenant in sola lettura.
2. Copia del database verso la destinazione.
3. Copia dei file.
4. Sincronizzazione delle modifiche accumulate durante la copia.
5. Verifica di integrità (conteggi, checksum).
6. Aggiornamento del puntamento nel landlord.
7. Aggiornamento del DNS.
8. Verifica funzionale.
9. Stato `active`.
10. Rimozione dell'origine **dopo un periodo di garanzia** (minimo 7 giorni).

La sola lettura durante la migrazione è preferibile a un fermo completo: gli utenti possono
consultare, e la finestra di scrittura persa è breve.

---

## Esportazione dei dati

Diritto contrattuale e, per i dati personali, obbligo normativo.

```bash
php artisan tenant:export acme --format=sql --include-files
php artisan tenant:export acme --format=csv --tables=suppliers,movements
```

| Formato | Uso |
|---|---|
| SQL | migrazione verso un'altra installazione |
| CSV | consegna al cliente per uso proprio |
| JSON | integrazione con altri sistemi |

L'archivio prodotto è cifrato e la password consegnata su un canale separato. La generazione è
tracciata nell'audit log: chi ha esportato, cosa, quando.

---

## Cancellazione

La cancellazione è **irreversibile** e ha una procedura in più passi, deliberatamente lenta.

```bash
php artisan tenant:terminate acme --confirm=acme --retention-days=30
```

| Fase | Durata | Cosa avviene |
|---|---|---|
| Richiesta | — | stato `terminating`, accesso in sola esportazione |
| Periodo di ripensamento | 30 giorni | dati conservati, revocabile |
| Cancellazione dei dati | — | database eliminato, file eliminati |
| Conservazione legale | secondo norma | solo audit log, in archivio separato |
| Rimozione dai backup | ciclo di conservazione | i backup scadono naturalmente |

Il parametro `--confirm=acme` richiede di digitare il nome del tenant: è la protezione minima
contro la cancellazione del tenant sbagliato.

Prima della cancellazione va prodotta e consegnata l'esportazione completa, e l'avvenuta consegna
va registrata.

---

## Manutenzione ordinaria

| Operazione | Frequenza | Comando |
|---|---|---|
| Verifica dell'allineamento delle migration | ad ogni deploy | `tenants:migrate:status` |
| Verifica di integrità | settimanale | `tenants:verify` |
| Pulizia dei dati scaduti | giornaliera | `tenants:prune` |
| Ricalcolo degli aggregati | notturna | `tenants:recalculate` |
| Verifica dei limiti di piano | giornaliera | `tenants:check-limits` |
| Rapporto sull'utilizzo | mensile | `tenants:usage-report` |

Ogni comando che opera su tutti i tenant deve poter **riprendere** da dove si è interrotto: con
cinquanta tenant, un fallimento a metà non deve costringere a ricominciare.

---

## Esempi

### Esempio 1 — provisioning che fallisce a metà

Il passo 3 (migration) fallisce per spazio esaurito. Il sistema elimina il database creato al
passo 2 e il record al passo 1: nessuna traccia, l'operazione si può ripetere dopo aver liberato
spazio.

Se lo stesso fallimento avvenisse al passo 6 (storage), il tenant resterebbe con database e utenti
già creati: si segnala e si completa manualmente, perché eliminare quei dati sarebbe peggio.

### Esempio 2 — cancellazione richiesta dal cliente

```
giorno 0   richiesta; stato `terminating`; esportazione generata e consegnata
giorno 0   conferma di ricezione registrata
giorno 30  cancellazione dei dati; audit log spostato in archivio legale
giorno 30  conferma di avvenuta cancellazione al cliente
giorno 90  ultimo backup contenente i dati scade naturalmente
```

---

## Best practice

- Ogni operazione sui tenant è un comando, mai una sequenza manuale.
- Il provisioning gira in coda, con annullamento sui primi passi.
- I limiti di piano si verificano nell'Action, non nell'interfaccia.
- I backup continuano durante la sospensione.
- La migrazione mantiene la sola lettura, non un fermo completo.
- La cancellazione ha un periodo di ripensamento e richiede conferma esplicita.
- Ogni comando massivo è riprendibile.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Provisioning manuale | Tenant in stati inconsistenti | Comando automatizzato |
| Provisioning sincrono | Richiesta HTTP in timeout | Esecuzione in coda |
| Limiti verificati solo nell'interfaccia | Aggirabili via API | Verifica nell'Action |
| Backup sospesi con il tenant | Nessuna copia proprio quando serve | Backup sempre attivi |
| Cancellazione immediata | Errori irreversibili | Periodo di ripensamento |
| Nessuna esportazione prima della cancellazione | Inadempienza contrattuale | Esportazione obbligatoria |
| Comandi massivi non riprendibili | Riavvio da zero ad ogni errore | Ripresa dal punto di interruzione |

---

## Checklist

**Provisioning**
- [ ] Eseguito tramite comando, in coda.
- [ ] Database, migration, seeder di sistema, amministratore, storage, dominio creati.
- [ ] Verifica di integrità superata.

**Sospensione**
- [ ] Motivo registrato, cliente notificato.
- [ ] Dati conservati, backup attivi.

**Cancellazione**
- [ ] Esportazione generata e consegnata, ricezione registrata.
- [ ] Periodo di ripensamento rispettato.
- [ ] Conferma esplicita richiesta.
- [ ] Audit log conservato secondo norma.

---

## Riferimenti

- [Ciclo di vita del tenant](../../architecture/07-tenant-lifecycle.md)
- [Database tenant](../../architecture/05-tenant-databases.md)
- [Backup e ripristino](04-backup-and-restore.md)
- [Modulo tenancy](../../modules/catalog/tenancy.md)
- [Riferimento comandi](../06-reference/03-command-reference.md)
