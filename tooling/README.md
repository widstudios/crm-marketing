# Tooling

> Le configurazioni condivise e gli script che rendono verificabili le regole.

---

## Indice

1. [Descrizione](#descrizione)
2. [Configurazioni](#configurazioni)
3. [Gli script di verifica](#gli-script-di-verifica)
4. [Il contratto degli script](#il-contratto-degli-script)
5. [Uso in pipeline](#uso-in-pipeline)
6. [Aggiungere uno script](#aggiungere-uno-script)
7. [Esempi](#esempi)
8. [Best practice](#best-practice)
9. [Errori comuni](#errori-comuni)
10. [Checklist](#checklist)
11. [Riferimenti](#riferimenti)

---

## Descrizione

Una regola senza un modo di verificarla non è una regola: è un auspicio. Regge finché applicarla
costa poco e viene abbandonata alla prima scadenza stretta, senza che nessuno debba decidere di
abbandonarla.

Questa cartella contiene ciò che trasforma le regole di [`rules/`](../rules/README.md) in verifiche
automatiche. Non tutte le regole sono automatizzabili — «la logica sta nel dominio» richiede
giudizio — ma quelle che lo sono devono esserlo, perché una verifica automatica non si stanca, non
ha fretta e trova le stesse cose il venerdì sera prima di un rilascio.

---

## Configurazioni

| File | Strumento | Note |
|---|---|---|
| [`configs/pint.json`](configs/pint.json) | Laravel Pint | preset `laravel`, più `declare_strict_types` e ordine degli elementi di classe |
| [`configs/phpstan.neon`](configs/phpstan.neon) | PHPStan / Larastan | livello 8, con la motivazione del perché non 9 |
| [`configs/rector.php`](configs/rector.php) | Rector | mai in scrittura in pipeline: solo `--dry-run` |

Un progetto le include, non le copia:

```neon
includes:
    - ./vendor/widstudios/factory-tooling/phpstan.neon
```

Copiarle significa che una modifica alla Factory non arriva ai progetti, e che dopo un anno ogni
progetto ha una configurazione leggermente diversa che nessuno ricorda di aver cambiato.

---

## Gli script di verifica

### Sulla Factory

| Script | Verifica |
|---|---|
| [`check-docs.php`](scripts/check-docs.php) | link, ancore, sezioni obbligatorie, segnaposto, documenti orfani |
| [`check-links.php`](scripts/check-links.php) | i soli link, in un secondo |
| [`check-secrets.php`](scripts/check-secrets.php) | segreti nel working tree e, con `--history`, nei commit |
| [`list-agents.php`](scripts/list-agents.php) | elenco degli agenti; con `--check`, il loro contratto |

### Sui progetti generati

| Script | Verifica | Regole |
|---|---|---|
| [`check-security.php`](scripts/check-security.php) | `$guarded`, query concatenate, cache non tenant-scoped, `env()` fuori da `config/`, job senza `TenantAware` | [security](../rules/security.md) |
| [`check-policies.php`](scripts/check-policies.php) | ogni model ha una Policy, nessuna è permissiva, ognuna ha un test di rifiuto | [policies](../rules/policies.md) |
| [`check-authorization.php`](scripts/check-authorization.php) | punti di ingresso senza autorizzazione | [security](../rules/security.md) |
| [`check-filament-authorize.php`](scripts/check-filament-authorize.php) | azioni, pagine e widget Filament scoperti | [filament](../rules/filament.md) |
| [`check-schema.php`](scripts/check-schema.php) | `down()`, `float`, `enum` MySQL, `tenant_id`, vincoli, trasformazioni di dati | [sql](../rules/sql.md), [database](../rules/database.md) |
| [`check-tests.php`](scripts/check-tests.php) | categorie, isolamento per entità, test per Action, factory, igiene | [testing](../rules/testing.md) |
| [`check-permissions.php`](scripts/check-permissions.php) | permessi usati ma non dichiarati, assegnazione al ruolo admin, seeder idempotenti | [policies](../rules/policies.md) |
| [`check-api.php`](scripts/check-api.php) | versione, autorizzazione, Resource, paginazione; con `--diff`, rotture del contratto | [rest-api](../rules/rest-api.md) |
| [`check-module-deps.php`](scripts/check-module-deps.php) | dipendenze dichiarate, cicli, uso non dichiarato | [moduli](../architecture/10-modular-system.md) |
| [`check-translations.php`](scripts/check-translations.php) | chiavi inesistenti, lingue disallineate, chiavi in italiano | [i18n](../rules/i18n.md) |
| [`check-external-assets.php`](scripts/check-external-assets.php) | asset da CDN, risorse su `http://` | [frontend](../rules/frontend.md) |
| [`check-baseline.php`](scripts/check-baseline.php) | che la baseline di PHPStan non cresca | [static analysis](../docs/04-quality/03-static-analysis.md) |

---

## Il contratto degli script

Tutti gli script rispettano lo stesso contratto. Non è una questione di eleganza: una pipeline che
non distingue «nessun problema» da «non ho potuto controllare» è peggio di nessuna pipeline.

| Uscita | Significato |
|---|---|
| `0` | nessuna violazione |
| `1` | violazioni trovate, elencate con file e riga |
| `2` | **lo script non ha potuto eseguire la verifica** |

Il terzo codice è quello che conta. Il caso peggiore non è il fallimento: è l'errore silenzioso che
passa per successo — la cartella che non esiste, il file di configurazione mancante, il comando che
non trova nulla da controllare e riporta zero violazioni.

Ogni script accetta come primo argomento il percorso del progetto, e usa la cartella corrente se non
lo riceve.

Il supporto comune è in [`scripts/_support.php`](scripts/_support.php).

---

## Uso in pipeline

```bash
# Sulla Factory
php tooling/scripts/check-docs.php --all
php tooling/scripts/check-secrets.php --history
php tooling/scripts/list-agents.php --check

# Su un progetto generato
php vendor/widstudios/factory-tooling/scripts/check-security.php .
php vendor/widstudios/factory-tooling/scripts/check-policies.php .
php vendor/widstudios/factory-tooling/scripts/check-schema.php .
php vendor/widstudios/factory-tooling/scripts/check-tests.php .
```

I gate girano **in parallelo**: un ritorno in dieci minuti viene letto, uno in quaranta viene
ignorato, e da quel momento la pipeline è un ostacolo invece che uno strumento.

---

## Aggiungere uno script

Il criterio è preciso, e non è «sarebbe utile controllare anche questo».

> Uno script nuovo si aggiunge quando lo **stesso difetto** è sfuggito alla revisione per la
> **seconda** volta.

Alla prima volta si segnala. Alla seconda si smette di segnalare e si scrive la verifica: se è
sfuggito due volte, sfuggirà una terza, e il tempo speso a cercarlo a mano supera già quello di
automatizzarlo.

Requisiti di uno script nuovo:

1. rispetta il contratto dei codici di uscita, **incluso il 2**;
2. indica **file e riga**: un rilievo senza posizione non è correggibile da chi lo riceve;
3. il messaggio dice **cosa fare**, non solo cosa è sbagliato;
4. gira in meno di dieci secondi su un progetto reale;
5. **nessun falso positivo su codice conforme** — è il requisito che decide se lo script
   sopravvivrà.

Il quinto merita una precisazione. Uno script che segnala codice corretto viene disattivato entro
una settimana, e con lui i controlli che funzionavano: la sua utilità netta è negativa. Meglio uno
script che trova l'ottanta per cento dei casi senza mai sbagliare, di uno che li trova tutti e
sbaglia una volta su dieci.

---

## Esempi

### Un messaggio utile e uno inutile

```
✗  app/Filament/Widgets/StatsWidget.php:34
       Cache usata in modo scorretto.

✓  app/Filament/Widgets/StatsWidget.php:34
       Chiave di cache scritta a mano: usare TenantCacheKey::for().
```

Il primo dice che c'è un problema. Il secondo dice quale, e come si risolve.

### Uscita 2 in azione

```bash
$ php tooling/scripts/check-policies.php /percorso/sbagliato
Nessuna cartella app/ sotto /percorso/sbagliato: questo script gira su un progetto generato.
$ echo $?
2
```

Senza il codice 2, questa esecuzione riporterebbe «nessuna violazione» e la pipeline sarebbe verde
su un controllo che non è mai stato eseguito.

### Falsi positivi eliminati, non tollerati

`check-docs.php --placeholders` cercava anche la formula «da completare». Compariva in prosa
legittima — *«un tenant incompleto da completare a mano»* — e il controllo segnalava frasi corrette.
La formula è stata tolta dai marcatori, e il codice in linea viene escluso dalla ricerca: un
documento che prescrive «nessun `TODO` senza riferimento tracciato» non contiene un marcatore:
contiene la regola che lo vieta.

---

## Best practice

- Includere le configurazioni, non copiarle.
- Far girare i gate in parallelo e tenere il ritorno sotto i dieci minuti.
- Aggiungere uno script solo alla seconda occorrenza dello stesso difetto.
- Eliminare i falsi positivi appena emergono: uno script che sbaglia viene disattivato.
- Tenere attivo in sviluppo ciò che costa poco — rilevamento N+1, Pint in salvataggio — così il
  difetto si vede quando viene scritto.
- Verificare che un controllo nuovo fallisca davvero su codice non conforme, prima di fidarsene.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Configurazioni copiate nei progetti | Divergono, nessuno sa quale sia quella buona | Includerle |
| Script senza codice di uscita 2 | «Verde» su un controllo mai eseguito | Contratto rispettato |
| Rilievo senza file e riga | Non correggibile da chi lo riceve | Posizione sempre |
| Messaggio che dice solo cosa è sbagliato | Chi lo legge non sa cosa fare | Indicare la correzione |
| Script con falsi positivi | Disattivato entro una settimana | Preferire copertura minore |
| Gate in sequenza | Ritorno lento, pipeline ignorata | In parallelo |
| Baseline che cresce | Analisi disattivata mantenendone l'aspetto | `check-baseline.php` |
| Rector in scrittura in pipeline | Modifiche che nessuno ha guardato | Solo `--dry-run` |
| Script mai provato su codice non conforme | Verde perché non trova nulla, non perché non c'è | Provarlo su un caso rotto |

---

## Checklist

- [ ] Le configurazioni sono incluse dai progetti, non copiate.
- [ ] Ogni script rispetta il contratto dei codici di uscita.
- [ ] Ogni rilievo indica file, riga e correzione.
- [ ] Nessuno script produce falsi positivi su codice conforme.
- [ ] I gate della pipeline girano in parallelo.
- [ ] Rector gira solo in `--dry-run`.
- [ ] La baseline non è cresciuta.
- [ ] Ogni script nuovo è stato provato su un caso non conforme.

---

## Riferimenti

- [Indice delle regole](../rules/README.md) · [Checklist](../checklists/README.md)
- [Analisi statica](../docs/04-quality/03-static-analysis.md)
- [Deployment](../deployment/README.md) · [Pipeline](../deployment/ci/README.md)
- [Metriche di qualità](../governance/quality-metrics.md)
