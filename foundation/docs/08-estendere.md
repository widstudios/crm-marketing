# Foundation — Estendere

> Quando qualcosa appartiene alla Foundation, come si aggiunge, e come si toglie senza rompere i
> progetti che la usano.

---

## Indice

1. [Descrizione](#descrizione)
2. [Il criterio di ammissione](#il-criterio-di-ammissione)
3. [Che cosa non entra mai](#che-cosa-non-entra-mai)
4. [Come si aggiunge](#come-si-aggiunge)
5. [Modifiche retrocompatibili e non](#modifiche-retrocompatibili-e-non)
6. [Deprecare e rimuovere](#deprecare-e-rimuovere)
7. [Esempi](#esempi)
8. [Best practice](#best-practice)
9. [Errori comuni](#errori-comuni)
10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

La Foundation è il codice che tutti i progetti ereditano. Ogni aggiunta ha quindi un costo che non
si vede al momento in cui la si fa: aumenta la superficie da mantenere, da documentare e da non
rompere — per anni, su ogni progetto.

Questo documento serve a rendere quel costo visibile **prima** dell'aggiunta.

---

## Il criterio di ammissione

> «Questo artefatto sarebbe utile identico in almeno **tre** software diversi?»

Tre, non due. Due occorrenze dimostrano una somiglianza; tre dimostrano un pattern. Promuovere alla
seconda occorrenza produce astrazioni costruite su un campione insufficiente, che al terzo progetto
vanno piegate — e un'astrazione piegata è peggio della duplicazione che sostituiva.

Tre domande di controllo, in ordine:

1. **È generico davvero, o è generico se lo si guarda da lontano?** Un `DocumentService` che
   funziona per tre progetti perché ognuno lo configura in modo diverso non è generico: è un
   punto di variabilità travestito da riuso.
2. **Il terzo progetto lo userebbe *identico*?** Se richiede un parametro in più per ogni nuovo
   caso, sta accumulando le differenze invece di astrarle.
3. **Che cosa succede se non lo si aggiunge?** Se la risposta è «venti righe duplicate in tre
   progetti», la duplicazione può essere la scelta giusta. Se è «l'isolamento tra tenant dipende da
   venti righe duplicate», non lo è.

---

## Che cosa non entra mai

| Non entra | Motivo | Dove va |
|---|---|---|
| Entità di dominio | è la regola di ammissione della Factory | nel progetto |
| Logica verticale (magazzino, CRM, ticket) | contamina tutti i progetti | nel progetto |
| Il model del tenant | ogni progetto ha colonne diverse | nel progetto, sul contratto |
| Ruoli e permessi predefiniti | è architettura, non infrastruttura | nel progetto o in un modulo |
| Astrazioni per un solo caso d'uso | costo senza beneficio | nel progetto |
| Wrapper su API del framework senza valore aggiunto | livello in più da attraversare | da nessuna parte |
| Funzionalità opzionali con tabelle proprie | costo imposto a chi non le usa | in un modulo |

L'ultima riga è la ragione per cui l'audit ha il contratto nella Foundation e l'implementazione in
un modulo: chi non ha requisiti di tracciamento non deve portarsi dietro le tabelle.

---

## Come si aggiunge

1. **Verificare il criterio** — tre progetti, identico. Se il terzo non esiste ancora, aspettare.
2. **Verificare che non esista già** — la duplicazione dentro la Foundation è il difetto peggiore,
   perché si propaga ovunque.
3. **Se tocca il contratto pubblico, scrivere una ADR** — interfacce, classi base, trait, chiavi di
   configurazione, firme dei comandi.
4. **Scrivere il codice con il test** — nessun artefatto senza test; per la Foundation la soglia è
   più alta: le classi base e i bootstrapper vanno al 100%.
5. **Documentare in `docs/`** — con esempi eseguibili, non pseudo-codice.
6. **Aggiornare il `CHANGELOG.md`** con la categoria corretta.
7. **Far verificare dal Foundation Owner e, per i contratti, dall'Architecture Owner.**

---

## Modifiche retrocompatibili e non

| Modifica | Livello |
|---|---|
| Nuovo metodo su una classe base | MINOR |
| Nuovo parametro **facoltativo** in coda a una firma | MINOR |
| Nuova chiave di configurazione con valore predefinito | MINOR |
| Nuova classe, nuovo trait, nuovo comando | MINOR |
| Nuovo metodo su un'**interfaccia** | **MAJOR** |
| Parametro reso obbligatorio | **MAJOR** |
| Cambio di tipo o di semantica di un ritorno | **MAJOR** |
| Rimozione o rinomina di qualunque elemento pubblico | **MAJOR** |
| Cambio del valore predefinito di una chiave di configurazione | **MAJOR** |

Il caso che sfugge più spesso è il **cambio di valore predefinito**: sembra una modifica innocua e
cambia il comportamento di ogni progetto che non aveva dichiarato quella chiave, al primo
aggiornamento, senza che nulla nel loro codice sia cambiato.

Aggiungere un metodo a un'interfaccia è MAJOR perché rompe ogni implementazione esistente. Quando
serve estendere un contratto senza rompere, le strade sono due: una nuova interfaccia che estende la
precedente, oppure un metodo con implementazione predefinita su una classe base astratta.

---

## Deprecare e rimuovere

Nulla si rimuove senza essere prima deprecato.

```php
/**
 * @deprecated dalla 2.4.0, rimozione prevista nella 3.0.0.
 *             Usare TenantCacheKey::for() al suo posto.
 */
public function cacheKey(string $key): string
{
    return $this->make($key);
}
```

Il ciclo:

| Fase | Cosa succede |
|---|---|
| Deprecazione | annotazione `@deprecated`, voce nel `CHANGELOG.md`, alternativa indicata |
| Periodo di grazia | almeno una versione MINOR completa, mai meno di **tre mesi** |
| Rimozione | solo in una MAJOR, con guida di migrazione in `governance/migrations/` |

Una deprecazione senza **data di rimozione** non è una deprecazione: è un commento. Il codice
deprecato senza scadenza resta per anni, e la sua presenza suggerisce che sia ancora una scelta
legittima.

---

## Esempi

### Aggiunta ammessa

`TenantCacheKey` è entrato nella Foundation dopo essere comparso, in forme leggermente diverse, in
tre progetti — e dopo che in uno dei tre la forma leggermente diversa aveva prodotto una collisione
di chiavi tra due clienti.

Soddisfa il criterio su tutti e tre i punti: è identico ovunque, il terzo progetto lo usa senza
modifiche, e non aggiungerlo significava lasciare l'isolamento della cache alla disciplina di chi
scrive la chiave.

### Aggiunta rifiutata

Una proposta di `ExportService` generico, capace di produrre CSV, Excel e PDF da qualunque query.

Rifiutata alla seconda domanda: ogni progetto lo avrebbe usato con una configurazione diversa —
colonne, intestazioni, formattazioni, raggruppamenti — e l'insieme delle opzioni sarebbe cresciuto a
ogni nuovo caso. Sarebbe stato un punto di variabilità travestito da riuso.

Esito: l'esportazione è diventata un **modulo**, con un contratto stretto e un'implementazione per
formato. Chi non esporta non se lo porta dietro.

---

## Best practice

- Aspettare il terzo progetto. Due occorrenze non dimostrano un pattern.
- Preferire un modulo alla Foundation quando la funzionalità è opzionale o ha tabelle proprie.
- Scrivere la ADR prima del codice, quando è ancora possibile cambiare idea a costo zero.
- Portare la copertura al 100% su classi base e bootstrapper: un difetto lì si propaga ovunque.
- Deprecare sempre con una data di rimozione.
- Documentare le assenze: sapere che qualcosa **non** c'è, e perché, evita la seconda proposta
  identica.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Promuovere alla seconda occorrenza | Astrazione su campione insufficiente | Aspettare la terza |
| Astrazione con molte opzioni | Variabilità travestita da riuso | Modulo, o duplicazione |
| Logica di dominio nella Foundation | Contamina tutti i progetti | Nel progetto |
| Metodo aggiunto a un'interfaccia | Rompe ogni implementazione | Nuova interfaccia o classe base |
| Valore predefinito cambiato | Comportamento diverso senza modifiche al progetto | MAJOR e guida di migrazione |
| Deprecazione senza data | Il codice resta per anni | Data di rimozione obbligatoria |
| Rimozione senza deprecazione | Progetti rotti all'aggiornamento | Ciclo completo |
| Aggiunta senza ADR sul contratto pubblico | La superficie cresce senza controllo | ADR prima del codice |

---

## Checklist

- [ ] L'artefatto sarebbe identico in almeno tre progetti.
- [ ] Non esiste già, in nessuna forma, nella Foundation.
- [ ] Non contiene logica di dominio verticale.
- [ ] Se è opzionale o ha tabelle proprie, è un modulo e non un'aggiunta alla Foundation.
- [ ] Se tocca il contratto pubblico, esiste una ADR accettata.
- [ ] Test presenti; copertura al 100% se è una classe base o un bootstrapper.
- [ ] Documentato in `docs/`, con esempi eseguibili.
- [ ] `CHANGELOG.md` aggiornato con la categoria corretta.
- [ ] Se qualcosa viene deprecato, la data di rimozione è dichiarata.

---

## Riferimenti

- [Foundation](../README.md) · [Foundation Agent](../../agents/01-foundation-agent.md)
- [Versionamento](../../governance/versioning.md) · [Processo decisionale](../../governance/decision-process.md)
- [ADR — template](../../architecture/decisions/template.md)
- [Guida al contributo](../../CONTRIBUTING.md) · [Checklist Foundation](../../checklists/foundation-checklist.md)
