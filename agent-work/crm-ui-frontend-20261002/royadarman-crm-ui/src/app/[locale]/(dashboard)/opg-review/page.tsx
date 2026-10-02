import { getTranslations } from "next-intl/server";
import { PageHeader, Reveal } from "@/components/ui/page";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { StatCard } from "@/components/ui/stat-card";
import { fileImage, Clock, CheckCircle2, AlertTriangle } from "@/lib/icons";
import { cn } from "@/lib/utils";

const stages = [
  "quarantine", "scanning", "clean", "assigned", "draft", "signed", "released",
] as const;

const stageTone: Record<string, string> = {
  quarantine: "bg-slate-500/15 text-slate-600 dark:text-slate-300",
  scanning: "bg-amber-500/15 text-amber-600 dark:text-amber-300",
  clean: "bg-emerald-500/15 text-emerald-600 dark:text-emerald-300",
  assigned: "bg-sky-500/15 text-sky-600 dark:text-sky-300",
  draft: "bg-violet-500/15 text-violet-600 dark:text-violet-300",
  signed: "bg-teal-500/15 text-teal-600 dark:text-teal-300",
  released: "bg-primary/15 text-primary",
};

const records = [
  { id: "OPG-1024", patient: "سارا محمدی", state: "released", quality: "خوب" },
  { id: "OPG-1025", patient: "علی رضایی", state: "draft", quality: "متوسط" },
  { id: "OPG-1026", patient: "مریم احمدی", state: "quarantine", quality: "—" },
  { id: "OPG-1027", patient: "حسین کریمی", state: "needsImage", quality: "ضعیف" },
  { id: "OPG-1028", patient: "نگار صادقی", state: "assigned", quality: "خوب" },
];

export default async function OpgReviewPage() {
  const t = await getTranslations("opg");
  const tc = await getTranslations("common");

  return (
    <>
      <PageHeader title={t("title")} description={t("subtitle")}>
        <Button variant="gradient">{t("uploadNew")}</Button>
      </PageHeader>

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <Reveal><StatCard label={t("quarantine")} value="۲" icon={AlertTriangle} tone="amber" /></Reveal>
        <Reveal delay={0.05}><StatCard label={t("scanning")} value="۱" icon={Clock} tone="sky" /></Reveal>
        <Reveal delay={0.1}><StatCard label={t("draft")} value="۳" icon={fileImage} tone="violet" /></Reveal>
        <Reveal delay={0.15}><StatCard label={t("released")} value="۱۲" icon={CheckCircle2} tone="emerald" /></Reveal>
      </div>

      <Reveal>
        <Card>
          <CardHeader><CardTitle>{t("annotation")}</CardTitle></CardHeader>
          <CardContent>
            <div className="flex flex-wrap items-center gap-2">
              {stages.map((s, i) => (
                <div key={s} className="flex items-center gap-2">
                  <span className={cn("rounded-full px-3 py-1 text-xs font-semibold", stageTone[s])}>
                    {t(s)}
                  </span>
                  {i < stages.length - 1 ? (
                    <span className="h-px w-4 bg-border" aria-hidden />
                  ) : null}
                </div>
              ))}
              <span className="rounded-full bg-rose-500/15 px-3 py-1 text-xs font-semibold text-rose-600 dark:text-rose-300">
                {t("needsImage")}
              </span>
            </div>
          </CardContent>
        </Card>
      </Reveal>

      <Reveal delay={0.1}>
        <Card>
          <CardHeader><CardTitle>{tc("status")}</CardTitle></CardHeader>
          <CardContent className="space-y-3">
            {records.map((r) => (
              <div key={r.id} className="flex items-center gap-4 rounded-xl border p-3 hover:bg-muted/40">
                <div className="grid size-12 shrink-0 place-items-center rounded-xl bg-muted">
                  <div className="h-10 w-8 rounded bg-gradient-to-br from-slate-300 to-slate-400 dark:from-slate-600 dark:to-slate-700" />
                </div>
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-semibold">{r.id} · {r.patient}</p>
                  <p className="text-xs text-muted-foreground">{t("imageQuality")}: {r.quality}</p>
                </div>
                <Badge
                  variant={r.state === "released" ? "success" : r.state === "needsImage" ? "destructive" : "secondary"}
                >
                  {t(r.state as Parameters<typeof t>[0])}
                </Badge>
                <Button variant="outline" size="sm">{tc("viewAll")}</Button>
              </div>
            ))}
          </CardContent>
        </Card>
      </Reveal>
    </>
  );
}
