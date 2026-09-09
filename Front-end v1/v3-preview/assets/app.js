const app = document.querySelector("#app");
const toastRegion = document.querySelector("#toast-region");

const ICONS = {
  arrow: '<path d="m9 18 6-6-6-6"/>',
  check: '<path d="m20 6-11 11-5-5"/>',
  clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  heart: '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8z"/>',
  home: '<path d="M3 10.8 12 3l9 7.8v9a1.2 1.2 0 0 1-1.2 1.2H4.2A1.2 1.2 0 0 1 3 19.8z"/><path d="M9 21v-7h6v7"/>',
  image: '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="m21 15-5-5L5 20"/>',
  location: '<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="2.5"/>',
  message: '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/><path d="M8 9h8M8 13h5"/>',
  phone: '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.8a2 2 0 0 1-.4 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
  shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/>',
  upload: '<path d="M12 16V4M7 9l5-5 5 5M4 20h16"/>',
  user: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
  users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
  wallet: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h2"/>',
  x: '<path d="M18 6 6 18M6 6l12 12"/>',
  alert: '<path d="M10.3 3.7 2.2 18a2 2 0 0 0 1.7 3h16.2a2 2 0 0 0 1.7-3L13.7 3.7a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
  lock: '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
  file: '<path d="M6 2h8l4 4v16H6z"/><path d="M14 2v5h5M9 13h6M9 17h6"/>',
  copy: '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
};

function icon(name, className = "") {
  return `<svg class="icon ${className}" viewBox="0 0 24 24" aria-hidden="true">${ICONS[name] || ICONS.heart}</svg>`;
}

