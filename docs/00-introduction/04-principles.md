# I dodici principi

> I principi che governano ogni scelta tecnica della Factory, con le loro implicazioni pratiche
> e il modo in cui si verifica che siano rispettati.

---

## Indice

1. [Descrizione](#descrizione)
2. [Come si legge un principio](#come-si-legge-un-principio)
3. [I dodici principi](#i-dodici-principi-1)
4. [Conflitti tra principi](#conflitti-tra-principi)
5. [Esempi](#esempi)
6. [Best practice](#best-practice)
7. [Errori comuni](#errori-comuni)
8. [Checklist](#checklist)
9. [Riferimenti](#riferimenti)

---

## Descrizione

I principi non sono valori generici («scriviamo codice pulito»): sono **criteri di decisione**.
Servono nel momento in cui una scelta non è coperta da una regola esplicita, e qualcuno deve
decidere comunque.

Un principio utile ha tre caratteristiche:

1. È **discriminante**: esclude qualcosa. Se non esclude nulla, è un augurio.
2. Ha un **costo**: rispettarlo comporta rinunciare a qualcosa di attraente.
3. È **verificabile**: si può stabilire se un artefatto lo rispetta.

---

## Come si legge un principio

Ogni principio è descritto con questa struttura:

| Voce | Significato |
|---|---|
| **Enunciato** | La formulazione sintetica |
| **Perché** | Il problema che previene |
| **Implicazioni** | Cosa comporta concretamente |
| **Costo accettato** | Cosa si perde rispettandolo |
| **Verifica** | Come si controlla che sia rispettato |

---

## I dodici principi

### 1. Multitenancy sempre

**Enunciato.** Ogni software nasce multitenant, anche quando il primo cliente è uno solo.

**Perché.** Aggiungere la multitenancy a posteriori è una riscrittura: tocca schema, query,
autenticazione, storage, cache, code e test. Averla dall'inizio costa poco perché è già risolta
nella Foundation.

**Implicazioni.**
- Esistono sempre due connessioni: `landlord` e `tenant`.
- Ogni entità di dominio vive nel database tenant.
- Ogni chiave di cache, coda, storage e lock è prefissata dal tenant.
- I test hanno sempre almeno due tenant, per rilevare le fughe di dati.

**Costo accettato.** Maggiore complessità iniziale dell'infrastruttura; migration eseguite N volte;
sviluppo locale che richiede il provisioning di un tenant di prova.

**Verifica.** Test di architettura: nessun model di dominio usa la connessione `landlord`.

→ [`architecture/03-multitenancy-overview.md`](../../architecture/03-multitenancy-overview.md)

---

### 2. Isolamento dei dati prima di tutto

**Enunciato.** Un tenant non deve poter accedere ai dati di un altro, per costruzione e non per
attenzione.

**Perché.** È l'unico difetto che può chiudere l'azienda. Un data leak tra clienti in ambito
sanitario o fiscale è un evento non recuperabile, contrattualmente e reputazionalmente.

**Implicazioni.**
- Database fisicamente separati per tenant, non colonna discriminante.
- Nessuna query cross-tenant, nemmeno per diagnostica: si usano viste aggregate nel landlord.
- Il contesto tenant si risolve una volta, all'ingresso della richiesta, e non si modifica dopo.
- Ogni job in coda porta con sé il proprio tenant e lo ripristina all'esecuzione.
- I file caricati vivono in percorsi separati per tenant.

**Costo accettato.** Impossibilità di fare join tra tenant (che è il punto); backup più numerosi;
migration più lente; reportistica aggregata che richiede un percorso dedicato.

**Verifica.** Test automatici che, per ogni entità, verificano l'invisibilità dei dati dell'altro
tenant; test di architettura sull'uso delle connessioni.

→ [`architecture/06-tenant-resolution.md`](../../architecture/06-tenant-resolution.md)

---

### 3. Nessuna duplicazione

**Enunciato.** Se una soluzione serve una seconda volta, si valuta; alla terza, si promuove nella
Foundation.

**Perché.** Il codice duplicato non è un problema di eleganza: è un problema di **correzione**.
Un difetto in codice duplicato quattro volte va corretto quattro volte, e la quarta si dimentica.

**Implicazioni.**
- Il codice infrastrutturale vive nella Foundation, come dipendenza versionata.
- Non si copia codice tra progetti: si promuove.
- La duplicazione tra progetti è una metrica monitorata.

**Costo accettato.** L'attesa della terza occorrenza produce, temporaneamente, duplicazione
consapevole. È un costo inferiore a quello di un'astrazione sbagliata.

**Verifica.** Metrica «codice duplicato tra progetti» in
[`governance/quality-metrics.md`](../../governance/quality-metrics.md).

---

### 4. Moduli indipendenti

**Enunciato.** Un modulo si installa e si disinstalla senza rompere gli altri.

**Perché.** I gestionali crescono per aggiunta. Se i moduli si conoscono a vicenda, dopo due anni
non è più possibile aggiungere nulla senza toccare tutto.

**Implicazioni.**
- Un modulo comunica con gli altri tramite eventi e contratti, mai per accesso diretto ai model.
- Un modulo dichiara le proprie dipendenze in `module.json`.
- Le migration di un modulo sono reversibili.
- I test di un modulo girano senza gli altri moduli facoltativi installati.

**Costo accettato.** Comunicazione più indiretta; a volte una duplicazione minima di dati tra
moduli invece di una join.

**Verifica.** Test di architettura sulle dipendenze tra namespace di modulo.

→ [`architecture/10-modular-system.md`](../../architecture/10-modular-system.md)

---

### 5. Il dominio non conosce il framework

**Enunciato.** La logica di dominio non importa nulla da `Illuminate\*`.

**Perché.** Il dominio è la parte che vive più a lungo. Deve sopravvivere all'aggiornamento del
framework, e deve essere testabile senza avviare l'applicazione.

**Implicazioni.**
- Le entità di dominio e i value object sono PHP puro.
- I contratti (interfacce) stanno nel dominio, le implementazioni nell'infrastruttura.
- Niente Facade, niente helper globali, niente Eloquent nel dominio.
- I test di dominio sono unitari e istantanei.

**Costo accettato.** Più classi, più mappature tra entità e model, meno «magia» del framework.

**Verifica.** Test di architettura: il namespace `Domain\` non dipende da `Illuminate\`.

→ [`architecture/12-domain-layer.md`](../../architecture/12-domain-layer.md)

---

### 6. Ogni scrittura è un'Action

**Enunciato.** Ogni modifica di stato passa da una classe Action con un unico metodo pubblico.

**Perché.** Rende esplicito l'inventario delle operazioni possibili. Rende ogni operazione
testabile, riutilizzabile da HTTP, CLI, coda e Filament senza duplicazione.

**Implicazioni.**
- Un'Action per operazione, nome imperativo (`CreateInvoiceAction`).
- L'Action riceve un DTO validato, non una Request.
- L'Action è transazionale se tocca più aggregati.
- L'Action emette gli eventi di dominio.

**Costo accettato.** Molte classi piccole; overhead percepito per le operazioni banali.

**Verifica.** Test di architettura: le classi in `Actions\` sono `final`, hanno un solo metodo
pubblico e sono coperte al 100%.

→ [`rules/action-pattern.md`](../../rules/action-pattern.md)

---

### 7. Niente logica nei controller

**Enunciato.** Il controller traduce HTTP in un'invocazione di dominio e ritorna una risposta.
Nient'altro.

**Perché.** La logica nei controller non è riutilizzabile da CLI o da coda, non è testabile in
isolamento, e tende a crescere fino a diventare il vero cuore dell'applicazione.

**Implicazioni.**
- Validazione in Form Request, non nel controller.
- Autorizzazione in Policy, non con `if` nel controller.
- Serializzazione in API Resource.
- Il corpo del metodo è tipicamente di 3-6 righe.

**Costo accettato.** Più file per operazione.

**Verifica.** Test di architettura sul numero di righe e sulle dipendenze dei controller.

→ [`rules/laravel.md`](../../rules/laravel.md)

---

### 8. Test come contratto

**Enunciato.** Nessuna feature senza test. Nessun bug corretto senza test di regressione.

**Perché.** In un sistema che vive anni e viene modificato da persone e agenti diversi, i test
sono l'unica documentazione che non può mentire.

**Implicazioni.**
- Copertura complessiva ≥ 80%; Action e Policy al 100%.
- Ogni bug produce prima un test rosso, poi la correzione.
- Test di architettura per i vincoli strutturali.
- Test di isolamento tenant per ogni entità.

**Costo accettato.** Tempo di sviluppo maggiore nell'immediato; suite da mantenere.

**Verifica.** Soglie di copertura in pipeline; la pipeline blocca il merge.

→ [`rules/testing.md`](../../rules/testing.md)

---

### 9. Tutto tracciato

**Enunciato.** Le modifiche ai dati sensibili producono audit log; le azioni degli utenti
producono activity log.

**Perché.** Nei domini in cui operiamo (sanità, fiscale, documentale) la tracciabilità è un
requisito normativo, non una comodità diagnostica.

**Implicazioni.**
- Audit log immutabile, con valori precedenti e successivi, autore, momento e contesto.
- Activity log per le operazioni rilevanti anche quando non modificano dati.
- I log non contengono mai dati personali non necessari né segreti.
- Politica di conservazione definita per tipo di log.

**Costo accettato.** Scritture aggiuntive, spazio su disco, attenzione alla privacy.

**Verifica.** Ogni entità marcata come sensibile ha il test che verifica la scrittura dell'audit.

→ [`architecture/20-audit-activity-log.md`](../../architecture/20-audit-activity-log.md)

---

### 10. Sicuro per default

**Enunciato.** Ciò che non è esplicitamente permesso è negato.

**Perché.** Il modello opposto (permesso salvo divieto) produce falle ogni volta che qualcuno
dimentica un controllo — e qualcuno dimentica sempre.

**Implicazioni.**
- Policy che negano in assenza di regola esplicita.
- `Gate::before` solo per il super admin, con audit.
- Validazione a lista bianca: si dichiara ciò che è ammesso.
- Configurazione di produzione restrittiva (debug off, error reporting silenzioso, header di
  sicurezza attivi).
- Nessun endpoint pubblico non dichiarato.

**Costo accettato.** Configurazione iniziale più laboriosa; errori di autorizzazione durante lo
sviluppo.

**Verifica.** Test che, per ogni Policy, verificano il rifiuto in assenza di permesso.

→ [`rules/security.md`](../../rules/security.md)

---

### 11. Documentazione contestuale

**Enunciato.** Il documento vive accanto a ciò che descrive e viene aggiornato nello stesso commit.

**Perché.** La documentazione separata dal codice diverge. Non «può divergere»: diverge, sempre,
ed è questione di mesi.

**Implicazioni.**
- Ogni modulo ha il proprio README, aggiornato con il modulo.
- Ogni decisione ha la sua ADR, datata.
- Le modifiche a un contratto pubblico aggiornano la documentazione nello stesso commit.
- La documentazione è parte della definizione di «fatto».

**Costo accettato.** Ogni modifica richiede anche il lavoro redazionale.

**Verifica.** Revisione: una PR che cambia un contratto senza toccare la documentazione viene
respinta.

→ [`rules/documentation.md`](../../rules/documentation.md)

---

### 12. Automazione dei controlli

**Enunciato.** Una regola che non è verificabile automaticamente è, nel tempo, una regola che non
esiste.

**Perché.** Il controllo umano è discontinuo. Le regole verificate solo in code review vengono
applicate quando c'è tempo, e ignorate quando non c'è.

**Implicazioni.**
- Ogni regola dichiara come si verifica.
- Le regole verificabili sono in pipeline: Pint, PHPStan, Pest, Pest Arch, script di controllo.
- Le regole non automatizzabili stanno nelle checklist di revisione.
- Obiettivo: ≥ 50% delle regole automatizzate.

**Costo accettato.** Tempo speso a costruire e mantenere gli strumenti di verifica.

**Verifica.** Metrica «regole automatizzate» nella scheda di qualità.

→ [`deployment/ci/README.md`](../../deployment/ci/README.md)

---

## Conflitti tra principi

I principi possono entrare in tensione. Quando succede, si applica questa priorità:

```
1. Isolamento dei dati (principio 2)      ← non cede mai
2. Sicurezza per default (principio 10)
3. Test come contratto (principio 8)
4. Tutti gli altri
```

Il principio 2 è **assoluto**: nessuna esigenza di prestazioni, di semplicità o di scadenza
giustifica una violazione dell'isolamento.

### Tensioni ricorrenti

| Tensione | Come si risolve |
|---|---|
| Isolamento (2) vs prestazioni della reportistica | Aggregati calcolati e memorizzati nel landlord, senza dati identificativi; mai query cross-tenant dirette |
| Dominio puro (5) vs velocità di sviluppo | Il dominio puro vale per la logica di business; il CRUD semplice può usare Eloquent direttamente |
| Nessuna duplicazione (3) vs moduli indipendenti (4) | Meglio una piccola duplicazione tra moduli che un accoppiamento; se cresce, si promuove nella Foundation |
| Action per ogni scrittura (6) vs pragmatismo | Anche l'operazione banale ha la sua Action: la coerenza vale più della brevità |
| Tracciamento (9) vs prestazioni in scrittura | L'audit va in coda quando il volume è alto, mai eliminato |

---

## Esempi

### Esempio 1 — principio 2 applicato sotto pressione

Richiesta: «Serve un report che confronta i consumi di tutti i clienti.»

Soluzione **non** ammissibile: un comando che itera sulle connessioni tenant e fa una query
unificata dall'interfaccia amministrativa.

Soluzione ammissibile: ogni tenant pubblica periodicamente un aggregato anonimo verso una tabella
del landlord (`tenant_metrics`), tramite un job schedulato che gira **nel contesto del tenant**.
Il report legge solo il landlord. Nessuna query cross-tenant, nessun dato identificativo fuori dal
proprio database.

### Esempio 2 — principio 5 applicato con giudizio

Un'anagrafica di comuni italiani, in sola lettura, senza logica. Non serve un'entità di dominio,
un repository e un mapper: un model Eloquent con una query è sufficiente.

Il principio 5 protegge la **logica di business**, non impone cerimonia sul CRUD.

### Esempio 3 — principio 3 con la giusta pazienza

Il primo progetto scrive un `NotificaScadenzeJob`. Il secondo scrive qualcosa di simile ma con
canali diversi. Il terzo lo richiede ancora. **Ora** si promuove: nasce il modulo `notifications`
con canali configurabili. Se lo si fosse promosso al primo, il modulo avrebbe avuto la forma del
primo progetto e sarebbe stato inutilizzabile per il terzo.

---

## Best practice

- Citare il principio quando si respinge o si difende una scelta in revisione: rende la discussione
  oggettiva.
- Se un principio viene violato spesso e per buoni motivi, il principio è sbagliato: si corregge
  con una ADR, non si tollera in silenzio.
- Ogni nuova regola in `rules/` dichiara a quale principio risponde.
- Insegnare i principi prima delle regole: chi conosce i principi deduce le regole, non viceversa.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Trattare i principi come slogan | Non guidano nessuna decisione reale | Usarli esplicitamente nelle revisioni |
| Violare il principio 2 «solo per debug» | Rischio di data leak permanente | Nessuna eccezione, mai |
| Applicare il principio 5 al CRUD banale | Cerimonia inutile, sviluppo lento | Il dominio puro vale per la logica di business |
| Promuovere alla prima occorrenza (principio 3) | Astrazione sbagliata | Attendere la terza |
| Scrivere regole senza principio di riferimento | Regole arbitrarie, aggirate | Collegare regola → principio |
| Rimandare i test «per andare più veloci» | Debito che blocca ogni modifica futura | Il test è parte della feature |

---

## Checklist

- [ ] So enunciare i dodici principi e il problema che ciascuno previene.
- [ ] So qual è il principio che non cede mai (isolamento dei dati).
- [ ] Ogni regola che scrivo dichiara il principio a cui risponde.
- [ ] Quando respingo una scelta in revisione, cito il principio.
- [ ] Le violazioni ricorrenti diventano proposte di ADR, non eccezioni tacite.

---

## Riferimenti

- [Visione](01-vision.md) · [Che cos'è la AI Factory](02-what-is-ai-factory.md)
- [Indice delle regole](../../rules/README.md)
- [Architettura di riferimento](../../architecture/README.md)
- [Metriche di qualità](../../governance/quality-metrics.md)
- [Processo decisionale](../../governance/decision-process.md)
