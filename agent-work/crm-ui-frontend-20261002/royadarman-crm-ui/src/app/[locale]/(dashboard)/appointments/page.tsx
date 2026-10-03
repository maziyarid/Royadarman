import { ModuleWorkbench } from "@/components/module-workbench";
import { NS_ICONS } from "@/lib/icon-map";

export default function Page() {
  return (
    <ModuleWorkbench
      config={{
        ns: "appointments",
        newLabel: "newAppointment",
        stats: [
          { label: "نوبت‌ها", value: "۲۴", icon: NS_ICONS["CalendarDays"], tone: "primary" },
          { label: "تاییدشده", value: "۸", icon: NS_ICONS["CheckCircle2"], tone: "emerald" },
          { label: "در انتظار", value: "۵", icon: NS_ICONS["Clock"], tone: "amber" },
        ],
        listTitle: "برنامه امروز",
        items: [
          { title: "۰۹:۰۰ · سارا محمدی", subtitle: "پرکردن دندان · کلینیک مرکزی", badge: "confirmed", badgeVariant: "success", meta: "اتاق ۲" },
          { title: "۰۹:۳۰ · علی رضایی", subtitle: "جرم‌گیری · شعبه سعادت‌آباد", badge: "confirmed", badgeVariant: "success", meta: "اتاق ۱" },
          { title: "۱۰:۰۰ · مریم احمدی", subtitle: "عصب‌کشی · کلینیک مرکزی", badge: "pending", badgeVariant: "warning", meta: "اتاق ۳" },
          { title: "۱۱:۰۰ · حسین کریمی", subtitle: "معاینه · شعبه شهرک غرب", badge: "noShow", badgeVariant: "destructive", meta: "اتاق ۱" },
        ],
      }}
    />
  );
}
