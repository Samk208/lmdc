/**
 * LNMC gallery viewer.
 *
 * Upgrades the [lnmc_gallery] tiles into a modal viewer using the native
 * <dialog> element, so focus trapping, the Escape key and inertness of the
 * page behind come from the browser rather than from script. If anything
 * here fails the tiles remain ordinary links to the full-size image.
 */
(function () {
  "use strict";

  var SUPPORTS_DIALOG =
    typeof HTMLDialogElement !== "undefined" &&
    typeof HTMLDialogElement.prototype.showModal === "function";

  if (!SUPPORTS_DIALOG) {
    return;
  }

  var gallery = null;
  var links = [];
  var index = 0;
  var dialog = null;
  var image = null;
  var caption = null;
  var counter = null;
  var lastFocused = null;
  var swipeStartX = 0;
  var swipeStartY = 0;

  /**
   * Build one navigational button: [modifier, data attribute, label, glyph].
   */
  function makeControl(spec) {
    var button = document.createElement("button");
    var glyph = document.createElement("span");

    button.type = "button";
    button.className = "lnmc-lightbox__button lnmc-lightbox__" + spec[0];
    button.setAttribute(spec[1], "");
    button.setAttribute("aria-label", spec[2]);
    glyph.setAttribute("aria-hidden", "true");
    glyph.textContent = spec[3];
    button.appendChild(glyph);

    return button;
  }

  function build() {
    if (dialog) {
      return dialog;
    }

    dialog = document.createElement("dialog");
    dialog.className = "lnmc-lightbox";
    dialog.setAttribute("aria-label", "Photo viewer");

    // Built node by node rather than from an HTML string so the viewer never
    // parses markup, and so each control carries a real accessible name.
    var controls = [
      ["close", "data-lnmc-close", "Close photo viewer", "\u00D7"],
      ["prev", "data-lnmc-prev", "Previous photo", "\u2039"],
      ["next", "data-lnmc-next", "Next photo", "\u203A"],
    ];

    var figure = document.createElement("figure");
    image = document.createElement("img");
    caption = document.createElement("figcaption");

    figure.className = "lnmc-lightbox__figure";
    image.className = "lnmc-lightbox__img";
    image.alt = "";
    image.decoding = "async";
    caption.className = "lnmc-lightbox__caption";
    figure.appendChild(image);
    figure.appendChild(caption);

    counter = document.createElement("p");
    counter.className = "lnmc-lightbox__counter";
    counter.setAttribute("aria-live", "polite");
    counter.setAttribute("aria-atomic", "true");

    dialog.appendChild(makeControl(controls[0]));
    dialog.appendChild(makeControl(controls[1]));
    dialog.appendChild(figure);
    dialog.appendChild(makeControl(controls[2]));
    dialog.appendChild(counter);

    document.body.appendChild(dialog);

    dialog.addEventListener("click", function (event) {
      var target = event.target.closest(
        "[data-lnmc-close], [data-lnmc-prev], [data-lnmc-next]",
      );

      if (target) {
        if (target.hasAttribute("data-lnmc-close")) {
          dialog.close();
        } else if (target.hasAttribute("data-lnmc-prev")) {
          show(index - 1);
        } else {
          show(index + 1);
        }
        return;
      }

      // Clicking the backdrop (i.e. the dialog itself, not the photo or a
      // control) dismisses the viewer, which is what people expect.
      if (event.target === dialog) {
        dialog.close();
      }
    });

    dialog.addEventListener("keydown", function (event) {
      if (event.key === "ArrowRight") {
        event.preventDefault();
        show(index + 1);
      } else if (event.key === "ArrowLeft") {
        event.preventDefault();
        show(index - 1);
      } else if (event.key === "Home") {
        event.preventDefault();
        show(0);
      } else if (event.key === "End") {
        event.preventDefault();
        show(links.length - 1);
      }
    });

    dialog.addEventListener(
      "touchstart",
      function (event) {
        if (event.touches.length !== 1) {
          return;
        }
        swipeStartX = event.touches[0].clientX;
        swipeStartY = event.touches[0].clientY;
      },
      { passive: true },
    );

    dialog.addEventListener(
      "touchend",
      function (event) {
        if (!swipeStartX || !event.changedTouches.length) {
          return;
        }

        var dx = event.changedTouches[0].clientX - swipeStartX;
        var dy = event.changedTouches[0].clientY - swipeStartY;

        // Ignore mostly-vertical gestures so scrolling the caption works.
        if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy)) {
          show(dx < 0 ? index + 1 : index - 1);
        }

        swipeStartX = 0;
      },
      { passive: true },
    );

    dialog.addEventListener("close", function () {
      document.body.classList.remove("lnmc-lightbox-open");
      image.removeAttribute("src");

      if (lastFocused && typeof lastFocused.focus === "function") {
        lastFocused.focus({ preventScroll: true });
      }
    });

    dialog.addEventListener("cancel", function () {
      document.body.classList.remove("lnmc-lightbox-open");
    });

    return dialog;
  }

  function show(next) {
    if (!links.length) {
      return;
    }

    // Wrap around so the viewer never dead-ends.
    index = (next + links.length) % links.length;

    var link = links[index];
    var thumb = link.querySelector("img");
    var url = link.getAttribute("data-full") || link.getAttribute("href");
    var text = link.getAttribute("data-caption") || "";

    dialog.classList.add("is-loading");

    var preload = new Image();
    preload.onload = function () {
      image.src = url;
      image.alt = thumb ? thumb.alt : text;
      image.width = preload.naturalWidth;
      image.height = preload.naturalHeight;
      dialog.classList.remove("is-loading");
    };
    preload.onerror = function () {
      image.src = url;
      dialog.classList.remove("is-loading");
    };
    preload.src = url;

    caption.textContent = text;
    caption.hidden = !text;
    counter.textContent = index + 1 + " / " + links.length;

    // Warm the cache for the neighbours so arrow keys feel instant.
    [
      links[(index + 1) % links.length],
      links[(index - 1 + links.length) % links.length],
    ].forEach(function (neighbour) {
      if (!neighbour) {
        return;
      }
      var warm = new Image();
      warm.src = neighbour.getAttribute("data-full") || neighbour.href;
    });
  }

  function open(link) {
    build();

    lastFocused = link;
    index = links.indexOf(link);

    document.body.classList.add("lnmc-lightbox-open");
    show(index < 0 ? 0 : index);

    if (!dialog.open) {
      dialog.showModal();
    }
  }

  document.addEventListener("click", function (event) {
    var link = event.target.closest("[data-lnmc-gallery] .lnmc-gallery__link");

    if (!link) {
      return;
    }

    // Let people open photos in a new tab / download them as normal.
    if (
      event.metaKey ||
      event.ctrlKey ||
      event.shiftKey ||
      event.altKey ||
      event.button !== 0
    ) {
      return;
    }

    gallery = link.closest("[data-lnmc-gallery]");
    links = Array.prototype.slice.call(
      gallery.querySelectorAll(".lnmc-gallery__link"),
    );

    event.preventDefault();
    open(link);
  });
})();