function esc(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

const state = {
  service: "",
  name: "",
  mobile: "",
  area: "",
  contactTime: "",
  reason: "",
  budget: "",
  file: null,
  fileState: "empty",
  fileError: "",
  consent: false,
  errors: {},
  submitted: false,
};

const serviceMeta = {
  guidance: {
    title: "راهنمایی و معرفی مرکز",
    short: "راهنمایی و ارجاع",
    icon: "message",
    description: "گفت‌وگو با تیم پشتیبانی و معرفی گزینه‌های مناسب از میان دندانپزشکان و مراکز همکار.",
  },
  home: {
    title: "دندانپزشکی در منزل",
    short: "خدمت در منزل",
    icon: "home",
    description: "بررسی درخواست و هماهنگی خدمت قابل ارائه در منزل؛ پوشش فعلی فقط شهر تهران است.",
  },
  opg: {
    title: "بررسی اولیه تصویر OPG",
    short: "بررسی OPG",
    icon: "image",
    description: "ارسال امن تصویر برای بررسی اولیه توسط دندانپزشک دارای مجوز و پیشنهاد گزینه‌های متناسب با بودجه اعلام‌شده.",
  },
};

const budgetMeta = {
  economic: "اقتصادی؛ اولویت با کنترل هزینه",
  balanced: "متعادل؛ توازن هزینه و انتخاب‌ها",
  flexible: "انعطاف‌پذیر؛ اولویت با گزینه‌های گسترده‌تر",
  call: "مایلم بودجه را تلفنی مطرح کنم",
};

const stepMeta = {
  service: ["انتخاب خدمت", "مسیر موردنیازتان را مشخص کنید."],
  contact: ["راه ارتباط و نیاز شما", "برای تماس کارشناس، اطلاعات ضروری را وارد کنید."],
  document: ["تصویر OPG", "فایل فقط در این پیش‌نمایش محلی بررسی می‌شود و ارسال نخواهد شد."],
  preferences: ["بودجه و ترجیحات", "بودجه یک ترجیح عملیاتی است و جای معیارهای بالینی را نمی‌گیرد."],
  review: ["بازبینی و رضایت", "اطلاعات را مرور کنید و درباره نحوه استفاده از آن تصمیم بگیرید."],
  receipt: ["پیش‌نمایش ثبت درخواست", "این نسخه هنوز به سامانه امن ثبت پرونده متصل نیست."],
};

function route() {
  const parts = location.hash.replace(/^#\/?/, "").split("/").filter(Boolean);
  if (parts[0] === "request") return { mode: "request", step: parts[1] || "service" };
  if (parts[0] === "receipt") return { mode: "request", step: "receipt" };
  return { mode: "home" };
}

function steps() {
  return state.service === "opg"
    ? ["service", "contact", "document", "preferences", "review"]
    : ["service", "contact", "preferences", "review"];
}

function goToStep(step) {
  location.hash = step === "receipt" ? "#/receipt" : `#/request/${step}`;
}

function shell(content, compact = false) {
  return `
    <header class="site-header ${compact ? "is-compact" : ""}">
      <div class="container header-inner">
        <a class="brand-lockup" href="#/home" aria-label="رویا درمان، صفحه اصلی">
          <img class="brand-mark" src="assets/logo-mark.svg" alt="" width="66" height="50">
          <span class="brand-copy"><strong>رویا درمان</strong><small>مرکز درمان و بیماریابی</small></span>
        </a>
        ${compact ? `
          <a class="button button-quiet header-back" href="#/home">بازگشت به صفحه اصلی ${icon("arrow", "icon-sm")}</a>
        ` : `
          <nav class="site-nav" aria-label="فهرست اصلی">
            <a href="#about">درباره ما</a>
            <a class="is-active" href="#services">خدمات</a>
            <a href="#articles">مقالات</a>
            <a href="#contact">تماس با ما</a>
          </nav>
          <a class="button button-login header-cta" href="#/request/service">${icon("user", "icon-sm")} ورود / ثبت نام</a>
        `}
      </div>
    </header>
    ${content}
  `;
}

function renderHome() {
  document.body.className = "page-home accepted-home";
  document.title = "رویا درمان | مسیر درمان روشن و قابل پیگیری";
  app.innerHTML = shell(`
    <main id="main-content">
      <section class="accepted-hero" id="about">
        <div class="container accepted-hero-grid">
          <div class="accepted-copy">
            <span class="support-pill">${icon("clock", "icon-sm")} پشتیبانی و پاسخ‌گویی ۲۴ ساعته</span>
            <h1>مسیر درمان، برای شما روشن است.<br><span>با توجه به نیاز، شرایط و بودجه شما</span></h1>
            <div class="accepted-lede">
              <strong>نیاز به درمان دارید اما نمی‌دانید از کجا شروع کنید؟</strong>
              <p>رویا درمان با بررسی دقیق شرایط شما توسط تیم دندانپزشکی، مسیر درمان را شفاف و قابل تصمیم‌گیری می‌کند.</p>
            </div>
            <div class="accepted-actions">
              <a class="button accepted-primary" href="#/request/service">ثبت درخواست و شروع مسیر درمان ${icon("arrow", "icon-sm")}</a>
              <a class="button accepted-secondary" href="#services">بیشتر درباره رویا درمان</a>
            </div>
            <div class="accepted-benefits" aria-label="مزیت‌های رویا درمان">
              <div class="accepted-benefit">
                <span>${icon("users")}</span>
                <div><strong>شبکه مراکز درمانی معتبر</strong><small>دسترسی به مراکز منتخب در نقاط مختلف شهر</small></div>
              </div>
              <div class="accepted-benefit">
                <span>${icon("wallet")}</span>
                <div><strong>طرح درمان متناسب با شرایط شما</strong><small>بررسی بودجه و انتخاب بهترین گزینه عملی</small></div>
              </div>
              <div class="accepted-benefit">
                <span>${icon("shield")}</span>
                <div><strong>پشتیبانی ۲۴ ساعته</strong><small>همراه شما قبل و بعد از درمان</small></div>
              </div>
            </div>
          </div>

          <div class="accepted-visual">
            <article class="accepted-opg-card">
              <img class="accepted-opg-image" src="assets/opg-hero.svg" alt="نمای نمونه تصویر پانورامیک دندان برای بررسی اولیه">
              <div class="accepted-opg-path" aria-hidden="true"><i></i><i></i><i></i></div>
              <span class="accepted-opg-chip">${icon("image", "icon-sm")} ارسال عکس OPG</span>
              <div class="accepted-shield">${icon("shield")}</div>
              <div class="accepted-opg-copy">
                <strong>درمان مطمئن، از تصمیم آگاهانه شروع می‌شود.</strong>
                <p>پیشنهاد گزینه‌های مناسب بدون نگرانی از هزینه‌های غیرضروری</p>
              </div>
            </article>
          </div>
        </div>
      </section>

      <section class="accepted-afterfold" id="services">
        <div class="container accepted-service-grid">
          <article><span>${icon("image")}</span><h2>بررسی اولیه OPG</h2><p>تصویر و توضیحات شما برای بررسی اولیه به دندانپزشک مرتبط ارجاع می‌شود و قدم بعدی به زبان روشن توضیح داده می‌شود.</p><a href="#/request/service" data-start-service="opg">ثبت درخواست بررسی</a></article>
          <article><span>${icon("home")}</span><h2>دندانپزشکی در منزل</h2><p>درخواست خدمات قابل ارائه در منزل فعلاً برای شهر تهران بررسی و با توجه به نوع خدمت و محدوده هماهنگ می‌شود.</p><a href="#/request/service" data-start-service="home">ثبت درخواست منزل</a></article>
          <article><span>${icon("message")}</span><h2>راهنمایی و معرفی مرکز</h2><p>نیاز، موقعیت و شرایط مالی شما کنار هم دیده می‌شوند تا گزینه‌های مناسب برای ادامه درمان معرفی شوند.</p><a href="#/request/service" data-start-service="guidance">شروع راهنمایی</a></article>
        </div>
      </section>

      <section class="accepted-info" id="articles">
        <div class="container accepted-info-inner">
          <div><span>شفافیت قبل از درمان</span><h2>OPG می‌تواند شروع مسیر باشد، نه پایان تشخیص.</h2><p>نظر اولیه بر اساس تصویر و اطلاعات ارسالی کمک می‌کند مسیر مناسب‌تر مشخص شود. تصمیم نهایی درمان پس از بررسی بالینی توسط دندانپزشک انجام می‌شود.</p></div>
          <a class="button accepted-secondary" href="#/request/service">ثبت درخواست بررسی</a>
        </div>
      </section>
    </main>
    <footer class="accepted-footer" id="contact">
      <div class="container"><span>© ۱۴۰۵ رویا درمان</span><span>پیش‌نمایش طراحی؛ ثبت درخواست واقعی پس از اتصال سامانه فعال می‌شود.</span></div>
    </footer>
  `);
  bindLandingAnchors();
}

function bindLandingAnchors() {
  if (location.hash && !location.hash.startsWith("#/")) {
    requestAnimationFrame(() => document.querySelector(location.hash)?.scrollIntoView());
  }
}

function progress(step) {
  if (step === "receipt") return "";
  const all = steps();
  const active = Math.max(0, all.indexOf(step));
  return `
    <nav class="request-progress" aria-label="مراحل ثبت درخواست">
      ${all.map((item, index) => `
        <span class="progress-step ${index < active ? "is-done" : index === active ? "is-current" : ""}" ${index === active ? 'aria-current="step"' : ""}>
          <i>${index < active ? icon("check", "icon-xs") : index + 1}</i>
          <b>${stepMeta[item][0]}</b>
        </span>
      `).join("")}
    </nav>
  `;
}

function fieldError(name) {
  return state.errors[name] ? `<p class="field-error" id="${name}-error">${icon("alert", "icon-sm")} ${esc(state.errors[name])}</p>` : "";
}

function invalid(name) {
  return state.errors[name] ? `aria-invalid="true" aria-describedby="${name}-error"` : "";
}

function serviceStep() {
  return `
    <form class="request-form" data-step-form="service" novalidate>
      <fieldset class="field-group" id="service">
        <legend>کدام مسیر به نیاز امروز شما نزدیک‌تر است؟</legend>
        <div class="service-choice-grid">
          ${Object.entries(serviceMeta).map(([key, item]) => `
            <label class="service-choice ${state.service === key ? "is-selected" : ""}">
              <input type="radio" name="service" value="${key}" ${state.service === key ? "checked" : ""}>
              <span class="choice-check">${icon("check", "icon-sm")}</span>
              <span class="service-icon">${icon(item.icon)}</span>
              <strong>${item.title}</strong>
              <small>${item.description}</small>
            </label>
          `).join("")}
        </div>
        ${fieldError("service")}
      </fieldset>
      <div class="step-actions"><a class="button button-quiet" href="#/home">انصراف</a><button class="button button-primary" type="submit">ادامه ${icon("arrow", "icon-sm")}</button></div>
    </form>
  `;
}

function contactStep() {
  const needsArea = state.service === "home";
  return `
    <form class="request-form" data-step-form="contact" novalidate>
      <div class="form-grid">
        <div class="field"><label for="name">نام و نام خانوادگی <span>اختیاری</span></label><input class="input" id="name" name="name" data-field="name" value="${esc(state.name)}" autocomplete="name" maxlength="80" placeholder="مثلاً سارا احمدی" ${invalid("name")}>${fieldError("name")}</div>
        <div class="field"><label for="mobile">شماره موبایل <em>ضروری</em></label><input class="input" id="mobile" name="mobile" data-field="mobile" value="${esc(state.mobile)}" autocomplete="tel" inputmode="tel" maxlength="14" placeholder="۰۹۱۲ ۰۰۰ ۰۰۰۰" ${invalid("mobile")}><p class="field-hint">برای تماس و ارسال کد پیگیری استفاده می‌شود.</p>${fieldError("mobile")}</div>
        ${needsArea ? `<div class="field full"><label for="area">محدوده در تهران <em>ضروری</em></label><select class="select" id="area" name="area" data-field="area" ${invalid("area")}><option value="">انتخاب کنید</option>${["شمال تهران", "مرکز تهران", "شرق تهران", "غرب تهران", "جنوب تهران"].map((item) => `<option ${state.area === item ? "selected" : ""}>${item}</option>`).join("")}</select><p class="field-hint">پوشش دقیق پس از بررسی نشانی و نوع خدمت تأیید می‌شود.</p>${fieldError("area")}</div>` : ""}
        <div class="field"><label for="contactTime">زمان ترجیحی تماس <span>اختیاری</span></label><select class="select" id="contactTime" name="contactTime" data-field="contactTime"><option value="">هر زمان ممکن بود</option>${["صبح ۸ تا ۱۲", "ظهر ۱۲ تا ۱۶", "عصر ۱۶ تا ۲۰", "شب ۲۰ تا ۲۴"].map((item) => `<option ${state.contactTime === item ? "selected" : ""}>${item}</option>`).join("")}</select></div>
        <div class="field full"><label for="reason">چطور می‌توانیم کمک کنیم؟ <span>اختیاری</span></label><textarea class="textarea" id="reason" name="reason" data-field="reason" maxlength="500" placeholder="نیاز یا پرسش خود را کوتاه بنویسید…" ${invalid("reason")}>${esc(state.reason)}</textarea><p class="field-hint"><span data-character-count>${state.reason.length.toLocaleString("fa-IR")}</span> از ۵۰۰ نویسه؛ از نوشتن اطلاعات غیرضروری خودداری کنید.</p>${fieldError("reason")}</div>
      </div>
      ${needsArea ? `<div class="inline-notice">${icon("location")}<div><strong>خدمت در منزل فعلاً فقط در تهران است</strong><p>ثبت این درخواست به معنی تأیید پوشش، نوع خدمت یا زمان مراجعه نیست.</p></div></div>` : ""}
      <div class="step-actions"><button class="button button-quiet" type="button" data-prev>مرحله قبل</button><button class="button button-primary" type="submit">ادامه ${icon("arrow", "icon-sm")}</button></div>
    </form>
  `;
}

function fileStatus() {
  if (state.fileState === "rejected") return `<div class="upload-result is-error">${icon("alert")}<span><strong>این فایل قابل استفاده نیست</strong><small>${esc(state.fileError)}</small></span><button type="button" data-remove-file>انتخاب دوباره</button></div>`;
  if (state.fileState === "checking") return `<div class="upload-result">${icon("clock")}<span><strong>در حال بررسی فایل…</strong><small>نوع واقعی و اندازه فایل در مرورگر کنترل می‌شود.</small></span></div>`;
  if (state.fileState === "ready" && state.file) return `<div class="upload-result is-ready">${icon("check")}<span><strong>${esc(state.file.name)}</strong><small>${humanSize(state.file.size)} · آماده برای پیش‌نمایش؛ هنوز ارسال نشده</small></span><button type="button" data-remove-file aria-label="حذف فایل ${esc(state.file.name)}">حذف</button></div>`;
  return "";
}

function documentStep() {
  return `
    <form class="request-form" data-step-form="document" novalidate>
      <div class="upload-shell" id="file">
        <label class="upload-zone ${state.fileState === "ready" ? "has-file" : ""}" for="opg-file" data-drop-zone>
          <input id="opg-file" type="file" name="opg" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf">
          <span class="upload-icon">${icon("upload")}</span>
          <strong>تصویر OPG را انتخاب یا اینجا رها کنید</strong>
          <small>JPEG، PNG یا PDF · حداکثر ۱۵ مگابایت</small>
          <span class="button button-secondary">انتخاب فایل</span>
        </label>
        <div class="upload-live" aria-live="polite">${fileStatus()}</div>
        ${fieldError("file")}
      </div>
      <div class="security-explainer">
        <span>${icon("lock")}</span><div><strong>در نسخه نهایی چه اتفاقی می‌افتد؟</strong><p>فایل در فضای خصوصی و قرنطینه دریافت می‌شود، کنترل امنیتی را می‌گذراند و فقط برای دندانپزشک دارای مجوزِ تعیین‌شده قابل مشاهده خواهد بود. این پیش‌نمایش هیچ فایلی را به سرور نمی‌فرستد.</p></div>
      </div>
      <div class="step-actions"><button class="button button-quiet" type="button" data-prev>مرحله قبل</button><button class="button button-primary" type="submit">ادامه ${icon("arrow", "icon-sm")}</button></div>
    </form>
  `;
}

function preferencesStep() {
  return `
    <form class="request-form" data-step-form="preferences" novalidate>
      <fieldset class="field-group" id="budget">
        <legend>درباره بودجه کدام گزینه به شرایط شما نزدیک‌تر است؟</legend>
        <p class="legend-help">این پاسخ برای مرتب‌کردن گزینه‌های عملی استفاده می‌شود و هیچ قیمت یا نتیجه درمانی را تضمین نمی‌کند.</p>
        <div class="budget-list">
          ${Object.entries(budgetMeta).map(([key, label]) => `<label class="budget-choice ${state.budget === key ? "is-selected" : ""}"><input type="radio" name="budget" value="${key}" ${state.budget === key ? "checked" : ""}><span>${icon(key === "call" ? "phone" : "wallet")}</span><strong>${label}</strong><i>${icon("check", "icon-sm")}</i></label>`).join("")}
        </div>
        ${fieldError("budget")}
      </fieldset>
      <div class="clinical-limit compact"><strong>هزینه و سلامت، هر دو شفاف</strong><p>گزینه کم‌هزینه فقط زمانی معرفی می‌شود که دندانپزشک یا مرکز درمانی آن را برای وضعیت شما مناسب بداند.</p></div>
      <div class="step-actions"><button class="button button-quiet" type="button" data-prev>مرحله قبل</button><button class="button button-primary" type="submit">بازبینی درخواست ${icon("arrow", "icon-sm")}</button></div>
    </form>
  `;
}

function reviewStep() {
  const service = serviceMeta[state.service];
  return `
    <form class="request-form" data-step-form="review" novalidate>
      <div class="review-list">
        <div><span>${icon(service.icon)}</span><small>نوع درخواست</small><strong>${service.title}</strong><button type="button" data-edit-step="service">ویرایش</button></div>
        <div><span>${icon("phone")}</span><small>راه تماس</small><strong dir="ltr">${esc(state.mobile || "—")}</strong><button type="button" data-edit-step="contact">ویرایش</button></div>
        ${state.service === "home" ? `<div><span>${icon("location")}</span><small>محدوده</small><strong>${esc(state.area)}</strong><a href="#/request/contact">ویرایش</a></div>` : ""}
        ${state.service === "opg" ? `<div><span>${icon("image")}</span><small>فایل OPG</small><strong>${esc(state.file?.name || "—")}</strong><a href="#/request/document">ویرایش</a></div>` : ""}
        <div><span>${icon("wallet")}</span><small>ترجیح بودجه</small><strong>${budgetMeta[state.budget] || "—"}</strong><button type="button" data-edit-step="preferences">ویرایش</button></div>
      </div>
      <label class="consent-box ${state.errors.consent ? "has-error" : ""}" id="consent">
        <input type="checkbox" name="consent" ${state.consent ? "checked" : ""} ${invalid("consent")}>
        <span><strong>با استفاده از اطلاعاتم برای پیگیری همین درخواست موافقم.</strong><small>${state.service === "opg" ? "در نسخه نهایی، اشتراک تصویر فقط با دندانپزشک دارای مجوز و تعیین‌شده برای همین بررسی انجام می‌شود. متن کامل و نسخه سیاست پیش از راه‌اندازی باید تأیید شود." : "اطلاعات فقط برای تماس، هماهنگی و معرفی مرتبط با همین درخواست استفاده می‌شود. متن کامل و نسخه سیاست پیش از راه‌اندازی باید تأیید شود."}</small></span>
      </label>
      ${fieldError("consent")}
      <div class="clinical-limit"><strong>پیش از ثبت بدانید</strong><p>رویا درمان هماهنگ‌کننده و پشتیبان مسیر است. بررسی تصویر و پیشنهاد اولیه جایگزین معاینه حضوری، تشخیص قطعی یا طرح درمان نهایی نیست.</p></div>
      <div class="prototype-warning">${icon("alert")}<div><strong>این فرم هنوز پیش‌نمایش است</strong><p>با زدن دکمه، یک رسید نمایشی می‌بینید؛ اطلاعات و فایل شما به هیچ سروری ارسال نمی‌شود.</p></div></div>
      <div class="step-actions"><button class="button button-quiet" type="button" data-prev>مرحله قبل</button><button class="button button-primary" type="submit">نمایش رسید آزمایشی ${icon("arrow", "icon-sm")}</button></div>
    </form>
  `;
}

function receiptStep() {
  return `
    <div class="receipt-card" role="status" aria-live="polite">
      <span class="receipt-icon">${icon("check")}</span>
      <span class="prototype-pill">رسید آزمایشی</span>
      <h2>مسیر درخواست با موفقیت پیش‌نمایش شد</h2>
      <p>هیچ اطلاعات یا فایلی ارسال و ذخیره نشد. پس از اتصال سامانه امن، این صفحه کد واقعی پیگیری و زمان مورد انتظار تماس را نمایش خواهد داد.</p>
      <div class="reference-row"><span><small>کد نمونه</small><strong dir="ltr">RD-DEMO-1405</strong></span><button class="icon-button" type="button" data-copy-reference aria-label="کپی کد نمونه">${icon("copy")}</button></div>
      <div class="next-card"><strong>در نسخه نهایی، قدم بعدی چیست؟</strong><ol><li>درخواست به صف پشتیبانی می‌رود.</li><li>کارشناس طبق زمان ترجیحی شما تماس می‌گیرد.</li><li>بررسی یا معرفی توسط فرد مجاز انجام و نتیجه قابل پیگیری می‌شود.</li></ol></div>
      <div class="clinical-limit"><strong>یادآوری</strong><p>هر نظر درباره OPG اولیه است و تصمیم نهایی درمان پس از معاینه مناسب توسط دندانپزشک دارای مجوز گرفته می‌شود.</p></div>
      <div class="receipt-actions"><a class="button button-primary" href="#/home">بازگشت به صفحه اصلی</a><button class="button button-secondary" type="button" data-restart>شروع پیش‌نمایش جدید</button></div>
    </div>
  `;
}

function requestBody(step) {
  if (step === "service") return serviceStep();
  if (step === "contact") return contactStep();
  if (step === "document" && state.service === "opg") return documentStep();
  if (step === "preferences") return preferencesStep();
  if (step === "review") return reviewStep();
  if (step === "receipt") return receiptStep();
  return serviceStep();
}

function requestAside(step) {
  const service = state.service ? serviceMeta[state.service] : null;
  return `
    <aside class="request-aside">
      <span class="aside-mark">ر</span>
      <div><span class="section-kicker">پیش‌نمایش امن جریان</span><h2>${service ? service.short : "درخواست راهنمایی"}</h2><p>این نسخه برای تأیید تجربه و متن محصول است. پیش از راه‌اندازی واقعی، ورود امن، رضایت‌نامه نهایی، ذخیره خصوصی و کنترل فایل فعال می‌شوند.</p></div>
      <ul>
        <li>${icon("clock", "icon-sm")} پیگیری درخواست در تمام ساعات</li>
        <li>${icon("user", "icon-sm")} هماهنگی انسانی، نه تشخیص خودکار</li>
        <li>${icon("shield", "icon-sm")} دسترسی بالینی فقط برای فرد مجاز</li>
      </ul>
      ${step !== "service" && service ? `<div class="aside-service"><span>${icon(service.icon)}</span><small>مسیر انتخاب‌شده</small><strong>${service.title}</strong></div>` : ""}
    </aside>
  `;
}

function renderRequest(step) {
  if (step !== "service" && !state.service) {
    goToStep("service");
    return;
  }
  if (step === "document" && state.service !== "opg") {
    goToStep("preferences");
    return;
  }
  const [title, lede] = stepMeta[step] || stepMeta.service;
  document.body.className = "page-request";
  document.title = `${title} | رویا درمان`;
  app.innerHTML = shell(`
    <main class="request-page" id="main-content" tabindex="-1">
      <div class="container request-container">
        ${progress(step)}
        <div class="request-layout">
          <section class="request-panel">
            <header class="request-head"><span class="section-kicker">${step === "receipt" ? "پایان پیش‌نمایش" : `مرحله ${(steps().indexOf(step) + 1).toLocaleString("fa-IR")} از ${steps().length.toLocaleString("fa-IR")}`}</span><h1>${title}</h1><p>${lede}</p></header>
            <div id="error-summary" class="error-summary" tabindex="-1" hidden></div>
            ${requestBody(step)}
          </section>
          ${requestAside(step)}
        </div>
      </div>
    </main>
  `, true);
  document.querySelector("#main-content")?.focus({ preventScroll: true });
}

function render() {
  const current = route();
  if (current.mode === "home") renderHome();
  else renderRequest(current.step);
  window.scrollTo({ top: 0, behavior: "auto" });
}

function normaliseDigits(value) {
  return String(value)
    .replace(/[۰-۹]/g, (digit) => "۰۱۲۳۴۵۶۷۸۹".indexOf(digit))
    .replace(/[٠-٩]/g, (digit) => "٠١٢٣٤٥٦٧٨٩".indexOf(digit));
}

function normaliseMobile(value) {
  return normaliseDigits(value).replace(/[^0-9+]/g, "").replace(/^\+98/, "0").replace(/^0098/, "0");
}

function validate(step) {
  const errors = {};
  if (step === "service" && !state.service) errors.service = "یکی از مسیرهای خدمت را انتخاب کنید.";
  if (step === "contact") {
    const mobile = normaliseMobile(state.mobile);
    if (!/^09\d{9}$/.test(mobile)) errors.mobile = "شماره موبایل معتبر ۱۱ رقمی وارد کنید.";
    if (state.name.length > 80) errors.name = "نام نمی‌تواند بیش از ۸۰ نویسه باشد.";
    if (state.reason.length > 500) errors.reason = "توضیح نمی‌تواند بیش از ۵۰۰ نویسه باشد.";
    if (state.service === "home" && !state.area) errors.area = "برای بررسی پوشش، محدوده تهران را انتخاب کنید.";
  }
  if (step === "document" && state.fileState !== "ready") errors.file = "یک فایل OPG معتبر انتخاب کنید.";
  if (step === "preferences" && !state.budget) errors.budget = "یک گزینه بودجه یا گفت‌وگوی تلفنی را انتخاب کنید.";
  if (step === "review" && !state.consent) errors.consent = "برای پیش‌نمایش ثبت، این رضایت مشخص را تأیید کنید.";
  state.errors = errors;
  return Object.keys(errors).length === 0;
}

function showErrorSummary() {
  const summary = document.querySelector("#error-summary");
  if (!summary || !Object.keys(state.errors).length) return;
  summary.hidden = false;
  summary.innerHTML = `<strong>${icon("alert", "icon-sm")} لطفاً موارد زیر را اصلاح کنید:</strong><ul>${Object.entries(state.errors).map(([name, message]) => `<li><a href="#${name}">${esc(message)}</a></li>`).join("")}</ul>`;
  summary.focus();
}

function nextStep(step) {
  const all = steps();
  const index = all.indexOf(step);
  return index >= 0 && index < all.length - 1 ? all[index + 1] : "receipt";
}

function previousStep(step) {
  const all = steps();
  const index = all.indexOf(step);
  return index > 0 ? all[index - 1] : "service";
}

function resetState() {
  Object.assign(state, { service: "", name: "", mobile: "", area: "", contactTime: "", reason: "", budget: "", file: null, fileState: "empty", fileError: "", consent: false, errors: {}, submitted: false });
}

function humanSize(bytes) {
  if (bytes < 1024) return `${bytes.toLocaleString("fa-IR")} بایت`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toLocaleString("fa-IR", { maximumFractionDigits: 1 })} کیلوبایت`;
  return `${(bytes / (1024 * 1024)).toLocaleString("fa-IR", { maximumFractionDigits: 1 })} مگابایت`;
}

async function inspectFile(file) {
  state.file = file;
  state.fileState = "checking";
  state.fileError = "";
  state.errors = {};
  renderRequest("document");
  if (!file) {
    state.fileState = "empty";
    renderRequest("document");
    return;
  }
  const max = 15 * 1024 * 1024;
  if (file.size > max) {
    state.fileState = "rejected";
    state.fileError = "اندازه فایل باید حداکثر ۱۵ مگابایت باشد.";
    renderRequest("document");
    return;
  }
  const ext = file.name.split(".").pop().toLowerCase();
  if (!["jpg", "jpeg", "png", "pdf"].includes(ext)) {
    state.fileState = "rejected";
    state.fileError = "فقط فایل JPEG، PNG یا PDF پذیرفته می‌شود.";
    renderRequest("document");
    return;
  }
  try {
    const bytes = new Uint8Array(await file.slice(0, 8).arrayBuffer());
    const isJpeg = bytes[0] === 0xff && bytes[1] === 0xd8 && bytes[2] === 0xff;
    const isPng = bytes[0] === 0x89 && bytes[1] === 0x50 && bytes[2] === 0x4e && bytes[3] === 0x47;
    const isPdf = String.fromCharCode(...bytes.slice(0, 5)) === "%PDF-";
    const matches = (["jpg", "jpeg"].includes(ext) && isJpeg) || (ext === "png" && isPng) || (ext === "pdf" && isPdf);
    if (!matches) throw new Error("signature");
    state.fileState = "ready";
  } catch {
    state.fileState = "rejected";
    state.fileError = "محتوای فایل با پسوند آن هم‌خوانی ندارد.";
  }
  renderRequest("document");
}

function toast(title, message) {
  const node = document.createElement("div");
  node.className = "toast";
  node.innerHTML = `${icon("check")}<div><strong>${esc(title)}</strong><p>${esc(message)}</p></div>`;
  toastRegion.append(node);
  window.setTimeout(() => node.remove(), 3400);
}

document.addEventListener("submit", (event) => {
  const form = event.target.closest("[data-step-form]");
  if (!form) return;
  event.preventDefault();
  const step = form.dataset.stepForm;
  if (!validate(step)) {
    renderRequest(step);
    requestAnimationFrame(showErrorSummary);
    return;
  }
  state.errors = {};
  if (step === "review") state.submitted = true;
  goToStep(nextStep(step));
});

document.addEventListener("input", (event) => {
  const field = event.target.closest("[data-field]");
  if (!field) return;
  state[field.dataset.field] = field.value;
  delete state.errors[field.dataset.field];
  if (field.dataset.field === "reason") document.querySelector("[data-character-count]").textContent = field.value.length.toLocaleString("fa-IR");
});

document.addEventListener("change", (event) => {
  const target = event.target;
  if (target.name === "service") {
    state.service = target.value;
    state.file = null;
    state.fileState = "empty";
    state.area = "";
    delete state.errors.service;
    renderRequest("service");
  }
  if (target.name === "budget") {
    state.budget = target.value;
    delete state.errors.budget;
    renderRequest("preferences");
  }
  if (target.name === "consent") {
    state.consent = target.checked;
    delete state.errors.consent;
    renderRequest("review");
  }
  if (target.id === "opg-file") inspectFile(target.files?.[0]);
  if (target.matches("select[data-field]")) state[target.dataset.field] = target.value;
});

document.addEventListener("click", async (event) => {
  const start = event.target.closest("[data-start-service]");
  if (start) {
    state.service = start.dataset.startService;
    state.errors = {};
    goToStep("contact");
    return;
  }
  if (event.target.closest("[data-prev]")) {
    goToStep(previousStep(route().step));
    return;
  }
  const edit = event.target.closest("[data-edit-step]");
  if (edit) {
    goToStep(edit.dataset.editStep);
    return;
  }
  if (event.target.closest("[data-remove-file]")) {
    state.file = null;
    state.fileState = "empty";
    state.fileError = "";
    renderRequest("document");
    return;
  }
  if (event.target.closest("[data-restart]")) {
    resetState();
    goToStep("service");
    return;
  }
  if (event.target.closest("[data-copy-reference]")) {
    await navigator.clipboard?.writeText("RD-DEMO-1405");
    toast("کد نمونه کپی شد", "این کد صرفاً برای پیش‌نمایش است.");
  }
});

document.addEventListener("dragover", (event) => {
  const zone = event.target.closest("[data-drop-zone]");
  if (!zone) return;
  event.preventDefault();
  zone.classList.add("is-dragging");
});

document.addEventListener("dragleave", (event) => {
  event.target.closest("[data-drop-zone]")?.classList.remove("is-dragging");
});

document.addEventListener("drop", (event) => {
  const zone = event.target.closest("[data-drop-zone]");
  if (!zone) return;
  event.preventDefault();
  zone.classList.remove("is-dragging");
  inspectFile(event.dataTransfer?.files?.[0]);
});

window.addEventListener("hashchange", render);
render();
