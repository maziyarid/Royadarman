import { getTranslations } from "next-intl/server";
import { PageHeader, Reveal } from "@/components/ui/page";
import { StatCard } from "@/components/ui/stat-card";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { users, fileImage, wallet, UserPlus, CalendarPlus, FilePlus2, ListChecks } from "@/lib/icons";
import { todayAppointments } from "@/data/mock";

export default async function DashboardPage() {
  const t = await getTranslations("dashboard");
  const tc = await getTranslations("common");

  return (
    <>
      <PageHeader title={t("title")} description={t("subtitle")}>
        <Button variant="gradient">{tc("new")} · {t("newPatient")}</Button>
      </PageHeader>

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <Reveal delay={0}>
          <StatCard label={t("todayPatients")} value="۲۴" delta="+۳" trend="up" icon={users} tone="primary" />
        </Reveal>
        <Reveal delay={0.05}>
          <StatCard label={t("newRequests")} value="۸" delta="+۲" trend="up" icon={ListChecks} tone="sky" />
        </Reveal>
        <Reveal delay={0.1}>
          <StatCard label={t("pendingReviews")} value="۵" delta="-۱" trend="down" icon={fileImage} tone="amber" />
        </Reveal>
        <Reveal delay={0.15}>
          <StatCard label={t("monthlyRevenue")} value="۴۸۵ م" delta="+۱۲٪" trend="up" icon={wallet} tone="emerald" />
        </Reveal>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <Reveal className="lg:col-span-2">
          <Card>
            <CardHeader className="flex-row items-center justify-between">
              <CardTitle>{t("todaySchedule")}</CardTitle>
              <Button variant="ghost" size="sm">{tc("viewAll")}</Button>
            </CardHeader>
            <CardContent className="space-y-3">
              {todayAppointments.map((a) => (
                <div
                  key={a.id}
                  className="flex items-center gap-4 rounded-xl border p-3 transition-colors hover:bg-muted/40"
                >
                  <div className="grid w-14 shrink-0 place-items-center rounded-lg bg-primary/10 py-1.5 text-primary">
                    <span className="text-sm font-bold">{a.time}</span>
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold">{a.patient}</p>
                    <p className="truncate text-xs text-muted-foreground">{a.procedure}</p>
                  </div>
                  <Badge variant={a.status === "done" ? "success" : a.status === "inProgress" ? "info" : "secondary"}>
                    {a.status === "done" ? t("done") : a.status === "inProgress" ? t("inProgress") : t("checkIn")}
                  </Badge>
                </div>
              ))}
            </CardContent>
          </Card>
        </Reveal>

        <Reveal delay={0.1}>
          <Card className="h-full">
            <CardHeader>
              <CardTitle>{t("quickActions")}</CardTitle>
            </CardHeader>
            <CardContent className="grid grid-cols-2 gap-3">
              {[
                { label: t("newPatient"), icon: UserPlus },
                { label: t("newAppointment"), icon: CalendarPlus },
                { label: t("newInvoice"), icon: FilePlus2 },
                { label: t("reviewQueue"), icon: ListChecks },
              ].map((a) => (
                <button
                  key={a.label}
                  className="group flex flex-col items-center gap-2 rounded-xl border p-4 text-center transition-all hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md"
                >
                  <span className="grid size-10 place-items-center rounded-full bg-primary/10 text-primary transition-transform group-hover:scale-110">
                    <a.icon className="size-5" />
                  </span>
                  <span className="text-xs font-semibold">{a.label}</span>
                </button>
              ))}
            </CardContent>
          </Card>
        </Reveal>
      </div>
    </>
  );
}
