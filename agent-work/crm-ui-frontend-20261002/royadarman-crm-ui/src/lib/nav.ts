import {
  LayoutDashboard, Stethoscope, CalendarDays, Users, FileImage, ClipboardList,
  CreditCard, MessageSquare, BarChart3, Settings, Boxes, Video, HeartPulse,
  Building2, UserCog, Bell, ShieldCheck, TestTube2, Sparkles,
} from "lucide-react";

export interface NavItem {
  href: string;
  labelKey: string;
  icon: React.ComponentType<{ className?: string }>;
}

export const navSections: { titleKey: string; items: NavItem[] }[] = [
  {
    titleKey: "nav.overview",
    items: [
      { href: "/dashboard", labelKey: "nav.dashboard", icon: LayoutDashboard },
      { href: "/patients", labelKey: "nav.patients", icon: Users },
      { href: "/appointments", labelKey: "nav.appointments", icon: CalendarDays },
    ],
  },
  {
    titleKey: "nav.clinical",
    items: [
      { href: "/dental-chart", labelKey: "nav.dentalChart", icon: HeartPulse },
      { href: "/opg-review", labelKey: "nav.opgReview", icon: FileImage },
      { href: "/treatments", labelKey: "nav.treatments", icon: Stethoscope },
      { href: "/lab", labelKey: "nav.lab", icon: TestTube2 },
    ],
  },
  {
    titleKey: "nav.crm",
    items: [
      { href: "/leads", labelKey: "nav.leads", icon: Sparkles },
      { href: "/invoices", labelKey: "nav.invoices", icon: CreditCard },
      { href: "/messages", labelKey: "nav.messages", icon: MessageSquare },
      { href: "/teleconsult", labelKey: "nav.teleconsult", icon: Video },
    ],
  },
  {
    titleKey: "nav.operations",
    items: [
      { href: "/staff", labelKey: "nav.staff", icon: UserCog },
      { href: "/inventory", labelKey: "nav.inventory", icon: Boxes },
      { href: "/clinics", labelKey: "nav.clinics", icon: Building2 },
      { href: "/reports", labelKey: "nav.reports", icon: BarChart3 },
      { href: "/tasks", labelKey: "nav.tasks", icon: ClipboardList },
    ],
  },
  {
    titleKey: "nav.system",
    items: [
      { href: "/notifications", labelKey: "nav.notifications", icon: Bell },
      { href: "/audit-security", labelKey: "nav.auditSecurity", icon: ShieldCheck },
      { href: "/settings", labelKey: "nav.settings", icon: Settings },
    ],
  },
];

export const moduleCount = navSections.reduce((n, s) => n + s.items.length, 0);
