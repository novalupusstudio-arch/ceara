document.addEventListener("submit", (event) => {
  const form = event.target;
  const button = form.querySelector('button[type="submit"]');
  if (!button || button.dataset.busy === "1") {
    return;
  }
  button.dataset.busy = "1";
  button.classList.add("is-busy");
});

document.addEventListener("DOMContentLoaded", () => {
  const form = document.querySelector("[data-processing-form]");
  if (!form) {
    return;
  }

  const processors = JSON.parse(form.dataset.processors || "[]");
  const searchInput = form.querySelector("[data-customer-search]");
  const searchLabel = form.querySelector("[data-search-text]");
  const resultsBox = form.querySelector("[data-lookup-results]");
  const newCustomerButton = form.querySelector("[data-new-customer-button]");
  const existingCustomerId = form.querySelector("[data-existing-customer-id]");
  const forceNewCustomer = form.querySelector("[data-force-new-customer]");
  const customerTypeInputs = form.querySelectorAll("[data-customer-type]");
  const customerName = form.querySelector("[data-customer-name]");
  const customerPhone = form.querySelector("[data-customer-phone]");
  const customerAddress = form.querySelector("[data-customer-address]");
  const customerNamePj = form.querySelector("[data-customer-name-pj]");
  const customerAddressPj = form.querySelector("[data-customer-address-pj]");
  const customerPhonePj = form.querySelector("[data-customer-phone-pj]");
  const customerCui = form.querySelector("[data-customer-cui]");
  const customerRepresentative = form.querySelector("[data-customer-representative]");
  const processorSelect = form.querySelector("[data-processor-select]");
  const processingPrice = form.querySelector("[data-processing-price]");
  const processingShrinkage = form.querySelector("[data-processing-shrinkage]");
  const nameLabel = form.querySelector("[data-name-label]");
  const phoneLabel = form.querySelector("[data-phone-label]");
  const pfFields = form.querySelectorAll("[data-pf-field]");
  const pjFields = form.querySelectorAll("[data-pj-field]");

  let lookupTimer = null;

  function customerType() {
    const checked = Array.from(customerTypeInputs).find((input) => input.checked);
    return checked ? checked.value : "PF";
  }

  function resetLookupResults() {
    resultsBox.innerHTML = "";
    resultsBox.hidden = true;
  }

  function setCustomerInputsReadOnly(readOnly) {
    [
      customerName,
      customerPhone,
      customerAddress,
      customerNamePj,
      customerAddressPj,
      customerPhonePj,
      customerCui,
      customerRepresentative,
    ].forEach((input) => {
      if (input) {
        input.readOnly = readOnly;
      }
    });
  }

  function switchCustomerMode(nextType) {
    const isPJ = nextType === "PJ";
    nameLabel.textContent = "Nume client";
    phoneLabel.textContent = "Telefon";
    customerName.placeholder = "Nume client";
    customerAddress.placeholder = "Adresa client";
    searchLabel.textContent = isPJ ? "Cautare dupa CUI" : "Cautare dupa telefon";
    searchInput.placeholder = isPJ ? "RO123456" : "07xxxxxxxx";
    customerName.required = !isPJ;
    customerPhone.required = !isPJ;
    customerAddress.required = !isPJ;
    customerCui.required = isPJ;
    customerRepresentative.required = isPJ;
    customerNamePj.required = isPJ;
    customerAddressPj.required = isPJ;
    customerPhonePj.required = isPJ;

    pfFields.forEach((field) => {
      field.hidden = isPJ;
      field.classList.toggle("is-hidden", isPJ);
      field.style.display = isPJ ? "none" : "";
    });
    pjFields.forEach((field) => {
      field.hidden = !isPJ;
      field.classList.toggle("is-hidden", !isPJ);
      field.style.display = isPJ ? "" : "none";
    });

    existingCustomerId.value = "0";
    forceNewCustomer.value = "0";
    setCustomerInputsReadOnly(false);
    resetLookupResults();
    searchInput.value = "";
    customerName.value = "";
    customerPhone.value = "";
    customerAddress.value = "";
    customerNamePj.value = "";
    customerAddressPj.value = "";
    customerPhonePj.value = "";
    customerCui.value = "";
    customerRepresentative.value = "";
  }

  function renderProcessorValues() {
    const selectedId = Number(processorSelect.value || 0);
    const processor = processors.find((item) => item.id === selectedId);
    const cents = processor ? processor.processing_price_cents : 0;
    const shrinkage = processor ? Number(processor.exchange_shrinkage_pct) : 0;
    processingPrice.value = `${(cents / 100).toFixed(2)} lei`;
    processingShrinkage.value = shrinkage.toFixed(3);
  }

  function applyCustomer(customer) {
    existingCustomerId.value = String(customer.id);
    forceNewCustomer.value = "0";
    const isPJ = customer.customer_type === "PJ";
    if (isPJ) {
      customerNamePj.value = customer.name || "";
      customerAddressPj.value = customer.address || "";
      customerPhonePj.value = customer.phone || "";
      customerCui.value = customer.cui || "";
      customerRepresentative.value = customer.representative || "";
    } else {
      customerName.value = customer.name || "";
      customerPhone.value = customer.phone || "";
      customerAddress.value = customer.address || "";
    }
    setCustomerInputsReadOnly(true);
    resetLookupResults();
  }

  function renderResults(customers) {
    if (!customers.length) {
      resultsBox.innerHTML = '<div class="lookup-empty">Nu am gasit clienti.</div>';
      resultsBox.hidden = false;
      return;
    }

    resultsBox.innerHTML = "";
    customers.forEach((customer) => {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "lookup-option";
      button.innerHTML = `
        <strong>${customer.name}</strong>
        <span>${customer.customer_type === "PJ" ? customer.cui : customer.phone}</span>
        <small>${customer.address || ""}</small>
      `;
      button.addEventListener("click", () => applyCustomer(customer));
      resultsBox.appendChild(button);
    });
    resultsBox.hidden = false;
  }

  function lookupCustomers() {
    const term = searchInput.value.trim();
    if (term.length < 2) {
      resetLookupResults();
      return;
    }

    const params = new URLSearchParams({
      page: "customer_lookup",
      customer_type: customerType(),
      term,
    });

    fetch(`index.php?${params.toString()}`, {
      headers: { Accept: "application/json" },
    })
      .then((response) => response.json())
      .then((payload) => {
        renderResults(payload.customers || []);
      })
      .catch(() => {
        resultsBox.innerHTML = '<div class="lookup-empty">Cautarea nu a reusit.</div>';
        resultsBox.hidden = false;
      });
  }

  customerTypeInputs.forEach((input) => {
    input.addEventListener("change", () => switchCustomerMode(input.value));
  });

  processorSelect.addEventListener("change", renderProcessorValues);

  searchInput.addEventListener("input", () => {
    existingCustomerId.value = "0";
    forceNewCustomer.value = "0";
    setCustomerInputsReadOnly(false);
    window.clearTimeout(lookupTimer);
    lookupTimer = window.setTimeout(lookupCustomers, 220);
  });

  newCustomerButton.addEventListener("click", () => {
    existingCustomerId.value = "0";
    forceNewCustomer.value = "1";
    setCustomerInputsReadOnly(false);
    resetLookupResults();
    customerName.value = "";
    customerPhone.value = "";
    customerAddress.value = "";
    customerNamePj.value = "";
    customerAddressPj.value = "";
    customerPhonePj.value = "";
    customerCui.value = "";
    customerRepresentative.value = "";
    searchInput.value = "";
    customerName.focus();
  });

  switchCustomerMode(customerType());
  if (processorSelect.options.length > 0 && !processorSelect.value) {
    processorSelect.value = processorSelect.options[0].value;
  }
  renderProcessorValues();
});
