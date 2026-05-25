# CRM Marketing - Modular Architecture

Piattaforma CRM marketing modulare in **PHP 8.0** con **SQLite**, progettata per scalare facilmente.

## ✨ Caratteristiche

- ✅ **Architettura modulare** - 5 moduli indipendenti
- ✅ **PHP 8.0** - Codice moderno e performante
- ✅ **SQLite** - Database leggero e portabile
- ✅ **RESTful API** - Interfaccia JSON completa
- ✅ **Unit Tests** - Copertura completa dei test
- ✅ **CLI Commands** - Gestione via terminale
- ✅ **Webhooks** - Event-driven architecture
- ✅ **Docker Support** - Deployment facile

## 📦 Moduli

1. **Contacts** - Gestione contatti e lead
2. **Campaigns** - Gestione campagne marketing
3. **Email** - Invio email e tracciamento
4. **Analytics** - Reportistica e dati
5. **Automation** - Workflow e automazioni

## 🚀 Installazione Rapida

```bash
git clone https://github.com/widstudios/crm-marketing.git
cd crm-marketing
composer install
php core/install.php
php -S localhost:8000 -t public
```

Accedi a: http://localhost:8000/dashboard.html

## 🔌 API Endpoints

```
GET    /api/v1/contacts
POST   /api/v1/contacts
PUT    /api/v1/contacts/:id
DELETE /api/v1/contacts/:id

GET    /api/v1/campaigns
POST   /api/v1/campaigns
PUT    /api/v1/campaigns/:id

GET    /api/v1/emails
POST   /api/v1/emails
PUT    /api/v1/emails/:id/status

GET    /api/v1/analytics/dashboard
GET    /api/v1/analytics/campaigns/:id

GET    /api/v1/automations
POST   /api/v1/automations
PUT    /api/v1/automations/:id
```

## 📄 Licenza

MIT License