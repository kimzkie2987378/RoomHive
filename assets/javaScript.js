/* =========================================================
   ROOMHIVE — MAIN SITE JAVASCRIPT
   ========================================================= */

document.addEventListener("DOMContentLoaded", () => {
  /* =========================
     TESTIMONIAL SLIDER
     (arrows, dots, autoplay, swipe, keyboard)
  ========================= */
  const section = document.querySelector(".testimonials");

  if (section) {
    const slider = section.querySelector(".testimonials-slider");
    const track = section.querySelector(".testimonials-track");
    const cards = section.querySelectorAll(".testimonial-card");
    const dots = section.querySelectorAll(".testimonials-dots .dot");

    const prevBtn = section.querySelector(
      '.nav-arrow[aria-label="Previous testimonial"]',
    );

    const nextBtn = section.querySelector(
      '.nav-arrow[aria-label="Next testimonial"]',
    );

    if (slider && track && cards.length > 0) {
      const AUTOPLAY_DELAY = 6000;

      const reducedMotion = window.matchMedia(
        "(prefers-reduced-motion: reduce)",
      ).matches;

      let current = 0;
      let autoplayTimer = null;

      /* Get how many cards should be visible */
      const getVisibleCount = () => {
        return window.innerWidth <= 900 ? 1 : 2;
      };

      /* Get maximum possible slide */
      const getMaxIndex = () => {
        return Math.max(cards.length - getVisibleCount(), 0);
      };

      /* Move slider */
      const goTo = (index) => {
        const maxIndex = getMaxIndex();

        current = Math.min(Math.max(index, 0), maxIndex);

        const cardWidth = cards[0].getBoundingClientRect().width;

        /* Get actual margin/gap from CSS */
        const cardStyle = window.getComputedStyle(cards[0]);
        const marginRight = parseFloat(cardStyle.marginRight) || 0;

        const offset = current * (cardWidth + marginRight);

        track.style.transform = `translateX(-${offset}px)`;

        /* Update dots */
        dots.forEach((dot, i) => {
          const isValidDot = i <= maxIndex;

          dot.style.display = isValidDot ? "" : "none";

          dot.classList.toggle("active", isValidDot && i === current);
        });

        /* Update navigation buttons */
        if (prevBtn) {
          prevBtn.classList.toggle("disabled", current === 0);
          prevBtn.disabled = current === 0;
        }

        if (nextBtn) {
          nextBtn.classList.toggle("disabled", current === maxIndex);

          nextBtn.disabled = current === maxIndex;
        }
      };

      /* ---- autoplay: loops; pauses on hover/focus/hidden tab ---- */
      const stopAutoplay = () => {
        if (autoplayTimer) {
          clearInterval(autoplayTimer);
          autoplayTimer = null;
        }
      };

      const startAutoplay = () => {
        if (reducedMotion || autoplayTimer) return;

        autoplayTimer = setInterval(() => {
          const maxIndex = getMaxIndex();
          goTo(current >= maxIndex ? 0 : current + 1);
        }, AUTOPLAY_DELAY);
      };

      section.addEventListener("mouseenter", stopAutoplay);
      section.addEventListener("mouseleave", startAutoplay);
      section.addEventListener("focusin", stopAutoplay);
      section.addEventListener("focusout", startAutoplay);

      document.addEventListener("visibilitychange", () => {
        if (document.hidden) {
          stopAutoplay();
        } else {
          startAutoplay();
        }
      });

      /* ---- arrows ---- */
      if (prevBtn) {
        prevBtn.addEventListener("click", () => goTo(current - 1));
      }

      if (nextBtn) {
        nextBtn.addEventListener("click", () => goTo(current + 1));
      }

      /* ---- dots ---- */
      dots.forEach((dot, index) => {
        dot.addEventListener("click", () => goTo(index));
      });

      /* ---- keyboard (while focus is inside the section) ---- */
      section.addEventListener("keydown", (event) => {
        if (event.key === "ArrowLeft") goTo(current - 1);
        if (event.key === "ArrowRight") goTo(current + 1);
      });

      /* ---- touch swipe ---- */
      let touchStartX = 0;

      slider.addEventListener(
        "touchstart",
        (event) => {
          touchStartX = event.changedTouches[0].clientX;
          stopAutoplay();
        },
        { passive: true },
      );

      slider.addEventListener(
        "touchend",
        (event) => {
          const delta = event.changedTouches[0].clientX - touchStartX;

          if (Math.abs(delta) > 45) {
            goTo(current + (delta < 0 ? 1 : -1));
          }

          startAutoplay();
        },
        { passive: true },
      );

      /* ---- re-measure on resize ---- */
      let resizeTimer;

      window.addEventListener("resize", () => {
        clearTimeout(resizeTimer);

        resizeTimer = setTimeout(() => {
          goTo(current);
        }, 100);
      });

      /* Start from first testimonial */
      goTo(0);
      startAutoplay();
    }
  }

  /* =========================
     CATEGORY DROPDOWN
  ========================= */
  const categoryFilter = document.getElementById("category-filter");

  const categoryToggle = document.getElementById("categoryDropdownToggle");

  const categoryPanel = document.getElementById("categoryDropdownPanel");

  const categoryHiddenInput = document.getElementById("category-hidden-input");

  const filterForm = document.getElementById("filter-form");

  if (
    categoryFilter &&
    categoryToggle &&
    categoryPanel &&
    categoryHiddenInput &&
    filterForm
  ) {
    /* Open / close dropdown */
    categoryToggle.addEventListener("click", (event) => {
      event.stopPropagation();

      const isOpen = categoryFilter.classList.toggle("open");

      categoryToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
    });

    /* Select category */
    const categoryItems = categoryPanel.querySelectorAll(
      ".category-dropdown-item",
    );

    categoryItems.forEach((item) => {
      item.addEventListener("click", (event) => {
        /* These are native <button type="submit" value="..."> controls.
           Stop the native submit so it doesn't race the manual one below,
           and read the category from `value` (there is no data-value attr). */
        event.preventDefault();
        event.stopPropagation();

        /* Set selected category */
        categoryHiddenInput.value = item.value || "";

        /* Close dropdown */
        categoryFilter.classList.remove("open");

        categoryToggle.setAttribute("aria-expanded", "false");

        /* Submit filter */
        filterForm.submit();
      });
    });

    /* Close dropdown when clicking outside */
    document.addEventListener("click", (event) => {
      if (!categoryFilter.contains(event.target)) {
        categoryFilter.classList.remove("open");

        categoryToggle.setAttribute("aria-expanded", "false");
      }
    });

    /* Close dropdown with ESC key */
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        categoryFilter.classList.remove("open");

        categoryToggle.setAttribute("aria-expanded", "false");
      }
    });
  }

  /* =========================
     EXPLORE MORE SPACES CAROUSEL
     Plain native horizontal scroll (drag/swipe/trackpad
     already work) — the arrows just nudge it along.
  ========================= */
  const carouselTrack = document.getElementById("rh-carousel-track");
  const carouselPrev = document.getElementById("rh-carousel-prev");
  const carouselNext = document.getElementById("rh-carousel-next");

  if (carouselTrack && carouselPrev && carouselNext) {
    const scrollByCard = (direction) => {
      const card = carouselTrack.querySelector(".rh-carousel-card");

      if (!card) {
        return;
      }

      const cardWidth = card.getBoundingClientRect().width;
      const gap = parseFloat(window.getComputedStyle(carouselTrack).gap) || 14;

      carouselTrack.scrollBy({
        left: direction * (cardWidth + gap) * 2,
        behavior: "smooth",
      });
    };

    const updateArrowState = () => {
      const maxScroll = carouselTrack.scrollWidth - carouselTrack.clientWidth;

      carouselPrev.disabled = carouselTrack.scrollLeft <= 4;
      carouselNext.disabled = carouselTrack.scrollLeft >= maxScroll - 4;
    };

    carouselPrev.addEventListener("click", () => scrollByCard(-1));
    carouselNext.addEventListener("click", () => scrollByCard(1));
    carouselTrack.addEventListener("scroll", updateArrowState);
    window.addEventListener("resize", updateArrowState);

    updateArrowState();
  }

  /* =========================
     SAVED LISTINGS (heart / favorites)
     Stored client-side in localStorage so it works
     without any backend changes. Key: array of listing ids.
  ========================= */
  const SAVED_KEY = "roomhive_saved_listings";

  const getSavedIds = () => {
    try {
      const raw = localStorage.getItem(SAVED_KEY);
      const parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed.map(String) : [];
    } catch (err) {
      return [];
    }
  };

  const setSavedIds = (ids) => {
    try {
      localStorage.setItem(SAVED_KEY, JSON.stringify(ids));
    } catch (err) {
      /* localStorage unavailable (private mode, etc) — fail silently */
    }
  };

  const saveButtons = document.querySelectorAll(".rh-save-btn");

  if (saveButtons.length > 0) {
    let savedIds = getSavedIds();

    /* Paint initial saved state on load */
    saveButtons.forEach((btn) => {
      const id = btn.dataset.listingId;

      if (savedIds.includes(id)) {
        btn.classList.add("saved");
        btn.setAttribute("aria-pressed", "true");
        btn.innerHTML = "&#9829;";
      }
    });

    saveButtons.forEach((btn) => {
      btn.addEventListener("click", (event) => {
        /* The button lives inside an <a class="listing-box">,
           so stop it from also navigating to the listing page. */
        event.preventDefault();
        event.stopPropagation();

        const id = btn.dataset.listingId;
        savedIds = getSavedIds();

        const isSaved = savedIds.includes(id);

        if (isSaved) {
          savedIds = savedIds.filter((savedId) => savedId !== id);
          btn.classList.remove("saved");
          btn.setAttribute("aria-pressed", "false");
          btn.innerHTML = "&#9825;";
        } else {
          savedIds.push(id);
          btn.classList.add("saved");
          btn.setAttribute("aria-pressed", "true");
          btn.innerHTML = "&#9829;";
        }

        setSavedIds(savedIds);

        /* If "Saved only" view is active, re-filter immediately */
        const savedToggle = document.getElementById("rh-saved-toggle");

        if (savedToggle && savedToggle.classList.contains("active")) {
          applySavedOnlyFilter(true);
        }
      });
    });
  }

  /* =========================
     "SAVED ONLY" VIEW TOGGLE
  ========================= */
  const savedToggle = document.getElementById("rh-saved-toggle");
  const resultsGrid = document.getElementById("rh-listings-results");

  function applySavedOnlyFilter(showSavedOnly) {
    if (!resultsGrid) {
      return;
    }

    const savedIds = getSavedIds();
    const cards = resultsGrid.querySelectorAll(".listing-box");

    cards.forEach((card) => {
      const id = card.dataset.listingId;
      const isSaved = savedIds.includes(id);

      card.classList.toggle("rh-hidden", showSavedOnly && !isSaved);
    });
  }

  if (savedToggle && resultsGrid) {
    savedToggle.addEventListener("click", () => {
      const isActive = savedToggle.classList.toggle("active");

      savedToggle.setAttribute("aria-pressed", isActive ? "true" : "false");
      savedToggle.querySelector(".rh-heart-icon").innerHTML = isActive
        ? "&#9829;"
        : "&#9825;";

      applySavedOnlyFilter(isActive);
    });
  }
});

