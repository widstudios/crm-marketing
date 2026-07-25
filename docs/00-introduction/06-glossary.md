# Glossario

> Il vocabolario comune della Factory. Quando due persone usano la stessa parola per cose diverse,
> il progetto diverge prima del codice.

---

## Indice

1. [Descrizione](#descrizione)
2. [Termini di piattaforma](#termini-di-piattaforma)
3. [Termini di multitenancy](#termini-di-multitenancy)
4. [Termini di architettura](#termini-di-architettura)
5. [Termini di dominio e persistenza](#termini-di-dominio-e-persistenza)
6. [Termini di processo](#termini-di-processo)
7. [Termini di qualità](#termini-di-qualità)
8. [Termini di esercizio](#termini-di-esercizio)
9. [Parole da non usare](#parole-da-non-usare)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Questo glossario è **normativo sui nomi**: i termini elencati vanno usati con il significato qui
definito, sia nella documentazione sia negli identificatori di codice. Un sinonimo introdotto per
varietà stilistica costa più di quanto renda.

Per ogni termine è indicata anche la forma usata **nel codice** (in inglese), perché il codice non
si scrive in italiano. Vedi [`docs/02-conventions/03-language-policy.md`](../02-conventions/03-language-policy.md).

---

## Termini di piattaforma

| Termine | Codice | Definizione |
|---|---|---|
| **AI Factory** | — | Questo repository: piattaforma di documentazione, regole, agenti, template e codice riutilizzabile con cui si producono i software aziendali |
| **Foundation** | `widstudios/foundation` | Il pacchetto PHP riutilizzabile installato da ogni progetto. Contiene tutto ciò che non va riscritto |
| **Progetto generato** | — | Un'applicazione prodotta seguendo la Factory. Vive in un repository proprio |
| **Modulo** | `Module` | Unità funzionale autonoma, installabile e disinstallabile, con database, backend, frontend, API, test e documentazione propri |
| **Modulo di catalogo** | — | Modulo generico mantenuto nella Factory e riusabile da qualsiasi progetto |
| **Modulo di dominio** | — | Modulo specifico di un progetto, che vive nel repository del progetto |
| **Template** | `.stub` | File con segnaposto istanziato per generare un artefatto |
| **Artefatto** | — | Qualsiasi output producibile: una classe, un documento, una migration, una pipeline |
| **Agente** | — | Ruolo AI specializzato con responsabilità, input, output e limiti definiti |
| **Orchestratore** | — | L'agente che coordina gli altri ed esegue il master workflow |
| **Quality gate** | — | Insieme di condizioni verificabili che devono essere vere perché una fase possa considerarsi conclusa |
| **Deroga** | — | Eccezione locale a una regola della Factory, dichiarata, motivata e con scadenza |
| **Promozione** | — | Spostamento di un artefatto dal progetto alla Factory, dopo la terza occorrenza |

---

## Termini di multitenancy

| Termine | Codice | Definizione |
|---|---|---|
| **Tenant** | `Tenant` | Il cliente: l'entità che possiede un insieme isolato di dati e utenti |
| **Landlord** | `landlord` | Il contesto di piattaforma: database e applicazione che gestiscono i tenant, i piani e la configurazione globale |
| **Database landlord** | connessione `landlord` | Database unico che contiene tenant, domini, piani, licenze, utenti di piattaforma, metriche aggregate |
| **Database tenant** | connessione `tenant` | Database dedicato a un singolo tenant, contenente tutti i suoi dati di dominio |
| **Contesto tenant** | `TenantContext` | Lo stato che indica quale tenant è attivo nella richiesta, nel job o nel comando corrente |
| **Risoluzione del tenant** | `TenantResolver` | Il processo che identifica il tenant dalla richiesta (dominio, sottodominio, header, token) |
| **Bootstrap del tenant** | `TenantBootstrapper` | La riconfigurazione dei servizi (database, cache, storage, code) sul tenant risolto |
| **Provisioning** | — | Creazione di un nuovo tenant: database, migration, seed, utente amministratore, storage |
| **Deprovisioning** | — | Dismissione di un tenant: esportazione dati, sospensione, cancellazione |
| **Tenant-scoped** | — | Aggettivo di ciò che è delimitato al tenant corrente: chiavi di cache, percorsi di storage, code |
| **Cross-tenant** | — | Operazione che attraversa più tenant. **Vietata** nelle query dirette |
| **Super Admin** | `super_admin` | Utente di piattaforma, esiste nel landlord, amministra i tenant |
| **Tenant Admin** | `tenant_admin` | Utente amministratore all'interno di un singolo tenant |

---

## Termini di architettura

| Termine | Codice | Definizione |
|---|---|---|
| **Dominio** | `Domain\` | Il livello che contiene entità, value object, eventi e contratti. Non conosce il framework |
| **Applicazione** | `Application\` | Il livello che orchestra il dominio: Action, DTO, Query, Service |
| **Infrastruttura** | `Infrastructure\` | Le implementazioni tecniche: Eloquent, HTTP, filesystem, provider esterni |
| **Presentazione** | `Http\`, `Filament\`, `Console\` | I punti di ingresso: controller, risorse Filament, comandi |
| **Action** | `...Action` | Classe con un solo metodo pubblico che esegue **una** mutazione di stato |
| **Query object** | `...Query` | Classe che incapsula una lettura complessa e ritorna una proiezione |
| **Service** | `...Service` | Classe che coordina più Action o incapsula logica tecnica riutilizzabile |
| **Repository** | `...Repository` | Astrazione della persistenza: ritorna entità o aggregati, mai proiezioni arbitrarie |
| **DTO** | `...Data` | Oggetto immutabile che trasporta dati validati tra i livelli |
| **Value object** | — | Oggetto identificato dal proprio valore, immutabile e auto-validante |
| **Entità** | — | Oggetto identificato da un'identità stabile nel tempo |
| **Aggregato** | — | Gruppo di entità con una radice, che si modifica come unità transazionale |
| **Evento di dominio** | `...Event` | Fatto accaduto nel dominio, al passato (`InvoiceIssued`) |
| **Listener** | `...Listener` | Reazione a un evento, tipicamente asincrona |
| **Policy** | `...Policy` | Classe che decide se un utente può compiere un'azione su una risorsa |
| **Bounded context** | — | Confine entro cui un termine di dominio ha un significato univoco |

---

## Termini di dominio e persistenza

| Termine | Codice | Definizione |
|---|---|---|
| **Model** | `Model` | Classe Eloquent: dettaglio di persistenza, non entità di dominio |
| **Migration** | — | Modifica versionata dello schema. Esistono migration landlord e migration tenant, separate |
| **Seeder** | `...Seeder` | Popolamento di dati iniziali. Distinguere dati **di sistema** (obbligatori) da dati **di prova** |
| **Factory** | `...Factory` | Generatore di dati di test |
| **Soft delete** | — | Cancellazione logica. Ammessa solo dove il dominio la richiede, mai come default |
| **Audit log** | `audit_logs` | Registro immutabile delle modifiche ai dati sensibili: chi, cosa, prima, dopo, quando |
| **Activity log** | `activity_log` | Registro delle azioni degli utenti, anche senza modifica di dati |
| **Scope** | — | Vincolo applicato a una query. Il *tenant scope* è l'unico scope globale ammesso |

---

## Termini di processo

| Termine | Codice | Definizione |
|---|---|---|
| **`loop crea`** | — | Il comando che avvia la generazione completa di un progetto |
| **Fase** | — | Segmento del master workflow con input, output e quality gate propri |
| **Master workflow** | — | La sequenza completa delle 14 fasi, dalla fondazione al deploy |
| **Project Brief** | — | Documento compilato prima di `loop crea`, con dominio, vincoli e obiettivi |
| **ADR** | — | Architecture Decision Record: decisione tecnica registrata con alternative e conseguenze |
| **Prompt di fase** | — | Il prompt che istruisce l'agente responsabile di una fase |
| **Handoff** | — | Passaggio di consegne tra due agenti, con artefatti dichiarati |
| **Rework** | — | Ritorno a una fase precedente a seguito di un quality gate fallito |

---

## Termini di qualità

| Termine | Codice | Definizione |
|---|---|---|
| **Test unitario** | `tests/Unit` | Verifica una classe in isolamento, senza database né framework |
| **Test di feature** | `tests/Feature` | Verifica un comportamento end-to-end attraverso i livelli |
| **Test di architettura** | `tests/Architecture` | Verifica vincoli strutturali (dipendenze, naming, finalità delle classi) |
| **Test di isolamento** | — | Verifica che un tenant non veda i dati di un altro |
| **Copertura** | — | Percentuale di linee eseguite dai test. Soglia complessiva 80%, Action e Policy 100% |
| **Baseline** | — | Elenco di violazioni statiche tollerate. È debito tecnico dichiarato, non una soluzione |
| **Code review** | — | Revisione umana o AI del codice, guidata da checklist |
| **Claude Reviewer** | — | Agente di revisione indipendente, che rilegge l'output degli altri agenti |

---

## Termini di esercizio

| Termine | Codice | Definizione |
|---|---|---|
| **Ambiente** | `local`, `testing`, `staging`, `production` | Contesto di esecuzione con configurazione propria |
| **Rilascio** | — | Pubblicazione di una versione in un ambiente |
| **Rollback** | — | Ritorno alla versione precedente |
| **Zero-downtime** | — | Rilascio senza interruzione del servizio |
| **Runbook** | — | Procedura operativa passo-passo per una situazione ricorrente |
| **Incidente** | — | Evento che degrada il servizio per gli utenti |
| **Post-mortem** | — | Analisi di un incidente, senza attribuzione di colpa, con azioni correttive |
| **Finestra di rilascio** | — | Intervallo temporale in cui è ammesso rilasciare |
| **Health check** | — | Endpoint che dichiara lo stato dell'applicazione e delle sue dipendenze |

---

## Parole da non usare

| Da evitare | Perché | Usare invece |
|---|---|---|
| «cliente» per indicare il tenant | Ambiguo: il cliente può essere anche un'entità di dominio | **tenant** (piattaforma), **customer** (dominio) |
| «utente admin» | Non distingue i due livelli | **super admin** o **tenant admin** |
| «manager» in un nome di classe | Non dice cosa fa | `...Service`, `...Action`, `...Repository` |
| «helper», «utils», «common» | Cartelle che raccolgono tutto e non significano nulla | Namespace che dichiara la responsabilità |
| «temporaneo» in una soluzione | Diventa permanente e non tracciato | Deroga dichiarata con scadenza |
| «legacy» per codice recente | Svuota il termine | Nome preciso del problema |
| «best practice» senza fonte | Non verificabile | Citare la regola della Factory |
| «dovrebbe» in una regola | Ambiguo | «deve» oppure «può», mai «dovrebbe» |
| «handle», «process», «do» come nomi di metodo | Non dicono nulla | Verbo specifico del dominio |

---

## Best practice

- Usare **un solo termine** per concetto, in tutto il repository e in tutti i progetti.
- Quando si introduce un termine nuovo, aggiungerlo qui **nello stesso commit**.
- I nomi di classe e tabella derivano dal glossario, non da traduzioni improvvisate.
- Se il cliente usa un termine diverso per lo stesso concetto, mapparlo esplicitamente nel
  glossario del progetto, senza cambiare i nomi tecnici.
- Preferire il termine del dominio a quello tecnico quando entrambi sono corretti: `Invoice`
  batte `BillingRecord`.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Sinonimi usati per varietà stilistica | Ricerche che non trovano, ambiguità nel codice | Un concetto, un termine |
| Termine di dominio del cliente usato nel codice tecnico | Il codice cambia quando cambia il cliente | Mappatura esplicita nel glossario di progetto |
| Traduzione letterale italiano → inglese | Nomi che nessuno riconosce | Termine inglese consolidato del settore |
| Classi `...Manager` o `...Helper` | Responsabilità indefinita, crescita incontrollata | Nome che dichiara cosa fa |
| «tenant» e «cliente» usati come sinonimi | Confusione tra piattaforma e dominio | Distinzione rigorosa |

---

## Checklist

- [ ] Ogni termine che uso in un documento è definito qui o è di uso comune non ambiguo.
- [ ] I nomi di classe e tabella derivano dal glossario.
- [ ] Non ho introdotto sinonimi di termini esistenti.
- [ ] I termini nuovi sono stati aggiunti a questo file.
- [ ] Non ho usato parole della lista «da non usare».

---

## Riferimenti

- [Regole di naming](../../rules/naming.md)
- [Politica linguistica](../02-conventions/03-language-policy.md)
- [Architettura: livelli](../../architecture/02-layers.md)
- [Multitenancy](../../architecture/03-multitenancy-overview.md)
