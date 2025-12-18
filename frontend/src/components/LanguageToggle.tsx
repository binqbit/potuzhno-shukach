import i18n, { languageStorageKey, supportedLanguages, type SupportedLanguage } from "../i18n";

const labels: Record<SupportedLanguage, string> = {
  en: "EN",
  uk: "UA",
};

function normalize(lang: string): SupportedLanguage {
  return lang.startsWith("uk") ? "uk" : "en";
}

export default function LanguageToggle() {
  const current = normalize(i18n.language);

  function setLanguage(next: SupportedLanguage) {
    if (!supportedLanguages.includes(next)) return;
    localStorage.setItem(languageStorageKey, next);
    void i18n.changeLanguage(next);
  }

  return (
    <div className="inline-flex items-center gap-1 rounded-full p-1 ps-card">
      {supportedLanguages.map((lang) => {
        const active = lang === current;
        return (
          <button
            key={lang}
            type="button"
            onClick={() => setLanguage(lang)}
            className={[
              "rounded-full px-3 py-1.5 text-xs font-semibold tracking-wide transition",
              "focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300/60",
              active
                ? "bg-gradient-to-r from-sky-300 to-amber-300 text-slate-950 shadow-[0_0_22px_rgba(34,211,238,0.12),0_0_18px_rgba(250,204,21,0.10)]"
                : "text-white/80 hover:bg-white/5 hover:text-white",
            ].join(" ")}
            aria-pressed={active}
          >
            {labels[lang]}
          </button>
        );
      })}
    </div>
  );
}
