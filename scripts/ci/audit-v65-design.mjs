#!/usr/bin/env node
/**
 * Deterministic source inventory and advisory audit.
 *
 * All source lines are scanned; flagged patterns are candidates for manual
 * review, NOT a claim of runtime/a11y/pixel parity failure. The approved
 * prototype is inventoried but not judged as application markup.
 */
import { existsSync, mkdirSync, readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { extname, join, relative } from 'node:path';

const root = process.cwd();
const directories = ['resources/views', 'resources/css', 'resources/js', 'reference'];
const allowed = ['.css', '.js', '.ts', '.tsx', '.html', '.vue', '.scss', '.blade.php'];
const output = 'artifacts/v65-design-source-audit.json';
const required = [
  'resources/views/layouts/app.blade.php',
  'resources/views/components/v65/button.blade.php',
  'resources/views/components/v65/modal.blade.php',
  'resources/views/components/v65/tabs.blade.php',
  'resources/css/v65-shell.css',
  'resources/css/v65-themes.css',
  'resources/css/v65-reporting.css',
];
const flags = [
  ['inline_event', /\bon(?:click|change|submit|input|load|error)\s*=/gi],
  ['inline_style', /\bstyle\s*=/gi],
  ['placeholder_anchor', /\bhref\s*=\s*(['"])#\1/gi],
  ['css_important', /!important\b/gi],
  ['css_hex_color', /#[\da-f]{3,8}\b/gi],
  ['raw_button', /<button\b/gi],
  ['raw_select', /<select\b/gi],
  ['raw_input', /<input\b/gi],
  ['raw_textarea', /<textarea\b/gi],
  ['aria_attribute', /\baria-[\w-]+\s*=/gi],
  ['table', /<table\b/gi],
  ['visual_field_component', /<x-(?:v65|field)\.[\w-]+/gi],
];
const isSource = path => allowed.some(ext => path.endsWith(ext));
const isCss = path => path.endsWith('.css') || path.endsWith('.scss');
const walk = (dir, found) => {
  if (!existsSync(dir)) return;
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const path = join(dir, entry.name);
    if (entry.isDirectory()) walk(path, found);
    else if (entry.isFile() && isSource(path)) found.push(path);
  }
};

const absoluteFiles = [];
for (const d of directories) walk(join(root, d), absoluteFiles);
absoluteFiles.sort();
const summary = {};
const review = [];
let linesChecked = 0;
const breakdown = {};

for (const absolute of absoluteFiles) {
  const path = relative(root, absolute).replaceAll('\\', '/');
  const text = readFileSync(absolute, 'utf8');
  const lines = text.split(/\r?\n/);
  linesChecked += lines.length;
  const prototype = path.startsWith('reference/');
  const kind = prototype ? 'prototype'
    : path.endsWith('.blade.php') ? 'blade'
      : extname(path).slice(1);
  breakdown[kind] = (breakdown[kind] ?? 0) + 1;
  const fileCounts = {};

  if (!prototype) {
    for (let i = 0; i < lines.length; i++) {
      const line = lines[i];
      for (const [flag, regex] of flags) {
        if ((flag.startsWith('css_') && !isCss(path))
          || (flag.startsWith('raw_') && !path.endsWith('.blade.php'))
          || (flag === 'table' && !path.endsWith('.blade.php'))) continue;

        const matches = [...line.matchAll(regex)];
        if (!matches.length) continue;
        summary[flag] = (summary[flag] ?? 0) + matches.length;
        fileCounts[flag] = (fileCounts[flag] ?? 0) + matches.length;

        // Only actionable hotspots, bounded to keep artifacts manageable.
        if (['inline_event', 'inline_style', 'placeholder_anchor'].includes(flag)) {
          review.push({ file: path, line: i + 1, kind: flag, occurrences: matches.length,
            excerpt: line.trim().slice(0, 190) });
        }
      }
    }
  }
  if (Object.keys(fileCounts).length) {
    review.push({ file: path, kind: 'file_totals', counts: fileCounts });
  }
}

const missing = required.filter(p => !existsSync(join(root, p)));
const snapshot = {
  scope: 'Static source review only; no runtime, accessibility or pixel-perfect claim',
  scannedFiles: absoluteFiles.length,
  scannedLines: linesChecked,
  fileTypes: breakdown,
  occurrences: summary,
  missingRequired: missing,
  findings: review,
};
mkdirSync(join(root, 'artifacts'), { recursive: true });
writeFileSync(join(root, output), JSON.stringify(snapshot, null, 2) + '\n');
process.stdout.write(
  'V65 design audit: ' + snapshot.scannedFiles + ' files / ' + snapshot.scannedLines +
  ' lines; inline_events=' + (summary.inline_event ?? 0) +
  '; inline_styles=' + (summary.inline_style ?? 0) +
  '; !important=' + (summary.css_important ?? 0) +
  '; report=' + output + '\n',
);

if (missing.length) {
  process.stderr.write('Missing required design source: ' + missing.join(', ') + '\n');
  process.exitCode = 1;
}
