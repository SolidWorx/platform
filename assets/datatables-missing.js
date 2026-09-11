import { Controller } from '@hotwired/stimulus';

/**
 * Stands in for pentiminax/ux-datatables when the Composer package is not
 * installed, so an application running with `datagrid.enabled: false` still
 * builds. Rendering a grid is impossible in that state, so this never runs.
 */
export default class extends Controller {}
