# Catalogue WhatsApp

Catalogue e-commerce léger pour vendre via WhatsApp. Architecture volontairement simple : **PHP 8.2+, SQLite, HTML/CSS et TypeScript**. Aucune application Node.js n'est nécessaire en production.

## Production recommandée

Le projet est conçu pour un hébergement PHP/Apache classique, par exemple **o2switch**. Leur hébergement permet de choisir une version PHP récente et de charger les modules nécessaires depuis cPanel. citehttps://faq.o2switch.fr/cpanel/logiciels/hebergement-php-multi-version/

Vercel n'est pas la cible de production de cette version : le backend est du PHP classique avec une base SQLite locale et des fichiers uploadés.

## Déploiement o2switch

### 1. Préparer le domaine

Dans cPanel, ajoutez le domaine ou sous-domaine qui servira ce catalogue et choisissez comme racine du document le dossier du projet.

### 2. Choisir PHP

Dans **cPanel → Sélectionner une version de PHP**, choisissez **PHP 8.3 ou supérieur** et activez au minimum :

- `pdo`
- `pdo_sqlite`
- `sqlite3`
- `fileinfo`
- `gd`
- `mbstring`
- `openssl`
- `json`
- `zip` (uniquement nécessaire pour la sauvegarde ZIP)

o2switch documente la sélection de version et des modules PHP dans cPanel. citehttps://faq.o2switch.fr/cpanel/logiciels/hebergement-php-multi-version/

### 3. Envoyer le projet

Envoyez le contenu du dépôt dans la racine du domaine avec le Gestionnaire de fichiers cPanel ou SFTP.

**Ne téléversez jamais une ancienne base SQLite contenant des données client.** Le fichier `data/catalogue.sqlite` est créé sur le serveur.

### 4. Installer

Ouvrez :

`https://votre-domaine.tld/install.php`

Créez le compte administrateur avec un mot de passe d'au moins 12 caractères.

L'installateur crée `config/local.php` avec :

- l'email administrateur ;
- un hash du mot de passe ;
- un secret aléatoire pour les tokens API.

### 5. Supprimer l'installateur

Après installation, **supprimez `install.php` du serveur**.

Ne laissez pas l'installateur accessible publiquement.

### 6. Vérifier les droits

PHP doit pouvoir écrire dans :

- `data/`
- `uploads/`

Évitez `777`. Utilisez les permissions normales de l'hébergement et le propriétaire PHP/cPanel approprié.

### 7. HTTPS

Le `.htaccess` force HTTPS et bloque notamment `config/`, `data/`, `src/` et les bases SQLite. Vérifiez ensuite :

- `https://votre-domaine.tld/`
- `https://votre-domaine.tld/admin.html`
- la connexion admin ;
- l'ajout d'un produit ;
- l'upload d'une image ;
- la commande WhatsApp ;
- la sauvegarde.

### 8. Configuration WhatsApp

Le numéro doit être enregistré au format international, sans `+`, espaces, parenthèses ni tirets. Exemple Côte d'Ivoire : `225XXXXXXXXXX`.

Le bouton de commande utilise le lien WhatsApp avec un message prérempli ; aucune API WhatsApp Business n'est nécessaire pour ce fonctionnement.

## Structure

```text
api/                 API PHP
public/              CSS/JS utilisés par la vitrine
src/                 TypeScript source
data/                SQLite créée en production
uploads/              images envoyées par le commerçant
config/local.php      configuration secrète, créée à l'installation
install.php            installateur one-shot
.htaccess              règles Apache de sécurité
```

## Développement local

Avec PHP installé :

```bash
php -S localhost:8080
```

Puis ouvrez `http://localhost:8080/install.php`.

Le JavaScript compilé est déjà présent dans `public/`. Node.js n'est utile que si vous souhaitez reconstruire les assets TypeScript.

## Sécurité production

- Ne commitez jamais `config/local.php`.
- Ne commitez jamais `data/*.sqlite`.
- Ne laissez pas `install.php` après installation.
- Utilisez HTTPS.
- Utilisez un mot de passe administrateur fort.
- Faites des sauvegardes régulières de `data/` et `uploads/`.
- Ne partagez jamais les tokens API.
- Surveillez les erreurs PHP dans les logs du serveur plutôt que d'activer `display_errors` en production.

## Modèle de déploiement multi-client

Chaque commerçant reçoit **sa propre installation** :

```text
client-a.tld  → PHP + SQLite A + uploads A
client-b.tld  → PHP + SQLite B + uploads B
client-c.tld  → PHP + SQLite C + uploads C
```

Les données des clients ne sont donc pas mélangées dans une base SaaS centrale.
