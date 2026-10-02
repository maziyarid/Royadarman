import { ModuleWorkbench } from "@/components/module-workbench";
import { NS_ICONS } from "@/lib/icon-map";

export default function Page() {
  return (
    <ModuleWorkbench
      config={{
        ns: "lab",
        newLabel: "orderLab",
        stats: [
          { label: "سفارش‌های فعال", value: "۲۴", icon: NS_ICONS["TestTube2"], tone: "primary" },
          { label: "در انتظار آزمایشگاه", value: "۸", icon: NS_ICONS["Clock"], tone: "amber" },
          { label: "آماده تحویل", value: "۵", icon: NS_ICONS["CheckCircle2"], tone: "emerald" },
        ],
        listTitle: "سفارش‌ها",
        items: [
          { title: "LAB-8821 · روکش PFM", subtitle: "سارا محمدی · آزمایشگاه دیانت", badge: "pendingOrder", badgeVariant: "warning", meta: "۲ روز" },
          { title: "LAB-8822 · پروسه کامل", subtitle: "حسین کریمی · آزمایشگاه لبخند", badge: "results", badgeVariant: "success", meta: "آماده" },
          { title: "LAB-8823 · ریتینر", subtitle: "مریم احمدی · آزمایشگاه دیانت", badge: "pendingOrder", badgeVariant: "warning", meta: "۴ روز" },
        ],
      }}
    />
  );
}
