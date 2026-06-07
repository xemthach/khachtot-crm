function ktInventoryNormalizeUrl(url) {
  if (!url) return '';
  if (url.startsWith('http://') && window.location.protocol === 'https:') {
    return url.replace('http://', 'https://');
  }
  if (url.startsWith('https://') && window.location.protocol === 'http:') {
    return url.replace('https://', 'http://');
  }
  return url;
}

document.addEventListener('click', function (event) {
  var addButton = event.target.closest('[data-kt-add-line]');
  if (addButton) {
    event.preventDefault();
    var targetId = addButton.getAttribute('data-kt-add-line');
    var target = document.getElementById(targetId);
    var template = document.getElementById(targetId + '-template');
    if (!target || !template) {
      return;
    }

    var index = target.querySelectorAll('tr[data-kt-line-row]').length;
    var html = template.innerHTML.replace(/__INDEX__/g, index);
    target.insertAdjacentHTML('beforeend', html);
    if (window.$ && window.$.fn && window.$.fn.selectpicker) {
      window.$(target).find('.selectpicker').selectpicker('render');
      window.$(target).find('.selectpicker').selectpicker('refresh');
    }
    return;
  }

  var removeButton = event.target.closest('[data-kt-remove-line]');
  if (removeButton) {
    event.preventDefault();
    var row = removeButton.closest('tr');
    if (row) {
      row.remove();
    }
  }
});

document.addEventListener('change', function (event) {
  var invoiceSelect = event.target.closest('#kt_inventory_invoice_id');
  if (invoiceSelect) {
    var selectedOption = invoiceSelect.options[invoiceSelect.selectedIndex];
    var customerId = selectedOption ? selectedOption.getAttribute('data-customer-id') : '';
    var customerSelect = document.getElementById('kt_inventory_customer_id');
    if (customerSelect && customerId) {
      customerSelect.value = customerId;
      if (window.$ && window.$.fn && window.$.fn.selectpicker) {
        window.$(customerSelect).selectpicker('refresh');
      }
    }
    return;
  }

  // 2. Item selection changes lot/batch requirements or loads batches
  var itemSelect = event.target.closest('.item-select');
  if (itemSelect) {
    var row = itemSelect.closest('tr');
    if (!row) return;

    var selectedOption = itemSelect.options[itemSelect.selectedIndex];
    var trackLot = selectedOption ? selectedOption.getAttribute('data-track-lot') : '0';
    var trackSerial = selectedOption ? selectedOption.getAttribute('data-track-serial') : '0';

    // Receipt form check
    var lotInput = row.querySelector('.lot-number-input');
    var expiryInput = row.querySelector('.expiry-date-input');
    var serialInput = row.querySelector('.serial-number-input');

    if (lotInput && expiryInput) {
      if (trackLot === '1') {
        lotInput.required = true;
        expiryInput.required = true;
        lotInput.closest('td').classList.add('has-warning');
        expiryInput.closest('td').classList.add('has-warning');
      } else {
        lotInput.required = false;
        expiryInput.required = false;
        lotInput.closest('td').classList.remove('has-warning');
        expiryInput.closest('td').classList.remove('has-warning');
      }
    }

    if (serialInput) {
      if (trackSerial === '1') {
        serialInput.required = true;
        serialInput.closest('td').classList.add('has-warning');
      } else {
        serialInput.required = false;
        serialInput.closest('td').classList.remove('has-warning');
      }
    }

    // Issue / Transfer / Adjustment form check
    var batchSelect = row.querySelector('.batch-select');
    if (batchSelect) {
      var warehouseSelect = document.getElementById('warehouse_id') || document.getElementById('from_warehouse_id');
      var warehouseId = warehouseSelect ? warehouseSelect.value : null;
      var selectedBatchId = batchSelect.getAttribute('data-selected-id') || batchSelect.value;
      batchSelect.removeAttribute('data-selected-id');
      ktInventoryLoadBatchesForSelect(itemSelect, batchSelect, warehouseId, selectedBatchId);
    }
    return;
  }

  // 3. Warehouse selection changes - reload batches for all rows
  var warehouseSelect = event.target.closest('#warehouse_id') || event.target.closest('#from_warehouse_id');
  if (warehouseSelect) {
    var warehouseId = warehouseSelect.value;
    var rows = document.querySelectorAll('tr[data-kt-line-row]');
    rows.forEach(function (row) {
      var itemSelect = row.querySelector('.item-select');
      var batchSelect = row.querySelector('.batch-select');
      if (itemSelect && batchSelect) {
        var selectedBatchId = batchSelect.value;
        ktInventoryLoadBatchesForSelect(itemSelect, batchSelect, warehouseId, selectedBatchId);
      }
    });
    return;
  }

  // 4. Batch selection changes - update expiry date
  var batchSelect = event.target.closest('.batch-select');
  if (batchSelect) {
    var row = batchSelect.closest('tr');
    if (!row) return;

    var expiryInput = row.querySelector('.expiry-date-input');
    if (expiryInput) {
      var selectedOption = batchSelect.options[batchSelect.selectedIndex];
      var expiryDate = selectedOption ? selectedOption.getAttribute('data-expiry') : '';
      expiryInput.value = expiryDate || '';
    }
    return;
  }
});