/* =========================================================
   ROOMHIVE - BECOME A HOST (STEP 3)
   PHOTO UPLOAD BEHAVIOUR
   ========================================================= */

document.addEventListener("DOMContentLoaded", () => {
  /* =========================================================
     COVER PHOTO PREVIEW
  ========================================================= */

  const coverInput = document.getElementById("cover_photo");
  const coverBox = document.getElementById("coverPhotoBox");

  if (coverInput && coverBox) {
    coverInput.addEventListener("change", () => {
      const file = coverInput.files && coverInput.files[0];

      if (!file) {
        return;
      }

      const reader = new FileReader();

      reader.onload = (event) => {
        coverBox.style.backgroundImage = `url(${event.target.result})`;
        coverBox.classList.add("has-image");
      };

      reader.readAsDataURL(file);
    });
  }

  /* =========================================================
     ADDITIONAL PHOTOS GRID
     (single shared file input; slots are rendered from a
     JS-held list of File objects so photos can be removed
     and the grid grows back up to a maximum count)
  ========================================================= */

  const MAX_ADDITIONAL_PHOTOS = 20;
  const MIN_EMPTY_SLOTS = 10;

  const grid = document.getElementById("photoGrid");
  const input = document.getElementById("additionalPhotosInput");

  if (!grid || !input) {
    return;
  }

  let selectedFiles = [];

  /* Rebuild the hidden <input> FileList from selectedFiles
     so the array survives multiple picker sessions and
     removals (native file inputs can't be appended to). */
  function syncInput() {
    const transfer = new DataTransfer();

    selectedFiles.forEach((file) => {
      transfer.items.add(file);
    });

    input.files = transfer.files;
  }

  function isValidFile(file) {
    const allowedTypes = ["image/jpeg", "image/png"];
    const maxSize = 5 * 1024 * 1024;

    return allowedTypes.includes(file.type) && file.size <= maxSize;
  }

  function openPicker() {
    input.click();
  }

  function removeAt(index) {
    selectedFiles.splice(index, 1);

    syncInput();
    render();
  }

  function render() {
    grid.innerHTML = "";

    /* ---- filled slots ---- */

    selectedFiles.forEach((file, index) => {
      const slot = document.createElement("div");
      slot.className = "photo-slot filled";

      const img = document.createElement("img");
      img.src = URL.createObjectURL(file);
      img.alt = "Uploaded photo";

      const removeBtn = document.createElement("button");
      removeBtn.type = "button";
      removeBtn.className = "photo-slot-remove";
      removeBtn.setAttribute("aria-label", "Remove photo");
      removeBtn.innerHTML = "&times;";

      removeBtn.addEventListener("click", () => {
        removeAt(index);
      });

      slot.appendChild(img);
      slot.appendChild(removeBtn);

      grid.appendChild(slot);
    });

    /* ---- empty slots ----
       Keep at least MIN_EMPTY_SLOTS total tiles on screen,
       but never let the count of empty tiles push the total
       possible photos past MAX_ADDITIONAL_PHOTOS. */

    const remainingCapacity = MAX_ADDITIONAL_PHOTOS - selectedFiles.length;

    const emptyNeededForMinimum = Math.max(
      MIN_EMPTY_SLOTS - selectedFiles.length,
      0,
    );

    const emptySlotCount = Math.min(
      Math.max(emptyNeededForMinimum, remainingCapacity > 0 ? 1 : 0),
      remainingCapacity,
    );

    for (let i = 0; i < emptySlotCount; i++) {
      const slot = document.createElement("div");
      slot.className = "photo-slot";

      const trigger = document.createElement("button");
      trigger.type = "button";
      trigger.className = "photo-slot-trigger";

      trigger.innerHTML = `
        <span class="photo-slot-icon">☁</span>
        <span class="photo-slot-text">
          <strong>Upload photo</strong>
          <small>JPG, PNG – Max 5MB</small>
        </span>
      `;

      trigger.addEventListener("click", openPicker);

      slot.appendChild(trigger);

      grid.appendChild(slot);
    }
  }

  /* Wire up the triggers rendered from PHP on first load */
  grid.querySelectorAll(".photo-slot-trigger").forEach((trigger) => {
    trigger.addEventListener("click", openPicker);
  });

  input.addEventListener("change", (event) => {
    const chosen = Array.from(event.target.files || []);

    chosen.forEach((file) => {
      if (selectedFiles.length >= MAX_ADDITIONAL_PHOTOS) {
        return;
      }

      if (isValidFile(file)) {
        selectedFiles.push(file);
      }
    });

    syncInput();
    render();
  });
});

