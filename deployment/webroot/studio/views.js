let LANG = localStorage.getItem("roya-studio-lang") || "fa";
let CAL_MONTH = 7;
const MONTH_LEN = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];
const MONTH_NAMES = [
  ["فروردین", "Farvardin", "فروردین"],
  ["اردیبهشت", "Ordibehesht", "أرديبهشت"],
  ["خرداد", "Khordad", "خرداد"],
  ["تیر", "Tir", "تير"],
  ["اَمرداد", "Mordad", "مرداد"],
  ["شهریور", "Shahrivar", "شهريور"],
  ["مهر", "Mehr", "مهر"],
  ["آبان", "Aban", "آبان"],
  ["آذر", "Azar", "آذر"],
  ["دی", "Dey", "دي"],
  ["بهمن", "Bahman", "بهمن"],
  ["اسفند", "Esfand", "إسفند"],
];

function tr(row) {
  return LANG === "en" ? row[1] : LANG === "ar" ? row[2] : row[0];
}
function ui(key) {
  return COPY[LANG][key];
}
function esc(value) {
  return String(value)
    .replaceAll("&", "&" + "amp;")
    .replaceAll("<", "&" + "lt;")
    .replaceAll(">", "&" + "gt;")
    .replaceAll('"', "&" + "quot;");
}
function profileBy(id) {
  return PROFILES.find((item) => item[0] === id);
}
function nameOf(item) {
  return LANG === "en" ? item[2] : LANG === "ar" ? item[3] : item[1];
}
function boardBy(id) {
  return BOARDS.find((item) => item[0] === id);
}
function boardName(item) {
  return tr(item.slice(2));
}
function table(rows) {
  return `<div class="card"><table>${rows
    .map((row) => `<tr data-q="${esc(row.join(" "))}">${row.map((cell) => `<td>${esc(cell)}</td>`).join("")}</tr>`)
    .join("")}</table></div>`;
}

function chartHtml() {
  const saved = JSON.parse(localStorage.getItem("roya-teeth") || '{"26":"watch","36":"image"}');
  const cell = (n) => {
    const state = saved[String(n)] || "";
    return `<button type="button" class="tooth${state ? " " + state : ""}" data-tooth="${n}">${n}</button>`;
  };
  return `<div class="chart card">
    <p class="muted">${esc(tr(["کلیک: بدون علامت، یادداشت، نیاز به تصویر. تشخیص نیست. شماره FDI ثابت است.", "Click: blank, note, needs image. Not a diagnosis. FDI numbers stay put.", "النقر: فارغ، ملاحظة، يحتاج صورة. ليس تشخيصاً. أرقام FDI ثابتة."]))}</p>
    <div class="arch upper">${UPPER.map(cell).join("")}</div>
    <div class="arch lower">${LOWER.map(cell).join("")}</div>
    <p id="picked" class="muted"></p>
  </div>`;
}

function calendarHtml() {
  const month = CAL_MONTH;
  const prev = MONTH_LEN.slice(0, month - 1).reduce((sum, n) => sum + n, 0);
  const first = prev % 7;
  const len = MONTH_LEN[month - 1];
  const heads = ["ش", "ی", "د", "س", "چ", "پ", "ج"].map((day) => `<b>${esc(day)}</b>`).join("");
  let cells = "";
  for (let i = 0; i < first; i += 1) cells += `<span class="day empty"></span>`;
  for (let day = 1; day <= len; day += 1) {
    const weekday = (first + day - 1) % 7;
    const key = String(month).padStart(2, "0") + "-" + String(day).padStart(2, "0");
    const off = HOLIDAYS_1405[key] || [];
    const cls = ["day"];
    if (weekday === 6) cls.push("fri");
    if (off.length) cls.push("off");
    cells += `<button type="button" class="${cls.join(" ")}" data-date="${key}">${day}</button>`;
  }
  const months = MONTH_NAMES.map(
    (name, index) => `<button type="button" class="chip ${index + 1 === month ? "on" : ""}" data-month="${index + 1}">${esc(tr(name))}</button>`,
  ).join("");
  return `<div class="card"><p>${esc(tr(["۱۴۰۵. شنبه اول هفته است. تعطیل رسمی از مجموعه time.ir است. جمعه فقط نشان است و تعطیلی درمانگاه نیست.", "1405. Saturday starts the week. Official holidays come from the time.ir set. Friday is a marker, not a clinic closure.", "١٤٠٥. السبت أول الأسبوع. العطل الرسمية من مجموعة time.ir. الجمعة علامة وليست إغلاقاً."]))}</p><div class="row">${months}</div><div class="month">${heads}${cells}</div><p id="daynote" class="muted"></p></div>`;
}

