# Workflow — rilascio

> Portare una versione in produzione, con la possibilità di tornare indietro in ogni momento.

| | |
|---|---|
| **Durata indicativa** | 1-2 ore, osservazione compresa |
| **Gate** | [`checklists/release-checklist.md`](../checklists/release-checklist.md) |

---

## Indice

1. [Descrizione](#descrizione) 2. [Quando si usa](#quando-si-usa) 2. [Prerequisiti](#prerequisiti) 3. [Sequenza](#sequenza)
4. [Rollback](#rollback) 5. [Osservazione](#osservazione) 6. [Comunicazione](#comunicazione)
7. [Esempi](#esempi) 8. [Best practice](#best-practice) 9. [Errori comuni](#errori-comuni)
10. [Checklist](#checklist) 11. [Riferimenti](#riferimenti)

---

## Descrizione

Un rilascio si prepara, si esegue e si verifica — e prima di tutto questo si stabilisce come si
torna indietro. Un rilascio senza percorso di ritorno provato non è un rilascio: è una scommessa.

Con un database per tenant il rilascio ha una proprietà che altrove non esiste: le migration girano
N volte, su N database che possono trovarsi in stati leggermente diversi. La durata va misurata
prima, su volumi reali, non scoperta durante.

Questo workflow descrive la sequenza completa; il gate corrispondente è la
[checklist di rilascio](../checklists/release-checklist.md).

---

## Quando si usa

Per ogni rilascio in produzione, di qualunque dimensione. Un rilascio «piccolo» non è un rilascio
diverso: è lo stesso rilascio con meno contenuto.

| Tipo | Preavviso | Finestra |
|---|---|---|
| Patch | nessuno | finestra ordinaria |
| Minor | 2 giorni | finestra ordinaria |
| Major | 2 settimane | concordata |
| Hotfix | immediato | qualunque momento |

---

## Prerequisiti

- [ ] Versione verificata in staging con dati realistici.
- [ ] Pipeline verde sul commit da rilasciare.
- [ ] Changelog completo, in linguaggio dell'utente.
- [ ] Migration provate in avanti **e** in rollback su staging.
- [ ] Durata delle migration tenant stimata.
- [ ] **Piano di rollback scritto.**
- [ ] Backup eseguito e **verificato**.
- [ ] Cliente avvisato secondo il preavviso.
- [ ] Chi rilascia disponibile per le due ore successive.
- [ ] Finestra rispettata (mai il venerdì, mai durante le chiusure del cliente).

Un backup non verificato non è un backup: è un file di cui si spera qualcosa.

---

## Sequenza

```
 1. Backup completo, verificato
 2. Tag annotato della versione
 3. Build dell'immagine e pubblicazione nel registro
 4. Migration landlord (solo additive)
 5. Deploy del codice (rolling, con health check)
 6. Migration tenant, a lotti, con monitoraggio
 7. Ricostruzione delle cache
 8. Riavvio dei worker (queue:restart)
 9. Verifica automatica: health check, smoke test
10. Verifica manuale dei percorsi critici
11. Osservazione per 30 minuti
12. Comunicazione di completamento
```

L'ordine dei passi 4-6 è **vincolante**: le migration additive non rompono il codice vecchio, quindi
durante il deploy convivono senza errori. L'ordine inverso produce errori nel tempo che intercorre.

Il passo 8 non è opzionale: i worker mantengono in memoria il codice caricato all'avvio.

---

## Rollback

| Scenario | Azione | Tempo atteso |
|---|---|---|
| Difetto senza migration | ripristino dell'immagine precedente | < 5 min |
| Difetto con migration additiva | ripristino del codice, schema invariato | < 5 min |
| Difetto con migration distruttiva | ripristino da backup | 30-120 min |
| Dati corrotti su un tenant | ripristino del singolo tenant | 15-60 min |

```bash
docker compose pull app:v2.3.9 && docker compose up -d --no-deps app
php artisan queue:restart
```

La differenza tra cinque minuti e due ore sta interamente nell'aver evitato le migration
distruttive: è la ragione pratica del pattern in tre rilasci.

---

## Osservazione

| Intervallo | Cosa si osserva |
|---|---|
| 0-5 min | health check, errori 5xx, code |
| 5-30 min | tempi di risposta, tasso di errore, job falliti |
| 30 min - 2 h | segnalazioni, log applicativi |
| 2-24 h | metriche aggregate, consumo risorse |
| 24 h - 7 gg | difetti latenti |

Soglie che impongono il **rollback immediato**: tasso di errore oltre l'1%, tempo di risposta
mediano raddoppiato, qualunque violazione di isolamento tenant.

---

## Comunicazione

**Prima** (secondo il preavviso): cosa cambia, quando, indisponibilità prevista, azioni richieste.

**Dopo**: completato, cosa è cambiato, come segnalare problemi.

**In caso di problema**: cosa è successo, cosa stiamo facendo, quando aggiorneremo — senza attendere
di avere la soluzione. Il silenzio durante un problema produce più danno del problema.

---

## Esempi

### Esempio 1 — rilascio ordinario

```
09:00  backup verificato
09:10  tag v2.4.0, build, pubblicazione
09:20  migration landlord (2 additive)
09:25  deploy rolling, 3 container sostituiti a uno a uno
09:30  migration tenant a lotti da 10: 45 tenant, ~12 minuti
09:45  cache ricostruita, worker riavviati
09:50  smoke test verdi, verifica manuale dei tre percorsi critici
10:20  osservazione conclusa senza anomalie, comunicazione inviata
```

### Esempio 2 — rollback riuscito

Otto minuti dopo il deploy il tasso di errore sale al 3%: una relazione non caricata provoca errori
su un elenco molto usato.

Rollback dell'immagine in quattro minuti. Le migration erano additive: schema invariato, nessuna
perdita di dati. La correzione viene rilasciata il giorno dopo, con il test che mancava.

---

## Best practice

- Rilasci piccoli e frequenti: la superficie di ciò che può rompersi è minore.
- Piano di rollback scritto **prima**, non improvvisato durante.
- Backup verificato, non solo eseguito.
- Migration additive separate da quelle distruttive.
- Chi rilascia resta disponibile due ore.
- Comunicare i problemi mentre si risolvono.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Rilascio senza piano di rollback | Ore di indisponibilità | Piano scritto prima |
| Backup non verificato | Ripristino impossibile quando serve | Verifica del ripristino |
| Migration distruttiva col codice | Rollback impossibile | Pattern in tre rilasci |
| Worker non riavviati | Codice vecchio in esecuzione | `queue:restart` |
| Migration tenant tutte insieme | Database saturato | Esecuzione a lotti |
| Rilascio il venerdì | Difetto per tutto il fine settimana | Finestra rispettata |
| Nessuna osservazione | Difetti scoperti dagli utenti | 30 minuti minimo |
| Silenzio durante un problema | Perdita di fiducia | Comunicazione immediata |

---

## Checklist

Completa in [`checklists/release-checklist.md`](../checklists/release-checklist.md).

- [ ] Prerequisiti tutti soddisfatti.
- [ ] Sequenza eseguita nell'ordine, senza passaggi manuali improvvisati.
- [ ] `queue:restart` eseguito.
- [ ] Smoke test e verifica manuale superati.
- [ ] Allineamento dello schema verificato.
- [ ] Osservazione di 30 minuti completata.
- [ ] Comunicazione di completamento inviata.

---

## Riferimenti

- [Workflow](README.md) · [Fase 13 — Deploy](14-phase-deploy.md)
- [Gestione dei rilasci](../docs/05-operations/02-release-management.md)
- [Deployment](../rules/deployment.md) · [Deployment (infrastruttura)](../deployment/README.md)
- [Checklist di rilascio](../checklists/release-checklist.md)