document.addEventListener('keydown', function (event) {
  var scanInput = event.target.closest('[data-kt-scan-input]');
  if (!scanInput || event.key !== 'Enter') {
    return;
  }

  event.preventDefault();
  ktInventoryHandleBarcodeScan(scanInput);
});

function ktInventoryHandleBarcodeScan(scanInput) {
  if (!window.$) {
    return;
  }

  var barcode = scanInput.value.trim();
  var form = scanInput.closest('form[data-kt-barcode-form]');
  if (!barcode || !form) {
    return;
  }

  var payload = {
    barcode: barcode,
    document_type: form.getAttribute('data-document-type') || ''
  };

  if (typeof csrfData !== 'undefined') {
    payload[csrfData.token_name] = csrfData.hash;
  }

  var ajaxUrl = ktInventoryNormalizeUrl(form.getAttribute('data-barcode-url'));
  console.log('Barcode scan payload:', payload);
  console.log('Requesting URL:', ajaxUrl);

  window.$.post(ajaxUrl, payload)
    .done(function (response) {
      console.log('Barcode scan response type:', typeof response);
      console.log('Barcode scan response raw:', response);
      if (typeof response === 'string') {
        try {
          response = JSON.parse(response);
        } catch (e) {
          console.error('Không thể phân tích phản hồi JSON:', e);
        }
      }
      console.log('Barcode scan response parsed:', response);
      if (!response || !response.success) {
        if (typeof alert_float === 'function') {
          alert_float('warning', response && response.message ? response.message : 'Không tìm thấy mã vạch.');
        }
        return;
      }

      ktInventoryApplyScannedItem(form, response);
      scanInput.value = '';
    })
    .fail(function (xhr) {
      console.error('Barcode scan fail (XHR):', xhr);
      var message = 'Không tìm thấy mã vạch.';
      if (xhr.responseJSON && xhr.responseJSON.message) {
        message = xhr.responseJSON.message;
      }
      if (typeof alert_float === 'function') {
        alert_float('warning', message);
      }
    });
}

function ktInventoryApplyScannedItem(form, itemData) {
  var documentType = form.getAttribute('data-document-type') || '';
  var lineContainerId = documentType + '-lines';
  var lineContainer = document.getElementById(lineContainerId);
  var template = document.getElementById(lineContainerId + '-template');
  var packageQty = parseFloat(itemData.package_quantity || 1);
  if (!lineContainer || !template) {
    return;
  }

  var existingRow = ktInventoryFindRowByItemId(lineContainer, itemData.inventory_item_id);
  if (!existingRow) {
    var index = lineContainer.querySelectorAll('tr[data-kt-line-row]').length;
    var html = template.innerHTML.replace(/__INDEX__/g, index);
    lineContainer.insertAdjacentHTML('beforeend', html);
    existingRow = lineContainer.querySelectorAll('tr[data-kt-line-row]')[index];
    if (window.$ && window.$.fn && window.$.fn.selectpicker) {
      window.$(existingRow).find('.selectpicker').selectpicker('render');
      window.$(existingRow).find('.selectpicker').selectpicker('refresh');
    }
  }

  var itemSelect = existingRow.querySelector('select[name*="[inventory_item_id]"]');
  var barcodeIdInput = existingRow.querySelector('input[name*="[barcode_id]"]');
  var scannedBarcodeInput = existingRow.querySelector('input[name*="[scanned_barcode]"]');
  
  var lotInput = existingRow.querySelector('.lot-number-input');
  var expiryInput = existingRow.querySelector('.expiry-date-input');
  var serialInput = existingRow.querySelector('.serial-number-input');
  var batchSelect = existingRow.querySelector('.batch-select');

  if (lotInput && itemData.lot_number) {
    lotInput.value = itemData.lot_number;
  }
  if (expiryInput && itemData.expiry_date) {
    expiryInput.value = itemData.expiry_date;
  }
  if (serialInput && itemData.serial_number) {
    serialInput.value = itemData.serial_number;
  }
  if (batchSelect && itemData.batch_id) {
    batchSelect.setAttribute('data-selected-id', itemData.batch_id);
  }

  if (itemSelect) {
    itemSelect.value = itemData.inventory_item_id;
    if (window.$ && window.$.fn && window.$.fn.selectpicker) {
      window.$(itemSelect).selectpicker('refresh');
    }
    itemSelect.dispatchEvent(new Event('change', { bubbles: true }));
  }
  if (barcodeIdInput) {
    barcodeIdInput.value = itemData.barcode_id || '';
  }
  if (scannedBarcodeInput) {
    scannedBarcodeInput.value = itemData.barcode || '';
  }

  var quantityInput = existingRow.querySelector('input[name*="[quantity]"]');
  var newQuantityInput = existingRow.querySelector('input[name*="[new_quantity]"]');
  var oldQuantityInput = existingRow.querySelector('input[name*="[old_quantity]"]');
  if (quantityInput) {
    quantityInput.value = (parseFloat(quantityInput.value || 0) + packageQty).toFixed(4).replace(/\.?0+$/, '');
  } else if (newQuantityInput) {
    newQuantityInput.value = (parseFloat(newQuantityInput.value || 0) + packageQty).toFixed(4).replace(/\.?0+$/, '');
    if (oldQuantityInput && oldQuantityInput.value === '') {
      oldQuantityInput.value = '0';
    }
  }

  if (itemData.batch_required) {
    var lotInput = existingRow.querySelector('.lot-number-input');
    var serialInput = existingRow.querySelector('.serial-number-input');
    var batchSelect = existingRow.querySelector('.batch-select');
    if (lotInput) {
      lotInput.focus();
      return;
    }
    if (batchSelect) {
      if (window.$ && window.$.fn && window.$.fn.selectpicker) {
        window.$(batchSelect).data('selectpicker').$button.focus();
      } else {
        batchSelect.focus();
      }
      return;
    }
    if (serialInput) {
      serialInput.focus();
      return;
    }
  }

  var noteInput = existingRow.querySelector('input[name*="[note]"]');
  if (noteInput) {
    noteInput.focus();
  }
}

