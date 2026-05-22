# Quittances de Loyer

Application web PHP de génération de quittances de loyer.  
Gestion multi-appartements, multi-locataires, génération PDF, historique.

---

## Fonctionnalités

- Connexion sécurisée par identifiant / mot de passe
- Gestion des appartements (propriétaire ou mandataire)
- Gestion des locataires par appartement
- Création et historique des quittances
- Génération PDF (TCPDF) — mise en cache sur disque
- Suppression de quittances avec effacement du PDF
- Profil bailleur personnalisable (affiché sur les PDF)
- Gestion des utilisateurs (admin)

---

## Prérequis

- PHP 8.0+
- MySQL 5.7+ ou MariaDB 10.3+
- [Composer](https://getcomposer.org)
- Apache avec `mod_rewrite` activé

---

## Installation locale (Laragon)

### 1. Installer Laragon

Téléchargez et installez **Laragon Full** depuis [laragon.org](https://laragon.org/download).  
Lancez Laragon → **Start All**.

### 2. Cloner le projet

```bash
git clone https://github.com/daarhaan/easy_receipt.git
```

### 3. Créer un lien vers Laragon

Dans PowerShell (ou en tant qu'administrateur) :

```powershell
New-Item -ItemType Junction -Path "C:\laragon\www\easy_receipt" -Target "C:\chemin\vers\easy_receipt"
```

Le site sera accessible sur `http://easy_receipt.test`

### 4. Créer la base de données

Ouvrez **HeidiSQL** depuis Laragon → **Database → HeidiSQL**.

- Créez une base de données nommée `rent_receipts`
- Importez le fichier `database.sql` (**Fichier → Exécuter un script SQL...**)

### 5. Configurer l'application

Ouvrez `config.php` et renseignez :

```php
define('DB_USER', 'root');
define('DB_PASS', '');                          // vide par défaut sous Laragon
define('DB_NAME', 'rent_receipts');
define('BASE_URL', 'http://easy_receipt.test');
```

### 6. Installer TCPDF

Depuis le dossier du projet :

```bash
C:\laragon\bin\php\php-x.x.x\php.exe C:\laragon\bin\composer\composer.phar require tecnickcom/tcpdf
```

Ou si Composer est dans votre PATH :

```bash
composer require tecnickcom/tcpdf
```

### 7. Permissions

Assurez-vous que le dossier `receipts_storage/` est accessible en écriture.

---

## Première connexion

| Identifiant | Mot de passe |
|-------------|--------------|
| `admin`     | `admin1234`  |

> **Changez le mot de passe immédiatement** dans *Mon profil* après connexion.

---

## Utilisation

### 1. Configurer le profil bailleur
**Mon profil → Profil bailleur** — ces informations apparaissent sur tous les PDF.

### 2. Ajouter un appartement
**Appartements → + Ajouter** — précisez si vous êtes *propriétaire* ou *mandataire*.

### 3. Ajouter un locataire
Depuis le détail d'un appartement → **+ Ajouter** un locataire.

### 4. Créer une quittance
**Quittances → + Nouvelle quittance** ou directement depuis un appartement.  
Le PDF est généré automatiquement et mis en cache.

### 5. Télécharger un PDF
Cliquez sur **PDF** dans n'importe quelle liste de quittances — s'ouvre dans un nouvel onglet.

---

## Déploiement sur OVH

1. Uploadez tous les fichiers via FTP (sauf `vendor/` — relancez `composer install` sur le serveur)
2. Importez `database.sql` via phpMyAdmin
3. Modifiez `config.php` avec les identifiants de production
4. Vérifiez que `receipts_storage/` est accessible en écriture par Apache
5. Désactivez `display_errors` dans `config.php` (déjà à `Off` par défaut)

---

## Structure du projet

```
/
├── config.php              Configuration (DB, chemins, locale)
├── database.sql            Schéma + compte admin par défaut
├── login.php / logout.php  Authentification
├── index.php               Tableau de bord
├── assets/css/app.css      Feuille de style
├── includes/               Auth, DB, helpers, header, footer
├── pages/                  Toutes les pages de l'application
├── pdf/                    Générateur PDF (TCPDF)
└── receipts_storage/       PDFs générés (non versionné)
```
