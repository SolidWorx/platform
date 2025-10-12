import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    modal = null;

    connect() {
        this.modal = new Modal(this.element);

        if (this.element.classList.contains('show')) {
            this.modal.show();
        }

        document.addEventListener('modal:close', () => this.modal?.hide());
    }
}
