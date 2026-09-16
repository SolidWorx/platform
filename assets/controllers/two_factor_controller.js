import { Controller } from '@hotwired/stimulus';

/**
 * The client-side half of the two-factor settings page.
 *
 * Two jobs, both of which would be a round-trip for nothing if the server did them:
 *
 * - **Stepping through the authenticator setup.** Scanning a QR code and typing the six digits it
 *   produces are two screens, but one form. Every field stays in the DOM the whole time — the
 *   steps are shown and hidden, never added and removed — so the live component submits a complete
 *   form whichever step is on screen.
 * - **Downloading backup codes.** The codes are already rendered on the page; asking the server for
 *   a file containing what the browser is holding would mean a route that returns recovery codes,
 *   which is a route worth not having.
 *
 * Anything that belongs to one step — a pane in the body, a button in the footer — carries the
 * `step` target and a `data-step` saying which one. Reading the step off the element rather than
 * its position lets the footer buttons sit directly in `.modal-footer`, where they pick up its
 * spacing and alignment, instead of being grouped into a wrapper per step.
 *
 * The starting step comes from the server through `stepValue`, so a failed verification reopens on
 * the step that failed rather than sending the user back to the QR code.
 */
export default class extends Controller {
    static targets = ['step', 'stepItem'];

    static values = {
        step: { type: Number, default: 0 },
        codes: { type: Array, default: [] },
        filename: { type: String, default: 'backup-codes.txt' },
    };

    /**
     * Runs on connect as well as on every change, so it is also what renders the initial step.
     */
    stepValueChanged(step) {
        this.stepTargets.forEach((element) => {
            element.classList.toggle('d-none', Number(element.dataset.step) !== step);
        });

        // Tabler marks the *current* step only: everything after an `.active` item is greyed out by
        // `.step-item.active ~ .step-item`, so marking the earlier ones active would grey out the
        // one the user is actually on.
        this.stepItemTargets.forEach((element, index) => {
            element.classList.toggle('active', index === step);
        });
    }

    next(event) {
        event.preventDefault();
        this.stepValue = Math.min(this.stepValue + 1, Math.max(this.stepItemTargets.length - 1, 0));
    }

    previous(event) {
        event.preventDefault();
        this.stepValue = Math.max(this.stepValue - 1, 0);
    }

    /**
     * Hands the codes to the browser as a text file, without them ever leaving the page.
     */
    download(event) {
        event.preventDefault();

        if (this.codesValue.length === 0) {
            return;
        }

        const url = URL.createObjectURL(
            new Blob([`${this.codesValue.join('\n')}\n`], { type: 'text/plain;charset=utf-8' }),
        );

        const link = document.createElement('a');
        link.href = url;
        link.download = this.filenameValue;
        link.hidden = true;

        document.body.appendChild(link);
        link.click();
        link.remove();

        URL.revokeObjectURL(url);
    }
}
