# Gestione dei rilasci

> Come si porta una versione in produzione senza interruzioni, con la possibilità di tornare
> indietro in ogni momento.

---

## Indice

1. [Descrizione](#descrizione)
2. [Tipi di rilascio](#tipi-di-rilascio)
3. [Finestre di rilascio](#finestre-di-rilascio)
4. [Preparazione](#preparazione)
5. [La sequenza di rilascio](#la-sequenza-di-rilascio)
6. [Rilascio senza interruzione](#rilascio-senza-interruzione)
7. [Rollback](#rollback)
8. [Osservazione post-rilascio](#osservazione-post-rilascio)
9. [Comunicazione al cliente](#comunicazione-al-cliente)
10. [Esempi](#esempi)
11. [Best practice](#best-practice)
12. [Errori comuni](#errori-comuni)
13. [Checklist](#checklist)
14. [Riferimenti](#riferimenti)

---

## Descrizione

Un rilascio va progettato attorno a una domanda sola: **come torniamo indietro se qualcosa va
storto?** Se la risposta non esiste o richiede più di dieci minuti, il rilascio non è pronto.

Il secondo criterio è la frequenza: rilasci piccoli e frequenti sono più sicuri di rilasci grandi
e rari, perché la superficie di ciò che può rompersi è minore e la causa è più facile da isolare.

---

## Tipi di rilascio

| Tipo | Contenuto | Preavviso | Finestra |
|---|---|---|---|
| **Patch** | correzioni, nessuna migration | nessuno | qualsiasi momento della finestra |
| **Minor** | nuove funzionalità, migration additive | 2 giorni | finestra ordinaria |
| **Major** | modifiche che richiedono formazione o migrazione dati | 2 settimane | finestra concordata |
| **Hotfix** | correzione urgente di un difetto in produzione | immediato | qualsiasi momento |

Il preavviso non è burocrazia: un cliente che scopre un cambiamento senza essere stato avvisato
apre un ticket di segnalazione, e il costo di quel ticket supera quello della comunicazione.

---

## Finestre di rilascio

| Momento | Ammesso | Motivo |
|---|---|---|
| Martedì–giovedì, 9:00–16:00 | sì | il team è disponibile per intervenire |
| Lunedì | sconsigliato | eventuali problemi del fine settimana non ancora emersi |
| Venerdì | **no** | un difetto resterebbe fino al lunedì |
| Fine mese, chiusure contabili | **no** | massimo utilizzo, massimo impatto |
| Fuori orario | solo hotfix | reperibilità limitata |

La regola sul venerdì non è scaramanzia: è la constatazione che il tempo di rilevamento e
correzione si allunga di due giorni.

---

## Preparazione

Prima del rilascio devono essere veri tutti questi punti:

- [ ] La versione è verificata in staging con dati realistici.
- [ ] La pipeline è verde sul commit da rilasciare.
- [ ] Il changelog è completo, in linguaggio dell'utente.
- [ ] Le migration sono state provate in avanti **e** in rollback su staging.
- [ ] Le migration dei tenant sono state stimate come durata.
- [ ] Il piano di rollback è scritto.
- [ ] Il cliente è stato avvisato secondo il preavviso previsto.
- [ ] Il backup pre-rilascio è stato eseguito e **verificato**.
- [ ] Chi rilascia è disponibile nelle due ore successive.

Un backup non verificato non è un backup: è un file di cui si spera qualcosa.

---

## La sequenza di rilascio

```
 1. Backup completo (landlord + tenant), verificato
 2. Tag della versione
 3. Build dell'immagine e pubblicazione nel registro
 4. Migration landlord (solo additive)
 5. Deploy del codice (compatibile con schema vecchio e nuovo)
 6. Migration tenant, a lotti, con monitoraggio
 7. Svuotamento e ricostruzione delle cache
 8. Riavvio dei worker
 9. Verifica automatica (health check, smoke test)
10. Verifica manuale dei percorsi critici
11. Osservazione per 30 minuti
12. Comunicazione di completamento
```

I passi 4 e 5 sono in quest'ordine per una ragione precisa: le migration additive non rompono il
codice vecchio, quindi durante il deploy convivono senza problemi. L'ordine inverso produce
errori nel tempo che intercorre.

Il passo 8 non è opzionale: i worker mantengono in memoria il codice caricato all'avvio e
continuerebbero a eseguire la versione precedente.

---

## Rilascio senza interruzione

| Requisito | Come si ottiene |
|---|---|
| Migration additive | mai `DROP` o `RENAME` nello stesso rilascio del codice |
| Codice compatibile con due schemi | il pattern in tre rilasci |
| Sostituzione graduale dei container | rolling update, con health check |
| Sessioni preservate | sessioni in Redis, non su file |
| Job in corso non persi | arresto controllato dei worker |
| Asset versionati | nomi con hash, cache invalidata automaticamente |

Il pattern in tre rilasci è ciò che rende possibile tutto il resto: senza, ogni modifica di schema
richiede una finestra di indisponibilità.

---

## Rollback

Il piano di rollback si scrive **prima** del rilascio.

| Scenario | Azione | Tempo |
|---|---|---|
| Difetto senza migration | ripristino dell'immagine precedente | < 5 min |
| Difetto con migration additiva | ripristino del codice, schema invariato | < 5 min |
| Difetto con migration distruttiva | ripristino da backup | 30-120 min |
| Dati corrotti | ripristino del tenant coinvolto | 15-60 min |

```bash
# Rollback del codice
docker compose pull app:v2.3.9 && docker compose up -d --no-deps app
php artisan queue:restart

# Rollback dello schema (solo se strettamente necessario)
php artisan tenants:migrate:rollback --step=1
```

La differenza tra cinque minuti e due ore sta interamente nell'aver evitato le migration
distruttive: è la ragione pratica del pattern in tre rilasci.

---

## Osservazione post-rilascio

| Intervallo | Cosa si osserva |
|---|---|
| 0-5 min | health check, errori 500, code |
| 5-30 min | tempi di risposta, tasso di errore, job falliti |
| 30 min - 2 h | segnalazioni degli utenti, log applicativi |
| 2-24 h | metriche aggregate, prestazioni, consumo risorse |
| 24 h - 7 gg | difetti latenti, comportamenti anomali |

Soglie che impongono il rollback immediato: tasso di errore oltre l'1%, tempo di risposta mediano
raddoppiato, qualunque violazione di isolamento tenant.

---

## Comunicazione al cliente

**Prima** (secondo il preavviso previsto): cosa cambia, quando, indisponibilità prevista, azioni
richieste.

**Dopo**: completato, cosa è cambiato, come segnalare problemi.

**In caso di problema**: cosa è successo, cosa stiamo facendo, quando aggiorneremo — senza
attendere di avere la soluzione. Il silenzio durante un problema produce più danno del problema.

---

## Esempi

### Esempio 1 — rilascio ordinario

```
09:00  backup verificato
09:10  tag v2.4.0, build, pubblicazione
09:20  migration landlord (2 additive)
09:25  deploy rolling, 3 container sostituiti a uno a uno
09:30  migration tenant a lotti da 10, 45 tenant, ~12 minuti
09:45  cache ricostruita, worker riavviati
09:50  smoke test verdi, verifica manuale dei tre percorsi critici
10:20  osservazione conclusa senza anomalie, comunicazione inviata
```

### Esempio 2 — rollback riuscito

Dopo 8 minuti dal deploy, il tasso di errore sale al 3%: una relazione non caricata provoca un
errore su un elenco molto usato.

Rollback dell'immagine in 4 minuti. Le migration erano additive, lo schema resta invariato.
Nessuna perdita di dati. La correzione viene rilasciata il giorno dopo, con il test che mancava.

---

## Best practice

- Rilasci piccoli e frequenti.
- Piano di rollback scritto prima del rilascio.
- Backup verificato, non solo eseguito.
- Migration additive separate da quelle distruttive.
- Mai il venerdì, mai durante le chiusure del cliente.
- Chi rilascia resta disponibile due ore.
- Comunicare i problemi mentre si risolvono, non dopo.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Rilascio senza piano di rollback | Ore di indisponibilità | Piano scritto prima |
| Backup non verificato | Ripristino impossibile quando serve | Verifica del ripristino |
| Migration distruttiva col codice | Rollback impossibile | Pattern in tre rilasci |
| Worker non riavviati | Codice vecchio in esecuzione | Riavvio nella sequenza |
| Rilascio il venerdì | Difetto per tutto il fine settimana | Finestra rispettata |
| Nessuna osservazione | Difetti scoperti dagli utenti | 30 minuti minimo |
| Silenzio durante un problema | Perdita di fiducia | Comunicazione immediata |

---

## Checklist

- [ ] Verifica in staging con dati realistici.
- [ ] Pipeline verde sul commit da rilasciare.
- [ ] Changelog completo.
- [ ] Migration provate in avanti e in rollback.
- [ ] Piano di rollback scritto.
- [ ] Backup eseguito e verificato.
- [ ] Cliente avvisato.
- [ ] Finestra di rilascio rispettata.
- [ ] Worker riavviati dopo il deploy.
- [ ] Smoke test e verifica manuale eseguiti.
- [ ] Osservazione di 30 minuti completata.
- [ ] Comunicazione di completamento inviata.

---

## Riferimenti

- [Ambienti](01-environments.md) · [Backup e ripristino](04-backup-and-restore.md)
- [Gestione degli incidenti](05-incident-management.md)
- [Deployment](../../deployment/README.md)
- [Checklist di rilascio](../../checklists/release-checklist.md)
- [Versionamento dei progetti](../02-conventions/04-project-versioning.md)
