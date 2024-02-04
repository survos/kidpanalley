import { startStimulusApp } from '@symfony/stimulus-bundle';
import ContentLoader from 'stimulus-content-loader'
import Reveal from 'stimulus-reveal-controller'
import ZoomImageController from '@kanety/stimulus-zoom-image';

const app = startStimulusApp();

// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);
// app.debug = true; // process.env.NODE_ENV === 'development'

app.register('content-loader', ContentLoader);
app.register('reveal', Reveal)
app.register('zoom-image', ZoomImageController);
