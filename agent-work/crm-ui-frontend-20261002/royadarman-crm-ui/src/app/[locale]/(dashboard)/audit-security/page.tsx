import { ModuleWorkbench } from "@/components/module-workbench";
import { NS_ICONS } from "@/lib/icon-map";

export default function Page() {
  return (
    <ModuleWorkbench
      config={{
        ns: "audit",
        newLabel: "export",
        stats: [
          { label: "رویداد امروز", value: "۲۴", icon: NS_ICONS["ShieldCheck"], tone: "primary" },
          { label: "دسترسی ممتاز", value: "۸", icon: NS_ICONS["Lock"], tone: "violet" },
          { label: "هشدار امنیتی", value: "۵", icon: NS_ICONS["AlertTriangle"], tone: "rose" },
        ],
        listTitle: "رویدادها",
        items: [
          { title: "clinical_report.released", subtitle: "actor: dentist · OPG-1024 · outcome: success", badge: "event", badgeVariant: "success", meta: "۱۰:۱۲" },
          { title: "patient_record.privileged_view", subtitle: "actor: superadmin · scope: 30m · reason: تسویه حساب", badge: "privileged", badgeVariant: "warning", meta: "۰۹:۴۵" },
          { title: "login.failed_mfa", subtitle: "actor: unknown · device: Android · outcome: denied", badge: "security", badgeVariant: "destructive", meta: "۰۸:۵۹" },
        ],
      }}
    />
  );
}
