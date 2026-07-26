# Prompt — aggiungere una funzionalità

> Il percorso breve per una nuova funzionalità su un progetto già generato.

| | |
|---|---|
| **Versione** | 1.0.0 |
| **Agenti** | Backend → Filament/Frontend → Security → Testing |

---

## Indice

1. [Descrizione](#descrizione) 2. [Quando si usa](#quando-si-usa) 2. [Prerequisiti](#prerequisiti) 3. [Sequenza](#sequenza)
4. [Il prompt](#il-prompt) 5. [Definizione di «fatto»](#definizione-di-fatto) 6. [Esempi](#esempi)
7. [Best practice](#best-practice) 8. [Errori comuni](#errori-comuni) 9. [Checklist](#checklist)
10. [Riferimenti](#riferimenti)

---

## Descrizione

Aggiungere una funzionalità a un progetto che funziona è un problema diverso dal costruirlo. Non
c'è un brief da interpretare, c'è un sistema da rispettare: convenzioni già scelte, entità che
esistono, utenti che usano ciò che c'è oggi.

Il rischio non è sbagliare la funzionalità: è **rompere qualcos'altro** mentre la si aggiunge, e
scoprirlo da chi la usa. Per questo la prima precondizione di questo prompt è che la suite sia verde
prima di iniziare — altrimenti non si distingue ciò che si è rotto da ciò che era già rotto — e
l'ultima verifica è che sia ancora verde alla fine, senza che nessun test sia stato modificato per
farlo passare.

---

## Quando si usa

| Copre | Non copre |
|---|---|
| Nuova operazione su entità esistenti | nuovo ambito funzionale → `add-module.md` |
| Nuovo campo con la sua logica | correzione di un difetto → `fix-bug.md` |
| Nuovo report o esportazione | nuovo progetto → `loop crea` |
| Nuova integrazione con un sistema esterno | aggiornamento della Foundation → `upgrade-foundation.md` |

---

## Prerequisiti

- [ ] Il requisito è **pronto**: problema descritto, criteri di accettazione scritti, casi limite
      elencati, interlocutore disponibile.
- [ ] Il progetto è allineato: `composer qa` verde prima di iniziare.
- [ ] Esiste un branch dedicato.

---

## Sequenza

```
1. Backend Agent        dominio, Action, Query, DTO, eventi, test
2. Filament Agent       resource e azioni, se la funzionalità è amministrativa
   Frontend Agent       componenti, se la funzionalità è rivolta all'utente finale
3. Security Agent       permesso, Policy, test di autorizzazione e isolamento
4. Testing Agent        completamento della suite
5. Documentation Agent  README di modulo, API doc, manuale, changelog
```

La fase 3 non si salta: una funzionalità senza permesso è invisibile dopo il deploy, oppure aperta a
tutti.

---

## Il prompt

```markdown
Aggiungi una funzionalità al progetto «{{ NOME_PROGETTO }}», seguendo
`prompts/library/add-feature.md`.

## Requisito

{{ DESCRIZIONE_DEL_PROBLEMA }}

Criteri di accettazione:
{{ CRITERI }}

Casi limite noti:
{{ CASI_LIMITE }}

## Contesto

Progetto: {{ PERCORSO }}
Modulo interessato: {{ MODULO }}
Entità coinvolte: {{ ENTITÀ }}
Attori: {{ ATTORI }}

## Sequenza

Esegui in quest'ordine, verificando il gate di ciascuna fase prima di procedere:

### 1. Backend

Dall'interno verso l'esterno: enum e value object se servono nuovi concetti → eventuale evento →
DTO → Action → Query se serve una lettura → controller o punto di ingresso.

Le **regole di business** vanno nel dominio, non nell'Action se riguardano una sola entità.
Le **precondizioni** si verificano nell'Action, prima della transazione.

Se serve una modifica allo schema, **non farla**: segnala la migration necessaria, che va prodotta
con il pattern in tre rilasci se la tabella ha già dati in produzione.

### 2. Interfaccia

- Amministrativa → Filament: resource o azione che **delega** all'Action, con `->authorize()`.
- Utente finale → Livewire o Blade, con autorizzazione in ogni metodo pubblico.

Ogni etichetta da `__()`, traduzioni `it` ed `en`.

### 3. Sicurezza

- Nuovo permesso in `config/permissions.php`, nel formato `<risorsa>.<azione>`.
- Seeder aggiornato: il permesso va assegnato a `tenant_admin`, altrimenti la funzionalità è
  invisibile a tutti dopo il deploy.
- Metodo di Policy corrispondente, deny-by-default, che considera anche lo **stato**.
- Test di autorizzazione negata.
- Se la funzionalità introduce un'entità nuova: test di isolamento tenant.

### 4. Test

Per l'operazione: percorso corretto, violazione della regola di dominio, autorizzazione negata.
Effetti collaterali: evento emesso, job accodato, audit scritto.
Copertura al 100% sulle Action e sulle Policy nuove.

### 5. Documentazione

README del modulo, documentazione API se l'endpoint è nuovo, manuale utente se la funzionalità è
visibile, changelog in linguaggio dell'utente.

## Vincoli

Valgono tutte le regole della Factory, senza riduzioni: il percorso è più breve, gli standard sono
gli stessi.

In particolare:
- ogni chiave di cache tenant-scoped;
- ogni job con `TenantAware` e idempotente;
- eventi dopo il commit;
- nessuna logica nel controller o nella Resource;
- copertura al 100% su Action e Policy.

## Verifica prima di consegnare

    composer qa
    composer test:coverage
    php artisan test --env=testing-mysql

## Output

Gli artefatti, più un rapporto con: cosa è stato aggiunto, quali permessi, quali test, cosa serve
fare al deploy (seeder da eseguire, migration, comunicazione al cliente).
```

---

## Definizione di «fatto»

- [ ] Criteri di accettazione soddisfatti e verificati manualmente con due tenant.
- [ ] Regole di business nel dominio, non nei punti di ingresso.
- [ ] Permesso creato, seminato, assegnato al ruolo amministratore.
- [ ] Policy con test di autorizzazione negata.
- [ ] Tre test per l'operazione; copertura al 100% sulle nuove Action e Policy.
- [ ] Traduzioni `it` ed `en`.
- [ ] Documentazione e changelog aggiornati nello stesso insieme di modifiche.
- [ ] `composer qa` verde.

---

## Esempi

### Esempio 1 — funzionalità completa

Requisito: «il magazziniere deve poter mettere un lotto in quarantena, indicando il motivo».

```
Backend      BatchStatus::Quarantined + transizione; QuarantineBatchAction; BatchQuarantined
             (evento); QuarantineData (DTO); test
Filament     azione «Metti in quarantena» con form del motivo, delega all'Action, ->authorize()
Security     permesso batch.quarantine; Policy::quarantine (permesso + transizione ammessa);
             test di rifiuto
Testing      tre test dell'operazione; verifica dell'evento; audit
Docs         README del modulo, manuale operatore, changelog
```

### Esempio 2 — errore da evitare

La stessa funzionalità, senza la fase 3: il permesso non esiste, la Policy non c'è, l'azione Filament
non ha `->authorize()`.

Esito: l'azione è disponibile a **chiunque** possa vedere la pagina dei lotti, compresi utenti in
sola lettura.

---

## Best practice

- Verificare che il requisito sia «pronto» prima di iniziare: cinque minuti contro mezza giornata.
- Aggiungere il permesso al seeder nello stesso commit del codice.
- Provare la funzionalità con due tenant prima di aprire la pull request.
- Scrivere il test di autorizzazione negata per primo.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Fase di sicurezza saltata | Funzionalità aperta o invisibile | Sequenza completa |
| Permesso non seminato | Invisibile a tutti dopo il deploy | Seeder nello stesso commit |
| Logica nella Resource | Non vale per API e importazioni | Delegare all'Action |
| Schema modificato senza pattern in tre rilasci | Rollback impossibile | Migration additiva |
| Documentazione rimandata | Diverge | Stesso insieme di modifiche |
| Verifica con un solo tenant | Difetti di isolamento non rilevati | Due tenant |

---

## Checklist

- [ ] Requisito pronto e criteri di accettazione scritti.
- [ ] Sequenza completa eseguita, sicurezza compresa.
- [ ] Definizione di «fatto» soddisfatta.
- [ ] Note per il deploy riportate nel rapporto.

---

## Riferimenti

- [Libreria](README.md) · [Sviluppare una feature](../../docs/03-development/02-feature-development-guide.md)
- [Backend Agent](../../agents/06-backend-agent.md) · [Security Agent](../../agents/08-security-agent.md)
- [Definizione di fatto](../../checklists/definition-of-done.md)
