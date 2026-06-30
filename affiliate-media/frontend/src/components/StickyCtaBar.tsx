import { CtaButton } from "./CtaButton";

interface StickyCtaBarProps {
  href: string;
  title: string;
  price: string;
}

export function StickyCtaBar({ href, title, price }: StickyCtaBarProps) {
  return (
    <div className="fixed inset-x-0 bottom-0 z-50 border-t border-zinc-800 bg-zinc-950/95 p-3 backdrop-blur md:hidden">
      <div className="mb-2 truncate text-xs text-zinc-400">{title}</div>
      <div className="flex items-center gap-3">
        <span className="shrink-0 text-sm font-bold text-orange-300">{price}</span>
        <CtaButton href={href} size="sm" fullWidth label="今すぐチェック" />
      </div>
    </div>
  );
}
