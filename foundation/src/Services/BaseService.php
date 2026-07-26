<?php

declare(strict_types=1);

namespace WidStudios\Foundation\Services;

/**
 * Base per i servizi.
 *
 * Un servizio esiste quando una capacita' non appartiene a nessuna entita' e
 * non e' una singola operazione: il calcolo di un listino, l'integrazione con
 * un sistema esterno, la generazione di un documento.
 *
 * Il criterio per distinguerlo da un'Action e' semplice: l'Action rappresenta
 * un fatto che accade una volta ("registra il movimento"), il servizio una
 * capacita' che si puo' invocare quante volte si vuole senza cambiare nulla
 * ("calcola il prezzo").
 *
 * Se un servizio cresce fino ad avere metodi che non condividono nulla, non e'
 * un servizio: e' una cartella. In quel caso si divide.
 *
 * Questa classe non impone comportamento: esiste come punto di aggancio comune
 * e come luogo in cui la regola sopra e' scritta.
 */
abstract class BaseService {}
