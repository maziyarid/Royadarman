import { ModuleWorkbench } from "@/components/module-workbench";
import { NS_ICONS } from "@/lib/icon-map";

export default function Page() {
  return (
    <ModuleWorkbench
      config={{
        ns: "teleconsult",
        newLabel: "startConsult",
        stats: [
          { label: "جلسات امروز", value: "۲۴", icon: NS_ICONS["Video"], tone: "primary" },
          { label: "در اتاق انتظار", value: "۸", icon: NS_ICONS["Clock"], tone: "amber" },
          { label: "ضبط با رضایت", value: "۵", icon: NS_ICONS["CheckCircle2"], tone: "emerald" },
        ],
        listTitle: "جلسات",
        items: [
          { title: "۱۶:۰۰ · مشاوره پیش از ایمپلنت", subtitle: "دکتر احمدی · علی رضایی · رضایت ثبت شد", badge: "waitingRoom", badgeVariant: "warning", meta: "شروع در ۱۵ دقیقه" },
          { title: "۱۷:۳۰ · پیگیری بعد از عصب‌کشی", subtitle: "دکتر احمدی · مریم احمدی", badge: "scheduled", badgeVariant: "info", meta: "امروز" },
          { title: "۱۹:۰۰ · مشاوره ارتودانس", subtitle: "دکتر کریمی · زهرا م.", badge: "scheduled", badgeVariant: "info", meta: "امروز" },
        ],
      }}
    />
  );
}
