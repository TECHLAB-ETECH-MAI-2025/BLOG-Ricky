// assets/bootstrap.js

import { startStimulusApp } from '@symfony/stimulus-bridge';

// Enregistre automatiquement tous les contrôleurs dans le dossier ./controllers
export const app = startStimulusApp(require.context(
    '@symfony/stimulus-bridge/lazy-controller-loader!./controllers',
    true,
    /\.[jt]sx?$/
));

// Tu peux enregistrer ici manuellement d'autres contrôleurs externes si nécessaire
// import SomeImportedController from './controllers/some_controller';
// app.register('some_controller_name', SomeImportedController);
