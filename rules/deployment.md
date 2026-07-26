# Regole — Deployment

> Rilascio senza interruzione, rollback in cinque minuti, migration a lotti.

---

## Indice

1. [Descrizione](#descrizione)
2. [Prerequisiti](#prerequisiti)
3. [Sequenza](#sequenza)
4. [Migration](#migration)
5. [Rollback](#rollback)
6. [Finestre](#finestre)
7. [Osservazione](#osservazione)
8. [Esempi](#esempi)
9. [Best practice](#best-practice)
10. [Errori comuni](#errori-comuni)
11. [Checklist](#checklist)
12. [Riferimenti](#riferimenti)

---

## Descrizione

Un rilascio si progetta attorno a una domanda: **come torniamo indietro se qualcosa va storto?** Se
la risposta non esiste o richiede più di dieci minuti, il rilascio non è pronto.

---

## Prerequisiti

**R1.** Nessun rilascio senza: pipeline verde, verifica in staging, changelog aggiornato, backup
verificato, piano di rollback scritto.
*Verifica:* checklist di rilascio. *Livello: vincolante.*

**R2.** Il backup pre-rilascio è **verificato**, non solo eseguito.
*Motivo:* un backup non verificato è un file di cui si spera qualcosa.
*Verifica:* esito della verifica.

**R3.** Le migration sono provate in avanti **e** in rollback su staging.
*Verifica:* esito su staging.

**R4.** La durata delle migration tenant è stata stimata su staging.
*Verifica:* misurazione.

**R5.** Chi rilascia resta disponibile per le due ore successive.
*Verifica:* prassi.

---

## Sequenza

```
 1. Backup completo, verificato
 2. Tag della versione
 3. Build dell'immagine e pubblicazione nel registro
 4. Migration landlord (solo additive)
 5. Deploy del codice (compatibile con schema vecchio e nuovo)
 6. Migration tenant, a lotti, con monitoraggio
 7. Ricostruzione delle cache
 8. Riavvio dei worker
 9. Verifica automatica (health check, smoke test)
10. Verifica manuale dei percorsi critici
11. Osservazione per 30 minuti
12. Comunicazione di completamento
```

**R6.** Le migration additive precedono il deploy del codice.
*Motivo:* non rompono il codice vecchio, quindi durante il deploy convivono senza errori.
*Verifica:* script di deploy. *Livello: vincolante.*

**R7.** `queue:restart` è obbligatorio dopo il deploy.
*Motivo:* i worker mantengono in memoria il codice caricato all'avvio.
*Verifica:* script di deploy. *Livello: vincolante.*

**R8.** La sequenza è **automatizzata**: nessun passaggio manuale.
*Verifica:* script di deploy.

**R9.** Il rilascio avviene senza interruzione, salvo migrazioni di dati non evitabili, comunicate in
anticipo.
*Verifica:* prassi.

---

## Migration

**R10.** Le migration distruttive (`DROP`, `RENAME`) non si rilasciano insieme al codice che smette
di usare la colonna: si applica il pattern in tre rilasci.
*Motivo:* è la differenza tra un rollback di cinque minuti e un ripristino da backup di due ore.
*Verifica:* revisione del piano. *Livello: vincolante.*

**R11.** Le migration tenant girano a lotti, con pausa tra i lotti.
*Motivo:* cento `ALTER TABLE` simultanei saturano il database.
*Verifica:* script di deploy.

**R12.** Se una migration fallisce su un tenant, i lotti successivi si **fermano**.
*Motivo:* non propagare un problema noto. *Verifica:* logica dello script.

**R13.** L'allineamento dello schema si verifica dopo ogni deploy.
*Verifica:* `tenants:migrate:status` nello script.

---

## Rollback

| Scenario | Azione | Tempo atteso |
|---|---|---|
| Difetto senza migration | ripristino dell'immagine precedente | < 5 min |
| Difetto con migration additiva | ripristino del codice, schema invariato | < 5 min |
| Difetto con migration distruttiva | ripristino da backup | 30-120 min |
| Dati corrotti su un tenant | ripristino del singolo tenant | 15-60 min |

**R14.** Il piano di rollback è scritto **prima** del rilascio.
*Verifica:* checklist. *Livello: vincolante.*

**R15.** Il rollback del codice non richiede rollback dello schema, se si è rispettato R10.
*Verifica:* prassi.

**R16.** Dopo un rollback si registra l'accaduto e si pianifica l'analisi.
*Verifica:* post-mortem.

---

## Finestre

| Momento | Ammesso |
|---|---|
| Martedì-giovedì, 9:00-16:00 | sì |
| Lunedì | sconsigliato |
| Venerdì | **no** |
| Chiusure contabili del cliente | **no** |
| Fuori orario | solo hotfix |

**R17.** Nessun rilascio ordinario il venerdì.
*Motivo:* un difetto resterebbe fino al lunedì. *Verifica:* prassi. *Livello: vincolante.*

**R18.** Il preavviso al cliente è di 2 giorni per una minor, 2 settimane per una major.
*Verifica:* prassi.

---

## Osservazione

| Intervallo | Cosa si osserva |
|---|---|
| 0-5 min | health check, errori 5xx, code |
| 5-30 min | tempi di risposta, tasso di errore, job falliti |
| 30 min - 2 h | segnalazioni, log applicativi |
| 2-24 h | metriche aggregate, consumo risorse |

**R19.** Soglie che impongono il rollback immediato: tasso di errore oltre l'1%, tempo di risposta
mediano raddoppiato, qualunque violazione di isolamento tenant.
*Verifica:* monitoraggio. *Livello: vincolante.*

**R20.** L'osservazione dura almeno **30 minuti** prima di considerare concluso il rilascio.
*Verifica:* prassi.

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
10:20  osservazione conclusa, comunicazione inviata
```

### Esempio 2 — rollback riuscito

Otto minuti dopo il deploy il tasso di errore sale al 3%: una relazione non caricata provoca errori
su un elenco molto usato.

Rollback dell'immagine in quattro minuti. Le migration erano additive: schema invariato, nessuna
perdita di dati. Correzione rilasciata il giorno successivo, con il test che mancava.

---

## Best practice

- Rilasci piccoli e frequenti: la superficie di ciò che può rompersi è minore.
- Automatizzare l'intera sequenza: i passaggi manuali si dimenticano sotto pressione.
- Provare il rollback su staging almeno una volta per trimestre.
- Comunicare i problemi mentre si risolvono, non dopo.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Rilascio senza piano di rollback | Ore di indisponibilità | Piano scritto prima |
| Backup non verificato | Ripristino impossibile quando serve | Verifica del ripristino |
| Migration distruttiva con il codice | Rollback impossibile | Pattern in tre rilasci |
| Worker non riavviati | Codice vecchio in esecuzione | `queue:restart` |
| Migration tenant tutte insieme | Database saturato | Esecuzione a lotti |
| Lotti proseguiti dopo un fallimento | Problema propagato | Arresto immediato |
| Rilascio il venerdì | Difetto per tutto il fine settimana | Finestra rispettata |
| Nessuna osservazione | Difetti scoperti dagli utenti | 30 minuti minimo |

---

## Checklist

- [ ] Pipeline verde, verifica in staging completata.
- [ ] Changelog aggiornato, cliente avvisato.
- [ ] Backup eseguito e verificato.
- [ ] Migration provate in avanti e in rollback.
- [ ] Piano di rollback scritto.
- [ ] Migration additive prima del deploy del codice.
- [ ] Migration tenant a lotti, con arresto in caso di fallimento.
- [ ] Cache ricostruita, worker riavviati.
- [ ] Smoke test e verifica manuale eseguiti.
- [ ] Allineamento dello schema verificato.
- [ ] Osservazione di 30 minuti completata.
- [ ] Finestra di rilascio rispettata.

---

## Riferimenti

- [Gestione dei rilasci](../docs/05-operations/02-release-management.md)
- [Deployment](../deployment/README.md) · [Ambienti](../docs/05-operations/01-environments.md)
- [Database](database.md) · [Queue](queue.md)
- [Checklist di rilascio](../checklists/release-checklist.md)
