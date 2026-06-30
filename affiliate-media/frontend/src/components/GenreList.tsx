import Link from "next/link";

import { genreToSlug } from "@/lib/utils";

interface GenreListProps {
  genres: string[];
  counts?: Record<string, number>;
}

export function GenreList({ genres, counts }: GenreListProps) {
  return (
    <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
      {genres.map((genre) => (
        <li key={genre}>
          <Link
            href={`/genres/${genreToSlug(genre)}`}
            className="flex items-center justify-between rounded-xl border border-zinc-800 bg-zinc-900 px-4 py-3 text-sm text-zinc-200 transition hover:border-orange-500/50 hover:bg-zinc-800"
          >
            <span className="line-clamp-1 font-medium">{genre}</span>
            {counts?.[genre] !== undefined && (
              <span className="ml-2 shrink-0 text-xs text-zinc-500">
                {counts[genre]}
              </span>
            )}
          </Link>
        </li>
      ))}
    </ul>
  );
}
