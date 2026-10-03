import * as React from "react";
import { cn } from "@/lib/utils";

export function DataTable({
  columns,
  rows,
  className,
}: {
  columns: { key: string; label: string; className?: string }[];
  rows: React.ReactNode[][];
  className?: string;
}) {
  return (
    <div className={cn("overflow-x-auto rounded-2xl border bg-card shadow-[var(--shadow-card)]", className)}>
      <table className="w-full text-sm">
        <thead>
          <tr className="border-b bg-muted/50 text-start">
            {columns.map((c) => (
              <th
                key={c.key}
                className={cn(
                  "whitespace-nowrap px-4 py-3 text-start text-xs font-bold uppercase tracking-wide text-muted-foreground",
                  c.className
                )}
              >
                {c.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, i) => (
            <tr
              key={i}
              className="border-b last:border-0 transition-colors hover:bg-muted/40"
            >
              {row.map((cell, j) => (
                <td key={j} className={cn("px-4 py-3", columns[j]?.className)}>
                  {cell}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
