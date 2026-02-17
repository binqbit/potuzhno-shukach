import type { SearchResult } from "../api/search";

function faviconUrl(url: string): string | null {
  try {
    const u = new URL(url);
    return `https://www.google.com/s2/favicons?sz=64&domain_url=${encodeURIComponent(u.origin)}`;
  } catch {
    return null;
  }
}

function formatDisplayUrl(url: string): string {
  try {
    const u = new URL(url);
    const host = u.host;
    const path = u.pathname === "/" ? "" : u.pathname;
    const suffix = path.length > 42 ? `${path.slice(0, 42)}…` : path;
    return `${host}${suffix}`;
  } catch {
    return url;
  }
}

type Props = {
  results: SearchResult[];
  aiAnswer?: string | null;
  aiAnswerLabel?: string;
};

export default function ResultsList({
  results,
  aiAnswer,
  aiAnswerLabel = "AI answer",
}: Props) {

  if (!results.length && !aiAnswer) return null;

  return (
    <div className="mt-4 space-y-3">
      {aiAnswer ? (
        <section className="rounded-2xl bg-gradient-to-r from-cyan-300/15 to-sky-300/15 p-4 ps-card ring-1 ring-cyan-300/20">
          <div className="mb-2 inline-flex items-center gap-2 rounded-full border border-cyan-200/25 bg-cyan-200/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-cyan-100">
            <span aria-hidden="true">✨</span>
            {aiAnswerLabel}
          </div>
          <p className="break-words whitespace-pre-wrap text-sm leading-relaxed text-white/90">
            {aiAnswer}
          </p>
        </section>
      ) : null}
      {results.length ? (
        <ul className="space-y-3">
          {results.map((r) => {
            const fav = faviconUrl(r.url);
            return (
              <li
                key={r.url}
                className="group rounded-2xl p-4 ps-card transition will-change-transform hover:-translate-y-0.5"
              >
                <a
                  href={r.url}
                  target="_blank"
                  rel="noreferrer"
                  className="ps-clamp-2 block break-words text-base font-semibold text-white hover:underline group-hover:text-cyan-200"
                >
                  {r.title}
                </a>
                <div className="mt-2 flex min-w-0 flex-wrap items-center gap-2 text-xs text-white/60">
                  <div className="flex min-w-0 flex-1 items-center gap-2">
                    {fav ? (
                      <img
                        src={fav}
                        alt=""
                        className="h-4 w-4 shrink-0 rounded-sm ring-1 ring-white/10"
                        loading="lazy"
                        decoding="async"
                        referrerPolicy="no-referrer"
                        onError={(e) => {
                          e.currentTarget.style.display = "none";
                        }}
                      />
                    ) : null}
                    <span className="min-w-0 truncate">{formatDisplayUrl(r.url)}</span>
                  </div>
                  {r.source ? (
                    <span className="rounded-full bg-amber-300/10 px-2 py-0.5 text-amber-100 ring-1 ring-amber-300/25">
                      {r.source}
                    </span>
                  ) : null}
                </div>
                <p className="ps-clamp-3 mt-2 break-words hyphens-auto text-sm leading-relaxed text-white/80">
                  {r.snippet}
                </p>
              </li>
            );
          })}
        </ul>
      ) : null}
    </div>
  );
}
