/*
 * Admin entry (loaded by the Admin layouts via @vite). Alpine components
 * live per concern under ./admin/; this file only wires them up.
 */
import { registerChartComponents } from './admin/chart.js';
import { registerInvoiceComponents } from './admin/invoices.js';
import { registerPageEditorComponents } from './admin/page-editor.js';
import { registerProductComponents } from './admin/products.js';
import { registerShellComponents } from './admin/shell.js';
import { fileUploadState } from './shared/file-upload.js';

document.addEventListener('alpine:init', () => {
    registerChartComponents(Alpine);
    registerShellComponents(Alpine);
    registerProductComponents(Alpine);
    registerInvoiceComponents(Alpine);
    registerPageEditorComponents(Alpine);
    Alpine.data('agFileUpload', fileUploadState);
});