function mapHtml() {
  return `<div class="citybox">
    <button type="button" class="spot vanak" data-clinic="vanak">ونک</button>
    <button type="button" class="spot tajrish" data-clinic="tajrish">تجریش</button>
    <button type="button" class="spot saadat" data-clinic="saadat">سعادت‌آباد</button>
  </div>
  <div class="map-pins">${CLINICS.map(
    (row, index) => `<article class="pin" id="clinic-${["vanak", "tajrish", "saadat"][index]}"><div><b>${esc(row[0])}</b><p class="muted">${esc(row[1])}</p></div><span class="pill">${esc(row[2])}</span></article>`,
  ).join("")}</div>
  <p class="note">${esc(tr(["نقشه شماتیک است و کاشی بیرونی ندارد. پیشنهاد تأیید نشده.", "Schematic map, no outside tiles. The suggestion is not confirmed.", "خريطة تخطيطية بلا بلاط خارجي. الاقتراح غير مؤكد."]))}</p>`;
}

function phone(screen, body) {
  const tabs = [
    ["services", tr(["خدمت", "Care", "خدمة"])],
    ["assistant", tr(["گفتگو", "Chat", "دردشة"])],
    ["activity", tr(["فعالیت", "Move", "نشاط"])],
    ["patient-app", tr(["من", "Me", "أنا"])],
  ];
  const dock = tabs.map(([id, label]) => `<a class="${screen === id ? "on" : ""}" href="#/b/${id}">${esc(label)}</a>`).join("");
  return `<div class="phone">${body}<nav class="dock">${dock}</nav></div>`;
}

function homeView() {
  const groups = ["phone", "desk", "clinical"];
  const labels = { phone: ui("phone"), desk: ui("desk"), clinical: ui("clinical") };
  const blocks = groups
    .map((group) => {
      const cards = BOARDS.filter((item) => item[1] === group)
        .map((item) => `<a class="card" href="#/b/${item[0]}"><b>${esc(boardName(item))}</b><p class="muted">${esc(ui("open"))}</p></a>`)
        .join("");
      return `<h2>${esc(labels[group])}</h2><div class="cards">${cards}</div>`;
    })
    .join("");
  const people = PROFILES.map(
    (item) => `<a class="card person" href="#/p/${item[0]}"><img src="avatars/${item[0]}.jpg" alt="" /><span><b>${esc(nameOf(item))}</b><span class="muted">${esc(item[4])}</span></span></a>`,
  ).join("");
  return `<div class="top"><div><h1>${esc(ui("studio"))}</h1><p class="lede">${esc(ui("sample"))}</p></div></div>
    <section class="stats">
      <article class="card"><span class="muted">${esc(ui("profiles"))}</span><b>16</b></article>
      <article class="card"><span class="muted">${esc(ui("phone"))}</span><b>6</b></article>
      <article class="card"><span class="muted">${esc(ui("desk"))}</span><b>13</b></article>
      <article class="card"><span class="muted">${esc(ui("clinical"))}</span><b>5</b></article>
    </section>
    ${blocks}
    <h2>${esc(ui("profiles"))}</h2>
    <div class="cards">${people}</div>`;
}

