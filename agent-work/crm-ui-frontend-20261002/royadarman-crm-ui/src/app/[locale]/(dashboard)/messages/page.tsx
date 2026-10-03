import { ModuleWorkbench } from "@/components/module-workbench";
import { NS_ICONS } from "@/lib/icon-map";

export default function Page() {
  return (
    <ModuleWorkbench
      config={{
        ns: "messages",
        newLabel: "compose",
        stats: [
          { label: "خوانده‌نشده", value: "۲۴", icon: NS_ICONS["MessageSquare"], tone: "primary" },
          { label: "در انتظار پاسخ", value: "۸", icon: NS_ICONS["Clock"], tone: "amber" },
          { label: "اولویت دار", value: "۵", icon: NS_ICONS["AlertTriangle"], tone: "rose" },
        ],
        listTitle: "صندوق ورودی",
        items: [
          { title: "سارا محمدی", subtitle: "سوال درباره بعد از پرکردن دندان", badge: "unread", badgeVariant: "info", meta: "۱۰:۲۴" },
          { title: "علی رضایی", subtitle: "درخواست تایید قسط سوم", badge: "waitingReply", badgeVariant: "warning", meta: "۰۹:۱۵" },
          { title: "گروه پشتیبانی", subtitle: "ارجاع: تیکت ۱۲۳۴ به سوپروایزر", badge: "priority", badgeVariant: "destructive", meta: "دیشب" },
        ],
      }}
    />
  );
}
