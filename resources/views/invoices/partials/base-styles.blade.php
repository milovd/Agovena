{{--
    Shared page setup. PDF output goes through dompdf: tables and absolute positioning only, no flexbox or grid.
    dompdf reads one @page rule, so each template passes its own $pageMargin (four values).
--}}
@page { size: A4; margin: {{ $pageMargin ?? '12mm 0 14mm 0' }}; }
* { box-sizing: border-box; }
html { background: #ffffff; }
body { margin: 0; padding: 0; background: #ffffff; }
body { color: #1f2933; font-family: "DejaVu Sans", Arial, Helvetica, sans-serif; font-size: 10.5px; line-height: 1.45; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
p { margin: 0; }
h1, h2, h3 { margin: 0; font-weight: bold; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 0; text-align: left; vertical-align: top; }
img { border: 0; }
.inv-page { position: relative; max-width: 210mm; margin: 0 auto; padding: 0 58px; background: #ffffff; }
.inv-num { text-align: right; white-space: nowrap; }
.inv-muted { color: #6b7280; }
.inv-strong { font-weight: bold; }
.inv-small { font-size: 9px; }
.inv-pre { white-space: pre-line; }
.inv-options { margin: 2px 0 0; padding: 0; list-style: none; color: #6b7280; font-size: 9px; }
.inv-logo { display: block; max-width: 170px; max-height: 56px; }
.inv-deco { position: absolute; display: block; }
.inv-toolbar { width: 210mm; margin: 0 auto; padding: 18px 0 0; text-align: right; }
.inv-toolbar button { padding: 7px 16px; border: 1px solid #cbd2d9; border-radius: 6px; background: #ffffff; color: #1f2933; font: inherit; font-size: 12px; cursor: pointer; }
.is-screen, .is-preview { background: #e5e7eb; }
.is-screen .inv-page, .is-preview .inv-page { min-height: 297mm; margin: 18px auto 32px; padding-top: 52px; padding-bottom: 64px; overflow: hidden; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06), 0 12px 32px rgba(15, 23, 42, 0.12); }
.is-preview .inv-page { margin: 0 auto; box-shadow: none; }
.is-preview { background: #ffffff; }
@media print {
    .inv-toolbar { display: none !important; }
    .is-screen { background: #ffffff; }
    .is-screen .inv-page { margin: 0; padding-top: 0; padding-bottom: 0; min-height: 0; box-shadow: none; }
}
