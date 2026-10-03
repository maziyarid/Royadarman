import * as React from "react";
import { TrendingUp, TrendingDown } from "lucide-react";
import { Card, CardContent } from "@/components/ui/card";
import { cn } from "@/lib/utils";

export function StatCard({
  label,
  value,
  delta,
  trend = "up",
  icon: Icon,
  tone = "primary",
  className,
}: {
  label: string;
  value: string;
  delta?: string;
  trend?: "up" | "down" | "flat";
  icon: React.ComponentType<{ className?: string }>;
  tone?: "primary" | "sky" | "amber" | "emerald" | "rose" | "violet";
  className?: string;
}) {
  const tones: Record<string, string> = {
    primary: "bg-teal-500/12 text-teal-600 dark:text-teal-300",
    sky: "bg-sky-500/12 text-sky-600 dark:text-sky-300",
    amber: "bg-amber-500/12 text-amber-600 dark:text-amber-300",
    emerald: "bg-emerald-500/12 text-emerald-600 dark:text-emerald-300",
    rose: "bg-rose-500/12 text-rose-600 dark:text-rose-300",
    violet: "bg-violet-500/12 text-violet-600 dark:text-violet-300",
  };
  return (
    <Card className={cn("group relative overflow-hidden hover:shadow-lg", className)}>
      <CardContent className="p-5">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0">
            <p className="truncate text-xs font-medium text-muted-foreground">{label}</p>
            <p className="mt-2 text-2xl font-extrabold tracking-tight">{value}</p>
            {delta ? (
              <p
                className={cn(
                  "mt-2 inline-flex items-center gap-1 text-xs font-semibold",
                  trend === "up" && "text-emerald-600 dark:text-emerald-400",
                  trend === "down" && "text-rose-600 dark:text-rose-400",
                  trend === "flat" && "text-muted-foreground"
                )}
              >
                {trend === "up" ? <TrendingUp className="size-3.5" /> : null}
                {trend === "down" ? <TrendingDown className="size-3.5" /> : null}
                {delta}
              </p>
            ) : null}
          </div>
          <div
            className={cn(
              "grid size-11 shrink-0 place-items-center rounded-xl transition-transform duration-300 group-hover:scale-110",
              tones[tone]
            )}
          >
            <Icon className="size-5" />
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
