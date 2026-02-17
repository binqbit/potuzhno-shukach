import type { SupportedLanguage } from "../i18n";

export type SearchResult = {
  title: string;
  url: string;
  snippet: string;
  source?: string | null;
};

export type SearchResponse = {
  query: string;
  lang: SupportedLanguage;
  results: SearchResult[];
  took_ms: number;
  answer?: string | null;
  ai_answer?: string | null;
};

export async function search(
  query: string,
  lang: SupportedLanguage,
  limit = 8,
  signal?: AbortSignal,
  images?: string[],
): Promise<SearchResponse> {
  const base = (import.meta.env.VITE_API_BASE as string | undefined) ?? "/api";
  const body: Record<string, unknown> = { query, lang, limit };
  if (images?.length) body.images = images;
  const resp = await fetch(`${base}/search`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "Accept-Language": lang,
    },
    body: JSON.stringify(body),
    signal,
  });

  if (!resp.ok) {
    try {
      const data = (await resp.json()) as { detail?: string };
      throw new Error(data.detail || `${resp.status} ${resp.statusText}`);
    } catch {
      throw new Error(`${resp.status} ${resp.statusText}`);
    }
  }

  return (await resp.json()) as SearchResponse;
}
