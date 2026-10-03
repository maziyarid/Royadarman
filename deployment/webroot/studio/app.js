const app = document.getElementById("app");

function shell(main, active) {
  const people = PROFILES.map((item) => {
    const on = active === "p:" + item[0] ? " on" : "";
    return `<a class="${on}" href="#/p/${item[0]}"><img src="avatars/${item[0]}.jpg" alt="" />${esc(nameOf(item))}</a>`;
  }).join("");
  const groups = ["phone", "desk", "clinical"]
    .map((group) => {
      const label = group === "phone" ? ui("phone") : group === "desk" ? ui("desk") : ui("clinical");
      const links = BOARDS.filter((item) => item[1] === group)
        .map((item) => `<a class="${active === "b:" + item[0] ? "on" : ""}" href="#/b/${item[0]}">${esc(boardName(item))}</a>`)
        .join("");
      return `<div class="nav-group">${esc(label)}</div>${links}`;
    })
    .join("");
  app.innerHTML = `<div class="shell"><aside class="side"><a class="brand" href="#/">${esc(ui("studio"))}</a>
    <div class="langs"><button type="button" data-lang="fa" class="${LANG === "fa" ? "on" : ""}">فا</button>
    <button type="button" data-lang="en" class="${LANG === "en" ? "on" : ""}">EN</button>
    <button type="button" data-lang="ar" class="${LANG === "ar" ? "on" : ""}">ع</button>
    <button type="button" class="menu" id="menu">${esc(ui("menu"))}</button></div>
    <nav class="nav" id="nav"><div class="nav-group">${esc(ui("profiles"))}</div>${people}${groups}</nav></aside>
    <main class="main">${main}</main></div>`;
  app.querySelectorAll("[data-lang]").forEach((button) => {
    button.addEventListener("click", () => {
      LANG = button.getAttribute("data-lang");
      localStorage.setItem("roya-studio-lang", LANG);
      render();
    });
  });
  document.getElementById("menu").addEventListener("click", () => document.getElementById("nav").classList.toggle("open"));
}

