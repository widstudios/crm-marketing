# Stile della documentazione

> Come si scrive un documento della Factory perché sia utile a chi lo leggerà tra due anni, umano
> o agente.

---

## Indice

1. [Descrizione](#descrizione)
2. [Struttura obbligatoria](#struttura-obbligatoria)
3. [Registro linguistico](#registro-linguistico)
4. [Formattazione](#formattazione)
5. [Esempi di codice](#esempi-di-codice)
6. [Collegamenti](#collegamenti)
7. [Quando dividere un documento](#quando-dividere-un-documento)
8. [Documenti leggibili dagli agenti](#documenti-leggibili-dagli-agenti)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

La documentazione della Factory ha due categorie di lettori con esigenze diverse ma compatibili:

- **Le persone**, che leggono in diagonale, cercano la risposta e vogliono capire il perché.
- **Gli agenti**, che leggono tutto ma non colmano i sottintesi e non chiedono chiarimenti.

Ciò che serve a entrambi: struttura prevedibile, prescrizioni esplicite, esempi concreti, nessun
riferimento implicito a conoscenze non scritte.

---

## Struttura obbligatoria

Ogni file `.md` della Factory ha questa ossatura:

```markdown
# Titolo

> Riga di sintesi: che cos'è e a chi serve.

---

## Indice
## Descrizione
   …sezioni specifiche…
## Esempi
## Best practice
## Errori comuni
## Checklist
## Riferimenti
```

| Sezione | Scopo | Errore da evitare |
|---|---|---|
| Titolo | un solo `#` per file | più titoli di primo livello |
| Sintesi | una frase, dice a chi serve | slogan generico |
| Indice | link agli heading `##` | indice non aggiornato dopo le modifiche |
| Descrizione | il contesto e il problema | partire dalla soluzione |
| Esempi | almeno uno concreto | esempi inventati o non eseguibili |
| Best practice | prescrizioni operative | ripetere la descrizione |
| Errori comuni | tabella errore → conseguenza → rimedio | elenco di divieti senza conseguenze |
| Checklist | caselle verificabili | voci non verificabili («fare bene») |
| Riferimenti | link ad altri documenti | link esterni al posto di quelli interni |

Le sezioni intermedie sono libere e dipendono dall'argomento.

---

## Registro linguistico

### Prescrivere, non suggerire

| Verbo | Significato | Uso |
|---|---|---|
| **deve** | obbligatorio, verificabile | regole |
| **non deve** | vietato | regole |
| **può** | ammesso, a discrezione | opzioni |
| ~~dovrebbe~~ | ambiguo | **mai** |
| ~~sarebbe meglio~~ | non verificabile | **mai** |

Se non si riesce a scrivere «deve» o «può», il contenuto non è una regola: va in `docs/`.

### Persona e tono

- Impersonale o seconda persona plurale, mai prima persona singolare.
- Frasi brevi. Una proposizione subordinata per frase, al massimo due.
- Nessuna enfasi retorica: niente «fondamentale», «cruciale», «assolutamente».
- L'ironia e le battute non sopravvivono alla traduzione né a un agente: si evitano.

### Motivare

Ogni prescrizione dichiara il problema che previene. Una regola senza motivazione viene aggirata
appena diventa scomoda, perché nessuno sa cosa sta proteggendo.

```markdown
✗  Le Action devono essere `final`.
✓  Le Action devono essere `final`: l'ereditarietà tra Action produce gerarchie in cui il
   comportamento effettivo dipende dalla classe concreta e non è più deducibile dal nome.
```

---

## Formattazione

| Elemento | Regola |
|---|---|
| Lunghezza riga | ≤ 110 caratteri |
| Heading | `##` per le sezioni, `###` per le sottosezioni; mai saltare livelli |
| Elenchi | `-` per i puntati, `1.` per le sequenze |
| Tabelle | per confronti e riferimenti; mai per contenuti discorsivi |
| Grassetto | per i termini definiti; non per enfasi generica |
| Corsivo | per i termini stranieri non consolidati |
| Codice inline | per identificatori, comandi, percorsi |
| Emoji | solo nelle legende di stato (✅ 🟡 ⬜ ⚠️) |
| Separatori `---` | tra le sezioni principali |
| Citazioni `>` | per la riga di sintesi e per gli avvisi |

Le tabelle sono lo strumento più efficace per rendere consultabile un contenuto, e il più abusato:
una tabella con celle di tre righe è un elenco travestito.

---

## Esempi di codice

**Requisiti:**

1. **Eseguibili o realistici.** Niente pseudo-codice quando è possibile scrivere PHP valido.
2. **Completi nella parte rilevante.** Niente `// ...` al posto della logica che l'esempio dimostra.
3. **Con il linguaggio dichiarato** nel blocco: `php`, `bash`, `json`, `yaml`, `sql`.
4. **Conformi alle regole della Factory.** Un esempio non conforme insegna l'errore.
5. **Minimali.** Solo ciò che serve a dimostrare il punto.

Quando serve mostrare il contrasto tra corretto e sbagliato:

````markdown
```php
// ✗ Errato: la Action riceve una Request, quindi non è utilizzabile da CLI o coda.
public function execute(Request $request): Supplier { /* … */ }

// ✓ Corretto: la Action riceve un DTO validato.
public function execute(SupplierData $data): Supplier { /* … */ }
```
````

Mostrare **prima** l'errato e poi il corretto, mai il contrario: l'ultima cosa letta è quella che
resta.

---

## Collegamenti

| Tipo | Regola |
|---|---|
| Interni | **sempre relativi**: `../../rules/php.md` |
| A una sezione | ancora in minuscolo con trattini: `php.md#tipizzazione` |
| Esterni | solo verso documentazione ufficiale stabile |
| Al codice | percorso relativo dal documento |

Non usare mai URL assoluti verso il repository: si rompono al cambio di host, di organizzazione o
di nome del repository.

**Ogni documento deve essere raggiungibile** dall'indice della propria cartella. Un file non
linkato, per la Factory, non esiste.

---

## Quando dividere un documento

Segnali che un documento va diviso:

| Segnale | Soglia |
|---|---|
| Lunghezza | oltre ~500 righe |
| Argomenti | tratta più di un argomento autonomo |
| Lettori | serve a categorie diverse in parti diverse |
| Indice | l'indice ha più di 12 voci di secondo livello |

**Come si divide, correttamente:**

1. Si individuano gli argomenti autonomi (non si taglia a metà per lunghezza).
2. Si crea una cartella con lo stesso nome del documento.
3. Si crea `README.md` come indice, con la panoramica e i rimandi.
4. Ogni parte diventa un file con struttura completa.
5. Si aggiornano **tutti** i riferimenti al documento originale.

Dividere non significa mai **accorciare**: il contenuto totale resta, cambia solo la distribuzione.

---

## Documenti leggibili dagli agenti

Un agente non chiede chiarimenti e non colma i vuoti con l'esperienza. Perciò:

- **Nessun sottinteso.** «Come al solito» e «ovviamente» sono vietati.
- **Nessun riferimento temporale relativo.** «Recentemente», «l'ultima versione»: usare date e
  numeri di versione.
- **Nessun rimando a conversazioni.** Se una decisione è stata presa, esiste una ADR.
- **Vincoli in forma di elenco**, non annegati nella prosa.
- **Criteri di verifica espliciti**: come si controlla che la regola sia rispettata.
- **Esempi di violazione**, non solo di conformità: un agente riconosce meglio uno schema se ha
  visto entrambe le forme.

---

## Esempi

### Esempio 1 — sintesi efficace

```markdown
✗  > Questo documento parla della cache.
✓  > Come si costruiscono le chiavi di cache in un'applicazione multitenant e come si invalidano
     senza svuotare la cache degli altri tenant.
```

La seconda dice a chi serve il documento e cosa vi si trova.

### Esempio 2 — voce di checklist

```markdown
✗  - [ ] Il codice è ben scritto.
✓  - [ ] Ogni classe in `Actions/` è `final` e ha un solo metodo pubblico.
```

La prima non è verificabile: due revisori daranno risposte diverse.

### Esempio 3 — riga di tabella «errori comuni»

```markdown
| Errore | Conseguenza | Rimedio |
|---|---|---|
| Chiave di cache senza prefisso tenant | Un tenant legge i dati in cache di un altro | Usare `TenantCacheKey::for()` |
```

Tre colonne, tre informazioni distinte: cosa si sbaglia, cosa succede, cosa si fa invece.

---

## Best practice

- Scrivere l'indice **dopo** il contenuto, poi verificarne i link.
- Rileggere chiedendosi: «un agente potrebbe eseguire questo senza fare domande?».
- Preferire una tabella a tre paragrafi quando l'informazione è enumerabile.
- Datare le affermazioni che invecchiano (versioni, soglie, numeri).
- Collegare ogni regola al principio o alla ADR che la giustifica.
- Aggiornare il documento **nello stesso commit** della modifica che lo rende obsoleto.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| «dovrebbe» in una regola | Nessuno sa se è obbligatorio | «deve» o «può» |
| Esempi con `// ...` al posto della logica | Non copiabili, non verificabili | Codice completo nella parte rilevante |
| Link assoluti al repository | Si rompono al primo cambio di host | Link relativi |
| Documento non linkato dall'indice | Invisibile, muore | Aggiornare il `README.md` di cartella |
| Accorciare invece di dividere | Perdita di contenuto | Dividere in file collegati |
| Riferimenti temporali relativi | Insensati dopo sei mesi | Date e versioni |
| Regola senza motivazione | Aggirata alla prima difficoltà | Dichiarare il problema che previene |
| Checklist non verificabile | Revisori con esiti diversi | Voci controllabili in modo oggettivo |

---

## Checklist

- [ ] Il documento ha tutte le sezioni obbligatorie, nell'ordine.
- [ ] La riga di sintesi dice a chi serve il documento.
- [ ] L'indice è aggiornato e i suoi link funzionano.
- [ ] Ogni prescrizione usa «deve» o «può», mai «dovrebbe».
- [ ] Ogni regola dichiara il problema che previene.
- [ ] Gli esempi sono eseguibili e conformi alle regole.
- [ ] I link interni sono relativi e validi.
- [ ] Le voci di checklist sono verificabili in modo oggettivo.
- [ ] Il documento è linkato dall'indice della sua cartella.
- [ ] Se supera le 500 righe, è stato diviso.

---

## Riferimenti

- [Guida al contributo](../../CONTRIBUTING.md)
- [Regole di documentazione](../../rules/documentation.md)
- [Politica linguistica](03-language-policy.md)
- [Struttura del repository](../00-introduction/05-repository-structure.md)
- [Checklist documentazione](../../checklists/documentation-checklist.md)
