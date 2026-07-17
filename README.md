# 🛫 Réseau Mobile — Aéroport International Léopold Sédar Senghor (DKR)
## Dakar-Yoff, Sénégal

Application PHP de visualisation et gestion du réseau mobile de l'aéroport.

---

## 📁 Structure des fichiers

```
airport_network/
├── index.php              ← Vue utilisateur (dashboard principal)
├── network.xml            ← Données réseau (source de vérité)
├── css/
│   └── style.css          ← Feuille de style globale
├── js/
│   └── app.js             ← Canvas topologie + interactions UI
├── includes/
│   └── xml_parser.php     ← Fonctions utilitaires XML
├── admin/
│   └── index.php          ← Interface administration
├── backups/               ← Sauvegardes auto du XML (créé auto)
└── README.md
```

---

## 🚀 Installation

### Prérequis
- PHP 8.0+
- Extension `simplexml` activée (par défaut)
- Extension `dom` activée (par défaut)
- Serveur web : Apache, Nginx, ou `php -S localhost:8080`

### Lancement rapide
```bash
cd airport_network
php -S localhost:8080
# Ouvrir http://localhost:8080
```

### Avec Apache
```apache
<VirtualHost *:80>
  DocumentRoot /path/to/airport_network
  DirectoryIndex index.php
</VirtualHost>
```

---

## 🔐 Accès Admin

| URL | Description |
|-----|-------------|
| `http://localhost:8080/` | Dashboard utilisateur |
| `http://localhost:8080/admin/` | Panel administration |

**Identifiants admin par défaut:**
- Login: `admin`
- Mot de passe: `DKR_Airport_2024!`

> ⚠️ **Changer le mot de passe en production** dans `admin/index.php` (ligne `define('ADMIN_PASS', ...)`).

---

## 📡 Données réseau

Le fichier `network.xml` contient les données réelles du réseau :

- **20 équipements** répartis sur 4 couches réseau
- **20 liens** inter-équipements
- **8 zones de couverture** 
- **3 opérateurs** (Orange, Free, Sonatel)
- **5 technologies** (2G à 5G NSA)

### Couches réseau modélisées
1. **Cœur de Réseau** : EPC, MSC, HSS, PCRF
2. **RAN** : BBU (x3), RRU Macro (x5), DAS Indoor (x2), Small Cell 5G
3. **Transport** : Routeur ASR9906, Switch EX9214, ODF, Lien Microonde
4. **IoT/Spéciaux** : Gateway IoT, Répéteurs

---

## 🖥️ Fonctionnalités

### Vue Utilisateur
- 📊 **Dashboard KPI** : 10 indicateurs clés de performance
- 📦 **Équipements** : Catalogue filtrable avec specs techniques complètes
- 🗺️ **Topologie interactive** : Canvas zoomable avec animation du trafic
- 🔗 **Liens réseau** : Tableau de tous les liens avec types et débits
- 📶 **Zones de couverture** : Signal, technologies, opérateurs par zone
- 📄 **Description** : Informations complètes sur la zone et les technologies

### Vue Admin
- ✏️ **Éditeur XML** : Éditeur plein écran avec numéros de ligne
- ✅ **Validation temps réel** : Validation XML côté client
- 🔧 **Formatage automatique** : Indentation propre
- 💾 **Sauvegarde avec backup** : Backup automatique daté
- ↩ **Restauration** : Retour à une version précédente
- ⬇ **Export** : Téléchargement du XML

---

## 🏗️ Architecture technique

```
                    ┌─────────────────┐
                    │   index.php     │  Vue utilisateur
                    │   admin/        │  Vue admin
                    └────────┬────────┘
                             │
                    ┌────────▼────────┐
                    │ xml_parser.php  │  Parsing & helpers
                    └────────┬────────┘
                             │
                    ┌────────▼────────┐
                    │  network.xml    │  Source de données
                    └─────────────────┘
```

---

## 📝 Modifier le réseau

Éditez `network.xml` via l'admin ou directement. Structure d'un équipement :

```xml
<equipement id="UNIQUE_ID" type="TYPE" nom="Nom complet"
  fabricant="Ericsson" modele="XXXXX"
  localisation="Description localisation"
  adresse_ip="10.x.x.x" statut="actif"
  operateur_ref="OP1"
  latitude="14.xxxx" longitude="-17.xxxx">
  <description>Description détaillée...</description>
  <specs>
    <spec nom="Clé" valeur="Valeur"/>
  </specs>
  <couverture>
    <zone>Nom de la zone couverte</zone>
  </couverture>
</equipement>
```

Types disponibles : `EPC`, `MSC`, `HSS`, `PCRF`, `BBU`, `RRU`, `RRU_Indoor`, `Small_Cell`, `Routeur_Agregation`, `Switch_Aggregation`, `ODF`, `Lien_Microonde`, `Gateway_IoT`, `Répéteur`

---

*Projet réalisé dans le cadre de la modélisation du réseau mobile de l'Aéroport International Léopold Sédar Senghor — Dakar, Yoff, Sénégal.*
