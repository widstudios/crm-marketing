# Stack tecnologico

> Lo stack vincolante della Factory: cosa si usa, con quale versione, perché, e cosa è stato
> scartato.

---

## Indice

1. [Descrizione](#descrizione)
2. [Lo stack in sintesi](#lo-stack-in-sintesi)
3. [Backend](#backend)
4. [Interfaccia amministrativa e frontend](#interfaccia-amministrativa-e-frontend)
5. [Persistenza e infrastruttura](#persistenza-e-infrastruttura)
6. [Qualità e strumenti di sviluppo](#qualità-e-strumenti-di-sviluppo)
7. [Dipendenze di terze parti ammesse](#dipendenze-di-terze-parti-ammesse)
8. [Alternative valutate e scartate](#alternative-valutate-e-scartate)
9. [Politica di aggiornamento](#politica-di-aggiornamento)
10. [Come si propone un cambio di stack](#come-si-propone-un-cambio-di-stack)
11. [Esempi](#esempi)
12. [Best practice](#best-practice)
13. [Errori comuni](#errori-comuni)
14. [Checklist](#checklist)
15. [Riferimenti](#riferimenti)

---

## Descrizione

Lo stack è **una decisione già presa**. Non si rivaluta per progetto, non si rivaluta perché è
uscito qualcosa di nuovo, non si rivaluta perché uno sviluppatore preferisce un'alternativa.

Il motivo non è conservatorismo: è che il valore della standardizzazione supera quasi sempre il
vantaggio marginale di una tecnologia migliore. Cinque progetti su uno stack conosciuto valgono
più di cinque progetti ciascuno sul suo stack ottimale.

Lo stack cambia solo attraverso una ADR accettata, con piano di migrazione.

---

## Lo stack in sintesi

| Livello | Tecnologia | Versione | Vincolo |
|---|---|---|---|
| Linguaggio | PHP | ≥ 8.4 | obbligatorio |
| Framework | Laravel | 12.x LTS | obbligatorio |
| Pannello amministrativo | Filament | 4.x | obbligatorio |
| Componenti reattivi | Livewire | 3.x | obbligatorio |
| CSS | Tailwind CSS | 4.x | obbligatorio |
| JS leggero | Alpine.js | 3.x | obbligatorio |
| Build asset | Vite | 6.x | obbligatorio |
| DB produzione | MySQL 8.x / MariaDB 11.x | — | obbligatorio |
| DB sviluppo e test | SQLite | ≥ 3.45 | obbligatorio |
| Cache, code, lock, sessioni | Redis | 7.x | obbligatorio |
| Container | Docker + Compose | — | obbligatorio |
| Test | Pest 3 su PHPUnit 11 | — | obbligatorio |
| Formattazione | Laravel Pint | ultima | obbligatorio |
| Analisi statica | PHPStan + Larastan | livello 8 | obbligatorio |
| Refactoring assistito | Rector | ultima | consigliato |
| Web server | Nginx + PHP-FPM | — | obbligatorio in produzione |

---

## Backend

### PHP 8.4+

Il minimo è 8.4 perché la Foundation usa in modo sistematico funzionalità recenti del linguaggio:

| Funzionalità | Uso nella Factory |
|---|---|
| Enum (8.1) | stati di dominio, tipi di ruolo, canali di notifica |
| Readonly properties e classi (8.1/8.2) | DTO immutabili |
| Costruttori con promozione (8.0) | tutte le classi di servizio |
| `never` return type (8.1) | guardie che sollevano eccezioni |
| Intersection types (8.1) | contratti compositi nella Foundation |
| Property hooks (8.4) | value object con validazione all'assegnazione |
| Asymmetric visibility (8.4) | entità con scrittura interna e lettura pubblica |
| `new` in initializer (8.1) | default dei parametri con oggetti |

Regole di scrittura: [`rules/php.md`](../../rules/php.md).

**Configurazione minima richiesta in produzione:**

```ini
memory_limit = 256M
max_execution_time = 60
opcache.enable = 1
opcache.jit = tracing
opcache.validate_timestamps = 0    ; in produzione, con deploy che invalida la cache
realpath_cache_size = 4096K
```

### Laravel 12.x LTS

Si usa **solo la versione LTS**. Il supporto lungo è il requisito che conta: i nostri gestionali
vivono anni, non mesi.

Componenti del framework che la Factory usa in modo prescrittivo:

| Componente | Uso |
|---|---|
| Eloquent | persistenza, dietro Repository |
| Migration | schema landlord e tenant, versionato |
| Queue | ogni operazione lenta o esterna |
| Scheduler | attività ricorrenti, con lock |
| Events / Listener | disaccoppiamento tra moduli |
| Policies / Gates | autorizzazione, deny by default |
| Form Request | validazione dell'input HTTP |
| API Resource | serializzazione delle risposte |
| Notifications | canali multipli, database + mail |
| Storage | astrazione filesystem, per-tenant |
| Cache | tagging e chiavi tenant-scoped |
| Localization | italiano e inglese, chiavi in inglese |

Componenti **non** usati o usati con restrizioni:

| Componente | Politica | Motivo |
|---|---|---|
| Facades nel dominio | vietate | il dominio non conosce il framework |
| `Model::observe` | sconsigliato | effetti collaterali impliciti, difficili da testare |
| Global scopes | ammessi solo per la tenancy | comportamento nascosto |
| Route model binding implicito | ammesso, con scope tenant esplicito | rischio di accesso cross-tenant |
| Mass assignment senza `$fillable` | vietato | superficie d'attacco |

---

## Interfaccia amministrativa e frontend

### Filament 4.x

È il **pannello amministrativo di serie** di ogni progetto. Non si costruiscono CRUD a mano.

Struttura standard dei pannelli:

| Pannello | Percorso | Guardia | Scopo |
|---|---|---|---|
| Super Admin | `/super-admin` | `landlord` | gestione tenant, piani, licenze, monitoraggio |
| Tenant Admin | `/admin` | `tenant` | amministrazione del singolo tenant |
| Portale utente | `/app` | `tenant` | operatività quotidiana (facoltativo) |

Regole d'uso: [`rules/filament.md`](../../rules/filament.md).

Vincolo fondamentale: **nessuna logica di business dentro le Resource**. Una Resource dichiara
form, tabella e azioni; le azioni delegano ad Action del dominio.

### Livewire 3.x

Per le interfacce reattive fuori da Filament (portali, dashboard pubbliche, wizard). Filament è
costruito su Livewire, quindi non si aggiunge un secondo paradigma.

**Non** si introduce un framework JS SPA (React, Vue, Inertia) senza ADR: comporterebbe una
seconda architettura frontend da mantenere.

### Tailwind CSS 4.x + Alpine.js 3.x

Tailwind per lo stile, Alpine per l'interattività leggera lato client. Entrambi sono già presenti
nell'ecosistema Filament/Livewire: usarne altri significherebbe caricare due sistemi.

Regole: [`rules/frontend.md`](../../rules/frontend.md), [`rules/tailwind.md`](../../rules/tailwind.md),
[`rules/alpine.md`](../../rules/alpine.md).

### Vite 6.x

Build degli asset. Configurazione standard fornita in
[`templates/frontend/vite.config.js.stub`](../../templates/frontend/vite.config.js.stub).

---

## Persistenza e infrastruttura

### MySQL 8 / MariaDB 11 in produzione

Motivazioni: diffusione presso i clienti, competenza interna consolidata, strumenti di gestione
maturi, replica e backup ben noti al team.

Vincoli operativi imposti dalla multitenancy a database separati:

| Vincolo | Valore di riferimento |
|---|---|
| Charset / collation | `utf8mb4` / `utf8mb4_unicode_ci` |
| Engine | InnoDB |
| `max_connections` | dimensionato su (numero tenant attivi × pool) + margine |
| Isolamento | `READ-COMMITTED` |
| Timezone | UTC nel database, conversione in presentazione |

### SQLite in sviluppo e test

Rende i test veloci e le installazioni locali immediate. Comporta però differenze reali:

| Differenza | Rischio | Mitigazione |
|---|---|---|
| Tipi meno stretti | Errori che emergono solo in produzione | Test di integrazione su MySQL in CI |
| `ALTER TABLE` limitato | Migration che funzionano in locale e falliscono in produzione | Migration verificate su MySQL in pipeline |
| Nessuna FULLTEXT nativa comparabile | Ricerca che si comporta diversamente | Astrazione della ricerca dietro contratto |
| Lock a livello di file | Concorrenza non rappresentativa | Test di concorrenza su MySQL |

**Regola:** la pipeline esegue la suite **due volte**, su SQLite e su MySQL. Le differenze non
sono opzionali da verificare.

### Redis 7.x

Un solo servizio per quattro funzioni, con database logici separati:

| Uso | DB logico | Prefisso chiavi |
|---|---|---|
| Cache | 0 | `<app>:<env>:cache:<tenant>:` |
| Code | 1 | `<app>:<env>:queue:` |
| Sessioni | 2 | `<app>:<env>:session:` |
| Lock e rate limit | 3 | `<app>:<env>:lock:` |

Il prefisso con il tenant è **obbligatorio** per la cache: vedi [`rules/cache.md`](../../rules/cache.md).

### Docker

Ogni progetto ha un ambiente locale identico a quello di produzione nella composizione dei
servizi. Immagini e compose standard: [`deployment/docker/README.md`](../../deployment/docker/README.md).

---

## Qualità e strumenti di sviluppo

| Strumento | Ruolo | Configurazione | Blocca la pipeline? |
|---|---|---|---|
| Pest 3 | test | `tests/Pest.php` | sì |
| PHPUnit 11 | motore sotto Pest | `phpunit.xml` | sì |
| Pint | formattazione PSR-12 + preset | `tooling/configs/pint.json` | sì |
| PHPStan + Larastan | analisi statica livello 8 | `tooling/configs/phpstan.neon` | sì |
| Rector | refactoring automatico | `tooling/configs/rector.php` | no (proposta) |
| Pest Arch | test di architettura | `tests/Architecture/` | sì |
| Laravel Telescope | diagnosi locale e staging | — | no |
| Laravel Pulse | osservabilità applicativa | — | no |

Nessuna baseline PHPStan nuova può essere aggiunta senza motivazione scritta: la baseline è debito
tecnico dichiarato.

---

## Dipendenze di terze parti ammesse

Ogni dipendenza è un impegno di manutenzione pluriennale. La lista è volutamente corta.

| Pacchetto | Uso | Criticità |
|---|---|---|
| `spatie/laravel-permission` | ruoli e permessi | alta — sostituibile solo con migrazione dati |
| `spatie/laravel-activitylog` | activity log | media |
| `spatie/laravel-medialibrary` | gestione media | media |
| `spatie/laravel-backup` | backup | media |
| `laravel/horizon` | supervisione code Redis | media |
| `laravel/sanctum` | autenticazione API a token | alta |
| `barryvdh/laravel-dompdf` o equivalente | generazione PDF | bassa — dietro contratto `PdfRenderer` |
| `maatwebsite/excel` | import/export Excel | bassa — dietro contratto |

### Criteri di ammissione di una nuova dipendenza

Tutti obbligatori:

1. Risolve un problema che ricorre in almeno tre progetti.
2. Ha manutenzione attiva (rilasci negli ultimi sei mesi, compatibilità con la LTS corrente).
3. Ha una licenza compatibile con l'uso commerciale.
4. Il costo di sostituzione è stimato e accettabile.
5. Se è critica, è isolata dietro un contratto della Foundation.
6. È stata valutata in una ADR.

---

## Alternative valutate e scartate

Documentare gli scarti evita di rivalutarli ogni sei mesi.

| Alternativa | Al posto di | Perché scartata |
|---|---|---|
| Symfony | Laravel | Ecosistema amministrativo meno immediato; competenza interna concentrata su Laravel; Filament non ha equivalente diretto |
| PostgreSQL | MySQL | Tecnicamente superiore su più fronti (schema separati, tipi, JSON), ma l'infrastruttura dei clienti e la competenza interna sono su MySQL. Rivalutabile con ADR |
| Inertia + Vue/React | Livewire | Introdurrebbe una seconda architettura frontend accanto a quella di Filament |
| Nova | Filament | Licenza a pagamento, minore estensibilità, comunità più piccola |
| Tenancy con colonna `tenant_id` | Database separati | Rischio permanente di data leak, backup non separabili per cliente. Vedi [ADR-0002](../../architecture/decisions/0002-tenant-isolation-strategy.md) |
| Bootstrap | Tailwind | Incompatibile con l'ecosistema Filament/Livewire |
| Beanstalkd / SQS | Redis | Un servizio in meno da gestire; Horizon copre la supervisione |
| Elasticsearch di serie | ricerca SQL + astrazione | Costo operativo ingiustificato per la maggior parte dei progetti; introducibile per progetto con ADR |
| Kubernetes di serie | Docker Compose | Complessità operativa non giustificata dalla scala attuale |

---

## Politica di aggiornamento

| Tipo | Frequenza | Responsabile | Vincolo |
|---|---|---|---|
| Patch di sicurezza | entro 72 ore | DevOps Owner | obbligatorio, anche fuori ciclo |
| Patch ordinarie | mensile | Foundation Owner | test verdi |
| Minor di framework | trimestrale | Foundation Owner | prima sulla Factory, poi sui progetti |
| Major di framework | pianificata | Factory Owner | ADR + guida di migrazione |
| PHP major | pianificata | Factory Owner | ADR + verifica su tutti i progetti attivi |

L'ordine è sempre: **prima la Factory, poi i progetti**. La Factory è il banco di prova.

---

## Come si propone un cambio di stack

1. Scrivere una ADR in stato `Proposta` in [`architecture/decisions/`](../../architecture/decisions/README.md).
2. Includere: problema concreto (non teorico), alternative, tabella comparativa sugli assi di
   [`governance/decision-process.md`](../../governance/decision-process.md).
3. Stimare il costo di migrazione **per ciascun progetto attivo**.
4. Realizzare un prototipo su un modulo reale, se la decisione è irreversibile.
5. Discussione: 10 giorni lavorativi per un cambio di stack.
6. Se accettata: aggiornare questo documento, scrivere la guida di migrazione, incrementare la
   major della Factory.

Una proposta senza stima del costo di migrazione non viene presa in considerazione.

---

## Esempi

### Esempio 1 — richiesta legittima

> «Il progetto Tracciabilità RFID riceve 5.000 letture al secondo. MySQL con InnoDB non regge la
> scrittura su questa serie temporale.»

Problema concreto, misurato. La proposta ammissibile non è «cambiamo database», ma «aggiungiamo
un archivio time-series **per quel dominio**, dietro un contratto della Foundation, lasciando
invariato lo stack per il resto».

### Esempio 2 — richiesta non ammissibile

> «Preferirei React, sono più veloce.»

Nessun problema misurato, il beneficio è individuale, il costo è collettivo e permanente
(seconda architettura frontend da mantenere per anni). Respinta senza ADR.

### Esempio 3 — aggiornamento gestito bene

Rilascio di una patch di sicurezza di Laravel. La Factory aggiorna la Foundation entro 24 ore,
esegue la suite, rilascia una patch della Foundation, notifica i progetti. I progetti aggiornano
con `composer update` e rilasciano entro 72 ore.

---

## Best practice

- Isolare ogni dipendenza sostituibile dietro un contratto della Foundation.
- Aggiornare spesso e in piccolo: gli aggiornamenti rimandati diventano migrazioni.
- Testare su MySQL in CI anche se si sviluppa su SQLite.
- Fissare le versioni in `composer.json` con vincolo caret e committare `composer.lock`.
- Mantenere questo documento allineato: uno stack documentato male è peggio di uno non documentato.
- Valutare ogni dipendenza per il suo **costo di uscita**, non per il tempo che fa risparmiare oggi.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Introdurre una libreria «solo per questo progetto» | Divergenza dello stack, manutenzione non prevista | ADR obbligatoria |
| Sviluppare e testare solo su SQLite | Bug che emergono in produzione | Doppia esecuzione della suite in CI |
| Usare `env()` fuori da `config/` | Rottura con `config:cache` | Accesso solo tramite `config()` |
| Restare su una versione non LTS | Fine supporto in mesi | Solo LTS |
| Dipendenza critica non isolata | Sostituzione impossibile | Contratto nella Foundation |
| Aggiornare i progetti prima della Factory | La Factory non è più il riferimento | Prima la Factory, sempre |
| Rimandare le patch di sicurezza | Esposizione nota e non mitigata | Finestra di 72 ore |

---

## Checklist

- [ ] Conosco le versioni minime obbligatorie e non le abbasso nei progetti.
- [ ] Ogni dipendenza che aggiungo soddisfa i sei criteri di ammissione.
- [ ] Le dipendenze critiche sono isolate dietro un contratto.
- [ ] La suite di test gira su SQLite **e** su MySQL in CI.
- [ ] `composer.lock` è committato.
- [ ] Nessuna baseline PHPStan nuova senza motivazione scritta.
- [ ] Le proposte di cambio stack hanno ADR e stima di migrazione.

---

## Riferimenti

- [Visione](01-vision.md) · [Principi](04-principles.md)
- [Regole PHP](../../rules/php.md) · [Laravel](../../rules/laravel.md) · [Filament](../../rules/filament.md)
- [Architettura di riferimento](../../architecture/README.md)
- [ADR-0001 — Scelta dello stack](../../architecture/decisions/0001-stack-tecnologico.md)
- [ADR-0002 — Strategia di isolamento dei tenant](../../architecture/decisions/0002-tenant-isolation-strategy.md)
- [Ambiente locale](../01-getting-started/02-local-environment.md)
