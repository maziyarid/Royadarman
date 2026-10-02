import { ModuleWorkbench } from "@/components/module-workbench";
import { NS_ICONS } from "@/lib/icon-map";

export default function Page() {
  return (
    <ModuleWorkbench
      config={{
        ns: "leads",
        newLabel: "newLead",
        stats: [
          { label: "سرنخ جدید", value: "۲۴", icon: NS_ICONS["Sparkles"], tone: "primary" },
          { label: "تبدیل‌شده این ماه", value: "۸", icon: NS_ICONS["CheckCircle2"], tone: "emerald" },
          { label: "پیگیری معوق", value: "۵", icon: NS_ICONS["AlertTriangle"], tone: "rose" },
        ],
        listTitle: "قیف تبدیل",
        items: [
          { title: "سارا ک. — ایمپلنت", subtitle: "از اینستاگرام · پیگیری تماس اول", badge: "high", badgeVariant: "destructive", meta: "فردا" },
          { title: "محمد ب. — ارتودانس", subtitle: "از جستجوی گوگل · ارسال برآورد", badge: "medium", badgeVariant: "warning", meta: "۳ روز" },
          { title: "الهام ر. — جرم‌گیری", subtitle: "معرفی بیمار · نوبت رزرو شد", badge: "converted", badgeVariant: "success", meta: "—" },
        ],
      }}
    />
  );
}
