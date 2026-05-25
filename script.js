// Ti-page frontend
const API_BASE = "https://api.technik-prignitz.de";

const MAX_FILES = 10;
const MAX_FILE_BYTES = 10 * 1024 * 1024;
const MAX_TOTAL_BYTES = 50 * 1024 * 1024;

const header = document.querySelector("[data-header]");
const nav = document.querySelector("[data-nav]");
const navToggle = document.querySelector("[data-nav-toggle]");
const contactForm = document.querySelector("[data-contact-form]");
const contactStatus = document.querySelector("[data-form-status]");
const offerForm = document.querySelector("[data-offer-form]");

const setHeaderState = () => {
  const alwaysSolid = document.body.classList.contains("offer-page");
  header?.classList.toggle("is-scrolled", alwaysSolid || window.scrollY > 10);
};

const closeNav = () => {
  nav?.classList.remove("is-open");
  header?.classList.remove("is-open");
  document.body.classList.remove("nav-open");
  navToggle?.setAttribute("aria-expanded", "false");
};

navToggle?.addEventListener("click", () => {
  const isOpen = nav?.classList.toggle("is-open");
  header?.classList.toggle("is-open", Boolean(isOpen));
  document.body.classList.toggle("nav-open", Boolean(isOpen));
  navToggle.setAttribute("aria-expanded", String(Boolean(isOpen)));
});
nav?.addEventListener("click", (event) => {
  if (event.target instanceof HTMLAnchorElement) closeNav();
});
document.addEventListener("keydown", (e) => { if (e.key === "Escape") closeNav(); });
window.addEventListener("scroll", setHeaderState, { passive: true });
setHeaderState();

// ---------- Helpers ----------

const clearFieldErrors = (form) => {
  form.querySelectorAll(".has-error").forEach(el => el.classList.remove("has-error"));
};

const showFieldErrors = (form, fields) => {
  clearFieldErrors(form);
  for (const [name, _msg] of Object.entries(fields || {})) {
    const input = form.querySelector(`[name="${name}"], [name="${name}[]"]`);
    if (input) {
      const wrapper = input.closest("label") || input.parentElement;
      wrapper?.classList.add("has-error");
    }
  }
};

const setStatus = (el, text, kind) => {
  if (!el) return;
  el.textContent = text;
  el.classList.remove("is-success", "is-error");
  if (kind) el.classList.add(`is-${kind}`);
};

// ---------- Contact form ----------

