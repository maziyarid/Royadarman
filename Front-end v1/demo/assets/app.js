import { patientPages, clinicPages, opsPages } from "./screens.js";

const app = document.querySelector("#app");
const toastRegion = document.querySelector("#toast-region");

const ICONS = {
  home: '<path d="M3 10.8 12 3l9 7.8v9a1.2 1.2 0 0 1-1.2 1.2H4.2A1.2 1.2 0 0 1 3 19.8z"/><path d="M9 21v-7h6v7"/>',
  calendar: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
  search: '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
  user: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
  users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
  heart: '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8z"/>',
  building: '<path d="M4 21V5a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v16M9 21v-4h4v4M8 7h1M12 7h1M8 11h1M12 11h1M17 9h2a1 1 0 0 1 1 1v11"/>',
  shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/>',
  logout: '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>',
  bell: '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
  menu: '<path d="M4 6h16M4 12h16M4 18h16"/>',
  x: '<path d="M18 6 6 18M6 6l12 12"/>',
  chevron: '<path d="m9 18 6-6-6-6"/>',
  check: '<path d="m20 6-11 11-5-5"/>',
  alert: '<path d="M10.3 3.7 2.2 18a2 2 0 0 0 1.7 3h16.2a2 2 0 0 0 1.7-3L13.7 3.7a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
  clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  location: '<path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="2.5"/>',
  card: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h2"/>',
  support: '<circle cx="12" cy="12" r="9"/><path d="M8.5 9a3.5 3.5 0 1 1 5.4 2.9c-1.3.8-1.9 1.3-1.9 2.6M12 18h.01"/>',
  settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.6v-.2h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1z"/>',
  briefcase: '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18M10 12v2h4v-2"/>',
  file: '<path d="M6 2h8l4 4v16H6z"/><path d="M14 2v5h5M9 13h6M9 17h6"/>',
  chart: '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
  wallet: '<path d="M4 5h14a2 2 0 0 1 2 2v12H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z"/><path d="M16 11h6v4h-6a2 2 0 0 1 0-4z"/>',
  message: '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/><path d="M8 9h8M8 13h5"/>',
  lock: '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
  star: '<path d="m12 2.5 3 6 6.5.9-4.7 4.6 1.1 6.5-5.9-3.1-5.9 3.1 1.1-6.5-4.7-4.6 6.5-.9z"/>',
  filter: '<path d="M4 5h16M7 12h10M10 19h4"/>',
  refresh: '<path d="M20 7v5h-5M4 17v-5h5"/><path d="M18.4 9A7 7 0 0 0 6 6.5L4 9M5.6 15A7 7 0 0 0 18 17.5l2-2.5"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  phone: '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.8a2 2 0 0 1-.4 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
  route: '<circle cx="6" cy="19" r="2"/><circle cx="18" cy="5" r="2"/><path d="M8 19h3a3 3 0 0 0 3-3v-5a3 3 0 0 1 3-3h1"/>',
  layers: '<path d="m12 2 9 5-9 5-9-5z"/><path d="m3 12 9 5 9-5M3 17l9 5 9-5"/>',
  eye: '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"/><circle cx="12" cy="12" r="2.5"/>',
  upload: '<path d="M12 16V4M7 9l5-5 5 5M4 20h16"/>',
};

function icon(name, extra = "") {
  return `<svg class="icon ${extra}" viewBox="0 0 24 24" aria-hidden="true">${ICONS[name] || ICONS.file}</svg>`;
}

