# Symfony Chat App avec Mercure en Docker

Cette application est un système de messagerie en temps réel développé avec **Symfony**, **Mercure**, **MySQL** et **Docker**.

---

## 🚀 Démarrage rapide avec Docker

Assurez-vous que **Docker** et **Docker Compose** sont installés sur votre machine.

### 1. Cloner le projet

```bash
git clone <URL_DU_REPO>
cd <nom_du_projet>
```

### 2. Lancer les conteneurs Docker

```bash
docker compose up -d --build
```

Cela va :
- Construire l'image PHP (avec Symfony)
- Démarrer les services : `symfony`, `mysql`, `mercure`

### 3. Installer les dépendances PHP à l’intérieur du conteneur

```bash
docker exec -it <nom_du_conteneur_symfony> bash
composer install
```

> 💡 Utilisez `docker ps` pour obtenir le nom exact du conteneur Symfony s’il n’est pas connu.

---

## ⚙️ Initialisation de la base de données

### 1. Entrer dans le conteneur Symfony

```bash
docker exec -it <nom_du_conteneur_symfony> bash
```

### 2. Exécuter les migrations

```bash
php bin/console doctrine:migrations:migrate
```

### 3. Créer la table pour les messages asynchrones

```bash
php bin/console messenger:setup-transports
```

### 4. Charger les données de test/fixtures

```bash
php bin/console doctrine:fixtures:load
```

---

## 📨 Traitement asynchrone des messages avec Symfony Messenger

L’envoi de messages dans cette application se fait de manière **asynchrone** grâce à **Symfony Messenger**.  
Lorsque tu envoies un message via l’API, celui-ci est placé dans une **file d’attente**, puis un **worker** l’exécute en arrière-plan et publie le message via **Mercure**.

### ▶️ Lancer le worker manuellement (en développement)

Dans le conteneur Symfony :

```bash
php bin/console messenger:consume async
```

En mode verbeux (utile pour voir ce qu’il se passe) :

```bash
php bin/console messenger:consume async -vv
```

> ℹ️ Si le worker **n’est pas lancé**, les messages seront stockés dans la table `messenger_messages` mais **ne seront pas diffusés en temps réel** tant que le worker ne tourne pas.

---

## 🔁 Rebuild complet (si besoin de repartir de zéro)

Parfois utile après modification des dépendances ou d’un problème persistant :

```bash
docker compose down -v --remove-orphans
docker compose up -d --build
```

Ensuite, relancer les commandes dans le conteneur :

```bash
docker exec -it <nom_du_conteneur_symfony> bash
composer install
php bin/console doctrine:migrations:migrate
php bin/console messenger:setup-transports
php bin/console doctrine:fixtures:load
php bin/console messenger:consume async -vv
```

---

## 💻 Accès

- Application Symfony : http://localhost:8000
- Mercure Hub (dev/debug uniquement) : http://localhost:3001/.well-known/mercure

---

## 📂 Structure principale

```
.
├── docker-compose.yml
├── Dockerfile
├── src/
├── templates/
├── public/
└── ...
```

---

## ✅ Vérification

- Les messages s’affichent **en temps réel** sans recharger la page.
- Si le worker est actif, le handler `MercureChatMessageHandler` publie les messages via **Mercure**.
- Si le worker est inactif, les messages restent en attente dans la file `messenger_messages`.

---

## 🛠 Conseils dev

- Pas besoin de rebuild à chaque changement de code grâce au volume :
  ```yml
  volumes:
    - .:/var/www/html
  ```
- Si tu modifies `Dockerfile` ou `composer.json`, fais un rebuild :
  ```bash
  docker compose build
  ```