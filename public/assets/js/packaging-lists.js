(() => {
  'use strict';
  const root = document.querySelector('[data-packaging-form]');
  if (!root) return;
  const cartonList = root.querySelector('[data-carton-list]');
  const cartonTemplate = document.querySelector('#plCartonTemplate');
  const itemTemplate = document.querySelector('#plItemTemplate');
  const number = value => Number.parseFloat(value) || 0;
  const openModal = modal => {
    if (!modal) return;
    if (typeof modal.showModal === 'function') modal.showModal(); else modal.setAttribute('open', '');
    requestAnimationFrame(() => modal.querySelector('input:not([type="hidden"]),select,textarea,button')?.focus());
  };
  const closeModal = modal => { if (!modal) return; if (typeof modal.close === 'function') modal.close(); else modal.removeAttribute('open'); };
  let nextCarton = cartonList.querySelectorAll('[data-carton]').length;
  let nextItem = Date.now();

  function update() {
    let cartons = 0, net = 0, gross = 0, cbm = 0;
    const sourceGroups = new Map();
    cartonList.querySelectorAll('[data-carton]').forEach((carton, index) => {
      const countInput = carton.querySelector('[data-carton-count]');
      const individual = carton.querySelector('[name$="[packing_mode]"]')?.value === 'individual';
      if (individual) countInput.value = '1';
      countInput.readOnly = individual;
      const count = number(countInput?.value);
      const length = number(carton.querySelector('[data-length]')?.value);
      const width = number(carton.querySelector('[data-width]')?.value);
      const height = number(carton.querySelector('[data-height]')?.value);
      const cartonCbm = length * width * height / 1000000;
      cartons += count;
      net += count * number(carton.querySelector('[data-net]')?.value);
      gross += count * number(carton.querySelector('[data-gross]')?.value);
      cbm += count * cartonCbm;
      const heading = carton.querySelector('[data-carton-number]');
      const cbmOutput = carton.querySelector('[data-cbm]');
      const summary = carton.querySelector('[data-carton-summary]');
      if (heading) heading.textContent = `Carton group ${index + 1}`;
      if (cbmOutput) cbmOutput.textContent = cartonCbm.toFixed(4);
      if (summary) summary.textContent = `${count || 0} carton${count === 1 ? '' : 's'} · ${length || 0} × ${width || 0} × ${height || 0} cm`;
      const itemCount = carton.querySelectorAll('[data-item]').length;
      const itemCountOutput = carton.querySelector('[data-item-count]');
      if (itemCountOutput) itemCountOutput.textContent = `${itemCount} item ${itemCount === 1 ? 'line' : 'lines'}`;
      carton.querySelectorAll('[data-item]').forEach(item => {
        const quantity = number(item.querySelector('[data-qty]')?.value);
        const total = item.querySelector('[data-total-qty]');
        if (total && document.activeElement !== total) total.value = formatQuantity(quantity * count);
        const source = item.querySelector('[name$="[source_quantity]"]');
        const previous = item.querySelector('[name$="[previously_packed_quantity]"]');
        const balance = item.querySelector('[data-source-balance]');
        if (source && source.value !== '' && balance) {
          const sourceId = item.querySelector('[name$="[source_item_id]"]')?.value;
          const sourceType = item.querySelector('[name$="[source_type]"]')?.value;
          const key = `${sourceType}:${sourceId || item.querySelector('[name$="[item_description]"]')?.value}`;
          const group = sourceGroups.get(key) || { source: number(source.value), previous: number(previous?.value), current: 0, balances: [] };
          group.current += quantity * count; group.balances.push(balance); sourceGroups.set(key, group);
        }
      });
    });
    sourceGroups.forEach(group => {
      const remaining = group.source - group.previous - group.current;
      group.balances.forEach(balance => {
        const output = balance.querySelector('[data-remaining]');
        output.textContent = formatQuantity(remaining);
        balance.classList.toggle('is-overpacked', remaining < 0);
        output.closest('span').firstChild.textContent = remaining < 0 ? 'Excess packing ' : 'Remaining after this PL ';
      });
    });
    const selectedText = name => {
      const field = root.querySelector(`[name="${name}"]`);
      return field?.selectedOptions?.[0]?.value ? field.selectedOptions[0].textContent.trim() : 'Not linked';
    };
    const summaryMap = {vendor_id:'[data-summary-vendor]',client_id:'[data-summary-client]',project_id:'[data-summary-project]',sales_invoice_id:'[data-summary-invoice]'};
    Object.entries(summaryMap).forEach(([name, selector]) => { const output=root.querySelector(selector); if(output) output.textContent=selectedText(name); });
    const from = root.querySelector('[name="from_name"]')?.value.trim() || 'Sender not set';
    const ship = root.querySelector('[name="ship_to_name"]')?.value.trim() || 'Destination not set';
    const partySummary = root.querySelector('[data-party-summary]'); if (partySummary) partySummary.textContent = `${from} → ${ship}`;
    root.querySelector('[data-total-cartons]').textContent = String(cartons);
    root.querySelector('[data-total-net]').textContent = `${net.toFixed(3)} kg`;
    root.querySelector('[data-total-gross]').textContent = `${gross.toFixed(3)} kg`;
    root.querySelector('[data-total-cbm]').textContent = cbm.toFixed(4);
  }
  function formatQuantity(value) { return Number.isInteger(value) ? String(value) : value.toFixed(3).replace(/0+$/, '').replace(/\.$/, ''); }
  function addItem(carton, sourceItem = null) {
    const cartonKey = carton.querySelector('[name*="[carton_count]"]').name.match(/cartons\[([^\]]+)\]/)?.[1] ?? nextCarton;
    const html = itemTemplate.innerHTML.replaceAll('__CARTON__', cartonKey).replaceAll('__ITEM__', String(nextItem++));
    carton.querySelector('[data-item-list]').insertAdjacentHTML('beforeend', html);
    const added = carton.querySelector('[data-item-list] [data-item]:last-child');
    if (sourceItem) sourceItem.querySelectorAll('input,select,textarea').forEach(control => {
      const field = control.name.match(/\[([^\]]+)\]$/)?.[1];
      const target = field ? added.querySelector(`[name$="[${field}]"]`) : null;
      if (target) target.value = control.value;
    });
    added.querySelector('input:not([type="hidden"])')?.focus();
    update();
  }
  function fill(select, targets) {
    const option = select.selectedOptions[0];
    if (!option || !option.value) return;
    Object.entries(targets).forEach(([dataKey, fieldName]) => {
      const target = root.querySelector(`[name="${fieldName}"]`);
      if (target && option.dataset[dataKey] && !target.value.trim()) target.value = option.dataset[dataKey];
    });
  }
  root.addEventListener('click', event => {
    if (event.target.matches('dialog.pl-modal')) { closeModal(event.target); return; }
    const opener = event.target.closest('[data-open-pl-modal]');
    if (opener) { openModal(document.getElementById(opener.dataset.openPlModal)); return; }
    const closer = event.target.closest('[data-close-pl-modal]');
    if (closer) { closeModal(closer.closest('dialog')); update(); return; }
    const addCarton = event.target.closest('[data-add-carton]');
    if (addCarton) {
      cartonList.insertAdjacentHTML('beforeend', cartonTemplate.innerHTML.replaceAll('__CARTON__', String(nextCarton++)));
      cartonList.querySelector('[data-carton]:last-child input')?.focus(); update(); return;
    }
    const copySources = event.target.closest('[data-copy-source-items]');
    if (copySources) {
      const carton = copySources.closest('[data-carton]');
      const candidates = [...cartonList.querySelectorAll('[data-item]')].filter(item => item.closest('[data-carton]') !== carton && item.querySelector('[name$="[source_item_id]"]')?.value);
      const sources = [...new Map(candidates.map(item => [`${item.querySelector('[name$="[source_type]"]').value}:${item.querySelector('[name$="[source_item_id]"]').value}`, item])).values()];
      if (!sources.length) { window.alert('No source-linked lines are available to copy.'); return; }
      const blank = carton.querySelectorAll('[data-item]').length === 1 && !carton.querySelector('[name$="[item_description]"]').value.trim();
      sources.forEach(item => addItem(carton, item));
      if (blank) carton.querySelector('[data-item]')?.remove();
      update(); return;
    }
    const add = event.target.closest('[data-add-item]');
    if (add) { addItem(add.closest('[data-carton]')); return; }
    const removeItem = event.target.closest('[data-remove-item]');
    if (removeItem) {
      const carton = removeItem.closest('[data-carton]');
      if (carton.querySelectorAll('[data-item]').length <= 1) { window.alert('Each carton group needs at least one item.'); return; }
      removeItem.closest('[data-item]').remove(); update(); return;
    }
    const removeCarton = event.target.closest('[data-remove-carton]');
    if (removeCarton) {
      if (cartonList.querySelectorAll('[data-carton]').length <= 1) { window.alert('A packaging list needs at least one carton group.'); return; }
      removeCarton.closest('[data-carton]').remove(); update();
    }
  });
  root.addEventListener('input', event => { if (event.target.matches('[data-carton-count],[data-length],[data-width],[data-height],[data-net],[data-gross],[data-qty],[name="from_name"],[name="ship_to_name"]')) update(); });
  root.addEventListener('invalid', event => { const modal=event.target.closest('dialog'); if(modal && !modal.open && !root.querySelector('dialog[open]')) openModal(modal); }, true);
  root.addEventListener('change', event => {
    if (event.target.matches('[name$="[packing_mode]"]')) update();
    if (event.target.matches('[data-product]')) {
      const description = event.target.closest('[data-item]').querySelector('[name$="[item_description]"]');
      const option = event.target.selectedOptions[0];
      if (description && option?.dataset.name && !description.value.trim()) description.value = option.dataset.name;
    }
    if (event.target.matches('[data-pl-vendor]')) fill(event.target, {name:'from_name', address:'from_address', contact:'from_contact', email:'from_email'});
    if (event.target.matches('[data-pl-client]')) fill(event.target, {name:'ship_to_name', address:'ship_to_address'});
    if (event.target.matches('[data-pl-shipment]')) {
      const option = event.target.selectedOptions[0];
      if (option?.value) {
        [['client','client_id'],['vendor','vendor_id'],['project','project_id'],['invoice','sales_invoice_id'],['mode','transport_mode']].forEach(([key,name]) => { const field=root.querySelector(`[name="${name}"]`); if(field && option.dataset[key]) field.value=option.dataset[key]; });
        fill(event.target, {fromName:'from_name',fromAddress:'from_address',shipName:'ship_to_name',shipAddress:'ship_to_address'});
      }
    }
    update();
  });
  update();
  try {
    const errorFields = JSON.parse(root.dataset.errorFields || '[]');
    const first = errorFields[0];
    if (first) {
      const parts = first.split('.');
      const fieldName = parts.shift() + parts.map(part => `[${part}]`).join('');
      const field = root.querySelector(`[name="${CSS.escape(fieldName)}"]`);
      const modal = field?.closest('dialog');
      if (modal) openModal(modal);
    }
  } catch (_) { /* malformed flash data must never block the form */ }
})();
