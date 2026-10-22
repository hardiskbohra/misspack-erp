(() => {
  'use strict';
  // Index rows already carry data-href; opt into the shared navigation contract.
  // Do this before the editor-only guard so the list can initialize without a form.
  if (document.querySelector('.packaging-list-page') && window.MasterList) {
    window.MasterList.rowNavigation({root: '.packaging-list-page'});
    window.MasterList.gridShadow({root: '.packaging-list-page'});
  }
  const root = document.querySelector('[data-packaging-form]');
  if (!root) return;
  const cartonList = root.querySelector('[data-carton-list]');
  const cartonTemplate = document.querySelector('#plCartonTemplate');
  const itemTemplate = document.querySelector('#plItemTemplate');
  const cartonTabs = root.querySelector('[data-carton-tabs]');
  const number = value => Number.parseFloat(value) || 0;
  const openModal = modal => {
    if (!modal) return;
    if (typeof modal.showModal === 'function') modal.showModal(); else modal.setAttribute('open', '');
    requestAnimationFrame(() => modal.querySelector('input:not([type="hidden"]),select,textarea,button')?.focus());
  };
  const closeModal = modal => { if (!modal) return; if (typeof modal.close === 'function') modal.close(); else modal.removeAttribute('open'); };
  let nextCarton = cartonList.querySelectorAll('[data-carton]').length;
  let nextItem = Date.now();
  let activeCarton = 0;

  /* With a dozen groups the strip is wider than the card, so the tab for the
     group you are opening — by click, arrow key or "Add carton group" — has to
     be scrolled into view, or the selection happens somewhere off-screen. */
  const revealTab = index => cartonTabs?.querySelector(`[data-carton-tab="${index}"]`)?.scrollIntoView({block:'nearest',inline:'nearest'});

  function activateCarton(index, focusTab = false) {
    const panels = [...cartonList.querySelectorAll('[data-carton]')];
    const next = Math.max(0, Math.min(index, panels.length - 1));
    const moved = next !== activeCarton;
    activeCarton = next;
    panels.forEach((panel, panelIndex) => {
      panel.hidden = panelIndex !== activeCarton;
      panel.setAttribute('role', 'tabpanel');
      panel.id = `plCartonPanel${panelIndex}`;
      panel.setAttribute('aria-labelledby', `plCartonTab${panelIndex}`);
    });
    cartonTabs?.querySelectorAll('[data-carton-tab]').forEach((tab, tabIndex) => {
      const selected = tabIndex === activeCarton;
      tab.classList.toggle('is-active', selected);
      tab.setAttribute('aria-selected', selected ? 'true' : 'false');
      tab.tabIndex = selected ? 0 : -1;
    });
    const activeTab = cartonTabs?.querySelector(`[data-carton-tab="${activeCarton}"]`);
    if (activeTab && (moved || focusTab)) revealTab(activeCarton);
    if (focusTab) activeTab?.focus({preventScroll:true});
  }

  function update() {
    let cartons = 0, net = 0, gross = 0, cbm = 0, chargeable = 0, itemLines = 0, completeGroups = 0;
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
      const cartonCbm = Number((length * width * height / 1000000).toFixed(4));
      const netPerCarton = number(carton.querySelector('[data-net]')?.value);
      const grossPerCarton = number(carton.querySelector('[data-gross]')?.value);
      const volumetricPerCarton = cartonCbm * 164;
      const chargeablePerCarton = Math.max(grossPerCarton, volumetricPerCarton);
      if (count > 0 && length > 0 && width > 0 && height > 0 && grossPerCarton >= netPerCarton) completeGroups++;
      cartons += count;
      net += count * netPerCarton;
      gross += count * grossPerCarton;
      cbm += count * cartonCbm;
      chargeable += count * chargeablePerCarton;
      const heading = carton.querySelector('[data-carton-number]');
      const cbmOutput = carton.querySelector('[data-cbm]');
      const summary = carton.querySelector('[data-carton-summary]');
      const sequence = carton.querySelector('[data-carton-sequence]');
      const cartonNumber = carton.querySelector('[name$="[carton_number]"]');
      const volumetricOutput = carton.querySelector('[data-volumetric]');
      const chargeableOutput = carton.querySelector('[data-chargeable]');
      const chargeableBasis = carton.querySelector('[data-chargeable-basis]');
      if (heading) heading.textContent = `Carton group ${index + 1}`;
      if (sequence) sequence.textContent = String(index + 1);
      if (cartonNumber) cartonNumber.value = String(index + 1);
      if (cbmOutput) cbmOutput.textContent = cartonCbm.toFixed(4);
      if (volumetricOutput) volumetricOutput.textContent = `${volumetricPerCarton.toFixed(1)} kg`;
      if (chargeableOutput) chargeableOutput.textContent = `${chargeablePerCarton.toFixed(1)} kg`;
      if (chargeableBasis) chargeableBasis.textContent = volumetricPerCarton > grossPerCarton ? 'Volumetric weight' : 'Gross weight';
      if (summary) {
        const group = carton.querySelector('[data-packing-group]')?.value.trim();
        const items = carton.querySelectorAll('[data-item]').length;
        summary.textContent = `${count || 0} carton${count === 1 ? '' : 's'} · ${length || 0} × ${width || 0} × ${height || 0} cm`
          + ` · ${items} item${items === 1 ? '' : 's'}`
          + (group ? ` · under “${group}”` : '');
      }
      const itemCount = carton.querySelectorAll('[data-item]').length;
      itemLines += itemCount;
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
    const panels = [...cartonList.querySelectorAll('[data-carton]')];
    if (cartonTabs) cartonTabs.innerHTML = panels.map((panel, index) => {
      /* The tab is named by what the block prints under: its packing group,
         then its carton label, then its first product — "Carton group 3" says
         nothing when three blocks of the same product sit side by side. */
      const label = panel.querySelector('[data-packing-group]')?.value.trim()
        || panel.querySelector('[name$="[carton_label]"]')?.value.trim()
        || panel.querySelector('[data-item] [name$="[item_description]"]')?.value.trim()
        || `Carton group ${index + 1}`;
      const count = number(panel.querySelector('[data-carton-count]')?.value);
      const dimensions = [panel.querySelector('[data-length]')?.value, panel.querySelector('[data-width]')?.value, panel.querySelector('[data-height]')?.value].filter(Boolean).join(' × ') || 'Dimensions pending';
      return `<button type="button" class="master-tab pl-carton-tab" role="tab" id="plCartonTab${index}" aria-controls="plCartonPanel${index}" data-carton-tab="${index}"><b>${index + 1}</b><span><strong>${escapeHtml(label)}</strong><small>${count || 0} carton${count === 1 ? '' : 's'} · ${escapeHtml(dimensions)}</small></span></button>`;
    }).join('');
    activateCarton(activeCarton);
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
    root.querySelector('[data-total-groups]').textContent = String(panels.length);
    root.querySelector('[data-total-items]').textContent = String(itemLines);
    root.querySelector('[data-total-cartons]').textContent = String(cartons);
    const completeness = root.querySelector('[data-summary-completeness]');
    if (completeness) completeness.textContent = completeGroups === panels.length ? 'All carton groups complete' : `${completeGroups} of ${panels.length} carton groups complete`;
    root.querySelector('[data-total-net]').textContent = `${net.toFixed(1)} kg`;
    root.querySelector('[data-total-gross]').textContent = `${gross.toFixed(1)} kg`;
    root.querySelector('[data-total-cbm]').textContent = cbm.toFixed(4);
    root.querySelector('[data-total-chargeable]').textContent = `${chargeable.toFixed(1)} kg`;
  }
  function escapeHtml(value) { const span=document.createElement('span'); span.textContent=String(value); return span.innerHTML; }
  function formatQuantity(value) { return Number.isInteger(value) ? String(value) : value.toFixed(1).replace(/0+$/, '').replace(/\.$/, ''); }
  function addItem(carton) {
    const cartonKey = carton.querySelector('[name*="[carton_count]"]').name.match(/cartons\[([^\]]+)\]/)?.[1] ?? nextCarton;
    const html = itemTemplate.innerHTML.replaceAll('__CARTON__', cartonKey).replaceAll('__ITEM__', String(nextItem++));
    carton.querySelector('[data-item-list]').insertAdjacentHTML('beforeend', html);
    const added = carton.querySelector('[data-item-list] [data-item]:last-child');
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
    const tab = event.target.closest('[data-carton-tab]');
    if (tab) { activateCarton(number(tab.dataset.cartonTab)); return; }
    const opener = event.target.closest('[data-open-pl-modal]');
    if (opener) { openModal(document.getElementById(opener.dataset.openPlModal)); return; }
    const closer = event.target.closest('[data-close-pl-modal]');
    if (closer) { closeModal(closer.closest('dialog')); update(); return; }
    const addCarton = event.target.closest('[data-add-carton]');
    if (addCarton) {
      cartonList.insertAdjacentHTML('beforeend', cartonTemplate.innerHTML.replaceAll('__CARTON__', String(nextCarton++)));
      activeCarton = cartonList.querySelectorAll('[data-carton]').length - 1;
      update();
      revealTab(activeCarton);
      cartonList.querySelector('[data-carton]:last-child input')?.focus(); return;
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
      const panels = [...cartonList.querySelectorAll('[data-carton]')];
      const removedIndex = panels.indexOf(removeCarton.closest('[data-carton]'));
      removeCarton.closest('[data-carton]').remove();
      if (removedIndex <= activeCarton) activeCarton = Math.max(0, activeCarton - 1);
      update();
    }
  });
  cartonTabs?.addEventListener('keydown', event => {
    if (!['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) return;
    event.preventDefault(); const count=cartonList.querySelectorAll('[data-carton]').length;
    const next=event.key==='Home'?0:event.key==='End'?count-1:event.key==='ArrowRight'?(activeCarton+1)%count:(activeCarton-1+count)%count;
    activateCarton(next,true);
  });
  root.addEventListener('input', event => { if (event.target.matches('[data-carton-count],[data-length],[data-width],[data-height],[data-net],[data-gross],[data-qty],[name$="[carton_label]"],[data-packing-group],[name$="[item_description]"],[name="from_name"],[name="ship_to_name"]')) update(); });
  root.addEventListener('invalid', event => {
    const panel = event.target.closest('[data-carton]');
    if (panel) activateCarton([...cartonList.querySelectorAll('[data-carton]')].indexOf(panel));
    const modal=event.target.closest('dialog');
    if(modal && !modal.open && !root.querySelector('dialog[open]')) openModal(modal);
  }, true);
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
      const panel = field?.closest('[data-carton]');
      if (panel) activateCarton([...cartonList.querySelectorAll('[data-carton]')].indexOf(panel));
      const modal = field?.closest('dialog');
      if (modal) openModal(modal);
    }
  } catch (_) { /* malformed flash data must never block the form */ }
})();
