(() => {
  'use strict';
  const form = document.querySelector('[data-print-settings]');
  const pageStyle = document.getElementById('plPageStyle');
  if (!form || !pageStyle) return;

  const orientation = form.querySelector('[name="orientation"]');
  const header = form.querySelector('[name="header_text"]');
  const footer = form.querySelector('[name="footer_text"]');
  const numbers = form.querySelector('input[type="checkbox"][name="show_page_numbers"]');
  const previewHeader = document.querySelector('[data-preview-header]');
  const previewFooter = document.querySelector('[data-preview-footer-text]');
  const previewNumbers = document.querySelector('[data-preview-page-number]');
  const status = form.querySelector('[data-print-settings-status]');

  // A CSS string is not JSON or HTML. Hex-escape every code point so arbitrary
  // text (including quotes, newlines, emoji and closing style tags) stays text.
  const cssText = text => `"${Array.from(text, character => `\\${character.codePointAt(0).toString(16)} `).join('')}"`;
  const apply = () => {
    const paper = orientation.value === 'landscape' ? 'landscape' : 'portrait';
    const font = 'font:9px "Inter",sans-serif;color:#000;vertical-align:middle;';
    pageStyle.textContent = `@page {
      size:A4 ${paper}; margin:16mm 12mm;
      @top-center { content:${cssText(header.value)}; ${font} white-space:pre-wrap; }
      @bottom-left { content:${cssText(footer.value)}; ${font} white-space:pre-wrap; width:80%; }
      @bottom-right { content:${numbers.checked ? '"Page " counter(page) " of " counter(pages)' : 'none'}; ${font} white-space:nowrap; }
    }`;
    document.body.dataset.orientation = paper;
    previewHeader.textContent = header.value;
    previewHeader.hidden = !header.value;
    previewFooter.textContent = footer.value;
    previewNumbers.hidden = !numbers.checked;
  };
  const update = () => {
    apply();
    status.textContent = 'Preview updated. Save settings to remember these changes.';
  };
  form.addEventListener('input', update);
  form.addEventListener('change', update);
  window.addEventListener('beforeprint', apply);
  document.querySelector('[data-print-document]')?.addEventListener('click', () => {
    if (!form.reportValidity()) return;
    apply();
    window.print();
  });
  apply();
})();
