export const locales = ["fa", "en", "ar"] as const;
export type Locale = (typeof locales)[number];
export const defaultLocale = "fa" as Locale;
export const localeDir: Record<Locale, "rtl" | "ltr"> = {
  fa: "rtl",
  ar: "rtl",
  en: "ltr",
};
export const localeLabels: Record<Locale, string> = {
  fa: "فارسی",
  en: "English",
  ar: "العربية",
};
