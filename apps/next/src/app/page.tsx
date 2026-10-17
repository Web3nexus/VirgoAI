import type { Metadata } from 'next'
import { LandingPage } from '@/marketing/LandingPage'

export const metadata: Metadata = {
  title: 'virgoAi: AI Content Studio for video, scripts and campaigns',
  description: 'virgoAi turns one idea, video, or script into a complete content package: story, scenes, images, narration, subtitles and thumbnail.',
  alternates: { canonical: '/' },
  openGraph: {
    type: 'website',
    siteName: 'virgoAi',
    title: 'virgoAi: AI Content Studio',
    description: 'Turn one idea, video, or script into a complete content package with AI.',
    url: '/',
    images: [{ url: '/og-image.png', width: 1200, height: 630 }],
  },
  twitter: { card: 'summary_large_image', title: 'virgoAi: AI Content Studio', description: 'Turn one idea, video, or script into a complete content package with AI.', images: ['/og-image.png'] },
}

export default function Page() {
  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{
          __html: JSON.stringify({
            '@context': 'https://schema.org',
            '@type': 'SoftwareApplication',
            name: 'virgoAi',
            applicationCategory: 'MultimediaApplication',
            operatingSystem: 'Web',
            description: 'AI Content Studio that turns one idea, video, or script into a complete content package.',
            url: '/',
          }),
        }}
      />
      <LandingPage />
    </>
  )
}
