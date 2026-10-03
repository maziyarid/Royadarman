import { ModuleWorkbench } from "@/components/module-workbench";
import { NS_ICONS } from "@/lib/icon-map";

export default function Page() {
  return (
    <ModuleWorkbench
      config={{
        ns: "treatments",
        newLabel: "plan",
        stats: [
          { label: "برنامه‌های فعال", value: "۲۴", icon: NS_ICONS["Stethoscope"], tone: "primary" },
          { label: "در انتظار رضایت", value: "۸", icon: NS_ICONS["Clock"], tone: "amber" },
          { label: "پایان‌یافته", value: "۵", icon: NS_ICONS["CheckCircle2"], tone: "emerald" },
        ],
        listTitle: "برنامه‌های درمانی",
        items: [
          { title: "سارا محمدی — فاز ۲", subtitle: "روکش + ترمیم · جلسه بعدی ۱۴۰۴/۰۷/۲۰", badge: "consent", badgeVariant: "info", meta: "۳ جلسه" },
          { title: "علی رضایی — ایمپلنت", subtitle: "مرحله جراحی · پیگیری بهبود هفته ۲", badge: "inProgress", badgeVariant: "success", meta: "۵ جلسه" },
          { title: "مریم احمدی — ارتودانس", subtitle: "ریتینر + کنترل ماهانه", badge: "draft", badgeVariant: "secondary", meta: "۱۲ جلسه" },
        ],
      }}
    />
  );
}
