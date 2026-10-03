import { ModuleWorkbench } from "@/components/module-workbench";
import { NS_ICONS } from "@/lib/icon-map";

export default function Page() {
  return (
    <ModuleWorkbench
      config={{
        ns: "inventory",
        newLabel: "stock",
        stats: [
          { label: "اقلام زیر حد", value: "۲۴", icon: NS_ICONS["Boxes"], tone: "rose" },
          { label: "نزدیک انقضا", value: "۸", icon: NS_ICONS["Clock"], tone: "amber" },
          { label: "سفارش‌های تامین", value: "۵", icon: NS_ICONS["Truck"], tone: "primary" },
        ],
        listTitle: "موجودی",
        items: [
          { title: "آمپول آنستزیک لیدو", subtitle: "موجودی ۴ ویدر · حداقل ۱۰", badge: "lowStock", badgeVariant: "destructive", meta: "سفارش فوری" },
          { title: "دستکش نیترایل سایز M", subtitle: "موجودی ۲۵ جعبه", badge: "ok", badgeVariant: "success", meta: "—" },
          { title: "کامپوزیت A2", subtitle: "انقضا ۱۴۰۴/۰۹/۰۱", badge: "nearExpiry", badgeVariant: "warning", meta: "استفاده اولویت‌دار" },
        ],
      }}
    />
  );
}
