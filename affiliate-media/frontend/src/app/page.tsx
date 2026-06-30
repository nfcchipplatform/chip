import type { Metadata } from "next";

import Link from "next/link";

import { GenreList } from "@/components/GenreList";
import { ProductCard } from "@/components/ProductCard";
import { getAllGenres, getAllProducts, getGenreCounts } from "@/lib/products";

export const revalidate = 3600;

export const metadata: Metadata = {
  title: "ホーム",
  description: "人気作品・新作をジャンル別に紹介するメディアサイト",
};

export default function HomePage() {
  const products = getAllProducts();
  const genres = getAllGenres();
  const genreCounts = getGenreCounts();

  return (
    <div className="mx-auto max-w-5xl px-4 py-8">
      <section className="mb-10">
        <h1 className="text-2xl font-bold text-white sm:text-3xl">
          厳選作品ピックアップ
        </h1>
        <p className="mt-2 text-sm text-zinc-400">
          全 {products.length.toLocaleString("ja-JP")} 作品を掲載
        </p>
      </section>

      <section className="mb-12">
        <h2 className="mb-4 text-lg font-semibold text-white">ジャンルから探す</h2>
        <GenreList genres={genres.slice(0, 8)} counts={genreCounts} />
        {genres.length > 8 && (
          <Link
            href="/genres"
            className="mt-4 inline-block text-sm text-orange-400 hover:text-orange-300"
          >
            すべてのジャンルを見る →
          </Link>
        )}
      </section>

      <section>
        <h2 className="mb-4 text-lg font-semibold text-white">おすすめ作品</h2>
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
          {products.map((product, index) => (
            <ProductCard key={product.id} product={product} priority={index < 4} />
          ))}
        </div>
      </section>
    </div>
  );
}