function sectionView(item, section) {
  const id = item[0];
  const who = item[4];
  if (section === "cases" || section === "search") {
    const rows = id === "patient" ? CASES.filter((row) => row[1] === "سارا نیک‌فرجام") : CASES;
    return `<div class="row"><input class="search" id="q" placeholder="${esc(ui("search"))}" /></div>${table(rows)}<p id="found" class="muted"></p>`;
  }
  if (section === "daylist") {
    return `<div class="timeline" id="daylist">${DAY.map(
      (row, index) => `<article class="card"><b>${esc(row[0])}</b> ${esc(row[1])}<p class="muted">${esc(row[2])}</p><button type="button" class="pill" data-arrive="${index}">${esc(row[3])}</button></article>`,
    ).join("")}</div>`;
  }
  if (section === "tasks") {
    const cols = [
      ["open", tr(["باز", "Open", "مفتوح"]), "تماس با سارا نیک‌فرجام"],
      ["late", tr(["عقب‌افتاده", "Late", "متأخر"]), "هماهنگی ونک"],
      ["done", tr(["انجام‌شده", "Done", "منجز"]), "یادداشت نمونه"],
    ];
    return `<div class="kanban" id="kanban">${cols
      .map(([key, title, body]) => `<section class="col" data-col="${key}"><h2>${esc(title)}</h2><article class="card" id="task-${key}">${esc(body)}<div><button type="button" class="ghost" data-move="${key}">${esc(tr(["جابه‌جایی", "Move", "نقل"]))}</button></div></article></section>`)
      .join("")}</div>`;
  }
  if (section === "support") {
    return `<div class="split"><div class="card"><p><b>${esc(tr(["زمان مراجعه", "Visit time", "وقت الزيارة"]))}</b></p><p class="muted">${esc(who)}</p></div>
      <form class="card" id="msg-form"><textarea name="text" placeholder="${esc(ui("send"))}"></textarea><button class="primary" type="submit">${esc(ui("send"))}</button><ul id="msgs"></ul></form></div>`;
  }
  if (section === "home" || section === "network") return mapHtml();
  if (section === "calendar") return calendarHtml();
  if (section === "reviews") {
    return `<div class="card"><p><b>RD-1405-014</b> ${esc("سارا نیک‌فرجام")}</p>
      <p class="muted">${esc(tr(["در انتظار بررسی. امضا منتشر نمی‌شود.", "Waiting. Nothing is signed or published.", "بانتظار المراجعة. لا يُنشر توقيع."]))}</p>
      <div class="row"><a class="chip" href="#/b/chart">${esc(tr(SECTIONS.chart))}</a><a class="chip" href="#/b/imaging">${esc(tr(SECTIONS.imaging))}</a></div>
      <button class="primary" type="button" id="draft">${esc(ui("save"))}</button><p id="draft-note"></p></div>`;
  }
  if (section === "ledger") {
    return `<div class="card"><table>
      <tr data-amount="12000000"><td>${esc(tr(["ویزیت نمونه", "Sample visit", "زيارة نموذجية"]))}</td><td>12,000,000 IRR</td></tr>
      <tr data-amount="18000000"><td>${esc(tr(["منزل، نمونه", "Home visit, sample", "منزل، نموذج"]))}</td><td>18,000,000 IRR</td></tr>
    </table><p id="sum"><b>30,000,000 IRR</b></p></div>
    <p class="note">${esc(tr(["بدون تبدیل تومان و بدون پرداخت.", "No toman conversion and no payment.", "بلا تحويل تومان وبلا دفع."]))}</p>`;
  }
  if (section === "analytics" || section === "overview") {
    return `<section class="stats">
      <article class="card"><span class="muted">${esc(tr(["پرونده نمونه", "Sample records", "ملفات نموذجية"]))}</span><b>3</b></article>
      <article class="card"><span class="muted">${esc(tr(["نوبت امروز", "Today", "اليوم"]))}</span><b>3</b></article>
      <article class="card"><span class="muted">${esc(tr(["درمانگاه", "Clinics", "عيادات"]))}</span><b>3</b></article>
      <article class="card"><span class="muted">IRR</span><b>0</b></article>
    </section><div class="card"><div class="bars"><i class="b75"></i><i class="b45"></i><i class="b60"></i><i class="b30"></i><i class="b90"></i></div></div>${id === "owner" || id === "coordinator" ? calendarHtml() : ""}`;
  }
  if (section === "policies") {
    return `<article class="card"><h2>${esc(tr(["حریم پیش‌نمایش", "Preview privacy", "خصوصية المعاينة"]))}</h2><p class="muted">${esc(tr(["پیش‌نویس نمونه ۱۴۰۵", "Sample draft 1405", "مسودة نموذجية"]))}</p></article>`;
  }
  if (section === "launch" || section === "diagnostics" || section === "integrations" || section === "deliveries") {
    const rows = [
      [tr(["تقویم ۱۴۰۵", "Calendar 1405", "تقويم ١٤٠٥"]), tr(["تعطیل رسمی محلی", "Local official holidays", "عطل رسمية محلية"])],
      [tr(["نقشه", "Map", "الخريطة"]), tr(["شماتیک، بدون کلید", "Schematic, no key", "تخطيطية، بلا مفتاح"])],
      [tr(["کلید عبور", "Passkey", "مفتاح المرور"]), tr(["همین مرورگر", "This browser", "هذا المتصفح"])],
      [tr(["صف پیام", "Message queue", "طابور الرسائل"]), tr(["بدون متن محرمانه", "No secret body", "بلا نص سري"])],
    ];
    return `<div class="card">${rows.map((row) => `<div class="check"><span>${esc(row[0])}</span><span class="pill ok">${esc(row[1])}</span></div>`).join("")}</div>`;
  }
  if (section === "admins") {
    return `<div class="cards">${PROFILES.map(
      (person) => `<a class="card person" href="#/p/${person[0]}"><img src="avatars/${person[0]}.jpg" alt="" /><span><b>${esc(person[4])}</b><span class="muted">${esc(nameOf(person))}</span></span></a>`,
    ).join("")}</div>`;
  }
  if (section === "profile") {
    return `<form class="card" id="key-form"><h2>${esc(ui("define"))}</h2><input class="search" name="label" placeholder="${esc(tr(["نام کلید", "Key name", "اسم المفتاح"]))}" /><div class="row"><button class="primary" type="submit">${esc(ui("define"))}</button></div><ul id="keys"></ul></form>`;
  }
  if (section === "chart") return chartHtml();
  if (section === "imaging") return opgHtml();
  return `<div class="card"><p>${esc(who)}</p><p class="muted">${esc(ui("sample"))}</p></div>`;
}

