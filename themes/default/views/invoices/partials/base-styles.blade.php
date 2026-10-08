{{-- Shared page setup for the Modern, Minimal and Compact templates: white A4 paper in every color mode. --}}
:root { color-scheme: only light; }
@page { size: A4; margin: 16mm 15mm 18mm; }
* { box-sizing: border-box; }
html, body { background: #ffffff; color: #1f2937; }
body { margin: 0; font-family: "DejaVu Sans", Arial, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
p { margin: 0; }
table { width: 100%; border-collapse: collapse; }
th, td { vertical-align: top; text-align: left; }
thead { display: table-header-group; }
tr { page-break-inside: avoid; }
.invoice-doc { max-width: 180mm; margin: 0 auto; padding: 14mm 0; }
.invoice-doc__toolbar { margin: 0 0 8mm; text-align: right; }
.invoice-doc__toolbar button { font: inherit; font-size: 13px; padding: 7px 16px; border: 1px solid #cbd5e1; border-radius: 6px; background: #ffffff; color: #1f2937; cursor: pointer; }
.invoice-doc__num { text-align: right; white-space: nowrap; }
.invoice-doc__options { margin: 2px 0 0; padding: 0; list-style: none; color: #6b7280; font-size: 0.85em; }
.invoice-doc__label { color: #6b7280; }
.invoice-doc__strong { font-weight: bold; }
@media print {
    .no-print { display: none !important; }
    .invoice-doc { max-width: none; padding: 0; }
}
