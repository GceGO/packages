// Independent oracle: Lightning CSS (the Rust CSS engine inside Tailwind CSS v4) converts each
// color to an sRGB fallback for old browsers. Prints a JSON list of hex values, one per input.
// Usage: node tests/Build/lightning-oracle.mjs '<json list of CSS colors>'
import { createRequire } from "node:module";
import path from "node:path";

const require = createRequire(path.resolve(process.cwd(), "../../package.json"));
const { transform } = require("lightningcss");
const colors = JSON.parse(process.argv[2] ?? "[]");
const code = colors.map((color, index) => `.c${index}{color:${color}}`).join("\n");
const css = transform({ filename: "oracle.css", code: Buffer.from(code), minify: true, targets: { chrome: 50 << 16 } }).code.toString();
const hex = colors.map((_, index) => {
    const match = css.match(new RegExp(`\\.c${index}\\{color:([^;}]+)`));
    return match ? match[1] : null;
});

process.stdout.write(JSON.stringify(hex));
