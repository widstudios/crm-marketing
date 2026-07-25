# Versionamento della Factory

> Come si numerano le versioni della AI Factory e della Foundation, cosa conta come modifica
> breaking, e come i progetti si agganciano a una versione.

---

## Indice

1. [Descrizione](#descrizione)
2. [Cosa viene versionato](#cosa-viene-versionato)
3. [Schema di versionamento](#schema-di-versionamento)
4. [Classificazione delle modifiche](#classificazione-delle-modifiche)
5. [Deprecazione](#deprecazione)
6. [Vincoli nei progetti](#vincoli-nei-progetti)
7. [Processo di rilascio](#processo-di-rilascio)
8. [Esempi](#esempi)
9. [Best practice](#best-practice)
10. [Errori comuni](#errori-comuni)
11. [Checklist](#checklist)
12. [Riferimenti](#riferimenti)

---

## Descrizione

La Factory è consumata in due modi diversi, e i due modi richiedono garanzie diverse:

- come **documentazione e regole**, lette da persone e agenti;
- come **codice** (`widstudios/foundation`), installato via Composer.

Il codice ha bisogno di garanzie forti (semver, deprecazioni, migrazioni). La documentazione ha
bisogno di tracciabilità (cosa è cambiato e quando), ma non blocca un progetto in esecuzione.
Per questo entrambe sono versionate, ma con regole diverse.

---

## Cosa viene versionato

| Artefatto | Schema | Tag | Consumato come |
|---|---|---|---|
| Factory (repository intero) | SemVer | `factory-vX.Y.Z` | riferimento documentale |
| Foundation (pacchetto PHP) | SemVer | `foundation-vX.Y.Z` | dipendenza Composer |
| Template | seguono la Factory | — | copiati al momento della generazione |
| Prompt e agenti | seguono la Factory, con `version:` nel front matter | — | letti dall'orchestratore |
| Moduli di catalogo | SemVer per modulo | `module-<nome>-vX.Y.Z` | installati nel progetto |

---

## Schema di versionamento

`MAJOR.MINOR.PATCH`

| Incremento | Quando | Impatto sui progetti |
|---|---|---|
| **MAJOR** | rimozione o modifica incompatibile di un contratto pubblico; cambio di stack; ristrutturazione del repository | richiede intervento, guida di migrazione obbligatoria |
| **MINOR** | nuove regole, nuovi template, nuovi moduli, nuove API retrocompatibili | adozione facoltativa, nessuna rottura |
| **PATCH** | correzioni, chiarimenti, refuso, esempi, fix non comportamentali | nessun impatto |

### Contratto pubblico della Foundation

È «pubblico» tutto ciò che un progetto può usare direttamente:

- interfacce in `foundation/src/Contracts/`
- classi astratte estendibili (`BaseAction`, `BaseRepository`, `BaseService`…)
- trait in `foundation/src/Concerns/`
- eventi emessi e loro payload
- chiavi di configurazione in `foundation/config/`
- comandi Artisan e loro firme
- nomi e struttura delle tabelle create dalle migration della Foundation

**Non** è pubblico: tutto ciò che è marcato `@internal`, le classi `final` non estendibili senza
interfaccia, i dettagli implementativi dei servizi.

---

## Classificazione delle modifiche

Tabella di riferimento rapida:

| Modifica | Classificazione |
|---|---|
| Aggiungere un metodo a un'interfaccia | **MAJOR** (rompe gli implementatori) |
| Aggiungere un metodo a una classe astratta con implementazione di default | MINOR |
| Aggiungere un parametro opzionale in coda | MINOR |
| Aggiungere un parametro obbligatorio | **MAJOR** |
| Rendere più stretto un tipo di parametro | **MAJOR** |
| Rendere più largo un tipo di parametro | MINOR |
| Rendere più stretto un tipo di ritorno | MINOR |
| Rinominare una colonna creata dalla Foundation | **MAJOR** |
| Aggiungere una colonna nullable | MINOR |
| Cambiare il payload di un evento | **MAJOR** |
| Aggiungere una chiave di configurazione con default | MINOR |
| Cambiare il default di una configurazione esistente | **MAJOR** se cambia il comportamento |
| Aggiungere una regola in `rules/` | MINOR |
| Rendere obbligatoria una regola prima facoltativa | **MAJOR** della Factory |
| Riorganizzare cartelle della Factory | **MAJOR** della Factory |
| Correggere un esempio errato | PATCH |

---

## Deprecazione

Nulla viene rimosso senza essere prima deprecato.

Ciclo minimo:

```
vX.Y     funzionalità marcata @deprecated + alternativa documentata + avviso a runtime (log)
vX.Y+1   avviso mantenuto, la Factory smette di usarla nei template
vX+1.0   rimozione
```

Marcatura nel codice:

```php
/**
 * @deprecated dalla 2.3.0, usare {@see TenantContext::current()}.
 *             Rimozione prevista nella 3.0.0.
 */
public function getTenant(): ?Tenant
{
    trigger_deprecation('widstudios/foundation', '2.3.0', 'Usare TenantContext::current().');

    return TenantContext::current();
}
```

Marcatura in un documento:

```markdown
> **Deprecato dalla 2.3.0.** Sostituito da [nuovo documento](./nuovo.md).
> Questo documento verrà rimosso nella 3.0.0.
```

---

## Vincoli nei progetti

Nel `composer.json` di un progetto generato:

```json
{
    "require": {
        "widstudios/foundation": "^2.4"
    }
}
```

Regole:

- Si usa sempre il vincolo **caret** (`^`), mai `*`, mai `dev-main` in produzione.
- L'aggiornamento *minor* è routine, previsto ad ogni ciclo di manutenzione.
- L'aggiornamento *major* è un'attività pianificata, con la sua guida di migrazione.
- Il file `composer.lock` è versionato e committato.

Il progetto dichiara inoltre a quale versione della **Factory documentale** è allineato, nel
proprio `CLAUDE.md`:

```markdown
Factory di riferimento: factory-v2.4.0
```

Questo permette a un agente di sapere quali regole erano vigenti quando il progetto è nato.

---

## Processo di rilascio

1. Verificare che `CHANGELOG.md` abbia la sezione `Unreleased` completa.
2. Determinare l'incremento in base alla modifica più impattante inclusa.
3. Se MAJOR: verificare che ogni voce breaking abbia la guida in `governance/migrations/`.
4. Chiudere la sezione `Unreleased` creando l'intestazione di versione con data.
5. Aggiornare la tabella delle versioni in [`roadmap.md`](roadmap.md).
6. Creare il tag annotato:
   ```bash
   git tag -a factory-v2.4.0 -m "Factory 2.4.0 — regole cache, modulo notifications"
   git push origin factory-v2.4.0
   ```
7. Comunicare ai progetti: elenco delle modifiche rilevanti e azioni richieste.

---

## Esempi

### Esempio 1 — MINOR

Si aggiunge il modulo `webhooks` al catalogo e la regola `rules/cache.md`.
Nessun progetto si rompe. Da `2.3.1` a `2.4.0`.

### Esempio 2 — MAJOR con deprecazione

`TenantAwareRepository::forTenant(string $id)` diventa `forTenant(TenantId $id)`.

- `2.5.0`: si aggiunge `forTenantId(TenantId $id)`, si deprecano le firme vecchie.
- `2.6.0`: i template smettono di usare la firma vecchia.
- `3.0.0`: si rimuove `forTenant(string)`, con guida
  `governance/migrations/0007-tenant-id-value-object.md`.

### Esempio 3 — PATCH

Un esempio in `rules/queue.md` usava `dispatchNow()`, rimosso da Laravel.
Si corregge in `dispatchSync()`. Da `2.4.0` a `2.4.1`.

---

## Best practice

- Classificare la modifica **prima** di scriverla: cambia il modo in cui la si progetta.
- Preferire l'aggiunta alla modifica: un metodo nuovo costa meno di una firma cambiata.
- Introdurre le modifiche breaking a gruppi, in una major pianificata, non a spizzichi.
- Tenere la finestra di deprecazione abbastanza lunga da coprire un ciclo di manutenzione reale.
- Automatizzare il controllo di compatibilità con uno strumento di analisi delle API pubbliche.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Aggiungere un metodo a un'interfaccia in minor | Tutti gli implementatori si rompono | Nuova interfaccia o classe astratta |
| Rimuovere senza deprecare | Aggiornamenti impossibili senza riscrittura | Rispettare il ciclo di deprecazione |
| Vincolo `dev-main` in produzione | Build non riproducibili | Vincolo caret + lock committato |
| Tag senza CHANGELOG | Nessuno sa cosa è cambiato | Rilascio bloccato finché il changelog non è completo |
| Cambiare il default di configurazione «tanto nessuno lo usa» | Comportamento diverso in produzione | Trattare come MAJOR |

---

## Checklist

- [ ] La modifica è classificata (MAJOR/MINOR/PATCH) e documentata.
- [ ] Le rimozioni sono precedute da almeno una versione di deprecazione.
- [ ] Ogni voce breaking ha la guida di migrazione.
- [ ] `CHANGELOG.md` è completo e la sezione è chiusa con data.
- [ ] Il tag è annotato e pubblicato.
- [ ] La roadmap riflette la nuova versione.

---

## Riferimenti

- [Governance](README.md) · [Roadmap](roadmap.md) · [Migrazioni](migrations/README.md)
- [CHANGELOG](../CHANGELOG.md)
- [Regole Git](../rules/git.md)
- [ADR](../architecture/decisions/README.md)
