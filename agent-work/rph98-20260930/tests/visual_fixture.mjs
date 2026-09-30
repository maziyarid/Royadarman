import { chromium } from "playwright";
import { mkdir } from "node:fs/promises";
import path from "node:path";
import { pathToFileURL } from "node:url";

const root = path.resolve(import.meta.dirname, "..");
const html = pathToFileURL(path.join(root, "evidence/calendar-fixture.html")).href;
const shots = path.join(root, "evidence/screenshots");
await mkdir(shots, { recursive: true });

const browser = await chromium.launch({ headless: true });
const failures = [];
const check = (ok, message) => {
  console.log(`${ok ? "PASS" : "FAIL"} ${message}`);
  if (!ok) failures.push(message);
};

const viewports = [
  ["desktop", 1280, 800],
  ["tablet", 768, 1024],
  ["mobile", 390, 844],
];

for (const [name, width, height] of viewports) {
  const page = await browser.newPage({ viewport: { width, height } });
  const errors = [];
  page.on("pageerror", (err) => errors.push(String(err)));
  await page.goto(html);
  await page.screenshot({ path: path.join(shots, `${name}.png`), fullPage: true });
  const weekdays = await page.locator(".rph98-calendar[data-rph98-calendar] .rph98-weekday").count();
  check(weekdays === 7, `${name} keeps 7 weekday labels`);
  const leading = await page.locator("section[aria-label='شروع جمعه'] .rph98-day-empty").count();
  check(leading === 6, `${name} Friday-start month has 6 leading cells, got ${leading}`);
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
  check(!overflow, `${name} has no horizontal page overflow`);
  check(errors.length === 0, `${name} console clean ${errors.join(" | ")}`);
  await page.close();
}

const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
await page.goto(html);
const first = page.locator(".rph98-toolbar .rph98-btn").first();
await first.focus();
const outline = await first.evaluate((el) => getComputedStyle(el).outlineStyle);
check(outline !== "none", `focused month control outline is ${outline}`);
await page.getByRole("button", { name: "خدمت در منزل" }).click();
const visibleTasks = await page.locator("[data-rph98-agenda] [data-kind='task']:visible").count();
const visibleHome = await page.locator("[data-rph98-agenda] [data-kind='home_service']:visible").count();
check(visibleTasks === 0 && visibleHome === 1, `filter leaves home events only (${visibleTasks}/${visibleHome})`);
await page.screenshot({ path: path.join(shots, "filtered-desktop.png"), fullPage: false });
await browser.close();
if (failures.length) {
  console.log(`FAILURES ${failures.length}`);
  process.exit(1);
}
console.log("VISUAL_FIXTURE_OK synthetic only");
