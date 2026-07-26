# CMS e landing page

> La presenza pubblica del prodotto: pagine di contenuto, SEO, moduli di contatto e registrazione
> di nuovi tenant.

---

## Indice

1. [Descrizione](#descrizione)
2. [Cosa vive nel contesto pubblico](#cosa-vive-nel-contesto-pubblico)
3. [Modello dei contenuti](#modello-dei-contenuti)
4. [Blocchi di contenuto](#blocchi-di-contenuto)
5. [SEO](#seo)
6. [Prestazioni](#prestazioni)
7. [Moduli e registrazione](#moduli-e-registrazione)
8. [Sicurezza delle pagine pubbliche](#sicurezza-delle-pagine-pubbliche)
9. [Esempi](#esempi)
10. [Best practice](#best-practice)
11. [Errori comuni](#errori-comuni)
12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Ogni prodotto ha una presenza pubblica: la pagina che lo presenta, la documentazione, i contatti,
e talvolta la registrazione autonoma di nuovi clienti.

Questa parte vive nel contesto **landlord**: non appartiene ad alcun tenant, ed è l'unica
superficie raggiungibile da chiunque. Per questo va trattata come la più esposta.

---

## Cosa vive nel contesto pubblico

| Contenuto | Nel landlord | Note |
|---|---|---|
| Landing page | sì | presentazione del prodotto |
| Pagine di contenuto | sì | chi siamo, funzionalità, prezzi |
| Documentazione pubblica | sì | guide, API |
| Blog / novità | sì | facoltativo |
| Moduli di contatto | sì | con protezione anti-abuso |
| Registrazione di un tenant | sì | se il prodotto la prevede |
| Pagina di accesso | sì, con reindirizzamento | l'accesso avviene sul dominio del tenant |
| Stato del servizio | sì | pagina indipendente, meglio se esterna |

Nulla di ciò che riguarda i dati dei clienti compare qui.

---

## Modello dei contenuti

```
┌──────────────┐        ┌─────────────────┐
│  cms_pages   │───1:N──▶│  cms_blocks    │
├──────────────┤        ├─────────────────┤
│ slug         │        │ page_id         │
│ locale       │        │ type            │
│ title        │        │ position        │
│ meta_title   │        │ content (json)  │
│ meta_desc    │        │ visible         │
│ published_at │        └─────────────────┘
│ layout       │
└──────────────┘        ┌─────────────────┐
                        │  cms_menus      │
┌──────────────┐        ├─────────────────┤
│  cms_media   │        │ location        │
├──────────────┤        │ items (json)    │
│ path         │        │ locale          │
│ alt_text     │        └─────────────────┘
│ locale       │
└──────────────┘
```

| Scelta | Motivo |
|---|---|
| Contenuti in database, non in file | modificabili senza rilascio |
| `locale` su ogni entità | multilingua fin dall'inizio |
| `published_at` invece di un booleano | permette la pubblicazione programmata |
| Blocchi invece di HTML libero | struttura verificabile, meno rischi |

---

## Blocchi di contenuto

Le pagine si compongono di blocchi tipizzati, non di HTML arbitrario.

| Tipo | Contenuto |
|---|---|
| `hero` | titolo, sottotitolo, immagine, invito all'azione |
| `text` | testo formattato, sanificato |
| `features` | elenco di caratteristiche con icone |
| `pricing` | piani con caratteristiche e prezzi |
| `faq` | domande e risposte |
| `testimonials` | citazioni con attribuzione |
| `cta` | invito all'azione |
| `contact_form` | modulo di contatto |

I blocchi tipizzati offrono tre vantaggi rispetto all'HTML libero: struttura coerente, nessun
rischio di XSS da contenuto redazionale, e possibilità di cambiare la resa grafica senza toccare
i contenuti.

---

## SEO

| Elemento | Regola |
|---|---|
| `<title>` | unico per pagina, 50-60 caratteri |
| Meta description | 150-160 caratteri, per pagina |
| URL | `kebab-case`, parlanti, stabili |
| Canonical | sempre presente |
| `hreflang` | per le pagine multilingua |
| Open Graph e Twitter Card | per la condivisione |
| Dati strutturati (JSON-LD) | organizzazione, prodotto, FAQ |
| Sitemap XML | generata, aggiornata automaticamente |
| `robots.txt` | esclude i contesti tenant |
| Redirect | `301` per gli URL cambiati, mai `404` |

`robots.txt` deve escludere esplicitamente i domini dei tenant: le interfacce amministrative dei
clienti non vanno indicizzate, e in alcuni domini sarebbe anche un problema di riservatezza.

---

## Prestazioni

Le pagine pubbliche sono la prima impressione e vanno servite in modo aggressivo.

| Tecnica | Effetto |
|---|---|
| Cache HTTP con `Cache-Control` | il contenuto pubblico si serve dalla cache del browser |
| Cache applicativa dei contenuti | evita query ad ogni visita |
| Generazione statica delle pagine invariate | risposta immediata |
| Immagini in formati moderni, dimensionate | peso ridotto |
| Caricamento differito sotto la piega | LCP migliore |
| CSS critico in linea | riduce il tempo di primo rendering |
| Nessun asset esterno | nessuna dipendenza da terzi |

Obiettivi: LCP < 2,5 s, CLS < 0,1, peso JS iniziale < 100 KB compressi.

L'invalidazione della cache avviene alla pubblicazione di un contenuto, non a scadenza: un
redattore che pubblica una correzione deve vederla subito.

---

## Moduli e registrazione

### Modulo di contatto

| Protezione | Nota |
|---|---|
| Limitazione del traffico | per indirizzo IP |
| Campo trappola | invisibile all'utente, compilato dai bot |
| Tempo minimo di compilazione | un invio in due secondi non è umano |
| Verifica anti-abuso | solo se le precedenti non bastano |
| Validazione lato server | sempre |
| Registrazione degli invii | per diagnosi e abusi |

La verifica anti-abuso interattiva (captcha) si aggiunge **solo se serve**: peggiora
l'accessibilità e l'esperienza, e le prime tre protezioni fermano la maggior parte del traffico
automatico.

### Registrazione di un tenant

```
modulo compilato
    │
    ▼ verifica dell'indirizzo email (obbligatoria)
    │
    ▼ creazione del record tenant, stato `provisioning`
    │
    ▼ provisioning in coda (database, migration, seeder, amministratore)
    │
    ▼ invito all'amministratore
    │
    ▼ stato `active`, accesso sul dominio del tenant
```

La verifica dell'indirizzo precede il provisioning: creare un database per ogni modulo compilato
sarebbe un vettore di esaurimento delle risorse.

---

## Sicurezza delle pagine pubbliche

| Aspetto | Regola |
|---|---|
| Content Security Policy | restrittiva, senza `unsafe-inline` |
| Header di sicurezza | HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy |
| Contenuto redazionale | sanificato lato server |
| Caricamento di file | non ammesso dalle pagine pubbliche |
| Analitica | conforme al GDPR, senza cookie non necessari |
| Errori | pagine generiche, nessun dettaglio tecnico |
| Superficie | nessun endpoint non dichiarato |

Le pagine pubbliche sono l'unico punto raggiungibile senza autenticazione: ogni endpoint aggiunto
qui va giustificato.

---

## Esempi

### Esempio 1 — pubblicazione programmata

Una pagina «Novità di ottobre» con `published_at` al primo ottobre: invisibile prima, pubblicata
automaticamente, senza intervento e senza rilascio.

### Esempio 2 — modulo protetto senza captcha

Campo trappola + tempo minimo di compilazione + limite di 3 invii all'ora per indirizzo IP: il
traffico automatico si azzera senza chiedere nulla agli utenti reali.

---

## Best practice

- Contenuti in database, modificabili senza rilascio.
- Blocchi tipizzati invece di HTML libero.
- Multilingua fin dall'inizio, anche con una sola lingua attiva.
- Cache aggressiva con invalidazione alla pubblicazione.
- Protezioni non interattive prima del captcha.
- Verifica dell'indirizzo prima del provisioning.
- `robots.txt` che esclude i domini dei tenant.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| Contenuti nelle viste | Ogni correzione richiede un rilascio | Contenuti in database |
| HTML libero da editor | Rischio XSS | Blocchi tipizzati e sanificazione |
| Multilingua aggiunto dopo | Riscrittura del modello | Previsto dall'inizio |
| Domini tenant indicizzati | Interfacce dei clienti nei motori di ricerca | `robots.txt` |
| Provisioning senza verifica dell'indirizzo | Database creati da moduli automatici | Verifica obbligatoria |
| Captcha come prima difesa | Accessibilità peggiorata | Protezioni non interattive |
| Cache senza invalidazione | I redattori non vedono le modifiche | Invalidazione alla pubblicazione |

---

## Checklist

- [ ] I contenuti sono in database, con `locale` e `published_at`.
- [ ] Le pagine si compongono di blocchi tipizzati.
- [ ] Meta tag, canonical, `hreflang`, dati strutturati presenti.
- [ ] Sitemap generata; `robots.txt` esclude i domini tenant.
- [ ] Cache HTTP e applicativa con invalidazione alla pubblicazione.
- [ ] Moduli protetti da limite, campo trappola e tempo minimo.
- [ ] Registrazione con verifica dell'indirizzo prima del provisioning.
- [ ] CSP e header di sicurezza attivi.
- [ ] Nessun caricamento di file dalle pagine pubbliche.

---

## Riferimenti

- [Sviluppare il frontend](../docs/03-development/06-frontend-development-guide.md)
- [Regole frontend](../rules/frontend.md) · [Accessibilità](../rules/accessibility.md) · [Sicurezza](../rules/security.md)
- [Ciclo di vita del tenant](07-tenant-lifecycle.md)
- [Modulo cms](../modules/catalog/cms.md)
