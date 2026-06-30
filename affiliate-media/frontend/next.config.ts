import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  images: {
    remotePatterns: [
      { protocol: "https", hostname: "pics.dmm.co.jp" },
      { protocol: "https", hostname: "images.dmm.co.jp" },
      { protocol: "https", hostname: "awsimgsrc.dmm.co.jp" },
      { protocol: "https", hostname: "images.duga.jp" },
    ],
  },
};

export default nextConfig;
