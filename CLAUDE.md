# easy_receipt — Quittances de Loyer

Application web PHP de génération de quittances de loyer, hébergée sur OVH.
Interface en français. Pas de framework — PHP vanilla + PDO + TCPDF.

---

## Stack technique

- **Backend** : PHP 8+ (pas de framework)
- **Base de données** : MySQL 5.7+ / MariaDB 10.3+ (PDO, `utf8mb4`)
- **PDF** : TCPDF via Composer (`composer require tecnickcom/tcpdf`)
- **CSS** : feuille personnalisée, Google Fonts (DM Sans + DM Serif Display)
- **Hébergement** : OVH (Apache + `.htaccess`)
- **Pas de JS framework** — JS vanilla inline pour les filtres de formulaire

---

## Structure des fichiers

```
/
├── .htaccess                   Sécurité Apache (protège includes/, pdf/, receipts_storage/)
├── config.php                  DB_HOST/NAME/USER/PASS, BASE_URL, SESSION_LIFETIME, locale fr
├── database.sql                Schéma complet + compte admin par défaut (admin / admin1234)
├── login.php                   Page de connexion (publique)
├── logout.php                  Déconnexion → /login.php
├── index.php                   Tableau de bord : stats, grille d'appartements, dernières quittances
├── app.css                     Copie racine (à ignorer — utiliser assets/css/app.css)
│
├── assets/
│   └── css/app.css             Feuille de style principale (référencée par toutes les pages)
│
├── includes/
│   ├── auth.php                session_init(), login(), logout(), auth_user(), require_auth(), require_admin()
│   ├── db.php                  Singleton PDO : fonction db()
│   ├── helpers.php             e(), money(), french_month(), french_date(), csrf_token(), csrf_check(), redirect(), flash(), get_flash()
│   ├── header.php              <!DOCTYPE> + navbar (attend $page_title, $current_nav, $user)
│   └── footer.php              Fermeture </main></body></html>
│
├── pages/
│   ├── flats.php               Liste + suppression d'appartements
│   ├── flat_form.php           Ajout/modification (?id=X pour édition)
│   ├── flat_detail.php         Détail : locataires + quittances (?id=X)
│   ├── tenant_form.php         Ajout/modification (?flat_id=X nouveau, ?id=X édition)
│   ├── receipts.php            Historique avec filtres GET : flat_id, year, month
│   ├── receipt_form.php        Création quittance ; pré-remplissage ?flat_id=X&tenant_id=Y
│   ├── receipt_download.php    Aperçu quittance (?id=X) ; ?id=X&action=pdf → stream PDF
│   ├── profile.php             Compte utilisateur (section=account) + profil bailleur (section=landlord)
│   └── users.php               Gestion utilisateurs — admin uniquement (?edit=X, ?new=1)
│
├── pdf/
│   └── generate_receipt.php    generate_receipt_pdf(array $data, string $output, string $filename)
│                               stream_receipt_pdf(array $data, string $filename)
│
└── receipts_storage/           PDFs générés (accès direct bloqué par .htaccess)
```

---

## Schéma de base de données

| Table       | Clé étrangère                        | Rôle                                      |
|-------------|--------------------------------------|-------------------------------------------|
| `users`     | —                                    | Comptes utilisateurs (role: admin\|user)  |
| `landlords` | `user_id → users.id`                 | Profil bailleur (1 par user)              |
| `flats`     | `user_id → users.id`                 | Appartements                              |
| `tenants`   | `flat_id → flats.id`                 | Locataires (active TINYINT)               |
| `receipts`  | `flat_id`, `tenant_id`, `user_id`    | Quittances ; `total_amount` colonne GENERATED |

Contrainte d'unicité : `(flat_id, tenant_id, period_month, period_year)` dans `receipts`.

---

## Conventions de code

### Chaque page protégée commence par :
```php
require_once __DIR__ . '/../config.php';       // (ou __DIR__ . '/config.php' depuis la racine)
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

session_init();
$user = require_auth(); // redirige vers /login.php si non connecté
```

### Avant d'inclure header.php, définir :
```php
$page_title  = 'Titre de la page';
$current_nav = 'dashboard|flats|receipts|profile|users';
// $user est déjà défini par require_auth()
require_once __DIR__ . '/../includes/header.php';
```

### Sécurité formulaires POST :
- Token CSRF : `<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">` + `csrf_check()` en début de handler
- Toujours vérifier `user_id` sur les données avant UPDATE/DELETE
- Isolation des données par `user_id` sur toutes les requêtes

### Données d'une quittance (array pour generate_receipt_pdf) :
```php
[
  'landlord_name', 'landlord_address',   // depuis table landlords
  'tenant_name',                          // CONCAT(first_name, last_name)
  'flat_address',                         // depuis table flats
  'period_month' (int), 'period_year' (int),
  'rent_amount' (float), 'charges_amount' (float), 'total_amount' (float),
  'payment_date' (Y-m-d), 'payment_mode',
  'notes' (optionnel),
]
```

---

## Classes CSS disponibles

| Classe          | Usage                                      |
|-----------------|--------------------------------------------|
| `.card`         | Bloc blanc avec ombre                      |
| `.card-grid`    | Grille responsive de cards                 |
| `.flat-card`    | Card appartement avec barre colorée        |
| `.stats`        | Grille de stat-cards                       |
| `.stat-card`    | Chiffre stat individuel                    |
| `.btn`          | Bouton base                                |
| `.btn-primary`  | Vert sage                                  |
| `.btn-secondary`| Transparent bordé                          |
| `.btn-danger`   | Terracotta                                 |
| `.btn-ghost`    | Transparent bordé sage                     |
| `.btn-sm`       | Petit bouton                               |
| `.form-grid`    | Grille 2 colonnes pour formulaires         |
| `.form-group`   | Groupe label + input                       |
| `.form-group.full` | Pleine largeur dans form-grid           |
| `.alert-success/error/info` | Messages flash                |
| `.breadcrumb`   | Fil d'Ariane                               |
| `.table-wrap`   | Conteneur table responsive                 |
| `.empty-state`  | État vide centré                           |
| `.page-header`  | Flex row titre + bouton d'action           |

Variables CSS principales : `--sage`, `--sage-dark`, `--sage-light`, `--ink`, `--ink-light`, `--cream`, `--gold`, `--terracotta`, `--border`.

---

## Installation / déploiement

1. Importer `database.sql` dans MySQL
2. Modifier `config.php` : `DB_USER`, `DB_PASS`, `BASE_URL`
3. Installer TCPDF : `composer require tecnickcom/tcpdf`
4. S'assurer que `receipts_storage/` est accessible en écriture par Apache
5. Se connecter avec `admin` / `admin1234` → **changer immédiatement le mot de passe**
6. Aller dans *Mon profil* → remplir le profil bailleur avant toute génération PDF

---

## Fonctionnalités à venir / pistes d'amélioration

- Export CSV des quittances
- Envoi par email (PHPMailer)
- Rappels de paiement
- Logo bailleur sur le PDF
- Gestion de plusieurs profils bailleur par user
