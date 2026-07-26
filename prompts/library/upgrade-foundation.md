# Prompt — aggiornare la Foundation

> Portare un progetto a una nuova versione della Foundation, minor o major.

| | |
|---|---|
| **Versione** | 1.0.0 |
| **Agenti** | Foundation → Testing |

---

## Indice

1. [Descrizione](#descrizione) 2. [Quando si usa](#quando-si-usa) 2. [Prerequisiti](#prerequisiti) 3. [Sequenza](#sequenza)
4. [Il prompt](#il-prompt) 5. [Definizione di «fatto»](#definizione-di-fatto) 6. [Esempi](#esempi)
7. [Best practice](#best-practice) 8. [Errori comuni](#errori-comuni) 9. [Checklist](#checklist)
10. [Riferimenti](#riferimenti)

---

## Descrizione

Aggiornare la Foundation tocca tutti i progetti, e la stessa modifica ha conseguenze diverse su
ognuno. Non è un aggiornamento di dipendenza come gli altri: la Foundation contiene i punti in cui
l'isolamento tra clienti dipende dal codice, e una regressione lì non produce un guasto ma una fuga
di dati.

Questo prompt distingue i tre casi — patch, minor, major — perché richiedono verifiche diverse. Per
una major la guida di migrazione in `governance/migrations/` non è un documento di supporto: è la
procedura, e va seguita nell'ordine in cui è scritta.

---

## Quando si usa

| Caso | Percorso |
|---|---|
| Patch di sicurezza | questo prompt, entro 72 ore |
| Aggiornamento minor | questo prompt, ciclo di manutenzione |
| Aggiornamento major | **guida di migrazione** + questo prompt |
| Allineamento a una nuova versione della Factory | questo prompt + revisione delle regole nuove |

---

## Prerequisiti

- [ ] Progetto allineato: `composer qa` verde prima di iniziare.
- [ ] Changelog della Foundation letto.
- [ ] Per una major: guida di migrazione letta **per intero**.
- [ ] Branch dedicato: `chore/upgrade-foundation-<versione>`.
- [ ] Backup di staging disponibile.

---

## Sequenza

```
1. Lettura del changelog e, per le major, della guida di migrazione
2. Verifica dell'impatto: il progetto usa ciò che è cambiato?
3. Aggiornamento della dipendenza
4. Esecuzione dell'automazione fornita (Rector, comandi)
5. Correzioni manuali residue
6. Esecuzione della suite
7. Verifica su staging
8. Aggiornamento del CLAUDE.md e del changelog di progetto
```

---

## Il prompt

```markdown
Aggiorna la Foundation del progetto «{{ NOME_PROGETTO }}», seguendo
`prompts/library/upgrade-foundation.md`.

## Aggiornamento

Da: {{ VERSIONE_ATTUALE }}
A: {{ VERSIONE_TARGET }}
Tipo: {{ patch | minor | major }}
Guida di migrazione: {{ PERCORSO }} (solo per le major)

## Procedura

### 1. Verifica l'impatto

Leggi il changelog della Foundation. Per ogni voce marcata `[BREAKING]`, verifica se il progetto è
interessato, usando il criterio indicato nella guida di migrazione.

Se il progetto non usa nulla di ciò che è cambiato, l'aggiornamento è un semplice
`composer update` seguito dalla suite.

Riporta l'esito della valutazione: quali modifiche interessano il progetto e quali no.

### 2. Aggiorna la dipendenza

    composer require widstudios/foundation:^{{ VERSIONE_TARGET }}

### 3. Esegui l'automazione

Se la guida fornisce una regola Rector o un comando, eseguilo **in anteprima** e rivedi il diff prima
di applicarlo. L'automazione non capisce l'intenzione: può cambiare più del previsto.

### 4. Correzioni manuali

Applica i passi manuali della guida, uno per volta, eseguendo la suite dopo ciascuno.

Se un passo della guida non funziona come descritto, **segnalalo**: la guida va corretta per i
progetti successivi.

### 5. Verifica

    composer qa
    php artisan test --env=testing-mysql
    php artisan tenants:migrate --pretend    # anteprima delle migration della Foundation
    php artisan tenants:migrate
    php artisan tenants:migrate:status

### 6. Verifica su staging

Rilascia su staging, verifica i percorsi critici, e **prova il rollback**: per una major, è la
verifica che conta di più.

### 7. Aggiorna la documentazione

- `CLAUDE.md`: versione della Foundation e della Factory di riferimento, data dell'allineamento.
- `CHANGELOG.md`: se l'aggiornamento cambia qualcosa per l'utente, dichiaralo.
- Deroghe: verifica se qualcuna è diventata inutile o non più applicabile.

## Vincoli

- **Non modificare il codice della Foundation**: se serve una correzione, si propone alla Factory.
- **Non aggirare una modifica breaking** con un adattatore locale senza dichiararlo: sarebbe una
  deroga non tracciata.
- **Non saltare major intermedie**: si applicano in ordine, una alla volta.
- Per le patch di sicurezza: la finestra è di **72 ore**, anche fuori dal ciclo ordinario.

## Output

Il progetto aggiornato, più un rapporto con: modifiche che interessavano il progetto, passi
eseguiti, problemi incontrati nella guida, esito della verifica su staging, tempo impiegato.

Il tempo impiegato serve alla Factory per correggere le stime delle guide future.
```

---

## Definizione di «fatto»

- [ ] Impatto valutato voce per voce sul changelog.
- [ ] Dipendenza aggiornata, `composer.lock` committato.
- [ ] Automazione eseguita con revisione del diff.
- [ ] Passi manuali applicati.
- [ ] `composer qa` verde; suite verde anche su MySQL.
- [ ] Migration della Foundation applicate a tutti i tenant.
- [ ] Verifica su staging, rollback provato per le major.
- [ ] `CLAUDE.md` aggiornato con le versioni e la data.
- [ ] Problemi della guida segnalati alla Factory.

---

## Esempi

### Esempio 1 — minor senza impatto

Changelog: tre voci, nessuna `[BREAKING]`, riguardano moduli che il progetto non usa.

```
composer update widstudios/foundation
composer qa            # verde
```

Trenta minuti, compresa la verifica su staging.

### Esempio 2 — major con impatto

`TenantAwareRepository::forTenant(string)` diventa `forTenantId(TenantId)`.

```
Verifica impatto   grep -rn "forTenant(" app/ → 14 occorrenze
Automazione        regola Rector fornita: applica 12 delle 14
Manuale            2 casi con logica condizionale, corretti a mano
Suite              verde dopo la correzione
Staging            rilascio, verifica, rollback provato, nuovo rilascio
Segnalazione       la guida non copre il caso con logica condizionale: proposta correzione
Tempo              3 ore (stima della guida: 2-4 ore)
```

---

## Best practice

- Aggiornare spesso e in piccolo: gli aggiornamenti rimandati diventano migrazioni.
- Leggere la guida per intero prima di iniziare, non passo per passo.
- Rivedere sempre il diff dell'automazione.
- Segnalare i problemi della guida: migliora i progetti successivi.
- Riportare il tempo impiegato: serve a correggere le stime.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Saltare major intermedie | Stati inconsistenti difficili da diagnosticare | Una alla volta, in ordine |
| Automazione applicata senza revisione | Modifiche non volute | Anteprima e revisione del diff |
| Adattatore locale per aggirare una breaking | Deroga non tracciata, divergenza | Applicare la migrazione |
| Modifica del codice della Foundation | Si perde all'aggiornamento successivo | Proporre alla Factory |
| Rollback non provato su una major | Nessun ritorno possibile in produzione | Prova su staging |
| Patch di sicurezza rimandata | Esposizione nota e non mitigata | Finestra di 72 ore |

---

## Checklist

- [ ] Changelog e guida letti.
- [ ] Impatto valutato.
- [ ] Automazione eseguita con revisione.
- [ ] Suite verde su SQLite e MySQL.
- [ ] Staging verificato; rollback provato per le major.
- [ ] `CLAUDE.md` e changelog aggiornati.
- [ ] Problemi della guida segnalati.

---

## Riferimenti

- [Libreria](README.md) · [Versionamento della Factory](../../governance/versioning.md)
- [Guide di migrazione](../../governance/migrations/README.md)
- [Foundation](../../foundation/README.md)
