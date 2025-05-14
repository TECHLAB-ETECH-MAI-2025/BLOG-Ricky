import './bootstrap.js';
// Importe la config Symfony

/*
 * Welcome to your app's main JavaScript file!
 * This file will be included onto the page via the encore_entry_script_tags() Twig function.
 */

import 'bootstrap'; // Bootstrap JS (inclut Popper)
import 'bootstrap/dist/css/bootstrap.min.css'; // Bootstrap CSS

// Tu peux aussi ajouter ton propre CSS après Bootstrap si besoin :
import './styles/app.css';

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');
