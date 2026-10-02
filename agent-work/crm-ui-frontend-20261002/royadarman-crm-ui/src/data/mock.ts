export const todayAppointments = [
  { id: "1", time: "۰۹:۰۰", patient: "سارا محمدی", procedure: "پرکردن دندان", status: "done" },
  { id: "2", time: "۰۹:۳۰", patient: "علی رضایی", procedure: "جرم‌گیری", status: "done" },
  { id: "3", time: "۱۰:۰۰", patient: "مریم احمدی", procedure: "عصب‌کشی", status: "inProgress" },
  { id: "4", time: "۱۰:۳۰", patient: "حسین کریمی", procedure: "کشیدن دندان عقل", status: "checkIn" },
  { id: "5", time: "۱۱:۰۰", patient: "نگار صادقی", procedure: "معاینه دوره‌ای", status: "checkIn" },
];

export const patientsList = [
  { name: "سارا محمدی", phone: "۰۹۱۲۳۴۵۶۷۸۹", lastVisit: "۱۴۰۴/۰۷/۱۲", condition: "در حال درمان", active: true },
  { name: "علی رضایی", phone: "۰۹۱۲۷۸۹۴۵۶۱", lastVisit: "۱۴۰۴/۰۷/۰۵", condition: "پایان درمان", active: true },
  { name: "مریم احمدی", phone: "۰۹۳۵۱۱۲۲۳۳۴", lastVisit: "۱۴۰۴/۰۶/۲۸", condition: "در حال درمان", active: true },
  { name: "حسین کریمی", phone: "۰۹۱۹۸۷۶۵۴۳۲", lastVisit: "۱۴۰۴/۰۷/۰۱", condition: "نیاز به پیگیری", active: true },
  { name: "نگار صادقی", phone: "۰۹۱۲۵۵۵۴۴۴۳", lastVisit: "۱۴۰۴/۰۵/۱۵", condition: "معاینه دوره‌ای", active: false },
];

export const dentalChartTeeth = [
  "unknown","healthy","healthy","caries","healthy","restored","healthy","healthy","caries","healthy",
  "missing","healthy","healthy","implant","healthy","caries","healthy","healthy","restored","healthy",
  "healthy","caries","healthy","missing","healthy","healthy","implant","healthy","caries","healthy",
  "healthy","unknown",
];
