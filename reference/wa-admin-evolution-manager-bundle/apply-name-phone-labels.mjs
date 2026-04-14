#!/usr/bin/env node
/**
 * Reapply "Name · phone" display labels to a pre-patch Evolution Manager bundle.
 * Usage: node apply-name-phone-labels.mjs [input.js] [output.js]
 * Defaults: ./assets/index-B_oZUlX_.js.pre-patch-backup -> ./assets/index-B_oZUlX_.js
 */
import { readFileSync, writeFileSync } from "fs";
import { dirname, join } from "path";
import { fileURLToPath } from "url";

const __dir = dirname(fileURLToPath(import.meta.url));
const inputPath = process.argv[2] ?? join(__dir, "assets", "index-B_oZUlX_.js.pre-patch-backup");
const outputPath = process.argv[3] ?? join(__dir, "assets", "index-B_oZUlX_.js");

let s = readFileSync(inputPath, "utf8");

const reps = [
  [
    'k.pushName||k.remoteJid.split("@")[0]',
    '(function(){const __p=k.remoteJid.split("@")[0],__n=String(k.pushName??"").trim(),__d=__n.replace(/\\D/g,"");return __n&&__d!==__p?__n+" · "+__p:__n||__p})()',
  ],
  [
    'T?.pushName||T?.remoteJid?.split("@")[0]',
    '(function(){const __p=T?.remoteJid?.split("@")[0]??"",__n=String(T?.pushName??"").trim(),__d=__n.replace(/\\D/g,"");return __n&&__d!==__p?__n+" · "+__p:__n||__p})()',
  ],
  ["R.pushName||Sy(R.remoteJid)", '(function(){const __p=Sy(R.remoteJid),__n=String(R.pushName??"").trim(),__d=__n.replace(/\\D/g,"");return __n&&__d!==__p?__n+" · "+__p:__n||__p})()'],
  ["y?.pushName||Sy(d)", '(function(){const __p=Sy(d),__n=String(y?.pushName??"").trim(),__d=__n.replace(/\\D/g,"");return __n&&__d!==__p?__n+" · "+__p:__n||__p})()'],
];

for (const [oldS, newS] of reps) {
  const n = s.split(oldS).length - 1;
  if (n === 0) {
    console.error("Pattern not found (bundle may differ):", oldS.slice(0, 60));
    process.exit(1);
  }
  s = s.split(oldS).join(newS);
  console.error("replaced", n, "×", oldS.slice(0, 48) + "…");
}

writeFileSync(outputPath, s);
console.error("Wrote", outputPath, "(" + s.length + " bytes)");