function ktInventoryFindRowByItemId(container, itemId) {
  var rows = container.querySelectorAll('tr[data-kt-line-row]');
  for (var i = 0; i < rows.length; i++) {
    var select = rows[i].querySelector('select[name*="[inventory_item_id]"]');
    if (select && parseInt(select.value || '0', 10) === parseInt(itemId, 10)) {
      return rows[i];
    }
  }

  return null;
}

function ktInventoryLoadBatchesForSelect(itemSelectSelect, batchSelectSelect, warehouseId, selectedBatchId) {
  var itemId = itemSelectSelect.value;
  var defaultLabel = batchSelectSelect.options[0] ? batchSelectSelect.options[0].textContent : 'Select Batch';
  
  if (!itemId) {
    batchSelectSelect.innerHTML = '<option value="">' + defaultLabel + '</option>';
    batchSelectSelect.disabled = true;
    if (window.$ && window.$.fn && window.$.fn.selectpicker) {
      window.$(batchSelectSelect).selectpicker('refresh');
    }
    return;
  }

  var selectedOption = itemSelectSelect.options[itemSelectSelect.selectedIndex];
  var trackLot = selectedOption ? selectedOption.getAttribute('data-track-lot') : '0';
  if (trackLot !== '1') {
    batchSelectSelect.innerHTML = '<option value="">' + defaultLabel + '</option>';
    batchSelectSelect.disabled = true;
    if (window.$ && window.$.fn && window.$.fn.selectpicker) {
      window.$(batchSelectSelect).selectpicker('refresh');
    }
    return;
  }

  batchSelectSelect.disabled = false;

  var barcodeForm = document.querySelector('form[data-barcode-url]');
  var baseUrl = barcodeForm ? barcodeForm.getAttribute('data-barcode-url') : '';
  var url = baseUrl ? baseUrl.replace('ajax_find_item_by_barcode', 'ajax_get_batches_by_item/' + itemId) : '/admin/kt_inventory/ajax_get_batches_by_item/' + itemId;
  url = ktInventoryNormalizeUrl(url);
  if (warehouseId) {
    url += '?warehouse_id=' + warehouseId;
  }

  window.$.getJSON(url, function (response) {
    var html = '<option value="">' + defaultLabel + '</option>';
    var hasSelected = false;
    var firstReleasedId = null;
    if (response && response.success && response.batches) {
      response.batches.forEach(function (b) {
        if (b.qc_status === 'released' || b.id == selectedBatchId) {
          var isSelected = '';
          if (selectedBatchId && b.id == selectedBatchId) {
            isSelected = ' selected';
            hasSelected = true;
          }
          if (b.qc_status === 'released' && firstReleasedId === null) {
            firstReleasedId = b.id;
          }
          var optLabel = b.lot_number;
          if (b.expiry_date) {
            optLabel += ' (' + b.expiry_date + ')';
          }
          optLabel += ' [Stock: ' + parseFloat(b.available_qty || b.available_qty || 0) + ']';
          html += '<option value="' + b.id + '"' + isSelected + ' data-expiry="' + (b.expiry_date || '') + '">' + optLabel + '</option>';
        }
      });
    }
    batchSelectSelect.innerHTML = html;
    
    if (!hasSelected && firstReleasedId !== null) {
      batchSelectSelect.value = firstReleasedId;
    }
    
    if (window.$ && window.$.fn && window.$.fn.selectpicker) {
      window.$(batchSelectSelect).selectpicker('refresh');
    }
    
    batchSelectSelect.dispatchEvent(new Event('change', { bubbles: true }));
  });
}
