# Livelli architetturali

> I quattro livelli, le loro responsabilità, la direzione delle dipendenze e come si verifica che
> non venga violata.

---

## Indice

1. [Descrizione](#descrizione)
2. [I quattro livelli](#i-quattro-livelli)
3. [Direzione delle dipendenze](#direzione-delle-dipendenze)
4. [Inversione delle dipendenze](#inversione-delle-dipendenze)
5. [Il grado di purezza](#il-grado-di-purezza)
6. [Attraversare i livelli](#attraversare-i-livelli)
7. [Verifica automatica](#verifica-automatica)
8. [Esempi](#esempi)
9. [Best practice](#best-practice)
10. [Errori comuni](#errori-comuni)
11. [Checklist](#checklist)
12. [Riferimenti](#riferimenti)

---

## Descrizione

La stratificazione serve a rendere **esplicito** ciò che dipende da cosa. Senza livelli, tutto può
dipendere da tutto, e dopo due anni non è più possibile modificare nulla senza effetti imprevisti.

Il beneficio pratico più immediato: la logica di business diventa testabile senza avviare
l'applicazione, e sopravvive all'aggiornamento del framework.

---

## I quattro livelli

### Dominio — `app/Domain/`

**Cosa contiene:** entità, value object, enum con le loro regole, eventi, eccezioni, contratti.

**Cosa può usare:** solo PHP e altre classi del dominio.

**Cosa non può usare:** Laravel, Eloquent, HTTP, filesystem, tempo di sistema non iniettato.

**Domanda di controllo:** *questo codice avrebbe senso in un'applicazione console senza database?*

### Applicazione — `app/Application/`

**Cosa contiene:** Action (mutazioni), Query (letture complesse), DTO, Service di coordinamento.

**Cosa può usare:** il dominio, i contratti, le transazioni.

**Cosa non può usare:** `Request`, `Response`, sessione, viste, componenti di interfaccia.

**Domanda di controllo:** *questa operazione funzionerebbe identica se invocata da riga di comando?*

### Infrastruttura — `app/Infrastructure/`

**Cosa contiene:** implementazioni dei contratti — repository Eloquent, client HTTP, filesystem,
generatori di documenti, canali di notifica.

**Cosa può usare:** tutto ciò che è tecnico, più i contratti del dominio.

**Cosa non può fare:** contenere regole di business.

**Domanda di controllo:** *se cambiassimo tecnologia, questa classe si riscriverebbe?* Se sì, è
infrastruttura.

### Presentazione — `app/Http/`, `app/Filament/`, `app/Console/`

**Cosa contiene:** controller, Form Request, API Resource, Filament Resource, componenti Livewire,
comandi Artisan.

**Cosa può usare:** il livello applicativo.

**Cosa non può fare:** contenere logica di business, accedere direttamente ai repository.

**Domanda di controllo:** *se togliessi questo punto di ingresso, perderei una regola di business?*
Se sì, la regola è nel posto sbagliato.

---

## Direzione delle dipendenze

```
    ┌──────────────────────────────────────────────┐
    │            PRESENTAZIONE                     │
    │   Http · Filament · Console · Livewire       │
    └───────────────────┬──────────────────────────┘
                        │ usa
                        ▼
    ┌──────────────────────────────────────────────┐
    │            APPLICAZIONE                      │
    │   Actions · Queries · Data · Services        │
    └───────────────────┬──────────────────────────┘
                        │ usa
                        ▼
    ┌──────────────────────────────────────────────┐
    │              DOMINIO                         │
    │   Entità · VO · Enum · Eventi · Contratti    │
    └───────────────────▲──────────────────────────┘
                        │ implementa
    ┌───────────────────┴──────────────────────────┐
    │           INFRASTRUTTURA                     │
    │   Repository · Client · Storage · Canali     │
    └──────────────────────────────────────────────┘
```

**Il dominio non dipende da nulla.** Tutte le frecce puntano verso di lui.

L'infrastruttura è l'unico livello che dipende dal dominio «al contrario»: lo implementa, senza
essere da lui conosciuta. È l'inversione delle dipendenze.

| Da → A | Ammesso |
|---|---|
| Presentazione → Applicazione | sì |
| Presentazione → Dominio | sì, in sola lettura (tipi, enum) |
| Presentazione → Infrastruttura | **no** |
| Applicazione → Dominio | sì |
| Applicazione → Infrastruttura | **no**, solo tramite contratti |
| Dominio → qualsiasi altro livello | **no** |
| Infrastruttura → Dominio | sì (implementa i contratti) |

---

## Inversione delle dipendenze

Il dominio dichiara **cosa gli serve**; l'infrastruttura fornisce **come si fa**.

```php
// Dominio: dichiara il bisogno
namespace App\Domain\Inventory\Contracts;

interface BatchRepository
{
    public function findById(int $id): ?Batch;

    /** @return Collection<int, Batch> */
    public function expiringWithin(int $days): Collection;
}
```

```php
// Infrastruttura: fornisce l'implementazione
namespace App\Infrastructure\Repositories;

final readonly class EloquentBatchRepository implements BatchRepository
{
    public function findById(int $id): ?Batch
    {
        return Batch::query()->find($id);
    }

    public function expiringWithin(int $days): Collection
    {
        return Batch::query()
            ->where('expiry_date', '<=', now()->addDays($days))
            ->orderBy('expiry_date')
            ->get();
    }
}
```

```php
// Composizione: nel service provider
$this->app->bind(BatchRepository::class, EloquentBatchRepository::class);
```

L'Action riceve il contratto, non l'implementazione: nel test si sostituisce senza database.

---

## Il grado di purezza

L'applicazione integrale della stratificazione a un CRUD banale produce cerimonia senza beneficio.
La Factory ammette due gradi, scelti **per bounded context** e dichiarati nella documentazione del
modulo.

| | Pragmatico | Puro |
|---|---|---|
| Quando | CRUD, anagrafiche, poche regole | logica ricca, invarianti, macchine a stati |
| Entità | model Eloquent con metodi di dominio | classi PHP pure, mappate |
| Repository | facoltativo | obbligatorio |
| Test di dominio | con database | senza database |
| Costo | basso | medio |
| Beneficio | rapidità | testabilità e longevità |

**Restano vincolanti in entrambi i casi:**

- enum per gli stati, con le transizioni;
- value object per i dati validati;
- Action per ogni mutazione;
- contratti per le dipendenze esterne.

La scelta si dichiara nel README del modulo, così chi arriva dopo sa cosa aspettarsi.

---

## Attraversare i livelli

Il percorso di una richiesta attraverso i livelli:

```
Controller (presentazione)
    │  riceve Request, autorizza
    ▼
FormRequest → DTO (applicazione)
    │  dati validati e tipizzati
    ▼
Action (applicazione)
    │  verifica precondizioni di dominio
    ▼
Entità / VO / Enum (dominio)
    │  applica le regole
    ▼
Repository (contratto nel dominio, implementazione in infrastruttura)
    │  persiste
    ▼
Evento (dominio) ──▶ Listener (applicazione o infrastruttura)
    │
    ▼
API Resource (presentazione)
```

Ogni freccia attraversa un confine, e ogni confine ha una trasformazione: `Request` → DTO,
entità → risorsa serializzata. Le trasformazioni non sono burocrazia: sono ciò che impedisce ai
dettagli di un livello di propagarsi negli altri.

---

## Verifica automatica

```php
arch('il dominio non dipende dal framework')
    ->expect('App\Domain')
    ->not->toUse(['Illuminate', 'Symfony']);

arch('l\'applicazione non conosce HTTP')
    ->expect('App\Application')
    ->not->toUse(['Illuminate\Http', 'Illuminate\Support\Facades\Request']);

arch('la presentazione non usa l\'infrastruttura')
    ->expect(['App\Http', 'App\Filament'])
    ->not->toUse('App\Infrastructure');

arch('i contratti stanno nel dominio')
    ->expect('App\Domain')
    ->interfaces()
    ->toBeInterfaces();

arch('i repository implementano un contratto del dominio')
    ->expect('App\Infrastructure\Repositories')
    ->toImplement('App\Domain');
```

Senza questi test, i confini si erodono: non per cattiva volontà, ma perché sotto scadenza la
scorciatoia è sempre attraente.

---

## Esempi

### Esempio 1 — regola nel posto giusto

```php
// ✗ La regola vive nel controller: non vale per API, CLI e importazioni
public function archive(Supplier $supplier): RedirectResponse
{
    if ($supplier->movements()->where('created_at', '>', now()->subYear())->exists()) {
        return back()->withErrors('Fornitore con movimenti recenti.');
    }

    $supplier->update(['status' => 'archived']);

    return redirect()->route('suppliers.index');
}
```

```php
// ✓ La regola vive nel dominio, il controller traduce
public function archive(Supplier $supplier, ArchiveSupplierAction $action): RedirectResponse
{
    $this->authorize('archive', $supplier);

    $action->execute($supplier);

    return redirect()->route('suppliers.index');
}
```

### Esempio 2 — sostituzione di infrastruttura senza toccare il dominio

Il generatore di PDF va sostituito. Il dominio dichiara `PdfRenderer`; l'implementazione cambia,
il binding cambia, il dominio e le Action restano identici. I test del dominio non si toccano.

---

## Best practice

- Domandarsi sempre a quale livello appartiene ciò che si sta scrivendo, prima di scriverlo.
- Il dominio è il livello che vive più a lungo: proteggerlo dalle dipendenze.
- Dichiarare i contratti nel dominio, implementarli nell'infrastruttura.
- Scegliere il grado di purezza per bounded context e dichiararlo.
- Verificare i confini con i test di architettura, dal primo giorno.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Eloquent nel dominio (contesto puro) | Impossibile testare senza database | Entità pure + repository |
| Contratti nell'infrastruttura | La dipendenza si inverte al contrario | Contratti nel dominio |
| Applicazione che riceve `Request` | Operazione inutilizzabile da CLI e coda | DTO |
| Presentazione che usa i repository | Salta le regole del livello applicativo | Passare dalle Action |
| Regole di business nell'infrastruttura | Duplicate ad ogni implementazione | Regole nel dominio |
| Purezza integrale sul CRUD banale | Cerimonia senza beneficio | Grado pragmatico |
| Nessun test di architettura | I confini si erodono in mesi | Test dal primo giorno |

---

## Checklist

- [ ] Ogni classe è nel livello corrispondente alla sua responsabilità.
- [ ] `Domain/` non importa da `Illuminate/`.
- [ ] I contratti sono nel dominio, le implementazioni nell'infrastruttura.
- [ ] Le Action ricevono DTO, non `Request`.
- [ ] La presentazione non accede ai repository.
- [ ] Il grado di purezza è dichiarato per ogni bounded context.
- [ ] I test di architettura verificano tutti i confini.

---

## Riferimenti

- [Livello di dominio](12-domain-layer.md) · [Applicativo](13-application-layer.md) · [Infrastruttura](14-infrastructure-layer.md) · [Presentazione](15-presentation-layer.md)
- [ADR-0003 — Architettura a livelli](decisions/0003-layered-architecture.md)
- [Action Pattern](../rules/action-pattern.md) · [Repository Pattern](../rules/repository-pattern.md)
- [Struttura di progetto](../docs/02-conventions/02-project-layout.md)
