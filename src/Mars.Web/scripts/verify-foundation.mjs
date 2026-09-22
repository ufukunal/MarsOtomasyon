import { readFile, readdir } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import path from "node:path";

const projectRoot = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const sourceRoot = path.join(projectRoot, "src");
const packageJson = JSON.parse(await readFile(path.join(projectRoot, "package.json"), "utf8"));

const forbiddenPackages = [
  "react",
  "react-dom",
  "vue",
  "@angular/core",
  "bootstrap",
  "tailwindcss",
  "jquery"
];

const allDependencies = {
  ...(packageJson.dependencies ?? {}),
  ...(packageJson.devDependencies ?? {})
};

for (const packageName of forbiddenPackages) {
  if (packageName in allDependencies) {
    throw new Error(`Forbidden frontend package detected: ${packageName}`);
  }
}

const files = await collectFiles(sourceRoot);
const sourceText = (await Promise.all(files.map((file) => readFile(file, "utf8")))).join("\n");

for (const forbidden of [
  "local" + "Storage",
  "session" + "Storage",
  "marsotomasyon_ui_v38_cari_bakiye_sadelestirildi.html",
  ".inner" + "HTML"
]) {
  if (sourceText.includes(forbidden)) {
    throw new Error(`Forbidden production-source pattern detected: ${forbidden}`);
  }
}

const required = [
  "src/app.ts",
  "src/api-client.ts",
  "src/ui/tokens.css",
  "src/ui/base.css",
  "src/ui/components.ts"
];

for (const relative of required) {
  if (!files.includes(path.join(projectRoot, relative))) {
    throw new Error(`Required FW-IMP-006 file missing: ${relative}`);
  }
}

console.log("PASS: FW-IMP-006 static architecture checks");

async function collectFiles(directory) {
  const result = [];
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    const full = path.join(directory, entry.name);
    if (entry.isDirectory()) result.push(...await collectFiles(full));
    else if (/\.(ts|css|html)$/i.test(entry.name)) result.push(full);
  }
  return result;
}
