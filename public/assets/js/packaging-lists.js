(() => {
  'use strict';
  const root = document.querySelector('[data-packaging-form]');
  if (!root) return;
  const cartonList = root.querySelector('[data-carton-list]');
  const cartonTemplate = document.querySelector('#plCartonTemplate');
  const itemTemplate = document.querySelector('#plItemTemplate');
  const number = value => Number.parseFloat(value) || 0;
  let nextCarton = cartonList.querySelectorAll('[data-carton]').length;
  let nextItem = Date.now();

  function update() {
    let cartons = 0, net = 0, gross = 0, cbm = 0;
    cartonList.querySelectorAll('[data-carton]').forEach((carton, index) => {
      const count = number(carton.querySelector('[data-carton-count]')?.value);
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
      carton.querySelectorAll('[data-item]').forEach(item => {
        const quantity = number(item.querySelector('[data-qty]')?.value);
        const total = item.querySelector('[data-total-qty]');
        if (total && document.activeElement !== total) total.value = formatQuantity(quantity * count);
      });
    });
    root.querySelector('[data-total-cartons]').textContent = String(cartons);
    root.querySelector('[data-total-net]').textContent = `${net.toFixed(3)} kg`;
    root.querySelector('[data-total-gross]').textContent = `${gross.toFixed(3)} kg`;
    root.querySelector('[data-total-cbm]').textContent = cbm.toFixed(4);
  }
  function formatQuantity(value) { return Number.isInteger(value) ? String(value) : value.toFixed(3).replace(/0+$/, '').replace(/\.$/, ''); }
  function addItem(carton) {
    const cartonKey = carton.querySelector('[name*="[carton_count]"]').name.match(/cartons\[([^\]]+)\]/)?.[1] ?? nextCarton;
    const html = itemTemplate.innerHTML.replaceAll('__CARTON__', cartonKey).replaceAll('__ITEM__', String(nextItem++));
    carton.querySelector('[data-item-list]').insertAdjacentHTML('beforeend', html);
    carton.querySelector('[data-item-list] [data-item]:last-child input')?.focus();
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
    const addCarton = event.target.closest('[data-add-carton]');
    if (addCarton) {
      cartonList.insertAdjacentHTML('beforeend', cartonTemplate.innerHTML.replaceAll('__CARTON__', String(nextCarton++)));
      cartonList.querySelector('[data-carton]:last-child input')?.focus(); update(); return;
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
  root.addEventListener('input', event => { if (event.target.matches('[data-carton-count],[data-length],[data-width],[data-height],[data-net],[data-gross],[data-qty]')) update(); });
  root.addEventListener('change', event => {
    if (event.target.matches('[data-product]')) {
      const description = event.target.closest('[data-item]').querySelector('[name$="[item_description]"]');
      const option = event.target.selectedOptions[0];
      if (description && option?.dataset.name && !description.value.trim()) description.value = option.dataset.name;
    }
    if (event.target.matches('[data-pl-vendor]')) fill(event.target, {name:'from_name', address:'from_address', contact:'from_contact', email:'from_email'});
    if (event.target.matches('[data-pl-client]')) fill(event.target, {name:'ship_to_name', address:'ship_to_address'});
    if (event.target.matches('[data-pl-shipment]')) {
      const option = event.target.selectedOptions[0];
      if (!option?.value) return;
      [['client','client_id'],['vendor','vendor_id'],['project','project_id'],['invoice','sales_invoice_id'],['mode','transport_mode']].forEach(([key,name]) => { const field=root.querySelector(`[name="${name}"]`); if(field && option.dataset[key]) field.value=option.dataset[key]; });
      fill(event.target, {fromName:'from_name',fromAddress:'from_address',shipName:'ship_to_name',shipAddress:'ship_to_address'});
    }
  });
  update();
})();
