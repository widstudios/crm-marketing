# Ambienti

> I quattro ambienti standard, cosa li distingue, e quali regole valgono in ciascuno.

---

## Indice

1. [Descrizione](#descrizione)
2. [I quattro ambienti](#i-quattro-ambienti)
3. [Configurazione per ambiente](#configurazione-per-ambiente)
4. [Dati per ambiente](#dati-per-ambiente)
5. [Accessi](#accessi)
6. [Promozione tra ambienti](#promozione-tra-ambienti)
7. [Parità tra ambienti](#parità-tra-ambienti)
8. [Esempi](#esempi)
9. [Best practice](#best-practice)
10. [Errori comuni](#errori-comuni)
11. [Checklist](#checklist)
12. [Riferimenti](#riferimenti)

---

## Descrizione

Gli ambienti servono a **scoprire i problemi prima del cliente**. Ogni differenza tra staging e
produzione è un punto cieco: qualcosa che non si può verificare prima di rilasciare.

Per questo la regola guida è la **parità**: gli ambienti differiscono per scala e per dati, mai
per composizione dei servizi.

---

## I quattro ambienti

| Ambiente | Scopo | Dati | Chi accede | Rilascio |
|---|---|---|---|---|
| **local** | sviluppo | finti, generati | sviluppatore | continuo |
| **testing** | esecuzione automatica dei test | effimeri, in memoria | pipeline | ad ogni push |
| **staging** | verifica prima della produzione | anonimizzati, realistici | team, cliente su richiesta | ad ogni merge su `main` |
| **production** | esercizio | reali | utenti finali | pianificato |

Un quinto ambiente ricorrente è la **demo**: un'istanza con dati dimostrativi per le presentazioni
commerciali. Tecnicamente è una produzione con dati finti, e va trattata con le stesse regole di
sicurezza.

---

## Configurazione per ambiente

| Impostazione | local | testing | staging | production |
|---|---|---|---|---|
| `APP_ENV` | `local` | `testing` | `staging` | `production` |
| `APP_DEBUG` | `true` | `true` | `false` | **`false`** |
| Database | MySQL in container | SQLite in memoria | MySQL dedicato | MySQL con replica |
| Cache | Redis | array | Redis | Redis |
| Code | Redis | sync | Redis | Redis + Horizon |
| Mail | Mailpit | array | Mailpit o dominio di prova | SMTP reale |
| Storage | locale/MinIO | locale | S3 dedicato | S3 con backup |
| Telescope | attivo | disattivo | attivo | **disattivo** |
| Log | `debug` su file | `debug` | `info` | `warning` + servizio esterno |
| HTTPS | facoltativo | — | obbligatorio | obbligatorio con HSTS |
| Limitazione traffico | permissiva | disattiva | come produzione | attiva |

`APP_DEBUG=false` in staging non è un dettaglio: con `true` non si verificherebbe mai il
comportamento reale delle pagine di errore.

---

## Dati per ambiente

| Ambiente | Origine dei dati | Anonimizzazione |
|---|---|---|
| local | factory e seeder demo | non necessaria |
| testing | factory | non necessaria |
| staging | copia anonimizzata della produzione, o dati generati | **obbligatoria** |
| production | reali | — |

L'anonimizzazione di staging non è facoltativa: uno staging con dati reali è un secondo bersaglio
con le stesse informazioni e meno protezioni.

Cosa si anonimizza: nomi, indirizzi, email, telefoni, codici fiscali, dati sanitari, allegati,
credenziali. Cosa si conserva: volumi, distribuzioni, relazioni, casi limite — perché sono quelli
che fanno emergere i problemi.

```bash
php artisan tenants:anonymize --source=production-dump.sql --target=staging
```

---

## Accessi

| Ambiente | Accesso applicativo | Accesso al database | Accesso al server |
|---|---|---|---|
| local | libero | libero | — |
| testing | — | — | solo pipeline |
| staging | team, cliente su richiesta | lettura per il team | DevOps |
| production | utenti finali | lettura con autorizzazione, scrittura solo con procedura | DevOps, tracciato |

Ogni accesso in produzione è **tracciato e motivato**. Un accesso senza motivazione registrata è
un accesso che nessuno può giustificare in caso di contestazione.

---

## Promozione tra ambienti

```
local ──(push)──▶ testing ──(merge su main)──▶ staging ──(tag)──▶ production
```

Condizioni per ogni passaggio:

| Passaggio | Condizioni |
|---|---|
| local → testing | `composer qa` verde in locale |
| testing → staging | pipeline verde, revisione approvata, merge su `main` |
| staging → production | verifica funzionale in staging, checklist di rilascio, tag, finestra rispettata |

Il codice **non salta** ambienti: una correzione urgente passa comunque da staging, anche se per
dieci minuti. L'unica eccezione è un incidente in corso, e in quel caso la verifica avviene dopo,
entro 24 ore.

---

## Parità tra ambienti

| Deve essere identico | Può differire |
|---|---|
| Versione di PHP e delle estensioni | numero di worker |
| Versione del database | dimensione delle risorse |
| Versione di Redis | numero di repliche |
| Immagini Docker | dominio |
| Configurazione del web server | livello di log |
| Dipendenze (`composer.lock`) | dati |
| Schema del database | credenziali |

La differenza che genera più incidenti è la versione delle estensioni PHP: un progetto che
funziona in locale e fallisce in produzione per una funzione mancante è quasi sempre questo.

---

## Esempi

### Esempio 1 — la differenza che nasconde un difetto

Staging con `APP_DEBUG=true`: la pagina di errore mostra lo stack trace, tutto sembra
diagnosticabile. In produzione, con `false`, la stessa condizione produce una pagina bianca perché
il gestore di errori personalizzato ha un difetto — mai verificato, perché in staging non veniva
usato.

### Esempio 2 — staging che vale davvero

Il tenant di staging più grande ha 300.000 movimenti, come il cliente principale. Una modifica
alla query di riepilogo passa i test in locale (500 righe) e impiega 8 secondi in staging.
Il problema si scopre prima del rilascio, non dopo.

---

## Best practice

- Stessa immagine Docker in tutti gli ambienti, cambia solo la configurazione.
- Staging con volumi di dati realistici, non simbolici.
- Anonimizzazione automatica ad ogni copia dalla produzione.
- Nessun salto di ambiente.
- Accessi in produzione tracciati e motivati.
- Verificare la parità delle versioni ad ogni aggiornamento.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Staging con dati reali | Secondo bersaglio con meno protezioni | Anonimizzazione obbligatoria |
| Staging con pochi dati | I problemi di prestazioni emergono in produzione | Volumi realistici |
| `APP_DEBUG=true` in staging | Comportamento degli errori mai verificato | `false` |
| Versioni diverse tra ambienti | Difetti non riproducibili | Stessa immagine |
| Rilascio diretto in produzione | Nessuna verifica preventiva | Passaggio da staging |
| Accessi in produzione non tracciati | Impossibile ricostruire chi ha fatto cosa | Tracciamento obbligatorio |

---

## Checklist

- [ ] Le versioni di PHP, database e Redis sono identiche tra staging e produzione.
- [ ] `APP_DEBUG=false` in staging e produzione.
- [ ] Telescope disabilitato in produzione.
- [ ] Staging ha dati anonimizzati e volumi realistici.
- [ ] Gli accessi in produzione sono tracciati.
- [ ] Nessun ambiente viene saltato nella promozione.
- [ ] La stessa immagine Docker è usata ovunque.

---

## Riferimenti

- [Gestione dei rilasci](02-release-management.md)
- [Docker](../../deployment/docker/README.md) · [Pipeline CI](../../deployment/ci/README.md)
- [Ambienti di deployment](../../deployment/environments/README.md)
- [Ambiente locale](../01-getting-started/02-local-environment.md)
