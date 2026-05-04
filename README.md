<div align="center">

```
███████╗███████╗ ██████╗██╗   ██╗██████╗ ███████╗
██╔════╝██╔════╝ ██╔════██║   ██║██╔═██╗ ██╔════╝
███████╗█████╗ ██║    ██║   ██║██████╔╝█████╗
╚════██║██╔══╝ ██║    ██║   ██║██╔═██╗ ██╔══╝
███████║███████╗╚██████╗╚██████╔╝██║  ██║███████╗
╚══════╝╚══════╝ ╚═════╝ ╚═════╝ ╚═╝  ╚═╝╚══════╝
        S T U D E N T   M A N A G E M E N T
```

> *A production-grade, security-first student management platform —
> built from scratch in pure PHP with zero frameworks.*

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white)
![Chart.js](https://img.shields.io/badge/Chart.js-4.4-FF6384?logo=chartdotjs&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-yellow)
![Status](https://img.shields.io/badge/Status-Production_Ready-success)
![Security](https://img.shields.io/badge/Security-Hardened-red)
![Made with ❤️](https://img.shields.io/badge/Made_with-%E2%9D%A4%EF%B8%8F-ff69b4)
![PRs Welcome](https://img.shields.io/badge/PRs-Welcome-brightgreen)
![XAMPP](https://img.shields.io/badge/XAMPP-Compatible-FB7A24)

</div>

**SecureStudentMS** est une plateforme web complète de gestion d'étudiants
conçue pour les établissements supérieurs qui ont besoin à la fois d'un
back-office administratif puissant et d'un portail étudiant en libre-service.
L'application expose deux interfaces strictement cloisonnées — une console
admin avec CRUD complet, dashboards analytiques, saisie des notes et console
SQL ; et un espace étudiant où chacun ne voit que ses propres données. La
sécurité y est traitée comme un concern architectural de premier plan :
bcrypt, jetons CSRF, rate limiting fichier, Content-Security-Policy avec
nonce, sessions hardenées, et défense honeypot anti-bot. Construit **sans la
moindre dépendance framework**, le projet démontre qu'une application PHP
moderne peut être propre, sécurisée et maintenable à condition que chaque
décision d'architecture soit explicite — ce qui est précisément l'objet
pédagogique de ce projet de fin d'études.

---

## 📋 Table des matières

1. [✨ Aperçu des fonctionnalités](#-aperçu-des-fonctionnalités)
2. [🔐 Architecture de sécurité](#-architecture-de-sécurité)
3. [🏗️ Architecture technique](#️-architecture-technique)
4. [🛠️ Stack technique](#️-stack-technique)
5. [🗄️ Modèle de données](#️-modèle-de-données)
6. [🛣️ Routes](#️-routes)
7. [🚀 Installation et démarrage](#-installation-et-démarrage)
8. [🎨 Interface utilisateur](#-interface-utilisateur)
9. [📊 Fonctionnalités avancées](#-fonctionnalités-avancées)
10. [📸 Captures d'écran](#-captures-décran)
11. [🔮 Améliorations futures](#-améliorations-futures)
12. [🤝 Contribution](#-contribution)
13. [📄 Licence](#-licence)
14. [👨‍💻 Auteur](#-auteur)

---

## ✨ Aperçu des fonctionnalités

L'application n'est pas une simple démo CRUD : elle implémente un cycle de
gestion académique complet, du référentiel des modules jusqu'à la saisie des
notes en passant par l'auto-inscription des étudiants. Chaque fonctionnalité
est conçue pour être **utilisée en production** — validation serveur,
messages d'erreur explicites, transitions soignées, états vides documentés,
notifications toast, et raccourcis clavier.

### 👨‍💼 Espace Administrateur

| Fonctionnalité | Description | Icône |
| --- | --- | :---: |
| **Tableau de bord** | KPIs, bar chart de répartition par filière, line chart des inscriptions sur 12 mois (Chart.js, palette adaptative) | 📊 |
| **Gestion étudiants** | CRUD complet avec recherche serveur, filtre par filière, tri par colonne, pagination intelligente | 👥 |
| **Gestion modules** | CRUD modules + auto-inscription transactionnelle des étudiants de la filière concernée | 📚 |
| **Saisie des notes** | Interface bulk avec aperçu live du statut, dirty-tracking, avertissement *unsaved changes* | 📝 |
| **Console SQL Runner** | Exécute toute requête (SELECT/INSERT/UPDATE/DELETE/DDL), modal de confirmation pour les requêtes destructives, audit log, export CSV | 💻 |
| **Authentification** | bcrypt + rate limiter (5/min puis blocage 5 min) + CSRF + session fixation defence | 🔐 |
| **Mode sombre/clair** | Thème GitHub-inspired, transition fluide, persistance localStorage, scrollbar themée | 🌓 |
| **Headers sécurité** | Content-Security-Policy strict avec nonce par requête, X-Frame-Options, Permissions-Policy | 🛡️ |

### 🎓 Espace Étudiant

| Fonctionnalité | Description | Icône |
| --- | --- | :---: |
| **Tableau de bord personnel** | Modules inscrits, validés, échoués, moyenne générale ; activité récente | 🏠 |
| **Mes modules** | Liste regroupée par semestre, crédits ECTS, statut par module, barre de progression globale | 📚 |
| **Mes notes** | Note sur 20 par module + statut auto-dérivé (`validé` / `échoué` / `inscrit`) | 🎯 |
| **Mon profil** | Infos personnelles + changement de mot de passe avec vérification de l'ancien | 👤 |
| **Interface dédiée** | Palette teal distincte de l'admin, navigation horizontale, identité visuelle propre | 🎨 |

---

## 🔐 Architecture de sécurité

> *This project treats security not as an afterthought but as a first-class
> architectural concern. Every user input is validated, every output is
> escaped, and every session is hardened.*

Chaque vecteur d'attaque a été identifié explicitement et reçoit au moins
**une mitigation dédiée**. La matrice ci-dessous documente la défense pour
chacun.

### Matrice de menaces

| Menace | Vecteur d'attaque | Protection implémentée | Fichier |
| --- | --- | --- | --- |
| **SQL Injection** | Paramètres malveillants en URL/formulaire | PDO prepared statements (`ATTR_EMULATE_PREPARES = false`) | `config/config.php` |
| **XSS** | Injection de scripts dans les champs | `htmlspecialchars()` `ENT_QUOTES \| ENT_HTML5` sur toutes les sorties via `e()` | `includes/helpers.php` |
| **CSRF** | Formulaires forgés depuis un autre domaine | Jeton CSRF unique par session, vérifié sur chaque POST avec `hash_equals` | `includes/helpers.php` |
| **Brute Force** | Tentatives répétées de connexion | Rate limiting fichier par IP (5/min, blocage 5 min) | `includes/rate_limiter.php` |
| **Session Fixation** | Réutilisation d'ID de session pré-auth | `session_regenerate_id(true)` à chaque connexion | `includes/security.php` |
| **Session Hijacking** | Vol de cookie de session | Vérification User-Agent + régénération toutes les 30 min | `includes/security.php` |
| **Clickjacking** | Intégration dans un iframe malveillant | `X-Frame-Options: DENY` + CSP `frame-ancestors 'none'` | `includes/security.php` |
| **MIME Sniffing** | Exécution de fichiers mal typés | `X-Content-Type-Options: nosniff` | `includes/security.php` |
| **CSP Bypass** | Injection de scripts inline | `Content-Security-Policy` avec nonce cryptographique généré par requête | `includes/security.php` |
| **Énumération utilisateurs** | Mesure du temps de réponse | Hash de décoy — `password_verify()` toujours exécuté | `includes/auth.php` |
| **Accès direct aux fichiers** | Navigation vers `config/` ou `includes/` | `.htaccess Require all denied` sur tous les dossiers sensibles | `.htaccess` |
| **Bots et spam** | Soumission automatique de formulaires | Champ honeypot invisible — faux succès silencieux | `includes/helpers.php` |
| **Injection d'en-têtes** | Manipulation des réponses HTTP | `header_remove('X-Powered-By')` + `Permissions-Policy` strict | `includes/security.php` |
| **Mots de passe faibles** | Stockage en clair ou MD5 | `password_hash($pw, PASSWORD_DEFAULT)` (bcrypt) + opportunistic rehash | `includes/auth.php` |
| **Élévation de privilège** | Étudiant accédant aux pages admin | Clés de session disjointes (`admin_id` vs `etudiant_id`) | `includes/auth.php` + `etudiant_auth.php` |
| **Tampering d'identifiants** | POST avec un `id` modifié | Vérification ownership : `WHERE id = ? AND etudiant_id = ?` | `pages/notes_etudiant.php` |
| **Logout via GET** | Lien malicieux qui déconnecte la victime | Endpoint logout accepte uniquement POST + CSRF | `pages/logout.php` |
| **Erreurs verbeuses** | Stack traces exposant la stack technique | `error_log()` interne, message générique côté client | `includes/security.php` (`abort()`) |

<details>
<summary>✅ Liste de contrôle sécurité complète (cliquer pour développer)</summary>

#### Authentification
- [x] Mots de passe hashés avec `password_hash($pw, PASSWORD_DEFAULT)` (bcrypt)
- [x] Vérification timing-safe via `password_verify()`
- [x] Hash de décoy joué pour les utilisateurs inexistants (anti-énumération)
- [x] `password_needs_rehash()` upgrade transparent à la connexion suivante
- [x] Rate limiting 5 tentatives/min/IP, blocage 5 min sur abus
- [x] Audit log de chaque tentative (succès et échec) avec IP + identifiant

#### Sessions
- [x] `session.cookie_httponly = 1`
- [x] `session.cookie_samesite = Strict`
- [x] `session.use_strict_mode = 1`
- [x] `session.use_only_cookies = 1`
- [x] `session.cookie_secure = 1` en production
- [x] `session_regenerate_id(true)` à la connexion
- [x] Régénération périodique toutes les 30 min
- [x] User-Agent pinning (destruction sur changement)

#### CSRF
- [x] Jeton 32 octets en hex stocké en session
- [x] Vérification timing-safe via `hash_equals`
- [x] Renouvellement après chaque opération réussie
- [x] Cookie `SameSite=Strict` comme défense additionnelle

#### Headers HTTP
- [x] `Content-Security-Policy` avec nonce cryptographique
- [x] `X-Frame-Options: DENY`
- [x] `X-Content-Type-Options: nosniff`
- [x] `X-XSS-Protection: 1; mode=block` (legacy)
- [x] `Referrer-Policy: strict-origin-when-cross-origin`
- [x] `Permissions-Policy: geolocation=(), microphone=(), camera=()`
- [x] `header_remove('X-Powered-By')`

#### Données
- [x] Toutes les requêtes en `prepared statements` PDO
- [x] `ATTR_EMULATE_PREPARES = false`
- [x] `ATTR_ERRMODE = ERRMODE_EXCEPTION`
- [x] Charset UTF-8 explicite dans le DSN
- [x] Colonnes `ORDER BY` en allowlist avant interpolation

#### Filesystem
- [x] `.htaccess` à la racine bloque les extensions sensibles
- [x] `.htaccess Require all denied` dans `config/`, `includes/`, `pages/`
- [x] `Options -Indexes` pour empêcher le listing
- [x] `ServerSignature Off`

</details>

---

## 🏗️ Architecture technique

Construire une application sans framework est un **choix pédagogique délibéré**.
Cela force à expliciter chaque décision — routing, autoloading,
rendering, validation, persistance — au lieu de s'appuyer sur des
conventions opaques. Le résultat est une codebase compacte (~3000 lignes
de PHP) où chaque fichier a une responsabilité unique et où n'importe quel
développeur peut tracer une requête de bout en bout en quelques minutes.

### Pattern MVC-inspiré

```
       Request (HTTP)
            │
            ▼
  ┌──────────────────────┐
  │   public/index.php   │  ← Front Controller (routing + auth gate)
  └──────────┬───────────┘
             │
             ▼
  ┌──────────────────────┐
  │    pages/*.php       │  ← Controllers (orchestration par page)
  └──────────┬───────────┘
             │
             ▼
  ┌──────────────────────┐
  │ includes/helpers.php │  ← Business Logic (validation, helpers)
  │ includes/auth.php    │
  │ includes/security.php│
  └──────────┬───────────┘
             │
             ▼
  ┌──────────────────────┐
  │  config/config.php   │  ← Model (PDO singleton)
  └──────────┬───────────┘
             │
             ▼
  ┌──────────────────────┐
  │ includes/header.php  │  ← Views (layout includes)
  │ includes/footer.php  │
  └──────────┬───────────┘
             │
             ▼
       Response (HTML)
```

<details>
<summary>📁 Structure complète du projet (cliquer pour développer)</summary>

```
SecureStudentMS/
├── 📁 config/
│   └── config.php                      # Credentials BDD, constantes globales, getPDO() singleton
├── 📁 database/
│   ├── install_auth.php                # CLI : crée la table admins + seed admin par défaut
│   └── install_student_auth.php        # CLI : étend etudiants + crée modules/inscriptions + seed
├── 📁 includes/
│   ├── auth.php                        # Auth admin : login_admin, logout_admin, require_auth
│   ├── etudiant_auth.php               # Auth étudiant (clés session disjointes)
│   ├── helpers.php                     # e(), redirect(), build_url(), paginate(), sort_icon(), CSRF, honeypot
│   ├── rate_limiter.php                # Rate-limit fichier (sys_get_temp_dir)
│   ├── security.php                    # send_security_headers(), abort(), configure_session()
│   ├── header.php                      # Layout admin : sidebar + topbar
│   ├── footer.php                      # Fermeture du layout admin + scripts
│   ├── etudiant_header.php             # Layout étudiant : top-nav teal
│   └── etudiant_footer.php             # Fermeture du layout étudiant
├── 📁 pages/
│   ├── 📁 etudiant/
│   │   ├── login.php                   # Connexion étudiant (standalone, pas de require_etudiant_auth)
│   │   ├── logout.php                  # Logout POST + CSRF
│   │   ├── dashboard.php               # 4 stat cards + activité récente
│   │   ├── modules.php                 # Modules par semestre + auto-inscription
│   │   └── profil.php                  # Infos + change mot de passe
│   ├── login.php                       # Connexion admin (standalone)
│   ├── logout.php                      # Logout admin POST + CSRF
│   ├── dashboard.php                   # KPIs + 2 charts Chart.js
│   ├── index.php                       # Liste étudiants : search/filter/sort/paginate
│   ├── ajouter.php                     # Créer un étudiant
│   ├── modifier.php                    # Éditer un étudiant
│   ├── supprimer.php                   # Confirmation + DELETE
│   ├── modules.php                     # Liste modules : search/filter/sort/paginate
│   ├── modules_ajouter.php             # Créer + auto-inscription transactionnelle
│   ├── modules_modifier.php            # Éditer + sync inscriptions si filière change
│   ├── modules_supprimer.php           # DELETE en cascade sur inscriptions
│   ├── notes.php                       # Liste étudiants à noter (avec compteurs)
│   ├── notes_etudiant.php              # Saisie bulk avec live preview
│   └── sql.php                         # Console SQL multi-requête
├── 📁 public/                          # Document root Apache
│   ├── index.php                       # Front controller : routing + dispatch
│   ├── 📁 css/
│   │   └── style.css                   # Design system + dark mode + tous les composants
│   └── 📁 js/
│       └── app.js                      # Dark mode, charts, SQL editor, dirty-tracking notes
├── 📁 screenshots/                     # Captures pour le README
├── .htaccess                           # Hardening Apache : -Indexes, deny dotfiles, etc.
├── README.md
└── index.php                           # Redirection racine vers public/
```

</details>

### Décisions d'architecture clés

1. **Single entry point (`public/index.php`)** — toutes les requêtes
   passent par un contrôleur frontal qui valide la route contre une
   whitelist explicite (`ALLOWED_PAGES`). Conséquence : aucune URL ne peut
   pointer vers un fichier non répertorié, et les chemins de pages restent
   invisibles depuis l'extérieur.

2. **Singleton PDO (`getPDO()`)** — une seule connexion par requête,
   instanciée à la demande, configurée avec `ERRMODE_EXCEPTION` et
   `EMULATE_PREPARES = false`. Évite la multiplication des handles et
   garantit que toute erreur SQL est *fail-loud* côté serveur.

3. **PRG (Post / Redirect / Get)** — chaque écriture redirige vers une URL
   GET avec un flag de succès (`?ajout=ok`). Refresh = pas de re-soumission,
   bouton retour = pas de pop-up navigateur, et les flash messages restent
   prévisibles.

4. **CSP nonce par requête** — chaque réponse génère un nonce
   cryptographique de 16 octets ; toute balise `<script>` doit le porter.
   Conséquence : aucun script inline injecté ne peut s'exécuter, même si
   l'échappement venait à tomber sur une page.

5. **Séparation des rôles par namespace de session** — `admin_id` et
   `etudiant_id` sont des clés distinctes. `require_auth()` vérifie la
   première, `require_etudiant_auth()` la seconde. Un étudiant ne peut
   *physiquement* pas accéder à un endpoint admin, même s'il connaît
   l'URL — sa session n'a pas la clé nécessaire.

6. **Rate limiting fichier** — un fichier JSON par tuple (action, IP)
   stocke les tentatives dans `sys_get_temp_dir()`. Pas de Redis, pas de
   cache externe, fonctionne sur n'importe quel hébergement mutualisé. La
   fenêtre est glissante (filter `array_filter` à chaque hit).

---

## 🛠️ Stack technique

| Catégorie | Technologie | Version | Usage |
| --- | --- | :---: | --- |
| **Backend** | PHP | 8.2 | Logique serveur, types stricts (`declare(strict_types=1)`) |
| **Base de données** | MySQL | 8.0 | Stockage relationnel, contraintes FK, ENUM |
| **Accès BDD** | PDO | natif | Requêtes préparées, transactions, ERRMODE_EXCEPTION |
| **Framework CSS** | Bootstrap | 5.3 | Grid, composants, utilitaires |
| **Design system** | CSS custom | — | Tokens (variables), dark mode 3 couches, animations |
| **Charts** | Chart.js | 4.4 | Bar + line charts, thème dynamique via `themeChanged` event |
| **Frontend JS** | Vanilla JS | ES2017+ | Aucune dépendance, IIFE pattern, CSP-safe |
| **Icônes** | Bootstrap Icons | 1.11 | Iconographie cohérente sur les deux espaces |
| **Typographie** | Inter (Google Fonts) | — | Variable font, lisible, neutre |
| **Mono font** | JetBrains Mono | — | Code blocks, console SQL, badges techniques |
| **Serveur web** | Apache | 2.4 | XAMPP, `.htaccess` pour le hardening |

---

## 🗄️ Modèle de données

Le schéma est volontairement **minimal mais relationnel** : quatre tables,
deux relations many-to-one, une table de jointure portant des données
métier (note + statut). Chaque table a sa propre clé primaire
auto-incrémentée et au moins un index utile.

### Diagramme entité-relation

```
   ┌────────────────────┐
   │      admins        │   ← gère l'ensemble du système
   ├────────────────────┤
   │ id (PK)            │
   │ username (UQ)      │
   │ password_hash      │
   │ full_name          │
   │ last_login         │
   │ created_at         │
   └────────────────────┘

   ┌────────────────────┐                         ┌────────────────────┐
   │     etudiants      │                         │      modules       │
   ├────────────────────┤                         ├────────────────────┤
   │ id (PK)            │   1 ┐             ┌ 1   │ id (PK)            │
   │ nom                │     │             │     │ code (UQ)          │
   │ prenom             │     │             │     │ nom                │
   │ email (UQ)         │     │             │     │ description        │
   │ password_hash      │     │             │     │ credits            │
   │ is_active          │     │             │     │ semestre           │
   │ last_login         │     │             │     │ filiere            │
   │ filieres           │     │             │     │ created_at         │
   │ created_at         │     │             │     └────────────────────┘
   └────────────────────┘     │             │
                              │             │
                              ▼ N         N ▼
                         ┌─────────────────────────┐
                         │      inscriptions       │
                         ├─────────────────────────┤
                         │ id (PK)                 │
                         │ etudiant_id (FK CASCADE)│
                         │ module_id   (FK CASCADE)│
                         │ note (DECIMAL 4,2 NULL) │
                         │ statut (ENUM)           │
                         │ inscribed_at            │
                         │ UNIQUE(etudiant, module)│
                         └─────────────────────────┘
```

### Table `admins`

| Colonne | Type | Contrainte | Description |
| --- | --- | --- | --- |
| `id` | INT | PK, AUTO_INCREMENT | Identifiant unique |
| `username` | VARCHAR(60) | UNIQUE, NOT NULL | Nom d'utilisateur |
| `password_hash` | VARCHAR(255) | NOT NULL | Hash bcrypt |
| `full_name` | VARCHAR(100) | NOT NULL | Nom complet affiché |
| `last_login` | DATETIME | NULL | Mise à jour à chaque connexion |
| `created_at` | DATETIME | NOT NULL | Horodatage de création |

### Table `etudiants`

| Colonne | Type | Contrainte | Description |
| --- | --- | --- | --- |
| `id` | INT | PK, AUTO_INCREMENT | Identifiant unique |
| `nom` | VARCHAR(100) | NOT NULL | Nom de famille |
| `prenom` | VARCHAR(100) | NOT NULL | Prénom |
| `email` | VARCHAR(150) | UNIQUE, NOT NULL | Identifiant de connexion étudiant |
| `password_hash` | VARCHAR(255) | NULL | Hash bcrypt (NULL = compte non activé) |
| `is_active` | TINYINT(1) | DEFAULT 1 | Désactivation sans suppression |
| `last_login` | DATETIME | NULL | Dernière connexion étudiant |
| `filieres` | VARCHAR(100) | NOT NULL | Filière de rattachement |
| `created_at` | DATETIME | NOT NULL | Horodatage d'inscription |

### Table `modules`

| Colonne | Type | Contrainte | Description |
| --- | --- | --- | --- |
| `id` | INT | PK, AUTO_INCREMENT | Identifiant unique |
| `code` | VARCHAR(20) | UNIQUE, NOT NULL | Code module (ex. `INF101`) |
| `nom` | VARCHAR(150) | NOT NULL | Intitulé complet |
| `description` | TEXT | NULL | Description pédagogique |
| `credits` | TINYINT | NOT NULL | Crédits ECTS (1-10) |
| `semestre` | TINYINT | NOT NULL | Semestre (1-6), indexé |
| `filiere` | VARCHAR(100) | NOT NULL | Filière, indexée |
| `created_at` | DATETIME | NOT NULL | Horodatage de création |

### Table `inscriptions`

| Colonne | Type | Contrainte | Description |
| --- | --- | --- | --- |
| `id` | INT | PK, AUTO_INCREMENT | Identifiant unique |
| `etudiant_id` | INT | FK → `etudiants(id)` ON DELETE CASCADE | Étudiant inscrit |
| `module_id` | INT | FK → `modules(id)` ON DELETE CASCADE | Module suivi |
| `note` | DECIMAL(4,2) | NULL | Note sur 20, NULL = non encore notée |
| `statut` | ENUM | `'inscrit'` \| `'valide'` \| `'echoue'` | Auto-dérivé de la note |
| `inscribed_at` | DATETIME | NOT NULL | Horodatage d'inscription |
| `UNIQUE` | (etudiant_id, module_id) | — | Empêche le doublon d'inscription |

---

## 🛣️ Routes

Toutes les routes sont validées par le contrôleur frontal contre une
**whitelist** stricte. Toute valeur hors-liste retombe sur `?page=index`,
et les routes nestées (`etudiant/*`) sont autorisées explicitement —
il n'y a aucun risque de path traversal via la route.

### Scope admin

| Route | Page | Rôle |
| --- | --- | --- |
| `?page=login` | `login.php` | Connexion admin (publique) |
| `?page=logout` | `logout.php` | Déconnexion (POST + CSRF) |
| `?page=dashboard` | `dashboard.php` | KPIs + charts |
| `?page=index` | `index.php` | Liste étudiants |
| `?page=ajouter` | `ajouter.php` | Ajouter un étudiant |
| `?page=modifier&id=N` | `modifier.php` | Éditer un étudiant |
| `?page=supprimer&id=N` | `supprimer.php` | Supprimer un étudiant |
| `?page=modules` | `modules.php` | Liste modules + filtres |
| `?page=modules_ajouter` | `modules_ajouter.php` | Créer un module + auto-inscription |
| `?page=modules_modifier&id=N` | `modules_modifier.php` | Éditer un module + sync inscriptions |
| `?page=modules_supprimer&id=N` | `modules_supprimer.php` | Supprimer un module |
| `?page=notes` | `notes.php` | Liste étudiants à noter |
| `?page=notes_etudiant&id=N` | `notes_etudiant.php` | Saisie des notes |
| `?page=sql` | `sql.php` | Console SQL avec confirmation |

### Scope étudiant

| Route | Page | Rôle |
| --- | --- | --- |
| `?page=etudiant/login` | `etudiant/login.php` | Connexion étudiant (publique) |
| `?page=etudiant/logout` | `etudiant/logout.php` | Déconnexion (POST + CSRF) |
| `?page=etudiant/dashboard` | `etudiant/dashboard.php` | Stats personnelles |
| `?page=etudiant/modules` | `etudiant/modules.php` | Modules de sa filière |
| `?page=etudiant/profil` | `etudiant/profil.php` | Profil + change mot de passe |

---

## 🚀 Installation et démarrage

### Prérequis système

| Requis | Version minimale | Recommandé |
| --- | :---: | :---: |
| **PHP** | 8.0 | **8.2** |
| **MySQL** | 5.7 | **8.0** |
| **Apache** | 2.4 | **2.4** |
| **Extensions PHP** | `pdo_mysql`, `mbstring`, `json` | toutes activées |

<details>
<summary>📦 Procédure d'installation pas-à-pas (cliquer pour développer)</summary>

#### Étape 1 — Cloner le projet

```bash
cd C:\xampp\htdocs
git clone https://github.com/KhalidMORJANE/SecureStudentMS.git
cd SecureStudentMS
```

#### Étape 2 — Démarrer XAMPP

Ouvrez le **XAMPP Control Panel** et démarrez les services :

- ✅ **Apache** (icône → vert)
- ✅ **MySQL** (icône → vert)

Vérifiez que <http://localhost> renvoie bien la page d'accueil XAMPP.

#### Étape 3 — Créer la base de données

Via la ligne de commande :

```bash
mysql -u root -e "CREATE DATABASE gestion_etudiants
                  CHARACTER SET utf8mb4
                  COLLATE utf8mb4_unicode_ci;"
```

Ou via phpMyAdmin (<http://localhost/phpmyadmin>) → onglet **SQL** :

```sql
CREATE DATABASE gestion_etudiants
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

#### Étape 4 — Importer le schéma initial des étudiants

Si vous avez un fichier `schema.sql` (export phpMyAdmin) :

```bash
mysql -u root gestion_etudiants < schema.sql
```

Sinon, créez la table `etudiants` directement (les autres tables seront
créées par les installeurs CLI à l'étape 6) :

```sql
CREATE TABLE etudiants (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nom       VARCHAR(100) NOT NULL,
    prenom    VARCHAR(100) NOT NULL,
    email     VARCHAR(150) NOT NULL UNIQUE,
    filieres  VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### Étape 5 — Configurer l'application

Ouvrez `config/config.php` et adaptez si nécessaire :

| Constante | Valeur par défaut | Description |
| --- | --- | --- |
| `DB_HOST` | `localhost` | Hôte MySQL |
| `DB_NAME` | `gestion_etudiants` | Nom de la base |
| `DB_USER` | `root` | Utilisateur MySQL |
| `DB_PASS` | (vide) | Mot de passe MySQL (vide en local XAMPP) |
| `DB_CHARSET` | `utf8mb4` | Charset de connexion |
| `APP_NAME` | `Gestion des étudiants` | Nom affiché en haut |
| `APP_ENV` | `development` | `development` ou `production` |
| `BASE_URL` | `/SecureStudentMS/public/` | Préfixe d'URL |

#### Étape 6 — Installer les données initiales

```bash
php database\install_auth.php
php database\install_student_auth.php
```

Le premier script crée la table `admins` et un compte par défaut. Le
second étend la table `etudiants` (colonnes auth), crée `modules` +
`inscriptions`, et alimente le catalogue avec 10 modules d'exemple.

#### Étape 7 — Lancer l'application

Ouvrez votre navigateur sur :

```
http://localhost/SecureStudentMS/public/
```

Vous serez redirigé vers la page de connexion admin si non authentifié.

#### Étape 8 — Identifiants par défaut

| Rôle | Identifiant | Mot de passe |
| --- | --- | --- |
| 👨‍💼 **Administrateur** | `admin` | `admin123` |
| 🎓 **Étudiant** | *email d'un étudiant existant* | `etudiant123` |

> ⚠️ **IMPORTANT** — Changez ces mots de passe immédiatement après la
> première connexion. Le compte étudiant utilise le mot de passe par
> défaut pour tous les étudiants existants au moment de l'installation —
> communiquez-leur individuellement et faites-les changer dès la
> première connexion.

</details>

---

## 🎨 Interface utilisateur

L'UI repose sur une **double identité visuelle** : palette indigo pour
l'administration, palette teal pour l'étudiant. Cette séparation n'est pas
décorative — elle rend le rôle actuel **immédiatement reconnaissable** et
empêche la confusion entre les deux espaces. Le design s'inspire de GitHub
pour le mode sombre (système de profondeur à trois couches) et privilégie
les transitions fluides à 300 ms (avec respect de `prefers-reduced-motion`).

### Palettes de couleurs

| Palette Admin (Indigo) | Palette Étudiant (Teal) | Dark Mode (GitHub) |
| --- | --- | --- |
| `#4f46e5` Primary | `#14b8a6` Primary | `#0d1117` Background base |
| `#ede9fe` Light | `#ccfbf1` Light | `#161b22` Surface |
| `#3730a3` Dark | `#0f766e` Dark | `#21262d` Elevated |
| `#1e1b4b` Sidebar | `#5eead4` Accent dark | `#30363d` Border |

### Caractéristiques UI clés

- **Sidebar admin** vs **top-nav horizontale étudiante** — deux mises en
  page différentes pour deux contextes d'usage différents
- **Live search** sur la liste étudiants avec **debounce 500 ms** —
  pas de spam de requêtes pendant la frappe
- **Tri par colonne** avec indicateur visuel direction — clic sur l'entête
  bascule asc/desc
- **Pagination intelligente** avec ellipsis (`first … current±2 … last`) —
  reste lisible même avec 50 pages
- **Toast notifications** déclenchés par flag GET (`?ajout=ok`) — auto-dismiss
  4,5 s, fermeture manuelle possible
- **Console SQL** avec **gouttière de numéros de ligne** + auto-grow +
  raccourci `Ctrl+Enter` pour exécuter
- **Live grade preview** sur la saisie des notes — le badge de statut
  change de couleur à chaque frappe
- **Sticky save bar** sur la grille de notes — le bouton "Enregistrer"
  reste visible au scroll
- **Dark mode pre-paint** — la classe `.dark-mode` est appliquée *avant*
  le premier rendu pour éviter le flash de thème clair
- **Transition smoothly** — la classe `.theme-transitioning` est ajoutée
  pendant 300 ms, puis retirée, pour interpoler proprement les couleurs

---

## 📊 Fonctionnalités avancées

### 💻 Console SQL Runner

La console SQL est une vraie surface de développement. Elle accepte
**toutes** les requêtes (SELECT, INSERT, UPDATE, DELETE, DDL) — en
contrepartie elle impose une **modal de confirmation** sur les
opérations destructives (`DELETE`, `DROP`, `TRUNCATE`, `ALTER`) avec un
aperçu du SQL en palette Catppuccin Mocha. Chaque requête exécutée est
**journalisée** via `error_log()` au format `SQL Runner [{IP}]: {200 chars}`
pour fournir une piste d'audit complète. Les résultats SELECT sont
affichés dans un tableau scrollable avec **export CSV** côté client
(via `Blob` + `URL.createObjectURL`, sans script inline).

### 📈 Dashboard Statistiques

Quatre KPI cards en haut (étudiants total, filières, filière la plus
populaire, dernier inscrit), suivies de deux graphiques **Chart.js** —
un bar chart de répartition par filière et un line chart des
inscriptions sur 12 mois glissants. Les couleurs sont **theme-aware** :
une fonction `applyChartDefaults()` détecte la classe `.dark-mode` sur
`<body>` et reconstruit chaque chart au déclenchement de l'événement
`themeChanged`. Les données sont passées du serveur au client par
**attributs `data-*`** sur un `<div>` caché — zéro inline JS, parfait
compatible CSP.

### 🎯 Système de notation

L'admin saisit les notes de manière **bulk** (toutes les inscriptions
d'un étudiant sur une seule page) avec un aperçu live du statut : tape
`15` → badge vert "Validé" instantané, tape `8` → badge rouge "Échoué",
efface → bleu "Inscrit". Le statut est dérivé selon la règle
**`note ≥ 10 → validé`, `note < 10 → échoué`, `vide → inscrit`** — la
même règle est implémentée côté JS (preview) et côté PHP
(`statut_for_note()`). La sécurité repose sur une vérification
**ownership par ligne** (`WHERE id = ? AND etudiant_id = ?`) qui rend
impossible la modification d'une inscription appartenant à un autre
étudiant via un POST forgé. Un avertissement **`beforeunload`** se
déclenche si l'admin tente de quitter avec des modifications non
sauvegardées, et le listener est nettoyé sur soumission pour ne pas
gêner l'enregistrement.

---

## 📸 Captures d'écran

| Page d'accueil | Ajout d'étudiant |
| :---: | :---: |
| ![Accueil](screenshots/home.PNG) | ![Ajouter](screenshots/add.PNG) |

> Captures du dashboard, de la console SQL, de l'espace étudiant et du
> dark mode disponibles dans `/screenshots`.

---

## 🔮 Améliorations futures

| Fonctionnalité | Priorité | Description |
| --- | :---: | --- |
| **Import CSV des notes** | 🔴 Haute | Upload d'un fichier CSV (`code, email, note`) pour saisie en masse en fin de semestre |
| **Export PDF** | 🔴 Haute | Relevé de notes PDF par étudiant (FPDF ou TCPDF) avec en-tête établissement et signature |
| **Notifications email** | 🟡 Moyenne | Email automatique quand une note est saisie (PHPMailer + SMTP) |
| **API REST** | 🟡 Moyenne | Endpoints JSON pour intégration mobile / autres systèmes (auth via token bearer) |
| **Authentification 2FA** | 🟡 Moyenne | TOTP pour les admins (Google Authenticator compatible) |
| **Gestion multi-admin** | 🟢 Basse | Rôles et permissions granulaires (super-admin, prof, secrétariat) |
| **Mode hors-ligne (PWA)** | 🟢 Basse | Service worker pour consultation offline du tableau de bord étudiant |
| **Logs d'activité** | 🟢 Basse | Journal complet des actions admin avec UI de consultation et filtres |

---

## 🤝 Contribution

Les contributions sont les bienvenues. Le projet suit une procédure
standard *fork → branch → PR* avec des conventions précises pour garder
l'historique lisible et la base de code cohérente.

### Procédure

```bash
# 1. Forker le dépôt sur GitHub

# 2. Cloner votre fork
git clone https://github.com/<votre-user>/SecureStudentMS.git
cd SecureStudentMS

# 3. Créer une branche descriptive
git checkout -b feature/import-csv-notes

# 4. Commit avec un message clair (Conventional Commits)
git commit -m "feat(notes): add CSV bulk import for end-of-semester grading"

# 5. Push
git push origin feature/import-csv-notes

# 6. Ouvrir une Pull Request avec description détaillée
```

### Convention de nommage des branches

| Préfixe | Usage |
| --- | --- |
| `feature/` | Nouvelle fonctionnalité |
| `fix/` | Correction de bug |
| `refactor/` | Restructuration sans changement fonctionnel |
| `docs/` | Documentation seule |
| `security/` | Patch de sécurité |

### Convention de message de commit

Suivre [Conventional Commits](https://www.conventionalcommits.org/) :

- `feat(scope): ...` — nouvelle fonctionnalité
- `fix(scope): ...` — correction
- `docs(scope): ...` — documentation
- `refactor(scope): ...` — restructuration
- `security(scope): ...` — patch de sécurité

### Checklist de PR

- [ ] `declare(strict_types=1)` sur tout fichier PHP nouveau
- [ ] Indentation 4 espaces, UTF-8 sans BOM
- [ ] Toute requête DB en `try/catch` avec `error_log` + `abort(500)`
- [ ] Toute sortie HTML passe par `e()`
- [ ] CSRF + rate limit sur tout nouvel endpoint d'écriture
- [ ] Zéro JS inline (CSP)
- [ ] Tests manuels documentés dans la description de la PR
- [ ] Captures d'écran si UI

---

## 📄 Licence

Distribué sous licence **MIT**.

<details>
<summary>Texte complet de la licence (cliquer pour développer)</summary>

```
MIT License

Copyright (c) 2025 Khalid MORJANE

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

</details>

---

## 👨‍💻 Auteur

<div align="center">

### **Khalid MORJANE**

*Étudiant en informatique — Projet de fin d'études*
**2025**

[![GitHub](https://img.shields.io/badge/GitHub-Khalid_MORJANE-181717?logo=github)](https://github.com/)

> *"Build the project you wish existed when you were learning."*

</div>

---

<div align="center">

**⭐ Si ce projet vous a été utile, n'hésitez pas à lui donner une étoile !**

<br>

Made with ❤️ and a lot of ☕ by Khalid MORJANE

</div>
