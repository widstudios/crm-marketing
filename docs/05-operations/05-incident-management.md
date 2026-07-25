# Gestione degli incidenti

> Cosa si fa quando qualcosa si rompe in produzione: priorità, ruoli, comunicazione e analisi
> successiva.

---

## Indice

1. [Descrizione](#descrizione)
2. [Classificazione](#classificazione)
3. [Ruoli durante un incidente](#ruoli-durante-un-incidente)
4. [La sequenza](#la-sequenza)
5. [Mitigazione prima della diagnosi](#mitigazione-prima-della-diagnosi)
6. [Comunicazione](#comunicazione)
7. [Incidenti di sicurezza](#incidenti-di-sicurezza)
8. [Post-mortem](#post-mortem)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Durante un incidente la capacità di ragionare si riduce e il tempo pesa. Per questo la procedura
è scritta prima: non perché sia complessa, ma perché serve qualcosa da seguire quando è difficile
pensare con lucidità.

Due principi guidano tutto:

1. **Ripristinare il servizio viene prima di capire la causa.**
2. **Comunicare durante, non dopo.**

---

## Classificazione

| Gravità | Definizione | Esempi | Risposta |
|---|---|---|---|
| **P1 — Critico** | servizio non disponibile o dati a rischio | applicazione ferma, violazione di isolamento, perdita di dati | immediata, 24/7 |
| **P2 — Alto** | funzionalità principale compromessa | impossibile registrare movimenti, login non funzionante | entro 1 ora, in orario |
| **P3 — Medio** | funzionalità secondaria compromessa | un report non si genera, notifiche in ritardo | entro 1 giorno |
| **P4 — Basso** | disagio senza impatto operativo | difetto grafico, testo errato | prossimo rilascio |

Due condizioni che sono **sempre P1**, indipendentemente da quanti utenti coinvolgono:

- qualunque sospetto di accesso ai dati di un altro tenant;
- qualunque perdita o corruzione di dati.

---

## Ruoli durante un incidente

| Ruolo | Responsabilità |
|---|---|
| **Coordinatore** | decide, non esegue; mantiene la visione d'insieme |
| **Tecnico** | diagnostica e interviene |
| **Comunicatore** | aggiorna clienti e team; protegge il tecnico dalle interruzioni |
| **Scriba** | annota cronologia, azioni, esiti |

Su un P3 una sola persona può ricoprire tutti i ruoli. Su un P1 la separazione tra coordinatore e
tecnico è ciò che evita l'errore più comune: la persona che sta diagnosticando viene interrotta
ogni tre minuti per aggiornamenti e non conclude nulla.

---

## La sequenza

```
 1. Rilevamento          allarme o segnalazione
 2. Classificazione      gravità, secondo la tabella
 3. Attivazione          ruoli assegnati, canale dedicato aperto
 4. Mitigazione          ripristino del servizio, anche senza capire la causa
 5. Comunicazione        primo aggiornamento entro 15 minuti (P1)
 6. Diagnosi             causa individuata
 7. Correzione           soluzione definitiva
 8. Verifica             il servizio funziona, i dati sono integri
 9. Chiusura             comunicazione finale
10. Post-mortem          entro 5 giorni lavorativi
```

I passi 4 e 6 sono in quest'ordine deliberatamente: si ripristina prima, si capisce dopo.

---

## Mitigazione prima della diagnosi

| Sintomo | Mitigazione immediata |
|---|---|
| Difetto introdotto dall'ultimo rilascio | rollback |
| Una funzionalità causa errori | disabilitazione della funzionalità |
| Un tenant satura le risorse | limitazione o sospensione temporanea |
| Coda bloccata | sospensione della coda problematica, ripresa delle altre |
| Database sovraccarico | limitazione del traffico, blocco delle operazioni pesanti |
| Integrazione esterna ferma | modalità degradata, accodamento delle richieste |
| Sospetta violazione di isolamento | **blocco immediato** dell'accesso interessato |

L'ultima riga non ha alternative: davanti al sospetto di una fuga di dati tra tenant si blocca
prima e si indaga poi, anche a costo di un fermo del servizio.

---

## Comunicazione

| Momento | P1 | P2 | P3 |
|---|---|---|---|
| Primo avviso | 15 min | 1 h | 1 giorno |
| Aggiornamenti | ogni 30 min | ogni 2 h | quotidiano |
| Chiusura | immediata | entro 2 h | nel rilascio |

Struttura di un aggiornamento:

```
Cosa sta succedendo:  L'inserimento dei movimenti restituisce errore per tutti i tenant.
Da quando:            14:12
Chi è coinvolto:      tutti gli utenti
Cosa stiamo facendo:  ripristino della versione precedente in corso
Prossimo aggiornamento: entro le 14:45
```

Non si comunica una causa finché non è confermata: un'ipotesi comunicata come certezza va
corretta dopo, e ogni correzione erode la fiducia.

---

## Incidenti di sicurezza

Procedura aggiuntiva, oltre a quella ordinaria:

1. **Contenere**: bloccare l'accesso, revocare i token, isolare i sistemi.
2. **Preservare le prove**: log, immagini dei sistemi, tracce. Non ripulire.
3. **Valutare l'esposizione**: quali dati, quanti tenant, per quanto tempo.
4. **Informare il responsabile** e la direzione, immediatamente.
5. **Valutare gli obblighi di notifica**: per dati personali, 72 ore al Garante.
6. **Informare i clienti coinvolti**, con quanto è noto.
7. **Correggere** la vulnerabilità.
8. **Post-mortem** con azioni correttive obbligatorie.

Al punto 2 si sbaglia spesso: la reazione istintiva è «pulire», ed è esattamente ciò che distrugge
le prove necessarie a capire l'accaduto e a dimostrare cosa è stato fatto.

---

## Post-mortem

Obbligatorio per ogni P1 e P2, entro cinque giorni lavorativi.

**Senza attribuzione di colpa.** Non per gentilezza, ma per efficacia: se le persone temono
conseguenze, le informazioni che servono non emergono, e il prossimo incidente sarà uguale.

Struttura:

```markdown
# Post-mortem — <titolo>

## Sintesi
Una frase: cosa è successo e quale è stato l'impatto.

## Impatto
Utenti coinvolti, tenant, durata, dati persi.

## Cronologia
| Ora | Evento |
|---|---|
| 14:12 | primo errore nei log |
| 14:18 | allarme scattato |
| 14:22 | incidente aperto, P1 |
| 14:35 | rollback completato, servizio ripristinato |
| 15:40 | causa individuata |

## Causa
Perché è successo. Non «errore umano»: perché il sistema ha permesso quell'errore.

## Cosa ha funzionato
L'allarme ha rilevato il problema in 6 minuti. Il rollback ha richiesto 4 minuti.

## Cosa non ha funzionato
Il caso non era coperto dai test. La verifica in staging non usava dati con quella caratteristica.

## Azioni correttive
| Azione | Responsabile | Scadenza |
|---|---|---|
| Test di regressione sul caso specifico | — | entro 2 giorni |
| Dati di staging estesi al caso | — | entro 1 settimana |
| Allarme sul tempo di risposta dell'elenco | — | entro 1 settimana |
```

«Errore umano» non è mai una causa accettabile: se una persona ha potuto causare l'incidente con
un'azione ordinaria, la causa è che il sistema lo permetteva.

---

## Esempi

### Esempio 1 — P1 gestito bene

```
14:12  primi errori 500 sull'inserimento movimenti
14:18  allarme «tasso di errore > 5%»
14:22  incidente P1 aperto, ruoli assegnati
14:25  primo avviso ai clienti
14:28  decisione: rollback, causa da individuare dopo
14:35  rollback completato, errori azzerati
14:40  comunicazione di ripristino
15:40  causa individuata: relazione non caricata su un percorso specifico
16:30  correzione e test di regressione pronti
giorno dopo  rilascio della correzione
+3 giorni    post-mortem con tre azioni correttive
```

### Esempio 2 — sospetto di violazione di isolamento

Un utente segnala di vedere un fornitore che non riconosce.

Reazione corretta: P1 immediato, blocco dell'accesso interessato, verifica dei log, ricostruzione
dell'accaduto. Si scopre che l'utente ha accesso a due tenant e aveva cambiato contesto senza
accorgersene: nessuna violazione.

Il sospetto è stato trattato come un P1 fino a prova contraria, ed è la reazione giusta anche
quando l'esito è innocuo.

---

## Best practice

- Ripristinare prima, capire dopo.
- Separare coordinatore e tecnico sui P1.
- Comunicare entro 15 minuti, anche senza avere risposte.
- Non comunicare cause non confermate.
- Annotare tutto durante, non ricostruire dopo.
- Post-mortem senza colpe, con azioni e scadenze.
- Verificare che le azioni correttive siano state completate.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Diagnosticare prima di mitigare | Disservizio prolungato | Mitigazione immediata |
| Nessuna comunicazione fino alla soluzione | Perdita di fiducia | Aggiornamenti periodici |
| Comunicare cause non confermate | Smentite successive | Solo fatti verificati |
| Interrompere il tecnico per aggiornamenti | Diagnosi che non avanza | Ruolo del comunicatore |
| «Pulire» durante un incidente di sicurezza | Prove distrutte | Preservare tutto |
| Post-mortem con colpevole | Le informazioni smettono di emergere | Analisi senza colpe |
| Azioni correttive senza scadenza | L'incidente si ripete | Responsabile e scadenza |

---

## Checklist

**Durante**
- [ ] Gravità classificata.
- [ ] Ruoli assegnati (P1 e P2).
- [ ] Mitigazione applicata prima della diagnosi.
- [ ] Prima comunicazione entro i tempi previsti.
- [ ] Cronologia annotata in tempo reale.

**Dopo**
- [ ] Servizio verificato, integrità dei dati confermata.
- [ ] Comunicazione di chiusura inviata.
- [ ] Post-mortem entro 5 giorni.
- [ ] Azioni correttive con responsabile e scadenza.
- [ ] Verifica del completamento delle azioni.

---

## Riferimenti

- [Monitoraggio e log](03-monitoring-and-logging.md) · [Backup e ripristino](04-backup-and-restore.md)
- [Gestione dei rilasci](02-release-management.md)
- [Runbook operativi](../../deployment/runbooks/README.md)
- [Guida alla sicurezza](../04-quality/05-security-guide.md)
