import { useEffect, useMemo, useRef, useState } from "react";
import { useTranslation } from "react-i18next";

import i18n from "./i18n";
import { search, type SearchResult } from "./api/search";
import LanguageToggle from "./components/LanguageToggle";
import ResultsList from "./components/ResultsList";

function normalizeLang(v: string): "en" | "uk" {
  return v.toLowerCase().startsWith("uk") ? "uk" : "en";
}

type AttachedImage = {
  id: string;
  file: File;
  previewUrl: string;
};

const MAX_IMAGES = 6;

function uid(): string {
  if (typeof crypto !== "undefined" && "randomUUID" in crypto) return crypto.randomUUID();
  return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function fileToDataUrl(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result || ""));
    reader.onerror = () => reject(reader.error ?? new Error("Failed to read file"));
    reader.readAsDataURL(file);
  });
}

export default function App() {
  const { t } = useTranslation();
  const lang = useMemo(() => normalizeLang(i18n.language), [i18n.language]);

  const inputRef = useRef<HTMLInputElement | null>(null);
  const abortRef = useRef<AbortController | null>(null);
  const imagesRef = useRef<AttachedImage[]>([]);

  const [query, setQuery] = useState("");
  const [results, setResults] = useState<SearchResult[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [hasSearched, setHasSearched] = useState(false);
  const [images, setImages] = useState<AttachedImage[]>([]);

  useEffect(() => {
    return () => {
      abortRef.current?.abort();
      for (const img of imagesRef.current) URL.revokeObjectURL(img.previewUrl);
    };
  }, []);

  useEffect(() => {
    imagesRef.current = images;
  }, [images]);

  function addImages(files: File[]) {
    setImages((prev) => {
      const remaining = MAX_IMAGES - prev.length;
      if (remaining <= 0) return prev;

      const additions = files.slice(0, remaining).map((file) => ({
        id: uid(),
        file,
        previewUrl: URL.createObjectURL(file),
      }));

      return [...prev, ...additions];
    });
  }

  function removeImage(id: string) {
    setImages((prev) => {
      const target = prev.find((img) => img.id === id);
      if (target) URL.revokeObjectURL(target.previewUrl);
      return prev.filter((img) => img.id !== id);
    });
  }

  function onPasteSearch(e: React.ClipboardEvent<HTMLInputElement>) {
    if (loading) return;
    const files: File[] = [];
    for (const item of Array.from(e.clipboardData.items)) {
      if (item.kind === "file" && item.type.startsWith("image/")) {
        const file = item.getAsFile();
        if (file) files.push(file);
      }
    }
    if (!files.length) return;

    const pastedText = e.clipboardData.getData("text");
    if (!pastedText) e.preventDefault();

    addImages(files);
  }

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    const q = query.trim();
    if (!q && !images.length) return;

    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setLoading(true);
    setHasSearched(true);
    setError(null);
    setResults([]);
    try {
      const imageDataUrls = images.length ? await Promise.all(images.map((img) => fileToDataUrl(img.file))) : undefined;
      if (controller.signal.aborted) return;

      const data = await search(q, lang, 8, controller.signal, imageDataUrls);
      setResults(data.results);
    } catch (err) {
      if (err instanceof Error && err.name === "AbortError") return;
      if (err instanceof Error && err.message) setError(err.message);
      else setError(t("errorGeneric"));
    } finally {
      setLoading(false);
    }
  }

  function clearQuery() {
    abortRef.current?.abort();
    setQuery("");
    setError(null);
    setResults([]);
    setHasSearched(false);
    queueMicrotask(() => inputRef.current?.focus());
  }

  return (
    <div className="relative min-h-screen overflow-x-hidden ps-bg text-white">
      <div className="pointer-events-none absolute inset-0 opacity-35 ps-grid" />

      <header className="relative mx-auto flex w-full max-w-4xl flex-col gap-4 px-4 py-6 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex items-center gap-3">
          <div className="relative rounded-2xl bg-gradient-to-r from-sky-300 to-amber-300 p-[1px] shadow-[0_0_26px_rgba(34,211,238,0.14),0_0_22px_rgba(250,204,21,0.10)]">
            <div className="grid h-10 w-10 place-items-center rounded-2xl bg-slate-950/70 ring-1 ring-white/10 backdrop-blur">
              <span className="select-none text-sm font-black tracking-tight text-white">PS</span>
            </div>
          </div>

          <div className="min-w-0">
            <div className="text-lg font-extrabold leading-none tracking-tight sm:text-xl">
              <span className="relative inline-block">
                <span className="bg-gradient-to-r from-sky-200 via-cyan-200 to-amber-200 bg-clip-text text-transparent drop-shadow-[0_0_18px_rgba(34,211,238,0.22)]">
                  {t("appName")}
                </span>
                <span className="pointer-events-none absolute -bottom-1 left-0 h-[2px] w-full bg-gradient-to-r from-sky-300 via-cyan-300 to-amber-300 opacity-70 blur-[0.5px]" />
              </span>
            </div>
            <div className="ps-clamp-2 mt-1 hidden max-w-[26rem] text-xs text-white/55 sm:block">
              {t("tagline")}
            </div>
          </div>
        </div>
        <LanguageToggle />
      </header>

      <main className="relative mx-auto w-full max-w-4xl px-4 pb-16 pt-2">
        <section className="ps-card rounded-3xl p-5 sm:p-8">
          <div className="text-center sm:text-left">
            <h1 className="text-2xl font-bold leading-tight tracking-tight sm:text-4xl">
              <span className="bg-gradient-to-r from-sky-200 via-cyan-200 to-amber-200 bg-clip-text text-transparent drop-shadow-[0_0_28px_rgba(34,211,238,0.22)]">
                {t("appName")}
              </span>
            </h1>
            <p className="mt-2 max-w-prose text-sm text-white/70 sm:text-base">{t("searchTagline")}</p>
          </div>

          <form onSubmit={onSubmit} className="mt-6 flex flex-col gap-3 sm:flex-row sm:items-stretch">
            <div className="relative w-full">
              <input
                ref={inputRef}
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                onPaste={onPasteSearch}
                placeholder={t("searchPlaceholder")}
                className={[
                  "w-full rounded-2xl bg-slate-950/40 px-4 py-3 pr-12 text-sm text-white",
                  "ring-1 ring-white/10 placeholder:text-white/40",
                  "focus:outline-none focus:ring-2 focus:ring-sky-300/60",
                  "focus:shadow-[0_0_0_1px_rgba(34,211,238,0.22),0_0_34px_rgba(34,211,238,0.10),0_0_28px_rgba(250,204,21,0.09)]",
                ].join(" ")}
                autoFocus
              />
              {query.length ? (
                <button
                  type="button"
                  onMouseDown={(e) => e.preventDefault()}
                  onClick={clearQuery}
                  className={[
                    "absolute right-3 top-1/2 -translate-y-1/2 rounded-full p-2",
                    "text-white/60 hover:bg-white/5 hover:text-white",
                    "focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300/60",
                    "disabled:cursor-not-allowed disabled:opacity-50",
                  ].join(" ")}
                  aria-label={t("clearSearch")}
                  title={t("clearSearch")}
                  disabled={loading}
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path
                      d="M18 6L6 18M6 6l12 12"
                      stroke="currentColor"
                      strokeWidth="2.5"
                      strokeLinecap="round"
                    />
                  </svg>
                </button>
              ) : null}
            </div>
            <button
              type="submit"
              className={[
                "w-full shrink-0 whitespace-nowrap rounded-2xl px-5 py-3 text-sm font-semibold sm:w-auto",
                "bg-gradient-to-r from-sky-300 to-amber-300 text-slate-950",
                "hover:from-sky-200 hover:to-amber-200",
                "shadow-[0_0_0_1px_rgba(255,255,255,0.10),0_0_26px_rgba(34,211,238,0.12),0_0_22px_rgba(250,204,21,0.10)]",
                "focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300/60",
                "disabled:cursor-not-allowed disabled:opacity-60",
              ].join(" ")}
              disabled={loading}
            >
              {loading ? t("searching") : t("search")}
            </button>
          </form>

          {images.length ? (
            <div className="mt-4 flex flex-wrap gap-2">
              {images.map((img) => (
                <div
                  key={img.id}
                  className="relative h-20 w-20 overflow-hidden rounded-2xl bg-white/5 ring-1 ring-white/10"
                >
                  <img src={img.previewUrl} alt="" className="h-full w-full object-cover" />
                  <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-slate-950/55 to-transparent" />
                  <button
                    type="button"
                    onMouseDown={(e) => e.preventDefault()}
                    onClick={() => removeImage(img.id)}
                    className={[
                      "absolute right-1.5 top-1.5 rounded-full bg-slate-950/60 p-1.5 text-white/80",
                      "ring-1 ring-white/10 backdrop-blur",
                      "hover:bg-white/10 hover:text-white",
                      "focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300/60",
                      "disabled:cursor-not-allowed disabled:opacity-50",
                    ].join(" ")}
                    aria-label={t("removeImage")}
                    title={t("removeImage")}
                    disabled={loading}
                  >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                      <path
                        d="M18 6L6 18M6 6l12 12"
                        stroke="currentColor"
                        strokeWidth="2.5"
                        strokeLinecap="round"
                      />
                    </svg>
                  </button>
                </div>
              ))}
            </div>
          ) : null}

          {!hasSearched ? <p className="mt-4 text-sm text-white/60">{t("emptyHint")}</p> : null}

          {error ? (
            <div className="mt-4 rounded-2xl bg-red-500/10 p-4 text-sm text-red-200 ring-1 ring-red-500/25">
              <span className="break-words">{error}</span>
            </div>
          ) : null}
        </section>

        {results.length ? (
          <div className="mt-8 flex flex-wrap items-center justify-between gap-2 text-sm font-semibold text-white/80">
            <div>{t("results")}</div>
            <div className="text-xs font-medium text-white/50">{results.length}</div>
          </div>
        ) : null}

        {results.length ? <ResultsList results={results} /> : null}

        {!loading && !error && hasSearched && !results.length ? (
          <p className="mt-6 text-sm text-white/60">{t("noResults")}</p>
        ) : null}
      </main>
    </div>
  );
}
