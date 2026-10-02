import { ModuleWorkbench } from "@/components/module-workbench";
import { NS_ICONS } from "@/lib/icon-map";

export default function Page() {
  return (
    <ModuleWorkbench
      config={{
        ns: "clinics",
        newLabel: "verified",
        stats: [
          { label: "شعبه فعال", value: "۲۴", icon: NS_ICONS["Building2"], tone: "primary" },
          { label: "ظرفیت امروز", value: "۸", icon: NS_ICONS["Activity"], tone: "emerald" },
          { label: "خدمات ثبت‌شده", value: "۵", icon: NS_ICONS["Stethoscope"], tone: "sky" },
        ],
        listTitle: "شعبه‌ها",
        items: [
          { title: "کلینیک مرکزی — سعادت‌آباد", subtitle: "۳ اتاق · ۵ دندانپزشک · ظرفیت ۸۵٪", badge: "capacity", badgeVariant: "success", meta: "۱۲ خدمت" },
          { title: "شعبه شهرک غرب", subtitle: "۲ اتاق · ۳ دندانپزشک · ظرفیت ۶۲٪", badge: "capacity", badgeVariant: "info", meta: "۸ خدمت" },
          { title: "شعبه پونک", subtitle: "۲ اتاق · ۲ دندانپزشک · ظرفیت ۴۰٪", badge: "capacity", badgeVariant: "warning", meta: "۶ خدمت" },
        ],
      }}
    />
  );
}
