export function Footer() {
  return (
    <footer className="mt-auto border-t border-zinc-800 bg-zinc-950 py-8 text-center text-xs text-zinc-500">
      <p>当サイトはアフィリエイトプログラムにより収益を得ています。</p>
      <p className="mt-1">© {new Date().getFullYear()} Media Pick</p>
    </footer>
  );
}
