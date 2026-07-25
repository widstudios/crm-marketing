# Autenticazione

> Come si stabilisce l'identità di chi accede, con guardie separate tra piattaforma e clienti.

---

## Indice

1. [Descrizione](#descrizione)
2. [Le due guardie](#le-due-guardie)
3. [Sessioni web](#sessioni-web)
4. [Token API](#token-api)
5. [Secondo fattore](#secondo-fattore)
6. [Password e credenziali](#password-e-credenziali)
7. [Accesso del personale ai tenant](#accesso-del-personale-ai-tenant)
8. [Utente su più tenant](#utente-su-più-tenant)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

L'autenticazione risponde alla domanda *chi sei*; l'autorizzazione a *cosa puoi fare*. Tenerle
separate evita il modello confuso in cui l'appartenenza a un gruppo implica implicitamente dei
permessi.

La specificità multitenant: esistono **due popolazioni di utenti** che non si mescolano mai — il
personale WidStudios (landlord) e gli utenti dei clienti (tenant).

---

## Le due guardie

| | `landlord` | `tenant` |
|---|---|---|
| Utenti | personale WidStudios | utenti del cliente |
| Tabella | `platform_users` nel landlord | `users` nel database del tenant |
| Pannelli | Super Admin | Tenant Admin, portale |
| Sessione | cookie sul dominio centrale | cookie sul dominio del tenant |
| Secondo fattore | **obbligatorio** | obbligatorio per gli amministratori |
| Registrazione autonoma | no | secondo configurazione |

```php
// config/auth.php
'guards' => [
    'landlord' => ['driver' => 'session', 'provider' => 'platform_users'],
    'tenant' => ['driver' => 'session', 'provider' => 'tenant_users'],
    'api' => ['driver' => 'sanctum', 'provider' => 'tenant_users'],
],
```

Un utente autenticato su una guardia **non** è autenticato sull'altra. Non esiste un percorso che
trasformi un accesso di piattaforma in un accesso a un tenant senza passare dalla procedura
tracciata.

---

## Sessioni web

| Aspetto | Regola |
|---|---|
| Archivio | Redis, mai file (più container) |
| Cookie | `secure`, `httponly`, `samesite=lax` |
| Nome del cookie | prefissato dallo slug del tenant |
| Scadenza per inattività | 120 minuti (configurabile) |
| Scadenza assoluta | 12 ore |
| Rigenerazione dell'identificatore | ad ogni login e cambio di privilegio |
| Invalidazione | al cambio password, su tutti i dispositivi |
| Sessioni simultanee | consentite, elencabili e revocabili dall'utente |

Il nome del cookie prefissato per tenant evita che, su un dominio condiviso, la sessione di un
tenant sovrascriva quella di un altro nello stesso browser.

---

## Token API

Laravel Sanctum, con vincoli aggiuntivi.

| Aspetto | Regola |
|---|---|
| Ambito | il token appartiene a un utente **di un tenant** |
| Abilità | rispecchiano i permessi, non li sostituiscono |
| Scadenza | **obbligatoria**, massimo 12 mesi |
| Archivio | solo l'hash; il valore si mostra una volta sola |
| Revoca | dal pannello del tenant, con effetto immediato |
| Rotazione | supportata, con sovrapposizione configurabile |
| Ultimo utilizzo | registrato, per individuare i token inattivi |

```php
$token = $user->createToken(
    name: 'Integrazione ERP',
    abilities: ['supplier.view', 'movement.create'],
    expiresAt: now()->addYear(),
);
```

Il token porta con sé il tenant: è ciò che permette al `TokenResolver` di determinare il contesto
senza alcun parametro nella richiesta.

Verifica delle abilità **e** dei permessi:

```php
$request->user()->tokenCan('movement.create')     // l'integrazione è autorizzata
    && $request->user()->can('movement.create');  // l'utente lo è ancora
```

Entrambi i controlli servono: le abilità limitano cosa può fare quel token; i permessi riflettono
lo stato attuale dell'utente, che può essere cambiato dopo l'emissione.

---

## Secondo fattore

| Ruolo | Obbligatorio |
|---|---|
| Super Admin, personale di piattaforma | **sì** |
| Tenant Admin | **sì** |
| Utenti operativi | secondo configurazione del tenant |

Metodo standard: TOTP con applicazione di autenticazione. Codici di recupero monouso generati
alla configurazione, mostrati una sola volta.

L'SMS non è ammesso come secondo fattore: è vulnerabile al trasferimento di numero e la sua
sicurezza dipende da un operatore terzo.

---

## Password e credenziali

| Aspetto | Regola |
|---|---|
| Lunghezza minima | 12 caratteri |
| Verifica contro elenchi compromessi | obbligatoria |
| Composizione forzata | **no** (produce password prevedibili) |
| Hash | `bcrypt` costo 12, o `argon2id` |
| Scadenza periodica | no, salvo requisito contrattuale |
| Tentativi | 5 per account, 20 per indirizzo IP, in 15 minuti |
| Recupero | token monouso, scadenza 60 minuti |
| Messaggio di recupero | identico per indirizzo esistente e inesistente |
| Notifica di cambio | sempre, all'indirizzo precedente |

La composizione forzata (maiuscole, numeri, simboli) è controproducente: produce `Password1!` al
posto di una passphrase lunga e memorabile. La lunghezza e il controllo contro gli elenchi di
password compromesse sono più efficaci.

---

## Accesso del personale ai tenant

Un operatore di assistenza può avere bisogno di accedere ai dati di un cliente. È un'operazione
**eccezionale, autorizzata e tracciata**.

```
1. richiesta con motivazione obbligatoria
2. autorizzazione da parte di un super admin
3. sessione temporanea, scadenza massima 2 ore
4. banner sempre visibile: «accesso di assistenza, sessione tracciata»
5. ogni azione registrata nell'audit log **del tenant**
6. notifica al tenant admin del cliente
7. termine automatico alla scadenza
```

La notifica al cliente non è facoltativa: è ciò che rende l'accesso verificabile da chi possiede i
dati. Un accesso di assistenza invisibile al cliente sarebbe indistinguibile da un abuso.

---

## Utente su più tenant

Un consulente può lavorare per più clienti. Il modello resta rigoroso:

| Regola | Motivo |
|---|---|
| Un account **per tenant**, non un account condiviso | gli utenti sono dati del cliente |
| Nessuna sessione condivisa tra tenant | isolamento |
| Il cambio di tenant richiede un nuovo accesso | il contesto è immutabile nella richiesta |
| L'indirizzo email può ripetersi tra tenant | sono database diversi |

Un account unico che «vede più tenant» introdurrebbe un percorso di attraversamento tra contesti,
che è esattamente ciò che l'architettura esclude.

---

## Esempi

### Esempio 1 — accesso di assistenza

```
14:02  operatore richiede accesso a `acme`, motivo: «ticket #4821, movimenti duplicati»
14:03  super admin autorizza, durata 2 ore
14:03  sessione creata; audit del tenant: `support_access_granted`
14:03  notifica al tenant admin di ACME
14:05  operatore consulta i movimenti; ogni lettura registrata
14:40  operatore chiude; audit: `support_access_ended`
```

### Esempio 2 — token compromesso

Un token di integrazione compare in un repository pubblico.

```bash
php artisan tenant:artisan "sanctum:revoke --token-id=1842" --tenant=acme
```

Effetto immediato. Il registro dell'ultimo utilizzo permette di verificare se e quando è stato
usato dopo l'esposizione.

---

## Best practice

- Guardie separate, senza percorsi di passaggio.
- Sessioni su Redis, cookie prefissati per tenant.
- Token con scadenza obbligatoria e abilità limitate.
- Verificare sia le abilità del token sia i permessi dell'utente.
- Secondo fattore obbligatorio per gli amministratori.
- Password lunghe invece che complesse.
- Accesso del personale tracciato e notificato al cliente.
- Un account per tenant, mai account che attraversano i contesti.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Guardia unica per landlord e tenant | Confusione tra le popolazioni, rischio di escalation | Guardie separate |
| Sessioni su file | Perdita di sessione con più container | Redis |
| Token senza scadenza | Credenziale valida per sempre | Scadenza obbligatoria |
| Solo abilità del token verificate | Permessi revocati ma token ancora efficace | Verificare entrambi |
| Composizione della password forzata | Password prevedibili | Lunghezza + elenchi compromessi |
| Messaggio di recupero differenziato | Rivela quali indirizzi esistono | Messaggio identico |
| Accesso di assistenza non notificato | Indistinguibile da un abuso | Notifica obbligatoria |
| Account unico su più tenant | Percorso di attraversamento tra contesti | Un account per tenant |

---

## Checklist

- [ ] Guardie `landlord` e `tenant` separate.
- [ ] Sessioni su Redis, cookie prefissati e sicuri.
- [ ] Token con scadenza, abilità e registro dell'ultimo utilizzo.
- [ ] Abilità e permessi verificati entrambi.
- [ ] Secondo fattore obbligatorio per gli amministratori.
- [ ] Password ≥ 12 caratteri, verificate contro elenchi compromessi.
- [ ] Limitazione dei tentativi per account e per indirizzo.
- [ ] Accesso di assistenza autorizzato, tracciato, notificato, a scadenza.
- [ ] Nessun account attraversa i tenant.

---

## Riferimenti

- [Autorizzazione, ruoli e permessi](09-authorization-roles-permissions.md)
- [Risoluzione del tenant](06-tenant-resolution.md)
- [Regole di sicurezza](../rules/security.md)
- [Guida alla sicurezza](../docs/04-quality/05-security-guide.md)
- [Modulo auth](../modules/catalog/auth.md)
