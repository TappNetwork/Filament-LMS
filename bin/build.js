import * as esbuild from "esbuild";

esbuild.build({
  entryPoints: ["./resources/js/vimeo.js"],
  outfile: "./dist/vimeo.js",
  bundle: true,
  mainFields: ["module", "main"],
  platform: "browser",
  treeShaking: true,
  target: ["es2020"],
  minify: true,
});
