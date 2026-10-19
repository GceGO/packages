// Builds resources/css/explorer.css into resources/dist/explorer.css (minified), or with --check fails when it is stale.
import { readFile, writeFile } from "node:fs/promises";
import path from "node:path";
import { compile, optimize } from "@tailwindcss/node";
import { Scanner } from "@tailwindcss/oxide";

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), "..");
const entry = path.join(root, "resources/css/explorer.css");
const output = path.join(root, "resources/dist/explorer.css");
const compiler = await compile(await readFile(entry, "utf8"), { base: path.dirname(entry), onDependency: () => {} });
const scanner = new Scanner({ sources: compiler.sources });
const css = optimize(compiler.build(scanner.scan()), { minify: true }).code;

if (process.argv.includes("--check")) {
    const current = await readFile(output, "utf8").catch(() => "");
    process.stdout.write(JSON.stringify({ fresh: current === css, bytes: css.length }));
} else {
    await writeFile(output, css);
    process.stdout.write(JSON.stringify({ written: output, bytes: css.length }));
}
