"use client";

import { useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { Menu, X } from "lucide-react";
import { useTranslations } from "next-intl";
import { navSections } from "@/lib/nav";
import { cn } from "@/lib/utils";

export function MobileNav() {
  const [open, setOpen] = useState(false);
  const pathname = usePathname();
  const t = useTranslations("nav");

  if (!open) {
    return (
      <button
        onClick={() => setOpen(true)}
        className="fixed bottom-6 start-6 z-40 grid size-12 place-items-center rounded-full bg-primary text-primary-foreground shadow-lg lg:hidden"
        aria-label="Open menu"
      >
        <Menu className="size-5" />
      </button>
    );
  }

  return (
    <div className="fixed inset-0 z-50 flex flex-col bg-card lg:hidden">
      <div className="flex h-16 items-center justify-between border-b px-5">
        <p className="font-extrabold">Roya Darman CRM</p>
        <button onClick={() => setOpen(false)} aria-label="Close menu">
          <X className="size-5" />
        </button>
      </div>
      <nav className="flex-1 overflow-y-auto px-4 py-4">
        {navSections.map((section) => (
          <div key={section.titleKey} className="mb-5">
            <p className="mb-2 px-2 text-[10px] font-bold uppercase tracking-widest text-muted-foreground/70">
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
                      onClick={() => setOpen(false)}
                      className={cn(
                        "flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium",
                        active
                          ? "bg-primary/10 text-primary"
                          : "text-muted-foreground hover:bg-muted"
                      )}
                    >
                      <item.icon className="size-4.5" />
                      {t(item.labelKey.replace("nav.", ""))}
                    </Link>
                  </li>
                );
              })}
            </ul>
          </div>
        ))}
      </nav>
    </div>
  );
}
