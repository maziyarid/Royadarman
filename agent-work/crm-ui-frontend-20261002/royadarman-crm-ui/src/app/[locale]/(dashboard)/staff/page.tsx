import { ModuleWorkbench } from "@/components/module-workbench";
import { NS_ICONS } from "@/lib/icon-map";

export default function Page() {
  return (
    <ModuleWorkbench
      config={{
        ns: "staff",
        newLabel: "onboarding",
        stats: [
          { label: "شیفت امروز", value: "۲۴", icon: NS_ICONS["UserCog"], tone: "primary" },
          { label: "در مرخصی", value: "۸", icon: NS_ICONS["CalendarDays"], tone: "sky" },
          { label: "آموزش معلق", value: "۵", icon: NS_ICONS["AlertTriangle"], tone: "amber" },
        ],
        listTitle: "برنامه شیفت",
        items: [
          { title: "پریسا نوری — پذیرش", subtitle: "شیفت صبح · شعبه سعادت‌آباد", badge: "roster", badgeVariant: "success", meta: "۰۸:۰۰–۱۴:۰۰" },
          { title: "امیر شریفی — دستیار دندانپزشک", subtitle: "شیفت عصر · کلینیک مرکزی", badge: "training", badgeVariant: "warning", meta: "اصلاحیه مورد نیاز" },
          { title: "لیلا کاظمی — حسابدار", subtitle: "مرخصی استعلاجی", badge: "leave", badgeVariant: "info", meta: "تا ۱۴۰۴/۰۷/۱۵" },
        ],
      }}
    />
  );
}
