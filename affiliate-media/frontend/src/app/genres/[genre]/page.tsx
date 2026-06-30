import type { Metadata } from "next";
import { notFound } from "next/navigation";

import { ProductCard } from "@/components/ProductCard";
import {
  getAllGenres,
  getProductsByGenre,
} from "@/lib/products";
import { genreToSlug, slugToGenre } from "@/lib/utils";

export const revalidate = 3600;

interface PageProps {
  params: Promise<{ genre: string }>;
}

export async function generateStaticParams() {
  return getAllGenres().map((genre) => ({
    genre: genreToSlug(genre),
  }));
}

export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { genre: genreSlug } = await params;
  const genre = slugToGenre(genreSlug);
  const products = getProductsByGenre(genre);

  return {
    title: `${genre} の作品一覧`,
    description: `${genre} ジャンルの作品 ${products.length} 件を掲載`,
  };
}

export default async function GenrePage({ params }: PageProps) {
  const { genre: genreSlug } = await params;
  const genre = slugToGenre(genreSlug);
  const allGenres = getAllGenres();

  if (!allGenres.includes(genre)) {
    notFound();
  }

  const products = getProductsByGenre(genre);

  return (
    <div className="mx-auto max-w-5xl px-4 py-8">
      <h1 className="text-2xl font-bold text-white">
        <span className="text-orange-400">#{genre}</span> の作品
      </h1>
      <p className="mt-2 text-sm text-zinc-400">
        {products.length.toLocaleString("ja-JP")} 件
      </p>

      {products.length === 0 ? (
        <p className="mt-8 text-zinc-500">該当する作品がありません。</p>
      ) : (
        <div className="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3">
          {products.map((product) => (
            <ProductCard key={product.id} product={product} />
          ))}
        </div>
      )}
    </div>
  );
}
