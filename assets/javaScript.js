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

  const canAnimate =
    !prefersReducedMotion && "IntersectionObserver" in window;

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
        img.addEventListener("error", show, { once: true }); /* never hide broken imgs */
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