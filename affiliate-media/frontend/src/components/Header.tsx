import Link from "next/link";

export function Header() {
  const siteName = process.env.NEXT_PUBLIC_SITE_NAME ?? "Media Pick";

  return (
    <header className="sticky top-0 z-40 border-b border-zinc-800 bg-zinc-950/95 backdrop-blur">
      <div className="mx-auto flex h-14 max-w-5xl items-center justify-between px-4">
        <Link href="/" className="text-lg font-bold tracking-tight text-white">
          {siteName}
        </Link>
        <nav className="flex gap-4 text-sm">
          <Link href="/" className="text-zinc-300 hover:text-white">
            ホーム
          </Link>
          <Link href="/genres" className="text-zinc-300 hover:text-white">
            ジャンル
          </Link>
        </nav>
      </div>
    </header>
  );
}
