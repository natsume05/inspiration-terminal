/**
 * Application entry point.
 *
 * Loads the module for the page that is actually present, so no page downloads
 * JavaScript it does not use. Previously every page carried inline script
 * blocks, which meant every visitor received all of it and none of it could be
 * tested or cached.
 */

import './community.js';

// Reveal the current page in the console for quick manual verification that the
// module graph loaded; harmless in production and useful when debugging.
console.debug('[inspiration] front-end modules loaded');
