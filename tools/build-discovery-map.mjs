// Run only in an isolated checkout with its pinned npm dependencies installed.
import { createRequire } from "node:module";
import { readFile, writeFile, mkdir, stat } from "node:fs/promises";
import { resolve, dirname, isAbsolute, relative, sep } from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";
import { createHash } from "node:crypto";

const repository = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const backend = resolve(repository, "backend");
const destination = process.argv[2];
const relativeOutput = destination ? relative(repository, resolve(destination)) : "";
if (!destination || !isAbsolute(destination) || (relativeOutput !== ".." && !relativeOutput.startsWith(".." + sep))) {
  throw new Error("Choose a new private output directory outside the source checkout");
}
try { await stat(destination); throw new Error("Output directory already exists"); }
catch (error) { if (error.code !== "ENOENT") throw error; }
try { await stat(resolve(backend, ".env")); throw new Error("Refusing a checkout with .env"); }
catch (error) { if (error.code !== "ENOENT") throw error; }

const hash = (bytes) => createHash("sha256").update(bytes).digest("hex");
const lockBytes = await readFile(resolve(backend, "package-lock.json"));
const sourceBytes = await readFile(resolve(backend, "resources/js/discovery-map.js"));
const builderBytes = await readFile(fileURLToPath(import.meta.url));
const lock = JSON.parse(lockBytes);
const require = createRequire(resolve(backend, "package.json"));
for (const dependency of ["vite", "maplibre-gl"]) {
  const installed = JSON.parse(await readFile(resolve(backend, "node_modules", dependency, "package.json")));
  if (installed.version !== lock.packages[`node_modules/${dependency}`].version) throw new Error("Dependency differs from lock");
}
const { build } = await import(pathToFileURL(require.resolve("vite")).href);
await mkdir(destination, { mode: 0o700 });
await build({
  root: backend, configFile: false, envDir: false, publicDir: false,
  base: "/build/", mode: "production",
  build: {
    outDir: destination, emptyOutDir: false, manifest: "manifest.json", sourcemap: false,
    rolldownOptions: { input: resolve(backend, "resources/js/discovery-map.js") },
  },
});
if (hash(await readFile(resolve(backend, "package-lock.json"))) !== hash(lockBytes)) throw new Error("Lock changed during build");
if (hash(await readFile(resolve(backend, "resources/js/discovery-map.js"))) !== hash(sourceBytes)) throw new Error("Source changed during build");
if (hash(await readFile(fileURLToPath(import.meta.url))) !== hash(builderBytes)) throw new Error("Builder changed during build");
const entry = "resources/js/discovery-map.js";
const generated = JSON.parse(await readFile(resolve(destination, "manifest.json")));
if (Object.keys(generated).length !== 1 || !generated[entry] || generated[entry].imports?.length || generated[entry].dynamicImports?.length) {
  throw new Error("Unexpected build graph; independent review required");
}
const existing = JSON.parse(await readFile(resolve(backend, "public/build/manifest.json")));
existing[entry] = generated[entry];
await writeFile(resolve(destination, "merged-manifest.json"), JSON.stringify(existing, null, 2) + "\n", { mode: 0o600 });
const artifacts = {};
for (const file of [generated[entry].file, ...generated[entry].css]) {
  if (!/^assets\/discovery-map-[A-Za-z0-9_-]+\.(js|css)$/.test(file)) throw new Error("Unexpected artifact");
  artifacts[file] = hash(await readFile(resolve(destination, file)));
}
const evidence = {
  node: process.version, lock_sha256: hash(lockBytes),
  source_sha256: hash(sourceBytes),
  builder_sha256: hash(builderBytes),
  vite: lock.packages["node_modules/vite"].version,
  maplibre: lock.packages["node_modules/maplibre-gl"].version,
  manifest_sha256: hash(await readFile(resolve(destination, "merged-manifest.json"))), artifacts,
  scope: "discovery only; no app/passkeys/fonts/plugin/environment loading; no WebGL/provider proof",
};
await writeFile(resolve(destination, "build-evidence.json"), JSON.stringify(evidence, null, 2) + "\n", { mode: 0o600 });
console.log(JSON.stringify(evidence));
