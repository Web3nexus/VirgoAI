import { api } from '@/lib/api'

/** Uploads a reference image for a run. Returns the public url the provider can fetch. */
export async function uploadReference(file: File): Promise<string> {
  const body = new FormData()
  body.append('image', file)
  const { data } = await api.post<{ url: string }>('/uploads/reference', body)
  return data.url
}
