import Link from "next/link";

import { genreToSlug } from "@/lib/utils";

interface GenreTagsProps {
  genres: string[];
}

export function GenreTags({ genres }: GenreTagsProps) {
  if (genres.length === 0) return null;

  return (
    <div className="flex flex-wrap gap-2">
      {genres.map((genre) => (
        <Link
          key={genre}
          href={`/genres/${genreToSlug(genre)}`}
          className="rounded-full border border-zinc-700 bg-zinc-800 px-3 py-1 text-xs text-zinc-300 hover:border-orange-500/50 hover:text-white"
        >
          #{genre}
        </Link>
      ))}
    </div>
  );
}
