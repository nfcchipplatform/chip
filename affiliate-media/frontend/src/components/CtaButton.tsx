import Link from "next/link";

interface CtaButtonProps {
  href: string;
  label?: string;
  size?: "sm" | "md" | "lg";
  fullWidth?: boolean;
  external?: boolean;
}

const sizeClasses = {
  sm: "px-4 py-2 text-sm",
  md: "px-6 py-3 text-base",
  lg: "px-8 py-4 text-lg",
};

export function CtaButton({
  href,
  label = "FANZAで詳細を見る",
  size = "md",
  fullWidth = false,
  external = true,
}: CtaButtonProps) {
  const className = [
    "inline-flex items-center justify-center rounded-xl font-bold text-white",
    "bg-gradient-to-r from-orange-500 to-red-600",
    "shadow-lg shadow-orange-500/30",
    "transition-transform active:scale-95 hover:from-orange-400 hover:to-red-500",
    "focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-offset-2 focus:ring-offset-zinc-950",
    sizeClasses[size],
    fullWidth ? "w-full" : "",
  ].join(" ");

  if (external) {
    return (
      <a
        href={href}
        target="_blank"
        rel="noopener noreferrer sponsored"
        className={className}
      >
        {label}
      </a>
    );
  }

  return (
    <Link href={href} className={className}>
      {label}
    </Link>
  );
}
