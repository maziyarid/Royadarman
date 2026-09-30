#!/usr/bin/env python3
"""Static contract checks for the RPH-98 calendar presentation slice."""
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
blade = (ROOT / "resources/views/panel/calendar/index.blade.php").read_text()
components = list((ROOT / "resources/views/components/rph98").glob("*.blade.php"))
css = (ROOT / "public_html/assets/rph98-presentation.css").read_text()
texts = {"index": blade, **{p.name: p.read_text() for p in components}, "css": css}
joined = "\n".join(texts.values())
failures = []

def check(ok, message):
    print(("PASS " if ok else "FAIL ") + message)
    if not ok:
        failures.append(message)

check("panel.calendar.index" in texts["month-toolbar.blade.php"], "previous/next/today still target panel.calendar.index")
check("jmonth" not in blade or "$monthQueryKey" in blade, "month query key stays a controller variable, not a hardcoded Gregorian conversion")
check("$monthQueryKey" in blade, "toolbar receives the controller month query key")
check("JalaliCalendar::fromGregorian" in texts["operations-agenda.blade.php"], "agenda still converts display dates with JalaliCalendar")
check("panel.calendar.kinds." not in joined, "missing kinds.* translation key is not rendered")
check("panel.calendar.agenda" not in joined, "missing agenda translation key is not rendered")
check("panel.calendar.types.referral_deadline" in texts["operations-agenda.blade.php"], "referral kind uses the existing deadline label")
check("$event['url']" in texts["operations-month.blade.php"] and "$event['url']" in texts["operations-agenda.blade.php"], "event links keep controller URLs")
check("data-kind=\"{{ $event['kind'] }}\"" in texts["operations-month.blade.php"], "filter uses existing kind token only")
check("CoordinationTask" not in joined and "DB::" not in joined and "ReferralGrant" not in joined, "presentation files add no queries")
for banned in ["patient_name", "national_id", "diagnosis", "opg", "invoice", "phone", "clinical_note", "amount"]:
    check(banned not in joined.lower(), f"no new field {banned}")
check("repeat(7, minmax(0, 1fr))" in css, "seven Saturday-first columns")
check(".rph98-weekday{display:none}" not in css.replace(" ", "") and "rph98-weekday" in css, "weekday labels are not removed at tablet widths")
check("rph98-day-empty" in css and "rph98-day-empty{display:none}" not in css.replace(" ", ""), "leading blanks stay in the grid")
check(":focus-visible" in css, "visible focus rule present")
check("dir=\"ltr\"" in texts["operations-agenda.blade.php"], "clock stays left-to-right")
check("<bdi>" in texts["operations-agenda.blade.php"], "mixed-direction titles are isolated")
check("min-height: 44px" in css, "toolbar and agenda targets are at least 44px")
check("@media (max-width: 640px)" in css and "@media (max-width: 900px)" in css, "mobile and tablet rules exist")
check("rph98-presentation.css" in blade, "view links the additive stylesheet, not workspace.css")
check("workspace.css" not in blade, "this slice does not rewrite the shared stylesheet link")

def channel(hex_color):
    value = int(hex_color, 16) / 255
    return value / 12.92 if value <= 0.04045 else ((value + 0.055) / 1.055) ** 2.4

def contrast(a, b):
    def lum(color):
        r, g, b = color[0:2], color[2:4], color[4:6]
        return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b)
    left, right = lum(a), lum(b)
    lighter, darker = max(left, right), min(left, right)
    return (lighter + 0.05) / (darker + 0.05)

pairs = {
    "ink on white": ("18233c", "ffffff"),
    "muted on white": ("3e4a5f", "ffffff"),
    "white on brand": ("ffffff", "2947a3"),
    "white on teal": ("ffffff", "0b6e78"),
    "brand on today": ("2947a3", "e7eefc"),
}
for name, (fg, bg) in pairs.items():
    ratio = contrast(fg, bg)
    check(ratio >= 4.5, f"{name} contrast {ratio:.2f} >= 4.5")

print(f"FILES {1 + len(components) + 1}")
sys.exit(1 if failures else 0)
