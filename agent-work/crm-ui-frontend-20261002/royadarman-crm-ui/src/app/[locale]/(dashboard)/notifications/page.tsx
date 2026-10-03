import { ModuleWorkbench } from "@/components/module-workbench";
import { NS_ICONS } from "@/lib/icon-map";

export default function Page() {
  return (
    <ModuleWorkbench
      config={{
        ns: "notifications",
        newLabel: "preferences",
        stats: [
          { label: "خوانده‌نشده", value: "۲۴", icon: NS_ICONS["Bell"], tone: "primary" },
          { label: "اولویت دار", value: "۸", icon: NS_ICONS["AlertTriangle"], tone: "amber" },
          { label: "این هفته", value: "۵", icon: NS_ICONS["CalendarDays"], tone: "sky" },
        ],
        listTitle: "رویدادها",
        items: [
          { title: "گزارش OPG منتشر شد", subtitle: "OPG-1024 برای سارا محمدی آزاد شد", badge: "system", badgeVariant: "info", meta: "۱۰ دقیقه" },
          { title: "پرداخت دریافت شد", subtitle: "قسط ۲ فاکتور INV-2072 تایید شد", badge: "payment", badgeVariant: "success", meta: "۱ ساعت" },
          { title: "شیفت تغییر کرد", subtitle: "شیفت پذیرش پونک به عصر منتقل شد", badge: "schedule", badgeVariant: "warning", meta: "دیشب" },
        ],
      }}
    />
  );
}
