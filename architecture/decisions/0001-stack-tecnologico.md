# ADR-0001 — Stack tecnologico

> Laravel LTS su PHP 8.4, con Filament, Livewire, Tailwind, MySQL e Redis: uno stack unico per
> tutti i prodotti aziendali.

| | |
|---|---|
| **Stato** | Accettata |
| **Data** | 2026-07-25 |
| **Decisore** | Factory Owner |
| **Impatto** | tutti i progetti, tutte le aree |
| **Reversibilità** | **irreversibile** nella pratica |

---

## Indice

1. [Contesto](#contesto)
2. [Decisione](#decisione)
3. [Alternative valutate](#alternative-valutate)
4. [Conseguenze](#conseguenze)
5. [Soglia di rivalutazione](#soglia-di-rivalutazione)
6. [Verifica](#verifica)
7. [Riferimenti](#riferimenti)

---

## Contesto

WidStudios sviluppa gestionali per clienti che li usano per anni. I prodotti previsti — magazzino
sanitario, CRM, project management, help desk, CAF, documentale, tracciabilità — condividono le
stesse necessità infrastrutturali e la stessa forma: interfacce amministrative ricche, API,
multitenancy, tracciabilità.

Vincoli reali del contesto:

| Vincolo | Origine |
|---|---|
| Competenza interna concentrata su PHP e Laravel | composizione del team |
| Infrastruttura dei clienti su MySQL/MariaDB | ambienti esistenti presso i clienti |
| Ciclo di vita dei prodotti di 5-10 anni | natura dei gestionali |
| Team piccolo | non sostenibile mantenere più stack |
| Necessità di interfacce amministrative complesse | ogni prodotto ha un pannello ricco |

Il problema da risolvere non è «quale sia lo stack migliore», ma **quale stack unico possa servire
tutti i prodotti per un decennio con un team piccolo**.

---

## Decisione

Si adotta uno stack **unico e vincolante** per tutti i progetti:

| Livello | Tecnologia | Versione minima |
|---|---|---|
| Linguaggio | PHP | 8.4 |
| Framework | Laravel | 12.x **LTS** |
| Pannello amministrativo | Filament | 4.x |
| Componenti reattivi | Livewire | 3.x |
| CSS | Tailwind CSS | 4.x |
| JS | Alpine.js | 3.x |
| Build | Vite | 6.x |
| Database (produzione) | MySQL 8 / MariaDB 11 | — |
| Database (sviluppo e test) | SQLite | 3.45 |
| Cache, code, sessioni, lock | Redis | 7.x |
| Container | Docker + Compose | — |
| Test | Pest 3 su PHPUnit 11 | — |
| Formattazione | Laravel Pint | ultima |
| Analisi statica | PHPStan + Larastan livello 8 | — |

Vincoli aggiuntivi:

- **Solo versioni LTS** del framework: il supporto lungo è il criterio decisivo.
- Nessuna deviazione senza ADR di progetto e stima del costo di migrazione.
- Ogni dipendenza di terze parti sostituibile è isolata dietro un contratto della Foundation.

---

## Alternative valutate

### Alternativa A — Symfony

Framework maturo, ottima architettura, forte tipizzazione.

**Scartata** per due ragioni: la competenza interna è concentrata su Laravel, e non esiste un
equivalente diretto di Filament. La costruzione delle interfacce amministrative — che rappresenta
una quota rilevante di ogni nostro prodotto — richiederebbe significativamente più lavoro per
progetto.

### Alternativa B — PostgreSQL invece di MySQL

Tecnicamente superiore su più fronti: schema separati nativi, tipi più ricchi, gestione JSON
migliore, ricerca testuale integrata.

**Scartata** per ragioni di contesto, non tecniche: l'infrastruttura dei clienti è su
MySQL/MariaDB, e la competenza interna di gestione (replica, backup, diagnosi) è su MySQL. È
l'alternativa con la maggiore probabilità di essere rivalutata in futuro.

### Alternativa C — Inertia con Vue o React

Frontend più ricco, ecosistema JavaScript ampio.

**Scartata** perché introdurrebbe una **seconda architettura frontend** accanto a quella di
Filament, che è costruito su Livewire. Due paradigmi da mantenere per dieci anni, con un team
piccolo, non sono sostenibili.

### Alternativa D — stack diverso per prodotto

Scegliere le tecnologie più adatte a ciascun dominio.

**Scartata** perché annullerebbe il beneficio della Factory: nessuna Foundation condivisa, nessun
riuso, competenze frammentate, costo di manutenzione moltiplicato per il numero di prodotti.

### Confronto

| Asse | Laravel + Filament (adottata) | Symfony | Inertia + Vue | Stack per prodotto |
|---|---|---|---|---|
| Competenze interne | **allineate** | da acquisire | parziali | frammentate |
| Interfacce amministrative | **molto rapide** | lente | lente | variabile |
| Costo di manutenzione | basso | medio | **alto** (due paradigmi) | **molto alto** |
| Riuso tra progetti | **massimo** | alto | medio | **nullo** |
| Supporto a lungo termine | LTS 3 anni | LTS 4 anni | dipende | non garantito |
| Coerenza tra prodotti | **massima** | alta | media | nulla |
| Flessibilità per dominio | media | alta | alta | **massima** |

---

## Conseguenze

### Positive

- Una sola Foundation serve tutti i prodotti.
- Chi passa da un progetto all'altro si orienta in mezz'ora.
- Filament riduce drasticamente il tempo di costruzione delle interfacce amministrative.
- Aggiornamenti e patch di sicurezza si applicano una volta e valgono per tutti.
- Le competenze si approfondiscono invece di disperdersi.
- Gli agenti AI operano su un contesto tecnologico noto e stabile.

### Negative (accettate consapevolmente)

- **Nessuna ottimizzazione per dominio.** Un prodotto con esigenze particolari (per esempio
  telemetria a volumi elevati) deve adattarsi, o chiedere un'eccezione con ADR.
- **Dipendenza da Filament.** È un pacchetto di terze parti su cui poggia una quota rilevante di
  ogni prodotto: un suo abbandono avrebbe impatto significativo.
- **Vincolo a MySQL.** Si rinuncia a funzionalità utili di PostgreSQL, in particolare per la
  ricerca testuale e la gestione degli schemi.
- **Nessuna esperienza su altri stack.** Il team non accumula competenze alternative, riducendo la
  capacità di valutare tecnologie diverse.
- **Aggiornamenti di framework come attività pianificata.** Un aggiornamento major tocca tutti i
  progetti contemporaneamente.

### Impatto operativo

| Area | Effetto |
|---|---|
| Sviluppo | un solo insieme di regole e template |
| Test | Pest su tutti i progetti; doppia esecuzione SQLite/MySQL |
| Deploy | una sola pipeline parametrica, una sola immagine base |
| Esercizio | un solo insieme di runbook |
| Formazione | percorso di inserimento unico |

---

## Soglia di rivalutazione

| Componente | Condizione di rivalutazione |
|---|---|
| Laravel | fine del supporto LTS senza successore; cambio di licenza |
| Filament | abbandono della manutenzione; incompatibilità con la LTS corrente |
| MySQL | requisito di dominio che richieda funzionalità assenti (dopo aver valutato archivi dedicati) |
| Livewire | requisito di interfaccia che non riesce a soddisfare (per esempio offline-first) |
| PHP | fine del supporto della versione minima |

Rivalutazione **parziale** ammessa: un archivio time-series affiancato a MySQL per un dominio
specifico non richiede di cambiare stack, ma richiede una ADR di progetto che ne delimiti i
confini.

---

## Verifica

| Verifica | Strumento | Automatica |
|---|---|---|
| Versione minima di PHP | `composer.json` + CI | sì |
| Versione LTS del framework | `composer.lock` + CI | sì |
| Nessuna dipendenza non ammessa | verifica dell'elenco in CI | sì |
| Livello PHPStan 8 | pipeline | sì |
| Formattazione Pint | pipeline | sì |
| Suite su SQLite e MySQL | pipeline | sì |
| Dipendenze critiche isolate dietro contratto | revisione | no |

---

## Riferimenti

- [Stack tecnologico](../../docs/00-introduction/03-technology-stack.md)
- [ADR-0002 — Isolamento dei tenant](0002-tenant-isolation-strategy.md)
- [Regole PHP](../../rules/php.md) · [Laravel](../../rules/laravel.md) · [Filament](../../rules/filament.md)
- [Foundation](../../foundation/README.md)
