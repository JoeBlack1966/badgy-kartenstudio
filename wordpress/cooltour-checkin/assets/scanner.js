/* Cooltour Check-in: QR-Scanner auf der Einlass-Seite. Liest die Kamera mit jsQR und prüft jede Karte über die REST-Schnittstelle. */
(function () {
  "use strict";
  var cfg = window.CTC || {};
  var video = document.getElementById("ctc-video");
  var hint = document.getElementById("ctc-hint");
  var resultBox = document.getElementById("ctc-result");
  var countEl = document.getElementById("ctc-count");
  var lastList = document.getElementById("ctc-last");
  var canvas = document.createElement("canvas");
  var ctx = canvas.getContext("2d", { willReadFrequently: true });
  var lastCode = "", lastTime = 0, busy = false, lastFrame = 0;
  var audio = null;

  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }

  function beep(ok) {
    try {
      audio = audio || new (window.AudioContext || window.webkitAudioContext)();
      var o = audio.createOscillator(), g = audio.createGain();
      o.frequency.value = ok ? 1320 : 220;
      g.gain.value = 0.15;
      o.connect(g); g.connect(audio.destination);
      o.start(); o.stop(audio.currentTime + (ok ? 0.12 : 0.45));
    } catch (e) { /* ohne Ton weiter */ }
    if (navigator.vibrate) navigator.vibrate(ok ? 80 : [200, 80, 200]);
  }

  function showResult(r) {
    resultBox.textContent = "";
    var box = el("div", "result " + r.status);
    box.appendChild(el("p", "t", r.title));
    box.appendChild(el("p", "r", r.reason));
    if (r.card) {
      var c = r.card;
      box.appendChild(el("div", "name", c.name || "(ohne Namen)"));
      var dl = el("dl");
      function row(k, v, href) {
        if (!v) return;
        dl.appendChild(el("dt", "", k));
        var dd = el("dd");
        if (href) { var a = el("a", "", v); a.href = href; dd.appendChild(a); } else { dd.textContent = v; }
        dl.appendChild(dd);
      }
      row("Karte", ((c.kartenart || "") + " " + (c.kategorie || "")).trim());
      row("Nummer", c.nummer);
      row("Gültig bis", c.gueltig_bis);
      row("Besuche", String(r.besuche));
      row("Notiz", c.notiz);
      row("Telefon", c.telefon, c.telefon ? "tel:" + c.telefon.replace(/[^0-9+]/g, "") : "");
      row("E-Mail", c.email, c.email ? "mailto:" + c.email : "");
      box.appendChild(dl);
    }
    resultBox.appendChild(box);
    if (countEl && typeof r.gaeste === "number") countEl.textContent = r.gaeste;
    if (r.status === "gruen" && r.card && lastList) {
      var li = el("li");
      li.appendChild(el("span", "", r.card.name || r.card.nummer));
      var d = new Date();
      li.appendChild(el("span", "muted", ("0" + d.getHours()).slice(-2) + ":" + ("0" + d.getMinutes()).slice(-2)));
      lastList.insertBefore(li, lastList.firstChild);
      while (lastList.children.length > 8) lastList.removeChild(lastList.lastChild);
    }
    beep(r.status === "gruen");
    box.scrollIntoView({ behavior: "smooth", block: "nearest" });
  }

  function check(body) {
    busy = true;
    return fetch(cfg.rest, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json", "X-WP-Nonce": cfg.nonce },
      body: JSON.stringify(body)
    }).then(function (res) {
      if (res.status === 401 || res.status === 403) throw new Error("Nicht angemeldet – bitte Seite neu laden und einloggen.");
      if (!res.ok) throw new Error("Fehler " + res.status);
      return res.json();
    }).then(showResult).catch(function (e) {
      showResult({ status: "rot", title: "Fehler", reason: e.message || "Keine Verbindung" });
    }).then(function () { busy = false; });
  }

  function tick(now) {
    requestAnimationFrame(tick);
    if (busy || video.readyState < 2 || now - lastFrame < 150) return;
    lastFrame = now;
    var vw = video.videoWidth, vh = video.videoHeight;
    if (!vw) return;
    var scale = Math.min(1, 640 / Math.max(vw, vh));
    canvas.width = Math.round(vw * scale); canvas.height = Math.round(vh * scale);
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    var img = ctx.getImageData(0, 0, canvas.width, canvas.height);
    var code = window.jsQR && window.jsQR(img.data, img.width, img.height, { inversionAttempts: "attemptBoth" });
    if (!code || !code.data) return;
    // Dieselbe Karte erst nach 5 Sekunden wieder prüfen.
    if (code.data === lastCode && Date.now() - lastTime < 5000) return;
    lastCode = code.data; lastTime = Date.now();
    check({ qr: code.data });
  }

  function startCamera() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      hint.textContent = "Kamera nicht verfügbar – bitte Handy-Kamera-App oder Kartennummer verwenden.";
      return;
    }
    navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" }, audio: false }).then(function (stream) {
      video.srcObject = stream;
      return video.play();
    }).then(function () {
      hint.textContent = "QR-Code der Karte in den Rahmen halten";
      requestAnimationFrame(tick);
    }).catch(function () {
      hint.textContent = "Kein Kamerazugriff – bitte im Browser erlauben und Seite neu laden.";
    });
  }

  document.getElementById("ctc-manual").addEventListener("submit", function (e) {
    e.preventDefault();
    var input = document.getElementById("ctc-nummer");
    var nr = input.value.trim();
    if (!nr) return;
    check({ nummer: nr }).then(function () { input.value = ""; });
  });

  startCamera();
})();
