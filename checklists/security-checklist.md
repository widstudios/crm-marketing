# Checklist — Sicurezza

> Il gate con autorità di blocco: qui non si negozia con la scadenza. Una voce non soddisfatta
> ferma la consegna.

| | |
|---|---|
| **Fase** | 7 — Security |
| **Agente** | [Security Agent](../agents/08-security-agent.md) |
| **Natura** | gate bloccante, con **autorità di blocco** |

---

## Indice

1. [Descrizione](#descrizione) 2. [Verifiche](#verifiche) 3. [Comandi di verifica](#comandi-di-verifica)
4. [Esempi](#esempi) 5. [Best practice](#best-practice) 6. [Errori comuni](#errori-comuni)
7. [Checklist](#checklist) 8. [Riferimenti](#riferimenti)

---

## Descrizione

Questa checklist verifica le proprietà che, se violate, producono danni **non recuperabili**: una
fuga di dati tra clienti, un'escalation di privilegi, un segreto pubblicato.

È l'unica checklist della Factory il cui esito rosso **non può** essere superato da una decisione di
priorità: si corregge, oppure si dichiara una fermata. Vedi
[Security Agent § autorità di blocco](../agents/08-security-agent.md).

Ogni voce si verifica in modo oggettivo. Le voci si riportano **una per una**, con l'esito reale.

---

## Verifiche

### Isolamento dei tenant — i cinque punti

- [ ] Il tenant si deriva **sempre** da dominio o token, mai da input del client (R1).
- [ ] Nessuna query cross-tenant, in nessun punto del codice, nemmeno diagnostico (R2).
- [ ] Ogni chiave di cache passa da `TenantCacheKey::for()` (R3).
- [ ] Esiste un test che dimostra l'isolamento della cache tra due tenant reali.
- [ ] Ogni job usa il trait `TenantAware` (R4).
- [ ] Esiste un test che dimostra il ripristino del contesto tenant dentro un job.
- [ ] I file vivono su un disco **privato** per tenant (R5).
- [ ] Ogni comando Artisan che tocca dati di dominio gira via `tenants:artisan`.
- [ ] Gli aggregati verso il landlord contengono solo valori numerici, mai identificativi (R6).
- [ ] La risoluzione per header `X-Tenant` è rifiutata fuori da `local` e `testing` (R7),
      con un test che lo dimostra.

### Autenticazione

- [ ] Guardie separate per landlord e tenant, senza percorsi di passaggio (R8).
- [ ] Password: minimo 12 caratteri, verificate contro elenchi di password compromesse (R9).
- [ ] Secondo fattore obbligatorio per super admin e tenant admin; SMS non ammesso (R10).
- [ ] Limitazione dei tentativi attiva: 5 per account, 20 per IP, in 15 minuti (R11).
- [ ] I messaggi di login e recupero password non rivelano se l'indirizzo esiste (R12).
- [ ] I token API hanno scadenza (massimo 12 mesi), sono archiviati come hash, mostrati una sola
      volta e revocabili (R13).
- [ ] L'identificatore di sessione si rigenera al login e ad ogni cambio di privilegio (R14).
- [ ] L'accesso del personale ai dati di un tenant è autorizzato, a scadenza, tracciato e
      notificato al tenant admin (R15).

### Autorizzazione

- [ ] Ogni model di dominio ha una Policy registrata.
- [ ] Ogni Policy **nega** in assenza di permesso esplicito: nessun `return true` incondizionato (R16).
- [ ] Esiste un test di rifiuto per ogni Policy, non solo di successo.
- [ ] Ogni rotta, azione Filament e metodo pubblico Livewire autorizza esplicitamente (R17).
- [ ] `Gate::before` è usato solo per il super admin di piattaforma, con registrazione (R18).
- [ ] Le API verificano **sia** le abilità del token **sia** i permessi dell'utente (R19).
- [ ] Una risorsa di un altro tenant produce `404`, non `403` (R20), con test dedicato.
- [ ] Nessun controllo su nomi di ruolo al posto dei permessi.

### Input

- [ ] Filtri, ordinamenti, campi di ricerca e redirect sono a **lista bianca** (R21).
- [ ] Nessuna concatenazione di stringhe nelle query: solo parametri legati (R22).
- [ ] L'HTML proveniente da editor è sanificato **lato server** (R23).
- [ ] I redirect accettano solo percorsi interni o domini in lista bianca (R24).
- [ ] Ogni model dichiara `$fillable`; nessun `$guarded = []` (R25).
- [ ] Il tipo dei file caricati è verificato dal **contenuto**, non dall'estensione (R26).
- [ ] Dimensione massima e tipi ammessi dichiarati su ogni caricamento.

### Dati sensibili

- [ ] Nessun dato sensibile nei log: password, token, chiavi, dati sanitari, codici fiscali,
      contenuti di documenti, corpi di richiesta completi (R27).
- [ ] Le password sono conservate solo come hash (`bcrypt` costo 12 o `argon2id`) (R28).
- [ ] I dati sanitari e fiscali sono cifrati a riposo e il loro accesso è tracciato (R29).
- [ ] Nessun segreto nel repository, nemmeno di esempio realistico (R30).
- [ ] La scansione dei segreti in CI è verde, inclusa la storia dei commit.
- [ ] Si conserva solo ciò che serve, per il tempo previsto (R31).

### File

- [ ] Nessun file di cliente su disco pubblico (R32).
- [ ] L'accesso ai file passa da un controller che autorizza, o da un URL firmato a scadenza
      breve (R33).
- [ ] Il nome del file è rigenerato; l'originale resta come metadato (R34).
- [ ] La generazione di un URL firmato è registrata nell'activity log (R35).

### Configurazione

- [ ] `APP_DEBUG=false` in staging e produzione (R36).
- [ ] `APP_ENV=production`, Telescope disabilitato, elenco directory disattivato.
- [ ] HTTPS obbligatorio con HSTS; cookie `secure`, `httponly`, `samesite=lax`.
- [ ] Header presenti: CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy.
- [ ] CSP restrittiva, senza `unsafe-inline` sugli script (R37).
- [ ] Nessun `env()` fuori da `config/`.

### Audit

- [ ] Ogni mutazione su dati sensibili è registrata nell'audit del tenant.
- [ ] La voce di audit contiene chi, cosa, quando, da dove — e non il contenuto sensibile.
- [ ] L'audit è **immutabile**: nessun percorso di codice lo modifica o lo cancella.

### Dipendenze

- [ ] L'audit delle dipendenze è verde, senza vulnerabilità note di gravità alta (R39).
- [ ] Nessuna dipendenza fuori dall'elenco ammesso (R40).

---

## Comandi di verifica

```bash
composer audit                       # vulnerabilità note nelle dipendenze
npm audit --audit-level=high
php artisan test --testsuite=Tenant  # isolamento tra tenant
php artisan test --testsuite=Architecture
php tooling/scripts/check-security.php
php tooling/scripts/check-policies.php    # ogni model ha Policy, ogni Policy ha test di rifiuto
php tooling/scripts/check-secrets.php     # segreti nel working tree e nella storia
```

Verifica manuale obbligatoria, che nessuno script sostituisce:

1. leggere ogni Policy introdotta o modificata, riga per riga;
2. rileggere i cinque punti dell'isolamento sul codice reale, non sulla documentazione;
3. verificare la configurazione dell'ambiente di destinazione, non quella locale.

---

## Esempi

### Esito conforme

```markdown
### Quality gate
- checklists/security-checklist.md: N/N soddisfatte.
- Voci non soddisfatte: nessuna.
- Verifica manuale delle Policy: 6 Policy lette, tutte deny by default.
```

### Esito con blocco

```markdown
### Quality gate — BLOCCO
- checklists/security-checklist.md: N-2/N soddisfatte.

Voci non soddisfatte:
- Chiave di cache tenant-scoped: `DashboardStats` usa `Cache::remember('stats', …)`,
  senza prefisso di tenant. Un tenant legge i dati di un altro.
  Correzione: `TenantCacheKey::for('stats')`.
- Policy deny by default: `DocumentPolicy::view()` ritorna `true` incondizionatamente.
  Correzione: verificare il permesso `document.view` e la proprietà della risorsa.

Esercito l'autorità di blocco: la consegna è sospesa fino alla correzione di entrambe.
```

---

## Best practice

- Verificare sul **codice**, mai sul report della fase precedente.
- Partire dai cinque punti dell'isolamento: sono i difetti più costosi e i meno visibili.
- Scrivere il test di rifiuto prima di dichiarare verificata una Policy.
- Considerare `403` al posto di `404` un difetto di sicurezza, non un dettaglio.
- Trattare ogni sospetto di fuga tra tenant come incidente P1 fino a prova contraria.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Isolamento verificato sulla documentazione | Il difetto è nel codice, non nel documento | Leggere il codice |
| Chiave di cache senza prefisso di tenant | Un tenant legge i dati di un altro | `TenantCacheKey::for()` |
| Job senza `TenantAware` | Scrittura nel database sbagliato | Trait obbligatorio |
| Policy con `return true` | Escalation di privilegi | Deny by default |
| Solo test di autorizzazione riuscita | Il rifiuto non è mai stato provato | Test di rifiuto |
| `403` per risorse di altri tenant | Conferma l'esistenza della risorsa | `404` |
| Segreto rimosso solo dal working tree | Resta nella storia dei commit | Scansione della storia, rotazione |
| Tipo del file dedotto dall'estensione | File dannosi caricati | Verifica del contenuto |
| Gate rosso superato «per la scadenza» | Danno non recuperabile in produzione | Autorità di blocco |

---

## Checklist

- [ ] Ho verificato ogni voce sul codice reale, non sui report.
- [ ] Ho eseguito tutti i comandi indicati e riportato l'esito.
- [ ] Ho letto una per una le Policy introdotte o modificate.
- [ ] Ho ripercorso i cinque punti dell'isolamento.
- [ ] Ho dichiarato le voci non applicabili con la motivazione.
- [ ] Se il gate è rosso, ho esercitato l'autorità di blocco invece di segnalare e proseguire.

---

## Riferimenti

- [Fase 7](../workflows/08-phase-security.md) · [Security Agent](../agents/08-security-agent.md)
- [Regole di sicurezza](../rules/security.md) · [Policies](../rules/policies.md) · [Validazione](../rules/validation.md)
- [Multitenancy](../architecture/03-multitenancy-overview.md) · [Autorizzazione](../architecture/09-authorization-roles-permissions.md)
- [Audit e activity log](../architecture/20-audit-activity-log.md)
- [Guida alla sicurezza](../docs/04-quality/05-security-guide.md)
