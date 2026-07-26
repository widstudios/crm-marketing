# Catalogo dei moduli

> Le schede dei moduli disponibili: che cosa forniscono, che cosa non fanno, come si adottano.

---

## Indice

1. [Descrizione](#descrizione) 2. [Le schede](#le-schede) 3. [Il grafo delle dipendenze](#il-grafo-delle-dipendenze)
4. [Come si legge una scheda](#come-si-legge-una-scheda) 5. [Esempi](#esempi)
6. [Best practice](#best-practice) 7. [Errori comuni](#errori-comuni) 8. [Checklist](#checklist)
9. [Riferimenti](#riferimenti)

---

## Descrizione

Ogni scheda descrive un modulo dal punto di vista di **chi deve decidere se adottarlo**: che cosa
otterrebbe, che cosa resterebbe da fare, che cosa costa in tabelle e complessità.

La sezione più importante di ogni scheda è **«che cosa non fa»**. È quella che evita i due errori
opposti: scegliere un modulo per un problema che non risolve, ed estenderlo fino a risolverlo male.

---

## Le schede

### Moduli di base — sempre attivi

| Scheda | Dipende da | In una riga |
|---|---|---|
| [tenancy](tenancy.md) | — | Tenant, domini, provisioning, ciclo di vita |
| [auth](auth.md) | tenancy | Guardie separate, ruoli, permessi, 2FA, token API |

### Moduli opzionali

| Scheda | Dipende da | In una riga |
|---|---|---|
| [audit](audit.md) | — | Registro immutabile delle mutazioni sensibili |
| [documents](documents.md) | — | Archiviazione, versioni, URL firmati, antivirus |
| [notifications](notifications.md) | — | Posta, banca dati, canali esterni, preferenze |
| [cms](cms.md) | documents | Pagine, sezioni, media, SEO, landing per tenant |
| [reporting](reporting.md) | documents | Report pianificati, esportazioni asincrone |

---

## Il grafo delle dipendenze

```
tenancy ──▶ auth

audit          (indipendente)

documents ──┬──▶ cms
            └──▶ reporting

notifications  (indipendente)
```

Il grafo è deliberatamente **piatto**: quattro moduli su sette non dipendono da nulla, e la
profondità massima è due. Un grafo profondo rende impossibile disattivare qualunque cosa, ed è il
modo in cui un sistema modulare torna a essere un monolite senza che nessuno lo decida.

`cms` e `reporting` dipendono da `documents` per la stessa ragione: entrambi producono file — media
e report — e archiviarli è un problema già risolto, con antivirus, versioni e URL firmati.

---

## Come si legge una scheda

| Se stai decidendo | Leggi |
|---|---|
| se adottare il modulo | Descrizione, **Che cosa non fa**, Dipendenze |
| come integrarlo | Che cosa fornisce, Integrazione |
| come attivarlo su un progetto esistente | Adozione |
| se estenderlo | Che cosa non fa, e poi il [blueprint](../_blueprint/README.md) |

---

## Esempi

### Una decisione di adozione

> *«Serve tracciare chi apre le schede dei pazienti.»*

`audit` fornisce il registro immutabile e la voce per accesso in lettura. Non fornisce
l'interfaccia di consultazione per l'auditor esterno, che resta da costruire — ed è scritto nella
sezione «che cosa non fa» della sua scheda.

Sapere in anticipo che quella parte manca è la differenza tra una stima corretta e una scoperta a
metà progetto.

---

## Best practice

- Leggere «che cosa non fa» prima di «che cosa fornisce»: cambia la decisione più spesso.
- Attivare solo i moduli che servono davvero: ogni modulo è tabelle, migration e superficie in più.
- Verificare le dipendenze prima di attivare: l'applicazione non parte se manca qualcosa.
- Se un modulo quasi risolve il problema, valutare un modulo nuovo invece di estendere quello.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Modulo scelto senza leggere i confini | Metà del lavoro scoperto a progetto avviato | «Che cosa non fa» |
| Moduli attivati «per sicurezza» | Tabelle e complessità che nessuno usa | Solo ciò che serve |
| Modulo esteso oltre il suo scopo | Diventa il posto dove finisce tutto | Modulo nuovo |
| Dipendenze non verificate | L'applicazione non parte dopo il deploy | Grafo delle dipendenze |

---

## Checklist

- [ ] Ho letto la sezione «che cosa non fa» del modulo che sto adottando.
- [ ] Le dipendenze del modulo sono attive.
- [ ] Ho eseguito le migration e il seeder dei permessi.
- [ ] Ho verificato che i permessi siano assegnati al ruolo amministratore.
- [ ] Attivo solo i moduli che servono davvero.

---

## Riferimenti

- [Catalogo](../README.md) · [Blueprint](../_blueprint/README.md)
- [Sistema modulare](../../architecture/10-modular-system.md)
- [Foundation — Moduli](../../foundation/docs/05-moduli.md)
