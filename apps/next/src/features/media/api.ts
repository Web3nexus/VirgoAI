import { api } from '@/lib/api'

/** Deletes a full video's files. The record stays, so its cost is still on the books. */
export async function deleteProduction(id: string): Promise<void> {
  await api.delete(`/productions/${id}`)
}

/** Deletes a tool run's image or video file. */
export async function deleteGeneration(id: string): Promise<void> {
  await api.delete(`/generations/${id}`)
}
