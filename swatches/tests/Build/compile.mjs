// Compiles a CSS entry exactly like @tailwindcss/vite does (compile -> scan sources -> build -> optimize)
// and prints JSON: { css, candidates, files }. Uses the monorepo's installed Tailwind CSS v4 packages.
//
// Usage: node tests/Build/compile.mjs <entry.css> [--no-minify]
import { readFile } from "node:fs/promises";
import path from "node:path";
import { compile, optimize } from "@tailwindcss/node";
import { Scanner } from "@tailwindcss/oxide";

const entry = path.resolve(process.argv[2] ?? "resources/css/build-entry.css");
const minify = !process.argv.includes("--no-minify");
const base = path.dirname(entry);

const compiler = await compile(await readFile(entry, "utf8"), { base, onDependency: () => {} });
const sources = (compiler.root === "none" ? [] : compiler.root === null ? [{ base, pattern: "**/*", negated: false }] : [{ ...compiler.root, negated: false }])
    .concat(compiler.sources);
const scanner = new Scanner({ sources });
const candidates = scanner.scan();
const css = optimize(compiler.build(candidates), { minify }).code;

process.stdout.write(JSON.stringify({ css, candidates: candidates.length, files: scanner.files }));
