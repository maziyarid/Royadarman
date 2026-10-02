import { ModuleWorkbench } from "@/components/module-workbench";
import { NS_ICONS } from "@/lib/icon-map";

export default function Page() {
  return (
    <ModuleWorkbench
      config={{
        ns: "invoices",
        newLabel: "newInvoice",
        stats: [
          { label: "پرداخت‌شده", value: "۲۴", icon: NS_ICONS["Wallet"], tone: "emerald" },
          { label: "پرداخت‌نشده", value: "۸", icon: NS_ICONS["AlertTriangle"], tone: "rose" },
          { label: "اقساط فعال", value: "۵", icon: NS_ICONS["CreditCard"], tone: "primary" },
        ],
        listTitle: "مطالبات",
        items: [
          { title: "INV-2071 · سارا محمدی", subtitle: "۵٬۵۰۰٬۰۰۰ تومان · نقدی", badge: "paid", badgeVariant: "success", meta: "۱۴۰۴/۰۷/۱۰" },
          { title: "INV-2072 · علی رضایی", subtitle: "۴۸٬۰۰۰٬۰۰۰ تومان · ۶ قسط", badge: "instalments", badgeVariant: "info", meta: "قسط ۲ از ۶" },
          { title: "INV-2073 · مریم احمدی", subtitle: "۹٬۲۰۰٬۰۰۰ تومان · چک", badge: "cheques", badgeVariant: "warning", meta: "سررسید ۱۴۰۴/۰۸/۰۱" },
          { title: "INV-2074 · حسین کریمی", subtitle: "۱٬۸۰۰٬۰۰۰ تومان · آنلاین", badge: "unpaid", badgeVariant: "destructive", meta: "سررسید گذشته" },
        ],
      }}
    />
  );
}
