#!/usr/bin/env node
// Static JavaScript gate for the approved single-file v65 prototype.
// Browser HTML scripts execute separately; one malformed script can leave the
// left navigation and main content empty while the static header still paints.
import { readFileSync } from 'node:fs';
import { Script } from 'node:vm';

const path = 'reference/marsotomasyon-PROTOTIP-ONAYLI-v65.html';
const html = readFileSync(path, 'utf8');
const tags = [...html.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script\s*>/gi)];

if (tags.length < 100) {
  throw new Error(`Expected at least 100 prototype script blocks; found ${tags.length}.`);
}

let verified = 0;
const failures = [];

for (const [index, match] of tags.entries()) {
  const attributes = match[1];
  if (/\bsrc\s*=/.test(attributes)
    || /\btype\s*=\s*['"](?:application\/json|text\/plain)['"]/.test(attributes)) {
    continue;
  }

  const line = html.slice(0, match.index).split('\n').length;
  try {
    new Script(match[2], { filename: `${path}:script-${index + 1}-at-html-line-${line}` });
    verified++;
  } catch (error) {
    failures.push(`Inline script #${index + 1} near HTML line ${line}: ${error.message}`);
  }
}

if (failures.length) {
  for (const message of failures) console.error(message);
  process.exitCode = 1;
} else {
  console.log(`PASS: approved v65 HTML has ${verified} parseable inline JavaScript blocks.`);
}