function wire() {
  const keyForm = document.getElementById("key-form");
  if (keyForm) {
    const list = JSON.parse(localStorage.getItem("roya-studio-keys") || "[]");
    const ul = document.getElementById("keys");
    ul.innerHTML = list.map((item) => `<li>${esc(item)}</li>`).join("");
    keyForm.addEventListener("submit", (event) => {
      event.preventDefault();
      const label = new FormData(keyForm).get("label") || "preview";
      list.push(String(label));
      localStorage.setItem("roya-studio-keys", JSON.stringify(list));
      ul.innerHTML = list.map((item) => `<li>${esc(item)}</li>`).join("");
      keyForm.reset();
    });
  }
  const msg = document.getElementById("msg-form");
  if (msg) {
    msg.addEventListener("submit", (event) => {
      event.preventDefault();
      const text = String(new FormData(msg).get("text") || "").trim();
      if (!text) return;
      const host = document.getElementById("msgs") || document.getElementById("thread");
      const line = document.createElement("p");
      line.textContent = text;
      host.appendChild(line);
      if (host.id === "thread") {
        const reply = document.createElement("p");
        reply.className = "muted";
        reply.textContent = tr(["ثبت شد. تشخیصی داده نمی‌شود.", "Saved. No clinical reading is given.", "سُجّل. لا تُعطى قراءة سريرية."]);
        host.appendChild(reply);
      }
      msg.reset();
    });
  }
  const draft = document.getElementById("draft");
  if (draft) {
    draft.addEventListener("click", () => {
      document.getElementById("draft-note").textContent = tr(["پیش‌نویس ماند. منتشر نشد.", "Draft kept. Not published.", "بقيت المسودة. لم تُنشر."]);
    });
  }
  const note = document.getElementById("note-form");
  if (note) {
    note.addEventListener("submit", (event) => {
      event.preventDefault();
      const text = String(new FormData(note).get("text") || "").trim();
      if (!text) return;
      const li = document.createElement("li");
      li.textContent = text;
      document.getElementById("notes").appendChild(li);
      note.reset();
    });
  }
  document.querySelectorAll("[data-tooth]").forEach((button) => {
    button.addEventListener("click", () => {
      const order = ["", "watch", "image"];
      const current = button.classList.contains("image") ? "image" : button.classList.contains("watch") ? "watch" : "";
      const next = order[(order.indexOf(current) + 1) % order.length];
      button.classList.remove("watch", "image", "on");
      if (next) button.classList.add(next);
      const saved = {};
      document.querySelectorAll("[data-tooth]").forEach((node) => {
        const state = node.classList.contains("image") ? "image" : node.classList.contains("watch") ? "watch" : "";
        if (state) saved[node.getAttribute("data-tooth")] = state;
      });
      localStorage.setItem("roya-teeth", JSON.stringify(saved));
      const slot = document.getElementById("picked");
      if (slot) slot.textContent = Object.entries(saved).map(([tooth, state]) => tooth + " " + state).join(" · ");
    });
  });
  document.querySelectorAll("[data-month]").forEach((button) => {
    button.addEventListener("click", () => {
      CAL_MONTH = Number(button.getAttribute("data-month"));
      render();
    });
  });
  document.querySelectorAll("[data-date]").forEach((button) => {
    button.addEventListener("click", () => {
      const key = button.getAttribute("data-date");
      const titles = HOLIDAYS_1405[key] || [];
      const note = document.getElementById("daynote");
      const friday = button.classList.contains("fri");
      const bits = [];
      if (friday) bits.push(tr(["جمعه، نشان است نه تعطیلی درمانگاه", "Friday marker, not a closure", "الجمعة علامة وليست إغلاقاً"]));
      if (titles.length) bits.push(titles.join(" · "));
      if (!bits.length) bits.push(tr(["روز عادی نمونه", "Ordinary sample day", "يوم عادي نموذجي"]));
      if (note) note.textContent = key + " — " + bits.join(" — ");
    });
  });
  document.querySelectorAll("[data-clinic]").forEach((button) => {
    button.addEventListener("click", () => {
      const card = document.getElementById("clinic-" + button.getAttribute("data-clinic"));
      document.querySelectorAll(".pin").forEach((pin) => pin.classList.remove("on"));
      if (card) card.classList.add("on");
    });
  });
  document.querySelectorAll("[data-arrive]").forEach((button) => {
    button.addEventListener("click", () => {
      const states = [
        tr(["حاضر", "Here", "حاضر"]),
        tr(["نرسیده", "Not in", "لم يصل"]),
        tr(["پیشنهاد", "Suggested", "مقترح"]),
      ];
      const index = states.indexOf(button.textContent);
      button.textContent = states[index < 0 ? 0 : (index + 1) % states.length];
    });
  });
  document.querySelectorAll("[data-move]").forEach((button) => {
    button.addEventListener("click", () => {
      const card = button.closest("article");
      const col = card.parentElement;
      const next = col.nextElementSibling || col.parentElement.firstElementChild;
      next.appendChild(card);
    });
  });
  document.querySelectorAll("[data-step]").forEach((button) => {
    button.addEventListener("click", () => {
      const states = [
        tr(["در صف", "Queued", "في الطابور"]),
        tr(["آماده", "Ready", "جاهز"]),
        tr(["تحویل نمونه", "Sample handed", "تسليم نموذجي"]),
      ];
      const index = states.indexOf(button.textContent);
      const next = states[index < 0 ? 0 : (index + 1) % states.length];
      button.textContent = next;
      button.classList.toggle("ok", next !== states[0]);
    });
  });
  const query = document.getElementById("q");
  if (query) {
    query.addEventListener("input", () => {
      const needle = query.value.trim();
      let shown = 0;
      document.querySelectorAll("[data-q]").forEach((row) => {
        const hit = !needle || row.getAttribute("data-q").includes(needle);
        row.classList.toggle("hidden", !hit);
        if (hit) shown += 1;
      });
      const found = document.getElementById("found");
      if (found) found.textContent = needle ? String(shown) : "";
    });
  }
  document.querySelectorAll("[data-read]").forEach((button) => {
    button.addEventListener("click", () => button.classList.add("on"));
  });
  const opg = document.getElementById("opg");
  if (opg) {
    document.querySelectorAll("[data-br]").forEach((button) => {
      button.addEventListener("click", () => {
        opg.classList.remove("br1", "br2", "br3");
        opg.classList.add(button.getAttribute("data-br"));
      });
    });
    document.querySelectorAll("[data-zoom]").forEach((button) => {
      button.addEventListener("click", () => {
        opg.classList.remove("z1", "z2", "z3");
        opg.classList.add(button.getAttribute("data-zoom"));
      });
    });
  }
  document.querySelectorAll(".mark").forEach((button) => {
    button.addEventListener("click", () => button.classList.toggle("on"));
  });
  document.querySelectorAll(".card [data-mark]").forEach((button) => {
    button.addEventListener("click", () => {
      const mark = document.querySelector(".mark." + button.getAttribute("data-mark"));
      if (mark) mark.classList.toggle("on");
    });
  });
  document.querySelectorAll("[data-suggest]").forEach((button) => {
    button.addEventListener("click", () => {
      const area = document.querySelector("#msg-form textarea");
      if (area) area.value = button.textContent;
    });
  });
  document.querySelectorAll("[data-service]").forEach((button) => {
    button.addEventListener("click", () => {
      document.querySelectorAll("[data-service]").forEach((item) => item.classList.remove("on"));
      button.classList.add("on");
      const note = document.getElementById("service-note");
      if (note) note.textContent = button.getAttribute("data-service");
    });
  });
}

function render() {
  document.documentElement.lang = LANG;
  document.documentElement.dir = LANG === "en" ? "ltr" : "rtl";
  const parts = location.hash.replace(/^#\/?/, "").split("/").filter(Boolean);
  if (parts[0] === "p" && parts[1]) {
    shell(profileView(parts[1], parts[2] || ""), "p:" + parts[1]);
    wire();
    return;
  }
  if (parts[0] === "b" && parts[1]) {
    shell(boardView(parts[1]), "b:" + parts[1]);
    wire();
    return;
  }
  shell(homeView(), "");
}

window.addEventListener("hashchange", render);
render();
