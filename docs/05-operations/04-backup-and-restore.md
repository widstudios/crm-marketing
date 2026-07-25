# Backup e ripristino

> Come si protegge il dato di ogni singolo cliente e come si verifica, prima che serva, che il
> ripristino funzioni davvero.

---

## Indice

1. [Descrizione](#descrizione)
2. [Obiettivi di ripristino](#obiettivi-di-ripristino)
3. [Cosa si salva](#cosa-si-salva)
4. [Strategia multitenant](#strategia-multitenant)
5. [Frequenza e conservazione](#frequenza-e-conservazione)
6. [Verifica dei backup](#verifica-dei-backup)
7. [Procedure di ripristino](#procedure-di-ripristino)
8. [Sicurezza dei backup](#sicurezza-dei-backup)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Un backup mai ripristinato è un'ipotesi, non una garanzia. La domanda che conta non è «facciamo i
backup?» ma «**quando abbiamo ripristinato l'ultima volta, e quanto ci è voluto?**».

La multitenancy con database separati ha qui un vantaggio decisivo: il ripristino di un singolo
cliente non tocca gli altri. È uno dei motivi per cui la strategia di isolamento è stata scelta.

---

## Obiettivi di ripristino

| Parametro | Definizione | Obiettivo standard |
|---|---|---|
| **RPO** (Recovery Point Objective) | quanti dati si possono perdere | ≤ 1 ora |
| **RTO** (Recovery Time Objective) | in quanto tempo si torna operativi | ≤ 4 ore |
| RTO per singolo tenant | ripristino di un solo cliente | ≤ 1 ora |
| RTO per disastro completo | ricostruzione totale | ≤ 8 ore |

Questi valori vanno **concordati con il cliente** e scritti nel contratto: un RPO di un'ora
significa che, nel caso peggiore, si perde un'ora di lavoro. Il cliente deve saperlo prima, non
scoprirlo durante un incidente.

---

## Cosa si salva

| Elemento | Frequenza | Priorità |
|---|---|---|
| Database landlord | ogni 6 ore + continuo (binlog) | critica |
| Database di ogni tenant | giornaliero + continuo | critica |
| File caricati (per tenant) | giornaliero incrementale | alta |
| Configurazione (senza segreti) | ad ogni modifica, versionata | media |
| Segreti | gestore dedicato, con proprio backup | critica |
| Immagini Docker | conservate nel registro | media |
| Log di audit | inclusi nel backup del tenant | critica (obbligo legale) |

Ciò che **non** si salva: cache, sessioni, code, file temporanei, log applicativi ordinari.
Sono ricostruibili, e includerli allunga inutilmente la finestra di backup.

---

## Strategia multitenant

```
Backup landlord ──▶ archivio cifrato ──▶ conservazione 90 giorni
                                    └──▶ copia geografica separata

Backup tenant (N) ──▶ un archivio per tenant ──▶ conservazione secondo il piano
                                            └──▶ copia geografica separata
```

Vantaggi dell'archivio separato per tenant:

- ripristino di un singolo cliente senza toccare gli altri;
- esportazione dei dati su richiesta (diritto alla portabilità) immediata;
- cancellazione dei dati di un cliente cessato, backup compresi;
- verifica del ripristino su un tenant di prova, senza rischi.

```bash
php artisan backup:run --database=landlord
php artisan tenants:backup                      # tutti i tenant
php artisan tenants:backup --tenant=acme        # un tenant
```

---

## Frequenza e conservazione

| Tipo | Frequenza | Conservazione |
|---|---|---|
| Completo | giornaliero, di notte | 7 giorni |
| Completo settimanale | domenica | 4 settimane |
| Completo mensile | primo del mese | 12 mesi |
| Incrementale / continuo | continuo (binlog) | 7 giorni |
| Pre-rilascio | prima di ogni deploy | 7 giorni |
| Su richiesta | prima di operazioni rischiose | 30 giorni |

La copia geografica separata è obbligatoria per i backup mensili: un incidente che coinvolge il
datacenter primario non deve portarsi via anche le copie.

---

## Verifica dei backup

**Un backup non verificato non è un backup.** La verifica è pianificata, non occasionale.

| Verifica | Frequenza | Come |
|---|---|---|
| Il backup è stato prodotto | giornaliera, automatica | allarme critico se manca |
| L'archivio è integro | giornaliera, automatica | checksum |
| Il ripristino funziona | **settimanale** | ripristino di un tenant in ambiente isolato |
| Il ripristino completo funziona | **trimestrale** | esercitazione con tempi misurati |
| I tempi rispettano RPO/RTO | trimestrale | misurazione durante l'esercitazione |

```bash
# Verifica settimanale automatica
php artisan backup:verify --tenant=random --target=verification
```

L'esercitazione trimestrale si svolge come se fosse reale, con i tempi misurati: è l'unico modo
per sapere se l'RTO dichiarato è realistico.

---

## Procedure di ripristino

### Ripristino di un singolo tenant

```bash
# 1. Sospendere il tenant (impedisce scritture durante il ripristino)
php artisan tenant:suspend acme --reason="Ripristino in corso"

# 2. Ripristinare il database
php artisan tenant:restore acme --backup=2026-07-24-03-00

# 3. Ripristinare i file
php artisan tenant:restore-files acme --backup=2026-07-24-03-00

# 4. Verificare l'integrità
php artisan tenant:verify acme

# 5. Riattivare
php artisan tenant:resume acme
```

Tempo atteso: 15-60 minuti secondo la dimensione.

### Ripristino completo

1. Ricostruire l'infrastruttura (Docker, rete, storage).
2. Ripristinare il landlord.
3. Ripristinare i tenant **in ordine di priorità contrattuale**, non alfabetico.
4. Verificare l'integrità di ciascuno.
5. Riattivare gradualmente, monitorando.
6. Comunicare ai clienti man mano che rientrano.

L'ordine di priorità va deciso **prima**, non durante l'emergenza.

### Ripristino puntuale

Con i binlog attivi è possibile tornare a un istante preciso, per esempio prima di una
cancellazione accidentale:

```bash
php artisan tenant:restore acme --backup=2026-07-24-03-00 --until="2026-07-24 14:32:00"
```

---

## Sicurezza dei backup

| Aspetto | Regola |
|---|---|
| Cifratura a riposo | obbligatoria |
| Cifratura in transito | obbligatoria |
| Chiavi | conservate separatamente dai backup |
| Accesso | limitato, tracciato |
| Copia geografica | in una regione diversa |
| Immutabilità | i backup recenti non sono cancellabili (protezione da ransomware) |
| Test di ripristino | in ambiente isolato, mai in produzione |

I backup contengono **tutti** i dati dei clienti in un unico posto: sono l'obiettivo più
appetibile dell'intera infrastruttura, e vanno protetti di conseguenza.

---

## Esempi

### Esempio 1 — cancellazione accidentale

Un utente cancella un anno di movimenti con un'operazione massiva errata alle 14:32.

```bash
php artisan tenant:suspend acme --reason="Ripristino dati"
php artisan tenant:restore acme --backup=2026-07-24-03-00 --until="2026-07-24 14:31:00"
php artisan tenant:verify acme
php artisan tenant:resume acme
```

Perdita: le operazioni tra le 14:31 e il momento del ripristino. Gli altri 49 tenant non si
accorgono di nulla.

### Esempio 2 — la verifica che ha evitato il disastro

Durante la verifica settimanale, il ripristino di un tenant fallisce: gli archivi degli ultimi
dieci giorni sono troncati per un disco pieno sul server di backup. Il file esisteva, la
dimensione sembrava plausibile, l'allarme «backup prodotto» era verde.

Senza la verifica, il problema sarebbe emerso al primo ripristino reale.

---

## Best practice

- Verificare il ripristino ogni settimana, non solo l'esecuzione del backup.
- Esercitazione completa ogni trimestre, con tempi misurati.
- Un archivio per tenant.
- Backup pre-rilascio sempre, e verificato.
- Copia geografica separata per i backup a lunga conservazione.
- Ordine di priorità di ripristino deciso in anticipo.
- Backup cifrati, chiavi conservate altrove.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Backup mai ripristinato | Si scopre che non funziona quando serve | Verifica settimanale |
| Backup unico per tutti i tenant | Impossibile ripristinare un solo cliente | Archivio per tenant |
| Backup non cifrati | Tutti i dati esposti in caso di accesso | Cifratura obbligatoria |
| Chiavi insieme ai backup | La cifratura non protegge | Conservazione separata |
| Nessuna copia geografica | Un incidente porta via anche le copie | Regione diversa |
| RPO/RTO non concordati | Aspettative disattese durante l'incidente | Scritti nel contratto |
| Nessun allarme sul fallimento | Giorni senza backup, non rilevati | Allarme critico |

---

## Checklist

- [ ] Backup giornaliero di landlord e di ogni tenant.
- [ ] Backup continuo (binlog) attivo.
- [ ] Un archivio separato per tenant.
- [ ] Backup cifrati, chiavi conservate separatamente.
- [ ] Copia geografica per i backup mensili.
- [ ] Allarme critico sul fallimento del backup.
- [ ] Verifica del ripristino settimanale.
- [ ] Esercitazione completa trimestrale con tempi misurati.
- [ ] RPO e RTO concordati con il cliente e documentati.
- [ ] Ordine di priorità di ripristino definito.

---

## Riferimenti

- [Gestione degli incidenti](05-incident-management.md)
- [Operazioni sui tenant](06-tenant-operations.md)
- [Database tenant](../../architecture/05-tenant-databases.md)
- [Runbook operativi](../../deployment/runbooks/README.md)
- [Checklist operativa](../../checklists/operations-checklist.md)