/* =========================================================
   ROOMHIVE - ACCOUNT DROPDOWN (shared across every page)
   =========================================================

   Every page's "MY ACCOUNT" / avatar dropdown uses two
   shared classes instead of onclick="" + getElementById():

     .js-account-dropdown   <- wraps the button + menu
     .js-account-toggle     <- the button that opens it

   This is handled with ONE delegated listener here instead
   of a copy-pasted inline <script> per page, so:
     - it can't break from duplicate IDs on a page
     - every page behaves identically
     - fixing a bug here fixes it everywhere at once

   Any page that uses the dropdown just needs those two
   classes in its markup (in addition to whatever classes
   are already there for styling) and must load this file
   with <script src="javaScript.js"></script>.

   NOTE: This is the ONLY handler for the account dropdown.
   A second, page-specific copy of this logic used to live
   at the bottom of this file (keyed off #accountDropdownToggle
   / .account-dropdown) and was removed — having both meant
   every click toggled the "open" class twice, so the menu
   never visibly opened. Do not re-add a per-page version;
   just make sure the markup has .js-account-dropdown /
   .js-account-toggle and this block will handle it.
   ========================================================= */

document.addEventListener("click", (event) => {
  const toggleBtn = event.target.closest(".js-account-toggle");

  if (toggleBtn) {
    const dropdown = toggleBtn.closest(".js-account-dropdown");

    if (dropdown) {
      const isOpen = dropdown.classList.toggle("open");
      toggleBtn.setAttribute("aria-expanded", isOpen ? "true" : "false");
    }

    /* Don't fall through to the "close everything" logic below
       for the very click that just opened this dropdown. */
    return;
  }

  document.querySelectorAll(".js-account-dropdown.open").forEach((dropdown) => {
    if (!dropdown.contains(event.target)) {
      dropdown.classList.remove("open");

      const btn = dropdown.querySelector(".js-account-toggle");

      if (btn) {
        btn.setAttribute("aria-expanded", "false");
      }
    }
  });
});

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape") {
    document
      .querySelectorAll(".js-account-dropdown.open")
      .forEach((dropdown) => {
        dropdown.classList.remove("open");

        const btn = dropdown.querySelector(".js-account-toggle");

        if (btn) {
          btn.setAttribute("aria-expanded", "false");
        }
      });
  }
});

/* =========================================================
   ROOMHIVE
   MY ACCOUNT JAVASCRIPT
========================================================= */

/* =========================================================
   EDIT PROFILE BUTTON
========================================================= */

const editProfileButton = document.getElementById("editProfileButton");