function esc(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

const translations = [
  ["داده نمایشی", ""], ["نمونه نمایشی", "۵۸۰٬۰۰۰ تومان"], ["نمونه", "—"],
  ["provider", "سرویس‌دهنده"], ["Provider", "سرویس‌دهنده"], ["matching", "تطبیق"],
  ["server", "سامانه"], ["review", "بررسی"], ["checkout", "پرداخت"],
  ["Hold", "رزرو موقت"], ["refund", "بازپرداخت"], ["Refund", "بازپرداخت"],
  ["settlement", "تسویه"], ["Settlement", "تسویه"], ["audit", "حسابرسی"],
  ["step-up", "تأیید مجدد"], ["OTP", "کد یک‌بارمصرف"], ["MFA", "تأیید دومرحله‌ای"],
  ["Batch", "دسته تسویه"], ["batch", "دسته تسویه"], ["notification", "اعلان"],
  ["callback", "بازگشت سامانه"], ["redirect", "بازگشت پرداخت"], ["webhook", "اعلان درگاه"],
  ["query", "استعلام"], ["payout", "واریز"], ["chargeback", "اعتراض بانکی"],
  ["hard filter", "شرط قطعی"], ["fallback", "مسیر جایگزین"], ["workflow", "فرایند"],
  ["history", "تاریخچه"], ["export", "خروجی"], ["retry", "تلاش دوباره"],
];

function fa(value) {
  let result = String(value ?? "");
  for (const [from, to] of translations) result = result.replaceAll(from, to);
  return result.replace(/\s+·\s*$/g, "").replace(/\s{2,}/g, " ").trim();
}

function keyFromSlug(slug) {
  return slug.split("/").pop().replace(".html", "").replace(/^\d+-/, "").replace("review-visit", "visit-feedback").replace("clinic-review", "clinic-assessment");
}

function preparePages(source, role) {
  return source.map((page, index) => ({
    ...page,
    role,
    key: keyFromSlug(page.slug),
    index,
    title: fa(page.title),
    lede: fa(page.lede),
    purpose: fa(page.purpose),
    primary: fa(page.primary),
    fields: (page.fields || []).map(fa),
    options: (page.options || []).map(fa),
    actions: (page.actions || []).map(fa),
    cards: (page.cards || []).map(fa),
    metrics: (page.metrics || []).map(([label, value]) => [fa(label), fa(value)]),
    states: (page.states || []).map(fa),
    rules: (page.rules || []).map(fa),
    empty: fa(page.empty),
    error: fa(page.error),
    kicker: fa(page.kicker),
    notice: fa(page.notice),
  }));
}

const pagesByRole = {
  patient: preparePages(patientPages, "patient"),
  clinic: preparePages(clinicPages, "clinic"),
  admin: preparePages(opsPages, "admin"),
};

const ROLE_META = {
  patient: {
    label: "اپ بیمار",
    shortLabel: "بیمار",
    description: "درخواست، یافتن مرکز، رزرو و پیگیری نوبت",
    profile: "سارا احمدی",
    profileMeta: "حساب دمو بیمار",
    initials: "س‌ا",
    icon: "heart",
    default: "home",
    count: pagesByRole.patient.length,
  },
  clinic: {
    label: "پورتال مرکز درمانی",
    shortLabel: "مرکز درمانی",
    description: "درخواست‌ها، نوبت‌ها، ظرفیت و تسویه",
    profile: "کلینیک دندان‌پزشکی آبان",
    profileMeta: "شعبه ونک · حساب دمو",
    initials: "آ",
    icon: "building",
    default: "dashboard",
    count: pagesByRole.clinic.length,
  },
  admin: {
    label: "مدیریت و عملیات",
    shortLabel: "مدیریت",
    description: "شبکه مراکز، استثناها، مالی و حسابرسی",
    profile: "مدیر عملیات دمو",
    profileMeta: "دسترسی کامل نمایشی",
    initials: "م",
    icon: "shield",
    default: "command-center",
    count: pagesByRole.admin.length,
  },
};

const NAV_SECTIONS = {
  patient: [
    ["شروع", ["home", "login"]],
    ["درخواست و رزرو", ["need", "urgency", "emergency", "location", "time-preferences", "matching", "offers", "clinic-detail", "reservation", "checkout", "payment-processing", "receipt"]],
    ["حساب و پشتیبانی", ["appointments", "appointment-detail", "cancel-reschedule", "support", "profile-consents", "visit-feedback"]],
  ],
  clinic: [
    ["نمای کلی", ["dashboard", "login-mfa"]],
    ["مرکز و تیم", ["onboarding", "branch-profile", "team-roles", "services"]],
    ["عملیات روزانه", ["availability", "incoming-requests", "request-detail", "appointments", "checkin", "completion"]],
    ["مالی و کیفیت", ["settlements", "quality-support"]],
  ],
  admin: [
    ["مرکز فرمان", ["command-center", "login"]],
    ["شبکه و تقاضا", ["clinic-verification", "clinic-assessment", "unmatched", "sla"]],
    ["رزرو و مالی", ["booking-exceptions", "payment-exceptions", "refund-approval", "disputes", "settlements", "reconciliation"]],
    ["پیکربندی و کنترل", ["matching-config", "taxonomy-areas", "audit-access", "communications"]],
  ],
};

const ICON_BY_KEY = {
  login: "lock", "login-mfa": "lock", home: "home", dashboard: "home", "command-center": "home",
  need: "heart", urgency: "alert", emergency: "phone", location: "location", "time-preferences": "calendar",
  matching: "search", offers: "building", "clinic-detail": "building", reservation: "calendar", checkout: "card",
  "payment-processing": "refresh", receipt: "file", appointments: "calendar", "appointment-detail": "calendar",
  "cancel-reschedule": "refresh", support: "support", "profile-consents": "user", "visit-feedback": "star",
  onboarding: "file", "branch-profile": "building", "team-roles": "users", services: "briefcase", availability: "calendar",
  "incoming-requests": "message", "request-detail": "file", checkin: "check", completion: "check", settlements: "wallet",
  "quality-support": "support", "clinic-verification": "shield", "clinic-assessment": "file", unmatched: "search", sla: "clock",
  "booking-exceptions": "alert", "payment-exceptions": "card", "refund-approval": "wallet", disputes: "message",
  reconciliation: "chart", "matching-config": "settings", "taxonomy-areas": "layers", "audit-access": "eye", communications: "message",
};

const STATE_LABELS = {
  active: "فعال", pending: "در انتظار", approved: "تأییدشده", rejected: "ردشده", draft: "پیش‌نویس",
  submitted: "ارسال‌شده", completed: "تکمیل‌شده", failed: "ناموفق", processing: "در حال پردازش",
  confirmed: "تأییدشده", expired: "منقضی", open: "باز", resolved: "حل‌شده", locked: "قفل‌شده",
  warning: "نیازمند توجه", healthy: "سالم", matched: "تطبیق‌شده", mismatch: "دارای اختلاف",
};

function stateLabel(state) {
  const key = String(state).toLowerCase().replaceAll(" ", "_");
  return STATE_LABELS[key] || state.replaceAll("_", " ");
}

function routeFor(role, key) {
  return `#/${role}/${key}`;
}

function currentRoute() {
  const parts = location.hash.replace(/^#\/?/, "").split("/").filter(Boolean);
  if (!parts.length || parts[0] === "access") return { mode: "access" };
  const role = ROLE_META[parts[0]] ? parts[0] : null;
  return role ? { mode: "app", role, key: parts[1] || ROLE_META[role].default } : { mode: "access" };
}

function pageFor(role, key) {
  return pagesByRole[role].find((page) => page.key === key) || pagesByRole[role].find((page) => page.key === ROLE_META[role].default) || pagesByRole[role][0];
}

function metricValue(value, index, role) {
  if (value && value !== "—") return value;
  const pools = {
    patient: ["۱", "۲", "۴٫۸"],
    clinic: ["۲۴", "۵", "۹۸٪"],
    admin: ["۱٬۲۴۸", "۱۲", "۹۹٫۸٪"],
  };
  return pools[role][index % pools[role].length];
}

function statusTone(index = 0) {
  return ["status-success", "status-warning", "status-info", "status-violet"][index % 4];
}

function showToast(title, message = "این اقدام در محیط دمو با موفقیت شبیه‌سازی شد.") {
  const toast = document.createElement("div");
  toast.className = "toast";
  toast.innerHTML = `${icon("check")}<div><strong>${esc(title)}</strong><p>${esc(message)}</p></div>`;
  toastRegion.append(toast);
  setTimeout(() => toast.remove(), 3600);
}

function renderAccess() {
  document.body.className = "";
  document.title = "ورود به دموی رویادَرمان";
  const roleCards = Object.entries(ROLE_META).map(([role, meta]) => `
    <article class="role-card ${role}">
      <div class="role-icon">${icon(meta.icon)}</div>
      <h3>${esc(meta.label)}</h3>
      <p>${esc(meta.description)}</p>
      <footer>
        <span class="page-count">${meta.count.toLocaleString("fa-IR")} صفحه کامل</span>
        <button class="enter-button" type="button" data-enter-role="${role}">ورود مستقیم ${icon("chevron", "icon-sm")}</button>
      </footer>
    </article>`).join("");

  app.innerHTML = `
    <div class="access-page">
      <header class="access-header">
        <a class="brand-lockup" href="#/access" aria-label="رویادَرمان، صفحه ورود دمو">
          <span class="brand-symbol">ر</span>
          <span class="brand-copy"><strong>رویادَرمان</strong><small>دسترسی نمایشی محصول</small></span>
        </a>
        <span class="demo-badge"><span class="demo-dot"></span> محیط دمو فعال است</span>
      </header>
      <main class="access-main" id="main-content">
        <section class="access-intro" aria-labelledby="access-title">
          <div class="access-copy">
            <span class="eyebrow">تجربه کامل محصول</span>
            <h1 id="access-title">مسیر مراقبت، <span>از درخواست تا مراجعه</span></h1>
            <p>برای مشاهده تمام جریان‌های محصول، یکی از نقش‌ها را انتخاب کنید. ورود مستقیم است و تمام اطلاعات این محیط صرفاً برای نمایش قابلیت‌های رویادَرمان ساخته شده‌اند.</p>
            <ul class="access-facts">
              <li>${icon("check", "icon-sm")} اپ بیمار با ۲۰ صفحه</li>
              <li>${icon("check", "icon-sm")} پورتال مرکز با ۱۴ صفحه</li>
              <li>${icon("check", "icon-sm")} مدیریت با ۱۶ صفحه</li>
            </ul>
          </div>
          <div class="route-visual" aria-hidden="true">
            <div class="route-core"><strong>۵۰</strong><span>صفحه متصل</span></div>
            <div class="route-node node-a">${icon("heart", "icon-sm")} درخواست بیمار</div>
            <div class="route-node node-b">${icon("search", "icon-sm")} یافتن مرکز</div>
            <div class="route-node node-c">${icon("calendar", "icon-sm")} رزرو نوبت</div>
            <div class="route-node node-d">${icon("wallet", "icon-sm")} تسویه شفاف</div>
          </div>
        </section>
        <section class="role-section" aria-labelledby="roles-title">
          <div class="section-heading"><div><h2 id="roles-title">انتخاب نقش دمو</h2><p>در هر لحظه می‌توانید از نوار بالای برنامه نقش را تغییر دهید.</p></div></div>
          <div class="role-grid">${roleCards}</div>
        </section>
      </main>
      <footer class="access-footer"><span>رویادَرمان · دموی نسخه محصول</span><span>طراحی فارسی، راست‌به‌چپ و واکنش‌گرا</span></footer>
    </div>`;
}

function navSections(role, currentKey) {
  return NAV_SECTIONS[role].map(([title, keys]) => {
    const links = keys.map((key) => {
      const page = pageFor(role, key);
      if (!page || page.key !== key) return "";
      return `<a class="nav-link" data-nav-item data-title="${esc(page.title)}" href="${routeFor(role, key)}" ${currentKey === key ? 'aria-current="page"' : ""}>${icon(ICON_BY_KEY[key] || "file")}<span>${esc(page.title)}</span></a>`;
    }).join("");
    return `<section class="nav-group"><div class="nav-group-title">${esc(title)}</div>${links}</section>`;
  }).join("");
}

function mobileNav(role, currentKey) {
  const items = role === "patient"
    ? [["home", "خانه", "home"], ["appointments", "نوبت‌ها", "calendar"], ["support", "پشتیبانی", "support"], ["profile-consents", "حساب", "user"]]
    : role === "clinic"
      ? [["dashboard", "خانه", "home"], ["incoming-requests", "درخواست‌ها", "message"], ["appointments", "نوبت‌ها", "calendar"], ["settlements", "مالی", "wallet"]]
      : [["command-center", "فرمان", "home"], ["clinic-verification", "مراکز", "building"], ["payment-exceptions", "مالی", "wallet"], ["audit-access", "کنترل", "shield"]];
  return `<nav class="mobile-bottom-nav" aria-label="دسترسی سریع">${items.map(([key, label, iconName]) => `<a class="bottom-link" href="${routeFor(role, key)}" ${currentKey === key ? 'aria-current="page"' : ""}>${icon(iconName)}<span>${label}</span></a>`).join("")}</nav>`;
}

function roleSwitcher(currentRole) {
  return `<details class="demo-switcher"><summary>${icon("users", "icon-sm")}<span>تغییر نقش دمو</span>${icon("chevron", "icon-sm")}</summary><div class="switcher-menu"><p>محیط موردنظر را انتخاب کنید</p>${Object.entries(ROLE_META).map(([role, meta]) => `<button class="switcher-option ${role === currentRole ? "is-current" : ""}" type="button" data-switch-role="${role}">${icon(meta.icon)}<span>${esc(meta.shortLabel)}</span></button>`).join("")}<button class="switcher-option" type="button" data-exit-demo>${icon("logout")}<span>بازگشت به ورودی دمو</span></button></div></details>`;
}

function patientSteps(page) {
  if (page.index < 2 || page.index > 13) return "";
  const stages = [
    ["نیاز", [2]], ["ایمنی", [3, 4]], ["مکان و زمان", [5, 6, 7]], ["انتخاب مرکز", [8, 9]], ["رزرو", [10]], ["پرداخت", [11, 12]], ["تأیید", [13]],
  ];
  return `<nav class="steps" aria-label="مراحل درخواست">${stages.map(([label, indexes], stepIndex) => `<span class="step ${page.index > Math.max(...indexes) ? "is-done" : indexes.includes(page.index) ? "is-current" : ""}"><span class="step-index">${stepIndex + 1}</span>${label}</span>`).join("")}</nav>`;
}

function pageHeader(page) {
  return `<header class="page-head"><div class="page-head-copy"><div class="page-kicker">${esc(page.kicker)}</div><h1>${esc(page.title)}</h1><p>${esc(page.lede)}</p></div><div class="page-head-actions"><button class="button button-secondary" type="button" data-toast="صفحه به‌روزرسانی شد">${icon("refresh", "icon-sm")} تازه‌سازی</button></div></header>`;
}

function stateStrip(page) {
  if (!page.states.length) return "";
  return `<div class="state-strip" aria-label="وضعیت‌های قابل نمایش">${page.states.map((state) => `<span class="state-pill">${esc(stateLabel(state))}</span>`).join("")}</div>`;
}

function actionBar(page, extraClass = "") {
  if (!page.actions.length) return "";
  return `<div class="action-bar ${extraClass}">${page.actions.map((action, index) => `<button class="button ${index === 0 ? "button-primary" : "button-secondary"}" type="button" data-page-action="${index}">${index === 0 ? icon("check", "icon-sm") : ""}${esc(action)}</button>`).join("")}</div>`;
}

function renderMetrics(page) {
  if (!page.metrics.length) return "";
  return `<div class="grid grid-${Math.min(page.metrics.length, 4)}">${page.metrics.map(([label, value], index) => `<article class="card metric-card"><div class="metric-top"><span class="metric-label">${esc(label)}</span><span class="status-badge ${statusTone(index)}">لحظه‌ای</span></div><div class="metric-value">${esc(metricValue(value, index, page.role))}</div><div class="metric-foot"><span class="trend-up">↑ ${index + 2}٪</span><span>نسبت به دوره پیش</span></div></article>`).join("")}</div>`;
}

function renderChoices(page) {
  if (!page.options.length) return "";
  const type = page.options.length > 5 ? "checkbox" : "radio";
  return `<fieldset class="field full"><legend>انتخاب گزینه</legend><div class="choice-list">${page.options.map((option, index) => `<label class="choice"><input type="${type}" name="page-choice" value="${index}" aria-label="${esc(option)}" ${(page.key === "urgency" ? index === page.options.length - 1 : index === 0) ? "checked" : ""}><span>${esc(option)}</span></label>`).join("")}</div></fieldset>`;
}

function fieldInput(field, index) {
  const lower = field.toLowerCase();
  const type = lower.includes("موبایل") || lower.includes("تلفن") ? "tel" : lower.includes("ایمیل") ? "email" : lower.includes("تاریخ") || lower.includes("روز") ? "date" : lower.includes("مبلغ") || lower.includes("وزن") ? "number" : "text";
  const demoValue = type === "tel" ? "۰۹۱۲ ۰۰۰ ۰۰۰۱" : type === "email" ? "demo@royadarman.com" : type === "date" ? "2026-09-03" : type === "number" ? "25" : index === 0 ? "اطلاعات دمو" : "";
  if (lower.includes("توضیح") || lower.includes("دلیل")) return `<textarea class="textarea" id="field-${index}" placeholder="توضیحات را وارد کنید">${index === 0 ? "توضیحات کوتاه برای نمایش فرایند" : ""}</textarea>`;
  return `<input class="input" id="field-${index}" type="${type}" value="${esc(demoValue)}" placeholder="${esc(field)}">`;
}

function renderFields(page) {
  if (!page.fields.length) return "";
  return `<div class="form-grid">${page.fields.map((field, index) => `<div class="field ${page.fields.length === 1 ? "full" : ""}"><label for="field-${index}">${esc(field)}</label>${fieldInput(field, index)}<p class="field-hint">این مقدار فقط در محیط دمو استفاده می‌شود.</p></div>`).join("")}</div>`;
}

function cardRows(page) {
  return `<div class="list-stack">${page.cards.map((item, index) => {
    const [title, ...rest] = item.split("·").map((part) => part.trim()).filter(Boolean);
    return `<div class="list-row"><span class="list-icon">${icon(ICON_BY_KEY[page.key] || (index % 2 ? "file" : "calendar"), "icon-sm")}</span><div class="list-copy"><strong>${esc(title || item)}</strong><small>${esc(rest.join(" · ") || ["آماده بررسی", "به‌روزرسانی لحظه‌ای", "اطلاعات کامل"][index % 3])}</small></div><span class="status-badge ${statusTone(index)}">${["فعال", "در انتظار", "تأییدشده", "نیازمند اقدام"][index % 4]}</span>${icon("chevron", "chevron icon-sm")}</div>`;
  }).join("")}</div>`;
}

function genericScreen(page) {
  const hasInput = page.fields.length || page.options.length;
  const main = hasInput ? `
    <section class="card card-elevated"><div class="card-head"><div><h2>${esc(page.purpose)}</h2><p>اطلاعات لازم را تکمیل کنید.</p></div><span class="status-badge status-info">دمو</span></div><form data-demo-form>${renderFields(page)}${renderChoices(page)}${actionBar(page)}</form></section>` : `
    <section class="card card-elevated"><div class="card-head"><div><h2>موارد قابل اقدام</h2><p>اطلاعات بر اساس اولویت عملیاتی مرتب شده‌اند.</p></div><button class="icon-button" type="button" aria-label="فیلتر" data-toast="فیلترها آماده‌اند">${icon("filter")}</button></div>${page.cards.length ? cardRows(page) : emptyState(page)}${actionBar(page)}</section>`;
  const side = `<aside class="card"><div class="card-head"><div><h2>وضعیت این بخش</h2><p>نمای لحظه‌ای فرایند</p></div></div>${stateStrip(page)}<div class="demo-note">${icon("support", "icon-sm")} برای نمایش حالت خطا یا خالی، از فهرست وضعیت‌ها استفاده کنید.</div></aside>`;
  return `${renderMetrics(page)}<div class="grid grid-main" style="margin-top:1rem">${main}${side}</div>`;
}

function emptyState(page) {
  return `<div class="empty-state"><span class="empty-icon">${icon("file")}</span><h2>موردی برای نمایش نیست</h2><p>${esc(page.empty || "در حال حاضر مورد بازی وجود ندارد.")}</p><button class="button button-secondary" type="button" data-toast="فهرست بررسی شد">${icon("refresh", "icon-sm")} بررسی دوباره</button></div>`;
}

function loginScreen(page) {
  const isPatient = page.role === "patient";
  return `<section class="card card-elevated centered-card" style="text-align:right;max-width:620px"><div class="card-head"><div><h2>${isPatient ? "ورود با شماره موبایل" : "ورود امن سازمانی"}</h2><p>برای ادامه می‌توانید از اطلاعات از پیش تکمیل‌شده استفاده کنید.</p></div><span class="status-badge status-success">دسترسی دمو</span></div><form data-demo-form>${renderFields(page)}${isPatient ? `<div class="otp-boxes" aria-label="کد یک‌بارمصرف"><input class="otp-box" inputmode="numeric" maxlength="1" value="1" aria-label="رقم اول"><input class="otp-box" inputmode="numeric" maxlength="1" value="2" aria-label="رقم دوم"><input class="otp-box" inputmode="numeric" maxlength="1" value="3" aria-label="رقم سوم"><input class="otp-box" inputmode="numeric" maxlength="1" value="4" aria-label="رقم چهارم"><input class="otp-box" inputmode="numeric" maxlength="1" value="5" aria-label="رقم پنجم"></div>` : ""}<div class="alert alert-info" style="margin-top:1rem">${icon("lock")}<div><strong>ورود نمایشی و امن</strong><p>هیچ اطلاعات واقعی ذخیره یا ارسال نمی‌شود.</p></div></div><div class="action-bar"><button class="button button-primary" type="button" data-login-demo>${icon("check", "icon-sm")} ورود مستقیم به دمو</button></div></form></section>`;
}

function patientHome(page) {
  return `<div class="grid grid-main"><div class="grid"><section class="card patient-hero"><h2>سلام سارا، برای لبخندت آماده‌ای؟</h2><p>نیازتان را در چند مرحله کوتاه ثبت کنید تا نزدیک‌ترین زمان مناسب را پیدا کنیم.</p><button class="button" type="button" data-go="patient/need">شروع درخواست جدید ${icon("chevron", "icon-sm")}</button></section><section class="card appointment-ticket"><div class="card-head"><div><h2>نوبت نزدیک</h2><p>فردا · کلینیک دندان‌پزشکی آبان</p></div><span class="status-badge status-success">تأییدشده</span></div><div class="list-row" style="border:0;padding:0;background:transparent"><div class="ticket-time"><strong>۱۶:۴۰</strong><small>پنج‌شنبه</small></div><div class="list-copy"><strong>معاینه و مشاوره</strong><small>شعبه ونک · ۱۲ دقیقه تا مقصد</small></div><button class="button button-secondary" type="button" data-go="patient/appointment-detail">جزئیات</button></div></section></div><aside class="card"><div class="card-head"><div><h2>دسترسی سریع</h2><p>کارهای پرکاربرد</p></div></div><div class="list-stack"><a class="list-row" href="${routeFor("patient", "appointments")}"><span class="list-icon">${icon("calendar")}</span><div class="list-copy"><strong>نوبت‌های من</strong><small>یک نوبت آینده</small></div>${icon("chevron", "chevron")}</a><a class="list-row" href="${routeFor("patient", "support")}"><span class="list-icon">${icon("support")}</span><div class="list-copy"><strong>پشتیبانی</strong><small>پاسخ معمولاً زیر ۱۵ دقیقه</small></div>${icon("chevron", "chevron")}</a><a class="list-row" href="${routeFor("patient", "profile-consents")}"><span class="list-icon">${icon("shield")}</span><div class="list-copy"><strong>حریم خصوصی</strong><small>مدیریت رضایت‌ها</small></div>${icon("chevron", "chevron")}</a></div></aside></div>`;
}

function emergencyScreen(page) {
  return `<div class="grid grid-main"><section class="card card-elevated"><div class="alert alert-danger">${icon("alert")}<div><strong>این وضعیت نیازمند اقدام فوری است</strong><p>${esc(page.notice)}</p></div></div><div class="grid grid-2" style="margin-top:1rem"><button class="card" type="button" data-toast="تماس اضطراری آماده شد" style="text-align:right;cursor:pointer"><span class="list-icon">${icon("phone")}</span><h2 style="margin:.8rem 0 .25rem;font-size:1rem">تماس با خدمات اضطراری</h2><p style="color:var(--muted);font-size:.75rem">برقراری تماس سریع با سرویس تأییدشده منطقه</p></button><button class="card" type="button" data-go="patient/support" style="text-align:right;cursor:pointer"><span class="list-icon">${icon("support")}</span><h2 style="margin:.8rem 0 .25rem;font-size:1rem">راهنمایی انسانی</h2><p style="color:var(--muted);font-size:.75rem">ارتباط با تیم پشتیبانی رویادَرمان</p></button></div>${actionBar(page)}</section><aside class="card"><div class="card-head"><div><h2>نکته مهم</h2><p>این مسیر جایگزین تشخیص نیست</p></div></div><p style="color:var(--ink-soft);font-size:.78rem">برای حفظ ایمنی، رزرو و پرداخت در این مرحله متوقف می‌شود. اطلاعات پاسخ شما برای پیگیری ایمن ثبت خواهد شد.</p></aside></div>`;
}

function locationScreen(page) {
  return `<div class="grid grid-main"><section class="card card-elevated"><div class="card-head"><div><h2>محدوده جست‌وجو</h2><p>نشانی تقریبی برای یافتن گزینه‌های نزدیک کافی است.</p></div><button class="button button-secondary" type="button" data-toast="موقعیت فعلی دریافت شد">${icon("location", "icon-sm")} موقعیت فعلی</button></div><div class="map-canvas" role="img" aria-label="نقشه نمایشی محدوده ونک"><span class="map-road r1"></span><span class="map-road r2"></span><span class="map-road r3"></span><span class="map-pin p1">${icon("location")}</span><span class="map-pin p2">${icon("building")}</span><span class="map-pin p3">${icon("building")}</span></div></section><aside class="card"><div class="card-head"><div><h2>نشانی شما</h2><p>قابل تغییر در هر زمان</p></div></div>${renderFields(page)}${actionBar(page)}</aside></div>`;
}

function matchingScreen(page) {
  const tasks = [["صلاحیت مراکز", "تکمیل شد", 100], ["زمان‌های آزاد", "در حال بررسی", 72], ["زمان سفر", "در صف", 38]];
  return `<section class="card card-elevated centered-card"><div class="processing-ring" aria-hidden="true"></div><h2>در حال یافتن بهترین گزینه‌ها</h2><p style="color:var(--muted);font-size:.8rem">نتایج بر اساس صلاحیت، زمان آزاد، فاصله و ترجیحات شما مرتب می‌شوند.</p><div class="list-stack" style="text-align:right;margin-top:1.25rem">${tasks.map(([title, status, progress], index) => `<div class="list-row"><span class="list-icon">${index === 0 ? icon("check") : icon("search")}</span><div class="list-copy"><strong>${title}</strong><small>${status}</small><div class="progress" style="margin-top:.35rem"><span style="width:${progress}%"></span></div></div></div>`).join("")}</div><div class="action-bar"><button class="button button-secondary" type="button" data-toast="جست‌وجو متوقف شد">لغو جست‌وجو</button><button class="button button-primary" type="button" data-go="patient/offers">${icon("check", "icon-sm")} مشاهده گزینه‌ها</button></div></section>`;
}

function offersScreen(page) {
  const offers = [
    { name: "کلینیک دندان‌پزشکی آبان", distance: "۱۲ دقیقه", time: "امروز · ۱۶:۴۰", price: "۵۸۰٬۰۰۰ تومان", rating: "۴٫۸", featured: true },
    { name: "مرکز تخصصی سپید", distance: "۱۸ دقیقه", time: "امروز · ۱۸:۰۰", price: "۶۲۰٬۰۰۰ تومان", rating: "۴٫۷" },
    { name: "درمانگاه شبانه‌روزی مهر", distance: "۹ دقیقه", time: "فردا · ۱۰:۳۰", price: "۵۵۰٬۰۰۰ تومان", rating: "۴٫۶" },
  ];
  return `<div class="grid grid-3">${offers.map((offer, index) => `<article class="card offer-card ${offer.featured ? "is-featured" : ""}">${offer.featured ? '<span class="featured-ribbon">پیشنهاد رویادَرمان</span>' : ""}<div class="clinic-brand"><span class="clinic-mark">${index === 0 ? "آ" : index === 1 ? "س" : "م"}</span><div><h2 style="font-size:.9rem;margin:0">${offer.name}</h2><span class="rating">★ ${offer.rating} · تأییدشده</span></div></div><div class="offer-meta"><span>${icon("clock", "icon-sm")} ${offer.distance}</span><span>${icon("calendar", "icon-sm")} ${offer.time}</span></div><div class="price-row"><div class="price"><strong>${offer.price}</strong><small>هزینه مراجعه اولیه</small></div><button class="button ${index === 0 ? "button-primary" : "button-secondary"}" type="button" data-go="patient/clinic-detail">انتخاب</button></div></article>`).join("")}</div><div class="demo-note">${icon("shield", "icon-sm")} همه گزینه‌ها دارای مجوز معتبر و ظرفیت قابل رزرو هستند.</div>`;
}

function clinicDetailScreen(page) {
  return `<div class="grid grid-main"><section class="card card-elevated"><div class="clinic-brand"><span class="clinic-mark" style="width:62px;height:62px;font-size:1.2rem">آ</span><div><h2 style="margin:0">کلینیک دندان‌پزشکی آبان</h2><span class="rating">★ ۴٫۸ از ۳۱۲ مراجعه تأییدشده</span><div class="state-strip"><span class="status-badge status-success">مجوز فعال</span><span class="status-badge status-info">پاسخ‌گویی سریع</span></div></div></div><div class="map-canvas" role="img" style="min-height:220px;margin-top:1rem" aria-label="موقعیت کلینیک"><span class="map-road r1"></span><span class="map-road r2"></span><span class="map-pin p1">${icon("building")}</span></div><div class="grid grid-3" style="margin-top:1rem"><div class="card"><strong>۱۲ دقیقه</strong><p style="color:var(--muted);font-size:.7rem;margin:0">زمان سفر</p></div><div class="card"><strong>امروز ۱۶:۴۰</strong><p style="color:var(--muted);font-size:.7rem;margin:0">اولین نوبت</p></div><div class="card"><strong>۵۸۰٬۰۰۰ تومان</strong><p style="color:var(--muted);font-size:.7rem;margin:0">هزینه مراجعه</p></div></div>${actionBar(page)}</section><aside class="card"><div class="card-head"><div><h2>امکانات مرکز</h2><p>اطلاعات تأییدشده شعبه</p></div></div><div class="list-stack">${["دسترسی ویلچر", "پارکینگ عمومی", "پذیرش کودک", "پرداخت آنلاین"].map((item) => `<div class="list-row"><span class="list-icon">${icon("check")}</span><div class="list-copy"><strong>${item}</strong><small>در دسترس</small></div></div>`).join("")}</div></aside></div>`;
}

function reservationScreen(page, mode = "reservation") {
  const isCheckout = mode === "checkout";
  return `<div class="grid grid-main"><section class="card card-elevated"><div class="card-head"><div><h2>${isCheckout ? "خلاصه پرداخت" : "رزرو موقت شما"}</h2><p>جزئیات را پیش از ادامه بررسی کنید.</p></div>${isCheckout ? '<span class="status-badge status-info">درگاه امن</span>' : `<span class="countdown">${icon("clock", "icon-sm")} ۰۶:۴۲</span>`}</div><div class="list-stack"><div class="list-row"><span class="list-icon">${icon("building")}</span><div class="list-copy"><strong>کلینیک دندان‌پزشکی آبان</strong><small>شعبه ونک · معاینه و مشاوره</small></div></div><div class="list-row"><span class="list-icon">${icon("calendar")}</span><div class="list-copy"><strong>پنج‌شنبه ۱۲ شهریور · ۱۶:۴۰</strong><small>زمان محلی شعبه</small></div></div><div class="list-row"><span class="list-icon">${icon("wallet")}</span><div class="list-copy"><strong>۵۸۰٬۰۰۰ تومان</strong><small>هزینه مراجعه اولیه</small></div></div></div>${isCheckout ? '<div class="alert alert-info" style="margin-top:1rem">' + icon("shield") + '<div><strong>پرداخت محافظت‌شده</strong><p>اطلاعات کارت فقط در صفحه امن درگاه وارد می‌شود.</p></div></div>' : renderChoices(page)}${actionBar(page)}</section><aside class="card"><div class="card-head"><div><h2>سیاست لغو</h2><p>نسخه ثبت‌شده این سفارش</p></div></div><p style="color:var(--ink-soft);font-size:.76rem">لغو تا ۲۴ ساعت پیش از مراجعه بدون کسر هزینه انجام می‌شود. مبلغ قابل بازگشت پیش از تأیید نمایش داده خواهد شد.</p><a class="button button-secondary" href="${routeFor("patient", "cancel-reschedule")}">${icon("eye", "icon-sm")} مشاهده جزئیات</a></aside></div>`;
}

function processingScreen(page) {
  return `<section class="card card-elevated centered-card"><div class="processing-ring" aria-hidden="true"></div><h2>در حال تأیید پرداخت</h2><p style="color:var(--muted);font-size:.8rem">لطفاً این صفحه را نبندید. نتیجه قطعی از درگاه دریافت می‌شود.</p><div class="alert alert-warning" style="text-align:right;margin-top:1rem">${icon("clock")}<div><strong>رزرو شما محفوظ است</strong><p>تا روشن‌شدن نتیجه، پرداخت را تکرار نکنید.</p></div></div>${actionBar(page)}</section>`;
}

function receiptScreen(page) {
  return `<section class="card card-elevated centered-card"><div class="success-seal">${icon("check")}</div><h2>نوبت شما با موفقیت تأیید شد</h2><p style="color:var(--muted);font-size:.8rem">شماره پیگیری: RD-1405-08312</p><div class="card appointment-ticket" style="text-align:right;margin-top:1.25rem"><div class="list-row" style="border:0;background:transparent;padding:0"><div class="ticket-time"><strong>۱۶:۴۰</strong><small>پنج‌شنبه</small></div><div class="list-copy"><strong>کلینیک دندان‌پزشکی آبان</strong><small>شعبه ونک · معاینه و مشاوره</small></div><span class="status-badge status-success">قطعی</span></div></div>${actionBar(page)}</section>`;
}

function appointmentsScreen(page) {
  const rows = [
    ["۱۲ شهریور · ۱۶:۴۰", "کلینیک آبان", "معاینه و مشاوره", "تأییدشده", "status-success"],
    ["۲۲ مرداد · ۱۰:۱۵", "مرکز سپید", "ارزیابی فوری", "تکمیل‌شده", "status-info"],
    ["۱۴ تیر · ۱۸:۰۰", "درمانگاه مهر", "معاینه", "ثبت نظر", "status-warning"],
  ];
  return `<section class="card card-elevated"><div class="card-head"><div><h2>همه نوبت‌ها</h2><p>آینده، گذشته و نیازمند اقدام</p></div><button class="button button-primary" type="button" data-go="patient/need">${icon("plus", "icon-sm")} درخواست جدید</button></div><div class="list-stack">${rows.map(([time, clinic, service, state, tone], index) => `<button class="list-row" type="button" data-go="patient/appointment-detail" style="width:100%;text-align:right;cursor:pointer"><span class="list-icon">${icon("calendar")}</span><div class="list-copy"><strong>${time} · ${clinic}</strong><small>${service}</small></div><span class="status-badge ${tone}">${state}</span>${icon("chevron", "chevron")}</button>`).join("")}</div></section>`;
}

function appointmentDetailScreen(page) {
  return `<div class="grid grid-main"><section class="card appointment-ticket card-elevated"><div class="card-head"><div><h2>کلینیک دندان‌پزشکی آبان</h2><p>معاینه و مشاوره · شعبه ونک</p></div><span class="status-badge status-success">تأییدشده</span></div><div class="grid grid-3"><div class="card"><strong>پنج‌شنبه ۱۲ شهریور</strong><p style="margin:0;color:var(--muted);font-size:.68rem">تاریخ مراجعه</p></div><div class="card"><strong>۱۶:۴۰</strong><p style="margin:0;color:var(--muted);font-size:.68rem">ساعت حضور</p></div><div class="card"><strong>RD-08312</strong><p style="margin:0;color:var(--muted);font-size:.68rem">شماره پیگیری</p></div></div><div class="map-canvas" role="img" style="min-height:190px;margin-top:1rem" aria-label="موقعیت مرکز برای نوبت تأییدشده"><span class="map-road r1"></span><span class="map-road r2"></span><span class="map-pin p1">${icon("building")}</span></div>${actionBar(page)}</section><aside class="card"><div class="card-head"><div><h2>مسیر مراجعه</h2><p>وضعیت مرحله‌به‌مرحله</p></div></div><div class="timeline"><div class="timeline-item"><strong>رزرو و پرداخت تأیید شد</strong><small>امروز · ۱۴:۲۲</small></div><div class="timeline-item"><strong>یادآوری پیش از مراجعه</strong><small>یک روز قبل ارسال می‌شود</small></div><div class="timeline-item"><strong>ورود به مرکز</strong><small>کد حضور نزدیک زمان فعال می‌شود</small></div></div></aside></div>`;
}

function profileScreen(page) {
  return `<div class="grid grid-main"><section class="card card-elevated"><div class="card-head"><div><h2>اطلاعات حساب</h2><p>اطلاعات تماس و نشانی‌های ذخیره‌شده</p></div><button class="button button-secondary" type="button" data-toast="ویرایش اطلاعات فعال شد">ویرایش</button></div><div class="list-stack"><div class="list-row"><span class="list-icon">${icon("user")}</span><div class="list-copy"><strong>سارا احمدی</strong><small>۰۹۱۲ ۰۰۰ ۰۰۰۱</small></div></div><div class="list-row"><span class="list-icon">${icon("location")}</span><div class="list-copy"><strong>نشانی خانه</strong><small>تهران، محدوده ونک</small></div></div><div class="list-row"><span class="list-icon">${icon("bell")}</span><div class="list-copy"><strong>اعلان‌ها</strong><small>پیامک و اعلان اپ فعال</small></div><span class="status-badge status-success">فعال</span></div></div></section><aside class="card"><div class="card-head"><div><h2>حریم خصوصی</h2><p>کنترل داده و رضایت‌ها</p></div></div><div class="list-stack"><button class="list-row" type="button" data-toast="رضایت‌های شما نمایش داده شد"><span class="list-icon">${icon("shield")}</span><div class="list-copy"><strong>رضایت‌ها</strong><small>نسخه جاری تأیید شده</small></div>${icon("chevron", "chevron")}</button><button class="list-row" type="button" data-toast="درخواست خروجی ثبت شد"><span class="list-icon">${icon("upload")}</span><div class="list-copy"><strong>دریافت اطلاعات</strong><small>خروجی امن حساب</small></div>${icon("chevron", "chevron")}</button></div></aside></div>`;
}

function feedbackScreen(page) {
  return `<section class="card card-elevated centered-card"><span class="list-icon" style="width:58px;height:58px;margin:0 auto 1rem">${icon("star")}</span><h2>تجربه مراجعه شما چطور بود؟</h2><p style="color:var(--muted);font-size:.8rem">نظر شما به بهبود کیفیت شبکه کمک می‌کند.</p><div style="display:flex;justify-content:center;gap:.55rem;margin:1.2rem 0" aria-label="امتیاز پنج از پنج">${[1, 2, 3, 4, 5].map((i) => `<button class="icon-button" type="button" data-toast="امتیاز ${i} ثبت شد" aria-label="امتیاز ${i}">${icon("star")}</button>`).join("")}</div>${renderFields(page)}${actionBar(page)}</section>`;
}

function dashboardScreen(page) {
  const isAdmin = page.role === "admin";
  const isClinic = page.role === "clinic";
  const metrics = isAdmin ? [["درخواست امروز", "۱٬۲۴۸", "+۱۲٪"], ["SLA در خطر", "۷", "۲ مورد جدید"], ["پرداخت مبهم", "۲", "بدون تغییر"], ["سلامت سرویس", "۹۹٫۹٪", "پایدار"]] : [["درخواست جدید", "۲۴", "+۸٪"], ["نوبت امروز", "۱۲", "۳ مراجعه بعدازظهر"], ["پاسخ در SLA", "۹۶٪", "+۲٪"], ["تسویه آماده", "۸۴ م", "دسته هفتگی"]];
  const queue = isAdmin ? ["پرداخت موفق بدون نوبت معتبر", "مجوز مرکز تا ۵ روز دیگر منقضی می‌شود", "اختلاف تطبیق بانکی", "صف اعلان با تأخیر ۴ دقیقه"] : ["درخواست جدید · ۲:۴۰ تا پایان SLA", "مراجعه آماده ثبت ورود", "درخواست تغییر زمان", "مجوز متخصص تا ۲۱ روز دیگر"];
  return `<div class="grid grid-4">${metrics.map(([label, value, note], index) => `<article class="card metric-card"><div class="metric-top"><span class="metric-label">${label}</span><span class="list-icon" style="width:34px;height:34px">${icon(["chart", "clock", "wallet", "shield"][index], "icon-sm")}</span></div><div class="metric-value">${value}</div><div class="metric-foot"><span class="${index === 1 ? "trend-down" : "trend-up"}">${note}</span></div></article>`).join("")}</div><div class="grid grid-main" style="margin-top:1rem"><section class="card card-elevated"><div class="card-head"><div><h2>${isAdmin ? "صف اقدام‌های اولویت‌دار" : "کارهای امروز شعبه"}</h2><p>بر اساس ریسک و زمان باقی‌مانده</p></div><button class="button button-secondary" type="button" data-toast="همه موارد نمایش داده شدند">مشاهده همه</button></div><div class="list-stack">${queue.map((item, index) => `<div class="list-row"><span class="list-icon">${icon(index === 1 ? "clock" : index === 2 ? "wallet" : "alert")}</span><div class="list-copy"><strong>${item}</strong><small>مالک: ${isAdmin ? "تیم عملیات" : "پذیرش شعبه"}</small></div><span class="status-badge ${index === 0 ? "status-danger" : statusTone(index)}">${["فوری", "امروز", "در حال بررسی", "پایدار"][index]}</span>${icon("chevron", "chevron")}</div>`).join("")}</div></section><aside class="card"><div class="card-head"><div><h2>روند هفتگی</h2><p>${isAdmin ? "مراجعه تکمیل‌شده" : "نوبت‌های شعبه"}</p></div><span class="status-badge status-success">+۱۱٪</span></div><div class="chart">${[48, 64, 56, 76, 68, 88, 73].map((height, index) => `<div class="chart-column"><span class="chart-bar" style="height:${height}%"></span><small>${["ش", "ی", "د", "س", "چ", "پ", "ج"][index]}</small></div>`).join("")}</div></aside></div>`;
}

function availabilityScreen(page) {
  const days = ["شنبه ۷", "یکشنبه ۸", "دوشنبه ۹", "سه‌شنبه ۱۰", "چهارشنبه ۱۱"];
  const times = ["۰۹:۰۰", "۱۱:۰۰", "۱۳:۰۰", "۱۵:۰۰"];
  return `<section class="card card-elevated"><div class="card-head"><div><h2>تقویم ظرفیت شعبه</h2><p>شهریور ۱۴۰۵ · شعبه ونک</p></div><div class="page-head-actions"><button class="button button-secondary" type="button" data-toast="زمان مسدود شد">مسدودکردن زمان</button><button class="button button-primary" type="button" data-toast="برنامه منتشر شد">انتشار برنامه</button></div></div><div class="calendar"><div class="calendar-cell calendar-head">ساعت</div>${days.map((day) => `<div class="calendar-cell calendar-head">${day}</div>`).join("")}${times.map((time, row) => `<div class="calendar-cell">${time}</div>${days.map((_, col) => `<div class="calendar-cell">${(row + col) % 3 === 0 ? `<div class="calendar-event">${row % 2 ? "رزرو شده" : "ظرفیت آزاد"}</div>` : ""}</div>`).join("")}`).join("")}</div></section>`;
}

function operationsTable(page) {
  const rows = page.role === "clinic" ? [
    ["RQ-1048", "معاینه و مشاوره", "امروز ۱۶:۴۰", "۲:۴۰", "جدید"],
    ["RQ-1047", "درد و ارزیابی فوری", "امروز ۱۸:۰۰", "۴:۱۰", "مشاهده‌شده"],
    ["RQ-1042", "مشکل لثه", "فردا ۱۰:۳۰", "—", "پذیرفته‌شده"],
  ] : [
    ["EX-0831", "کلینیک آبان", "پرداخت / رزرو", "۵ دقیقه", "فوری"],
    ["EX-0829", "مرکز سپید", "مجوز / شبکه", "۲ ساعت", "در بررسی"],
    ["EX-0824", "درمانگاه مهر", "تطبیق مالی", "۱ روز", "تخصیص‌شده"],
  ];
  return `${renderMetrics(page)}<section class="card card-elevated" style="margin-top:${page.metrics.length ? "1rem" : "0"}"><div class="card-head"><div><h2>فهرست ${esc(page.title)}</h2><p>مرتب‌سازی‌شده بر اساس اولویت</p></div><div class="page-head-actions"><button class="button button-secondary" type="button" data-toast="فیلتر اعمال شد">${icon("filter", "icon-sm")} فیلتر</button><button class="button button-primary" type="button" data-toast="مورد به شما تخصیص یافت">${icon("plus", "icon-sm")} ${esc(page.actions[0] || "اقدام جدید")}</button></div></div><div class="data-table-wrap"><table class="data-table"><thead><tr><th>شناسه</th><th>مرکز / خدمت</th><th>دسته</th><th>زمان</th><th>وضعیت</th><th><span class="sr-only">اقدام</span></th></tr></thead><tbody>${rows.map((row, index) => `<tr><td><strong>${row[0]}</strong></td><td><div class="row-main"><span class="row-avatar">${index === 0 ? "آ" : index === 1 ? "س" : "م"}</span><span>${row[1]}</span></div></td><td>${row[2]}</td><td>${row[3]}</td><td><span class="status-badge ${index === 0 ? "status-warning" : statusTone(index)}">${row[4]}</span></td><td><button class="icon-button" type="button" aria-label="مشاهده جزئیات" data-toast="جزئیات مورد باز شد">${icon("chevron")}</button></td></tr>`).join("")}</tbody></table></div></section>`;
}

function checkinScreen(page) {
  return `<div class="grid grid-main"><section class="card card-elevated centered-card"><span class="status-badge status-success">نوبت معتبر</span><h2 style="margin-top:1rem">ثبت ورود بیمار</h2><p style="color:var(--muted);font-size:.78rem">کد یک‌بارمصرف بیمار را وارد کنید.</p><div class="otp-boxes" role="group" aria-label="کد یک‌بارمصرف پذیرش"><input class="otp-box" maxlength="1" inputmode="numeric" value="4" aria-label="رقم اول"><input class="otp-box" maxlength="1" inputmode="numeric" value="7" aria-label="رقم دوم"><input class="otp-box" maxlength="1" inputmode="numeric" value="2" aria-label="رقم سوم"><input class="otp-box" maxlength="1" inputmode="numeric" value="8" aria-label="رقم چهارم"><input class="otp-box" maxlength="1" inputmode="numeric" value="1" aria-label="رقم پنجم"></div>${actionBar(page)}</section><aside class="card"><div class="card-head"><div><h2>اطلاعات نوبت</h2><p>حداقل داده موردنیاز پذیرش</p></div></div><div class="list-stack"><div class="list-row"><span class="list-icon">${icon("user")}</span><div class="list-copy"><strong>سارا ا.</strong><small>شناسه نوبت AP-8312</small></div></div><div class="list-row"><span class="list-icon">${icon("calendar")}</span><div class="list-copy"><strong>امروز · ۱۶:۴۰</strong><small>معاینه و مشاوره</small></div></div></div></aside></div>`;
}

function settlementScreen(page) {
  return `${renderMetrics(page)}<div class="grid grid-main" style="margin-top:1rem"><section class="card card-elevated"><div class="card-head"><div><h2>دسته‌های تسویه اخیر</h2><p>مانده، وضعیت و تاریخ واریز</p></div><button class="button button-primary" type="button" data-toast="گزارش مالی آماده شد">${icon("upload", "icon-sm")} دریافت گزارش</button></div><div class="data-table-wrap"><table class="data-table"><thead><tr><th>شناسه</th><th>بازه</th><th>مبلغ</th><th>موارد</th><th>وضعیت</th></tr></thead><tbody><tr><td>ST-1405-31</td><td>۱ تا ۷ شهریور</td><td>۸۴٬۵۰۰٬۰۰۰ ریال</td><td>۲۱ مراجعه</td><td><span class="status-badge status-success">آماده واریز</span></td></tr><tr><td>ST-1405-30</td><td>۲۵ تا ۳۱ مرداد</td><td>۷۶٬۲۰۰٬۰۰۰ ریال</td><td>۱۹ مراجعه</td><td><span class="status-badge status-info">تطبیق‌شده</span></td></tr><tr><td>ST-1405-29</td><td>۱۸ تا ۲۴ مرداد</td><td>۶۸٬۹۰۰٬۰۰۰ ریال</td><td>۱۷ مراجعه</td><td><span class="status-badge status-success">واریزشده</span></td></tr></tbody></table></div></section><aside class="card"><div class="card-head"><div><h2>ترکیب مانده</h2><p>وضعیت اقلام این دوره</p></div></div><div class="donut" data-value="۸۴٪"></div><div class="state-strip"><span class="status-badge status-success">واجد تسویه ۸۴٪</span><span class="status-badge status-warning">در انتظار ۱۲٪</span><span class="status-badge">متوقف ۴٪</span></div></aside></div>`;
}

function matchingConfigScreen(page) {
  const weights = [["زمان سفر", 30], ["نزدیک‌ترین نوبت", 28], ["تناسب خدمت", 22], ["اتکاپذیری مرکز", 12], ["انصاف توزیع", 8]];
  return `<div class="grid grid-main"><section class="card card-elevated"><div class="card-head"><div><h2>نسخه پیکربندی ۱.۸</h2><p>پیش‌نویس · آخرین تغییر امروز ۱۰:۳۲</p></div><span class="status-badge status-warning">پیش‌نویس</span></div>${weights.map(([label, value], index) => `<div class="slider-row"><label for="weight-${index}">${label}</label><input id="weight-${index}" type="range" min="0" max="50" value="${value}" data-slider><span class="slider-value">${value}٪</span></div>`).join("")}<div class="alert alert-success" style="margin-top:1rem">${icon("check")}<div><strong>مجموع وزن‌ها ۱۰۰٪ است</strong><p>پیکربندی برای شبیه‌سازی آماده است.</p></div></div>${actionBar(page)}</section><aside class="card"><div class="card-head"><div><h2>نتیجه شبیه‌سازی</h2><p>بر پایه داده ناشناس پایلوت</p></div></div><div class="chart">${[62, 73, 68, 84, 78].map((height, index) => `<div class="chart-column"><span class="chart-bar" style="height:${height}%"></span><small>${["نسخه فعلی", "زمان", "تناسب", "اتکا", "نسخه جدید"][index]}</small></div>`).join("")}</div></aside></div>`;
}

function taxonomyScreen(page) {
  return `<div class="grid grid-main"><section class="card card-elevated"><div class="card-head"><div><h2>محدوده پوشش پایلوت</h2><p>تهران · نسخه ۳</p></div><button class="button button-primary" type="button" data-toast="نسخه جدید ساخته شد">${icon("plus", "icon-sm")} ساخت نسخه</button></div><div class="map-canvas" role="img" aria-label="نقشه محدوده پوشش"><span class="map-road r1"></span><span class="map-road r2"></span><span class="map-road r3"></span><span class="map-pin p1">${icon("location")}</span><span class="map-pin p2">${icon("building")}</span><span class="map-pin p3">${icon("building")}</span></div></section><aside class="card"><div class="card-head"><div><h2>خدمات فعال</h2><p>طبقه‌بندی تأییدشده</p></div></div><div class="list-stack">${["معاینه و مشاوره", "درد و ارزیابی فوری", "آسیب و شکستگی", "مشکل لثه"].map((item, index) => `<div class="list-row"><span class="list-icon">${icon("heart")}</span><div class="list-copy"><strong>${item}</strong><small>${index + 8} مرکز واجد شرایط</small></div><span class="status-badge status-success">فعال</span></div>`).join("")}</div></aside></div>`;
}

function auditScreen(page) {
  const events = [["بازپرداخت تأیید شد", "مدیر مالی · ۱۲:۴۲", "RF-0812"], ["دسترسی مرکز تغییر کرد", "مدیر شبکه · ۱۱:۱۸", "CL-0418"], ["گزارش محدود دریافت شد", "کارشناس عملیات · ۱۰:۰۵", "EX-311"], ["ورود با تأیید دومرحله‌ای", "مدیر عملیات · ۰۹:۳۱", "SE-092"]];
  return `<div class="grid grid-main"><section class="card card-elevated"><div class="card-head"><div><h2>ردپای اقدامات مدیریتی</h2><p>غیرقابل ویرایش و قابل جست‌وجو</p></div><button class="button button-secondary" type="button" data-toast="خروجی محدود آماده شد">${icon("upload", "icon-sm")} خروجی محدود</button></div><div class="timeline">${events.map(([title, meta, code]) => `<div class="timeline-item"><strong>${title}</strong><small>${meta} · ${code}</small></div>`).join("")}</div></section><aside class="card"><div class="card-head"><div><h2>فیلتر رویداد</h2><p>جست‌وجوی امن</p></div></div>${renderFields(page)}${actionBar(page)}</aside></div>`;
}

function communicationsScreen(page) {
  return `${renderMetrics(page)}<section class="card card-elevated" style="margin-top:1rem"><div class="card-head"><div><h2>کانال‌ها و تحویل</h2><p>وضعیت لحظه‌ای سرویس‌های ارتباطی</p></div><button class="button button-primary" type="button" data-toast="مسیر سرویس تغییر کرد">تغییر مسیر سرویس</button></div><div class="data-table-wrap"><table class="data-table"><thead><tr><th>کانال</th><th>تحویل امروز</th><th>میانگین زمان</th><th>خطا</th><th>وضعیت</th></tr></thead><tbody><tr><td>پیامک کد ورود</td><td>۴٬۸۲۰</td><td>۳٫۲ ثانیه</td><td>۰٫۳٪</td><td><span class="status-badge status-success">سالم</span></td></tr><tr><td>اعلان اپ</td><td>۱۲٬۴۱۶</td><td>۱٫۱ ثانیه</td><td>۰٫۱٪</td><td><span class="status-badge status-success">سالم</span></td></tr><tr><td>پیامک یادآوری</td><td>۲٬۱۰۳</td><td>۴٫۸ ثانیه</td><td>۱٫۲٪</td><td><span class="status-badge status-warning">پایش</span></td></tr><tr><td>ایمیل رسید</td><td>۱٬۸۹۰</td><td>۸ ثانیه</td><td>۰٫۲٪</td><td><span class="status-badge status-info">فعال</span></td></tr></tbody></table></div></section>`;
}

function renderSpecial(page) {
  if (["login", "login-mfa"].includes(page.key)) return loginScreen(page);
  if (page.role === "patient" && page.key === "home") return patientHome(page);
  if (page.role === "patient" && page.key === "emergency") return emergencyScreen(page);
  if (page.role === "patient" && page.key === "location") return locationScreen(page);
  if (page.role === "patient" && page.key === "matching") return matchingScreen(page);
  if (page.role === "patient" && page.key === "offers") return offersScreen(page);
  if (page.role === "patient" && page.key === "clinic-detail") return clinicDetailScreen(page);
  if (page.role === "patient" && page.key === "reservation") return reservationScreen(page);
  if (page.role === "patient" && page.key === "checkout") return reservationScreen(page, "checkout");
  if (page.role === "patient" && page.key === "payment-processing") return processingScreen(page);
  if (page.role === "patient" && page.key === "receipt") return receiptScreen(page);
  if (page.role === "patient" && page.key === "appointments") return appointmentsScreen(page);
  if (page.role === "patient" && page.key === "appointment-detail") return appointmentDetailScreen(page);
  if (page.role === "patient" && page.key === "profile-consents") return profileScreen(page);
  if (page.role === "patient" && page.key === "visit-feedback") return feedbackScreen(page);
  if ((page.role === "clinic" && page.key === "dashboard") || (page.role === "admin" && page.key === "command-center")) return dashboardScreen(page);
  if (page.role === "clinic" && page.key === "availability") return availabilityScreen(page);
  if (page.role === "clinic" && ["incoming-requests", "appointments"].includes(page.key)) return operationsTable(page);
  if (page.role === "clinic" && page.key === "checkin") return checkinScreen(page);
  if (page.role === "clinic" && page.key === "settlements") return settlementScreen(page);
  if (page.role === "admin" && ["clinic-verification", "clinic-assessment", "unmatched", "sla", "booking-exceptions", "payment-exceptions", "refund-approval", "disputes"].includes(page.key)) return operationsTable(page);
  if (page.role === "admin" && ["settlements", "reconciliation"].includes(page.key)) return settlementScreen(page);
  if (page.role === "admin" && page.key === "matching-config") return matchingConfigScreen(page);
  if (page.role === "admin" && page.key === "taxonomy-areas") return taxonomyScreen(page);
  if (page.role === "admin" && page.key === "audit-access") return auditScreen(page);
  if (page.role === "admin" && page.key === "communications") return communicationsScreen(page);
  return genericScreen(page);
}

function renderApp(role, key) {
  const meta = ROLE_META[role];
  const page = pageFor(role, key);
  document.body.className = `demo-role-${role}`;
  document.title = `${page.title} | دموی رویادَرمان`;
  localStorage.setItem("royadarman-demo-role", role);
  app.innerHTML = `
    <div class="app-shell role-${role}">
      <aside class="sidebar" id="app-sidebar" aria-label="فهرست صفحات ${esc(meta.label)}">
        <div class="sidebar-head"><a class="brand-lockup" href="${routeFor(role, meta.default)}"><span class="brand-symbol">ر</span><span class="brand-copy"><strong>رویادَرمان</strong><small>محیط نمایشی</small></span></a><button class="icon-button sidebar-close" type="button" data-close-menu aria-label="بستن فهرست">${icon("x")}</button></div>
        <div class="role-label">${icon(meta.icon, "icon-sm")} ${esc(meta.label)} <span class="status-badge" style="margin-inline-start:auto">${meta.count.toLocaleString("fa-IR")}</span></div>
        <div class="nav-search-wrap">${icon("search", "icon-sm")}<label class="sr-only" for="nav-search">جست‌وجوی صفحه</label><input class="nav-search" id="nav-search" type="search" placeholder="جست‌وجوی صفحه…" autocomplete="off"></div>
        <nav class="sidebar-nav">${navSections(role, page.key)}</nav>
        <div class="sidebar-foot"><div class="profile-mini"><span class="avatar">${esc(meta.initials)}</span><span style="min-width:0;flex:1"><strong>${esc(meta.profile)}</strong><small>${esc(meta.profileMeta)}</small></span><button class="icon-button" type="button" data-exit-demo aria-label="خروج از دمو">${icon("logout", "icon-sm")}</button></div></div>
      </aside>
      <button class="sidebar-overlay" type="button" data-close-menu aria-label="بستن فهرست"></button>
      <div class="app-workspace">
        <header class="topbar"><div class="topbar-start"><button class="icon-button menu-button" type="button" data-open-menu aria-label="بازکردن فهرست" aria-controls="app-sidebar" aria-expanded="false">${icon("menu")}</button><div class="breadcrumb-mini">${esc(meta.shortLabel)} <span> / </span> <strong>${esc(page.title)}</strong></div></div><div class="topbar-actions"><span class="demo-badge"><span class="demo-dot"></span> دمو</span><button class="icon-button" type="button" aria-label="اعلان‌ها" data-toast="اعلان جدیدی ندارید">${icon("bell")}</button>${roleSwitcher(role)}</div></header>
        <main class="content" id="main-content" tabindex="-1">${pageHeader(page)}${role === "patient" ? patientSteps(page) : ""}${renderSpecial(page)}<div class="demo-note">${icon("shield", "icon-sm")} این محیط شامل اطلاعات ساختگی است و هیچ عملیات واقعی مالی یا درمانی انجام نمی‌دهد.</div></main>
        ${mobileNav(role, page.key)}
      </div>
    </div>`;
  window.scrollTo({ top: 0, behavior: "auto" });
}

function render() {
  const route = currentRoute();
  if (route.mode === "access") {
    renderAccess();
    return;
  }
  renderApp(route.role, route.key);
}

function nextPage(page) {
  const pages = pagesByRole[page.role];
  return pages[Math.min(page.index + 1, pages.length - 1)];
}

document.addEventListener("click", (event) => {
  const enter = event.target.closest("[data-enter-role]");
  if (enter) {
    const role = enter.dataset.enterRole;
    location.hash = routeFor(role, ROLE_META[role].default);
    return;
  }

  const switcher = event.target.closest("[data-switch-role]");
  if (switcher) {
    const role = switcher.dataset.switchRole;
    location.hash = routeFor(role, ROLE_META[role].default);
    return;
  }

  if (event.target.closest("[data-exit-demo]")) {
    localStorage.removeItem("royadarman-demo-role");
    location.hash = "#/access";
    return;
  }

  if (event.target.closest("[data-open-menu]")) {
    document.querySelector(".sidebar")?.classList.add("is-open");
    document.querySelector(".sidebar-overlay")?.classList.add("is-open");
    document.querySelector("[data-open-menu]")?.setAttribute("aria-expanded", "true");
    document.body.classList.add("nav-open");
    document.querySelector(".sidebar-close")?.focus();
    return;
  }

  if (event.target.closest("[data-close-menu]")) {
    document.querySelector(".sidebar")?.classList.remove("is-open");
    document.querySelector(".sidebar-overlay")?.classList.remove("is-open");
    document.querySelector("[data-open-menu]")?.setAttribute("aria-expanded", "false");
    document.body.classList.remove("nav-open");
    document.querySelector("[data-open-menu]")?.focus();
    return;
  }

  const go = event.target.closest("[data-go]");
  if (go) {
    const [role, key] = go.dataset.go.split("/");
    location.hash = routeFor(role, key);
    return;
  }

  if (event.target.closest("[data-login-demo]")) {
    const route = currentRoute();
    location.hash = routeFor(route.role, ROLE_META[route.role].default);
    showToast("ورود موفق", "به محیط نمایشی رویادَرمان خوش آمدید.");
    return;
  }

  const pageAction = event.target.closest("[data-page-action]");
  if (pageAction) {
    const route = currentRoute();
    const page = pageFor(route.role, route.key);
    const index = Number(pageAction.dataset.pageAction);
    const label = page.actions[index] || "اقدام";
    showToast(label);
    const patientFlowKeys = ["need", "urgency", "location", "time-preferences", "matching", "clinic-detail", "reservation", "checkout", "payment-processing"];
    if (route.role === "patient" && page.key === "urgency" && index === 0) {
      const selected = document.querySelector('input[name="page-choice"]:checked');
      const destination = Number(selected?.value) === page.options.length - 1 ? "location" : "emergency";
      setTimeout(() => { location.hash = routeFor(route.role, destination); }, 320);
      return;
    }
    if (route.role === "patient" && index === 0 && patientFlowKeys.includes(page.key)) {
      const next = nextPage(page);
      setTimeout(() => { location.hash = routeFor(route.role, next.key); }, 320);
    }
    return;
  }

  const toastTrigger = event.target.closest("[data-toast]");
  if (toastTrigger) showToast(toastTrigger.dataset.toast);
});

document.addEventListener("input", (event) => {
  if (event.target.matches("#nav-search")) {
    const query = event.target.value.trim().toLocaleLowerCase("fa");
    document.querySelectorAll("[data-nav-item]").forEach((item) => {
      item.hidden = Boolean(query) && !item.dataset.title.toLocaleLowerCase("fa").includes(query);
    });
  }
  if (event.target.matches("[data-slider]")) {
    event.target.nextElementSibling.textContent = `${event.target.value}٪`;
  }
  if (event.target.classList.contains("otp-box") && event.target.value) {
    const next = event.target.nextElementSibling;
    if (next?.classList.contains("otp-box")) next.focus();
  }
});

document.addEventListener("submit", (event) => {
  if (event.target.matches("[data-demo-form]")) {
    event.preventDefault();
    showToast("اطلاعات ثبت شد");
  }
});

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape") {
    document.querySelector(".sidebar")?.classList.remove("is-open");
    document.querySelector(".sidebar-overlay")?.classList.remove("is-open");
    document.querySelector("[data-open-menu]")?.setAttribute("aria-expanded", "false");
    document.body.classList.remove("nav-open");
    document.querySelector("[data-open-menu]")?.focus();
  }
});

window.addEventListener("hashchange", render);

if (!location.hash) {
  const savedRole = localStorage.getItem("royadarman-demo-role");
  location.hash = savedRole && ROLE_META[savedRole] ? routeFor(savedRole, ROLE_META[savedRole].default) : "#/access";
} else {
  render();
}

export { pagesByRole, ROLE_META, NAV_SECTIONS, renderAccess, renderApp, renderSpecial, routeFor };
