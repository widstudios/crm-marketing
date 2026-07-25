# Ciclo di vita del tenant

> Gli stati di un cliente sulla piattaforma, le transizioni ammesse e cosa accade tecnicamente in
> ciascuna.

---

## Indice

1. [Descrizione](#descrizione)
2. [Gli stati](#gli-stati)
3. [Le transizioni](#le-transizioni)
4. [Provisioning](#provisioning)
5. [Sospensione](#sospensione)
6. [Migrazione](#migrazione)
7. [Dismissione](#dismissione)
8. [Eventi di ciclo di vita](#eventi-di-ciclo-di-vita)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Il ciclo di vita di un tenant tocca database, storage, code, cache, DNS e fatturazione. Ogni
transizione deve essere **atomica quanto possibile** e **riprendibile** quando non può esserlo:
un tenant a metà tra due stati è la condizione più difficile da diagnosticare in produzione.

---

## Gli stati

```
                  ┌──────────────┐
                  │ provisioning │
                  └──────┬───────┘
                         │ completato
                         ▼
   ┌──────────┐    ┌──────────┐    ┌───────────┐
   │ migrating│◀──▶│  active  │◀──▶│ suspended │
   └──────────┘    └────┬─────┘    └─────┬─────┘
                        │                │
                        └────────┬───────┘
                                 ▼
                        ┌────────────────┐
                        │  terminating   │
                        └────────┬───────┘
                                 │ trascorso il ripensamento
                                 ▼
                        ┌────────────────┐
                        │   terminated   │
                        └────────────────┘
```

| Stato | Accesso utenti | Job | Backup | Reversibile |
|---|---|---|---|---|
| `provisioning` | no | no | no | sì (annullamento) |
| `active` | completo | sì | sì | — |
| `suspended` | bloccato | sospesi | **sì** | sì |
| `migrating` | sola lettura | sospesi | sì | sì |
| `terminating` | solo esportazione | no | sì | sì, entro il ripensamento |
| `terminated` | nessuno | no | scadono | **no** |

---

## Le transizioni

```php
enum TenantStatus: string
{
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Suspended = 'suspended';
    case Migrating = 'migrating';
    case Terminating = 'terminating';
    case Terminated = 'terminated';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Provisioning => in_array($target, [self::Active, self::Terminated], true),
            self::Active => in_array($target, [self::Suspended, self::Migrating, self::Terminating], true),
            self::Suspended => in_array($target, [self::Active, self::Terminating], true),
            self::Migrating => in_array($target, [self::Active, self::Suspended], true),
            self::Terminating => in_array($target, [self::Active, self::Terminated], true),
            self::Terminated => false,
        };
    }
}
```

`Terminated` è uno stato terminale: nessuna transizione in uscita. È l'unico punto irreversibile
dell'intero ciclo, e per questo vi si arriva solo dopo un periodo di ripensamento.

---

## Provisioning

Dieci passi, con comportamento diverso in caso di errore prima e dopo il quarto.

| Passo | Azione | Errore prima del 4° | Errore dopo il 4° |
|---|---|---|---|
| 1 | record nel landlord | annulla tutto | — |
| 2 | creazione del database | annulla | — |
| 3 | utente MySQL dedicato | annulla | — |
| 4 | migration complete | annulla | — |
| 5 | seeder di sistema | — | mantieni e segnala |
| 6 | utente amministratore e invito | — | mantieni e segnala |
| 7 | disco di storage | — | mantieni e segnala |
| 8 | associazione del dominio | — | mantieni e segnala |
| 9 | configurazione dal piano | — | mantieni e segnala |
| 10 | verifica e stato `active` | — | segnala |

La ragione della differenza: fino al quarto passo non esistono dati; dopo, eliminare sarebbe più
pericoloso di lasciare un tenant incompleto da completare a mano.

Il provisioning gira **in coda**: dura da secondi a minuti secondo il numero di migration.

---

## Sospensione

Motivi ricorrenti: mancato pagamento, richiesta del cliente, manutenzione straordinaria,
violazione contrattuale.

| Aspetto | Comportamento |
|---|---|
| Accesso utenti | bloccato con pagina esplicativa, non con errore generico |
| API | `403` con motivo |
| Dati | conservati integralmente |
| Job schedulati | sospesi |
| Job già in coda | completati, poi nessun nuovo accodamento |
| **Backup** | **continuano** |
| Notifiche | sospese, tranne quelle amministrative |
| Fatturazione | secondo contratto |

I backup continuano perché la sospensione è il momento in cui è più probabile che il cliente
chieda l'esportazione dei propri dati.

---

## Migrazione

Spostamento su un'altra infrastruttura: server più capiente, regione diversa, installazione
dedicata.

```
1. stato `migrating`, sola lettura
2. copia del database verso la destinazione
3. copia dei file
4. sincronizzazione delle modifiche accumulate
5. verifica di integrità (conteggi, checksum)
6. aggiornamento del puntamento nel landlord
7. aggiornamento DNS
8. verifica funzionale
9. stato `active`
10. rimozione dell'origine dopo almeno 7 giorni di garanzia
```

La sola lettura invece del fermo completo riduce l'impatto: gli utenti consultano, e la finestra
di scrittura persa si limita ai passi 4-8.

Il periodo di garanzia prima della rimozione dell'origine è ciò che rende la migrazione
reversibile.

---

## Dismissione

| Fase | Durata | Cosa accade |
|---|---|---|
| Richiesta | — | stato `terminating`, accesso in sola esportazione |
| Esportazione | subito | archivio cifrato generato e consegnato, ricezione registrata |
| Ripensamento | 30 giorni | dati conservati, revocabile |
| Cancellazione | — | database eliminato, file eliminati, dominio liberato |
| Conservazione legale | secondo norma | solo audit log, in archivio separato |
| Backup | ciclo naturale | scadono secondo la politica di conservazione |

L'esportazione **precede** la cancellazione ed è obbligatoria: la consegna dei dati è un obbligo
contrattuale, e spesso normativo.

La conservazione dell'audit log dopo la cancellazione risponde a obblighi di legge che
sopravvivono al rapporto commerciale: va conservato in un archivio separato, con accesso
eccezionale e tracciato.

---

## Eventi di ciclo di vita

Ogni transizione emette un evento, registrato in `tenant_events` nel landlord.

| Evento | Ascoltatori tipici |
|---|---|
| `TenantProvisioned` | notifica commerciale, configurazione del monitoraggio |
| `TenantActivated` | invio delle credenziali, avvio della fatturazione |
| `TenantSuspended` | notifica al cliente, sospensione dello scheduler |
| `TenantResumed` | notifica, riallineamento delle migration |
| `TenantMigrationStarted` / `Completed` | aggiornamento del monitoraggio |
| `TenantTerminationRequested` | generazione dell'esportazione, notifica |
| `TenantTerminated` | chiusura della fatturazione, rimozione dal monitoraggio |

Il registro degli eventi sopravvive alla cancellazione dei dati: permette di ricostruire la storia
di un rapporto anche anni dopo.

---

## Esempi

### Esempio 1 — riattivazione dopo sospensione

Un tenant sospeso per due mesi viene riattivato. Nel frattempo sono stati rilasciati quattro
aggiornamenti con migration.

```bash
php artisan tenant:resume acme
```

```
✓ stato: active
⚠ schema disallineato: 7 migration mancanti
→ esecuzione automatica delle migration
✓ schema allineato
✓ scheduler riattivato
✓ notifica inviata al cliente
```

Il riallineamento automatico alla riattivazione evita che un tenant riattivato lavori su uno
schema vecchio.

### Esempio 2 — dismissione con ripensamento

```
giorno 0   richiesta; `terminating`; esportazione generata e consegnata; ricezione registrata
giorno 12  il cliente ripensa; `active`; nessun dato perso
```

Senza il periodo di ripensamento, il ripristino sarebbe possibile solo da backup, con perdita
delle operazioni successive.

---

## Best practice

- Ogni transizione è un comando, mai una sequenza manuale.
- Il provisioning gira in coda, con annullamento sui primi passi.
- I backup continuano durante la sospensione.
- La migrazione mantiene la sola lettura e un periodo di garanzia.
- La dismissione richiede esportazione consegnata e ripensamento.
- Ogni transizione emette un evento registrato.
- Il riallineamento dello schema avviene automaticamente alla riattivazione.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Provisioning sincrono | Timeout della richiesta HTTP | Esecuzione in coda |
| Nessun annullamento sui primi passi | Tenant fantasma nel landlord | Annullamento fino al 4° passo |
| Backup sospesi con il tenant | Nessuna copia quando serve l'esportazione | Backup sempre attivi |
| Migrazione con fermo completo | Impatto evitabile sul cliente | Sola lettura |
| Origine rimossa subito dopo la migrazione | Nessun ritorno possibile | Garanzia di 7 giorni |
| Cancellazione immediata | Errori irreversibili | Ripensamento di 30 giorni |
| Riattivazione senza riallineamento | Tenant su schema vecchio | Migration automatiche |
| Audit log cancellato con i dati | Inadempienza normativa | Archivio separato |

---

## Checklist

- [ ] Le transizioni ammesse sono definite in un enum.
- [ ] Il provisioning gira in coda con annullamento.
- [ ] La sospensione conserva i dati e mantiene i backup.
- [ ] La migrazione usa la sola lettura e un periodo di garanzia.
- [ ] La dismissione richiede esportazione consegnata e registrata.
- [ ] Il ripensamento è di almeno 30 giorni.
- [ ] Ogni transizione emette un evento registrato.
- [ ] La riattivazione riallinea automaticamente lo schema.
- [ ] L'audit log è conservato secondo norma dopo la cancellazione.

---

## Riferimenti

- [Multitenancy](03-multitenancy-overview.md) · [Database tenant](05-tenant-databases.md)
- [Operazioni sui tenant](../docs/05-operations/06-tenant-operations.md)
- [Backup e ripristino](../docs/05-operations/04-backup-and-restore.md)
- [Modulo tenancy](../modules/catalog/tenancy.md)
