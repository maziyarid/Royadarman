import { ModuleWorkbench } from "@/components/module-workbench";
import { NS_ICONS } from "@/lib/icon-map";

export default function Page() {
  return (
    <ModuleWorkbench
      config={{
        ns: "tasks",
        newLabel: "newTask",
        stats: [
          { label: "کارهای من", value: "۲۴", icon: NS_ICONS["ClipboardList"], tone: "primary" },
          { label: "معوق", value: "۸", icon: NS_ICONS["AlertTriangle"], tone: "rose" },
          { label: "انجام‌شده امروز", value: "۵", icon: NS_ICONS["CheckCircle2"], tone: "emerald" },
        ],
        listTitle: "کارهای من",
        items: [
          { title: "تماس پیگیری با سارا ک.", subtitle: "موعد امروز ۱۴:۰۰", badge: "overdue", badgeVariant: "destructive", meta: "معوق ۲ ساعت" },
          { title: "بازبینی OPG-1025", subtitle: "امضا و انتشار برای بیمار", badge: "today", badgeVariant: "info", meta: "تا ۱۸:۰۰" },
          { title: "بررسی سفارش آزمایشگاه LAB-8822", subtitle: "تایید کیفیت", badge: "completed", badgeVariant: "success", meta: "انجام شد" },
        ],
      }}
    />
  );
}
