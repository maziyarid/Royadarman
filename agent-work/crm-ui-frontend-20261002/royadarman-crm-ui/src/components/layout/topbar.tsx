"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useLocale, useTranslations } from "next-intl";
import { locales, localeLabels, type Locale } from "@/i18n/config";
import { navSections } from "@/lib/nav";
import { Search, Globe, Moon, Sun } from "lucide-react";
import { useEffect, useState } from "react";
import { cn } from "@/lib/utils";

function useDark() {
  const [dark, setDark] = useState(false);
  useEffect(() => {
    const root = document.documentElement;
    if (dark) root.classList.add("dark");
    else root.classList.remove("dark");
  }, [dark]);
  return [dark, setDark] as const;
}

export function Topbar() {
  const pathname = usePathname();
  const locale = useLocale();
  const t = useTranslations();
  const [dark, setDark] = useDark();
  const [menuOpen, setMenuOpen] = useState(false);

  const currentTitle = (() => {
    for (const s of navSections) {
      for (const item of s.items) {
        if (pathname === item.href || pathname.startsWith(item.href)) {
          return t(item.labelKey);
        }
      }
    }
    return t("nav.dashboard");
  })();

  const switchLocale = (l: Locale) => {
    const segments = pathname.split("/");
    if (locales.includes(segments[1] as Locale)) segments[1] = l;
    else segments.splice(1, 0, l);
    window.location.assign(segments.join("/") || "/");
  };

  return (
    <header className="sticky top-0 z-20 flex h-16 items-center gap-3 border-b bg-card/80 px-4 backdrop-blur-lg lg:px-6">
      <Link href="/dashboard" className="flex items-center gap-2 lg:hidden">
        <div className="grid size-8 place-items-center rounded-lg bg-gradient-to-br from-teal-500 to-sky-600 text-white">
          <svg viewBox="0 0 24 24" className="size-4" fill="currentColor">
            <path d="M12 2C8 2 5 4.5 5 8c0 2 1 3.5 2 4.5V19a3 3 0 0 0 3 3h4a3 3 0 0 0 3-3v-6.5c1-1 2-2.5 2-4.5 0-3.5-3-6-7-6Z" />
          </svg>
        </div>
      </Link>

      <h2 className="truncate text-sm font-bold lg:text-base">{currentTitle}</h2>

      <div className="ms-auto flex items-center gap-2">
        <div className="relative hidden md:block">
          <Search className="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
          <input
            placeholder={t("nav.search")}
            className="h-9 w-56 rounded-full border bg-muted/60 ps-9 pe-3 text-sm outline-none transition-all focus:w-72 focus:border-ring"
          />
        </div>

        <div className="relative">
          <button
            onClick={() => setMenuOpen(!menuOpen)}
            onBlur={() => setTimeout(() => setMenuOpen(false), 150)}
            className="flex h-9 items-center gap-1.5 rounded-full border px-3 text-sm font-medium hover:bg-muted"
            aria-label={t("nav.language")}
          >
            <Globe className="size-4" />
            <span className="hidden sm:inline">{localeLabels[locale as Locale]}</span>
          </button>
          {menuOpen ? (
            <div className="absolute end-0 top-11 z-30 w-36 overflow-hidden rounded-xl border bg-card shadow-lg">
              {locales.map((l) => (
                <button
                  key={l}
                  onClick={() => switchLocale(l)}
                  className={cn(
                    "block w-full px-4 py-2 text-start text-sm hover:bg-muted",
                    l === locale && "font-bold text-primary"
                  )}
                >
                  {localeLabels[l]}
                </button>
              ))}
            </div>
          ) : null}
        </div>

        <button
          onClick={() => setDark(!dark)}
          className="grid size-9 place-items-center rounded-full border hover:bg-muted"
          aria-label="Toggle theme"
        >
          {dark ? <Sun className="size-4" /> : <Moon className="size-4" />}
        </button>

        <div className="ms-1 flex items-center gap-2">
          <div className="hidden text-end sm:block">
            <p className="text-xs font-bold">دکتر نیلوفر احمدی</p>
            <p className="text-[10px] text-muted-foreground">{t("nav.demoUser")}</p>
          </div>
          <div className="grid size-9 place-items-center rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 text-xs font-bold text-white">
            ن‌ا
          </div>
        </div>
      </div>
    </header>
  );
}
