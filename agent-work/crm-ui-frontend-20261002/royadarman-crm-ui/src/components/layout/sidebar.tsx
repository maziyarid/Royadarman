"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useTranslations } from "next-intl";
import { navSections } from "@/lib/nav";
import { cn } from "@/lib/utils";

export function Sidebar() {
  const pathname = usePathname();
  const t = useTranslations("nav");

  return (
    <aside className="sticky top-0 z-30 hidden h-screen w-64 shrink-0 flex-col border-e bg-card lg:flex">
      <div className="flex h-16 items-center gap-2.5 border-b px-5">
        <div className="grid size-9 place-items-center rounded-xl bg-gradient-to-br from-teal-500 to-sky-600 text-white shadow-md">
          <svg viewBox="0 0 24 24" className="size-5" fill="currentColor">
            <path d="M12 2C8 2 5 4.5 5 8c0 2 1 3.5 2 4.5V19a3 3 0 0 0 3 3h4a3 3 0 0 0 3-3v-6.5c1-1 2-2.5 2-4.5 0-3.5-3-6-7-6Zm-2.5 7a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Zm5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Z" />
          </svg>
        </div>
        <div className="leading-tight">
          <p className="text-sm font-extrabold">Roya Darman</p>
          <p className="text-[11px] text-muted-foreground">Smart Teb CRM</p>
        </div>
      </div>

      <nav className="flex-1 overflow-y-auto px-3 py-4">
        {navSections.map((section) => (
          <div key={section.titleKey} className="mb-5">
            <p className="mb-2 px-3 text-[10px] font-bold uppercase tracking-widest text-muted-foreground/70">
              {t(section.titleKey.replace("nav.", ""))}
            </p>
            <ul className="space-y-1">
              {section.items.map((item) => {
                const active =
                  pathname === item.href ||
                  (item.href !== "/dashboard" && pathname.startsWith(item.href));
                return (
                  <li key={item.href}>
                    <Link
                      href={item.href}
                      className={cn(
                        "relative flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition-colors",
                        active
                          ? "bg-primary/10 text-primary"
                          : "text-muted-foreground hover:bg-muted hover:text-foreground"
                      )}
                    >
                      {active ? (
                        <span className="absolute inset-y-1 start-0 w-1 rounded-full bg-primary" />
                      ) : null}
                      <item.icon className="size-4.5 shrink-0" />
                      <span className="truncate">{t(item.labelKey.replace("nav.", ""))}</span>
                    </Link>
                  </li>
                );
              })}
            </ul>
          </div>
        ))}
      </nav>

      <div className="border-t p-4">
        <div className="rounded-xl bg-gradient-to-l from-teal-500/15 to-sky-500/15 p-3">
          <p className="text-xs font-semibold">{t("demoNotice")}</p>
        </div>
      </div>
    </aside>
  );
}
