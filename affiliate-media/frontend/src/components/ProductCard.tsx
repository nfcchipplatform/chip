import Image from "next/image";
import Link from "next/link";

import type { Product } from "@/types/product";
import { formatPrice, truncate } from "@/lib/utils";

interface ProductCardProps {
  product: Product;
  priority?: boolean;
}

export function ProductCard({ product, priority = false }: ProductCardProps) {
  const summary =
    product.intro_text ?? product.description ?? product.title;

  return (
    <article className="group overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900 transition hover:border-zinc-600">
      <Link href={`/products/${product.slug}`} className="block">
        <div className="relative aspect-[3/4] overflow-hidden bg-zinc-800">
          <Image
            src={product.thumbnail_url}
            alt={product.title}
            fill
            sizes="(max-width: 640px) 50vw, 33vw"
            className="object-cover transition duration-300 group-hover:scale-105"
            priority={priority}
          />
          {product.price !== null && (
            <span className="absolute left-2 top-2 rounded-lg bg-black/70 px-2 py-1 text-xs font-bold text-orange-300">
              {formatPrice(product.price)}
            </span>
          )}
        </div>
        <div className="p-3">
          <h2 className="line-clamp-2 text-sm font-semibold leading-snug text-white">
            {product.title}
          </h2>
          {product.actresses.length > 0 && (
            <p className="mt-1 line-clamp-1 text-xs text-zinc-400">
              {product.actresses.join(" / ")}
            </p>
          )}
          <p className="mt-2 line-clamp-2 text-xs text-zinc-500">
            {truncate(summary, 80)}
          </p>
        </div>
      </Link>
    </article>
  );
}
