export function formatPrice(price: number | null): string {
  if (price === null || price === undefined) return "価格未定";
  return `${price.toLocaleString("ja-JP")}円`;
}

export function formatDate(date: string | null): string {
  if (!date) return "";
  const parsed = new Date(date);
  if (Number.isNaN(parsed.getTime())) return date;
  return parsed.toLocaleDateString("ja-JP", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}

export function genreToSlug(genre: string): string {
  return encodeURIComponent(genre);
}

export function slugToGenre(slug: string): string {
  return decodeURIComponent(slug);
}

export function truncate(text: string, maxLength: number): string {
  if (text.length <= maxLength) return text;
  return text.slice(0, maxLength).trimEnd() + "…";
}

export function getSiteUrl(): string {
  return process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000";
}
