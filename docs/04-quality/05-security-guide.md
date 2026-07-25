# Guida alla sicurezza

> La superficie d'attacco di un gestionale multitenant e le contromisure standard che ogni
> progetto adotta senza doverle riprogettare.

---

## Indice

1. [Descrizione](#descrizione)
2. [Modello di minaccia](#modello-di-minaccia)
3. [Isolamento dei tenant](#isolamento-dei-tenant)
4. [Autenticazione](#autenticazione)
5. [Autorizzazione](#autorizzazione)
6. [Validazione dell'input](#validazione-dellinput)
7. [Dati sensibili](#dati-sensibili)
8. [File caricati](#file-caricati)
9. [Configurazione sicura](#configurazione-sicura)
10. [Tracciabilità](#tracciabilità)
11. [Esempi](#esempi)
12. [Best practice](#best-practice)
13. [Errori comuni](#errori-comuni)
14. [Checklist](#checklist)
15. [Riferimenti](#riferimenti)

---

## Descrizione

I nostri software gestiscono dati sanitari, fiscali e documentali di clienti diversi sulla stessa
infrastruttura. Il profilo di rischio non è quello di un sito vetrina: una singola falla di
isolamento può avere conseguenze legali e contrattuali non recuperabili.

Questa guida è **operativa**. Le regole verificabili stanno in [`rules/security.md`](../../rules/security.md).

---

## Modello di minaccia

| Minaccia | Gravità | Contromisura principale |
|---|---|---|
| Accesso ai dati di un altro tenant | **critica** | database separati, contesto derivato dal token |
| Escalation di privilegi | critica | policy deny-by-default, permessi granulari |
| Furto di credenziali | alta | password robuste, 2FA, limitazione dei tentativi |
| SQL injection | alta | query parametrizzate, mai concatenazione |
| XSS | alta | escape automatico di Blade, sanificazione dell'HTML |
| CSRF | media | token su ogni form, `SameSite` sui cookie |
| Esposizione di dati in risposta | alta | API Resource esplicite |
| File dannosi caricati | alta | validazione, storage fuori dalla web root |
| Segreti nel repository | alta | scansione automatica, variabili d'ambiente |
| Dipendenze vulnerabili | media | audit automatico, aggiornamenti entro 72 ore |
| Denial of service da un tenant | media | limitazione del traffico per tenant |
| Abuso da parte di un utente legittimo | media | audit log immutabile |

L'ultima riga è spesso trascurata: chi ha accesso legittimo è anche chi può fare più danni, e
l'unica contromisura è la tracciabilità.

---

## Isolamento dei tenant

Il fondamento è architetturale: **database fisicamente separati**. Non è una verifica applicativa
che si può dimenticare, è una proprietà della struttura.

Restano quattro punti dove l'isolamento va comunque presidiato:

| Punto | Rischio | Contromisura |
|---|---|---|
| Cache | chiave condivisa | prefisso tenant obbligatorio |
| Code | job eseguito nel tenant sbagliato | contesto serializzato e ripristinato |
| Storage | percorsi sovrapposti | disco per tenant |
| Log | dati di tenant diversi mescolati | `tenant` nel contesto di ogni riga |

Verifica automatica: test di isolamento per ogni entità, più test di architettura sull'uso delle
connessioni.

```php
arch('i model di dominio non usano la connessione landlord')
    ->expect('App\Domain')
    ->not->toUse('App\Models\Landlord');
```

---

## Autenticazione

| Aspetto | Regola |
|---|---|
| Password | minimo 12 caratteri, verifica contro elenchi di password compromesse |
| Hash | `bcrypt` o `argon2id`, mai algoritmi personalizzati |
| 2FA | obbligatoria per super admin e tenant admin |
| Tentativi | limitati per indirizzo e per account |
| Sessione | scadenza per inattività, rigenerazione dell'identificatore al login |
| Token API | Sanctum, con abilità e scadenza obbligatoria |
| Recupero password | token monouso, scadenza breve, nessuna informazione sull'esistenza dell'account |
| Guardie | separate tra landlord e tenant |

Il messaggio di errore del login non deve rivelare se l'indirizzo esiste: «credenziali non valide»
per entrambi i casi.

---

## Autorizzazione

**Deny by default**, senza eccezioni.

```php
// ✗ Permissivo: chi non rientra nei casi previsti può fare tutto
public function update(User $user, Supplier $supplier): bool
{
    if ($user->isGuest()) {
        return false;
    }

    return true;
}

// ✓ Restrittivo: serve un permesso esplicito, e lo stato deve consentirlo
public function update(User $user, Supplier $supplier): bool
{
    return $user->can('supplier.update')
        && $supplier->status !== SupplierStatus::Archived;
}
```

| Regola | Motivo |
|---|---|
| Ogni model ha la sua Policy | nessuna risorsa senza regole |
| Ogni endpoint autorizza esplicitamente | l'assenza di controllo non è un default sicuro |
| Ogni azione Filament ha `->authorize()` | non è ereditato |
| `Gate::before` solo per il super admin, con audit | è una scorciatoia potente |
| Permessi granulari, non ruoli nel codice | i ruoli cambiano, i permessi no |
| Test di autorizzazione negata per ogni operazione | il caso che si dimentica |

---

## Validazione dell'input

Due livelli, con scopi diversi:

1. **Form Request** — verifica la forma: tipi, lunghezze, formati, presenza.
2. **Dominio** — verifica le regole: unicità nel tenant, stato ammesso, coerenza.

```php
// Forma
'quantity' => ['required', 'numeric', 'min:0.001', 'max:999999'],

// Regola di dominio, nell'Action
throw_if($requested > $available, InsufficientStock::forBatch($batch->id));
```

Sempre a **lista bianca**: si dichiara ciò che è ammesso, non ciò che è vietato. Un elenco di
divieti è sempre incompleto.

| Input | Rischio | Contromisura |
|---|---|---|
| Parametri di ordinamento e filtro | injection, query costose | lista bianca dei campi |
| HTML da editor | XSS | sanificazione lato server |
| File caricati | esecuzione di codice | validazione, storage isolato |
| Redirect | open redirect | solo percorsi interni |
| Identificatori in URL | accesso non autorizzato | autorizzazione, non oscurità |

---

## Dati sensibili

| Categoria | Trattamento |
|---|---|
| Password | hash, mai reversibile |
| Token API | hash in archivio, mostrati una sola volta |
| Dati sanitari | cifratura a riposo, accesso tracciato |
| Dati fiscali | accesso tracciato, conservazione secondo norma |
| Dati personali | minimizzazione, cancellazione su richiesta |
| Segreti di integrazione | variabili d'ambiente o gestore di segreti |

**Mai nei log**: password, token, numeri di carta, dati sanitari, contenuto integrale di documenti.

```php
// ✗ Registra tutto ciò che l'utente ha inviato
Log::info('Richiesta ricevuta', $request->all());

// ✓ Solo ciò che serve alla diagnosi
Log::info('Movimento registrato', [
    'movement_id' => $movement->id,
    'tenant' => tenant()->id,
    'user_id' => $user->id,
]);
```

---

## File caricati

| Controllo | Come |
|---|---|
| Tipo | verifica del contenuto (MIME reale), non dell'estensione |
| Dimensione | limite per tipo di documento |
| Nome | rigenerato, mai quello fornito dall'utente |
| Posizione | fuori dalla web root, disco per tenant |
| Accesso | tramite controller che autorizza, mai URL diretta |
| Contenuto | scansione antivirus dove il dominio lo richiede |

```php
Route::get('/documents/{document}', function (Document $document) {
    Gate::authorize('view', $document);

    return Storage::disk('tenant')->download($document->path, $document->original_name);
})->middleware(['auth', 'tenant']);
```

Un file servito direttamente da un URL statico è accessibile a chiunque ne indovini il percorso,
anche di un altro tenant.

---

## Configurazione sicura

| Impostazione | Produzione |
|---|---|
| `APP_DEBUG` | `false` |
| `APP_ENV` | `production` |
| Telescope | disabilitato |
| Messaggi di errore | generici, dettagli solo nei log |
| HTTPS | obbligatorio, con HSTS |
| Cookie | `secure`, `httponly`, `samesite=lax` |
| Header | CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy |
| Elenco delle directory | disabilitato |
| Versioni esposte in header | rimosse |

`APP_DEBUG=true` in produzione espone configurazione, percorsi e talvolta credenziali nella pagina
di errore: è la singola configurazione errata più dannosa.

---

## Tracciabilità

| Registro | Contenuto | Conservazione |
|---|---|---|
| Audit log | modifiche ai dati sensibili: chi, cosa, prima, dopo, quando | secondo norma (spesso 10 anni) |
| Activity log | azioni degli utenti | 12-24 mesi |
| Log di accesso | login, logout, tentativi falliti | 12 mesi |
| Log applicativi | errori e diagnostica | 30-90 giorni |

L'audit log è **immutabile**: nessun percorso applicativo può modificarlo o cancellarlo, nemmeno
per un amministratore.

---

## Esempi

### Esempio 1 — la falla più comune

```php
// ✗ Il tenant arriva dal client
$tenant = Tenant::find($request->input('tenant_id'));
```

Chiunque può cambiare quel valore e accedere ai dati di un altro cliente. Il tenant si deriva
**sempre** dal dominio o dal token, mai dall'input.

### Esempio 2 — autorizzazione dimenticata su un'azione Filament

Un'azione «esporta tutti i movimenti» senza `->authorize()`: chiunque possa vedere la pagina può
esportare l'intero storico. Il test di autorizzazione negata l'avrebbe intercettata.

---

## Best practice

- Derivare il tenant dal contesto autenticato, mai dall'input.
- Deny by default in ogni Policy.
- Lista bianca per ogni input variabile.
- Nessun dato sensibile nei log.
- File serviti da un controller che autorizza.
- Segreti solo in variabili d'ambiente.
- Dipendenze aggiornate, patch di sicurezza entro 72 ore.
- Test di autorizzazione negata per ogni operazione.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Tenant dall'input dell'utente | Accesso ai dati altrui | Tenant dal contesto |
| Policy permissiva per default | Escalation di privilegi | Deny by default |
| Azione Filament senza autorizzazione | Operazione aperta a tutti | `->authorize()` |
| `Log::info($request->all())` | Dati personali e segreti nei log | Log selettivi |
| File in `public/` | Accessibili senza autorizzazione | Storage privato per tenant |
| `APP_DEBUG=true` in produzione | Esposizione di configurazione e credenziali | `false` |
| Chiave di cache senza tenant | Dati di un tenant serviti a un altro | Prefisso obbligatorio |
| Audit log modificabile | Tracciabilità inutile | Immutabilità garantita |

---

## Checklist

- [ ] Il tenant è derivato dal dominio o dal token, mai dall'input.
- [ ] Ogni model ha una Policy deny-by-default.
- [ ] Ogni endpoint e ogni azione Filament autorizzano esplicitamente.
- [ ] Test di autorizzazione negata per ogni operazione.
- [ ] Test di isolamento tenant per ogni entità.
- [ ] Nessun dato sensibile nei log.
- [ ] File serviti tramite controller autorizzato, storage per tenant.
- [ ] Configurazione di produzione verificata (debug, header, cookie, HTTPS).
- [ ] Audit log attivo e immutabile sulle entità sensibili.
- [ ] Nessun segreto nel repository.
- [ ] Dipendenze senza vulnerabilità note.

---

## Riferimenti

- [Regole di sicurezza](../../rules/security.md) · [Policies](../../rules/policies.md) · [Validazione](../../rules/validation.md)
- [Agente Security](../../agents/08-security-agent.md)
- [Autorizzazione](../../architecture/09-authorization-roles-permissions.md)
- [Audit e activity log](../../architecture/20-audit-activity-log.md)
- [Checklist di sicurezza](../../checklists/security-checklist.md)
