/**
 * LNMC Diaspora — Community Chat Assistant
 * Adapted from globalconnect/gc-chat.js. Vanilla JS, no jQuery.
 * All message content is inserted via textContent to avoid XSS.
 */
(function () {
  "use strict";

  if (typeof window === "undefined" || !window.lnmc_chat_obj) return;
  if (document.getElementById("lnmc-chat-widget")) return;

  var cfg = window.lnmc_chat_obj;
  var SVGNS = "http://www.w3.org/2000/svg";

  function ce(tag, attrs, kids) {
    var e = document.createElement(tag);
    if (attrs)
      Object.keys(attrs).forEach(function (k) {
        e.setAttribute(k, attrs[k]);
      });
    if (kids)
      (Array.isArray(kids) ? kids : [kids]).forEach(function (c) {
        if (c == null) return;
        e.appendChild(typeof c === "string" ? document.createTextNode(c) : c);
      });
    return e;
  }

  function svg(path, size) {
    size = size || 16;
    var s = document.createElementNS(SVGNS, "svg");
    s.setAttribute("width", size);
    s.setAttribute("height", size);
    s.setAttribute("viewBox", "0 0 24 24");
    s.setAttribute("fill", "currentColor");
    s.setAttribute("aria-hidden", "true");
    var p = document.createElementNS(SVGNS, "path");
    p.setAttribute("d", path);
    s.appendChild(p);
    return s;
  }

  var ICON_CHAT =
    "M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z";
  var ICON_CHEVRON = "M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z";
  var ICON_SEND = "M2 21l21-9L2 3v7l15 2-15 2z";
  var ICON_WA =
    "M20.52 3.48A11.78 11.78 0 0 0 12 0C5.37 0 .01 5.37.01 12c0 2.11.55 4.17 1.6 5.99L0 24l6.15-1.61A11.93 11.93 0 0 0 12 24c6.63 0 12-5.37 12-12 0-3.2-1.25-6.21-3.48-8.52zM12 21.82a9.8 9.8 0 0 1-5-1.37l-.36-.21-3.64.96.97-3.55-.24-.37A9.77 9.77 0 0 1 2.18 12C2.18 6.57 6.57 2.18 12 2.18 17.43 2.18 21.82 6.57 21.82 12c0 5.43-4.39 9.82-9.82 9.82zm5.38-7.35c-.3-.15-1.77-.87-2.04-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.27-.47-2.42-1.49-.9-.8-1.5-1.79-1.68-2.09-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.8.37-.27.3-1.05 1.03-1.05 2.5s1.07 2.9 1.22 3.1c.15.2 2.1 3.2 5.08 4.48.71.31 1.26.49 1.69.63.71.23 1.35.2 1.86.12.57-.08 1.77-.72 2.02-1.42.25-.7.25-1.3.17-1.42-.07-.12-.27-.2-.57-.35z";

  function buildWidget() {
    var widget = ce("div", {
      id: "lnmc-chat-widget",
      class: "lnmc-chat-closed",
      role: "complementary",
      "aria-label": "Chat Assistant",
    });

    // Header
    var title = ce("div", { class: "lnmc-chat-title" }, [
      ce("span", { class: "lnmc-status-dot", "aria-hidden": "true" }),
      ce("span", null, "LNMC Community Assistant"),
    ]);

    var controls = ce("div", { class: "lnmc-chat-controls" });
    if (cfg.whatsapp && cfg.whatsapp.length) {
      var wa = ce("a", {
        href: "https://wa.me/" + encodeURIComponent(cfg.whatsapp),
        target: "_blank",
        rel: "noopener",
        class: "lnmc-chat-handover",
        title: "Talk to a human",
        "aria-label": "Switch to WhatsApp chat",
      });
      wa.appendChild(svg(ICON_WA));
      controls.appendChild(wa);
    }
    var toggle = ce("button", {
      type: "button",
      class: "lnmc-chat-toggle",
      "aria-label": "Minimize chat",
    });
    toggle.appendChild(svg(ICON_CHEVRON, 14));
    controls.appendChild(toggle);

    var header = ce("div", { class: "lnmc-chat-header" }, [title, controls]);

    // Body — FAQ mode always works, so the greeting is never "being configured".
    var body = ce("div", {
      class: "lnmc-chat-body",
      id: "lnmc-chat-messages",
      role: "log",
      "aria-live": "polite",
      "aria-label": "Chat messages",
    });
    var greeting =
      "As-salamu alaykum and welcome. I can help you learn about our mission, membership tiers, events, and resources. How can I assist you today?";
    body.appendChild(ce("div", { class: "lnmc-chat-msg bot" }, greeting));

    // Input
    var input = ce("input", {
      type: "text",
      id: "lnmc-chat-input",
      placeholder: "Type a message...",
      "aria-label": "Type your message",
      maxlength: "500",
    });
    var sendBtn = ce("button", {
      type: "button",
      id: "lnmc-chat-send",
      "aria-label": "Send message",
    });
    sendBtn.appendChild(svg(ICON_SEND));

    // Honeypot field — hidden from humans, bots fill every input.
    // Named `lnmc_hp` so the server can silently absorb submissions that have it set.
    var honeypot = ce("input", {
      type: "text",
      name: "lnmc_hp",
      id: "lnmc_hp",
      tabindex: "-1",
      autocomplete: "off",
      "aria-hidden": "true",
    });
    honeypot.style.cssText =
      "position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;";

    var inputArea = ce("div", { class: "lnmc-chat-input-area" }, [
      input,
      sendBtn,
      honeypot,
    ]);

    // Launcher
    var launcher = ce("button", {
      type: "button",
      class: "lnmc-chat-launcher",
      "aria-label": "Open chat assistant",
    });
    launcher.appendChild(svg(ICON_CHAT, 22));

    // Disclosure
    var disclosure = ce(
      "p",
      { class: "lnmc-chat-disclosure" },
      "Powered by OpenAI. Conversations are not used for training.",
    );

    widget.appendChild(header);
    widget.appendChild(body);
    widget.appendChild(inputArea);
    widget.appendChild(launcher);
    widget.appendChild(disclosure);
    return {
      widget: widget,
      launcher: launcher,
      toggle: toggle,
      body: body,
      input: input,
      sendBtn: sendBtn,
    };
  }

  function appendMsg(body, role, text) {
    var m = ce("div", { class: "lnmc-chat-msg " + role });
    // Use textContent to safely render user/bot text; preserve line breaks via <br> inserted safely
    var parts = String(text).split("\n");
    parts.forEach(function (line, i) {
      m.appendChild(document.createTextNode(line));
      if (i < parts.length - 1) m.appendChild(document.createElement("br"));
    });
    body.appendChild(m);
    body.scrollTop = body.scrollHeight;
    return m;
  }

  function init() {
    var parts = buildWidget();
    document.body.appendChild(parts.widget);

    var widget = parts.widget;
    var body = parts.body;
    var input = parts.input;
    var sendBtn = parts.sendBtn;
    var honeypot = document.getElementById("lnmc_hp");
    var sending = false;

    function open() {
      widget.classList.remove("lnmc-chat-closed");
      widget.classList.add("lnmc-chat-open");
      input.focus();
    }
    function close() {
      widget.classList.remove("lnmc-chat-open");
      widget.classList.add("lnmc-chat-closed");
      parts.launcher.focus();
    }

    parts.launcher.addEventListener("click", open);
    parts.toggle.addEventListener("click", close);
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && widget.classList.contains("lnmc-chat-open"))
        close();
    });

    function setBusy(state) {
      sending = state;
      input.disabled = state;
      sendBtn.disabled = state;
      if (state) sendBtn.setAttribute("aria-busy", "true");
      else sendBtn.removeAttribute("aria-busy");
    }

    function getHistory() {
      var out = [];
      var nodes = body.querySelectorAll(".lnmc-chat-msg:not(.typing)");
      // Last 5 before the one we just appended
      var slice = Array.prototype.slice.call(nodes, -6, -1);
      slice.forEach(function (n) {
        var role = n.classList.contains("user") ? "user" : "assistant";
        var content = n.textContent.trim();
        if (content) out.push({ role: role, content: content });
      });
      return out;
    }

    function buildTyping() {
      var t = ce("div", { class: "lnmc-chat-msg bot typing" });
      var dots = ce("span", { class: "lnmc-typing-dots" }, [
        ce("span"),
        ce("span"),
        ce("span"),
      ]);
      t.appendChild(dots);
      return t;
    }

    function send() {
      var msg = input.value.trim();
      if (!msg || sending) return;

      setBusy(true);
      appendMsg(body, "user", msg);
      input.value = "";

      var typing = buildTyping();
      body.appendChild(typing);
      body.scrollTop = body.scrollHeight;

      var history = getHistory();

      function call(retries) {
        fetch(cfg.root + "lnmc/v1/chat", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": cfg.nonce,
          },
          body: JSON.stringify({
            message: msg,
            history: history,
            lnmc_hp: honeypot ? honeypot.value : "",
          }),
        })
          .then(function (r) {
            return r.json().then(function (j) {
              return { ok: r.ok, status: r.status, body: j };
            });
          })
          .then(function (res) {
            if (typing.parentNode) typing.parentNode.removeChild(typing);
            if (!res.ok) {
              var errText =
                res.body && res.body.message
                  ? res.body.message
                  : "Connection error. Please try again.";
              appendMsg(body, "bot error", errText);
            } else if (res.body && res.body.reply) {
              appendMsg(body, "bot", res.body.reply);
            } else {
              appendMsg(
                body,
                "bot error",
                "Sorry, I didn't catch that. Try again?",
              );
            }
            setBusy(false);
          })
          .catch(function () {
            if (retries > 0) {
              setTimeout(function () {
                call(retries - 1);
              }, 1500);
              return;
            }
            if (typing.parentNode) typing.parentNode.removeChild(typing);
            appendMsg(body, "bot error", "Connection error. Please try again.");
            setBusy(false);
          });
      }

      call(1);
    }

    sendBtn.addEventListener("click", send);
    input.addEventListener("keypress", function (e) {
      if (e.key === "Enter") send();
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
