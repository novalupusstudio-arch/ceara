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
  function formatKg(value) {
    return `${Number(value || 0).toFixed(3).replace(".", ",")} kg`;
  }

  const factoryForm = document.querySelector("[data-factory-form]");
  if (factoryForm) {
    const totalWax = factoryForm.querySelector("[data-factory-total-wax]");
    const totalReject = factoryForm.querySelector("[data-factory-total-reject]");
    const totalCost = factoryForm.querySelector("[data-factory-total-cost]");
    const totalFoundation = factoryForm.querySelector("[data-factory-total-foundation]");
    const rows = factoryForm.querySelectorAll("[data-factory-row]");

    function parseKg(value) {
      return Number(String(value || "0").replace(",", "."));
    }

    function renderFactoryTotals() {
      let waxKg = 0;
      let rejectKg = 0;
      let foundationKg = 0;
      let costCents = 0;

      rows.forEach((row) => {
        const qtyInput = row.querySelector("[data-factory-qty]");
        const rejectInput = row.querySelector("[data-factory-reject-qty]");
        const rowCost = row.querySelector("[data-row-cost]");
        const rowFoundation = row.querySelector("[data-row-foundation]");
        const priceCents = Number(row.dataset.priceCents || factoryForm.dataset.priceCents || 0);
        const shrinkagePct = Number(row.dataset.shrinkagePct || factoryForm.dataset.shrinkagePct || 0);
        const maxWaxKg = Number(row.dataset.maxWaxKg || 0);
        let qtyKg = parseKg(qtyInput.value);
        let rowRejectKg = parseKg(rejectInput.value);

        if (!Number.isFinite(qtyKg) || qtyKg < 0) {
          qtyKg = 0;
        }
        if (!Number.isFinite(rowRejectKg) || rowRejectKg < 0) {
          rowRejectKg = 0;
        }
        if (qtyKg > maxWaxKg) {
          qtyKg = maxWaxKg;
          qtyInput.value = maxWaxKg.toFixed(3);
        }
        if (qtyKg + rowRejectKg > maxWaxKg) {
          rowRejectKg = Math.max(0, maxWaxKg - qtyKg);
          rejectInput.value = rowRejectKg.toFixed(3);
        }

        const rowCostCents = Math.max(0, Math.round(qtyKg * priceCents));
        const rowFoundationKg = Math.max(0, qtyKg * (1 - (shrinkagePct / 100)));

        waxKg += qtyKg;
        rejectKg += rowRejectKg;
        foundationKg += rowFoundationKg;
        costCents += rowCostCents;

        rowCost.textContent = `${(rowCostCents / 100).toFixed(2)} lei`;
        rowFoundation.textContent = formatKg(rowFoundationKg);
      });

      totalWax.value = formatKg(waxKg);
      totalReject.value = formatKg(rejectKg);
      totalCost.value = `${(costCents / 100).toFixed(2)} lei`;
      totalFoundation.value = formatKg(foundationKg);
    }

    factoryForm.querySelectorAll("[data-factory-qty], [data-factory-reject-qty]").forEach((input) => {
      input.addEventListener("input", renderFactoryTotals);
      input.addEventListener("change", renderFactoryTotals);
    });

    renderFactoryTotals();
    return;
  }

  const exchangeForm = document.querySelector("[data-exchange-form]");
  if (exchangeForm) {
    const qtyInput = exchangeForm.querySelector("[data-exchange-qty]");
    const foundationOutput = exchangeForm.querySelector("[data-exchange-foundation]");
    const serviceOutput = exchangeForm.querySelector("[data-exchange-service]");
    const maxWaxKg = Number(exchangeForm.dataset.maxWaxKg || 0);
    const priceCents = Number(exchangeForm.dataset.priceCents || 0);
    const shrinkagePct = Number(exchangeForm.dataset.shrinkagePct || 0);

    function parseKg(value) {
      return Number(String(value || "0").replace(",", "."));
    }

    function renderExchangeValues() {
      let waxKg = parseKg(qtyInput.value);
      if (!Number.isFinite(waxKg) || waxKg < 0) {
        waxKg = 0;
      }

      if (waxKg > maxWaxKg) {
        waxKg = maxWaxKg;
        qtyInput.value = maxWaxKg.toFixed(3);
      }

      const foundationKg = Math.max(0, waxKg * (1 - (shrinkagePct / 100)));
      const serviceCents = Math.max(0, Math.round(waxKg * priceCents));

      foundationOutput.value = formatKg(foundationKg);
      serviceOutput.value = `${(serviceCents / 100).toFixed(2)} lei`;
    }

    qtyInput.addEventListener("input", renderExchangeValues);
    qtyInput.addEventListener("change", renderExchangeValues);
    renderExchangeValues();
  }

  const returnForm = document.querySelector("[data-return-form]");
  if (returnForm) {
    const qtyInput = returnForm.querySelector("[data-return-qty]");
    const availableOutput = returnForm.querySelector("[data-return-available]");
    const maxReturnKg = Number(returnForm.dataset.maxReturnKg || 0);

    function parseKg(value) {
      return Number(String(value || "0").replace(",", "."));
    }

    function clampReturnValue() {
      let waxKg = parseKg(qtyInput.value);
      if (!Number.isFinite(waxKg) || waxKg < 0) {
        waxKg = 0;
      }

      if (waxKg > maxReturnKg) {
        waxKg = maxReturnKg;
        qtyInput.value = maxReturnKg.toFixed(3);
      }

      if (availableOutput) {
        availableOutput.value = formatKg(maxReturnKg);
      }
    }

    qtyInput.addEventListener("input", clampReturnValue);
    qtyInput.addEventListener("change", clampReturnValue);
    clampReturnValue();
  }

  const purchaseForm = document.querySelector("[data-purchase-form]");
  if (purchaseForm) {
    const typeInputs = purchaseForm.querySelectorAll("[data-purchase-type]");
    const identifierField = purchaseForm.querySelector("[data-purchase-identifier-field]");
    const cuiField = purchaseForm.querySelector("[data-purchase-cui-field]");
    const positionField = purchaseForm.querySelector("[data-purchase-position-field]");
    const docDateField = purchaseForm.querySelector("[data-purchase-doc-date-field]");
    const countySelect = purchaseForm.querySelector("[data-purchase-county]");
    const localitySelect = purchaseForm.querySelector("[data-purchase-locality]");
    const countyName = purchaseForm.querySelector("[data-purchase-county-name]");
    const localityName = purchaseForm.querySelector("[data-purchase-locality-name]");
    const postalCode = purchaseForm.querySelector("[data-purchase-postal-code]");
    const grossInput = purchaseForm.querySelector("[data-purchase-gross]");
    const shrinkageInput = purchaseForm.querySelector("[data-purchase-shrinkage]");
    const priceInput = purchaseForm.querySelector("[data-purchase-price]");
    const totalOutput = purchaseForm.querySelector("[data-purchase-total]");
    const netOutput = purchaseForm.querySelector("[data-purchase-net]");
    let purchaseLocalitySeq = 0;

    function selectedPurchaseType() {
      const checked = Array.from(typeInputs).find((input) => input.checked);
      return checked ? checked.value : "PF";
    }

    function parseDecimal(value) {
      return Number(String(value || "0").replace(",", "."));
    }

    function renderPurchaseTotals() {
      const grossKg = Math.max(0, parseDecimal(grossInput.value));
      const shrinkage = Math.max(0, parseDecimal(shrinkageInput.value));
      const price = Math.max(0, parseDecimal(priceInput.value));
      const netKg = Math.max(0, grossKg * (1 - (shrinkage / 100)));
      totalOutput.value = `${(grossKg * price).toFixed(2)} lei`;
      netOutput.value = formatKg(netKg);
    }

    function syncPurchaseLocation() {
      const countyOption = countySelect.selectedOptions[0];
      const localityOption = localitySelect.selectedOptions[0];
      countyName.value = countyOption && countyOption.value ? (countyOption.dataset.name || countyOption.textContent || "") : "";
      localityName.value = localityOption && localityOption.value ? (localityOption.dataset.name || localityOption.textContent || "") : "";
      postalCode.value = localityOption && localityOption.value ? (localityOption.dataset.postalCode || "") : "";
    }

    function populatePurchaseCounties() {
      return fetch("index.php?page=counties_lookup", { headers: { Accept: "application/json" } })
        .then((response) => response.json())
        .then((payload) => {
          countySelect.innerHTML = '<option value="">Alege judet</option>';
          (payload.counties || []).forEach((county) => {
            const option = document.createElement("option");
            option.value = county.county_code;
            option.textContent = county.name;
            option.dataset.name = county.name;
            countySelect.appendChild(option);
          });
        })
        .catch(() => {});
    }

    function populatePurchaseLocalities(countyCode) {
      const seq = ++purchaseLocalitySeq;
      localitySelect.innerHTML = '<option value="">Alege localitate</option>';
      localitySelect.disabled = !countyCode;
      if (!countyCode) {
        syncPurchaseLocation();
        return;
      }

      const params = new URLSearchParams({ page: "localities_lookup", county_code: countyCode });
      fetch(`index.php?${params.toString()}`, { headers: { Accept: "application/json" } })
        .then((response) => response.json())
        .then((payload) => {
          if (seq !== purchaseLocalitySeq) {
            return;
          }
          (payload.localities || []).forEach((locality) => {
            const option = document.createElement("option");
            option.value = locality.siruta_code;
            option.textContent = locality.display_name || locality.name;
            option.dataset.name = locality.name || "";
            option.dataset.postalCode = locality.postal_code || "";
            localitySelect.appendChild(option);
          });
          syncPurchaseLocation();
        })
        .catch(() => {});
    }

    function switchPurchaseType() {
      const type = selectedPurchaseType();
      const isCompany = type === "PJ/PFA";
      identifierField.hidden = isCompany;
      identifierField.classList.toggle("is-hidden", isCompany);
      cuiField.hidden = !isCompany;
      cuiField.classList.toggle("is-hidden", !isCompany);
      positionField.hidden = isCompany;
      positionField.classList.toggle("is-hidden", isCompany);
      docDateField.hidden = !isCompany;
      docDateField.classList.toggle("is-hidden", !isCompany);
      purchaseForm.querySelector("[data-purchase-doc-series]").placeholder = isCompany ? "STU" : (type === "PF" ? "BA-2026-GEST1" : "CP-2026");
      purchaseForm.querySelector("[data-purchase-doc-number]").placeholder = isCompany ? "Numar factura" : "Numar document";
    }

    typeInputs.forEach((input) => input.addEventListener("change", switchPurchaseType));
    [grossInput, shrinkageInput, priceInput].forEach((input) => {
      input.addEventListener("input", renderPurchaseTotals);
      input.addEventListener("change", renderPurchaseTotals);
    });
    countySelect.addEventListener("change", () => populatePurchaseLocalities(countySelect.value));
    localitySelect.addEventListener("change", syncPurchaseLocation);

    populatePurchaseCounties();
    switchPurchaseType();
    renderPurchaseTotals();
    return;
  }

  const form = document.querySelector("[data-processing-form]");
  if (!form) {
    const board = document.querySelector("[data-lot-board]");
    if (!board) {
      return;
    }

    const filter = document.querySelector("[data-lot-filter]");
    if (!filter) {
      return;
    }

    const checkboxes = filter.querySelectorAll('input[type="checkbox"][name="status[]"]');
    const rows = board.querySelectorAll('tbody tr[data-lot-status]');

    function applyLotFilter() {
      const active = new Set(
        Array.from(checkboxes)
          .filter((checkbox) => checkbox.checked)
          .map((checkbox) => checkbox.value)
      );

      rows.forEach((row) => {
        row.hidden = !active.has(row.dataset.lotStatus);
      });
    }

    checkboxes.forEach((checkbox) => {
      checkbox.addEventListener("change", applyLotFilter);
    });

    applyLotFilter();
    return;
  }

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
  const customerIdentifier = form.querySelector("[data-customer-identifier]");
  const customerNamePj = form.querySelector("[data-customer-name-pj]");
  const customerAddressPj = form.querySelector("[data-customer-address-pj]");
  const customerPhonePj = form.querySelector("[data-customer-phone-pj]");
  const customerCui = form.querySelector("[data-customer-cui]");
  const customerRepresentative = form.querySelector("[data-customer-representative]");
  const customerCounty = form.querySelector("[data-customer-county]");
  const customerLocality = form.querySelector("[data-customer-locality]");
  const customerCountyName = form.querySelector("[data-customer-county-name]");
  const customerLocalityName = form.querySelector("[data-customer-locality-name]");
  const customerPostalCode = form.querySelector("[data-customer-postal-code]");
  const customerRegistryNumber = form.querySelector("[data-customer-registry-number]");
  const customerLegalForm = form.querySelector("[data-customer-legal-form]");
  const customerVatStatus = form.querySelector("[data-customer-vat-status]");
  const customerExternalSource = form.querySelector("[data-customer-external-source]");
  const customerExternalCheckedAt = form.querySelector("[data-customer-external-checked-at]");
  const processorSelect = form.querySelector("[data-processor-select]");
  const processingPrice = form.querySelector("[data-processing-price]");
  const processingShrinkage = form.querySelector("[data-processing-shrinkage]");
  const processingExchange = form.querySelector("[data-processing-exchange]");
  const processingCost = form.querySelector("[data-processing-cost]");
  const grossInput = form.querySelector('input[name="gross_kg"]');
  const nameLabel = form.querySelector("[data-name-label]");
  const phoneLabel = form.querySelector("[data-phone-label]");
  const pfRows = form.querySelectorAll("[data-pf-row]");
  const pjRows = form.querySelectorAll("[data-pj-row]");
  const pfAddressField = form.querySelector("[data-pf-address-field]");
  const pjAddressField = form.querySelector("[data-pj-address-field]");

  let lookupTimer = null;
  let anafLookupSeq = 0;
  let localityLookupSeq = 0;
  const countiesReady = populateCounties();

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
      customerIdentifier,
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

  function setLegalMeta(customer) {
    customerRegistryNumber.value = customer.registry_number || "";
    customerLegalForm.value = customer.legal_form || "";
    customerVatStatus.value = customer.vat_status || "";
    customerExternalSource.value = customer.external_source || "";
    customerExternalCheckedAt.value = customer.external_checked_at || "";
  }

  function syncLocationHiddenFields() {
    const countyOption = customerCounty ? customerCounty.selectedOptions[0] : null;
    const localityOption = customerLocality ? customerLocality.selectedOptions[0] : null;
    customerCountyName.value = countyOption && countyOption.value ? (countyOption.dataset.name || countyOption.textContent || "") : "";
    customerLocalityName.value = localityOption && localityOption.value ? (localityOption.dataset.name || localityOption.textContent || "") : "";
    customerPostalCode.value = localityOption && localityOption.value ? (localityOption.dataset.postalCode || customerPostalCode.value || "") : customerPostalCode.value;
  }

  function resetLocationFields() {
    if (customerCounty) {
      customerCounty.value = "";
    }
    if (customerLocality) {
      customerLocality.innerHTML = '<option value="">Alege localitate</option>';
      customerLocality.disabled = true;
    }
    customerCountyName.value = "";
    customerLocalityName.value = "";
    customerPostalCode.value = "";
  }

  function populateCounties() {
    if (!customerCounty) {
      return Promise.resolve();
    }
    return fetch("index.php?page=counties_lookup", { headers: { Accept: "application/json" } })
      .then((response) => response.json())
      .then((payload) => {
        const selected = customerCounty.value;
        customerCounty.innerHTML = '<option value="">Alege judet</option>';
        (payload.counties || []).forEach((county) => {
          const option = document.createElement("option");
          option.value = county.county_code;
          option.textContent = county.name;
          option.dataset.name = county.name;
          customerCounty.appendChild(option);
        });
        customerCounty.value = selected;
      })
      .catch(() => {});
  }

  function populateLocalities(countyCode, selectedSiruta = "", selectedName = "") {
    const lookupSeq = ++localityLookupSeq;
    if (!customerLocality) {
      return Promise.resolve();
    }
    customerLocality.innerHTML = '<option value="">Alege localitate</option>';
    customerLocality.disabled = !countyCode;
    if (!countyCode) {
      syncLocationHiddenFields();
      return Promise.resolve();
    }

    const params = new URLSearchParams({ page: "localities_lookup", county_code: countyCode });
    return fetch(`index.php?${params.toString()}`, { headers: { Accept: "application/json" } })
      .then((response) => response.json())
      .then((payload) => {
        if (lookupSeq !== localityLookupSeq) {
          return;
        }
        (payload.localities || []).forEach((locality) => {
          const option = document.createElement("option");
          option.value = locality.siruta_code;
          option.textContent = locality.display_name || locality.name;
          option.dataset.name = locality.name || "";
          option.dataset.postalCode = locality.postal_code || "";
          customerLocality.appendChild(option);
        });
        if (selectedSiruta) {
          customerLocality.value = String(selectedSiruta);
        }
        if (!customerLocality.value && selectedName) {
          const normalized = String(selectedName)
            .toLowerCase()
            .replace(/^(municipiul|orasul|oras|comuna|satul|sat)\s+/i, "")
            .replace(/\s+/g, " ")
            .trim();
          const option = Array.from(customerLocality.options).find((item) => {
            const candidate = String(item.dataset.name || item.textContent || "")
              .toLowerCase()
              .replace(/^(municipiul|orasul|oras|comuna|satul|sat)\s+/i, "")
              .replace(/\s+/g, " ")
              .trim();
            return candidate === normalized;
          });
          if (option) {
            customerLocality.value = option.value;
          }
        }
        syncLocationHiddenFields();
      })
      .catch(() => {});
  }

  function applyCustomerLocation(customer) {
    if (!customerCounty) {
      return;
    }
    countiesReady.finally(() => {
      customerCounty.value = customer.county_code || "";
      if (!customerCounty.value && customer.county_name) {
        const normalizedCounty = String(customer.county_name)
          .toLowerCase()
          .replace(/\s+/g, " ")
          .trim();
        const countyOption = Array.from(customerCounty.options).find((option) => {
          return (option.dataset.name || option.textContent || "")
            .toLowerCase()
            .replace(/\s+/g, " ")
            .trim() === normalizedCounty;
        });
        if (countyOption) {
          customerCounty.value = countyOption.value;
        }
      }
      customerCountyName.value = customer.county_name || "";
      customerPostalCode.value = customer.postal_code || "";
      populateLocalities(customerCounty.value || customer.county_code || "", customer.locality_siruta || "", customer.locality_name || customer.locality_display_name || "");
    });
  }

  function switchCustomerMode(nextType) {
    const isPJ = nextType === "PJ";
    nameLabel.textContent = "Nume client";
    phoneLabel.textContent = "Telefon";
    customerName.placeholder = "Nume client";
    customerAddress.placeholder = "Adresa client";
    searchLabel.textContent = isPJ ? "Cautare dupa CUI" : "Cautare dupa telefon/CNP/CI";
    searchInput.placeholder = isPJ ? "RO123456" : "Telefon, CNP sau CI";
    customerName.required = !isPJ;
    customerPhone.required = !isPJ;
    customerAddress.required = !isPJ;
    customerCui.required = isPJ;
    customerRepresentative.required = isPJ;
    customerNamePj.required = isPJ;
    customerAddressPj.required = isPJ;
    customerPhonePj.required = isPJ;

    pfRows.forEach((row) => {
      row.hidden = isPJ;
      row.classList.toggle("is-hidden", isPJ);
    });
    pjRows.forEach((row) => {
      row.hidden = !isPJ;
      row.classList.toggle("is-hidden", !isPJ);
    });
    if (pfAddressField) {
      pfAddressField.hidden = isPJ;
      pfAddressField.classList.toggle("is-hidden", isPJ);
    }
    if (pjAddressField) {
      pjAddressField.hidden = !isPJ;
      pjAddressField.classList.toggle("is-hidden", !isPJ);
    }

    existingCustomerId.value = "0";
    forceNewCustomer.value = "0";
    setCustomerInputsReadOnly(false);
    resetLookupResults();
    searchInput.value = "";
    customerName.value = "";
    customerPhone.value = "";
    customerAddress.value = "";
    customerIdentifier.value = "";
    customerNamePj.value = "";
    customerAddressPj.value = "";
    customerPhonePj.value = "";
    customerCui.value = "";
    customerRepresentative.value = "";
    resetLocationFields();
    setLegalMeta({});
    newCustomerButton.textContent = isPJ ? "Preia date ANAF" : "Client nou";
  }

  function renderProcessorValues() {
    const grossValue = Number(String(grossInput.value || "0").replace(",", "."));
    const priceLei = Number(String(processingPrice.value || "0").replace(",", "."));
    const cents = Math.max(0, Math.round(priceLei * 100));
    const shrinkage = Number(String(processingShrinkage.value || "0").replace(",", "."));
    const exchangeKg = Math.max(0, grossValue * (1 - (shrinkage / 100)));
    const processingKg = Math.max(0, grossValue);
    const costCents = Math.max(0, Math.round(processingKg * cents));
    processingExchange.value = formatKg(exchangeKg);
    processingCost.value = `${(costCents / 100).toFixed(2)} lei`;
  }
  function applyCustomer(customer) {
    existingCustomerId.value = customer.id ? String(customer.id) : "0";
    forceNewCustomer.value = customer.id ? "0" : "1";
    const isPJ = customer.customer_type === "PJ";
    if (isPJ) {
      customerNamePj.value = customer.name || "";
      customerAddressPj.value = customer.address || "";
      customerPhonePj.value = customer.phone || "";
      customerCui.value = customer.identifier || customer.cui || "";
      customerRepresentative.value = customer.representative || "";
    } else {
      customerName.value = customer.name || "";
      customerPhone.value = customer.phone || "";
      customerAddress.value = customer.address || "";
      customerIdentifier.value = customer.identifier || customer.cui || "";
    }
    applyCustomerLocation(customer);
    setLegalMeta(customer);
    setCustomerInputsReadOnly(false);
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
        <span>${customer.customer_type === "PJ" ? (customer.identifier || customer.cui || "") : [customer.phone, customer.identifier || customer.cui || ""].filter(Boolean).join(" / ")}</span>
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
  grossInput.addEventListener("input", renderProcessorValues);
  processingPrice.addEventListener("input", renderProcessorValues);
  processingShrinkage.addEventListener("input", renderProcessorValues);

  searchInput.addEventListener("input", () => {
    existingCustomerId.value = "0";
    forceNewCustomer.value = "0";
    setCustomerInputsReadOnly(false);
    if (customerType() === "PJ") {
      anafLookupSeq += 1;
      customerNamePj.value = "";
      customerAddressPj.value = "";
      customerPhonePj.value = "";
      customerCui.value = searchInput.value.trim();
      customerRepresentative.value = "";
      resetLocationFields();
      setLegalMeta({});
    }
    window.clearTimeout(lookupTimer);
    lookupTimer = window.setTimeout(lookupCustomers, 220);
  });

  newCustomerButton.addEventListener("click", () => {
    if (customerType() === "PJ") {
      const cui = (customerCui.value || searchInput.value || "").trim();
      if (!cui) {
        searchInput.focus();
        return;
      }

      const currentLookup = ++anafLookupSeq;
      existingCustomerId.value = "0";
      forceNewCustomer.value = "1";
      customerNamePj.value = "";
      customerAddressPj.value = "";
      customerPhonePj.value = "";
      customerCui.value = cui;
      customerRepresentative.value = "";
      resetLocationFields();
      setLegalMeta({});
      resetLookupResults();
      newCustomerButton.disabled = true;
      newCustomerButton.textContent = "Preiau...";
      fetch(`index.php?page=anaf_company_lookup&cui=${encodeURIComponent(cui)}`, {
        headers: { Accept: "application/json" },
      })
        .then((response) => response.json().then((payload) => ({ ok: response.ok, payload })))
        .then(({ ok, payload }) => {
          if (currentLookup !== anafLookupSeq) {
            return;
          }
          if (!ok) {
            throw new Error(payload.error || "Preluarea ANAF nu a reusit.");
          }
          setCustomerInputsReadOnly(false);
          resetLookupResults();
          applyCustomer(payload.company || {});
          customerPhonePj.focus();
        })
        .catch((error) => {
          if (currentLookup !== anafLookupSeq) {
            return;
          }
          resultsBox.innerHTML = `<div class="lookup-empty">${error.message || "Preluarea ANAF nu a reusit."}</div>`;
          resultsBox.hidden = false;
        })
        .finally(() => {
          if (currentLookup !== anafLookupSeq) {
            return;
          }
          newCustomerButton.disabled = false;
          newCustomerButton.textContent = "Preia date ANAF";
        });
      return;
    }

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
    resetLocationFields();
    setLegalMeta({});
    searchInput.value = "";
    customerName.focus();
  });

  if (customerCounty) {
    customerCounty.addEventListener("change", () => {
      customerPostalCode.value = "";
      customerLocalityName.value = "";
      syncLocationHiddenFields();
      populateLocalities(customerCounty.value);
    });
  }
  if (customerLocality) {
    customerLocality.addEventListener("change", syncLocationHiddenFields);
  }

  switchCustomerMode(customerType());
  if (processorSelect.options.length > 0 && !processorSelect.value) {
    processorSelect.value = processorSelect.options[0].value;
  }
  applyProcessorDefaults();
});
