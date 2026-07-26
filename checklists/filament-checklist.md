# Checklist — Amministrazione

> Verifica che i pannelli dichiarino interfacce e deleghino i comportamenti, senza N+1 né azioni aperte.

| | |
|---|---|
| **Fase** | 5 — Amministrazione |
| **Agente** | [Filament Agent](../agents/07-filament-agent.md) |
| **Natura** | gate bloccante |

---

## Indice

1. [Descrizione](#descrizione) 2. [Verifiche](#verifiche) 3. [Comandi di verifica](#comandi-di-verifica)
4. [Esempi](#esempi) 5. [Best practice](#best-practice) 6. [Errori comuni](#errori-comuni)
7. [Checklist](#checklist) 8. [Riferimenti](#riferimenti)

---

## Descrizione

Verifica che i pannelli dichiarino interfacce e deleghino i comportamenti, senza N+1 né azioni aperte.

Ogni voce si verifica in modo oggettivo. Le voci si riportano **una per una**, con l'esito reale.

---

## Verifiche

### Pannelli

- [ ] Pannelli **separati** per Super Admin e Tenant Admin.
- [ ] Ogni pannello usa la guardia corretta.
- [ ] Il pannello tenant ha il middleware di risoluzione.

### Resource

- [ ] Nessuna logica di business nelle Resource.
- [ ] Nessun filtro per tenant dentro le Resource.
- [ ] Ogni etichetta passa da `__()`.
- [ ] Traduzioni presenti in `it` ed `en`.

### Form

- [ ] Validazione allineata a quella del dominio.
- [ ] Campi condizionali basati sullo stato (enum).
- [ ] Campi calcolati in sola lettura.
- [ ] Select con molte opzioni: ricerca lato server.

### Tabelle

- [ ] **Eager loading** dichiarato in `getEloquentQuery()`.
- [ ] Nessun accessor con query nelle colonne.
- [ ] Filtri e ricerca **solo** su colonne indicizzate.
- [ ] Ordinamento predefinito esplicito.
- [ ] Colonne pesanti nascoste per default.

### Azioni

- [ ] Ogni azione di modifica **delega a un'Action**.
- [ ] Ogni azione personalizzata ha `->authorize()`.
- [ ] Ogni azione massiva ha `->authorize()`.
- [ ] Le azioni distruttive hanno `requiresConfirmation()`.
- [ ] La visibilità riflette le transizioni ammesse dall'enum.
- [ ] Il corpo di `->action()` non contiene condizioni di dominio.

### Widget

- [ ] Cache con chiave **tenant-scoped**.
- [ ] Query aggregate, non iterative.
- [ ] Widget tabellari con limite.

### Stati dell'interfaccia

- [ ] Stato di caricamento gestito.
- [ ] Stato vuoto con messaggio e azione suggerita.
- [ ] Stato di errore gestito.
- [ ] Indicatore sulle azioni oltre 300 ms.

### Test

- [ ] Test di feature su ogni azione personalizzata.
- [ ] Numero di query verificato con dati realistici (≥ 500 righe).

---

## Comandi di verifica

```bash
php artisan tenant:seed --tenant=acme --class=Demo\\LargeDatasetSeeder
composer test
php tooling/scripts/check-filament-authorize.php    # azioni senza authorize()
```

Contare le query di ogni elenco e della dashboard, e riportare i numeri.

---

## Esempi

### Esito conforme

```markdown
### Quality gate
- checklists/filament-checklist.md: N/N soddisfatte.
- Voci non soddisfatte: nessuna.
```

### Esito con voci non soddisfatte

```markdown
### Quality gate
- checklists/filament-checklist.md: N-2/N soddisfatte.

Voci non soddisfatte:
- <voce>: <cosa manca>. Correzione: <cosa fare>.

Richiedo rework su questi punti.
```

---

## Best practice

- Verificare durante il lavoro, non solo alla fine.
- Eseguire davvero i comandi indicati.
- Dichiarare le voci non applicabili con la motivazione.
- Un gate rosso è un'informazione utile, non un fallimento personale.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Logica di business nella Resource | Non vale per API e importazioni | Delegare all'Action |
| Azione senza `authorize()` | Aperta a chiunque veda la pagina | Autorizzazione esplicita |
| Widget senza cache tenant-scoped | Dashboard lenta, o dati di altri tenant | Cache con prefisso |
| Accessor con query nelle colonne | N+1 per riga | `counts()`, `with()` |
| Filtro su colonna non indicizzata | Elenco lentissimo | Segnalare alla fase 3 |
| Stato vuoto non gestito | Tabella vuota senza spiegazioni | Componente dedicato |

---

## Checklist

- [ ] Ho verificato ogni voce eseguendo i comandi indicati.
- [ ] Ho riportato l'esito reale, voce per voce.
- [ ] Ho dichiarato le voci non applicabili.
- [ ] Se il gate è rosso, l'ho dichiarato.

---

## Riferimenti

- [Fase 5](../workflows/06-phase-filament.md) · [Filament Agent](../agents/07-filament-agent.md)
- [Filament](../rules/filament.md) · [UI](../rules/ui.md) · [Cache](../rules/cache.md)
