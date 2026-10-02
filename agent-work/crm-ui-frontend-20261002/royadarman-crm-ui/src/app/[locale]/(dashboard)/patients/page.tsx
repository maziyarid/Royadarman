import { getTranslations } from "next-intl/server";
import { PageHeader, Reveal } from "@/components/ui/page";
import { DataTable } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { UserPlus, Search } from "lucide-react";
import { patientsList } from "@/data/mock";

export default async function PatientsPage() {
  const t = await getTranslations("patients");
  const tc = await getTranslations("common");

  const columns = [
    { key: "name", label: t("name") },
    { key: "phone", label: t("phone") },
    { key: "lastVisit", label: t("lastVisit") },
    { key: "condition", label: t("condition") },
    { key: "status", label: tc("status") },
    { key: "actions", label: tc("actions") },
  ];

  const rows = patientsList.map((p) => [
    <div key="n" className="flex items-center gap-3">
      <span className="grid size-9 place-items-center rounded-full bg-gradient-to-br from-teal-500 to-sky-500 text-xs font-bold text-white">
        {p.name.slice(0, 2)}
      </span>
      <span className="font-semibold">{p.name}</span>
    </div>,
    <span key="p" className="text-muted-foreground" dir="ltr">{p.phone}</span>,
    p.lastVisit,
    p.condition,
    <Badge key="s" variant={p.active ? "success" : "secondary"}>
      {p.active ? t("active") : t("inactive")}
    </Badge>,
    <Button key="a" variant="ghost" size="sm">{tc("edit")}</Button>,
  ]);

  return (
    <>
      <PageHeader
        title={t("title")}
        description={t("subtitle")}
        actions={
          <>
            <div className="relative hidden md:block">
              <Search className="absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
              <Input placeholder={tc("search")} className="w-56 ps-9" />
            </div>
            <Button variant="gradient">
              <UserPlus className="size-4" />
              {t("addPatient")}
            </Button>
          </>
        }
      />
      <Reveal>
        <DataTable columns={columns} rows={rows} />
      </Reveal>
      <p className="text-center text-xs text-muted-foreground">
        <Badge variant="info" className="mx-auto">{tc("syntheticData")}</Badge>
      </p>
    </>
  );
}
