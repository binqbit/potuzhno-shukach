import i18n from "i18next";
import { initReactI18next } from "react-i18next";

import en from "./locales/en.json";
import uk from "./locales/uk.json";

export const supportedLanguages = ["uk", "en"] as const;
export type SupportedLanguage = (typeof supportedLanguages)[number];

export const languageStorageKey = "ps_lang";

function detectInitialLanguage(): SupportedLanguage {
  const saved = localStorage.getItem(languageStorageKey);
  if (saved === "en" || saved === "uk") return saved;

  const nav = (navigator.language || "").toLowerCase();
  if (nav.startsWith("uk") || nav.startsWith("ua")) return "uk";
  if (nav.startsWith("en")) return "en";
  return "uk";
}

i18n.use(initReactI18next).init({
  resources: {
    en: { translation: en },
    uk: { translation: uk },
  },
  lng: detectInitialLanguage(),
  fallbackLng: "uk",
  interpolation: { escapeValue: false },
});

function updateDocumentMeta() {
  document.documentElement.lang = i18n.language;
  document.title = `${i18n.t("appName")} — ${i18n.t("tagline")}`;
}

updateDocumentMeta();
i18n.on("languageChanged", () => updateDocumentMeta());

export default i18n;
