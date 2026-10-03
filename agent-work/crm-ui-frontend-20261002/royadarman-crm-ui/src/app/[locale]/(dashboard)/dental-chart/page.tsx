import { getTranslations } from "next-intl/server";
import { PageHeader, Reveal } from "@/components/ui/page";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { dentalChartTeeth } from "@/data/mock";
import { cn } from "@/lib/utils";

type ToothStatus =
  | "healthy"
  | "caries"
  | "missing"
  | "restored"
  | "implant"
  | "unknown";

const toothColors: Record<ToothStatus, string> = {
  healthy: "bg-emerald-500/80 text-white",
  caries: "bg-rose-500/80 text-white",
  missing: "bg-slate-400/60 text-white line-through",
  restored: "bg-sky-500/80 text-white",
  implant: "bg-violet-500/80 text-white",
  unknown: "bg-muted text-muted-foreground",
};

export default async function DentalChartPage() {
  const t = await getTranslations("dental");
  const statuses = Object.keys(toothColors) as ToothStatus[];

  const upper = dentalChartTeeth.slice(0, 16);
  const lower = dentalChartTeeth.slice(16);

  const Tooth = ({ status, num }: { status: string; num: number }) => {
    const s = (status in toothColors ? status : "unknown") as ToothStatus;
    return (
      <div className="flex flex-col items-center gap-1">
        <span
          className={cn(
            "grid h-10 w-7 place-items-center rounded-lg text-[10px] font-bold transition-transform hover:scale-110",
            toothColors[s]
          )}
          title={`${num}: ${t(s)}`}
        >
          {num}
        </span>
      </div>
    );
  };

  return (
    <>
      <PageHeader title={t("title")} description={t("subtitle")} />

      <Reveal>
        <Card>
          <CardHeader className="flex-row items-center justify-between">
            <CardTitle>{t("adult")} · FDI</CardTitle>
            <Badge variant="secondary">{t("legend")}</Badge>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex flex-wrap justify-center gap-1.5">
              {upper.map((s, i) => (
                <Tooth key={i} status={s} num={11 + i} />
              ))}
            </div>
            <div className="h-px bg-border" />
            <div className="flex flex-wrap justify-center gap-1.5">
              {lower.map((s, i) => (
                <Tooth key={i} status={s} num={48 - i} />
              ))}
            </div>
            <div className="flex flex-wrap gap-3 pt-2">
              {statuses.map((s) => (
                <span
                  key={s}
                  className="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                  <span className={cn("size-3 rounded-sm", toothColors[s])} />
                  {t(s)}
                </span>
              ))}
            </div>
          </CardContent>
        </Card>
      </Reveal>

      <Reveal delay={0.1}>
        <Card>
          <CardHeader>
            <CardTitle>{t("child")} · FDI</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex flex-wrap justify-center gap-1.5">
              {Array.from({ length: 10 }).map((_, i) => (
                <Tooth
                  key={i}
                  status={i % 4 === 0 ? "unknown" : "healthy"}
                  num={51 + i}
                />
              ))}
            </div>
            <div className="h-px bg-border" />
            <div className="flex flex-wrap justify-center gap-1.5">
              {Array.from({ length: 10 }).map((_, i) => (
                <Tooth
                  key={i}
                  status={i % 5 === 0 ? "caries" : "healthy"}
                  num={85 - i}
                />
              ))}
            </div>
          </CardContent>
        </Card>
      </Reveal>
    </>
  );
}
