# Checklist — Architettura

> Verifica che la struttura del progetto sia coerente, modulare e senza dipendenze circolari.

| | |
|---|---|
| **Fase** | 2 — Architettura |
| **Agente** | [Architect Agent](../agents/03-architect-agent.md) |
| **Natura** | gate bloccante |

---

## Indice

1. [Descrizione](#descrizione) 2. [Verifiche](#verifiche) 3. [Comandi di verifica](#comandi-di-verifica)
4. [Esempi](#esempi) 5. [Best practice](#best-practice) 6. [Errori comuni](#errori-comuni)
7. [Checklist](#checklist) 8. [Riferimenti](#riferimenti)

---

## Descrizione

Verifica che la struttura del progetto sia coerente, modulare e senza dipendenze circolari.

Ogni voce si verifica in modo oggettivo. Le voci si riportano **una per una**, con l'esito reale.

---

## Verifiche

### Bounded context

- [ ] Ogni entità appartiene a un contesto.
- [ ] I contesti sono nominati con il **linguaggio del dominio**, non con termini tecnici.
- [ ] I confini corrispondono a cambi di significato dei termini.

### Moduli

- [ ] Il **catalogo è stato verificato**: il riuso è dichiarato e motivato.
- [ ] Ogni modulo ha un manifesto `module.json` completo.
- [ ] Dipendenze obbligatorie **minime**; le facoltative in `optional`.
- [ ] **Nessuna dipendenza circolare.**
- [ ] Nessun modulo di catalogo dipende da un modulo di dominio.
- [ ] Eventi emessi e ascoltati dichiarati.
- [ ] Contratti pubblicati dichiarati.

### Livelli

- [ ] Grado di purezza dichiarato **per contesto**, con motivazione.
- [ ] I contratti sono collocati nel dominio.

### Dati

- [ ] Ogni entità ha la collocazione landlord/tenant dichiarata.
- [ ] **Nessuna entità di dominio nel landlord.**
- [ ] Gli aggregati verso il landlord contengono solo valori numerici.

### Autorizzazione

- [ ] Elenco dei permessi nel formato `<risorsa>.<azione>`.
- [ ] I permessi coprono ogni operazione dei casi d'uso.
- [ ] I permessi coprono anche i divieti dichiarati per gli attori.

### Normativa

- [ ] Ogni vincolo normativo ha una risposta architetturale.

### Decisioni

- [ ] Ogni decisione non ovvia ha una ADR di progetto.
- [ ] Ogni ADR dichiara alternative e conseguenze negative.
- [ ] Nessuna deviazione dall'architettura di riferimento senza ADR.

---

## Comandi di verifica

```bash
php tooling/scripts/check-module-deps.php     # dipendenze circolari
php tooling/scripts/check-permissions.php     # copertura dei permessi
```

Verifica manuale: per ogni caso d'uso della fase 1, individuare il modulo che lo realizza.

---

## Esempi

### Esito conforme

```markdown
### Quality gate
- checklists/architecture-checklist.md: N/N soddisfatte.
- Voci non soddisfatte: nessuna.
```

### Esito con voci non soddisfatte

```markdown
### Quality gate
- checklists/architecture-checklist.md: N-2/N soddisfatte.

Voci non soddisfatte:
- <voce>: <cosa manca>. Correzione: <cosa fare>.

Richiedo rework su questi punti.
```

---

## Best practice

- Verificare durante il lavoro, non solo alla fine.
- Eseguire davvero i comandi: la verifica «a memoria» non è una verifica.
- Dichiarare le voci non applicabili con la motivazione.
- Un gate rosso è un'informazione utile, non un fallimento personale.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Contesti su criteri tecnici | Confini che non corrispondono al dominio | Linguaggio del dominio |
| Modulo costruito invece che riusato | Duplicazione, manutenzione doppia | Verifica del catalogo |
| Troppe dipendenze obbligatorie | Moduli non indipendenti | `optional` + eventi |
| Dipendenza circolare | Ordine di caricamento impossibile | Ripensare i confini |
| Entità di dominio nel landlord | Isolamento compromesso | Criterio di collocazione |
| Decisioni non registrate | Motivazione perduta in mesi | ADR di progetto |

---

## Checklist

- [ ] Ho verificato ogni voce eseguendo i comandi indicati.
- [ ] Ho riportato l'esito reale, voce per voce.
- [ ] Ho dichiarato le voci non applicabili.
- [ ] Se il gate è rosso, l'ho dichiarato.

---

## Riferimenti

- [Fase 2](../workflows/03-phase-architecture.md) · [Architect Agent](../agents/03-architect-agent.md)
- [Sistema modulare](../architecture/10-modular-system.md) · [Contratto di modulo](../architecture/11-module-contract.md)
- [Catalogo moduli](../modules/README.md)
