import { getTranslations } from "next-intl/server";
import { PageHeader, Reveal } from "@/components/ui/page";
import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { designEntries } from "@/data/design-registry";

export default async function DesignReferencePage() {
  const t = await getTranslations("design");
  const tc = await getTranslations("common");
  const grouped = designEntries.reduce<Record<string, typeof designEntries>>(
    (acc, e) => {
      (acc[e.module] ??= []).push(e);
      return acc;
    },
    {}
  );

  return (
    <>
      <PageHeader
        title={t("title")}
        description={`${t("subtitle")} · ${designEntries.length}`}
      />

      {Object.entries(grouped).map(([mod, entries], gi) => (
        <Reveal key={mod} delay={Math.min(gi * 0.03, 0.2)}>
          <section className="space-y-3">
            <div className="flex items-center gap-3">
              <h3 className="text-sm font-bold">
                {entries[0].moduleLabel}
              </h3>
              <Badge variant="secondary">{entries.length}</Badge>
              <a
                href={`/${mod}`}
                className="text-xs text-primary hover:underline"
              >
                {tc("viewAll")} →
              </a>
            </div>
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
              {entries.map((e) => (
                <Card key={e.id} className="group overflow-hidden">
                  <CardContent className="p-0">
                    <a
                      href={e.viewUrl}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="block"
                    >
                      <div className="relative aspect-video overflow-hidden bg-muted">
                        {/* eslint-disable-next-line @next/next/no-img-element */}
                        <img
                          src={e.thumb}
                          alt={`${e.id} ${e.fileName}`}
                          loading="lazy"
                          className="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                        />
                      </div>
                      <div className="space-y-1 p-3">
                        <p className="text-xs font-bold">{e.id}</p>
                        <p className="truncate text-[10px] text-muted-foreground" dir="ltr">
                          {e.fileName}
                        </p>
                      </div>
                    </a>
                  </CardContent>
                </Card>
              ))}
            </div>
          </section>
        </Reveal>
      ))}
    </>
  );
}
