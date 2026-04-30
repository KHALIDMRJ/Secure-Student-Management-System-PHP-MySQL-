# TP CRUD en PHP / MySQL — Gestion des étudiants

Application web CRUD (Create / Read / Update / Delete) pour la gestion d'une liste d'étudiants, écrite en **PHP procédural** avec **PDO** sur **MySQL/MariaDB**.
Architecture en couches (config / includes / pages / public) avec un point d'entrée unique et un router en query string `?page=…`.

## Auteur

**Khalid MORJAN**

## Fonctionnalités

- Affichage de la liste des étudiants (tri par nom et prénom)
- Ajout d'un nouvel étudiant avec validation côté serveur
- Modification d'un étudiant existant
- Suppression via une page de confirmation dédiée
- Messages flash de succès après chaque opération (PRG)
- Détection des emails déjà utilisés (avec exclusion de l'étudiant courant lors d'une modification)
- Routage interne par query string : `?page=index|ajouter|modifier|supprimer`
- Navbar avec mise en évidence de la page active

### Sécurité

- Requêtes préparées PDO (anti-injection SQL)
- Jetons CSRF générés par session, vérifiés par `hash_equals`, régénérés après chaque écriture
- Échappement HTML systématique via la fonction `e()` (anti-XSS)
- Validation stricte (longueurs, regex Unicode, `FILTER_VALIDATE_EMAIL`)
- Validation des identifiants par `filter_input` + `FILTER_VALIDATE_INT (min_range = 1)`
- Whitelist de routes côté router (`ALLOWED_PAGES`) — toute valeur inconnue retombe sur `index`
- Erreurs SQL journalisées avec `error_log()` ; aucun message technique exposé en production
- Réponses HTTP correctes : `403` sur jeton CSRF invalide, `500` sur erreur serveur

## Prérequis

- **XAMPP** (ou pile équivalente Apache + MySQL)
- **PHP 8.1+**
- **MySQL** ou **MariaDB**
- Un navigateur moderne

## Installation

1. **Placer le projet** dans le répertoire `htdocs` de XAMPP :
   ```
   C:\xampp\htdocs\TP_CRUD_en_PHP_MySQL_MORJAN_KHALID
   ```

2. **Démarrer Apache et MySQL** depuis le panneau de contrôle XAMPP.

3. **Importer le schéma** dans MySQL — au choix :

   - Via **phpMyAdmin** : ouvrir [http://localhost/phpmyadmin](http://localhost/phpmyadmin), onglet **Importer**, puis sélectionner le fichier `schema.sql`.
   - Via la **CLI MySQL** :
     ```bash
     mysql -u root -p < schema.sql
     ```

4. **Vérifier la configuration** dans `config/config.php` :
   - Constantes `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` adaptées à votre environnement
   - `APP_ENV` défini à `'development'` pendant le développement, `'production'` en déploiement
   - `BASE_URL` ajusté si vous renommez le dossier (par défaut : `/TP_CRUD_en_PHP_MySQL_MORJAN_KHALID/public/`)

5. **Ouvrir l'application** dans le navigateur :
   ```
   http://localhost/TP_CRUD_en_PHP_MySQL_MORJAN_KHALID/
   ```
   Le fichier `index.php` à la racine redirige automatiquement vers `public/index.php`.

## Structure du projet

```
TP_CRUD_en_PHP_MySQL_MORJAN_KHALID/
├── config/
│   └── config.php          # Constantes app + BD, getPDO() singleton, garde APP_LOADED
├── includes/
│   ├── helpers.php         # CSRF, validate_student, is_email_taken, e(), redirect, active_page
│   ├── header.php          # <head> + navbar (style inline minimal)
│   └── footer.php          # </main> + footer + lien JS
├── pages/
│   ├── index.php           # Liste des étudiants
│   ├── ajouter.php         # Ajout
│   ├── modifier.php        # Modification
│   └── supprimer.php       # Suppression (confirmation + DELETE)
├── public/
│   ├── css/
│   │   └── style.css       # (Phase 3 — Bootstrap + styles personnalisés)
│   ├── js/
│   │   └── app.js          # (Phase 3 — JavaScript)
│   └── index.php           # Front controller / router (?page=…)
├── schema.sql              # Création BD + table + jeu de données démo
├── index.php               # Redirige vers public/index.php
└── README.md
```

## Routage

- Toute la navigation passe par `?page=…` côté `public/index.php`.
- Pages autorisées : `index`, `ajouter`, `modifier`, `supprimer`.
- Toute autre valeur retombe silencieusement sur `index`.
- Aucune URL absolue n'est codée en dur dans les pages — seule `BASE_URL` (dans `config/config.php`) sert pour les liens vers `public/css/` et `public/js/`.

## Mesures de sécurité appliquées

| Risque              | Contre-mesure                                                                |
|---------------------|------------------------------------------------------------------------------|
| Injection SQL       | Requêtes préparées PDO avec `bindValue` typé (`PDO::PARAM_STR`/`PARAM_INT`)  |
| XSS                 | Échappement systématique via `e()` (`htmlspecialchars`, UTF-8)               |
| CSRF                | Jeton de session vérifié par `hash_equals`, régénéré après chaque écriture   |
| Double soumission   | Pattern Post / Redirect / Get sur tous les formulaires                       |
| Données invalides   | `validate_student()` + `filter_input(..., FILTER_VALIDATE_INT, min_range=1)` |
| Routes inattendues  | Whitelist `ALLOWED_PAGES` dans `public/index.php`                            |
| Doublons d'email    | Contrainte `UNIQUE` sur la colonne + vérification applicative                |
| Fuite d'erreurs     | `error_log()` côté serveur, message générique côté utilisateur en production |
| Double-include      | Garde `APP_LOADED` au sommet de `config/config.php`                          |

## Limitations connues / Pistes d'amélioration

- **Pagination** : la liste affiche tous les étudiants. À refaire avec `LIMIT` / `OFFSET` au-delà de quelques dizaines de lignes.
- **Authentification & autorisation** : aucune connexion utilisateur n'est requise. Une couche de login (sessions + rôles) protégerait les actions d'écriture.
- **Phase 3 — Bootstrap UI** : le style CSS embarqué dans `header.php` est minimal. Les fichiers `public/css/style.css` et `public/js/app.js` sont prêts à recevoir Bootstrap (ou Tailwind) et un peu de JavaScript.
- **Internationalisation** : libellés en français en dur ; un système de traductions (`gettext` ou tableau de clés) faciliterait l'ajout d'autres langues.
- **Tests automatisés** : ajouter une suite **PHPUnit** couvrant `validate_student`, `is_email_taken`, `active_page` et les flux CRUD.
- **Logs structurés** : remplacer `error_log()` par **Monolog** pour bénéficier de niveaux et de canaux de sortie configurables.
- **Migration vers un framework** : pour un projet plus important, **Laravel** ou **Symfony** apporterait routage avancé, ORM (Eloquent / Doctrine) et validation déclarative.