contactForm?.addEventListener("submit", async (event) => {
  event.preventDefault();
  if (!contactForm.checkValidity()) { contactForm.reportValidity(); return; }

  clearFieldErrors(contactForm);
  const button = contactForm.querySelector('button[type="submit"]');
  button.disabled = true;
  setStatus(contactStatus, "Wird gesendet…", null);

  const fd = new FormData(contactForm);
  const payload = {
    name:    String(fd.get("name") || "").trim(),
    contact: String(fd.get("contact") || "").trim(),
    topic:   String(fd.get("topic") || "").trim(),
    message: String(fd.get("message") || "").trim(),
    website: String(fd.get("website") || ""),
  };

  try {
    const res = await fetch(`${API_BASE}/contact.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const data = await res.json().catch(() => ({}));
    handleResponse(res, data, contactForm, contactStatus,
      "Vielen Dank! Wir melden uns innerhalb von 2 Werktagen.");
  } catch (_err) {
    setStatus(contactStatus,
      "Etwas ist schiefgelaufen. Bitte erneut versuchen oder anrufen: +49 3876 612474.",
      "error");
  } finally {
    button.disabled = false;
  }
});

// ---------- Angebot form (multi-step + uploads) ----------

if (offerForm) {
  const steps = Array.from(offerForm.querySelectorAll("[data-offer-step]"));
  const progress = offerForm.querySelector("[data-offer-progress]");
  const stepLabel = offerForm.querySelector("[data-offer-step-label]");
  const backButton = offerForm.querySelector("[data-offer-back]");
  const nextButton = offerForm.querySelector("[data-offer-next]");
  const submitButton = offerForm.querySelector("[data-offer-submit]");
  const offerStatus = offerForm.querySelector("[data-offer-status]");
  const fileInput = offerForm.querySelector("[data-offer-files]");
  const fileList = offerForm.querySelector("[data-offer-file-list]");
  let currentStep = 0;
  let selectedFiles = [];

  const setOfferStep = (index) => {
    currentStep = Math.max(0, Math.min(index, steps.length - 1));
    steps.forEach((s, i) => s.classList.toggle("is-active", i === currentStep));
    if (progress) progress.style.width = `${((currentStep + 1) / steps.length) * 100}%`;
    if (stepLabel) stepLabel.textContent = `Schritt ${currentStep + 1} von ${steps.length}`;
    backButton?.toggleAttribute("disabled", currentStep === 0);
    nextButton?.classList.toggle("is-hidden", currentStep === steps.length - 1);
    submitButton?.classList.toggle("is-hidden", currentStep !== steps.length - 1);
  };

  const setStepError = (msg = "") => {
    const err = steps[currentStep]?.querySelector("[data-step-error]");
    if (err) err.textContent = msg;
  };

  const validateOfferStep = () => {
    const step = steps[currentStep];
    if (!step) return true;
    setStepError();

    const checkboxNames = new Set(
      Array.from(step.querySelectorAll('input[type="checkbox"]')).map(i => i.name)
    );
    for (const name of checkboxNames) {
      const boxes = Array.from(step.querySelectorAll(`input[name="${name}"]`));
      if (name === "components" && !boxes.some(b => b.checked)) {
        setStepError("Bitte wählen Sie mindestens eine Komponente aus.");
        return false;
      }
    }
    const radioNames = new Set(
      Array.from(step.querySelectorAll('input[type="radio"]')).map(i => i.name)
    );
    for (const name of radioNames) {
      const radios = Array.from(step.querySelectorAll(`input[name="${name}"]`));
      if (!radios.some(r => r.checked)) {
        setStepError("Bitte wählen Sie eine Option aus.");
        return false;
      }
    }
    const required = Array.from(step.querySelectorAll("[required]"));
    for (const f of required) if (!f.checkValidity()) { f.reportValidity(); return false; }
    return true;
  };

  const renderFileList = () => {
    if (!fileList) return;
    fileList.innerHTML = "";
    selectedFiles.forEach((f, idx) => {
      const li = document.createElement("li");
      li.innerHTML = `<span>${escapeHtml(f.name)}</span>
                      <small>${(f.size/1024/1024).toFixed(2)} MB</small>
                      <button type="button" class="remove" aria-label="Entfernen">×</button>`;
      li.querySelector("button.remove").addEventListener("click", () => {
        selectedFiles.splice(idx, 1); renderFileList();
      });
      fileList.appendChild(li);
    });
  };

  const escapeHtml = (s) => s.replace(/[<>&"']/g, c => ({
    "<":"&lt;",">":"&gt;","&":"&amp;",'"':"&quot;","'":"&#39;"
  }[c]));

  fileInput?.addEventListener("change", () => {
    const newOnes = Array.from(fileInput.files);
    setStepError();
    const merged = [...selectedFiles, ...newOnes];
    if (merged.length > MAX_FILES) {
      setStepError(`Maximal ${MAX_FILES} Dateien.`);
      fileInput.value = ""; return;
    }
    let total = 0;
    for (const f of merged) {
      if (f.size > MAX_FILE_BYTES) {
        setStepError(`${f.name} ist größer als 10 MB.`);
        fileInput.value = ""; return;
      }
      total += f.size;
    }
    if (total > MAX_TOTAL_BYTES) {
      setStepError("Gesamtgröße über 50 MB.");
      fileInput.value = ""; return;
    }
    selectedFiles = merged;
    fileInput.value = "";
    renderFileList();
  });

  backButton?.addEventListener("click", () => setOfferStep(currentStep - 1));
  nextButton?.addEventListener("click", () => { if (validateOfferStep()) setOfferStep(currentStep + 1); });

  offerForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!validateOfferStep()) return;

    submitButton.disabled = true;
    setStatus(offerStatus, "Wird gesendet…", null);
    clearFieldErrors(offerForm);

    const fd = new FormData();
    const native = new FormData(offerForm);
    for (const [k, v] of native.entries()) {
      if (k === "files") continue;          // we re-add below
      if (k === "components") fd.append("components[]", v);
      else fd.append(k, v);
    }
    if (selectedFiles.length > 0) fd.append("photos_followup", "1");
    selectedFiles.forEach(f => fd.append("files[]", f, f.name));

    try {
      const res = await fetch(`${API_BASE}/angebot.php`, { method: "POST", body: fd });
      const data = await res.json().catch(() => ({}));
      if (res.status === 413) {
        setStatus(offerStatus,
          "Die hochgeladenen Dateien sind zu groß. Bitte reduzieren Sie die Auswahl.",
          "error");
      } else {
        handleResponse(res, data, offerForm, offerStatus,
          "Vielen Dank! Wir melden uns innerhalb von 2 Werktagen.");
        if (res.ok && data.ok) { selectedFiles = []; renderFileList(); setOfferStep(0); }
      }
    } catch (_err) {
      setStatus(offerStatus,
        "Etwas ist schiefgelaufen. Bitte erneut versuchen oder anrufen: +49 3876 612474.",
        "error");
    } finally {
      submitButton.disabled = false;
    }
  });

  setOfferStep(0);
}

// ---------- Shared response handler ----------

function handleResponse(res, data, form, statusEl, successMsg) {
  if (res.ok && data.ok) {
    setStatus(statusEl, successMsg, "success");
    form.reset();
    return;
  }
  if (res.status === 429) {
    setStatus(statusEl,
      "Zu viele Anfragen. Bitte versuchen Sie es in einer Stunde erneut.",
      "error");
    return;
  }
  if (data.error === "validation") {
    showFieldErrors(form, data.fields);
    setStatus(statusEl, "Bitte prüfen Sie die markierten Felder.", "error");
    return;
  }
  setStatus(statusEl,
    "Etwas ist schiefgelaufen. Bitte erneut versuchen oder anrufen: +49 3876 612474.",
    "error");
}

// ---------- Lucide icons ----------

window.addEventListener("load", () => { if (window.lucide) window.lucide.createIcons(); });
