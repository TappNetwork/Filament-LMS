import * as colors from "tailwindcss/colors";

/** @type {import('tailwindcss').Config} */
export default {
  content: ["./resources/views/**/*.blade.php", "./src/**/*.php"],
  darkMode: "class",
  theme: {
    extend: {
      colors: {
        danger: colors.rose,
        primary: colors.amber,
        success: colors.green,
        warning: colors.amber,
      },
    },
  },
  corePlugins: {
    preflight: false,
  },
  plugins: [],
};
