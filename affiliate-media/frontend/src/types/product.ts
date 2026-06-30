export interface Product {
  id: string;
  source: string;
  title: string;
  actresses: string[];
  genres: string[];
  thumbnail_url: string;
  affiliate_url: string;
  price: number | null;
  release_date: string | null;
  description: string | null;
  intro_text: string | null;
  slug: string;
  updated_at: string;
}

export interface ProductsPayload {
  generated_at: string;
  count: number;
  products: Product[];
}

export interface GenrePayload {
  generated_at: string;
  genres: Record<string, Product[]>;
}

export interface IndexItem {
  id: string;
  slug: string;
  title: string;
  genres: string[];
  thumbnail_url: string;
  intro_text: string | null;
}
