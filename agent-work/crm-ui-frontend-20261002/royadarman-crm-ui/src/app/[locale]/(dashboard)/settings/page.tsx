import { ModuleWorkbench } from "@/components/module-workbench";
import { NS_ICONS } from "@/lib/icon-map";

export default function Page() {
  return (
    <ModuleWorkbench
      config={{
        ns: "settings",
        newLabel: "save",
        stats: [
          { label: "پروفایل", value: "۲۴", icon: NS_ICONS["UserCog"], tone: "primary" },
          { label: "امنیت", value: "۸", icon: NS_ICONS["ShieldCheck"], tone: "emerald" },
          { label: "یکپارچه‌سازی‌ها", value: "۵", icon: NS_ICONS["Settings"], tone: "sky" },
        ],
        listTitle: "پیکربندی",
        items: [
          { title: "ورود دومرحله‌ای", subtitle: "اپلیکیشن احراز هویت فعال است", badge: "mfa", badgeVariant: "success", meta: "فعال" },
          { title: "نشست‌های فعال", subtitle: "۳ دستگاه · آخرین فعالیت تهران", badge: "sessions", badgeVariant: "info", meta: "۱۰ دقیقه پیش" },
          { title: "منطقه زمانی", subtitle: "Asia/Tehran · تقویم جلالی", badge: "timezone", badgeVariant: "secondary", meta: "—" },
          { title: "پیکربندی پیامک", subtitle: "الگوی جدید تایید نشده", badge: "integrations", badgeVariant: "warning", meta: "بررسی لازم" },
        ],
      }}
    />
  );
}