function opgHtml() {
  return `<div class="split">
    <div class="opg br2" id="opg">
      <img src="media/radiograph.jpg" alt="" />
      <button type="button" class="mark a on" data-mark="a"></button>
      <button type="button" class="mark b" data-mark="b"></button>
      <button type="button" class="mark c" data-mark="c"></button>
    </div>
    <div class="card">
      <h2>${esc(tr(["اوپی‌جی آموزشی", "Teaching OPG", "أشعة تعليمية"]))}</h2>
      <p class="note">${esc(tr(["تشخیص نیست و درصد سلامت ندارد.", "Not a reading and not a health score.", "ليست قراءة وليست درجة صحة."]))}</p>
      <div class="row">
        <button type="button" class="chip" data-br="br1">${esc(tr(["تیره‌تر", "Darker", "أغمق"]))}</button>
        <button type="button" class="chip on" data-br="br2">${esc(tr(["معمول", "Normal", "عادي"]))}</button>
        <button type="button" class="chip" data-br="br3">${esc(tr(["روشن‌تر", "Brighter", "أفتح"]))}</button>
      </div>
      <div class="row">
        <button type="button" class="chip" data-zoom="z1">${esc(tr(["اندازه", "Fit", "ملاءمة"]))}</button>
        <button type="button" class="chip" data-zoom="z2">${esc(tr(["نزدیک", "Closer", "أقرب"]))}</button>
        <button type="button" class="chip" data-zoom="z3">${esc(tr(["نزدیک‌تر", "Closest", "الأقرب"]))}</button>
      </div>
      <div class="row">
        <button type="button" class="chip on" data-mark="a">${esc(tr(["نشان ۱", "Mark 1", "علامة ١"]))}</button>
        <button type="button" class="chip" data-mark="b">${esc(tr(["نشان ۲", "Mark 2", "علامة ٢"]))}</button>
        <button type="button" class="chip" data-mark="c">${esc(tr(["نشان ۳", "Mark 3", "علامة ٣"]))}</button>
      </div>
    </div>
  </div>`;
}

