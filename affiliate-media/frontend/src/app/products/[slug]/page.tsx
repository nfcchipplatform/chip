import type { Metadata } from "next";
import Image from "next/image";
import { notFound } from "next/navigation";

import { CtaButton } from "@/components/CtaButton";
import { GenreTags } from "@/components/GenreTags";
import { JsonLd } from "@/components/JsonLd";
import { StickyCtaBar } from "@/components/StickyCtaBar";
import { getAllSlugs, getProductBySlug } from "@/lib/products";
import {
  buildBreadcrumbSchema,
  buildProductMetaDescription,
  buildProductSchema,
} from "@/lib/schema";
import { formatDate, formatPrice, getSiteUrl } from "@/lib/utils";

export const revalidate = 3600;

interface PageProps {
  params: Promise<{ slug: string }>;
}

export async function generateStaticParams() {
  return getAllSlugs().map((slug) => ({ slug }));
}

export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { slug } = await params;
  const product = getProductBySlug(slug);
  if (!product) return { title: "作品が見つかりません" };

  const description = buildProductMetaDescription(product);
  const siteUrl = getSiteUrl();

  return {
    title: product.title,
    description,
    openGraph: {
      title: product.title,
      description,
      images: [{ url: product.thumbnail_url, alt: product.title }],
      url: `${siteUrl}/products/${product.slug}`,
    },
    twitter: {
      card: "summary_large_image",
      title: product.title,
      description,
      images: [product.thumbnail_url],
    },
    alternates: {
      canonical: `${siteUrl}/products/${product.slug}`,
    },
  };
}

export default async function ProductPage({ params }: PageProps) {
  const { slug } = await params;
  const product = getProductBySlug(slug);
  if (!product) notFound();

  const siteUrl = getSiteUrl();
  const pageUrl = `${siteUrl}/products/${product.slug}`;
  const intro =
    product.intro_text ?? product.description ?? `${product.title}の詳細情報`;

  return (
    <>
      <JsonLd
        data={[
          buildProductSchema(product),
          buildBreadcrumbSchema([
            { name: "ホーム", url: siteUrl },
            ...(product.genres[0]
              ? [
                  {
                    name: product.genres[0],
                    url: `${siteUrl}/genres/${encodeURIComponent(product.genres[0])}`,
                  },
                ]
              : []),
            { name: product.title, url: pageUrl },
          ]),
        ]}
      />

      <article className="mx-auto max-w-3xl px-4 py-8 pb-28 md:pb-8">
        <div className="overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900">
          <div className="relative aspect-[3/4] w-full max-w-sm mx-auto bg-zinc-800">
            <Image
              src={product.thumbnail_url}
              alt={product.title}
              fill
              sizes="(max-width: 768px) 100vw, 384px"
              className="object-cover"
              priority
            />
          </div>

          <div className="p-5 sm:p-6">
            <h1 className="text-xl font-bold leading-snug text-white sm:text-2xl">
              {product.title}
            </h1>

            <div className="mt-3 flex flex-wrap items-center gap-3 text-sm">
              {product.price !== null && (
                <span className="text-2xl font-bold text-orange-400">
                  {formatPrice(product.price)}
                </span>
              )}
              {product.release_date && (
                <span className="text-zinc-400">
                  発売: {formatDate(product.release_date)}
                </span>
              )}
            </div>

            {product.actresses.length > 0 && (
              <p className="mt-4 text-sm text-zinc-300">
                <span className="text-zinc-500">出演: </span>
                {product.actresses.join("、")}
              </p>
            )}

            <div className="mt-4">
              <GenreTags genres={product.genres} />
            </div>

            <div className="mt-6 rounded-xl bg-zinc-800/50 p-4">
              <h2 className="mb-2 text-sm font-semibold text-zinc-300">作品紹介</h2>
              <p className="text-sm leading-relaxed text-zinc-200">{intro}</p>
            </div>

            <div className="mt-8 hidden md:block">
              <CtaButton
                href={product.affiliate_url}
                size="lg"
                fullWidth
                label="FANZAで詳細・サンプルを見る"
              />
            </div>
          </div>
        </div>
      </article>

      <StickyCtaBar
        href={product.affiliate_url}
        title={product.title}
        price={formatPrice(product.price)}
      />
    </>
  );
}
