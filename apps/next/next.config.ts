import type { NextConfig } from 'next'

const nextConfig: NextConfig = {
  // Dev-only: lets the browser reach the dev server's hot reload from these hosts.
  allowedDevOrigins: ['127.0.0.1', 'localhost'],
  // The Laravel API. Browser requests to /api/v1 are forwarded here, so the app and the API share one origin.
  async rewrites() {
    return [{ source: '/api/:path*', destination: `${process.env.API_ORIGIN ?? 'http://localhost:8000'}/api/:path*` }]
  },
}

export default nextConfig
