import { ModuleWorkbench } from "@/components/module-workbench";
import { NS_ICONS } from "@/lib/icon-map";

export default function Page() {
  return (
    <ModuleWorkbench
      config={{
        ns: "reports",
        newLabel: "export",
        stats: [
          { label: "مراجعات ماه", value: "۲۴", icon: NS_ICONS["BarChart3"], tone: "primary" },
          { label: "درآمد تحقق‌یافته", value: "۸", icon: NS_ICONS["Wallet"], tone: "emerald" },
          { label: "نرخ عدم حضور", value: "۵", icon: NS_ICONS["AlertTriangle"], tone: "rose" },
        ],
        listTitle: "شاخص‌ها",
        items: [
          { title: "مراجعات به تفکیک خدمت", subtitle: "۵۳۲ مراجعه · ۱۲٪ رشد نسبت به ماه قبل", badge: "visits", badgeVariant: "info", meta: "این ماه" },
          { title: "درآمد به تفکیک روش پرداخت", subtitle: "۴۸۵ م تومان · نقدی ۶۰٪ / آنلاین ۴۰٪", badge: "revenue", badgeVariant: "success", meta: "این ماه" },
          { title: "SLA پیگیری تخصصی", subtitle: "میانگین پاسخ ۴٫۲ ساعت · هدف ≤ ۶", badge: "followUpSla", badgeVariant: "warning", meta: "هفته اخیر" },
        ],
      }}
    />
  );
}
