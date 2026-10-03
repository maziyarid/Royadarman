const PROFILES = [
  ["patient", "بیمار", "Patient", "مريض", "سارا نیک‌فرجام", ["overview", "cases", "support", "home", "profile"]],
  ["coordinator", "هماهنگ‌کننده", "Coordinator", "منسق", "کیان مرادی", ["overview", "cases", "tasks", "calendar", "support", "home", "search", "profile"]],
  ["clinician", "درمانگر", "Clinician", "معالج", "لیلا بهرامی", ["overview", "reviews", "cases", "profile"]],
  ["clinic-rep", "نماینده درمانگاه", "Clinic rep", "ممثل العيادة", "نرگس حدادی", ["overview", "home", "daylist", "profile"]],
  ["owner", "مالک", "Owner", "المالك", "بهرام کیانی", ["overview", "calendar", "analytics", "network", "policies", "launch", "deliveries", "admins", "profile"]],
  ["tech-admin", "مدیر فنی", "Tech admin", "المدير التقني", "آریا نعمتی", ["overview", "integrations", "launch", "deliveries", "admins", "diagnostics", "profile"]],
  ["receptionist", "پذیرش", "Reception", "الاستقبال", "هستی رضایی", ["daylist", "calendar", "profile"]],
  ["clinic-manager", "مدیر درمانگاه", "Clinic manager", "مدير العيادة", "کامران داوودی", ["overview", "daylist", "network", "profile"]],
  ["accountant", "حسابدار", "Accountant", "المحاسب", "مینا افشار", ["ledger", "profile"]],
  ["dentist", "دندان‌پزشک", "Dentist", "طبيب الأسنان", "سامان یزدانی", ["reviews", "chart", "imaging", "profile"]],
  ["supervisor", "ناظر", "Supervisor", "المشرف", "روژان کریمی", ["overview", "tasks", "profile"]],
  ["support", "پشتیبانی", "Support", "الدعم", "شیدا مرادی", ["support", "profile"]],
  ["developer", "توسعه‌دهنده", "Developer", "المطور", "پارسا نیکو", ["diagnostics", "launch", "deliveries", "profile"]],
  ["superadmin", "مدیر ارشد", "Superadmin", "المدير الأعلى", "هوشنگ صالحی", ["diagnostics", "admins", "launch", "profile"]],
  ["treatment-specialist", "متخصص هماهنگی", "Treatment specialist", "أخصائي التنسيق", "فرهاد اسدی", ["overview", "cases", "tasks", "profile"]],
  ["clinical-assistant", "دستیار بالینی", "Clinical assistant", "المساعد السريري", "یلدا صفری", ["reviews", "daylist", "profile"]],
];

const SECTIONS = {
  overview: ["خانه", "Home", "الرئيسية"],
  cases: ["پرونده", "Records", "الملفات"],
  support: ["پشتیبانی", "Support", "الدعم"],
  home: ["منزل", "Home visit", "زيارة منزلية"],
  profile: ["نمایه", "Profile", "الملف"],
  tasks: ["کارها", "Tasks", "المهام"],
  calendar: ["تقویم", "Calendar", "التقويم"],
  search: ["جستجو", "Search", "بحث"],
  reviews: ["بررسی", "Review queue", "المراجعة"],
  daylist: ["فهرست روز", "Day list", "قائمة اليوم"],
  analytics: ["گزارش", "Reports", "التقارير"],
  network: ["شبکه", "Network", "الشبكة"],
  policies: ["سیاست", "Policies", "السياسات"],
  launch: ["آمادگی", "Launch", "الإطلاق"],
  deliveries: ["ارسال", "Deliveries", "الإرسال"],
  admins: ["کاربران", "Users", "المستخدمون"],
  integrations: ["یکپارچگی", "Integrations", "التكامل"],
  diagnostics: ["تشخیص سامانه", "Diagnostics", "تشخيص النظام"],
  ledger: ["دفتر", "Ledger", "الدفتر"],
  chart: ["چارت دندان", "Tooth chart", "مخطط الأسنان"],
  imaging: ["اوپی‌جی", "OPG", "الأشعة البانورامية"],
};

