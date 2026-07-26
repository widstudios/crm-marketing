# Regole — Sicurezza

> Le regole che proteggono da ciò che non è recuperabile: fughe di dati tra clienti, escalation di
> privilegi, esposizione di segreti.

---

## Indice

1. [Descrizione](#descrizione)
2. [Isolamento dei tenant](#isolamento-dei-tenant)
3. [Autenticazione](#autenticazione)
4. [Autorizzazione](#autorizzazione)
5. [Input](#input)
6. [Dati sensibili](#dati-sensibili)
7. [File](#file)
8. [Configurazione](#configurazione)
9. [Dipendenze](#dipendenze)
10. [Esempi](#esempi)
11. [Best practice](#best-practice)
12. [Errori comuni](#errori-comuni)
13. [Checklist](#checklist)
14. [Riferimenti](#riferimenti)

---

## Descrizione

Nei domini in cui operiamo — sanitario, fiscale, documentale — una fuga di dati tra clienti ha
conseguenze legali e contrattuali non recuperabili. Le regole di questo documento hanno priorità su
qualunque esigenza di prestazioni, semplicità o scadenza.

---

## Isolamento dei tenant

**R1.** Il tenant si deriva **sempre** da dominio o token, mai da input del client.
*Motivo:* un parametro è modificabile da chi invia la richiesta.
*Verifica:* revisione dei resolver, test. *Livello: assoluto.*

**R2.** Nessuna query cross-tenant, in nessuna circostanza, nemmeno per diagnostica.
*Verifica:* test di architettura, revisione. *Livello: assoluto.*

**R3.** Ogni chiave di cache è tenant-scoped, tramite l'helper della Foundation.
*Verifica:* test di isolamento della cache. *Livello: assoluto.*

**R4.** Ogni job ripristina il contesto tenant.
*Verifica:* test di architettura. *Livello: assoluto.*

**R5.** I file vivono su disco per tenant, privato.
*Verifica:* configurazione, test. *Livello: assoluto.*

**R6.** Gli aggregati verso il landlord contengono solo valori numerici, mai identificativi.
*Verifica:* revisione.

**R7.** La risoluzione per header (`X-Tenant`) è attiva solo in `local` e `testing`, con verifica
dell'ambiente nel codice.
*Verifica:* test che ne verifica il rifiuto in altri ambienti.

---

## Autenticazione

**R8.** Guardie separate per landlord e tenant, senza percorsi di passaggio.
*Verifica:* configurazione, test.

**R9.** Password: minimo 12 caratteri, verificate contro elenchi di password compromesse, nessuna
composizione forzata.
*Motivo:* la composizione forzata produce password prevedibili.
*Verifica:* regole di validazione.

**R10.** Secondo fattore obbligatorio per super admin e tenant admin. SMS non ammesso come secondo
fattore.
*Verifica:* revisione.

**R11.** Limitazione dei tentativi: 5 per account, 20 per indirizzo IP, in 15 minuti.
*Verifica:* configurazione, test.

**R12.** Il messaggio di errore del login e del recupero password non rivela se l'indirizzo esiste.
*Verifica:* test.

**R13.** Token API: scadenza obbligatoria (massimo 12 mesi), archiviati come hash, mostrati una
sola volta, revocabili.
*Verifica:* revisione, test.

**R14.** L'identificatore di sessione si rigenera al login e ad ogni cambio di privilegio.
*Verifica:* test.

**R15.** L'accesso del personale ai dati di un tenant è autorizzato, a scadenza, tracciato
nell'audit del tenant e notificato al tenant admin.
*Verifica:* revisione, test. *Livello: vincolante.*

---

## Autorizzazione

**R16.** Deny by default in ogni Policy: nessun `return true` senza permesso esplicito.
*Verifica:* test di rifiuto. *Livello: assoluto.*

**R17.** Ogni endpoint, ogni azione Filament e ogni metodo pubblico Livewire autorizzano
esplicitamente.
*Verifica:* script di verifica, revisione. *Livello: vincolante.*

**R18.** `Gate::before` solo per il super admin di piattaforma, con registrazione nell'audit.
*Verifica:* revisione.

**R19.** Le API verificano sia le abilità del token sia i permessi dell'utente.
*Motivo:* i permessi possono essere revocati dopo l'emissione del token.
*Verifica:* revisione, test.

**R20.** Una risorsa non visibile al tenant corrente produce `404`, non `403`.
*Motivo:* `403` confermerebbe l'esistenza della risorsa. *Verifica:* test.

---

## Input

**R21.** Tutto l'input variabile è a **lista bianca**: filtri, ordinamenti, campi di ricerca,
percorsi di redirect.
*Motivo:* un elenco di divieti è sempre incompleto. *Verifica:* revisione.

**R22.** Nessuna concatenazione di stringhe nelle query: solo parametri legati.
*Verifica:* ricerca in CI. *Livello: assoluto.*

**R23.** L'HTML proveniente da editor è sanificato **lato server**, con una lista bianca di tag e
attributi.
*Verifica:* revisione, test.

**R24.** I redirect accettano solo percorsi interni o una lista bianca di domini.
*Verifica:* revisione.

**R25.** `$fillable` esplicito su ogni model; mai `$guarded = []`.
*Verifica:* ricerca in CI. *Livello: vincolante.*

**R26.** Il tipo dei file caricati si verifica dal **contenuto**, non dall'estensione.
*Verifica:* revisione, test.

---

## Dati sensibili

**R27.** Mai nei log: password, token, chiavi, numeri di carta, dati sanitari, codici fiscali,
contenuti di documenti, corpi di richiesta completi.
*Verifica:* revisione, scansione dei log in staging. *Livello: assoluto.*

**R28.** Le password si conservano solo come hash (`bcrypt` costo 12 o `argon2id`).
*Verifica:* revisione.

**R29.** I dati sanitari e fiscali sono cifrati a riposo e il loro accesso è tracciato.
*Verifica:* revisione, audit.

**R30.** Nessun segreto nel repository, nemmeno di esempio realistico: placeholder evidenti.
*Verifica:* scansione dei segreti in CI. *Livello: assoluto.*

**R31.** Minimizzazione: si registra e si conserva solo ciò che serve, per il tempo previsto.
*Verifica:* revisione della politica di conservazione.

---

## File

**R32.** Nessun file di cliente su disco pubblico.
*Verifica:* revisione, test. *Livello: assoluto.*

**R33.** L'accesso ai file passa da un controller che autorizza, o da un URL firmato con scadenza
breve.
*Verifica:* revisione.

**R34.** Il nome del file è rigenerato; l'originale si conserva come metadato.
*Verifica:* revisione.

**R35.** La generazione di un URL firmato è registrata nell'activity log.
*Motivo:* è una condivisione di dati verso l'esterno. *Verifica:* revisione.

---

## Configurazione

| Impostazione | Produzione |
|---|---|
| `APP_DEBUG` | **`false`** |
| `APP_ENV` | `production` |
| Telescope | disabilitato |
| HTTPS | obbligatorio, con HSTS |
| Cookie | `secure`, `httponly`, `samesite=lax` |
| Header | CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy |
| Elenco directory | disabilitato |
| Versioni negli header | rimosse |

**R36.** `APP_DEBUG=false` in staging e produzione.
*Motivo:* con `true` la pagina di errore espone configurazione, percorsi e talvolta credenziali.
*Verifica:* verifica dell'ambiente in CI. *Livello: assoluto.*

**R37.** CSP restrittiva, senza `unsafe-inline` sugli script.
*Verifica:* verifica degli header in CI.

---

## Dipendenze

**R38.** Le patch di sicurezza si applicano entro **72 ore**.
*Verifica:* processo di aggiornamento.

**R39.** L'audit delle dipendenze gira in pipeline e blocca in caso di vulnerabilità note di
gravità alta.
*Verifica:* pipeline.

**R40.** Nessuna dipendenza non dichiarata nell'elenco ammesso.
*Verifica:* verifica in CI.

---

## Esempi

### Esempio 1 — la falla più comune

```php
// ✗ R1: chiunque può cambiare il valore e accedere ai dati di un altro cliente
$tenant = Tenant::find($request->input('tenant_id'));

// ✓ Il tenant deriva dal contesto autenticato
$tenant = TenantContext::currentOrFail();
```

### Esempio 2 — log conforme e non conforme

```php
// ✗ R27: registra dati personali e potenzialmente segreti
Log::info('Richiesta ricevuta', $request->all());

// ✓ Solo ciò che serve alla diagnosi
Log::info('Movimento registrato', [
    'movement_id' => $movement->id,
    'batch_id' => $movement->batch_id,
]);
```

---

## Best practice

- Trattare ogni sospetto di fuga tra tenant come incidente P1, fino a prova contraria.
- Lista bianca sempre, anche quando l'elenco dei valori ammessi è lungo.
- Scrivere il test di autorizzazione negata prima di quello del percorso corretto.
- Verificare la configurazione di produzione con un controllo automatico, non a memoria.
- Rivedere i cinque punti dell'isolamento prima di ogni rilascio maggiore.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Tenant da input | Accesso ai dati altrui | Dal contesto autenticato |
| Chiave di cache senza prefisso | Un tenant legge i dati di un altro | Helper della Foundation |
| Policy permissiva | Escalation di privilegi | Deny by default |
| Azione Filament senza autorizzazione | Operazione aperta | `->authorize()` |
| `Log::info($request->all())` | Dati personali e segreti nei log | Log selettivi |
| File su disco pubblico | Accessibili senza autorizzazione | Disco privato per tenant |
| `APP_DEBUG=true` in produzione | Esposizione di configurazione | `false` |
| `$guarded = []` | Assegnazione massiva di colonne di stato | `$fillable` |
| Tipo del file dall'estensione | File dannosi caricati | Verifica del contenuto |
| `403` per risorse di altri tenant | Conferma l'esistenza | `404` |

---

## Checklist

- [ ] Il tenant deriva da dominio o token.
- [ ] Nessuna query cross-tenant.
- [ ] Chiavi di cache tenant-scoped; job con contesto ripristinato.
- [ ] File su disco privato per tenant, nomi rigenerati.
- [ ] Guardie separate; 2FA per gli amministratori.
- [ ] Token con scadenza, archiviati come hash.
- [ ] Deny by default in ogni Policy.
- [ ] Autorizzazione esplicita in ogni punto di ingresso.
- [ ] Lista bianca su tutti gli input variabili.
- [ ] Nessun dato sensibile nei log.
- [ ] Nessun segreto nel repository.
- [ ] `APP_DEBUG=false`, header di sicurezza attivi, Telescope disabilitato.
- [ ] Audit delle dipendenze verde.

---

## Riferimenti

- [Guida alla sicurezza](../docs/04-quality/05-security-guide.md)
- [Policies](policies.md) · [Validazione](validation.md) · [Logging](logging.md)
- [Autenticazione](../architecture/08-authentication.md) · [Autorizzazione](../architecture/09-authorization-roles-permissions.md)
- [Checklist di sicurezza](../checklists/security-checklist.md)
