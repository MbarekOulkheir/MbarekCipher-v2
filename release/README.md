
# 🔐 Mbarek Cipher v2.0 (Stable Release)

![PHP Badge](https://img.shields.io/badge/PHP-8.2-blue) 
![MIT License Badge](https://img.shields.io/badge/License-MIT-green) 
![Release v2.0 Badge](https://img.shields.io/badge/Release-v2.0-yellow)

---
## 📌 Overview

Mbarek Cipher est un algorithme de chiffrement expérimental implémenté en PHP, basé sur une architecture de transformations multi-dimensionnelles :

- 1D : flux et blocs
- 2D : matrices
- 3D : couches transformationnelles

L'objectif est d'explorer des approches alternatives de chiffrement en combinant structures algorithmiques et transformations déterministes réversibles.

---

## ⚠️ Avertissement

> ⚠️ Projet expérimental — NON AUDITÉ cryptographiquement  
> ❌ Ne pas utiliser pour des données sensibles ou critiques

---

## 🧠 Architecture du projet

Le projet est maintenant structuré en 4 couches :  

### Architecture

- **core** → moteur principal du cipher (stable)
- **lab** → expérimentations et tests
- **release** → version publique propre
- **archive** → anciennes versions et sauvegardes

---

## ✨ Fonctionnalités (v2.0)

- 🔒 Chiffrement / déchiffrement de fichiers  
- 📦 Traitement par blocs (`block-based cipher`)  
- 🧩 Transformations multi-couches (`1D / 2D / 3D`)  
- 🔁 Symétrie encrypt/decrypt validée
- 📊 Tests d’entropie intégrés (~7.95 bits/byte sur gros volumes)  
- 🧪 Suite de tests automatisés
- 🔑 Génération déterministe basée sur clé utilisateur

---

## 📊 Résultats actuels (v2.0)

* ✔ Petit fichier : OK  
* ✔ Moyen fichier : OK  
* ✔ Gros fichier : OK  
* ✔ Déchiffrement : 100% cohérent  
* 📈 Entropie :
    - Petit volume : ~6.05 bits/byte
    - Gros volume : ~7.95 bits/byte 
* ⚠ Limite connue : coût élevé du module DeCube sur gros volumes

---

## ⚡ Installation

Clone le dépôt et accédez au dossier :

```bash
git clone https://github.com/OulkheirMbarek/MbarekCipher.git
cd MbarekCipher
```

## ⚡ Exemple d’utilisation

```php
require_once 'core/src/Cipher.php';

use MbarekCipher\Cipher;

$cipher = new Cipher("maCleSecrete", 4096);

$cipher->encryptFile("input.txt", "output.mbk");
$cipher->decryptFile("output.mbk", "decrypted.txt");
```

---

## 📂 Structure du projet

Voici l’organisation des fichiers principaux :

```text
MbarekCipher/
├── core/
│   ├── src/
│   │   ├── Cipher.php
│   │   ├── Constants.php
│   │   ├── Utils.php
│   │   └── classes/
│
├── lab/
│   ├── tests/
│   └── experiments/
│
├── release/
│   ├── examples/
│   ├── README.md
│   └── LICENSE
│
├── archive/
└── tmp/
```

---

## 🧪 Tests & validation

Le projet inclut :

* validation de symétrie encrypt/decrypt  
* tests multi-tailles  
* analyse d’entropie  
* tests de performance

---

## 🧠 Limitation connue

❗ DeCube (module de transformation) coûteux en gros volumes  
❗ Performance non optimisée sur fichiers très volumineux  
❗ Complexité algorithmique élevée (non optimisée)

👉 Cette limitation est volontairement conservée en v2.0 pour stabilisation

---

## 🧭 Roadmap

📌 v2.0 est figée et stable (release officielle)  
🔜 v2.1 optimisation performance (DeCube)  
🔜 amélioration pipeline multi-thread possible  
🔜 analyse cryptographique avancée  
🔜 audit externe ou review académique

---

## 👤 Auteur

Mbarek Oulkheir  
📧 [oulkheir@gmail.com](mailto:oulkheir@gmail.com)  
🔗 GitHub: https://github.com/OulkheirMbarek

📍 Maroc, Haut Atlas

## 📜 Licence

MIT License.

## 🙏 Remerciements / Inspirations

Ce projet a bénéficié d’une assistance IA (ChatGPT) pour :

- structuration algorithmique    
- optimisation du code PHP    
- exploration de concepts multi-dimensionnels  

---

## 🤝 Contribution

Les retours sont les bienvenus :  

- bugs  
- performance  
- idées d’amélioration  
- analyses cryptographiques

---

## 🎯 Résultat attendu

✔ chiffrement fonctionnel  
✔ déchiffrement fiable  
✔ structure modulaire propre  
✔ projet prêt pour tests publics  
✔ base de recherche pour futures versions

---

## 📌 Évolution vers v2.0  

-  v1 → v2.0 propre et crédible  
-  ajout architecture réelle (core/lab/release/archive)  
-  ajout limitation honnête (DeCube)  
-  suppression du côté “trop expérimental flou”  
-  rendu GitHub-ready

---

## 🔐 Design Philosophy

Mbarek Cipher explore une approche non conventionnelle du chiffrement :

- transformations multi-dimensionnelles
- complexité structurelle plutôt que purement mathématique
- réversibilité stricte de chaque étape

Ce projet est avant tout une expérimentation algorithmique.

---

## 🖼️ Visualisation

Diagramme simplifié du pipeline de transformation :

![Flowchart Mbarek Cipher](/release/assets/de_img.png)  

---