if (editProfileButton) {
  editProfileButton.addEventListener("click", function () {
    /*
     * Temporary action.
     *
     * Later we can create:
     *
     * editprofile.php
     *
     * where the user can change:
     * - Name
     * - Email
     * - Phone
     * - Location
     * - Profile photo
     */

    window.location.href = "editprofile.php";
  });
}

/* =========================================================
   ROOMHIVE — MOTION & INTERACTION LAYER
   Pairs with motion.css. Runs on every page that loads
   this file; safely no-ops where elements are absent.
   ========================================================= */

function initMotionLayer() {
  "use strict";

  const prefersReducedMotion = window.matchMedia(
    "(prefers-reduced-motion: reduce)",
  ).matches;

  const canAnimate = !prefersReducedMotion && "IntersectionObserver" in window;

  /* ---------------------------------------------------------
     1. SCROLL REVEAL — sections & cards fade/slide in.
        Siblings in the same grid get a stagger delay.
  --------------------------------------------------------- */
  const initScrollReveal = () => {
    if (!canAnimate) return; /* leave everything visible */

    const revealSelectors = [
      ".club-content",
      ".hive-club .join-button",
      ".listings-eyebrow",
      ".listings-title",
      ".listing-card",
      ".about-text",
      ".about-image",
      ".reasons-heading",
      ".reasons-desc",
      ".reason-card",
      ".testimonials-text",
      ".testimonial-card",
      ".cta-image-strip img",
      ".cta-box",
      ".dual-cta-panel",
    ];

    const elements = document.querySelectorAll(revealSelectors.join(","));

    elements.forEach((el) => {
      const parent = el.parentElement;

      if (parent) {
        const revealedSiblings = Array.from(parent.children).filter((child) =>
          child.classList.contains("reveal"),
        );

        const index = revealedSiblings.indexOf(el);

        if (index > 0) {
          el.style.transitionDelay = `${Math.min(index * 90, 450)}ms`;
        }
      }

      el.classList.add("reveal");
    });

    const revealObserver = new IntersectionObserver(
      (entries, observer) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;

          observer.unobserve(entry.target);
          entry.target.classList.add("is-visible");

          /* Strip helper classes after revealing so normal hover
             transitions (lift/zoom) work untouched again. */
          const el = entry.target;
          const delay = parseFloat(el.style.transitionDelay) || 0;

          setTimeout(() => {
            el.classList.remove("reveal", "is-visible");
            el.style.transitionDelay = "";
          }, delay + 900);
        });
      },
      { threshold: 0.12, rootMargin: "0px 0px -50px 0px" },
    );

    elements.forEach((el) => revealObserver.observe(el));
  };

  /* ---------------------------------------------------------
     2. SMART NAVBAR + HERO FADE + BACK-TO-TOP
        One rAF-throttled scroll handler drives all three.
  --------------------------------------------------------- */
  const initScrollEffects = () => {
    const navbar = document.querySelector(".navbar");
    const heroContent = document.querySelector(".hero-content");

    /* Back-to-top button — created here, no HTML change needed */
    const backToTop = document.createElement("button");

    backToTop.type = "button";
    backToTop.className = "back-to-top";
    backToTop.setAttribute("aria-label", "Back to top");
    backToTop.innerHTML = "&#8593;";
    backToTop.addEventListener("click", () => {
      window.scrollTo({
        top: 0,
        behavior: prefersReducedMotion ? "auto" : "smooth",
      });
    });
    document.body.appendChild(backToTop);

    /* Only drift hero text if nothing else transforms it
       (e.g. a centering translateY(-50%)) */
    let heroCanDrift = false;

    if (heroContent && !prefersReducedMotion) {
      const baseTransform = window.getComputedStyle(heroContent).transform;
      heroCanDrift = !baseTransform || baseTransform === "none";
    }

    let lastY = window.scrollY;
    let ticking = false;

    const update = () => {
      const y = window.scrollY;

      if (navbar) {
        navbar.classList.toggle("nav-scrolled", y > 24);

        if (canAnimate) {
          if (y > lastY && y > 220) {
            navbar.classList.add("nav-hidden"); /* scrolling down */
          } else {
            navbar.classList.remove("nav-hidden"); /* scrolling up */
          }
        }
      }

      if (heroContent && !prefersReducedMotion) {
        const fade = Math.max(0, 1 - y / 520);
        heroContent.style.opacity = fade.toFixed(3);

        if (heroCanDrift) {
          heroContent.style.transform = `translateY(${(y * 0.18).toFixed(1)}px)`;
        }
      }

      backToTop.classList.toggle("is-visible", y > 600);

      lastY = y;
      ticking = false;
    };

    window.addEventListener(
      "scroll",
      () => {
        if (!ticking) {
          window.requestAnimationFrame(update);
          ticking = true;
        }
      },
      { passive: true },
    );

    update();
  };

  /* ---------------------------------------------------------
     3. BUTTON RIPPLES — soft pulse from the click point
  --------------------------------------------------------- */
  const initRipples = () => {
    if (prefersReducedMotion) return;

    const buttons = document.querySelectorAll(
      ".btn-primary, .btn-secondary, .cta-button, .join-button, " +
        ".about-button, .dual-cta-button, .list-space",
    );

    buttons.forEach((button) => {
      button.addEventListener("pointerdown", (event) => {
        const rect = button.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height) * 1.15;

        const ripple = document.createElement("span");
        ripple.className = "ripple";
        ripple.style.width = ripple.style.height = `${size}px`;
        ripple.style.left = `${event.clientX - rect.left - size / 2}px`;
        ripple.style.top = `${event.clientY - rect.top - size / 2}px`;

        button.appendChild(ripple);
        ripple.addEventListener("animationend", () => ripple.remove());
      });
    });
  };

  /* ---------------------------------------------------------
     4. IMAGE FADE-IN — photos fade up once actually loaded
  --------------------------------------------------------- */
  const initImageFade = () => {
    if (prefersReducedMotion) return;

    document.querySelectorAll("img").forEach((img) => {
      const show = () => img.classList.add("rh-img-loaded");

      img.classList.add("rh-img-fade");

      if (img.complete && img.naturalWidth > 0) {
        show();
      } else {
        img.addEventListener("load", show, { once: true });
        img.addEventListener("error", show, {
          once: true,
        }); /* never hide broken imgs */
      }
    });
  };

  /* ---------------------------------------------------------
     GO
  --------------------------------------------------------- */
  initScrollReveal();
  initScrollEffects();
  initRipples();
  initImageFade();
}

