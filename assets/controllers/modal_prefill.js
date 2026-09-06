import { Controller } from '@hotwired/stimulus';

/*
 * Seeds fields inside a modal from the element that opened it.
 *
 * Put `data-controller="modal-prefill"` on the modal and `data-prefill-<field>`
 * on any trigger:
 *
 *     <button data-bs-toggle="modal" data-bs-target="#add-entry"
 *             data-prefill-project="01JX...">Add time</button>
 *
 * Field names are matched against the form field's `name`, so both a bare
 * `name="project"` and Symfony's `name="my_form[project]"` resolve. Values are
 * applied through TomSelect's own API where it owns the control, and a `change`
 * event is dispatched either way so LiveComponent models pick the value up.
 *
 * One modal can therefore serve any number of triggers — including one per table
 * row — instead of being repeated alongside each of them.
 */

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    connect() {
        this.onShow = (event) => this.prefill(event);
        this.element.addEventListener('show.bs.modal', this.onShow);
    }

    disconnect() {
        this.element.removeEventListener('show.bs.modal', this.onShow);
    }

    prefill(event) {
        const trigger = event.relatedTarget;

        if (!trigger || !trigger.dataset) {
            return;
        }

        Object.entries(trigger.dataset).forEach(([key, value]) => {
            if (!key.startsWith('prefill') || key === 'prefill') {
                return;
            }

            const name = key.charAt(7).toLowerCase() + key.slice(8);
            const field = this.findField(name);

            if (field) {
                this.setValue(field, value);
            }
        });
    }

    findField(name) {
        return this.element.querySelector(
            `[name="${name}"], [name$="[${name}]"], [name$="[${name}][]"]`
        );
    }

    setValue(field, value) {
        // TomSelect fires its own change event, which is what Live listens for.
        if (field.tomselect) {
            field.tomselect.setValue(value);
            return;
        }

        field.value = value;
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }
}
