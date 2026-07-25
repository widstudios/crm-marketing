# Politica linguistica

> Cosa si scrive in italiano, cosa in inglese, e come si gestiscono i termini di dominio del
> cliente senza contaminare il codice.

---

## Indice

1. [Descrizione](#descrizione)
2. [La regola in una riga](#la-regola-in-una-riga)
3. [Tabella di riferimento](#tabella-di-riferimento)
4. [Codice in inglese](#codice-in-inglese)
5. [Documentazione in italiano](#documentazione-in-italiano)
6. [Termini di dominio del cliente](#termini-di-dominio-del-cliente)
7. [Localizzazione dell'interfaccia](#localizzazione-dellinterfaccia)
8. [Esempi](#esempi)
9. [Best practice](#best-practice)
10. [Errori comuni](#errori-comuni)
11. [Checklist](#checklist)
12. [Riferimenti](#riferimenti)

---

## Descrizione

Il bilinguismo non è una preferenza estetica: è una separazione di responsabilità.

- Il **codice** vive nell'ecosistema PHP/Laravel, che è in inglese. Un identificatore italiano in
  mezzo a `whereHas`, `firstOrFail` e `dispatch` produce frasi ibride illeggibili
  (`$fornitore->whereHas('movimenti')`), rompe le convenzioni di Eloquent e disorienta chi arriva
  dalla documentazione ufficiale.
- La **documentazione** serve alle persone dell'azienda, che ragionano in italiano. Scriverla in
  inglese aggiungerebbe un costo di traduzione ad ogni lettura, per un beneficio nullo.

---

## La regola in una riga

> **Tutto ciò che il computer esegue è in inglese. Tutto ciò che una persona legge per capire è
> in italiano.**

---

## Tabella di riferimento

| Elemento | Lingua | Esempio |
|---|---|---|
| Nomi di classe, metodo, variabile | inglese | `ArchiveSupplierAction`, `$expiryDate` |
| Tabelle e colonne | inglese | `suppliers`, `vat_number` |
| Chiavi di configurazione | inglese | `tenancy.central_domains` |
| Chiavi di traduzione | inglese | `suppliers.status.archived` |
| Nomi di rotta | inglese | `suppliers.archive` |
| Permessi | inglese | `supplier.archive` |
| Nomi di evento e job | inglese | `SupplierArchived`, `SendExpiryReportJob` |
| Messaggi di commit | italiano | `feat(suppliers): archiviazione con vincolo di stato` |
| Branch | inglese (tipo) + italiano (descrizione) | `feat/archiviazione-fornitori` |
| Documentazione | italiano | questo documento |
| Commenti nel codice | italiano | `// La scadenza si calcola dalla consegna, non dalla produzione.` |
| PHPDoc | inglese per i tipi, italiano per le spiegazioni | vedi sotto |
| Messaggi di eccezione | inglese | `Supplier is already archived.` |
| Messaggi all'utente finale | tradotti, chiave in inglese | `__('suppliers.archived')` |
| Log tecnici | inglese | `Tenant database provisioned` |
| ADR | italiano | `0002-tenant-isolation-strategy.md` (titolo file in inglese) |
| Nomi di file di documentazione | italiano o inglese, coerenti nella cartella | `03-multitenancy-overview.md` |

---

## Codice in inglese

### Perché anche i commenti tecnici seguono la regola?

Non la seguono: i commenti sono in **italiano**. La distinzione è tra ciò che il computer
interpreta (inglese) e ciò che spiega a una persona (italiano).

```php
/**
 * Archivia il fornitore.
 *
 * L'archiviazione è irreversibile per scelta di dominio: un fornitore archiviato
 * non può tornare attivo, perché la sua posizione fiscale è stata chiusa.
 *
 * @throws SupplierAlreadyArchived quando il fornitore è già in stato archiviato
 */
public function execute(Supplier $supplier): Supplier
```

I tag PHPDoc (`@throws`, `@param`, `@return`) e i tipi restano in inglese perché sono sintassi;
il testo descrittivo è in italiano perché è spiegazione.

### Traduzioni di dominio ricorrenti

Un piccolo dizionario condiviso evita che ogni progetto traduca a modo suo:

| Italiano | Inglese | Note |
|---|---|---|
| fornitore | `supplier` | non `provider`, riservato ai servizi tecnici |
| cliente (del tenant) | `customer` | mai `client`, e mai `tenant` |
| magazzino | `warehouse` | il luogo fisico |
| giacenza | `stock` | la quantità |
| lotto | `batch` | non `lot` |
| scadenza | `expiry_date` | non `deadline` |
| movimento | `movement` | non `transaction` |
| causale | `reason` | |
| pratica (CAF) | `case` | |
| scheda / anagrafica | `record` / `registry` | |
| fattura | `invoice` | |
| preventivo | `quote` | |
| ordine | `order` | |
| ubicazione | `location` | |
| operatore | `operator` | l'utente che esegue |
| allegato | `attachment` | |
| protocollo | `protocol_number` | |

Il dizionario si estende nel glossario del progetto quando il dominio introduce termini nuovi.

---

## Documentazione in italiano

Con due eccezioni pratiche:

1. **I termini tecnici consolidati restano in inglese**: repository, deploy, commit, rollback,
   queue, cache. Tradurli («deposito», «rilascio», «coda») produce testi che nessuno riconosce.
2. **I nomi di file** possono essere in inglese quando corrispondono a concetti tecnici
   (`multitenancy-overview.md`), purché la scelta sia coerente dentro la cartella.

---

## Termini di dominio del cliente

Un cliente può usare un termine proprio per un concetto standard: «commessa» per progetto,
«posizione» per pratica, «articolo» per prodotto.

**La regola: il termine del cliente non entra nel codice.** Si mappa esplicitamente.

Nel glossario del progetto:

```markdown
| Termine del cliente | Concetto | Codice |
|---|---|---|
| commessa | progetto | `Project` |
| posizione | pratica | `Case` |
| bolla | documento di trasporto | `DeliveryNote` |
```

E nell'interfaccia si traduce, senza toccare il codice:

```php
// resources/lang/it/projects.php
return [
    'title' => 'Commesse',
    'singular' => 'Commessa',
];
```

Così, se il secondo cliente chiama la stessa cosa «lavoro», cambia un file di traduzione e non
duecento riferimenti nel codice.

---

## Localizzazione dell'interfaccia

| Regola | Motivo |
|---|---|
| Le chiavi di traduzione sono in inglese, gerarchiche | stabili, indipendenti dalla lingua di visualizzazione |
| Ogni stringa visibile passa da `__()` | nessun testo scritto direttamente nelle viste |
| Il fallback è l'italiano | è la lingua dei nostri clienti |
| L'inglese è mantenuto per le API e i log | interoperabilità |
| Date e numeri sono formattati per locale | mai formattazione manuale |
| I file di lingua seguono la struttura dei moduli | `resources/lang/it/suppliers.php` |

```php
// ✗ Testo nella vista
<h1>Elenco fornitori</h1>

// ✓ Chiave di traduzione
<h1>{{ __('suppliers.index.title') }}</h1>
```

---

## Esempi

### Esempio 1 — classe conforme

```php
<?php

declare(strict_types=1);

namespace App\Application\Suppliers\Actions;

/**
 * Sospende un fornitore.
 *
 * La sospensione è reversibile: serve a bloccare temporaneamente gli ordini
 * senza perdere lo storico. Il ripristino avviene con ReactivateSupplierAction.
 */
final readonly class SuspendSupplierAction
{
    public function execute(Supplier $supplier, string $reason): Supplier
    {
        // Il motivo della sospensione è obbligatorio per la tracciabilità richiesta
        // dalla procedura di qualità del cliente.
        throw_if($reason === '', new InvalidArgumentException('Suspension reason is required.'));

        // …
    }
}
```

Identificatori inglesi, spiegazioni italiane, messaggio di eccezione inglese.

### Esempio 2 — non conforme

```php
// ✗ Identificatori in italiano: rompe le convenzioni Eloquent e produce frasi ibride
final class SospendiFornitoreAction
{
    public function esegui(Fornitore $fornitore, string $motivo): Fornitore
    {
        return $fornitore->sospendi($motivo);   // $fornitore->whereHas('movimenti')…
    }
}
```

### Esempio 3 — mappatura di un termine del cliente

Il cliente chiama «bolla» quello che il dominio conosce come documento di trasporto.

- Codice: `DeliveryNote`, tabella `delivery_notes`.
- Glossario di progetto: riga di mappatura.
- Interfaccia: `resources/lang/it/delivery_notes.php` con `'singular' => 'Bolla'`.

Se un secondo cliente la chiama «DDT», cambia solo il file di lingua.

---

## Best practice

- Decidere la traduzione di un termine **una volta**, scriverla nel glossario, riusarla ovunque.
- Non tradurre i termini tecnici consolidati.
- Ogni stringa visibile passa da `__()`, anche quando il progetto è monolingua: il costo è nullo
  e la seconda lingua arriva sempre.
- Le chiavi di traduzione seguono la struttura del dominio, non la posizione nella pagina.
- Mappare i termini del cliente nel glossario di progetto, non nel codice.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Identificatori in italiano | Codice ibrido, convenzioni Eloquent rotte | Inglese nel codice |
| Testo scritto nelle viste | Seconda lingua impossibile senza riscrittura | `__()` sempre |
| Chiavi di traduzione in italiano | Diventano assurde quando si aggiunge l'inglese | Chiavi in inglese |
| Termine del cliente nel codice | Riscrittura al cliente successivo | Mappatura nel glossario |
| Traduzione di termini tecnici | Testi incomprensibili | Lasciare deploy, commit, cache |
| Commenti in inglese approssimativo | Spiegazioni peggiori dell'assenza di commento | Italiano nei commenti |
| Traduzioni diverse dello stesso termine tra progetti | Confusione, ricerche inefficaci | Dizionario condiviso |

---

## Checklist

- [ ] Tutti gli identificatori sono in inglese.
- [ ] Tutte le colonne e le tabelle sono in inglese.
- [ ] Le chiavi di traduzione sono in inglese e gerarchiche.
- [ ] Nessun testo visibile è scritto direttamente in una vista.
- [ ] I commenti e la documentazione sono in italiano.
- [ ] I termini del cliente sono mappati nel glossario di progetto.
- [ ] Le traduzioni dei termini di dominio seguono il dizionario condiviso.

---

## Riferimenti

- [Glossario](../00-introduction/06-glossary.md)
- [Regole di naming](../../rules/naming.md)
- [Regole di internazionalizzazione](../../rules/i18n.md)
- [Stile della documentazione](01-documentation-style.md)
