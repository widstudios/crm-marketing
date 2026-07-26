# Templates

> Gli stub che gli agenti istanziano davvero. Non sono esempi: sono il punto di partenza di ogni
> artefatto prodotto dalla Factory.

---

## Indice

1. [Descrizione](#descrizione)
2. [Indice degli stub](#indice-degli-stub)
3. [Convenzione dei segnaposto](#convenzione-dei-segnaposto)
4. [Come si istanzia uno stub](#come-si-istanzia-uno-stub)
5. [Regole degli stub](#regole-degli-stub)
6. [Esempi](#esempi)
7. [Best practice](#best-practice)
8. [Errori comuni](#errori-comuni)
9. [Checklist](#checklist)
10. [Riferimenti](#riferimenti)

---

## Descrizione

Uno stub è la forma **conforme** di un artefatto: già tipizzato, già `final` dove deve esserlo, già
con il posto in cui l'autorizzazione va scritta, già con il commento che spiega perché una certa
riga non va spostata.

La differenza rispetto a un esempio è che un esempio si legge, uno stub si **istanzia**. Il codice
che ne esce entra nel progetto, quindi ogni riga di uno stub è codice di produzione scritto una
volta e replicato per anni. Un difetto qui non si ripete: si moltiplica.

Gli stub non sostituiscono le regole. Sono il modo in cui le regole diventano il percorso di minor
resistenza: se la forma corretta è già lì, scrivere quella sbagliata costa di più.

---

## Indice degli stub

### `domain/` — il cuore del software

| Stub | Artefatto | Regole |
|---|---|---|
| [Model.php.stub](domain/Model.php.stub) | entità di dominio | [laravel](../rules/laravel.md) · [naming](../rules/naming.md) |
| [Enum.php.stub](domain/Enum.php.stub) | enum di stato con transizioni | [php](../rules/php.md) |
| [ValueObject.php.stub](domain/ValueObject.php.stub) | value object auto-validante | [php](../rules/php.md) |
| [Event.php.stub](domain/Event.php.stub) | evento di dominio | [events](../rules/events.md) |
| [Exception.php.stub](domain/Exception.php.stub) | eccezione di dominio | [error-handling](../rules/error-handling.md) |
| [Contract.php.stub](domain/Contract.php.stub) | interfaccia del dominio | [dependency-injection](../rules/dependency-injection.md) |

→ [`domain/README.md`](domain/README.md)

### `backend/` — applicazione e infrastruttura dati

| Stub | Artefatto | Regole |
|---|---|---|
| [Action.php.stub](backend/Action.php.stub) | operazione del dominio | [action-pattern](../rules/action-pattern.md) |
| [Data.php.stub](backend/Data.php.stub) | DTO | [dto](../rules/dto.md) |
| [Query.php.stub](backend/Query.php.stub) | proiezione di lettura | [repository-pattern](../rules/repository-pattern.md) |
| [Repository.php.stub](backend/Repository.php.stub) | accesso alle entità | [repository-pattern](../rules/repository-pattern.md) |
| [Service.php.stub](backend/Service.php.stub) | capacità trasversale | [service-layer](../rules/service-layer.md) |
| [Policy.php.stub](backend/Policy.php.stub) | autorizzazione | [policies](../rules/policies.md) |
| [Controller.php.stub](backend/Controller.php.stub) | controller web | [laravel](../rules/laravel.md) |
| [MigrationTenant.php.stub](backend/MigrationTenant.php.stub) | schema di dominio | [sql](../rules/sql.md) |
| [MigrationLandlord.php.stub](backend/MigrationLandlord.php.stub) | schema di piattaforma | [sql](../rules/sql.md) |
| [Seeder.php.stub](backend/Seeder.php.stub) | dati obbligatori | [database](../rules/database.md) |
| [Factory.php.stub](backend/Factory.php.stub) | dati di prova | [database](../rules/database.md) |

→ [`backend/README.md`](backend/README.md)

### `api/` — il contratto verso l'esterno

| Stub | Artefatto | Regole |
|---|---|---|
| [ApiController.php.stub](api/ApiController.php.stub) | controller API | [rest-api](../rules/rest-api.md) |
| [FormRequest.php.stub](api/FormRequest.php.stub) | validazione | [validation](../rules/validation.md) |
| [ApiResource.php.stub](api/ApiResource.php.stub) | forma della risposta | [rest-api](../rules/rest-api.md) |

→ [`api/README.md`](api/README.md)

### `filament/` — il pannello

| Stub | Artefatto | Regole |
|---|---|---|
| [FilamentResource.php.stub](filament/FilamentResource.php.stub) | risorsa CRUD | [filament](../rules/filament.md) |
| [FilamentWidget.php.stub](filament/FilamentWidget.php.stub) | widget di dashboard | [filament](../rules/filament.md) |
| [FilamentPage.php.stub](filament/FilamentPage.php.stub) | pagina personalizzata | [filament](../rules/filament.md) |

→ [`filament/README.md`](filament/README.md)

### `frontend/` — l'interfaccia pubblica

| Stub | Artefatto | Regole |
|---|---|---|
| [LivewireComponent.php.stub](frontend/LivewireComponent.php.stub) | componente interattivo | [livewire](../rules/livewire.md) |
| [BladeComponent.php.stub](frontend/BladeComponent.php.stub) | componente di presentazione | [frontend](../rules/frontend.md) |
| [vite.config.js.stub](frontend/vite.config.js.stub) | build degli asset | [frontend](../rules/frontend.md) |

→ [`frontend/README.md`](frontend/README.md)

### `infrastructure/` — esecuzione e contorno

| Stub | Artefatto | Regole |
|---|---|---|
| [Job.php.stub](infrastructure/Job.php.stub) | lavoro asincrono | [queue](../rules/queue.md) |
| [Listener.php.stub](infrastructure/Listener.php.stub) | reazione a un evento | [events](../rules/events.md) |
| [Notification.php.stub](infrastructure/Notification.php.stub) | notifica multicanale | [laravel](../rules/laravel.md) |
| [Mail.php.stub](infrastructure/Mail.php.stub) | messaggio di posta | [laravel](../rules/laravel.md) |
| [Command.php.stub](infrastructure/Command.php.stub) | comando Artisan | [laravel](../rules/laravel.md) |
| [Middleware.php.stub](infrastructure/Middleware.php.stub) | middleware HTTP | [middleware](../rules/middleware.md) |
| [ServiceProvider.php.stub](infrastructure/ServiceProvider.php.stub) | registrazione nel container | [dependency-injection](../rules/dependency-injection.md) |
| [Dockerfile.stub](infrastructure/Dockerfile.stub) | immagine dell'applicazione | [deployment](../rules/deployment.md) |
| [compose.yaml.stub](infrastructure/compose.yaml.stub) | ambiente locale | [deployment](../rules/deployment.md) |
| [ci.yaml.stub](infrastructure/ci.yaml.stub) | pipeline | [deployment](../rules/deployment.md) |
| [env.example.stub](infrastructure/env.example.stub) | variabili d'ambiente | [configuration](../rules/configuration.md) |
| [project-claude.md.stub](infrastructure/project-claude.md.stub) | `CLAUDE.md` del progetto generato | [documentation](../rules/documentation.md) |

→ [`infrastructure/README.md`](infrastructure/README.md)

### `testing/` — la rete di protezione

| Stub | Artefatto | Regole |
|---|---|---|
| [UnitTest.php.stub](testing/UnitTest.php.stub) | test unitario | [testing](../rules/testing.md) |
| [FeatureTest.php.stub](testing/FeatureTest.php.stub) | test di feature | [testing](../rules/testing.md) |
| [TenantIsolationTest.php.stub](testing/TenantIsolationTest.php.stub) | isolamento tra tenant | [testing](../rules/testing.md) |
| [ArchitectureTest.php.stub](testing/ArchitectureTest.php.stub) | vincoli strutturali | [testing](../rules/testing.md) |

→ [`testing/README.md`](testing/README.md)

---

## Convenzione dei segnaposto

Un segnaposto è racchiuso tra doppie graffe con uno spazio interno: `{{ Entity }}`.

| Segnaposto | Significato | Esempio |
|---|---|---|
| `{{ Namespace }}` | namespace completo della classe | `App\Domain\Warehouse` |
| `{{ Class }}` | nome della classe | `RegisterMovementAction` |
| `{{ Entity }}` | entità in `StudlyCase` singolare | `Batch` |
| `{{ entity }}` | entità in `camelCase` singolare | `batch` |
| `{{ entities }}` | entità in `camelCase` plurale | `batches` |
| `{{ table }}` | tabella in `snake_case` plurale | `batches` |
| `{{ permission }}` | prefisso dei permessi | `batch` |
| `{{ Module }}` | modulo in `StudlyCase` | `Warehouse` |
| `{{ module }}` | modulo in `snake_case` | `warehouse` |
| `{{ Project }}` | nome del progetto | `Magazzino Sanitario` |
| `{{ project }}` | nome del progetto in `kebab-case` | `magazzino-sanitario` |

I blocchi delimitati da `{{-- … --}}` sono **istruzioni per chi istanzia** e non compaiono mai nel
file prodotto. Servono a dire cosa va deciso, non a lasciare il lavoro a metà.

---

## Come si istanzia uno stub

1. Copiare lo stub nella destinazione, togliendo l'estensione `.stub`.
2. Sostituire **tutti** i segnaposto.
3. Rimuovere **tutti** i blocchi `{{-- … --}}`.
4. Completare la logica: uno stub istanziato e lasciato così com'è non è un artefatto, è
   un'intenzione.
5. Scrivere il test corrispondente, prima di considerare l'artefatto consegnato.

Un file che raggiunge la revisione con un segnaposto residuo è un gate rosso automatico: significa
che nessuno lo ha letto dopo averlo generato.

---

## Regole degli stub

**R1.** Ogni stub PHP dichiara `declare(strict_types=1);`.

**R2.** Ogni stub produce codice che passa `composer qa` una volta completato: nessuno stub genera
violazioni note che qualcuno dovrà correggere dopo.

**R3.** Ogni stub è `final` dove la regola corrispondente lo richiede.

**R4.** Nessuno stub contiene logica di dominio verticale: i nomi sono segnaposto, non esempi presi
da un progetto reale.

**R5.** I commenti di uno stub spiegano **perché** una riga è dove è, mai cosa fa. Un commento che
descrive il codice viene cancellato al primo utilizzo e non protegge da nulla.

**R6.** Nessuno stub contiene segreti, nemmeno di esempio realistico: solo segnaposto evidenti.

**R7.** Uno stub nuovo si aggiunge solo se l'artefatto compare in almeno tre progetti — lo stesso
criterio della Foundation.

**R8.** Quando una regola cambia, gli stub interessati cambiano **nello stesso commit**: uno stub
che genera codice non conforme è peggio dell'assenza dello stub.

---

## Esempi

### Prima e dopo

```php
// templates/backend/Action.php.stub
namespace {{ Namespace }};

final class {{ Class }} extends BaseAction
{
    public function execute({{ Entity }}Data $data): {{ Entity }}
```

```php
// app/Application/Warehouse/Actions/RegisterMovementAction.php
namespace App\Application\Warehouse\Actions;

final class RegisterMovementAction extends BaseAction
{
    public function execute(RegisterMovementData $data): StockMovement
```

---

## Best practice

- Istanziare uno stub e completarlo subito: uno stub lasciato a metà sembra finito.
- Rileggere i blocchi `{{-- … --}}` prima di rimuoverli: contengono le decisioni da prendere.
- Aggiornare lo stub quando si scopre che genera codice da correggere sempre allo stesso modo.
- Verificare in pipeline che nessun file del progetto contenga segnaposto residui.
- Non aggiungere uno stub per un artefatto che compare in un progetto solo.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Segnaposto residuo nel codice | Il file non compila, o compila con nomi assurdi | Verifica in pipeline |
| Blocco `{{-- … --}}` lasciato | Istruzioni interne nel codice di produzione | Rimuoverli tutti |
| Stub istanziato e non completato | Sembra finito e non fa nulla | Completare subito |
| Stub che genera codice non conforme | Il difetto si moltiplica su ogni progetto | Aggiornare lo stub, non il codice generato |
| Nomi di un progetto reale in uno stub | Logica verticale che si propaga | Solo segnaposto |
| Stub duplicato con variazioni minime | Divergono, e nessuno sa quale usare | Un solo stub, con i blocchi di scelta |
| Regola cambiata e stub non aggiornato | Ogni nuovo artefatto nasce non conforme | Stesso commit |

---

## Checklist

- [ ] Tutti i segnaposto sono stati sostituiti.
- [ ] Tutti i blocchi `{{-- … --}}` sono stati rimossi.
- [ ] Il file istanziato è completo, non un'intenzione.
- [ ] Il test corrispondente esiste.
- [ ] `composer qa` è verde sul file prodotto.
- [ ] Nessun segnaposto residuo nel progetto.

---

## Riferimenti

- [Catalogo degli artefatti](../docs/06-reference/02-artifact-catalog.md)
- [Foundation](../foundation/README.md) · [Moduli](../modules/README.md)
- [Indice delle regole](../rules/README.md) · [Naming](../rules/naming.md)
- [Foundation Agent](../agents/01-foundation-agent.md)