const BOARDS = [
  ["services", "phone", "خدمت ترجیحی", "Preferred service", "الخدمة المفضلة"],
  ["assistant", "phone", "دستیار", "Assistant", "المساعد"],
  ["activity", "phone", "فعالیت", "Activity", "النشاط"],
  ["notices", "phone", "اعلان‌ها", "Notices", "التنبيهات"],
  ["smile", "phone", "خانه لبخند", "Smile home", "الرئيسية"],
  ["patient-app", "phone", "اپ بیمار", "Patient app", "تطبيق المريض"],
  ["overview", "desk", "نمای بالینی", "Clinical overview", "نظرة سريرية"],
  ["desk", "desk", "میز درمانگاه", "Clinic desk", "مكتب العيادة"],
  ["welcome", "desk", "خوش‌آمد", "Welcome", "الترحيب"],
  ["staff", "desk", "تیم درمان", "Care team", "فريق الرعاية"],
  ["doctor", "desk", "میز پزشک", "Clinician desk", "مكتب الطبيب"],
  ["record", "desk", "پرونده", "Record", "الملف"],
  ["ops", "desk", "هماهنگی ویزیت", "Visit board", "لوحة الزيارات"],
  ["crm", "desk", "تعامل‌ها", "Interactions", "التفاعلات"],
  ["metrics", "desk", "شاخص درمانگاه", "Clinic metrics", "مؤشرات العيادة"],
  ["trends", "desk", "روندها", "Trends", "الاتجاهات"],
  ["dispense", "desk", "صف تحویل", "Dispense queue", "طابور الصرف"],
  ["reports", "desk", "گزارش‌ها", "Reports", "التقارير"],
  ["insights", "desk", "بینش", "Insights", "الرؤى"],
  ["chart", "clinical", "چارت دندان", "Tooth chart", "مخطط الأسنان"],
  ["trainer", "clinical", "ثبت مشاهده", "Observation pad", "سجل الملاحظة"],
  ["scan", "clinical", "بازبینی تصویر", "Image review", "مراجعة الصورة"],
  ["imaging", "clinical", "اوپی‌جی", "OPG", "الأشعة البانورامية"],
  ["night", "clinical", "میز تیره", "Night desk", "المكتب الليلي"],
];

const COPY = {
  fa: { studio: "استودیو", profiles: "نمایه‌ها", phone: "اپ موبایل", desk: "میز کار", clinical: "بالینی", sample: "نمونه است. تشخیص، نسخه و پرداخت واقعی نیست.", search: "جستجو", open: "باز کردن", menu: "فهرست", save: "ذخیره پیش‌نویس", send: "ارسال", define: "تعریف کلید", filter: "صافی" },
  en: { studio: "Studio", profiles: "Profiles", phone: "Mobile apps", desk: "Desks", clinical: "Clinical", sample: "Sample only. Not a diagnosis, prescription, or payment.", search: "Search", open: "Open", menu: "Menu", save: "Save draft", send: "Send", define: "Define key", filter: "Filter" },
  ar: { studio: "الاستوديو", profiles: "الملفات", phone: "تطبيقات", desk: "المكاتب", clinical: "سريري", sample: "نموذج فقط. ليس تشخيصاً أو وصفة أو دفعاً.", search: "بحث", open: "فتح", menu: "القائمة", save: "حفظ المسودة", send: "إرسال", define: "تعريف المفتاح", filter: "تصفية" },
};

const UPPER = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];
const LOWER = [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38];
const CASES = [
  ["RD-1405-014", "سارا نیک‌فرجام", "هماهنگی ویزیت", "در حال هماهنگی"],
  ["RD-1405-021", "آرمان قاسمی", "پیگیری تماس", "در صف تماس"],
  ["RD-1405-033", "نرگس حدادی", "خدمت در منزل", "درخواست ثبت شد"],
];
const DAY = [
  ["۰۹:۳۰", "سارا نیک‌فرجام", "معاینه", "حاضر"],
  ["۱۱:۰۰", "آرمان قاسمی", "جرم‌گیری", "نرسیده"],
  ["۱۶:۱۵", "نرگس حدادی", "ویزیت در منزل", "پیشنهاد"],
];
const CLINICS = [
  ["ونک", "۴ نفر", "۲ نوبت باز"],
  ["تجریش", "۳ نفر", "۱ نوبت باز"],
  ["سعادت‌آباد", "۲ نفر", "نوبت باز ندارد"],
];
