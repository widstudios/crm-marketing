# Il primo modulo

> Costruire un modulo completo dall'inizio alla fine, applicando tutti i pattern della Factory su
> un caso volutamente semplice.

---

## Indice

1. [Descrizione](#descrizione)
2. [Il caso di esempio](#il-caso-di-esempio)
3. [Passo 1 — Struttura del modulo](#passo-1--struttura-del-modulo)
4. [Passo 2 — Database](#passo-2--database)
5. [Passo 3 — Dominio](#passo-3--dominio)
6. [Passo 4 — Applicazione](#passo-4--applicazione)
7. [Passo 5 — Infrastruttura](#passo-5--infrastruttura)
8. [Passo 6 — Autorizzazione](#passo-6--autorizzazione)
9. [Passo 7 — Interfaccia Filament](#passo-7--interfaccia-filament)
10. [Passo 8 — API](#passo-8--api)
11. [Passo 9 — Test](#passo-9--test)
12. [Passo 10 — Documentazione](#passo-10--documentazione)
13. [Best practice](#best-practice)
14. [Errori comuni](#errori-comuni)
15. [Checklist](#checklist)
16. [Riferimenti](#riferimenti)

---

## Descrizione

Questo documento è un percorso guidato: al termine si è costruito un modulo che rispetta ogni
regola della Factory. Il dominio è volutamente banale, perché l'oggetto della guida sono i
**pattern**, non la complessità.

Tempo indicativo: 3-4 ore la prima volta, meno di un'ora quando i pattern sono acquisiti.

---

## Il caso di esempio

Modulo **`suppliers`**: anagrafica dei fornitori di un tenant.

| Elemento | Descrizione |
|---|---|
| Entità | `Supplier` (ragione sociale, partita IVA, email, stato) |
| Stati | `active`, `suspended`, `archived` |
| Operazioni | creazione, aggiornamento, sospensione, riattivazione, archiviazione |
| Regole di dominio | la partita IVA è unica nel tenant; un fornitore archiviato non torna attivo |
| Interfaccia | Filament Resource nel pannello Tenant Admin |
| API | REST, lettura ed elenco |
| Autorizzazione | permessi `supplier.view`, `supplier.create`, `supplier.update`, `supplier.archive` |

---

## Passo 1 — Struttura del modulo

```
modules/suppliers/
├── module.json
├── README.md
├── database/
│   ├── migrations/tenant/2026_07_25_000001_create_suppliers_table.php
│   ├── factories/SupplierFactory.php
│   └── seeders/SupplierSeeder.php
├── src/
│   ├── Domain/
│   │   ├── Models/Supplier.php
│   │   ├── Enums/SupplierStatus.php
│   │   ├── ValueObjects/VatNumber.php
│   │   ├── Events/SupplierArchived.php
│   │   └── Contracts/SupplierRepository.php
│   ├── Application/
│   │   ├── Actions/CreateSupplierAction.php
│   │   ├── Actions/UpdateSupplierAction.php
│   │   ├── Actions/ArchiveSupplierAction.php
│   │   ├── Data/SupplierData.php
│   │   └── Queries/ListSuppliersQuery.php
│   ├── Infrastructure/
│   │   └── Repositories/EloquentSupplierRepository.php
│   ├── Http/
│   │   ├── Controllers/Api/SupplierController.php
│   │   ├── Requests/StoreSupplierRequest.php
│   │   └── Resources/SupplierResource.php
│   ├── Filament/
│   │   └── Resources/SupplierResource.php
│   ├── Policies/SupplierPolicy.php
│   └── Providers/SuppliersServiceProvider.php
├── tests/
│   ├── Unit/
│   ├── Feature/
│   └── Architecture/
└── docs/
    ├── overview.md
    └── checklist.md
```

`module.json` dichiara identità e dipendenze:

```json
{
    "name": "suppliers",
    "version": "1.0.0",
    "description": "Anagrafica fornitori",
    "provider": "Modules\\Suppliers\\Providers\\SuppliersServiceProvider",
    "requires": ["core", "auth"],
    "database": "tenant",
    "permissions": [
        "supplier.view", "supplier.create", "supplier.update", "supplier.archive"
    ]
}
```

Il campo `database: tenant` è determinante: dichiara che le migration del modulo vanno eseguite su
ogni database tenant, non sul landlord.

---

## Passo 2 — Database

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('vat_number', 20);
            $table->string('email')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique('vat_number');
            $table->index(['status', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
```

Punti che valgono come regola generale:

- **Nessuna colonna `tenant_id`**: il tenant è il database. Una colonna del genere sarebbe un
  errore di comprensione dell'architettura.
- Il vincolo di unicità è **per database**, quindi automaticamente per tenant.
- L'indice composto `(status, name)` serve l'elenco filtrato, che è la query più frequente.
- `down()` è sempre implementato: una migration non reversibile blocca i rollback.

---

## Passo 3 — Dominio

**Enum di stato:**

```php
<?php

declare(strict_types=1);

namespace Modules\Suppliers\Domain\Enums;

enum SupplierStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Active => in_array($target, [self::Suspended, self::Archived], true),
            self::Suspended => in_array($target, [self::Active, self::Archived], true),
            self::Archived => false,
        };
    }

    public function label(): string
    {
        return __("suppliers::status.{$this->value}");
    }
}
```

La macchina a stati vive **nell'enum**, non sparsa in controller e resource. Chi legge l'enum
conosce tutte le transizioni ammesse.

**Value object:**

```php
<?php

declare(strict_types=1);

namespace Modules\Suppliers\Domain\ValueObjects;

use Modules\Suppliers\Domain\Exceptions\InvalidVatNumber;

final readonly class VatNumber implements \Stringable
{
    public function __construct(public string $value)
    {
        if (! preg_match('/^IT[0-9]{11}$/', $value)) {
            throw InvalidVatNumber::forValue($value);
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

Un value object auto-validante rende **impossibile** l'esistenza di una partita IVA non valida in
memoria: la validazione non può essere dimenticata perché è nel costruttore.

**Contratto del repository:**

```php
<?php

declare(strict_types=1);

namespace Modules\Suppliers\Domain\Contracts;

use Modules\Suppliers\Domain\Models\Supplier;
use Modules\Suppliers\Domain\ValueObjects\VatNumber;

interface SupplierRepository
{
    public function findById(int $id): ?Supplier;

    public function findByVatNumber(VatNumber $vatNumber): ?Supplier;

    public function save(Supplier $supplier): Supplier;
}
```

Il contratto sta nel dominio, l'implementazione nell'infrastruttura: è la direzione della
dipendenza che rende il dominio indipendente dal framework.

---

## Passo 4 — Applicazione

**DTO:**

```php
<?php

declare(strict_types=1);

namespace Modules\Suppliers\Application\Data;

use Modules\Suppliers\Domain\ValueObjects\VatNumber;

final readonly class SupplierData
{
    public function __construct(
        public string $name,
        public VatNumber $vatNumber,
        public ?string $email = null,
    ) {}

    /**
     * @param array{name: string, vat_number: string, email?: string|null} $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            name: $payload['name'],
            vatNumber: new VatNumber($payload['vat_number']),
            email: $payload['email'] ?? null,
        );
    }
}
```

**Action:**

```php
<?php

declare(strict_types=1);

namespace Modules\Suppliers\Application\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Suppliers\Application\Data\SupplierData;
use Modules\Suppliers\Domain\Contracts\SupplierRepository;
use Modules\Suppliers\Domain\Enums\SupplierStatus;
use Modules\Suppliers\Domain\Events\SupplierCreated;
use Modules\Suppliers\Domain\Exceptions\DuplicateVatNumber;
use Modules\Suppliers\Domain\Models\Supplier;

final readonly class CreateSupplierAction
{
    public function __construct(private SupplierRepository $repository) {}

    public function execute(SupplierData $data): Supplier
    {
        if ($this->repository->findByVatNumber($data->vatNumber) !== null) {
            throw DuplicateVatNumber::forValue((string) $data->vatNumber);
        }

        $supplier = DB::transaction(function () use ($data): Supplier {
            $supplier = new Supplier();
            $supplier->name = $data->name;
            $supplier->vat_number = (string) $data->vatNumber;
            $supplier->email = $data->email;
            $supplier->status = SupplierStatus::Active;

            return $this->repository->save($supplier);
        });

        SupplierCreated::dispatch($supplier->id);

        return $supplier;
    }
}
```

Caratteristiche obbligatorie di un'Action: `final`, **un solo** metodo pubblico, riceve un DTO
(mai una `Request`), transazionale quando tocca più righe, emette gli eventi di dominio.

L'evento trasporta l'**identificatore**, non l'oggetto: un listener asincrono deve rileggere lo
stato corrente, non lavorare su una fotografia serializzata.

---

## Passo 5 — Infrastruttura

```php
<?php

declare(strict_types=1);

namespace Modules\Suppliers\Infrastructure\Repositories;

use Modules\Suppliers\Domain\Contracts\SupplierRepository;
use Modules\Suppliers\Domain\Models\Supplier;
use Modules\Suppliers\Domain\ValueObjects\VatNumber;

final readonly class EloquentSupplierRepository implements SupplierRepository
{
    public function findById(int $id): ?Supplier
    {
        return Supplier::query()->find($id);
    }

    public function findByVatNumber(VatNumber $vatNumber): ?Supplier
    {
        return Supplier::query()
            ->where('vat_number', (string) $vatNumber)
            ->first();
    }

    public function save(Supplier $supplier): Supplier
    {
        $supplier->save();

        return $supplier;
    }
}
```

Il binding nel service provider del modulo:

```php
$this->app->bind(SupplierRepository::class, EloquentSupplierRepository::class);
```

---

## Passo 6 — Autorizzazione

```php
<?php

declare(strict_types=1);

namespace Modules\Suppliers\Policies;

use App\Models\User;
use Modules\Suppliers\Domain\Enums\SupplierStatus;
use Modules\Suppliers\Domain\Models\Supplier;

final readonly class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('supplier.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->can('supplier.view');
    }

    public function create(User $user): bool
    {
        return $user->can('supplier.create');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->can('supplier.update')
            && $supplier->status !== SupplierStatus::Archived;
    }

    public function archive(User $user, Supplier $supplier): bool
    {
        return $user->can('supplier.archive')
            && $supplier->status->canTransitionTo(SupplierStatus::Archived);
    }
}
```

La Policy combina **permesso** (l'utente ha il diritto) e **stato** (l'operazione è ammessa
adesso). Nessun metodo ritorna `true` in assenza di un permesso esplicito: è il principio
deny-by-default.

---

## Passo 7 — Interfaccia Filament

```php
<?php

declare(strict_types=1);

namespace Modules\Suppliers\Filament\Resources;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Suppliers\Domain\Enums\SupplierStatus;
use Modules\Suppliers\Domain\Models\Supplier;

final class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    public static function getNavigationLabel(): string
    {
        return __('suppliers::navigation.suppliers');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('vat_number')->required()->maxLength(20)
                ->rules(['regex:/^IT[0-9]{11}$/']),
            TextInput::make('email')->email()->maxLength(255),
            Select::make('status')
                ->options(SupplierStatus::class)
                ->required()
                ->disabled(fn (?Supplier $record): bool => $record?->status === SupplierStatus::Archived),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('vat_number')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('name');
    }
}
```

La Resource **dichiara** l'interfaccia. Non contiene logica di business: le azioni che modificano
lo stato delegano alle Action del modulo.

---

## Passo 8 — API

```php
<?php

declare(strict_types=1);

namespace Modules\Suppliers\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Suppliers\Application\Queries\ListSuppliersQuery;
use Modules\Suppliers\Domain\Models\Supplier;
use Modules\Suppliers\Http\Resources\SupplierResource;

final class SupplierController extends Controller
{
    public function index(Request $request, ListSuppliersQuery $query): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Supplier::class);

        return SupplierResource::collection(
            $query->execute(
                status: $request->string('status')->toString() ?: null,
                perPage: $request->integer('per_page', 25),
            )
        );
    }

    public function show(Supplier $supplier): SupplierResource
    {
        $this->authorize('view', $supplier);

        return new SupplierResource($supplier);
    }
}
```

Il controller autorizza, delega, serializza. Il corpo di ogni metodo resta di poche righe.

---

## Passo 9 — Test

**Unitario, senza database:**

```php
it('rifiuta una partita IVA non valida', function (): void {
    expect(fn () => new VatNumber('XX123'))->toThrow(InvalidVatNumber::class);
});

it('non permette transizioni da archiviato', function (): void {
    expect(SupplierStatus::Archived->canTransitionTo(SupplierStatus::Active))->toBeFalse();
});
```

**Di feature, con database:**

```php
it('crea un fornitore ed emette l\'evento', function (): void {
    Event::fake([SupplierCreated::class]);

    $supplier = app(CreateSupplierAction::class)->execute(
        SupplierData::fromArray(['name' => 'ACME', 'vat_number' => 'IT12345678901'])
    );

    expect($supplier->status)->toBe(SupplierStatus::Active);
    Event::assertDispatched(SupplierCreated::class);
});

it('rifiuta una partita IVA duplicata', function (): void {
    Supplier::factory()->create(['vat_number' => 'IT12345678901']);

    expect(fn () => app(CreateSupplierAction::class)->execute(
        SupplierData::fromArray(['name' => 'Altro', 'vat_number' => 'IT12345678901'])
    ))->toThrow(DuplicateVatNumber::class);
});
```

**Di isolamento tenant — obbligatorio per ogni entità:**

```php
it('non espone i fornitori di un altro tenant', function (): void {
    $acme = Tenant::factory()->create();
    $globex = Tenant::factory()->create();

    tenancy()->run($acme, fn () => Supplier::factory()->create(['vat_number' => 'IT11111111111']));

    tenancy()->run($globex, function (): void {
        expect(Supplier::query()->count())->toBe(0);
    });
});
```

**Di architettura:**

```php
arch('le action sono final e hanno un solo metodo pubblico')
    ->expect('Modules\Suppliers\Application\Actions')
    ->toBeFinal()
    ->toHaveMethod('execute');

arch('il dominio non dipende dal framework')
    ->expect('Modules\Suppliers\Domain')
    ->not->toUse('Illuminate');
```

---

## Passo 10 — Documentazione

Il modulo non è completo finché non ha:

| File | Contenuto |
|---|---|
| `README.md` | scopo, entità, operazioni, permessi, dipendenze, installazione |
| `docs/overview.md` | modello di dominio, stati, decisioni prese |
| `docs/checklist.md` | verifica di completezza specifica del modulo |

---

## Best practice

- Costruire nell'ordine indicato: database → dominio → applicazione → infrastruttura →
  autorizzazione → interfaccia. Ogni livello dipende solo dai precedenti.
- Scrivere il test **insieme** all'artefatto, non alla fine.
- Mettere le regole di dominio nel dominio (enum, value object), non nell'interfaccia.
- Partire dal template corrispondente in [`templates/`](../../templates/README.md): evita di
  dimenticare parti obbligatorie.
- Se un'operazione richiede più di un'Action, serve un Service che le coordini, non un'Action più
  grande.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Colonna `tenant_id` nelle tabelle tenant | Fraintendimento dell'architettura, indici inutili | Il tenant è il database |
| Logica di business nella Filament Resource | Non riutilizzabile da API e CLI, non testabile | Delegare alle Action |
| Action che riceve una `Request` | Inutilizzabile fuori da HTTP | Ricevere un DTO |
| Validazione solo nel Form Request | Aggirabile da CLI, coda e Filament | Value object nel dominio |
| Policy che ritorna `true` per default | Autorizzazione permissiva | Deny by default |
| Nessun test di isolamento tenant | Data leak non rilevato | Test obbligatorio per ogni entità |
| Evento che trasporta l'intero modello | Dati obsoleti nel listener asincrono | Trasportare l'identificatore |
| Migration senza `down()` | Rollback impossibile | Sempre reversibile |

---

## Checklist

- [ ] `module.json` completo, con dipendenze e permessi dichiarati.
- [ ] Migration reversibile, indici sulle query frequenti, nessun `tenant_id`.
- [ ] Regole di dominio in enum e value object.
- [ ] Contratto del repository nel dominio, implementazione nell'infrastruttura.
- [ ] Un'Action per operazione, `final`, con un solo metodo pubblico.
- [ ] Policy deny-by-default, che considera anche lo stato.
- [ ] Filament Resource senza logica di business.
- [ ] Controller API di poche righe, con autorizzazione esplicita.
- [ ] Test unitari, di feature, di isolamento tenant e di architettura.
- [ ] README e documentazione del modulo presenti.
- [ ] `composer qa` verde.

---

## Riferimenti

- [Blueprint di modulo](../../modules/_blueprint/README.md) · [Catalogo moduli](../../modules/README.md)
- [Template](../../templates/README.md)
- [Action Pattern](../../rules/action-pattern.md) · [Repository](../../rules/repository-pattern.md) · [DTO](../../rules/dto.md)
- [Policy](../../rules/policies.md) · [Testing](../../rules/testing.md)
- [Guida allo sviluppo di una feature](../03-development/02-feature-development-guide.md)
