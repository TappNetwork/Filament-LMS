import * as esbuild from "esbuild";

esbuild.build({
  entryPoints: ["./resources/js/filament-lms.js"],
  outfile: "./dist/filament-lms.js",
  bundle: true,
  mainFields: ["module", "main"],
  platform: "browser",
  treeShaking: true,
  target: ["es2020"],
  minify: true,
});
