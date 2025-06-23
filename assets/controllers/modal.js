import { Controller } from '@hotwired/stimulus';
import $, {jQuery} from 'jquery';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    /**
     * @type {jQuery}
     */
    modal = null;

    connect() {
        this.modal = $(this.element);

        if (this.modal.hasClass('show')) {
            this.modal.modal('show');
        }

        document.addEventListener('modal:close', () => this.modal?.modal('hide'));
    }
}
