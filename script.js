const header = document.querySelector("[data-header]");
const nav = document.querySelector("[data-nav]");
const navToggle = document.querySelector("[data-nav-toggle]");
const form = document.querySelector("[data-contact-form]");
const formStatus = document.querySelector("[data-form-status]");
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
  if (event.target instanceof HTMLAnchorElement) {
    closeNav();
  }
});

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape") {
    closeNav();
  }
});

window.addEventListener("scroll", setHeaderState, { passive: true });
setHeaderState();

form?.addEventListener("submit", (event) => {
  event.preventDefault();

  if (!form.checkValidity()) {
    form.reportValidity();
    return;
  }

  const formData = new FormData(form);
  const name = String(formData.get("name") || "").trim();
  const contact = String(formData.get("contact") || "").trim();
  const topic = String(formData.get("topic") || "").trim();
  const message = String(formData.get("message") || "").trim();

  const body = [
    `Name: ${name}`,
    `Kontakt: ${contact}`,
    `Thema: ${topic}`,
    "",
    message,
  ].join("\n");

  const mailto = new URL("mailto:info@technik-prignitz.de");
  mailto.searchParams.set("subject", `Anfrage: ${topic}`);
  mailto.searchParams.set("body", body);

  window.location.href = mailto.toString();

  if (formStatus) {
    formStatus.textContent = "Ihr E-Mail-Programm wurde geöffnet.";
  }
});

if (offerForm) {
  const steps = Array.from(offerForm.querySelectorAll("[data-offer-step]"));
  const progress = offerForm.querySelector("[data-offer-progress]");
  const stepLabel = offerForm.querySelector("[data-offer-step-label]");
  const backButton = offerForm.querySelector("[data-offer-back]");
  const nextButton = offerForm.querySelector("[data-offer-next]");
  const submitButton = offerForm.querySelector("[data-offer-submit]");
  const offerStatus = offerForm.querySelector("[data-offer-status]");
  let currentStep = 0;

  const setOfferStep = (index) => {
    currentStep = Math.max(0, Math.min(index, steps.length - 1));

    steps.forEach((step, stepIndex) => {
      step.classList.toggle("is-active", stepIndex === currentStep);
    });

    const percent = ((currentStep + 1) / steps.length) * 100;
    if (progress) {
      progress.style.width = `${percent}%`;
    }

    if (stepLabel) {
      stepLabel.textContent = `Schritt ${currentStep + 1} von ${steps.length}`;
    }

    backButton?.toggleAttribute("disabled", currentStep === 0);
    nextButton?.classList.toggle("is-hidden", currentStep === steps.length - 1);
    submitButton?.classList.toggle("is-hidden", currentStep !== steps.length - 1);
  };

  const setStepError = (message = "") => {
    const error = steps[currentStep]?.querySelector("[data-step-error]");
    if (error) {
      error.textContent = message;
    }
  };

  const validateOfferStep = () => {
    const step = steps[currentStep];
    if (!step) {
      return true;
    }

    setStepError();

    const checkboxGroups = new Set(
      Array.from(step.querySelectorAll('input[type="checkbox"]')).map((input) => input.name)
    );

    for (const name of checkboxGroups) {
      const boxes = Array.from(step.querySelectorAll(`input[name="${name}"]`));
      const isRequiredGroup = name === "components";
      if (isRequiredGroup && !boxes.some((box) => box.checked)) {
        setStepError("Bitte wählen Sie mindestens eine Komponente aus.");
        return false;
      }
    }

    const radioGroups = new Set(
      Array.from(step.querySelectorAll('input[type="radio"]')).map((input) => input.name)
    );

    for (const name of radioGroups) {
      const radios = Array.from(step.querySelectorAll(`input[name="${name}"]`));
      if (!radios.some((radio) => radio.checked)) {
        setStepError("Bitte wählen Sie eine Option aus.");
        return false;
      }
    }

    const requiredFields = Array.from(step.querySelectorAll("[required]"));
    for (const field of requiredFields) {
      if (!field.checkValidity()) {
        field.reportValidity();
        return false;
      }
    }

    return true;
  };

  const getOfferValues = () => {
    const formData = new FormData(offerForm);
    const components = formData.getAll("components").join(", ");

    return {
      components,
      building: formData.get("building") || "-",
      location: formData.get("location") || "-",
      roof: formData.get("roof") || "-",
      usage: formData.get("usage") || "-",
      consumption: formData.get("consumption") || "-",
      timeline: formData.get("timeline") || "-",
      details: formData.get("details") || "-",
      photos: formData.get("photos") || "-",
      name: formData.get("name") || "-",
      phone: formData.get("phone") || "-",
      email: formData.get("email") || "-",
    };
  };

  backButton?.addEventListener("click", () => {
    setOfferStep(currentStep - 1);
  });

  nextButton?.addEventListener("click", () => {
    if (validateOfferStep()) {
      setOfferStep(currentStep + 1);
    }
  });

  offerForm.addEventListener("submit", (event) => {
    event.preventDefault();

    if (!validateOfferStep()) {
      return;
    }

    const values = getOfferValues();
    const body = [
      "Neue Angebotsanfrage",
      "",
      `Komponenten: ${values.components}`,
      `Objekt: ${values.building}`,
      `Standort / PLZ: ${values.location}`,
      `Dachform: ${values.roof}`,
      `Nutzung: ${values.usage}`,
      `Jahresverbrauch: ${values.consumption}`,
      `Zeitraum: ${values.timeline}`,
      `Fotos / Unterlagen: ${values.photos}`,
      "",
      "Details:",
      values.details,
      "",
      `Name: ${values.name}`,
      `Telefon: ${values.phone}`,
      `E-Mail: ${values.email}`,
    ].join("\n");

    const mailto = new URL("mailto:info@technik-prignitz.de");
    mailto.searchParams.set("subject", `Angebotsanfrage: ${values.components || "Projekt"}`);
    mailto.searchParams.set("body", body);
    window.location.href = mailto.toString();

    if (offerStatus) {
      offerStatus.textContent = "Ihr E-Mail-Programm wurde geöffnet.";
    }
  });

  setOfferStep(0);
}

window.addEventListener("load", () => {
  if (window.lucide) {
    window.lucide.createIcons();
  }
});
