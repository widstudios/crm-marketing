# Analisi statica

> PHPStan, Pint e Rector: cosa verificano, come si configurano, e come si gestisce il debito
> rappresentato dalla baseline.

---

## Indice

1. [Descrizione](#descrizione)
2. [Gli strumenti](#gli-strumenti)
3. [PHPStan](#phpstan)
4. [La baseline](#la-baseline)
5. [Pint](#pint)
6. [Rector](#rector)
7. [Test di architettura](#test-di-architettura)
8. [Integrazione in pipeline](#integrazione-in-pipeline)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

L'analisi statica trova, senza eseguire il codice, una classe di difetti che i test intercettano
solo se qualcuno ha pensato al caso specifico: valori nulli non gestiti, tipi incompatibili, rami
irraggiungibili, metodi inesistenti.

È l'applicazione più diretta del principio «automazione dei controlli»: ciò che una macchina può
verificare non deve occupare l'attenzione di un revisore.

---

## Gli strumenti

| Strumento | Verifica | Corregge | Blocca la pipeline |
|---|---|---|---|
| PHPStan + Larastan | tipi, nulli, logica irraggiungibile | no | sì |
| Pint | formattazione PSR-12 e preset | sì | sì |
| Rector | modernizzazione e refactoring meccanici | sì | no |
| Pest Arch | vincoli strutturali | no | sì |

I quattro sono complementari: PHPStan guarda i tipi, Pint la forma, Rector l'evoluzione del
linguaggio, Pest Arch l'architettura.

---

## PHPStan

**Livello obbligatorio: 8.** È il massimo prima di `strict-rules`, e verifica tra l'altro che
ogni valore potenzialmente nullo sia gestito e che ogni tipo generico sia dichiarato.

```neon
# tooling/configs/phpstan.neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    level: 8
    paths:
        - app
        - modules
        - database
    excludePaths:
        - app/Console/Kernel.php
    checkMissingIterableValueType: true
    checkGenericClassInNonGenericObjectType: true
    treatPhpDocTypesAsCertain: false
```

Gli errori più frequenti al livello 8 e come si risolvono **davvero**:

| Errore | Correzione sbagliata | Correzione corretta |
|---|---|---|
| `Cannot call method on possibly null` | `@phpstan-ignore-next-line` | gestire il caso nullo o usare `findOrFail()` |
| `Parameter expects array<string>, array given` | `@var` generico | annotare il tipo con `array<int, string>` |
| `Method has no return type specified` | `mixed` | dichiarare il tipo reale |
| `Access to undefined property` | `@property` fittizia | verificare l'esistenza o correggere il model |
| `Unreachable statement` | rimuovere l'analisi | il ramo è davvero irraggiungibile: rimuoverlo |

La regola generale: **PHPStan ha quasi sempre ragione**. Quando segnala qualcosa che «non può
accadere», di solito può accadere in un percorso che non si era considerato.

### Annotazioni di soppressione

Ammesse solo con motivazione scritta:

```php
/** @phpstan-ignore-next-line La libreria dichiara un tipo errato; vedi issue upstream #412. */
$result = $externalClient->fetch($id);
```

Una soppressione senza motivazione viene respinta in revisione.

---

## La baseline

La baseline elenca gli errori **tollerati** in un momento dato. Serve per adottare l'analisi
statica su codice esistente senza fermare tutto.

```bash
vendor/bin/phpstan --generate-baseline
```

Regole d'uso:

| Regola | Motivo |
|---|---|
| La baseline si genera una volta, all'adozione | non è uno strumento quotidiano |
| Non cresce mai | una baseline crescente è debito che aumenta |
| Ogni voce rimossa è un miglioramento permanente | si riduce a ogni ciclo di manutenzione |
| Il codice nuovo non entra mai in baseline | va scritto conforme |
| La pipeline verifica che il numero di voci non aumenti | controllo automatico |

```bash
# In CI: fallisce se la baseline è cresciuta
php tooling/scripts/check-baseline.php --max=$(git show origin/main:phpstan-baseline.neon | grep -c 'message:')
```

Una baseline che cresce è il modo in cui un progetto abbandona l'analisi statica senza mai
dichiararlo.

---

## Pint

```json
{
    "preset": "laravel",
    "rules": {
        "declare_strict_types": true,
        "final_class": false,
        "ordered_imports": { "sort_algorithm": "alpha" },
        "no_unused_imports": true,
        "not_operator_with_successor_space": true,
        "trailing_comma_in_multiline": true,
        "void_return": true
    }
}
```

| Comando | Uso |
|---|---|
| `composer lint` | verifica, non modifica (usato in CI) |
| `composer lint:fix` | applica le correzioni |

La formattazione **non si discute in revisione**: è determinata da Pint. Chi ha preferenze diverse
le porta come proposta di modifica alla configurazione della Factory, non come commento su una PR.

---

## Rector

```php
return RectorConfig::configure()
    ->withPaths([__DIR__ . '/app', __DIR__ . '/modules'])
    ->withPhpSets(php84: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
    )
    ->withSkip([
        RemoveUnusedPrivateMethodRector::class,   // rimuove metodi usati solo dai test
    ]);
```

Rector **non blocca** la pipeline: propone. L'esecuzione è periodica e il diff si rivede sempre.

| Uso corretto | Uso scorretto |
|---|---|
| aggiornare la sintassi a una nuova versione di PHP | riorganizzare l'architettura |
| aggiungere tipi di ritorno mancanti | «pulire» il codice in blocco senza revisione |
| rimuovere codice morto verificato | applicare su un repository senza test |

---

## Test di architettura

L'analisi statica verifica i tipi; i test di architettura verificano le **decisioni**.

```php
arch('il dominio è indipendente dal framework')
    ->expect('App\Domain')->not->toUse('Illuminate');

arch('i DTO sono immutabili')
    ->expect('App\Application')->classes()->toHaveSuffix('Data')->toBeReadonly();

arch('nessun helper globale di debug')
    ->expect(['dd', 'dump', 'ray'])->not->toBeUsed();

arch('le migration non contengono query sui dati')
    ->expect('Database\Migrations')->not->toUse('App\Models');
```

Sono la traduzione eseguibile delle regole in `rules/`: senza, quelle regole dipendono
dall'attenzione dei revisori.

---

## Integrazione in pipeline

```yaml
- name: Formattazione
  run: composer lint

- name: Analisi statica
  run: composer analyse

- name: Baseline non cresciuta
  run: php tooling/scripts/check-baseline.php

- name: Test di architettura
  run: composer test:arch

- name: Test
  run: composer test --coverage --min=80
```

L'ordine è deliberato: dal più veloce al più lento, così un errore banale non fa attendere dieci
minuti di test.

---

## Esempi

### Esempio 1 — PHPStan che trova un difetto reale

```php
public function handle(): void
{
    $batch = Batch::find($this->batchId);
    $batch->recalculate();   // PHPStan: Cannot call method on Batch|null
}
```

Non è un falso positivo: se il lotto è stato cancellato tra l'emissione dell'evento e l'esecuzione
del job, `find()` ritorna `null` e il job va in errore.

```php
$batch = Batch::find($this->batchId);

if ($batch === null) {
    return;   // il lotto è stato rimosso: nulla da ricalcolare
}

$batch->recalculate();
```

### Esempio 2 — soppressione legittima

```php
/**
 * @phpstan-ignore-next-line
 * Il pacchetto dichiara `array` senza tipo generico; la struttura reale è
 * array<string, string>. Rimuovere quando la versione 4.x sarà rilasciata.
 */
$headers = $client->getHeaders();
```

Motivazione, causa esterna, condizione di rimozione.

---

## Best practice

- Livello 8 dal primo giorno di un progetto nuovo.
- Correggere il codice, non sopprimere l'errore.
- Baseline solo all'adozione su codice esistente, e sempre decrescente.
- Formattazione automatica, mai discussa in revisione.
- Rector periodico, con revisione del diff.
- Test di architettura per ogni regola strutturale che si dichiara.
- Eseguire gli strumenti in locale prima del commit.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Abbassare il livello per «sbloccare» | Si perde il valore dell'analisi | Correggere il codice |
| Aggiungere alla baseline il codice nuovo | Debito immediato | Baseline solo storica |
| Baseline che cresce | Abbandono silenzioso dell'analisi | Controllo automatico in CI |
| `@phpstan-ignore` senza motivazione | Soppressioni permanenti e inspiegabili | Motivazione obbligatoria |
| Discutere la formattazione in revisione | Tempo sprecato | Pint decide |
| Rector applicato senza test | Regressioni silenziose | Test verdi prima e dopo |
| Regole dichiarate ma non verificate | Erosione strutturale | Test di architettura |

---

## Checklist

- [ ] PHPStan al livello 8, senza eccezioni non motivate.
- [ ] Nessuna nuova voce in baseline.
- [ ] Il numero di voci in baseline è diminuito o è invariato.
- [ ] Ogni `@phpstan-ignore` ha una motivazione e una condizione di rimozione.
- [ ] Pint verde.
- [ ] Test di architettura presenti per le regole strutturali del progetto.
- [ ] La pipeline esegue tutti i controlli nell'ordine corretto.

---

## Riferimenti

- [Regole PHP](../../rules/php.md) · [Testing](../../rules/testing.md)
- [Strategia di testing](01-testing-strategy.md)
- [Configurazioni condivise](../../tooling/README.md)
- [Pipeline CI](../../deployment/ci/README.md)
