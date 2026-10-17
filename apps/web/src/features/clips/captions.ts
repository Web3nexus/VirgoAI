import { api } from '@/lib/api'

/**
 * Captions need the signed-in API, which a plain <track src> cannot send. Fetch them with the auth header,
 * then give the player a local object URL.
 */
export async function captionObjectUrl(path: string, format: 'vtt' | 'srt' = 'vtt'): Promise<string> {
  const { data } = await api.get<string>(path, { params: { format }, responseType: 'text' })
  return URL.createObjectURL(new Blob([data], { type: 'text/vtt' }))
}

export async function downloadCaptions(path: string, format: 'vtt' | 'srt', filename: string): Promise<void> {
  const { data } = await api.get<string>(path, { params: { format }, responseType: 'text' })
  const url = URL.createObjectURL(new Blob([data], { type: 'text/plain' }))
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}
