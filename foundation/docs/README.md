# Documentazione della Foundation

> Indice della documentazione del pacchetto `widstudios/foundation`: come si installa, come
> funziona la tenancy, come si usano le classi base.

---

## Indice

1. [Descrizione](#descrizione)
2. [I documenti](#i-documenti)
3. [Percorsi di lettura](#percorsi-di-lettura)
4. [Esempi](#esempi)
5. [Best practice](#best-practice)
6. [Errori comuni](#errori-comuni)
7. [Checklist](#checklist)
8. [Riferimenti](#riferimenti)

---

## Descrizione

Questa cartella documenta la Foundation dal punto di vista di **chi la usa** in un progetto
generato, non di chi la sviluppa. Per il punto di vista di chi la estende, il riferimento è
[`08-estendere.md`](08-estendere.md) e il [Foundation Agent](../../agents/01-foundation-agent.md).

Il codice resta la specifica più precisa: quando questi documenti e il codice divergono, vale il
codice, e il documento è un difetto da correggere.

---

## I documenti

| Documento | Argomento |
|---|---|
| [01-installazione.md](01-installazione.md) | installazione, binding obbligatori, configurazione |
| [02-tenancy.md](02-tenancy.md) | contesto, manager, resolver, bootstrapper, i cinque punti |
| [03-action-e-dto.md](03-action-e-dto.md) | `BaseAction`, `BaseData`, transazioni ed eventi |
| [04-repository-e-query.md](04-repository-e-query.md) | repository, Query object, ordinamenti in lista bianca |
| [05-moduli.md](05-moduli.md) | registro dei moduli, dipendenze, provider |
| [06-audit.md](06-audit.md) | contratto di audit, voci, cosa non registrare mai |
| [07-testing.md](07-testing.md) | aiuti per i test, isolamento, orologio fermo |
| [08-estendere.md](08-estendere.md) | quando e come si aggiunge qualcosa alla Foundation |

---

## Percorsi di lettura

**Sto avviando un progetto nuovo** → [01](01-installazione.md) → [02](02-tenancy.md) →
[03](03-action-e-dto.md) → [07](07-testing.md)

**Devo scrivere una funzionalità** → [03](03-action-e-dto.md) → [04](04-repository-e-query.md) →
[06](06-audit.md)

**Devo aggiungere un modulo** → [05](05-moduli.md) → [`modules/README.md`](../../modules/README.md)

**Devo estendere la Foundation** → [08](08-estendere.md) →
[`governance/versioning.md`](../../governance/versioning.md)

---

## Esempi

Ogni documento contiene esempi eseguibili. Gli esempi non usano `// ...` al posto della logica:
quando serve una regola, la regola è scritta per intero, perché un esempio incompleto genera codice
incompleto.

---

## Best practice

- Leggere `02-tenancy.md` prima di scrivere qualunque cosa che tocchi dati: è il documento che
  spiega dove l'isolamento può rompersi.
- Aprire il codice della classe insieme al documento: sono complementari, non alternativi.
- Segnalare ogni divergenza tra documento e codice come difetto, non come dettaglio.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Usare la Foundation senza leggere la tenancy | Si violano i cinque punti senza saperlo | `02-tenancy.md` |
| Fidarsi del documento contro il codice | Si programma su un comportamento inesistente | Il codice è la specifica |
| Aggiungere alla Foundation senza ADR | Il contratto pubblico cresce senza controllo | `08-estendere.md` |

---

## Checklist

- [ ] Ho letto il documento dell'area su cui sto lavorando.
- [ ] Ho verificato sul codice ciò che il documento afferma.
- [ ] Se ho trovato una divergenza, l'ho segnalata.

---

## Riferimenti

- [Foundation](../README.md) · [Foundation Agent](../../agents/01-foundation-agent.md)
- [Architettura](../../architecture/README.md) · [Regole](../../rules/README.md)