function boardView(id) {
  const board = boardBy(id);
  if (!board) return homeView();
  const title = esc(boardName(board));
  if (id === "services") {
    return phone(id, `<h1>${title}</h1><div class="person"><img src="avatars/dentist.jpg" alt="" /><div><b>${esc("سامان یزدانی")}</b><p class="muted">${esc(tr(["دندان‌پزشک نمونه", "Sample dentist", "طبيب نموذج"]))}</p></div></div>
      <div class="row">${["هماهنگی", "معاینه", "منزل", "تصویر"].map((item, index) => `<button type="button" class="chip ${index === 0 ? "on" : ""}" data-service="${esc(item)}">${esc(item)}</button>`).join("")}</div>
      <p id="service-note" class="muted">${esc(ui("sample"))}</p><a class="primary" href="#/p/patient">${esc(ui("open"))}</a>`);
  }
  if (id === "assistant") {
    return phone(id, `<h1>${title}</h1><div class="card" id="thread"><p class="muted">${esc(tr(["فقط هماهنگی وقت. تشخیص نمی‌دهد.", "Scheduling only. It does not diagnose.", "للتنسيق فقط. لا يشخص."]))}</p></div>
      <div class="row"><button type="button" class="chip" data-suggest="1">${esc(tr(["وقت ونک", "Vanak time", "وقت ونك"]))}</button><button type="button" class="chip" data-suggest="2">${esc(tr(["ویزیت منزل", "Home visit", "زيارة منزل"]))}</button></div>
      <form id="msg-form"><textarea name="text"></textarea><button class="primary" type="submit">${esc(ui("send"))}</button></form>`);
  }
  if (id === "activity") {
    return phone(id, `<h1>${title}</h1><img class="shot" src="media/orb.jpg" alt="" /><section class="stats"><article class="card"><b>۶۴۰۰</b><span class="muted">${esc(tr(["گام نمونه", "Sample steps", "خطوات نموذجية"]))}</span></article></section><div class="bars"><i class="b45"></i><i class="b75"></i><i class="b60"></i><i class="b30"></i><i class="b90"></i><i class="b45"></i><i class="b60"></i></div>`);
  }
  if (id === "notices") {
    return phone(id, `<h1>${title}</h1>${DAY.map((row) => `<article class="card"><b>${esc(row[0])}</b><p>${esc(row[2])}</p><button type="button" class="ghost" data-read="1">${esc(tr(["خواندم", "Read", "قرأت"]))}</button></article>`).join("")}`);
  }
  if (id === "smile" || id === "patient-app") {
    return phone(id, `<div class="person"><img src="avatars/patient.jpg" alt="" /><div><h1>${esc("سارا نیک‌فرجام")}</h1><p class="muted">${esc(ui("sample"))}</p></div></div>
      <div class="row"><a class="chip" href="#/p/patient/cases">${esc(tr(SECTIONS.cases))}</a><a class="chip" href="#/b/imaging">${esc(tr(SECTIONS.imaging))}</a><a class="chip" href="#/b/chart">${esc(tr(SECTIONS.chart))}</a></div>
      ${table([CASES[0]])}`);
  }
  if (id === "chart" || id === "trainer") {
    return `<div class="top"><h1>${title}</h1></div>${chartHtml()}<form class="card" id="note-form"><textarea name="text" placeholder="${esc(tr(["یادداشت مشاهده، نه تشخیص", "Observation note, not a diagnosis", "ملاحظة، ليست تشخيصاً"]))}"></textarea><button class="primary" type="submit">${esc(ui("save"))}</button><ul id="notes"></ul></form>`;
  }
  if (id === "imaging" || id === "scan") return `<div class="top"><h1>${title}</h1></div>${opgHtml()}`;
  if (id === "overview" || id === "doctor") {
    return `<div class="hero"><div><h1>${title}</h1><p>${esc(ui("sample"))}</p><div class="row"><a class="primary" href="#/b/chart">${esc(tr(SECTIONS.chart))}</a><a class="ghost" href="#/b/imaging">${esc(tr(SECTIONS.imaging))}</a></div></div><img src="media/still-life.jpg" alt="" /></div>
      <section class="stats"><article class="card"><b>۱۲۰/۸۰</b><span class="muted">${esc(tr(["فشار نمونه", "Sample pressure", "ضغط نموذجي"]))}</span></article><article class="card"><b>۷۲</b><span class="muted">${esc(tr(["نبض نمونه", "Sample pulse", "نبض نموذجي"]))}</span></article><article class="card"><b>۳۶٫۶</b><span class="muted">${esc(tr(["دما نمونه", "Sample temp", "حرارة نموذجية"]))}</span></article><article class="card"><b>۳</b><span class="muted">${esc(tr(["ویزیت", "Visits", "زيارات"]))}</span></article></section>`;
  }
  if (id === "desk" || id === "welcome" || id === "ops") {
    return `<div class="top"><div><h1>${title}</h1><p class="lede">${esc(ui("sample"))}</p></div><a class="primary" href="#/p/receptionist/daylist">${esc(ui("open"))}</a></div>${sectionView(profileBy("receptionist"), "daylist")}`;
  }
  if (id === "staff") {
    return `<h1>${title}</h1><input class="search" id="q" placeholder="${esc(ui("search"))}" /><div class="cards" id="people">${PROFILES.map((item) => `<a class="card person" data-q="${esc(item[4] + " " + nameOf(item))}" href="#/p/${item[0]}"><img src="avatars/${item[0]}.jpg" alt="" /><span><b>${esc(item[4])}</b><span class="muted">${esc(nameOf(item))}</span></span></a>`).join("")}</div>`;
  }
  if (id === "record") {
    return `<div class="person"><img src="avatars/patient.jpg" alt="" /><div><h1>${esc("سارا نیک‌فرجام")}</h1><p class="muted">RD-1405-014</p></div></div>${opgHtml()}${chartHtml()}`;
  }
  if (id === "crm" || id === "night") {
    return `<div class="ink"><h1>${title}</h1>${table(CASES)}<p class="muted">${esc(ui("sample"))}</p></div>`;
  }
  if (id === "metrics" || id === "trends" || id === "reports" || id === "insights") {
    return `<h1>${title}</h1><section class="stats"><article class="card"><b>۳</b><span class="muted">${esc(tr(["پرونده", "Records", "ملفات"]))}</span></article><article class="card"><b>۰</b><span class="muted">IRR</span></article></section><div class="card"><div class="bars"><i class="b60"></i><i class="b90"></i><i class="b45"></i><i class="b75"></i><i class="b30"></i><i class="b60"></i></div></div>`;
  }
  if (id === "dispense") {
    return `<h1>${title}</h1><div class="card" id="queue">
      <div class="check"><span>${esc(tr(["دهان‌شویه نمونه", "Sample rinse", "غسول نموذجي"]))}</span><button type="button" class="pill ok" data-step="0">${esc(tr(["آماده", "Ready", "جاهز"]))}</button></div>
      <div class="check"><span>${esc(tr(["ژل نمونه", "Sample gel", "جل نموذجي"]))}</span><button type="button" class="pill" data-step="1">${esc(tr(["در صف", "Queued", "في الطابور"]))}</button></div>
    </div><p class="note">${esc(tr(["نسخه واقعی نیست. وضعیت فقط روی همین صفحه عوض می‌شود.", "Not a real prescription. Status changes only on this page.", "ليست وصفة حقيقية. تتغير الحالة في هذه الصفحة فقط."]))}</p>`;
  }
  return homeView();
}

function profileView(id, section) {
  const item = profileBy(id);
  if (!item) return homeView();
  const current = item[5].includes(section) ? section : item[5][0];
  const tabs = item[5].map((key) => `<a class="chip ${key === current ? "on" : ""}" href="#/p/${id}/${key}">${esc(tr(SECTIONS[key]))}</a>`).join("");
  return `<div class="person"><img src="avatars/${id}.jpg" alt="" /><div><h1>${esc(nameOf(item))}</h1><p class="lede">${esc(item[4])} · ${esc(ui("sample"))}</p></div></div>
    <div class="tabs">${tabs}</div>${sectionView(item, current)}`;
}
