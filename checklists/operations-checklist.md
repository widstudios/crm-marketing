# Checklist — Esercizio

> Le verifiche periodiche che tengono in vita un sistema multitenant: backup, code, spazio, log,
> tenant, segreti.

| | |
|---|---|
| **Quando** | quotidiana, settimanale, mensile, trimestrale — secondo la cadenza indicata |
| **Chi la applica** | chi ha la responsabilità di esercizio; il [Deploy Agent](../agents/14-deploy-agent.md) la predispone |
| **Natura** | verifica ricorrente, non legata a un rilascio |

---

## Indice

1. [Descrizione](#descrizione) 2. [Verifiche](#verifiche) 3. [Comandi di verifica](#comandi-di-verifica)
4. [Esempi](#esempi) 5. [Best practice](#best-practice) 6. [Errori comuni](#errori-comuni)
7. [Checklist](#checklist) 8. [Riferimenti](#riferimenti)

---

## Descrizione

I guasti di un sistema in esercizio raramente arrivano all'improvviso. Quasi sempre c'è stato un
periodo in cui erano visibili e nessuno guardava: il disco che si riempie da settimane, la coda dei
falliti che cresce, il backup che fallisce in silenzio da quindici giorni, il certificato che scade
fra sei giorni.

Questa checklist esiste per rendere quel periodo utile. Non sostituisce il monitoraggio automatico:
lo verifica. La domanda a cui risponde non è «ci sono allarmi?», ma «gli allarmi funzionerebbero se
ci fosse un problema?».

Ogni voce si verifica in modo oggettivo. Le voci si riportano **una per una**, con l'esito reale.

---

## Verifiche

### Quotidiane

- [ ] Il backup del landlord della notte è **presente**, con dimensione plausibile.
- [ ] I backup dei database tenant della notte sono presenti, per **tutti** i tenant attivi.
- [ ] Nessun backup fallito senza che sia stato notato.
- [ ] I job falliti nelle ultime 24 ore sono sotto la soglia di allarme (50/ora).
- [ ] Nessuna coda ha un accumulo in crescita costante.
- [ ] Nessun worker è fermo o in ciclo di riavvio.
- [ ] Lo scheduler ha eseguito tutti i comandi pianificati, con esito.
- [ ] Nessun errore nuovo nei log rispetto al giorno precedente.
- [ ] Il controllo di salute risponde su tutti i nodi.

### Settimanali

- [ ] Lo spazio disco è sotto il 75% su tutti i volumi, storage dei tenant incluso.
- [ ] La crescita dei database è in linea con l'andamento atteso; nessun tenant anomalo.
- [ ] I tempi di risposta dei percorsi principali sono in linea con la settimana precedente.
- [ ] I job più lenti sono stati esaminati; nessuno è peggiorato senza spiegazione.
- [ ] La coda dei falliti è stata esaminata voce per voce, non solo contata.
- [ ] I certificati TLS scadono a più di 30 giorni.
- [ ] L'audit delle dipendenze è verde; nessuna vulnerabilità di gravità alta aperta.
- [ ] Le patch di sicurezza pubblicate nella settimana sono state valutate entro **72 ore**.

### Mensili

- [ ] **Prova di ripristino reale** da backup, su un ambiente separato, di almeno un tenant.
- [ ] Il tempo di ripristino misurato è entro l'obiettivo dichiarato.
- [ ] I dati ripristinati sono stati verificati, non solo il completamento del comando.
- [ ] Il ripristino del landlord è stato provato almeno una volta nel trimestre.
- [ ] Gli account con accesso di piattaforma sono stati riesaminati; nessun accesso non più
      necessario.
- [ ] Gli accessi del personale ai dati dei tenant sono stati riesaminati nell'audit.
- [ ] I token API attivi sono stati riesaminati; quelli inutilizzati da 90 giorni sono revocati.
- [ ] Le soglie di allarme sono state riviste alla luce dei volumi attuali.
- [ ] È stato verificato che almeno un allarme si attivi davvero, provocandolo di proposito.
- [ ] I tenant sospesi o disattivati da oltre il periodo di conservazione sono stati trattati
      secondo la politica.

### Trimestrali

- [ ] I segreti sono stati ruotati secondo la politica: chiavi applicative, credenziali dei
      database, token dei servizi esterni.
- [ ] Il piano di ritorno di un rilascio è stato provato su staging, anche senza un rilascio in
      corso.
- [ ] La procedura di gestione degli incidenti è stata provata con un caso simulato.
- [ ] La documentazione di esercizio è stata riletta e corretta dove non corrisponde più.
- [ ] I cinque punti dell'isolamento tenant sono stati riverificati sul codice attuale.
- [ ] La politica di conservazione dei dati è stata verificata: nessun dato conservato oltre il
      previsto.
- [ ] Le versioni di PHP, del framework e delle dipendenze principali sono ancora supportate.

### Su ogni nuovo tenant

- [ ] Il database è stato creato e le migration risultano applicate.
- [ ] I seeder di sistema sono stati eseguiti: permessi e ruoli presenti.
- [ ] Il primo accesso dell'amministratore del tenant funziona.
- [ ] Il disco di storage del tenant esiste, è privato ed è scrivibile.
- [ ] Il tenant è incluso nel backup della notte successiva — **verificato**, non presunto.
- [ ] Il tenant compare nel monitoraggio.

---

## Comandi di verifica

```bash
# backup
php artisan tenants:backup:status --since=24h
php artisan backup:verify --database=landlord

# code e scheduler
php artisan queue:monitor high,default,notifications,documents,integrations,bulk
php artisan queue:failed
php artisan schedule:list

# stato dei tenant
php artisan tenants:artisan "migrate:status" --chunk=50
php artisan tenants:list --with=size,last-backup,status

# infrastruttura
df -h
php artisan health:check
composer audit
```

Due verifiche non hanno un comando e sono quelle che contano di più:

1. **il ripristino provato davvero**, su un ambiente separato, con controllo dei dati ripristinati —
   un backup mai ripristinato non è un backup, è un file;
2. **un allarme provocato di proposito** — è l'unico modo di sapere se il monitoraggio avviserebbe
   qualcuno.

---

## Esempi

### Esito settimanale conforme

```markdown
### Verifiche di esercizio — settimana 2026-30
Quotidiane: 7/7 eseguite, tutte verdi.
Backup: 38/38 tenant + landlord, tutte le notti.
Job falliti: 12 in 7 giorni, tutti su `integrations` (servizio esterno non disponibile
il 22/07, rientrato). Riprovati con esito positivo.
Disco: 61% (+2% rispetto alla settimana precedente).
Certificati: scadenza più vicina 2026-11-02.
`composer audit`: nessuna vulnerabilità nota.
```

### Esito con anomalie

```markdown
### Verifiche di esercizio — settimana 2026-31

Voci non soddisfatte:
- Backup tenant presenti per tutti: mancano 3 notti su `globex`. Il comando esce con
  codice 0 nonostante il fallimento, quindi nessun allarme è scattato.
  Azione: correggere il codice di uscita, aggiungere l'allarme su backup mancante,
  eseguire subito un backup di `globex`.
- Spazio disco sotto il 75%: volume storage all'87%, +9% in una settimana.
  Causa: un tenant carica scansioni non ridimensionate.
  Azione: ridimensionamento lato server e politica di conservazione degli allegati.

Entrambe aperte come attività tracciate.
```

---

## Best practice

- Verificare il ripristino, non il backup: sono due cose diverse e solo una serve davvero.
- Provocare un allarme di proposito ogni mese: un monitoraggio mai provato è una supposizione.
- Guardare le **tendenze**, non i valori istantanei: l'87% di oggi conta meno del +9% in una
  settimana.
- Esaminare la coda dei falliti voce per voce: il conteggio nasconde la causa.
- Annotare ogni verifica, anche quando è tutto verde: la storia serve durante gli incidenti.
- Trasformare in allarme automatico ogni anomalia trovata a mano due volte.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Backup mai ripristinato | Si scopre inutilizzabile quando serve | Prova mensile reale |
| Backup fallito in silenzio | Nessun dato per giorni, senza saperlo | Allarme su backup mancante |
| Allarmi mai provati | Nessuno viene avvisato al momento giusto | Provocarne uno di proposito |
| Valori istantanei senza tendenza | Il problema si vede solo quando è tardi | Confronto settimanale |
| Job falliti solo contati | La causa resta sconosciuta e si ripete | Esame voce per voce |
| Nuovo tenant non verificato nel backup | Il tenant più recente è il meno protetto | Verifica esplicita |
| Certificati non monitorati | Disservizio totale alla scadenza | Allarme a 30 giorni |
| Segreti mai ruotati | Una credenziale trapelata resta valida per anni | Rotazione trimestrale |
| Token API mai riesaminati | Accessi attivi di integrazioni dismesse | Revoca dei non usati |
| Verifiche non annotate | Durante un incidente non si sa cosa era normale | Registro delle verifiche |
| Anomalia corretta a mano ogni volta | Il tempo si consuma senza risolvere | Automatizzare al secondo caso |

---

## Checklist

- [ ] Ho eseguito le verifiche della cadenza corrente, voce per voce.
- [ ] Ho verificato il ripristino, non solo la presenza del backup.
- [ ] Ho esaminato la coda dei falliti voce per voce.
- [ ] Ho confrontato i valori con quelli del periodo precedente.
- [ ] Ho annotato l'esito, anche dove tutto è verde.
- [ ] Ho aperto un'attività tracciata per ogni anomalia.
- [ ] Ho automatizzato ciò che ho trovato a mano per la seconda volta.

---

## Riferimenti

- [Operazioni sui tenant](../docs/05-operations/06-tenant-operations.md)
- [Monitoraggio e log](../docs/05-operations/03-monitoring-and-logging.md)
- [Backup e ripristino](../docs/05-operations/04-backup-and-restore.md)
- [Gestione degli incidenti](../docs/05-operations/05-incident-management.md)
- [Osservabilità](../architecture/25-observability.md) · [Ciclo di vita del tenant](../architecture/07-tenant-lifecycle.md)
- [Rilascio](release-checklist.md) · [Sicurezza](security-checklist.md)