/* Run whether the script loads at end-of-body or in <head> */
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initMotionLayer);
} else {
  initMotionLayer();
}

document.addEventListener("DOMContentLoaded", () => {
  const overlay = document.getElementById("loginModalOverlay");
  const form = document.getElementById("loginModalForm");
  const errorBox = document.getElementById("loginModalError");

  if (!overlay || !form) {
    return;
  }

  function openModal(redirectTarget) {
    overlay.querySelector('input[name="redirect"]').value =
      redirectTarget || "";

    overlay.classList.add("open");
    overlay.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";

    const emailField = overlay.querySelector("#modal-email");
    if (emailField) {
      emailField.focus();
    }
  }

  function closeModal() {
    overlay.classList.remove("open");
    overlay.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";

    if (errorBox) {
      errorBox.hidden = true;
      errorBox.textContent = "";
    }

    form.reset();
  }

  /* Open from any .js-open-login link, close from the
     backdrop or the × button — one delegated listener. */
  document.addEventListener("click", (event) => {
    const trigger = event.target.closest(".js-open-login");

    if (trigger) {
      event.preventDefault();

      const url = new URL(trigger.href, window.location.origin);
      openModal(url.searchParams.get("redirect") || "");

      return;
    }

    if (event.target.closest("[data-close-login]")) {
      closeModal();
    }
  });

  /* Esc closes it, same as the category dropdown elsewhere. */
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && overlay.classList.contains("open")) {
      closeModal();
    }
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    if (errorBox) {
      errorBox.hidden = true;
    }

    const submitBtn = form.querySelector(".login-button");
    const originalLabel = submitBtn ? submitBtn.textContent : "";

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = "Logging in…";
    }

    try {
      const response = await fetch("/webprogg/auth/loginform.php", {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest" },
        body: new FormData(form),
      });

      const data = await response.json();

      if (data.success) {
        window.location.href = data.redirect;
        return; /* leave the button disabled while we navigate away */
      }

      if (errorBox) {
        errorBox.textContent = data.error || "Something went wrong.";
        errorBox.hidden = false;
      }
    } catch (err) {
      if (errorBox) {
        errorBox.textContent = "Couldn't reach the server. Please try again.";
        errorBox.hidden = false;
      }
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = originalLabel || "Log in";
      }
    }
  });
});

/* =========================================================
   ROOMHIVE — LISTINGS POWER-UP LAYER
   Purely additive: layers behaviour ON TOP of javaScript.js
   and listing.php's inline script. Removes/replaces nothing.
   ========================================================= */
