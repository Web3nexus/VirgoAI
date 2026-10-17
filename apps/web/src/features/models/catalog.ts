/**
 * The models shown in the studio menu. Only a model with a verified provider endpoint is "connected" and can be chosen.
 * The rest are listed so the catalogue is complete; they stay disabled until their endpoint is built and tested.
 * `brand` names the provider's mark in components/models/brandPaths.ts. Without one, the model shows its first letter.
 */
export type CatalogModel = { name: string; connected: boolean; brand?: string }

export const VIDEO_MODELS: CatalogModel[] = [
  { name: 'Seedance 2.0', connected: true, brand: 'bytedance' },
  { name: 'Seedance 2.5', connected: false, brand: 'bytedance' },
  { name: 'Kling 4.0', connected: false },
  { name: 'Hailuo 2.3', connected: false },
  { name: 'Hailuo 2.3 Fast', connected: false },
  { name: 'MiniMax H3 Max', connected: false, brand: 'minimax' },
  { name: 'MiniMax H3', connected: false, brand: 'minimax' },
  { name: 'Gemini Omni Flash', connected: false, brand: 'googlegemini' },
  { name: 'Wan 3.0', connected: false },
  { name: 'Sora 2', connected: false },
  { name: 'Kling 3.0', connected: false },
  { name: 'Runway Gen-4', connected: false },
]

export const IMAGE_MODELS: CatalogModel[] = [
  { name: 'GPT Image 1.5', connected: true },
  { name: 'Nano Banana 2.1', connected: false, brand: 'google' },
  { name: 'GPT Image 2.5 Free', connected: false },
  { name: 'GPT Image 2', connected: false },
  { name: 'Midjourney', connected: false },
]

export const TEXT_MODELS: CatalogModel[] = [
  { name: 'Claude Sonnet 5.5', connected: true, brand: 'anthropic' },
  { name: 'Claude Opus 5.5', connected: false, brand: 'anthropic' },
]
