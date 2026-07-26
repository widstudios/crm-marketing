# Ricerca

> Come si cerca dentro i dati di un tenant: filtri, ricerca testuale, e quando serve un motore
> dedicato.

---

## Indice

1. [Descrizione](#descrizione)
2. [Tre livelli di ricerca](#tre-livelli-di-ricerca)
3. [Filtri strutturati](#filtri-strutturati)
4. [Ricerca testuale su database](#ricerca-testuale-su-database)
5. [Il contratto di ricerca](#il-contratto-di-ricerca)
6. [Motore dedicato](#motore-dedicato)
7. [Ricerca e multitenancy](#ricerca-e-multitenancy)
8. [Prestazioni](#prestazioni)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

La ricerca è la funzionalità che più spesso viene sovradimensionata. La tentazione di installare un
motore di ricerca dedicato al primo requisito «serve cercare» porta a un servizio in più da
mantenere, da monitorare, da mettere in sicurezza e da tenere sincronizzato — per un problema che
nella maggior parte dei casi il database risolve.

La regola: si sale di livello **quando il livello inferiore non basta**, misurando, non prevedendo.

---

## Tre livelli di ricerca

| Livello | Tecnica | Quando basta | Costo |
|---|---|---|---|
| **1. Filtri** | `WHERE` su colonne indicizzate | la maggior parte dei casi | nullo |
| **2. Testuale su DB** | `LIKE` con prefisso, o FULLTEXT | ricerca su nomi, codici, descrizioni | basso |
| **3. Motore dedicato** | indice esterno | ricerca su documenti, rilevanza, sinonimi, tolleranza agli errori | alto |

Segnali che il livello 2 non basta più: tempi oltre il secondo su volumi reali, necessità di
ordinare per rilevanza, ricerca su contenuto di documenti, tolleranza agli errori di battitura,
sinonimi.

---

## Filtri strutturati

La forma più comune ed efficiente. **Sempre a lista bianca.**

```php
final readonly class BatchFilter
{
    private const SORTABLE = ['expiry_date', 'number', 'quantity', 'created_at'];
    private const FILTERABLE = ['status', 'article_id', 'warehouse_id'];

    public function apply(Builder $query, array $params): Builder
    {
        foreach (array_intersect_key($params, array_flip(self::FILTERABLE)) as $column => $value) {
            $query->where($column, $value);
        }

        if (isset($params['expiring_within'])) {
            $query->where('expiry_date', '<=', now()->addDays((int) $params['expiring_within']));
        }

        $sort = in_array($params['sort'] ?? '', self::SORTABLE, true) ? $params['sort'] : 'expiry_date';

        return $query->orderBy($sort, ($params['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc');
    }
}
```

La lista bianca serve a due cose: impedisce l'ordinamento o il filtro su colonne non indicizzate
(che produrrebbe scansioni complete) e chiude la superficie a input arbitrari.

---

## Ricerca testuale su database

### `LIKE` con prefisso

```sql
WHERE name LIKE 'garz%'    -- usa l'indice
```

Efficiente perché l'indice è utilizzabile. Adatta al completamento automatico su codici e nomi.

### `LIKE` con jolly iniziale

```sql
WHERE name LIKE '%garza%'  -- scansione completa
```

Non usa l'indice. Accettabile solo su tabelle piccole (sotto le ~50.000 righe) e con un limite di
risultati.

### FULLTEXT

```php
Schema::table('articles', function (Blueprint $table): void {
    $table->fullText(['code', 'name', 'description']);
});
```

```php
Article::query()
    ->whereFullText(['code', 'name', 'description'], $terms)
    ->limit(50)
    ->get();
```

| Vantaggi | Limiti |
|---|---|
| Usa un indice dedicato | lunghezza minima delle parole configurabile |
| Ordinamento per rilevanza | nessuna tolleranza agli errori di battitura |
| Sintassi booleana | nessun supporto per i sinonimi |
| Nessun servizio aggiuntivo | comportamento diverso su SQLite |

L'ultimo limite è rilevante: FULLTEXT si comporta diversamente in sviluppo (SQLite) e in produzione
(MySQL). Va verificato su MySQL, sempre.

---

## Il contratto di ricerca

Per poter cambiare tecnologia senza toccare il dominio, la ricerca sta dietro un contratto.

```php
namespace App\Domain\Shared\Contracts;

interface SearchEngine
{
    /**
     * @param class-string $entity
     * @param array<string, mixed> $filters
     * @return SearchResult
     */
    public function search(string $entity, string $terms, array $filters = [], int $limit = 50): SearchResult;

    public function index(object $entity): void;

    public function remove(object $entity): void;
}
```

Implementazioni possibili: `DatabaseSearchEngine` (FULLTEXT), `NullSearchEngine` (per i test),
`ExternalSearchEngine` (motore dedicato).

Passare dall'una all'altra è un cambio di binding: il codice applicativo non cambia. È questa
possibilità che rende accettabile iniziare con la soluzione semplice.

---

## Motore dedicato

Da introdurre **solo** con una ADR di progetto che documenti il problema misurato.

| Requisito | Implicazione |
|---|---|
| Indice per tenant | l'isolamento vale anche qui: mai un indice condiviso |
| Sincronizzazione | in coda, alla modifica dell'entità |
| Ricostruzione | comando dedicato, per tenant |
| Coerenza eventuale | l'indice può essere temporaneamente disallineato |
| Autorizzazione | i risultati vanno filtrati per permessi dopo la ricerca |
| Backup | l'indice è ricostruibile, non va salvato |
| Monitoraggio | disallineamento tra database e indice |

Il punto sull'autorizzazione è quello che si dimentica: un motore di ricerca non conosce le Policy.
Se un utente non può vedere un documento, quel documento non deve comparire nei suoi risultati —
e il filtro va applicato **dopo** la ricerca, sui risultati.

---

## Ricerca e multitenancy

| Livello | Isolamento |
|---|---|
| Filtri e FULLTEXT | automatico: la query gira sul database del tenant |
| Motore dedicato | **da garantire**: un indice per tenant |

Con un motore dedicato l'isolamento torna a essere una responsabilità applicativa, non
strutturale: è il costo principale di quella scelta, e va valutato nella ADR.

```php
// Nome dell'indice derivato dal tenant, mai da input
$index = "{$tenant->slug}_articles";
```

Non esiste, in nessun caso, una ricerca «su tutti i tenant»: non è una limitazione, è il modello.

---

## Prestazioni

| Tecnica | Effetto |
|---|---|
| Limite obbligatorio sui risultati | evita risposte enormi |
| Debounce sull'input dell'utente | riduce le richieste durante la digitazione |
| Lunghezza minima dei termini (3 caratteri) | evita ricerche inutili |
| Cache dei risultati frequenti | con chiave tenant-scoped |
| Selezione delle sole colonne necessarie | riduce il trasferimento |
| Paginazione | sempre |

```php
// Livewire: una richiesta ogni 300 ms invece di una per carattere
wire:model.live.debounce.300ms="query"
```

---

## Esempi

### Esempio 1 — livello 2 sufficiente

Requisito: cercare un articolo per codice o nome nel completamento automatico.

```php
Article::query()
    ->select(['id', 'code', 'name'])
    ->where(fn (Builder $q) => $q
        ->where('code', 'like', "{$terms}%")
        ->orWhere('name', 'like', "{$terms}%"))
    ->limit(20)
    ->get();
```

Con indici su `code` e `name`, risponde in pochi millisecondi anche su centinaia di migliaia di
righe. Nessun servizio aggiuntivo.

### Esempio 2 — livello 3 giustificato

Requisito: cercare dentro il **contenuto** di 200.000 documenti PDF, con rilevanza e tolleranza
agli errori.

Il database non lo fa. La ADR documenta: il problema, i volumi misurati, il motore scelto,
l'isolamento per tenant, la strategia di sincronizzazione e il filtro dei permessi sui risultati.

---

## Best practice

- Iniziare dal livello più basso e salire misurando.
- Lista bianca per filtri e ordinamenti, sempre.
- Ricerca dietro un contratto, per poter cambiare tecnologia.
- Limite obbligatorio sui risultati.
- Verificare FULLTEXT su MySQL, non su SQLite.
- Con un motore dedicato: un indice per tenant e filtro dei permessi sui risultati.
- Motore dedicato solo con ADR e problema misurato.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Motore dedicato «per sicurezza» | Servizio in più senza beneficio | Misurare prima |
| Ordinamento o filtro su colonne arbitrarie | Scansioni complete, superficie aperta | Lista bianca |
| `LIKE '%…%'` su tabelle grandi | Query lentissime | FULLTEXT o prefisso |
| Nessun limite sui risultati | Risposte enormi, memoria esaurita | Limite obbligatorio |
| Indice di ricerca condiviso tra tenant | Fuga di dati | Un indice per tenant |
| Risultati non filtrati per permessi | L'utente vede ciò che non può aprire | Filtro dopo la ricerca |
| FULLTEXT verificato solo su SQLite | Comportamento diverso in produzione | Verifica su MySQL |
| Ricerca a ogni carattere digitato | Carico inutile | Debounce e lunghezza minima |

---

## Checklist

- [ ] Il livello di ricerca è il più basso che soddisfa il requisito.
- [ ] Filtri e ordinamenti a lista bianca.
- [ ] Le colonne cercate sono indicizzate.
- [ ] La ricerca passa da un contratto.
- [ ] Limite e paginazione sempre presenti.
- [ ] Lunghezza minima dei termini e debounce nell'interfaccia.
- [ ] FULLTEXT verificato su MySQL.
- [ ] Con motore dedicato: indice per tenant, sincronizzazione in coda, filtro dei permessi.
- [ ] Il motore dedicato, se presente, è giustificato da una ADR.

---

## Riferimenti

- [Regole SQL](../rules/sql.md) · [Cache](../rules/cache.md)
- [Guida alle prestazioni](../docs/04-quality/04-performance-guide.md)
- [Infrastruttura](14-infrastructure-layer.md)
- [Multitenancy](03-multitenancy-overview.md)
