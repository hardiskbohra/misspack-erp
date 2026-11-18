#!/usr/bin/env python3
"""Inspect selected-field Chromium PDFs using only fictional .work fixtures."""
import collections
import json
import re
from pathlib import Path

import pymupdf

work = Path('.work').resolve()
fixture = json.loads((work / 'statement-print-fields-fixture.json').read_text())
for name in ('vendor', 'client'):
    doc = pymupdf.open(work / f'statement-print-fields-{name}-a4.pdf')
    text = ''.join(page.get_text() for page in doc)
    compact = re.sub(r'\s+', '', text)
    assert ('Browser Supplier' if name == 'vendor' else 'Browser Clinic') in text
    assert 'This print omits selected transaction fields' in text
    assert 'Print options' not in text and 'Apply filters' not in text
    assert text.count('Closing Payable' if name == 'vendor' else 'Closing Receivable') == 1
    for page in doc:
        assert 590 <= page.rect.width <= 600 and 835 <= page.rect.height <= 850, page.rect
        spans = [span for block in page.get_text('dict')['blocks'] if 'lines' in block
                 for line in block['lines'] for span in line['spans'] if span['text'].strip()]
        outside = [span for span in spans if span['bbox'][0] < 30 or span['bbox'][1] < 30
                   or span['bbox'][2] > page.rect.width - 30 or span['bbox'][3] > page.rect.height - 30]
        assert not outside, f'{name} page {page.number + 1}: text outside printable margins: {outside}'
        page_text = page.get_text()
        expected = ('PROJECT', 'BALANCE') if name == 'vendor' else ('DATE', 'REFERENCE', 'DEBIT (invoice)', 'BALANCE')
        assert all(label in page_text for label in expected), f'{name} page {page.number + 1}: missing repeated heading'
        assert 'PARTICULARS' not in page_text
    if name == 'vendor':
        assert len(doc) <= 2
        for value in (fixture['alpha_name'], fixture['beta_name'], '300.50', '115.125'):
            assert re.sub(r'\s+', '', value) in compact, f'Incomplete project-only output: {value}'
        assert 'BROWSER-PROJECT-BILL' not in text and 'Advance shared across documents' not in text
        assert 'Bank:' not in text and 'ACCOUNT SUMMARY' not in text
    else:
        assert 2 <= len(doc) <= 4, f'Expected multipage client fixture, got {len(doc)}'
        found = collections.Counter(map(int, re.findall(r'FIELD-ROW-(\d+)', compact)))
        assert found == collections.Counter(range(1, 66)), 'Each of 65 invoice references must print exactly once'
        assert '1,633.125' in text and '25.125' in text
        assert 'Bank:' not in text
        assert all('CREDIT (received)' not in page.get_text() for page in list(doc)[1:])
    doc[0].get_pixmap(matrix=pymupdf.Matrix(1.4, 1.4)).save(work / f'statement-print-fields-{name}-paper.png')
    print(f'PASS {name}: {len(doc)} A4 page(s), selected headings/content and printable margins')
