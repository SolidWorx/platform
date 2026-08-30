import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ["overlay"];
    overlay = null;

    connect() {
        // Re-use a pre-rendered overlay when the template provides one, otherwise build it on the fly
        this.overlay = this.hasOverlayTarget ? this.overlayTarget : this.createOverlay();
    }

    createOverlay() {
        const overlay = document.createElement('div');
        overlay.className = 'position-absolute top-0 start-0 w-100 h-100 d-none justify-content-center align-items-center';
        overlay.style.backgroundColor = 'rgba(255, 255, 255, 0.8)';
        overlay.style.zIndex = '1050';
        overlay.setAttribute('data-loading-target', 'overlay');

        const spinner = document.createElement('div');
        spinner.className = 'spinner-border text-primary';
        spinner.style.width = '3rem';
        spinner.style.height = '3rem';
        spinner.setAttribute('role', 'status');

        const spinnerText = document.createElement('span');
        spinnerText.className = 'visually-hidden';
        spinnerText.textContent = 'Loading...';

        spinner.appendChild(spinnerText);
        overlay.appendChild(spinner);

        // Make sure the parent container has relative positioning
        if (getComputedStyle(this.element).position === 'static') {
            this.element.style.position = 'relative';
        }

        this.element.appendChild(overlay);

        return overlay;
    }

    show() {
        if (this.overlay) {
            this.overlay.classList.remove('d-none');
            this.overlay.classList.add('d-flex');
        }
    }

    hide() {
        if (this.overlay) {
            this.overlay.classList.add('d-none');
            this.overlay.classList.remove('d-flex');
        }
    }

    // This method will be called when the form is submitted
    onSubmit() {
        this.show();
    }
}
