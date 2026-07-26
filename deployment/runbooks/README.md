# Deployment — Runbook

> Le procedure da seguire quando qualcosa va storto, scritte quando non stava andando storto.

---

## Indice

1. [Descrizione](#descrizione)
2. [Come si scrive un runbook](#come-si-scrive-un-runbook)
3. [I runbook](#i-runbook)
4. [R1 — Sospetta fuga di dati tra tenant](#r1--sospetta-fuga-di-dati-tra-tenant)
5. [R2 — Ripristino di un tenant da backup](#r2--ripristino-di-un-tenant-da-backup)
6. [R3 — Coda ferma o in accumulo](#r3--coda-ferma-o-in-accumulo)
7. [R4 — Ritorno a una versione precedente](#r4--ritorno-a-una-versione-precedente)
8. [R5 — Migration bloccata a metà](#r5--migration-bloccata-a-metà)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Un runbook si scrive quando le cose funzionano, perché è l'unico momento in cui si ha la lucidità
per scriverlo. Durante un incidente non si progetta una procedura: si esegue quella che c'è, oppure
si improvvisa — e improvvisare su dati di clienti, sotto pressione, di notte, è il modo in cui un
disservizio diventa una perdita di dati.

Ogni runbook di questa pagina segue la stessa forma: **quando si applica**, **come si conferma il
sintomo**, **i passi in ordine**, **come si verifica di aver finito**, **cosa non fare**.

L'ultima sezione non è retorica. In quasi tutti gli incidenti esiste un'azione che sembra la più
ovvia e che peggiora la situazione in modo irreversibile.

---

## Come si scrive un runbook

| Requisito | Perché |
|---|---|
| Passi numerati, uno per riga | sotto pressione si legge una riga per volta |
| Comandi copiabili, senza segnaposto da indovinare | un segnaposto ambiguo si risolve male |
| Un criterio di verifica dopo ogni passo | proseguire senza conferma moltiplica gli errori |
| Una sezione «non fare» | l'azione ovvia è spesso quella che peggiora |
| Il punto di non ritorno dichiarato | serve sapere fin dove si può tornare indietro |
| Chi avvisare, e quando | l'informazione tardiva vale meno di quella parziale |

Un runbook non provato è una bozza. La prova si fa su staging, in un momento tranquillo, con
qualcuno che non l'ha scritto: è l'unico modo di scoprire i passaggi impliciti.

---

## I runbook

| # | Situazione | Gravità | Punto di non ritorno |
|---|---|---|---|
| [R1](#r1--sospetta-fuga-di-dati-tra-tenant) | sospetta fuga di dati tra tenant | **P1** | nessuno: è tutto diagnostico |
| [R2](#r2--ripristino-di-un-tenant-da-backup) | ripristino di un tenant da backup | P1 | il passo 6, che sovrascrive |
| [R3](#r3--coda-ferma-o-in-accumulo) | coda ferma o in accumulo | P2 | nessuno |
| [R4](#r4--ritorno-a-una-versione-precedente) | ritorno a una versione precedente | P1 | il passo 5, se ci sono migration |
| [R5](#r5--migration-bloccata-a-metà) | migration bloccata a metà su N tenant | P1 | il passo 4 |

---

## R1 — Sospetta fuga di dati tra tenant

**Quando.** Un cliente segnala dati che non gli appartengono, oppure un controllo rileva
un'anomalia. Fino a prova contraria è un **P1**: si tratta come confermato.

**Conferma del sintomo**

```bash
php artisan tenants:artisan "audit:recent --minutes=60" --tenant=<slug-che-segnala>
php artisan queue:failed
```

**Passi**

1. **Non spegnere nulla.** Un riavvio cancella lo stato dei worker, che è dove può esserci la prova.
2. Raccogliere: quale dato, in quale schermata, a che ora, quale utente.
3. Determinare **la via**, fra le cinque possibili:

   | Via | Come si verifica |
   |---|---|
   | cache | il dato è in una schermata memorizzata? `grep -rn "Cache::" app/` |
   | coda | è comparso dopo l'esecuzione di un job? `queue:failed`, log dei worker |
   | database | la connessione era quella giusta? log delle query, se attivi |
   | storage | è un file? verificare il percorso del disco |
   | comando CLI | è comparso dopo un comando pianificato? `schedule:list`, log |

4. Se è la **cache**: svuotare **solo** lo spazio del tenant colpito, non tutta la cache.

   ```bash
   php artisan tenants:artisan "cache:clear" --tenant=<slug>
   ```

5. Se è la **coda**: sospendere i worker della coda coinvolta, non tutti.

   ```bash
   php artisan queue:pause --queue=<coda>
   ```

6. Individuare il punto nel codice. La causa è quasi sempre una di queste quattro:
   una chiave di cache scritta a mano, un job senza `TenantAware`, un comando eseguito senza
   `tenants:artisan`, un aggregato verso il landlord che porta identificativi.
7. Correggere, aggiungere il test che il difetto avrebbe fatto fallire, rilasciare come
   [hotfix](../../workflows/21-hotfix-workflow.md).
8. **Notificare i tenant coinvolti.** Non è una scelta di opportunità: è un obbligo verso chi ci ha
   affidato i propri dati, e la tempistica è spesso normata.

**Verifica di aver finito**

- il test di isolamento che riproduceva il difetto è verde;
- `php artisan test --testsuite=Tenant` è verde;
- l'audit del tenant colpito non mostra ulteriori accessi anomali.

**Cosa non fare**

- **Non svuotare tutta la cache**: cancella la prova, e il difetto torna al prossimo popolamento
  senza che si sappia più da dove.
- **Non riavviare i worker** prima di aver letto i log: la coda dei falliti contiene lo slug del
  tenant, ed è l'informazione più diretta che esiste.
- **Non correggere in silenzio**: un incidente non registrato non è mai avvenuto, e la stessa causa
  si ripresenta senza che nessuno la riconosca.

---

## R2 — Ripristino di un tenant da backup

**Quando.** Perdita o corruzione dei dati di un cliente.

**Prerequisiti**

- il backup più recente è stato **verificato**, non solo prodotto;
- il tenant è stato messo in stato `suspended`, così nessuno scrive durante il ripristino;
- è dichiarato **quale momento** si sta ripristinando, e quali dati si perdono.

**Passi**

1. Sospendere il tenant.

   ```bash
   php artisan tenants:suspend --slug=<slug> --reason="ripristino in corso"
   ```

2. Individuare il backup e verificarne l'integrità.

   ```bash
   php artisan tenants:backup:list --slug=<slug>
   php artisan backup:verify --file=<file>
   ```

3. Ripristinare **su un database temporaneo**, mai direttamente su quello del cliente.

   ```bash
   mysql -u <utente> -p tenant_<slug>_restore < <file>
   ```

4. Verificare il contenuto: conteggi delle tabelle principali, ultima riga di ogni tabella
   operativa, coerenza con ciò che il cliente ricorda.
5. Concordare con il cliente la **perdita accettata**: le operazioni tra il momento del backup e
   quello del guasto non esistono più.
6. **PUNTO DI NON RITORNO.** Rinominare il database corrente e promuovere quello ripristinato.

   ```sql
   RENAME TABLE ... ;   -- oppure: rinominare i database
   ```

   Il database corrente **non si cancella**: si conserva per almeno trenta giorni. Contiene le
   operazioni perse, e potrebbero servire.
7. Ripristinare anche i **file**: un database ripristinato senza i file mostra documenti che non
   esistono.
8. Riattivare il tenant e verificare l'accesso.

   ```bash
   php artisan tenants:activate --slug=<slug>
   ```

9. Registrare l'incidente: momento ripristinato, dati persi, causa.

**Verifica di aver finito**

- accesso funzionante, dati coerenti con il momento dichiarato;
- i file corrispondono ai riferimenti nel database;
- il backup successivo comprende il tenant ripristinato.

**Cosa non fare**

- **Non ripristinare direttamente sul database del cliente**: se il backup è incompleto si perde
  anche ciò che restava.
- **Non cancellare il database corrotto**: è l'unica copia delle operazioni perse.
- **Non ripristinare solo il database**: senza i file, il sistema mostra documenti inesistenti.
- **Non riattivare prima di aver verificato**: il cliente scoprirebbe i problemi al posto nostro.

---

## R3 — Coda ferma o in accumulo

**Quando.** Un allarme segnala accumulo, oppure le notifiche non arrivano.

**Conferma del sintomo**

```bash
php artisan queue:monitor high,provisioning,default,notifications,documents,integrations,bulk
php artisan queue:failed
```

**Passi**

1. Distinguere i tre casi, che hanno cause diverse:

   | Sintomo | Causa tipica |
   |---|---|
   | accumulo su **tutte** le code | worker fermi, o Redis irraggiungibile |
   | accumulo su **una** coda | un job lento o bloccato su quella coda |
   | falliti in crescita | dipendenza esterna non disponibile, o difetto |

2. Verificare che i worker siano vivi e sulla versione giusta.

   ```bash
   docker compose ps worker
   docker compose logs --tail=100 worker
   ```

3. Se i worker eseguono codice vecchio — sintomo: un difetto corretto continua a manifestarsi solo
   in coda — è `queue:restart` dimenticato nel rilascio.

   ```bash
   php artisan queue:restart
   ```

4. Se una coda sola è in accumulo, cercare il job lento nei log e valutare se **spostarlo su
   `bulk`**: un'importazione da 100.000 righe su `default` ritarda le notifiche che qualcuno sta
   aspettando.
5. Se i falliti crescono per una dipendenza esterna non disponibile, **aspettare il rientro** e poi
   riprovare in blocco. Riprovare durante il disservizio consuma tentativi.

   ```bash
   php artisan queue:retry all
   ```

6. Esaminare i falliti **voce per voce**, non solo contarli: il conteggio nasconde la causa, e le
   cause sono spesso due o tre diverse mescolate.

**Verifica di aver finito**

- nessuna coda con accumulo in crescita;
- i falliti dell'ultima ora sono sotto la soglia di allarme;
- un job di prova attraversa la coda e viene eseguito.

**Cosa non fare**

- **Non svuotare la coda dei falliti** per «ripartire puliti»: sono operazioni dei clienti che non
  sono avvenute.
- **Non aumentare i worker** prima di aver capito la causa: se il problema è un job che si blocca,
  più worker significa più worker bloccati.
- **Non riprovare in blocco** mentre la dipendenza esterna è ancora giù.

---

## R4 — Ritorno a una versione precedente

**Quando.** Il criterio dichiarato nel piano di rilascio è stato raggiunto.

**Prima di iniziare**

Rispondere a una domanda sola: **il rilascio conteneva migration?**

| Risposta | Percorso |
|---|---|
| No, o solo di espansione | ritorno del **solo codice**: semplice e reversibile |
| Sì, di contrazione o con trasformazione di dati | il ritorno **non è** solo codice: passo 5 |

**Passi**

1. Sospendere lo scheduler.

   ```bash
   php artisan schedule:clear-cache
   docker compose stop scheduler
   ```

2. Riportare l'immagine alla versione precedente, identificata dal commit.

   ```bash
   docker compose up -d --no-deps app web worker
   ```

3. `queue:restart`, altrimenti i worker restano sul codice nuovo.
4. Verificare: controllo di salute, login su landlord e su un tenant, un percorso critico.
5. **PUNTO DI NON RITORNO — solo se c'erano migration non di espansione.** Il rollback delle
   migration su N tenant è lungo e non sempre reversibile. Se lo schema nuovo è compatibile con il
   codice vecchio, **non toccarlo**: si corregge in avanti.

   ```bash
   php artisan tenants:artisan "migrate:rollback --step=1" --chunk=50
   ```

6. Riattivare lo scheduler.
7. Registrare: cosa è stato ritirato, perché, cosa resta da correggere.

**Verifica di aver finito**

- controllo di salute verde su tutti i nodi;
- `migrate:status` coerente su tutti i tenant;
- code che smaltiscono, nessun errore nuovo nei log per 15 minuti.

**Cosa non fare**

- **Non annullare le migration se non è necessario**: uno schema con una colonna in più non rompe il
  codice vecchio, mentre un rollback su trentotto database può rompere tutto.
- **Non tornare indietro senza registrare il motivo**: il rilascio successivo ripeterebbe l'errore.
- **Non dimenticare `queue:restart`**: i worker resterebbero sul codice ritirato.

---

## R5 — Migration bloccata a metà

**Quando.** `tenants:migrate` si interrompe: alcuni tenant sono migrati, altri no.

È lo stato peggiore in cui un sistema multitenant possa trovarsi, perché non è omogeneo e non è
evidente **quale** parte sia in quale stato.

**Passi**

1. **Fermare tutto.** Sospendere lo scheduler e i worker: un job che gira su uno schema a metà
   migrazione produce dati incoerenti.
2. Determinare lo stato reale, tenant per tenant.

   ```bash
   php artisan tenants:artisan "migrate:status" --chunk=50 > /tmp/stato-migrazioni.txt
   ```

3. Classificare: migrati, non migrati, **falliti a metà**. Il terzo gruppo è quello che conta.
4. **PUNTO DI NON RITORNO.** Per i tenant falliti a metà: se la migration non era atomica — DDL su
   MySQL non lo è — il database è in uno stato intermedio. Ripristinare **quei soli tenant** da
   backup (R2) è più sicuro di correggere a mano.
5. Correggere la causa. Le tipiche, in ordine di frequenza: una colonna `NOT NULL` aggiunta a una
   tabella con dati, una migration che dipende da dati che un tenant non ha, un timeout sul tenant
   più grande.
6. Riprendere la migration **solo sui tenant non migrati**.

   ```bash
   php artisan tenants:migrate --tenant=<slug1> --tenant=<slug2> --force
   ```

7. Riattivare worker e scheduler.

**Verifica di aver finito**

- `migrate:status` allineato su **tutti** i tenant, verificato con un conteggio;
- un percorso critico provato su almeno un tenant per gruppo;
- il provisioning di un tenant nuovo funziona ancora.

**Cosa non fare**

- **Non rilanciare `tenants:migrate` su tutti** prima di aver classificato: sui tenant a metà
  peggiora lo stato.
- **Non correggere a mano lo schema** di un tenant per «allinearlo»: uno schema corretto a mano
  diverge dalle migration, e la divergenza si scopre al rilascio successivo.
- **Non lasciare i worker attivi** durante la correzione.

---

## Esempi

### Un runbook che ha funzionato

> Alle 23:40 un allarme segnala 340 job falliti in un'ora sulla coda `integrations`. R3, passo 1:
> accumulo su **una** coda sola. Passo 5: il servizio esterno risponde `503`. Si aspetta il rientro,
> alle 00:20 si esegue `queue:retry all`, tutti riusciti.
>
> Nessuna modifica al codice, nessun riavvio, nessuno svegliato. Il runbook ha risparmiato l'ipotesi
> più naturale delle 23:40 — «riavviamo i worker» — che non avrebbe cambiato nulla e avrebbe
> cancellato i log.

### Un runbook che non c'era

> Ripristino di un tenant eseguito direttamente sul database del cliente. Il backup era incompleto:
> mancava l'ultima tabella. Si è perso anche ciò che restava.
>
> Da quell'episodio: R2, passo 3 — **su un database temporaneo, mai direttamente**.

---

## Best practice

- Scrivere il runbook quando le cose funzionano.
- Provarlo su staging, con qualcuno che non l'ha scritto.
- Dichiarare il punto di non ritorno prima dei passi che lo superano.
- Scrivere «cosa non fare»: in quasi tutti gli incidenti l'azione ovvia peggiora la situazione.
- Aggiungere un passo ogni volta che un incidente rivela un'informazione mancante.
- Registrare ogni esecuzione: la storia degli incidenti è la prima cosa che si consulta.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Runbook mai provato | Non funziona quando serve | Prova su staging |
| Passi senza criterio di verifica | Si prosegue su un passo fallito | Verifica dopo ogni passo |
| Punto di non ritorno non dichiarato | Si supera senza saperlo | Dichiararlo |
| Nessuna sezione «non fare» | L'azione ovvia peggiora la situazione | Scriverla per prima |
| Cache svuotata per intera durante un'indagine | Cancella la prova | Solo lo spazio del tenant |
| Coda dei falliti svuotata | Operazioni dei clienti perse | Esaminare, non cancellare |
| Ripristino diretto sul database del cliente | Si perde anche ciò che restava | Database temporaneo |
| Database corrotto cancellato | Unica copia delle operazioni perse | Conservare 30 giorni |
| Rollback delle migration non necessario | Rischio su N database senza motivo | Solo se il codice vecchio non regge |
| Schema corretto a mano | Diverge dalle migration, si scopre al rilascio dopo | Correggere la migration |
| Incidente non registrato | La stessa causa si ripresenta irriconosciuta | Registro degli incidenti |

---

## Checklist

- [ ] Ogni runbook ha: quando, conferma, passi numerati, verifica, «cosa non fare».
- [ ] Il punto di non ritorno è dichiarato dove esiste.
- [ ] Ogni runbook è stato provato su staging da chi non l'ha scritto.
- [ ] I comandi sono copiabili, senza segnaposto ambigui.
- [ ] Ogni esecuzione viene registrata.
- [ ] Dopo ogni incidente il runbook è stato aggiornato con ciò che mancava.

---

## Riferimenti

- [Deployment](../README.md) · [Ambienti](../environments/README.md) · [Pipeline](../ci/README.md)
- [Gestione degli incidenti](../../docs/05-operations/05-incident-management.md)
- [Backup e ripristino](../../docs/05-operations/04-backup-and-restore.md) · [Operazioni sui tenant](../../docs/05-operations/06-tenant-operations.md)
- [Hotfix](../../workflows/21-hotfix-workflow.md) · [Rilascio](../../workflows/22-release-workflow.md)
- [Checklist di esercizio](../../checklists/operations-checklist.md) · [Risoluzione dei problemi](../../docs/06-reference/05-troubleshooting.md)