(() => {
  "use strict";

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
  const reduced = matchMedia("(prefers-reduced-motion: reduce)").matches;

  const form = $("#filter-form");
  const grid = $("#rh-listings-results");
  const bar = $(".filter-bar");
  const search = $(".search-input");

  /* ---------- TOASTS ---------- */
  const toastStack = document.createElement("div");
  toastStack.className = "rh-toast-stack";
  document.body.appendChild(toastStack);

  function toast(message, icon = "✨") {
    if (toastStack.children.length >= 3) toastStack.firstElementChild.remove();
    const el = document.createElement("div");
    el.className = "rh-toast";
    el.innerHTML = `<span class="rh-toast-ico">${icon}</span><span></span>`;
    el.lastElementChild.textContent = message; // textContent = XSS-safe
    toastStack.appendChild(el);
    setTimeout(() => {
      el.classList.add("out");
      el.addEventListener("animationend", () => el.remove(), { once: true });
    }, 2600);
  }

  /* ---------- FILTER VEIL — every reload feels intentional ---------- */
  let veil = null;
  function veilOn() {
    if (veil) return;
    grid?.classList.add("rh-loading");
    veil = document.createElement("div");
    veil.className = "rh-loading-pill";
    veil.innerHTML = `<span class="rh-spinner"></span> Finding your hive…`;
    document.body.appendChild(veil);
  }
  form?.addEventListener("submit", veilOn, true); // search / category buttons
  $("#location-select")?.addEventListener("change", veilOn); // inline onchange skips the event
  $(".listings-pagination")?.addEventListener(
    "click",
    (e) => {
      if (e.target.closest("a, button")) veilOn();
    },
    true,
  );

  /* ---------- STICKY FILTER CONSOLE ---------- */
  if (bar) {
    const dockCheck = () =>
      bar.classList.toggle(
        "rh-stuck",
        bar.getBoundingClientRect().top <= 84 && scrollY > 8,
      );
    addEventListener("scroll", dockCheck, { passive: true });
    dockCheck();
  }

  /* ---------- PRICE SLIDER — filled track + apply on release ---------- */
  const price = $("#price-range");
  if (price) {
    const paint = () => {
      const pct = ((price.value - price.min) / (price.max - price.min)) * 100;
      price.style.setProperty("--fill", pct + "%");
    };
    price.addEventListener("input", paint);
    paint();
    let t;
    price.addEventListener("change", () => {
      clearTimeout(t);
      t = setTimeout(() => form?.requestSubmit(), 400);
    });
  }

  /* ---------- LIVE SEARCH — debounced auto-apply + focus restore ---------- */
  if (search && form) {
    let t;
    search.addEventListener("input", () => {
      clearTimeout(t);
      const v = search.value.trim();
      if (v.length === 1) return; // wait for 2+ chars (or a full clear)
      t = setTimeout(() => {
        try {
          sessionStorage.setItem(
            "rh-caret",
            JSON.stringify({
              start: search.selectionStart,
              end: search.selectionEnd,
            }),
          );
        } catch {}
        veilOn();
        form.requestSubmit();
      }, 700);
    });
    try {
      // put the caret back after reload
      const caret = JSON.parse(sessionStorage.getItem("rh-caret") || "null");
      if (caret) {
        search.focus();
        search.setSelectionRange(caret.start, caret.end);
        sessionStorage.removeItem("rh-caret");
      }
    } catch {}
  }

  /* ---------- CARD SPOTLIGHT — glow follows the cursor ---------- */
  if (!reduced) {
    $$(".listing-box").forEach((card) => {
      card.addEventListener("pointermove", (e) => {
        const r = card.getBoundingClientRect();
        card.style.setProperty("--mx", e.clientX - r.left + "px");
        card.style.setProperty("--my", e.clientY - r.top + "px");
      });
    });
  }

  /* ---------- SAVE HEARTS — pop + particle burst + toast ----------
       Capture phase: runs BEFORE the page's real save logic,
       which still executes untouched afterwards. */
  function burst(btn) {
    const r = btn.getBoundingClientRect();
    const cx = r.left + r.width / 2;
    const cy = r.top + r.height / 2;
    const colors = ["#eda423", "#e0505a", "#1f4a3d", "#f6c04e"];
    for (let i = 0; i < 12; i++) {
      const p = document.createElement("span");
      p.className = "rh-burst";
      p.style.left = cx - 4 + "px";
      p.style.top = cy - 4 + "px";
      p.style.background = colors[i % colors.length];
      document.body.appendChild(p);
      const a = (Math.PI * 2 * i) / 12 + Math.random() * 0.6;
      const d = 30 + Math.random() * 28;
      p.animate(
        [
          { transform: "translate(0,0) scale(1)", opacity: 1 },
          {
            transform: `translate(${Math.cos(a) * d}px, ${Math.sin(a) * d}px) scale(0.15)`,
            opacity: 0,
          },
        ],
        {
          duration: 550 + Math.random() * 250,
          easing: "cubic-bezier(.22,1,.36,1)",
        },
      ).onfinish = () => p.remove();
    }
  }

  document.addEventListener(
    "click",
    (e) => {
      const btn = e.target.closest(".rh-save-btn");
      if (!btn) return;
      const saving = btn.getAttribute("aria-pressed") !== "true";
      if (saving) {
        btn.classList.remove("saved");
        requestAnimationFrame(() => btn.classList.add("saved"));
        if (!reduced) burst(btn);
        toast("Saved to your hive", "💛");
      } else {
        toast("Removed from saved", "🤍");
      }
      setTimeout(updateCount, 80);
    },
    true,
  );

  $("#rh-save-search-btn")?.addEventListener("click", () =>
    toast("Search saved — we'll keep watch", "📌"),
  );

  /* ---------- RESULTS COUNT — count-up + live updates ---------- */
  const countEl = $("#rh-results-count");
  const toolbar = $(".rh-toolbar");
  const TOTAL = +(toolbar?.dataset.total || 0);
  const SHOWN = +(
    toolbar?.dataset.shown || $$(".listing-box", grid || document).length
  );

  function countUp(el, to, dur = 800) {
    if (reduced) {
      el.textContent = to;
      return;
    }
    const t0 = performance.now();
    (function step(now) {
      const k = Math.min(1, (now - t0) / dur);
      el.textContent = Math.round(to * (1 - Math.pow(1 - k, 3)));
      if (k < 1) requestAnimationFrame(step);
    })(t0);
  }

  function updateCount() {
    if (!countEl) return;
    const visible = $$(".listing-box", grid || document).filter(
      (c) => !c.classList.contains("rh-hidden"),
    ).length;
    const total = TOTAL || visible;
    countEl.innerHTML = `Showing <strong>${visible}</strong> of ${total} space${total === 1 ? "" : "s"}`;
  }

  if (countEl) {
    countEl.innerHTML = `Showing <strong></strong> of ${TOTAL || SHOWN} space${(TOTAL || SHOWN) === 1 ? "" : "s"}`;
    countUp(countEl.querySelector("strong"), SHOWN);
  }

  $("#rh-saved-toggle")?.addEventListener(
    "click",
    () => setTimeout(updateCount, 60),
    true,
  );

  /* ---------- SORT — FLIP-animated reordering, remembered ---------- */
  const sortSel = $("#rh-sort");
  if (sortSel && grid) {
    const serverOrder = $$(".listing-box", grid);

    const priceOf = (el) =>
      parseFloat(el.dataset.price ?? "") ||
      parseFloat(
        el
          .querySelector(".listing-box-price")
          ?.textContent.replace(/[^\d.]/g, "") || 0,
      ) ||
      0;

    function reorder(mode, animate = true) {
      const cards = $$(".listing-box", grid);
      const before = new Map(
        cards.map((el) => [el, el.getBoundingClientRect()]),
      );

      let ordered = [...cards];
      if (mode === "price-asc") ordered.sort((a, b) => priceOf(a) - priceOf(b));
      if (mode === "price-desc")
        ordered.sort((a, b) => priceOf(b) - priceOf(a));
      if (mode === "featured")
        ordered = serverOrder.filter((el) => cards.includes(el));
      ordered.forEach((el) => grid.appendChild(el));

      if (!animate || reduced) return;
      cards.forEach((el) => {
        const f = before.get(el);
        if (!f) return;
        const l = el.getBoundingClientRect();
        const dx = f.left - l.left;
        const dy = f.top - l.top;
        if (dx || dy) {
          el.animate(
            [
              { transform: `translate(${dx}px, ${dy}px)` },
              { transform: "none" },
            ],
            { duration: 450, easing: "cubic-bezier(.22,1,.36,1)" },
          );
        }
      });
    }

    const savedSort = sessionStorage.getItem("rh-sort");
    if (savedSort) {
      sortSel.value = savedSort;
      reorder(savedSort, false);
    }

    sortSel.addEventListener("change", () => {
      sessionStorage.setItem("rh-sort", sortSel.value);
      reorder(sortSel.value);
    });
  }

  /* ---------- VIEW TOGGLE — grid ⇄ list, remembered ---------- */
  const viewBtns = $$(".rh-view-btn");
  if (viewBtns.length && grid) {
    const applyView = (v) => {
      grid.classList.toggle("rh-list-view", v === "list");
      viewBtns.forEach((b) =>
        b.classList.toggle("active", b.dataset.view === v),
      );
    };
    applyView(sessionStorage.getItem("rh-view") || "grid");
    viewBtns.forEach((b) =>
      b.addEventListener("click", () => {
        applyView(b.dataset.view);
        sessionStorage.setItem("rh-view", b.dataset.view);
      }),
    );
  }

  /* ---------- CAROUSEL — drag to scroll, wheel, no accidental clicks ---------- */
  const track = $("#rh-carousel-track");
  if (track) {
    let down = false,
      startX = 0,
      startScroll = 0,
      moved = false;
    track.addEventListener("pointerdown", (e) => {
      down = true;
      moved = false;
      startX = e.clientX;
      startScroll = track.scrollLeft;
      track.setPointerCapture(e.pointerId);
    });
    track.addEventListener("pointermove", (e) => {
      if (!down) return;
      const dx = e.clientX - startX;
      if (Math.abs(dx) > 5) moved = true;
      track.scrollLeft = startScroll - dx;
    });
    ["pointerup", "pointercancel"].forEach((ev) =>
      track.addEventListener(ev, () => {
        down = false;
      }),
    );
    track.addEventListener(
      "click",
      (e) => {
        // swallow the click that ends a drag
        if (moved) {
          e.preventDefault();
          e.stopPropagation();
        }
      },
      true,
    );
    track.addEventListener(
      "wheel",
      (e) => {
        if (Math.abs(e.deltaY) <= Math.abs(e.deltaX)) return;
        const d = e.deltaY;
        const atStart = track.scrollLeft <= 0 && d < 0;
        const atEnd =
          track.scrollLeft + track.clientWidth >= track.scrollWidth - 1 &&
          d > 0;
        if (atStart || atEnd) return; // let the page scroll at the edges
        track.scrollLeft += d;
        e.preventDefault();
      },
      { passive: false },
    );
  }

  /* ---------- SCROLL REVEAL — sidebar / toolbar / pagination ---------- */
  if (!reduced && "IntersectionObserver" in window) {
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach((en) => {
          if (en.isIntersecting) {
            en.target.classList.add("rh-in");
            io.unobserve(en.target);
          }
        });
      },
      { threshold: 0.15 },
    );
    [".rh-toolbar", ".amenities-sidebar", ".listings-pagination"].forEach(
      (sel) => {
        const el = $(sel);
        if (el) {
          el.classList.add("rh-reveal");
          io.observe(el);
        }
      },
    );
  }

  /* ---------- BACK TO TOP — with scroll-progress ring ---------- */
  const btt = document.createElement("button");
  btt.className = "rh-btt";
  btt.type = "button";
  btt.setAttribute("aria-label", "Back to top");
  btt.innerHTML = `
        <svg viewBox="0 0 44 44" aria-hidden="true">
            <circle class="rh-btt-track" cx="22" cy="22" r="20"></circle>
            <circle class="rh-btt-prog"  cx="22" cy="22" r="20"></circle>
        </svg>
        <span aria-hidden="true">&uarr;</span>`;
  document.body.appendChild(btt);

  const CIRC = 2 * Math.PI * 20;
  const ring = $(".rh-btt-prog", btt);
  ring.style.strokeDasharray = CIRC;

  const onScroll = () => {
    const max = document.documentElement.scrollHeight - innerHeight;
    const p = max > 0 ? Math.min(1, scrollY / max) : 0;
    ring.style.strokeDashoffset = CIRC * (1 - p);
    btt.classList.toggle("show", scrollY > 480);
  };
  addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  btt.addEventListener("click", () =>
    scrollTo({ top: 0, behavior: reduced ? "auto" : "smooth" }),
  );

  /* ---------- KEYBOARD — "/" jumps to search, Esc closes dropdown ---------- */
  document.addEventListener("keydown", (e) => {
    const typing = /input|select|textarea/i.test(
      document.activeElement?.tagName || "",
    );
    if (e.key === "/" && !typing) {
      e.preventDefault();
      search?.focus();
    }
    if (e.key === "Escape") {
      const openCat = $(".category-filter.open");
      if (openCat) {
        openCat.classList.remove("open");
        $("#categoryDropdownToggle")?.setAttribute("aria-expanded", "false");
      }
    }
  });
})();
/* =========================================================
   ROOMHIVE — LISTINGS PAGE, INTERACTIVE LAYER
   listings-interactive.js

   Loads after javaScript.js. Purely additive: none of the
   existing filter / save / sort / view logic is touched —
   this only layers the hex-and-honeycomb motion described
   in listings-interactive.css on top of what's already there.
   ========================================================= */
