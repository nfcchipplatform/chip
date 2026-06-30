import type { Metadata } from "next";

import { GenreList } from "@/components/GenreList";
import { getAllGenres, getGenreCounts } from "@/lib/products";

export const revalidate = 3600;

export const metadata: Metadata = {
  title: "ジャンル一覧",
  description: "ジャンル別に作品を探す",
};

export default function GenresPage() {
  const genres = getAllGenres();
  const counts = getGenreCounts();

  return (
    <div className="mx-auto max-w-5xl px-4 py-8">
      <h1 className="mb-2 text-2xl font-bold text-white">ジャンル一覧</h1>
      <p className="mb-8 text-sm text-zinc-400">
        {genres.length} ジャンルから作品を探せます
      </p>
      <GenreList genres={genres} counts={counts} />
    </div>
  );
}
