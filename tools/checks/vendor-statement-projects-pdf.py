#!/usr/bin/env python3
"""Inspect the browser-generated A4 statement with PyMuPDF (disposable .work only)."""
import json
import re
from pathlib import Path

import pymupdf

work = Path('.work').resolve()
fixture = json.loads((work / 'vendor-statement-projects-fixture.json').read_text())
doc = pymupdf.open(work / 'vendor-statement-projects-a4.pdf')
assert 1 <= len(doc) <= 2, f'Unexpected pagination for this fixture: {len(doc)} pages'
text = ''.join(page.get_text() for page in doc)
compact = re.sub(r'\s+', '', text)
for expected in (fixture['alpha_name'], fixture['beta_name'], 'BROWSER-PROJECT-BILL', 'BROWSER-SPLIT',
                 'BROWSER-LEDGER-SLICE', 'BROWSER-NO-PROJECT', '300.50', '185.375', '115.125'):
    assert re.sub(r'\s+', '', expected) in compact, f'Missing or clipped {expected!r}'
assert 'Project:' in text and 'Projects:' in text
assert text.count('Closing Payable') == 1, 'Closing total must not repeat on every page'
assert compact.count(re.sub(r'\s+', '', fixture['beta_name'])) == 2, 'Both split payments must print the complete second project'
for page in doc:
    page_text = page.get_text()
    assert all(header in page_text for header in ('PARTICULARS', 'DEBIT (paid)', 'CREDIT (billed)', 'BALANCE')), 'Table headings repeat on every page'
    assert 590 <= page.rect.width <= 600 and 835 <= page.rect.height <= 850, f'Not A4: {page.rect}'
    spans = [span for block in page.get_text('dict')['blocks'] if 'lines' in block
             for line in block['lines'] for span in line['spans'] if span['text'].strip()]
    outside = [span for span in spans if span['bbox'][0] < 30 or span['bbox'][1] < 30
               or span['bbox'][2] > page.rect.width - 30 or span['bbox'][3] > page.rect.height - 30]
    assert not outside, f'Text outside printable margins: {outside}'
print('PASS A4 PDF: both complete project names, original references and figures within margins')
