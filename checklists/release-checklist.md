# Checklist — Rilascio

> L'ultima verifica prima che il codice raggiunga persone reali: preparazione, sequenza, verifica,
> ritorno indietro.

| | |
|---|---|
| **Fase** | 13 — Deploy |
| **Agente** | [Deploy Agent](../agents/14-deploy-agent.md) |
| **Natura** | gate bloccante |

---

## Indice

1. [Descrizione](#descrizione) 2. [Verifiche](#verifiche) 3. [Comandi di verifica](#comandi-di-verifica)
4. [Esempi](#esempi) 5. [Best practice](#best-practice) 6. [Errori comuni](#errori-comuni)
7. [Checklist](#checklist) 8. [Riferimenti](#riferimenti)

---

## Descrizione

Un rilascio si prepara, si esegue e si verifica — e prima di tutto questo si stabilisce **come si
torna indietro**. Un rilascio senza percorso di ritorno provato non è un rilascio: è una scommessa.

Con un database per tenant, il rilascio ha una proprietà che altrove non esiste: le migration girano
N volte. La durata va misurata **prima**, su volumi reali, non scoperta durante.

Ogni voce si verifica in modo oggettivo. Le voci si riportano **una per una**, con l'esito reale.

---

## Verifiche

### Prima del rilascio — preparazione

- [ ] Tutti i gate delle fasi precedenti sono verdi, quello di sicurezza incluso.
- [ ] `composer qa` verde sul commit che si rilascia, non su uno precedente.
- [ ] La suite gira verde su MySQL, non solo su SQLite.
- [ ] Il numero di versione è assegnato secondo SemVer e riportato nel `CHANGELOG.md`.
- [ ] Il `CHANGELOG.md` elenca le modifiche rivolte all'utente, non i commit.
- [ ] Le modifiche non retrocompatibili sono elencate, con la migrazione richiesta.
- [ ] Le deprecazioni introdotte hanno una data di rimozione.
- [ ] Le variabili d'ambiente nuove sono documentate e **impostate** sull'ambiente di destinazione.
- [ ] Nessun segreto nel commit; scansione dei segreti verde.
- [ ] Il rilascio è stato provato **integralmente su staging**, con dati simili a produzione.

### Migration

- [ ] Ogni migration implementa `down()`, provato almeno una volta.
- [ ] Nessuna trasformazione di dati dentro una migration di schema.
- [ ] Le trasformazioni di dati sono in comandi Artisan riprendibili e idempotenti.
- [ ] Le modifiche su tabelle con dati in produzione seguono il pattern in **tre rilasci**.
- [ ] Le migration di espansione precedono il deploy del codice; quelle di contrazione lo seguono
      di almeno un rilascio.
- [ ] La durata delle migration tenant è stata **misurata** su un tenant con volumi reali.
- [ ] La durata stimata complessiva (durata × numero di tenant, a lotti) è dichiarata.
- [ ] Il provisioning di un tenant **nuovo** funziona con lo schema di questa versione.
- [ ] I seeder di sistema sono idempotenti, verificati con doppia esecuzione.
- [ ] I nuovi permessi sono assegnati al ruolo amministratore dal seeder.

### Backup

- [ ] Il backup del landlord è recente e **verificato**.
- [ ] Il backup dei database tenant è recente e verificato.
- [ ] Il ripristino da backup è stato provato, non solo il backup.
- [ ] Il punto di ripristino è dichiarato nel piano di rilascio.

### Piano di ritorno

- [ ] Il percorso di ritorno è scritto, passo per passo, prima del rilascio.
- [ ] Il ritorno è stato **provato su staging**.
- [ ] È dichiarato se il ritorno richiede il rollback delle migration o solo del codice.
- [ ] È dichiarato il criterio che fa scattare il ritorno, in modo oggettivo.
- [ ] È dichiarato chi decide il ritorno e in quanto tempo.

### Sequenza di rilascio

- [ ] La sequenza è scritta e ordinata, senza passaggi impliciti.
- [ ] Le migration di espansione girano **prima** del deploy del codice.
- [ ] Le migration tenant girano a lotti (`--chunk`).
- [ ] `config:cache`, `route:cache`, `view:cache`, `event:cache` sono nella sequenza.
- [ ] `queue:restart` è nella sequenza, **dopo** il deploy del codice.
- [ ] Lo scheduler è sospeso durante il rilascio e riattivato alla fine.
- [ ] La modalità di manutenzione è usata solo se necessaria, con pagina dedicata e
      `--secret` per la verifica.

### Configurazione dell'ambiente di destinazione

- [ ] `APP_ENV=production`, `APP_DEBUG=false`.
- [ ] HTTPS con HSTS; header di sicurezza attivi.
- [ ] Telescope disabilitato; elenco directory disattivato.
- [ ] Redis raggiungibile; driver di cache, coda e sessione corretti.
- [ ] I dischi di storage puntano ai percorsi per tenant, privati.
- [ ] I worker girano sulla **stessa immagine** dell'applicazione web.
- [ ] Il numero di worker per coda è dichiarato e adeguato.

### Dopo il rilascio — verifica

- [ ] Il controllo di salute risponde correttamente.
- [ ] Login funzionante su landlord e su almeno un tenant reale.
- [ ] Un percorso critico per modulo è stato provato manualmente.
- [ ] Le migration risultano applicate su **tutti** i tenant, verificate con un conteggio.
- [ ] Le code smaltiscono: nessun accumulo anomalo, nessun picco di job falliti.
- [ ] I log non mostrano errori nuovi nei 15 minuti successivi.
- [ ] I tempi di risposta sono in linea con quelli precedenti al rilascio.
- [ ] Lo scheduler è tornato attivo e il primo comando pianificato è andato a buon fine.
- [ ] Il rilascio è annotato: versione, ora, esito, anomalie.

---

## Comandi di verifica

```bash
composer qa                                          # sul commit che si rilascia
php artisan test --env=testing-mysql

# misura preventiva, su un tenant con volumi reali
php artisan tenants:migrate --tenant=<slug> --pretend
php artisan tenants:migrate --tenant=<slug> --force   # in staging

# sequenza (estratto)
php artisan down --render=errors::503 --secret=<placeholder>
php artisan migrate --database=landlord --force
php artisan tenants:migrate --chunk=50 --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
php artisan up

# verifica successiva
php artisan tenants:artisan "migrate:status" --chunk=50
php artisan queue:monitor default,high,notifications
```

Il piano di ritorno si scrive **prima**, non durante. Un rilascio il cui percorso di ritorno non è
stato provato su staging non supera questo gate.

---

## Esempi

### Esito conforme

```markdown
### Quality gate
- checklists/release-checklist.md: N/N soddisfatte.

Versione: 1.4.0. Modifiche non retrocompatibili: nessuna.
Migration: 3 di espansione, nessuna contrazione, nessuna trasformazione di dati.
Durata misurata su tenant con volumi reali: 4,2 s. Stima su 38 tenant a lotti di 10: ~1 min.
Backup landlord e tenant verificati con ripristino di prova.
Piano di ritorno: solo rollback del codice (migration di sola espansione). Provato su staging.
Prova integrale su staging: completata.

Verifica successiva al rilascio:
- Controllo di salute: verde. Login landlord e tenant `acme`: riusciti.
- `migrate:status`: 38/38 tenant allineati.
- Code: nessun accumulo, 0 job falliti in 15 minuti.
- Log: nessun errore nuovo. Tempi di risposta invariati.
```

### Esito con voci non soddisfatte

```markdown
### Quality gate — rilascio sospeso
- checklists/release-checklist.md: N-2/N soddisfatte.

Voci non soddisfatte:
- Nessuna trasformazione di dati in migration: `2026_07_20_add_vat_number` popola la colonna
  con un `UPDATE` su tutte le righe. Su un tenant con 40.000 righe richiede 18 s; su 38 tenant
  supera gli 11 minuti, e il rollback non è possibile.
  Correzione: migration di sola espansione, popolamento con comando Artisan riprendibile,
  secondo il pattern in tre rilasci.
- Piano di ritorno provato: il ritorno non è stato eseguito su staging.
  Correzione: eseguirlo e riportarne l'esito.

Il rilascio è sospeso fino alla correzione di entrambe.
```

---

## Best practice

- Scrivere il piano di ritorno prima del piano di rilascio.
- Misurare la durata delle migration su un tenant reale, mai stimarla.
- Preferire migration di sola espansione: rendono il ritorno una questione di solo codice.
- Rilasciare in una fascia oraria in cui c'è qualcuno che può intervenire.
- Non rilasciare due modifiche rischiose insieme: se qualcosa va storto, non si sa quale.
- Annotare ogni rilascio: la storia dei rilasci è la prima cosa che si consulta durante un
  incidente.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Nessun piano di ritorno | Si improvvisa durante un disservizio | Scritto e provato prima |
| Ritorno mai provato | Il percorso non funziona quando serve | Prova su staging |
| Durata delle migration stimata | Disservizio molto più lungo del previsto | Misura su volumi reali |
| Trasformazione di dati in migration | Deploy lentissimo, rollback impossibile | Comando dedicato |
| Migration di contrazione nello stesso rilascio | Il ritorno del codice rompe l'applicazione | Rilascio successivo |
| `queue:restart` dimenticato | I worker eseguono il codice vecchio | Nella sequenza |
| Scheduler attivo durante il rilascio | Comandi su schema a metà migrazione | Sospeso e riattivato |
| Variabile d'ambiente non impostata | Errore in produzione al primo utilizzo | Verificata prima |
| Backup non verificato | Si scopre inutilizzabile quando serve | Prova di ripristino |
| Nessuna verifica dopo il rilascio | Il difetto lo segnala il cliente | Verifica strutturata |
| Provisioning di tenant nuovo non provato | I nuovi clienti non si attivano più | Prova in CI |

---

## Checklist

- [ ] Tutti i gate precedenti sono verdi.
- [ ] Il rilascio è stato provato integralmente su staging.
- [ ] La durata delle migration è stata misurata su volumi reali.
- [ ] Backup verificati con una prova di ripristino.
- [ ] Il piano di ritorno è scritto, provato e con criterio di attivazione dichiarato.
- [ ] La sequenza di rilascio è scritta, senza passaggi impliciti.
- [ ] Ho eseguito la verifica successiva al rilascio e ne ho riportato l'esito.
- [ ] Ho annotato il rilascio.
- [ ] Se il gate è rosso, ho sospeso il rilascio invece di proseguire.

---

## Riferimenti

- [Fase 13](../workflows/14-phase-deploy.md) · [Deploy Agent](../agents/14-deploy-agent.md)
- [Workflow di rilascio](../workflows/22-release-workflow.md) · [Hotfix](../workflows/21-hotfix-workflow.md)
- [Regole di deployment](../rules/deployment.md) · [Migration](../rules/database.md) · [Queue](../rules/queue.md)
- [Gestione dei rilasci](../docs/05-operations/02-release-management.md) · [Ambienti](../docs/05-operations/01-environments.md)
- [Backup e ripristino](../docs/05-operations/04-backup-and-restore.md)
- [Versionamento](../governance/versioning.md)