(() => {
  "use strict";

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
  const reduced = matchMedia("(prefers-reduced-motion: reduce)").matches;
  const canHover = matchMedia("(hover: hover)").matches;

  /* ---------- 1. HERO — cursor-lit honeycomb ---------- */
  const hero = $(".listings-greeting");

  if (hero && !reduced && canHover) {
    hero.addEventListener("pointermove", (e) => {
      const r = hero.getBoundingClientRect();
      hero.style.setProperty(
        "--hx",
        ((e.clientX - r.left) / r.width) * 100 + "%",
      );
      hero.style.setProperty(
        "--hy",
        ((e.clientY - r.top) / r.height) * 100 + "%",
      );
      hero.classList.add("rh-hero-lit");
    });
    hero.addEventListener("pointerleave", () =>
      hero.classList.remove("rh-hero-lit"),
    );
  }

  /* ---------- 2. LISTING CARDS — tilt + light sweep + hex mark ---------- */
  const cards = $$(".listing-box");

  cards.forEach((card) => {
    const media = $(".rh-card-media", card);

    if (media && !$(".rh-hex-mark", media)) {
      const mark = document.createElement("span");
      mark.className = "rh-hex-mark";
      mark.setAttribute("aria-hidden", "true");
      media.appendChild(mark);
    }

    if (reduced || !canHover) return;

    card.addEventListener("pointermove", (e) => {
      const r = card.getBoundingClientRect();
      const px = (e.clientX - r.left) / r.width; // 0..1 across the card
      const py = (e.clientY - r.top) / r.height;

      const maxTilt = 5; // degrees — kept subtle on purpose
      card.style.setProperty("--rh-tilt-y", (px - 0.5) * maxTilt * 2 + "deg");
      card.style.setProperty("--rh-tilt-x", (0.5 - py) * maxTilt * 2 + "deg");

      if (media) {
        media.style.setProperty("--smx", px * 100 + "%");
        media.style.setProperty("--smy", py * 100 + "%");
      }
    });

    card.addEventListener("pointerleave", () => {
      card.style.setProperty("--rh-tilt-x", "0deg");
      card.style.setProperty("--rh-tilt-y", "0deg");
    });
  });

  /* ---------- 3. MAGNETIC CONTROLS ---------- */
  if (!reduced && canHover) {
    $$(".rh-carousel-arrow, .rh-view-btn, .search-button").forEach((btn) => {
      btn.addEventListener("pointermove", (e) => {
        const r = btn.getBoundingClientRect();
        const dx = e.clientX - (r.left + r.width / 2);
        const dy = e.clientY - (r.top + r.height / 2);
        btn.style.setProperty(
          "--mgx",
          Math.max(-5, Math.min(5, dx * 0.28)) + "px",
        );
        btn.style.setProperty(
          "--mgy",
          Math.max(-5, Math.min(5, dy * 0.28)) + "px",
        );
      });
      btn.addEventListener("pointerleave", () => {
        btn.style.setProperty("--mgx", "0px");
        btn.style.setProperty("--mgy", "0px");
      });
    });
  }

  /* ---------- 4. ACTIVE FILTER CHIPS ----------
     listing.php already builds $activeFilters server-side but
     never renders it — this rebuilds the same idea from the
     URL so filters get removable chips without touching PHP. */
  const CATEGORY_LABELS = {
    apartment: "Apartment",
    boardinghouse: "Boarding House",
    privateroom: "Private Room",
    entirehouse: "Entire House",
    sharedbedroom: "Shared Bedroom",
    studioloft: "Studio Loft",
  };

  const AMENITY_LABELS = {
    wifi: "Wi-fi",
    parking: "Parking",
    aircon: "Aircon",
    "pet-friendly": "Pet Friendly",
    "free-water": "Free Water",
    "free-electricity": "Free Electricity",
  };

  function prettifyLocation(slug) {
    return slug
      .split("-")
      .map((w) => (w ? w.charAt(0).toUpperCase() + w.slice(1) : w))
      .join(" ");
  }

  function buildChipHref(mutate) {
    const params = new URLSearchParams(location.search);
    params.delete("page");
    mutate(params);
    const qs = params.toString();
    return location.pathname + (qs ? "?" + qs : "");
  }

  function renderChips() {
    const params = new URLSearchParams(location.search);
    const listingsMain = $(".listings-main");
    const resultsBar = $(".rh-results-bar");

    if (!listingsMain || !resultsBar) return;

    const chips = [];

    const loc = params.get("location");
    if (loc) {
      chips.push({
        label: prettifyLocation(loc),
        href: buildChipHref((p) => p.delete("location")),
      });
    }

    const cat = params.get("category");
    if (cat && CATEGORY_LABELS[cat]) {
      chips.push({
        label: CATEGORY_LABELS[cat],
        href: buildChipHref((p) => p.delete("category")),
      });
    }

    const q = params.get("q");
    if (q) {
      chips.push({
        label: `"${q}"`,
        href: buildChipHref((p) => p.delete("q")),
      });
    }

    const priceMax = params.get("price_max");
    if (priceMax && Number(priceMax) < 20000) {
      chips.push({
        label: `Up to \u20b1${Number(priceMax).toLocaleString()}`,
        href: buildChipHref((p) => p.delete("price_max")),
      });
    }

    if (params.get("amenities_submitted")) {
      const amenities = params.getAll("amenities[]");
      amenities.forEach((amenity) => {
        if (!AMENITY_LABELS[amenity]) return;
        chips.push({
          label: AMENITY_LABELS[amenity],
          href: buildChipHref((p) => {
            const remaining = amenities.filter((a) => a !== amenity);
            p.delete("amenities[]");
            remaining.forEach((a) => p.append("amenities[]", a));
            p.set("amenities_submitted", "1");
          }),
        });
      });
    }

    if (!chips.length) return;

    const row = document.createElement("div");
    row.className = "rh-chip-row";

    chips.forEach((chip, i) => {
      const a = document.createElement("a");
      a.className = "rh-chip";
      a.href = chip.href;
      a.style.animationDelay = i * 40 + "ms";
      const text = document.createElement("span");
      text.textContent = chip.label; // textContent — no injected markup from URL params
      const x = document.createElement("span");
      x.className = "rh-chip-x";
      x.setAttribute("aria-hidden", "true");
      x.textContent = "\u00d7";
      a.appendChild(text);
      a.appendChild(x);
      row.appendChild(a);
    });

    listingsMain.insertBefore(row, resultsBar);
  }

  renderChips();

  /* ---------- 5. HONEYCOMB LOADING SKELETON ---------- */
  const grid = $("#rh-listings-results");

  if (grid) {
    const overlay = document.createElement("div");
    overlay.className = "rh-skeleton-overlay";
    overlay.setAttribute("aria-hidden", "true");

    for (let i = 0; i < 6; i++) {
      overlay.appendChild(document.createElement("div")).className =
        "rh-skeleton-card";
    }

    grid.appendChild(overlay);

    const mo = new MutationObserver(() => {
      overlay.classList.toggle(
        "rh-show",
        grid.classList.contains("rh-loading"),
      );
    });

    mo.observe(grid, { attributes: true, attributeFilter: ["class"] });
  }

  /* ---------- 6. SAVE — HEX RING PULSE ---------- */
  document.addEventListener(
    "click",
    (e) => {
      const btn = e.target.closest(".rh-save-btn");
      if (!btn || reduced) return;

      const r = btn.getBoundingClientRect();
      const ring = document.createElement("span");
      ring.className = "rh-hex-ring";
      ring.style.left = r.left + r.width / 2 - 17 + "px";
      ring.style.top = r.top + r.height / 2 - 17 + "px";
      document.body.appendChild(ring);
      ring.addEventListener("animationend", () => ring.remove(), {
        once: true,
      });
    },
    true,
  );

  /* ---------- 7. EMPTY STATE — DRIFTING HEX ACCENT ---------- */
  const emptyTitle = $(".rh-empty-title");

  if (emptyTitle && !$(".rh-empty-drift")) {
    const drift = document.createElement("div");
    drift.className = "rh-empty-drift";
    drift.setAttribute("aria-hidden", "true");
    drift.appendChild(document.createElement("span"));
    drift.appendChild(document.createElement("span"));
    emptyTitle.parentElement.insertBefore(drift, emptyTitle);
  }
})();
