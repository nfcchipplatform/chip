import type { Product } from "@/types/product";

import { formatPrice, getSiteUrl } from "./utils";

export function buildProductSchema(product: Product) {
  const siteUrl = getSiteUrl();
  const pageUrl = `${siteUrl}/products/${product.slug}`;

  return {
    "@context": "https://schema.org",
    "@type": "Product",
    name: product.title,
    description: product.intro_text ?? product.description ?? product.title,
    image: product.thumbnail_url,
    url: pageUrl,
    ...(product.price !== null && {
      offers: {
        "@type": "Offer",
        price: product.price,
        priceCurrency: "JPY",
        availability: "https://schema.org/InStock",
        url: product.affiliate_url,
      },
    }),
    ...(product.actresses.length > 0 && {
      actor: product.actresses.map((name) => ({
        "@type": "Person",
        name,
      })),
    }),
    ...(product.genres.length > 0 && {
      category: product.genres.join(", "),
    }),
  };
}

export function buildBreadcrumbSchema(items: { name: string; url: string }[]) {
  return {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: items.map((item, index) => ({
      "@type": "ListItem",
      position: index + 1,
      name: item.name,
      item: item.url,
    })),
  };
}

export function buildWebsiteSchema() {
  const siteUrl = getSiteUrl();
  return {
    "@context": "https://schema.org",
    "@type": "WebSite",
    name: process.env.NEXT_PUBLIC_SITE_NAME ?? "Media Pick",
    url: siteUrl,
    potentialAction: {
      "@type": "SearchAction",
      target: `${siteUrl}/genres/{search_term_string}`,
      "query-input": "required name=search_term_string",
    },
  };
}

export function buildProductMetaDescription(product: Product): string {
  if (product.intro_text) return product.intro_text;
  if (product.description) return product.description;
  const parts = [product.title];
  if (product.actresses.length) parts.push(product.actresses.join("、"));
  if (product.price !== null) parts.push(formatPrice(product.price));
  return parts.join(" | ");
}
