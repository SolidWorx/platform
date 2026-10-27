import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select';

/**
 * Behaviour for CountryType (see CountryTypeExtension).
 *
 * Enhances the country `<select>` into a searchable tom-select dropdown. Option values are ISO 3166-1
 * alpha-2 codes, which map directly onto Tabler's `flag-country-*` classes.
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller {
    #tomSelect = null;

    connect() {
        const render = (data, escape) => {
            if (!data.value) {
                return `<div>${escape(data.text)}</div>`;
            }

            return `<div><span class="flag flag-xs flag-country-${escape(data.value.toLowerCase())} me-2"></span>${escape(data.text)}</div>`;
        };

        this.#tomSelect = new TomSelect(this.element, {
            maxOptions: null,
            render: { option: render, item: render },
        });
    }

    disconnect() {
        this.#tomSelect?.destroy();
        this.#tomSelect = null;
    }
}
