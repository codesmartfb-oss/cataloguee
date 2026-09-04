# Katalog WhatsApp

Un catalogue e-commerce léger, pensé pour les ventes par WhatsApp. Il utilise PHP 8.2+, SQLite et un front TypeScript sans dépendance.

## Installation

1. Copiez les fichiers chez un hébergeur PHP 8.2+ avec les extensions `pdo_sqlite` et `sqlite3`.
2. Rendez le dossier `data/` accessible en écriture par PHP. L'application le crée automatiquement au premier lancement.
3. Lancez `php -S localhost:8080` à la racine puis ouvrez `http://localhost:8080`.
4. Connectez-vous à `/admin.html` avec `admin@katalog.local` / `change-me-now`, puis changez les informations de la boutique.

> Avant la mise en ligne, modifiez immédiatement le mot de passe dans `api/auth.php` et déployez derrière HTTPS.

## Ce qui est inclus

- Vitrine mobile-first avec recherche, panier et catégories
- Commande préremplie dans WhatsApp, sans paiement compliqué
- Tableau d'administration : boutique, produits, disponibilité et commandes
- Base SQLite initialisée automatiquement avec des données de démonstration

## Structure

`api/` contient l'API PHP, `public/` les assets compilés et `src/` le TypeScript source. Le site fonctionne directement : `public/app.js` est livré dans le dépôt.
