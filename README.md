# DoliStockMove

Module Dolibarr pour la **saisie rapide de mouvements de stock** liés aux propositions commerciales (devis), optimisé mobile/tablette.

## Fonctionnalités

- **Formulaire multi-produits** : saisie de sorties/retours sur plusieurs produits en un seul formulaire
- **Liaison au devis** : chaque mouvement est rattaché à une proposition commerciale via un extrafield
- **Onglet sur le devis** : récapitulatif des produits sortis/retournés par chantier (proposition)
- **Création rapide de produit** : modale intégrée (référence + libellé + description)
- **Affichage du stock actuel** : badge de stock mis à jour en temps réel lors de la sélection du produit
- **Interface mobile/tablette** : CSS responsive, boutons touch-friendly
- **Liste des mouvements** : filtrable par devis, produit, plage de dates, triable par toutes colonnes
- **Droits natifs Dolibarr** : `stock > lire` pour consulter, `stock > mouvement > creer` pour créer/supprimer

## Prérequis

- Dolibarr **20.0+**
- PHP **7.4+**
- Modules Dolibarr actifs : **Stock**, **Propositions commerciales**

## Installation

1. Copier le dossier `dolistockmove/` dans `htdocs/custom/` (ou dans `htdocs/` directement)
2. Aller dans **Accueil > Configuration > Modules/Applications**
3. Activer **DoliStockMove** (catégorie « Stock »)
4. Configurer dans **Admin > DoliStockMove > Paramètres** : entrepôt par défaut

## Structure du module

```
dolistockmove/
├── core/modules/modDolistockmove.class.php   # Descripteur du module
├── dolistockmoveindex.php                    # Page d'accueil (10 derniers mouvements)
├── stockmove_card.php                        # Formulaire de saisie multi-produits
├── stockmove_list.php                        # Liste filtrée des mouvements
├── propal_stockmovements.php                 # Onglet sur le devis
├── ajax/
│   ├── product_info.php                      # Autocomplete produit + stock + devis
│   └── create_product.php                    # Création rapide produit
├── class/actions_dolistockmove.class.php     # Hooks (extensible)
├── admin/
│   ├── setup.php                             # Paramètres
│   └── about.php                             # À propos
├── css/dolistockmove.css                     # Styles responsive
├── js/dolistockmove.js                       # Interactions JS
├── lib/dolistockmove.lib.php                 # Fonctions helpers
├── langs/
│   ├── fr_FR/dolistockmove.lang
│   └── en_US/dolistockmove.lang
└── sql/
    ├── llx_mouvement_stock_extrafields.sql   # Crée la table si absente
    └── llx_mouvement_stock_extrafields.key.sql
```

## Droits

| Action                          | Droit requis                              |
|---------------------------------|-------------------------------------------|
| Consulter les mouvements        | `stock > lire`                            |
| Saisir / supprimer un mouvement | `stock > mouvement > creer`               |

## License

GPL v3 — voir [LICENSE](LICENSE)

## Auteur

[NEYKINFO](https://github.com/NEYKINFO)
