# Guide di migrazione

> Come si aggiorna un progetto esistente quando la Factory introduce una modifica incompatibile.

---

## Indice

1. [Descrizione](#descrizione)
2. [Quando serve una guida](#quando-serve-una-guida)
3. [Struttura di una guida](#struttura-di-una-guida)
4. [Indice delle migrazioni](#indice-delle-migrazioni)
5. [Come si esegue una migrazione](#come-si-esegue-una-migrazione)
6. [Esempi](#esempi)
7. [Best practice](#best-practice)
8. [Errori comuni](#errori-comuni)
9. [Checklist](#checklist)
10. [Riferimenti](#riferimenti)

---

## Descrizione

Una modifica breaking senza guida di migrazione produce progetti che restano indietro per sempre:
nessuno aggiorna, la Factory si biforca, e dopo due versioni l'allineamento diventa una
riscrittura. La guida è la parte **non facoltativa** di ogni modifica MAJOR.

---

## Quando serve una guida

Sempre, quando la modifica è classificata MAJOR secondo [`versioning.md`](../versioning.md):

- rimozione o cambio di firma di un contratto pubblico della Foundation;
- cambio di struttura delle tabelle create dalla Foundation;
- cambio del payload di un evento pubblicato;
- cambio del significato di una chiave di configurazione;
- ristrutturazione del repository che invalida percorsi citati nei progetti;
- cambio di una regola da facoltativa a vincolante.

---

## Struttura di una guida

Nome file: `NNNN-titolo-breve.md` (numerazione progressiva, quattro cifre).

Sezioni obbligatorie:

| Sezione | Contenuto |
|---|---|
| Intestazione | numero, titolo, versione di origine e di destinazione, ADR collegata |
| Che cosa cambia | descrizione tecnica precisa della differenza |
| Perché | rimando alla ADR, in una frase |
| Chi è impattato | criterio per capire se il proprio progetto è coinvolto |
| Tempo stimato | ordine di grandezza realistico |
| Procedura | passi numerati, eseguibili, con comandi |
| Automazione | script o regola Rector se disponibile |
| Verifica | come si accerta che la migrazione sia riuscita |
| Rollback | come si torna indietro se qualcosa va storto |
| Problemi noti | casi particolari e loro soluzione |

---

## Indice delle migrazioni

| # | Titolo | Da → A | ADR | Stato |
|---|---|---|---|---|
| — | *Nessuna migrazione registrata: la Factory non ha ancora rilasciato una MAJOR* | — | — | — |

Quando si aggiunge una guida, questa tabella va aggiornata nello stesso commit.

---

## Come si esegue una migrazione

Procedura standard, valida per qualsiasi guida:

1. **Leggere la guida per intero** prima di toccare il codice.
2. **Verificare l'impatto**: il criterio «chi è impattato» dice se il progetto è coinvolto.
3. **Branch dedicato**: `chore/migrazione-NNNN-titolo`.
4. **Backup dello stato**: database di staging, non di produzione.
5. **Aggiornare la dipendenza** alla nuova major.
6. **Eseguire l'automazione** se fornita (Rector, script, comando Artisan).
7. **Applicare i passi manuali** residui.
8. **Eseguire `composer qa`**: lint, analisi statica, test.
9. **Verificare** secondo la sezione dedicata della guida.
10. **Rilasciare in staging**, osservare, poi produzione.

Le migrazioni si applicano **una alla volta e in ordine**: mai saltare una major intermedia.

---

## Esempi

### Esempio di intestazione di guida

```markdown
# 0003 — TenantId come Value Object

| | |
|---|---|
| **Da** | foundation-v2.x |
| **A** | foundation-v3.0.0 |
| **ADR** | [ADR-0011](../../architecture/decisions/0011-tenant-id-value-object.md) |
| **Tempo stimato** | 2-4 ore per progetto |
| **Automazione** | parziale (regola Rector fornita) |
```

### Esempio di sezione «Chi è impattato»

```markdown
## Chi è impattato

Tutti i progetti che:

- implementano `TenantAware` in classi proprie, oppure
- chiamano direttamente `forTenant(string $id)`.

Per verificarlo:

    grep -rn "forTenant(" app/ --include="*.php"

Se il comando non produce risultati, il progetto non è impattato: basta aggiornare la dipendenza.
```

### Esempio di sezione «Rollback»

```markdown
## Rollback

La migrazione non modifica lo schema del database: il rollback è il ripristino della versione
precedente della dipendenza.

    composer require widstudios/foundation:^2.9
    git revert <commit-di-migrazione>
    composer qa

Se erano già state eseguite migration di schema, applicare prima:

    php artisan tenants:migrate:rollback --step=1
```

---

## Best practice

- Scrivere la guida **contestualmente** alla modifica, non dopo il rilascio.
- Fornire sempre un modo per capire in 30 secondi se si è impattati.
- Automatizzare ciò che è automatizzabile: una regola Rector vale dieci pagine di istruzioni.
- Stimare il tempo per eccesso: una stima ottimistica scoraggia l'aggiornamento.
- Provare la guida su un progetto reale prima di pubblicarla.
- Indicare sempre un percorso di rollback, anche quando sembra ovvio.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Guida scritta dopo il rilascio | I progetti restano fermi alla versione precedente | Guida obbligatoria prima del merge |
| Nessun criterio di impatto | Tutti devono leggere tutto | Sezione «chi è impattato» con comando di verifica |
| Procedura non provata | Fallisce al primo tentativo reale | Provarla su un progetto pilota |
| Rollback assente | Nessuno osa aggiornare in produzione | Documentare sempre il ritorno indietro |
| Saltare major intermedie | Stati inconsistenti difficili da diagnosticare | Applicare in ordine, una alla volta |

---

## Checklist

- [ ] La guida ha numero progressivo e intestazione completa.
- [ ] È linkata dalla ADR e dal `CHANGELOG.md`.
- [ ] Contiene il criterio di impatto con comando di verifica.
- [ ] La procedura è stata provata su un progetto reale.
- [ ] È presente la sezione di rollback.
- [ ] La tabella indice di questo documento è aggiornata.

---

## Riferimenti

- [Versionamento](../versioning.md)
- [Processo decisionale](../decision-process.md)
- [ADR](../../architecture/decisions/README.md)
- [CHANGELOG](../../CHANGELOG.md)
