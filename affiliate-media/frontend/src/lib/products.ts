import fs from "fs";
import path from "path";

import type { Product, ProductsPayload, GenrePayload } from "@/types/product";

function resolveDataDir(): string {
  const dataDir =
    process.env.PRODUCTS_DATA_DIR ?? path.join(process.cwd(), "public", "data");
  const productsPath = path.join(dataDir, "products.json");

  if (!fs.existsSync(productsPath)) {
    throw new Error(
      `products.json not found at ${productsPath}. Run: npm run sync-data`
    );
  }

  return dataDir;
}

function readJsonFile<T>(filename: string): T {
  const filePath = path.join(resolveDataDir(), filename);
  const raw = fs.readFileSync(filePath, "utf-8");
  return JSON.parse(raw) as T;
}

export function getAllProducts(): Product[] {
  const payload = readJsonFile<ProductsPayload>("products.json");
  return payload.products;
}

export function getProductBySlug(slug: string): Product | undefined {
  return getAllProducts().find((p) => p.slug === slug);
}

export function getAllSlugs(): string[] {
  return getAllProducts().map((p) => p.slug);
}

export function getAllGenres(): string[] {
  const products = getAllProducts();
  const genreSet = new Set<string>();
  for (const product of products) {
    for (const genre of product.genres) {
      genreSet.add(genre);
    }
  }
  return Array.from(genreSet).sort((a, b) => a.localeCompare(b, "ja"));
}

export function getProductsByGenre(genre: string): Product[] {
  try {
    const payload = readJsonFile<GenrePayload>("products_by_genre.json");
    return payload.genres[genre] ?? [];
  } catch {
    return getAllProducts().filter((p) => p.genres.includes(genre));
  }
}

export function getGenreCounts(): Record<string, number> {
  const counts: Record<string, number> = {};
  for (const product of getAllProducts()) {
    for (const genre of product.genres) {
      counts[genre] = (counts[genre] ?? 0) + 1;
    }
  }
  return counts;
}
