import { getTranslations } from "next-intl/server";
import { PageHeader, Reveal } from "@/components/ui/page";
import { StatCard } from "@/components/ui/stat-card";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Search, Plus, Download } from "lucide-react";
import { cn } from "@/lib/utils";

export interface WorkbenchItem {
  title: string;
  subtitle: string;
  badge?: string;
  badgeVariant?: "default" | "success" | "warning" | "destructive" | "info" | "secondary";
  meta?: string;
}

export interface WorkbenchConfig {
  ns: string;
  newLabel: string;
  stats: { label: string; value: string; icon: React.ComponentType<{ className?: string }>; tone?: "primary" | "sky" | "amber" | "emerald" | "rose" | "violet" }[];
  listTitle: string;
  items: WorkbenchItem[];
}

export async function ModuleWorkbench({ config }: { config: WorkbenchConfig }) {
  const t = await getTranslations(config.ns);
  const tc = await getTranslations("common");
  const { stats, items, listTitle } = config;

  return (
    <>
      <PageHeader
        title={t("title")}
        description={t("subtitle")}
        actions={
          <>
            <div className="relative hidden md:block">
              <Search className="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
              <Input placeholder={tc("search")} className="w-52 ps-9" />
            </div>
            <Button variant="outline" size="icon" aria-label={tc("export")}>
              <Download className="size-4" />
            </Button>
            <Button variant="gradient">
              <Plus className="size-4" />
              {config.newLabel}
            </Button>
          </>
        }
      />

      <div className={cn("grid gap-4", stats.length === 4 ? "grid-cols-2 lg:grid-cols-4" : "grid-cols-2 lg:grid-cols-3")}>
        {stats.map((s, i) => (
          <Reveal key={s.label} delay={i * 0.05}>
            <StatCard label={s.label} value={s.value} icon={s.icon} tone={s.tone ?? "primary"} />
          </Reveal>
        ))}
      </div>

      <Reveal>
        <Card>
          <CardHeader className="flex-row items-center justify-between">
            <CardTitle>{listTitle}</CardTitle>
            <Button variant="ghost" size="sm">{tc("viewAll")}</Button>
          </CardHeader>
          <CardContent className="space-y-3">
            {items.map((item) => (
              <div
                key={item.title}
                className="flex items-center gap-4 rounded-xl border p-3 transition-colors hover:bg-muted/40"
              >
                <div className="grid size-10 shrink-0 place-items-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                  {item.title.slice(0, 2)}
                </div>
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-semibold">{item.title}</p>
                  <p className="truncate text-xs text-muted-foreground">{item.subtitle}</p>
                </div>
                {item.badge ? (
                  <Badge variant={item.badgeVariant ?? "secondary"}>{item.badge}</Badge>
                ) : null}
                {item.meta ? <span className="hidden text-xs text-muted-foreground sm:block">{item.meta}</span> : null}
                <Button variant="outline" size="sm">{tc("edit")}</Button>
              </div>
            ))}
          </CardContent>
        </Card>
      </Reveal>
    </>
  );
}
