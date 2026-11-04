import { startStimulusApp } from '@symfony/stimulus-bundle';

// Hooks the browser tests read: the extension events the controller dispatches, the
// clipboard payloads of copy/cut, and the Stimulus application (to reach a controller).
window.__events = [];
['pre-connect', 'connect', 'disconnect'].forEach((name) => {
    document.addEventListener(`lexical:${name}`, (event) => {
        window.__events.push({ name, keys: Object.keys(event.detail).sort() });
    });
});
window.__clip = [];
document.addEventListener('copy', (event) => {
    window.__clip.push(event.clipboardData?.getData('application/x-lexical-editor') ?? null);
});
window.__alerts = [];
window.alert = (message) => { window.__alerts.push(String(message)); };

window.__harness = { app: startStimulusApp() };
