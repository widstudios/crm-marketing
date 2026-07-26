# Modulo — cms

> La parte pubblica: landing page, contenuti editoriali, media, presenza sui motori.

| | |
|---|---|
| **Nome** | `cms` |
| **Categoria** | opzionale |
| **Dipende da** | `documents` |

---

## Indice

1. [Descrizione](#descrizione) 2. [Che cosa fornisce](#che-cosa-fornisce)
3. [Che cosa non fa](#che-cosa-non-fa) 4. [Landing di piattaforma e siti dei tenant](#landing-di-piattaforma-e-siti-dei-tenant)
5. [Sicurezza dei contenuti](#sicurezza-dei-contenuti) 6. [Configurazione](#configurazione)
7. [Integrazione](#integrazione) 8. [Adozione](#adozione) 9. [Esempi](#esempi)
10. [Best practice](#best-practice) 11. [Errori comuni](#errori-comuni) 12. [Checklist](#checklist)
13. [Riferimenti](#riferimenti)

---

## Descrizione

Ogni SaaS ha bisogno di una landing page, e molti clienti hanno bisogno di una parte pubblica
propria: un portale, un catalogo, una pagina informativa.

Questo modulo copre entrambi i casi con la stessa struttura. Non è un CMS generalista, ed è una
scelta: un CMS generalista è un prodotto a sé, e costruirne uno dentro un gestionale produce una
versione peggiore di strumenti che esistono già. Questo modulo fa poche cose — pagine strutturate a
sezioni, media, metadati — e le fa in modo che siano veloci, sicure e traducibili.

---

## Che cosa fornisce

### Tabelle — landlord

| Tabella | Contenuto |
|---|---|
| `platform_pages` | pagine della landing del servizio |
| `platform_page_sections` | sezioni tipizzate |

### Tabelle — tenant

| Tabella | Contenuto |
|---|---|
| `pages` | pagine pubbliche del cliente |
| `page_sections` | sezioni tipizzate, ordinate |
| `page_translations` | contenuti per lingua |
| `page_revisions` | storico delle modifiche, con ripristino |
| `menus`, `menu_items` | navigazione |
| `redirects` | reindirizzamenti permanenti |

L'ultima tabella evita il difetto più comune di un CMS: una pagina rinominata rompe ogni link
esterno che vi puntava, e i link esterni non si aggiornano.

### Permessi

| Permesso | Consente |
|---|---|
| `page.view` · `page.create` · `page.update` · `page.delete` | gestione |
| `page.publish` | pubblicazione — separato dalla modifica |
| `menu.manage` · `redirect.manage` | navigazione |

`page.publish` è separato da `page.update` di proposito: chi scrive non è necessariamente chi
approva, e in molte organizzazioni non deve esserlo.

### Comandi

| Comando | Quando |
|---|---|
| `cms:publish-scheduled` | pianificato: pubblica i contenuti programmati |
| `cms:warm-cache` | dopo un rilascio |
| `cms:check-links` | verifica dei link interni rotti |
| `cms:sitemap` | rigenerazione della mappa del sito |

---

## Che cosa non fa

| Non fa | Dove va cercato |
|---|---|
| Editor visuale a trascinamento | non previsto: sezioni tipizzate, non composizione libera |
| Blog con commenti | fuori ambito |
| Commercio elettronico | fuori ambito |
| Test A/B, personalizzazione | fuori ambito |
| Analitiche di traffico | servizio esterno |
| Modulo di contatto | progetto: la destinazione dei messaggi è dominio |
| Gestione dei consensi ai cookie | progetto: dipende dalla normativa e dai servizi usati |

**Perché sezioni tipizzate e non composizione libera.** Un editor a trascinamento permette a
chiunque di costruire una pagina, e permette a chiunque di costruirla male: si perde la coerenza
visiva, l'accessibilità e la possibilità di cambiare il tema senza rifare tutto. Le sezioni tipizzate
— «testimonianze», «prezzi», «domande frequenti» — sono meno libere e producono pagine che restano
coerenti nel tempo. È una limitazione deliberata, non una funzionalità mancante.

---

## Landing di piattaforma e siti dei tenant

| | Landing di piattaforma | Sito del tenant |
|---|---|---|
| Database | landlord | tenant |
| Dominio | quello centrale | quello del cliente |
| Chi la gestisce | personale di piattaforma | tenant admin |
| Autenticazione | nessuna, è pubblica | nessuna, è pubblica |

Stessa struttura, due destinazioni. Una pagina di piattaforma non è mai visibile da un dominio
cliente, e viceversa: il contesto è già deciso dal dominio da cui arriva la richiesta.

---

## Sicurezza dei contenuti

Un CMS accetta HTML da persone che non sono sviluppatori. È il punto in cui un progetto sicuro può
diventare vulnerabile in un pomeriggio.

**Sanificazione lato server, con lista bianca.** Il contenuto proveniente da un editor viene ripulito
prima di essere salvato, con un elenco chiuso di tag e attributi ammessi. La sanificazione nel
browser non conta: chi vuole aggirarla invia la richiesta direttamente.

```php
'allowed_tags' => ['p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'a', 'h2', 'h3', 'blockquote'],
'allowed_attributes' => ['href', 'title', 'target', 'rel'],
```

**Nessuno `<script>`, nessun `<iframe>`, nessun `<style>`, nessun attributo `on*`.** Un iframe verso
un servizio esterno si aggiunge come **sezione tipizzata** con un elenco di domini ammessi, non come
HTML libero: così il dominio è verificabile.

**CSP restrittiva**, senza `unsafe-inline` sugli script: è la seconda linea, quella che limita il
danno se la prima ha un buco.

---

## Configurazione

```php
// config/cms.php
return [
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        // Invalidazione esplicita alla pubblicazione: una pagina pubblicata che
        // compare fra un'ora è un difetto che il cliente segnala subito.
        'invalidate_on_publish' => true,
    ],

    'sanitizer' => [
        'allowed_tags' => ['p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'a', 'h2', 'h3', 'blockquote'],
        'allowed_attributes' => ['href', 'title', 'target', 'rel'],
        'allowed_iframe_hosts' => [],
    ],

    'media' => [
        'max_width' => 2560,
        'formats' => ['webp', 'jpeg'],
    ],

    'revisions' => ['keep' => 20],

    'locales' => ['it', 'en'],
];
```

---

## Integrazione

Le pagine pubbliche sono **cacheable** e non richiedono autenticazione. Il contesto tenant è già
aperto dal middleware, quindi la pagina servita è quella del cliente proprietario del dominio.

La cache è tenant-scoped: una landing memorizzata senza prefisso mostrerebbe la pagina di un cliente
sul dominio di un altro — un difetto visibile e imbarazzante, e per fortuna evidente.

---

## Adozione

```bash
# config/foundation.php → 'modules' => ['enabled' => [..., 'documents', 'cms']]

php artisan migrate --database=landlord
php artisan tenants:migrate
php artisan tenants:artisan "auth:sync-permissions"
php artisan tenants:artisan "cms:warm-cache"
```

`documents` deve essere attivo: senza, i media delle pagine non hanno dove essere archiviati.

---

## Esempi

### Sezione tipizzata

```php
final class PricingSection extends PageSection
{
    protected string $type = 'pricing';

    /** @return array<string, mixed> */
    public function schema(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'plans' => ['required', 'array', 'min:1', 'max:4'],
            'plans.*.name' => ['required', 'string', 'max:60'],
            'plans.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
```

Il contenuto è **strutturato e validato**: non è HTML libero, e questo è ciò che permette di
cambiare il tema senza rifare le pagine.

### Reindirizzamento alla rinomina

```php
if ($page->isDirty('slug')) {
    Redirect::create([
        'from' => $page->getOriginal('slug'),
        'to' => $page->slug,
        'status' => 301,
    ]);
}
```

Senza, ogni link esterno alla pagina precedente porta a un errore, e i link esterni non si
aggiornano.

---

## Best practice

- Sezioni tipizzate, mai HTML libero dove è possibile evitarlo.
- Sanificare lato server, con lista bianca.
- Creare il reindirizzamento automaticamente a ogni rinomina.
- Invalidare la cache alla pubblicazione, non aspettare la scadenza.
- Ridimensionare i media lato server e servirli in formati moderni.
- Verificare l'accessibilità delle pagine pubbliche: sono la superficie con il pubblico più vario.

---

## Errori comuni

| Errore | Conseguenza | Rimedio |
|---|---|---|
| HTML libero senza sanificazione | Cross-site scripting nella pagina pubblica | Lista bianca lato server |
| Sanificazione solo nel browser | Aggirata inviando la richiesta direttamente | Sempre lato server |
| `<iframe>` da HTML libero | Contenuto arbitrario incorporato | Sezione tipizzata con domini ammessi |
| Cache non tenant-scoped | La landing di un cliente su un altro dominio | `TenantCacheKey` |
| Cache non invalidata alla pubblicazione | La pagina compare un'ora dopo | Invalidazione esplicita |
| Nessun reindirizzamento alla rinomina | Link esterni rotti | `redirects` automatici |
| Immagini non ridimensionate | Landing lenta su connessioni mobili | Ridimensionamento lato server |
| Pubblicazione non separata dalla modifica | Chi scrive pubblica | Permesso `page.publish` |
| Nessuna revisione | Una modifica sbagliata non si annulla | `page_revisions` |

---

## Checklist

- [ ] Il contenuto degli editor è sanificato lato server con lista bianca.
- [ ] Nessuno `<script>`, `<iframe>`, `<style>` o attributo `on*` ammesso.
- [ ] La CSP è restrittiva, senza `unsafe-inline`.
- [ ] La cache è tenant-scoped e si invalida alla pubblicazione.
- [ ] Ogni rinomina crea un reindirizzamento.
- [ ] I media sono ridimensionati e serviti in formati moderni.
- [ ] `page.publish` è separato da `page.update`.
- [ ] Le pagine pubbliche rispettano i requisiti di accessibilità.

---

## Riferimenti

- [CMS e landing](../../architecture/17-cms-landing.md)
- [Sicurezza](../../rules/security.md) · [Accessibilità](../../rules/accessibility.md) · [i18n](../../rules/i18n.md)
- [Cache](../../rules/cache.md) · [Strategia di caching](../../architecture/22-caching-strategy.md)
- [documents](documents.md)
