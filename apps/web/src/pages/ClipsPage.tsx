import { useState, type FormEvent } from 'react'
import { HugeiconsIcon } from '@hugeicons/react'
import { Download01Icon, Film01Icon, Link01Icon, Upload01Icon } from '@hugeicons/core-free-icons'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { useAddSource, useClipRun, useClipSource, useClipSources, useRemix, useRequestClips, useRequestRemix, useSplitSource } from '@/features/clips/hooks'
import { captionObjectUrl, downloadCaptions } from '@/features/clips/captions'
import { trimClip } from '@/features/clips/api'
import { useEffect } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/api'
import { useQuote } from '@/features/usage/hooks'
import { CostBadge } from '@/components/usage/CostBadge'
import { UsagePanel } from '@/components/usage/UsagePanel'
import { Link } from 'react-router-dom'
import type { Clip, ClipRun, ClipSource } from '@/features/clips/types'
import { cn } from '@/lib/utils'

const MODELS = [
  { key: 'claude-haiku-5-5', label: 'Haiku 5.5 · fastest, lowest cost (recommended)' },
  { key: 'claude-sonnet-5-5', label: 'Sonnet 5.5 · better judgement on subtle requests' },
  { key: 'claude-opus-5-5', label: 'Opus 5.5 · highest quality, highest cost' },
]

const STATUS_LABEL: Record<string, string> = {
  uploaded: 'Received',
  probing: 'Reading the video',
  transcribing: 'Transcribing',
  ready: 'Ready',
  failed: 'Failed',
}

/** Long videos: add one, then ask for clips. A YouTube video plays in full. */
export function ClipsPage() {
  const { data: sources, isLoading } = useClipSources()
  const [selected, setSelected] = useState<string | null>(null)

  return (
    <div className="space-y-8">
      <PageHeader title="Clips" description="Upload a long video and ask for the moments you want. Paste a YouTube link to watch it in full." />

      <div className="grid gap-6 lg:grid-cols-[minmax(0,340px)_minmax(0,1fr)]">
        <div className="space-y-6">
          <AddSource onAdded={(source) => setSelected(source.id)} />

          <section aria-label="Your videos" className="space-y-2">
            <h2 className="text-xs font-medium uppercase tracking-[0.14em] text-muted-foreground">Your videos</h2>
            {isLoading ? (
              <Skeleton className="h-24 rounded-xl" />
            ) : (sources ?? []).length === 0 ? (
              <p className="text-sm text-muted-foreground">Nothing yet.</p>
            ) : (
              <ul className="space-y-2">
                {(sources ?? []).map((source) => (
                  <li key={source.id}>
                    <button
                      type="button"
                      onClick={() => setSelected(source.id)}
                      className={cn(
                        'flex w-full items-center justify-between gap-3 rounded-xl border border-border bg-card/40 p-3 text-left transition-colors hover:border-brand/40',
                        selected === source.id && 'border-brand/60 bg-brand/10',
                      )}
                    >
                      <span className="min-w-0">
                        <span className="block truncate text-sm font-medium">{source.name ?? 'Video'}</span>
                        <span className="text-xs text-muted-foreground">
                          {source.kind === 'youtube' ? 'YouTube · full video' : source.duration_seconds ? formatDuration(source.duration_seconds) : 'Uploaded'}
                        </span>
                      </span>
                      <Badge variant={source.status === 'failed' ? 'destructive' : 'outline'}>{STATUS_LABEL[source.status] ?? source.status}</Badge>
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </section>
        </div>

        <div>{selected ? <SourceDetail key={selected} id={selected} /> : <EmptyState />}</div>
      </div>
    </div>
  )
}

function AddSource({ onAdded }: { onAdded: (source: ClipSource) => void }) {
  const [url, setUrl] = useState('')
  const [progress, setProgress] = useState<number | null>(null)
  const add = useAddSource(onAdded)

  function uploadFile(file: File | undefined) {
    if (!file) return
    setProgress(0)
    add.mutate({ file, onProgress: setProgress }, { onSettled: () => setProgress(null) })
  }

  function addLink(event: FormEvent) {
    event.preventDefault()
    add.mutate({ youtubeUrl: url.trim() }, { onSuccess: () => setUrl('') })
  }

  return (
    <Card className="glass border-border/60">
      <CardContent className="space-y-5 p-5">
        <div>
          <Label htmlFor="clip-file" className="flex items-center gap-2 text-sm">
            <HugeiconsIcon icon={Upload01Icon} size={16} strokeWidth={1.75} aria-hidden /> Upload a video
          </Label>
          <Input id="clip-file" type="file" accept="video/mp4,video/quicktime,video/webm,video/x-matroska" className="mt-2" disabled={add.isPending} onChange={(e) => uploadFile(e.target.files?.[0])} />
          <p className="mt-2 text-xs text-muted-foreground">MP4, MOV, WebM or MKV. Long videos are fine.</p>
          {progress !== null && (
            <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-card" role="progressbar" aria-valuenow={progress} aria-valuemin={0} aria-valuemax={100}>
              <div className="h-full bg-gradient-to-r from-brand to-neon-cyan transition-all" style={{ width: `${progress}%` }} />
            </div>
          )}
        </div>

        <form onSubmit={addLink} className="space-y-2 border-t border-border/60 pt-5">
          <Label htmlFor="clip-youtube" className="flex items-center gap-2 text-sm">
            <HugeiconsIcon icon={Link01Icon} size={16} strokeWidth={1.75} aria-hidden /> Or paste a YouTube link
          </Label>
          <div className="flex gap-2">
            <Input id="clip-youtube" type="url" placeholder="https://youtube.com/watch?v=…" value={url} onChange={(e) => setUrl(e.target.value)} />
            <Button type="submit" variant="outline" disabled={url.trim() === '' || add.isPending}>Add</Button>
          </div>
          <p className="text-xs text-muted-foreground">YouTube videos play in full, in YouTube&apos;s player. We do not download them.</p>
        </form>
      </CardContent>
    </Card>
  )
}

function SourceDetail({ id }: { id: string }) {
  const { data: source, isLoading, isError } = useClipSource(id)
  const [run, setRun] = useState<ClipRun | null>(null)

  if (isLoading) return <Skeleton className="h-96 rounded-xl" />
  if (isError || !source) return <p className="text-sm text-red-300">That video could not be loaded.</p>

  return (
    <div className="space-y-6">
      <Card className="glass border-border/60">
        <CardContent className="space-y-3 p-5">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 className="font-heading text-lg font-semibold">{source.name ?? 'Video'}</h2>
              <p className="text-xs text-muted-foreground">
                {source.kind === 'youtube' ? 'Full video' : source.duration_seconds ? `${formatDuration(source.duration_seconds)}${source.width ? ` · ${source.width}×${source.height}` : ''}` : 'Reading the length'}
              </p>
            </div>
            <Badge variant={source.status === 'failed' ? 'destructive' : 'outline'}>{STATUS_LABEL[source.status] ?? source.status}</Badge>
          </div>
          {source.status === 'failed' && <p className="text-sm text-red-300">{source.error}</p>}
          {source.kind === 'youtube' && source.youtube_embed_url && (
            <div className="aspect-video overflow-hidden rounded-xl border border-border/60 bg-black">
              <iframe
                src={source.youtube_embed_url}
                title={`YouTube video ${source.youtube_watch_url ?? ''}`}
                className="size-full"
                allow="accelerometer; encrypted-media; gyroscope; picture-in-picture"
                allowFullScreen
              />
            </div>
          )}
        </CardContent>
      </Card>

      {source.kind === 'upload' && <ClipForm source={source} onStarted={setRun} />}
      {source.kind === 'upload' && source.status === 'ready' && <SplitPanel sourceId={source.id} onStarted={setRun} />}
      {source.transcript_segments > 0 && <CaptionDownloads sourceId={source.id} name={source.name ?? 'video'} />}
      {source.can_find_moments && <RemixPanel sourceId={source.id} />}

      {run && <RunResult key={run.id} runId={run.id} initial={run} />}

      {source.runs && source.runs.length > 0 && (
        <section aria-label="Earlier requests" className="space-y-3">
          <h3 className="text-xs font-medium uppercase tracking-[0.14em] text-muted-foreground">Earlier requests</h3>
          {source.runs.map((earlier) => (
            <RunResult key={earlier.id} runId={earlier.id} initial={earlier} compact />
          ))}
        </section>
      )}
    </div>
  )
}

function ClipForm({ source, onStarted }: { source: ClipSource; onStarted: (run: ClipRun) => void }) {
  const [request, setRequest] = useState('')
  const [count, setCount] = useState('5')
  const [minSeconds, setMinSeconds] = useState('20')
  const [maxSeconds, setMaxSeconds] = useState('90')
  const [model, setModel] = useState('claude-haiku-5-5')
  const start = useRequestClips(source.id, onStarted)
  const ready = source.status === 'ready'
  const quote = useQuote(ready && request.trim().length >= 3 ? { kind: 'clips', source_id: source.id, model_key: model, request: request.trim() } : null)

  function submit(event: FormEvent) {
    event.preventDefault()
    start.mutate({
      request: request.trim(),
      count: Number(count),
      min_seconds: Number(minSeconds),
      max_seconds: Number(maxSeconds),
      model_key: model,
    })
  }

  return (
    <Card className="glass border-border/60">
      <CardContent className="p-5">
        <form onSubmit={submit} className="space-y-4">
          <div>
            <h3 className="font-heading text-base font-semibold">What clips do you want?</h3>
            <p className="text-xs text-muted-foreground">Describe the moments. The clips are cut from this video, with the times shown.</p>
          </div>
          <Textarea
            rows={3}
            value={request}
            onChange={(e) => setRequest(e.target.value)}
            placeholder="e.g. The strongest arguments about pricing, and the funniest audience moments"
            disabled={!ready}
            aria-label="Clip request"
          />
          <div className="grid gap-4 sm:grid-cols-3">
            <div className="space-y-2">
              <Label htmlFor="clip-count">How many</Label>
              <Input id="clip-count" type="number" min={1} max={10} value={count} onChange={(e) => setCount(e.target.value)} disabled={!ready} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="clip-min">Shortest (s)</Label>
              <Input id="clip-min" type="number" min={5} max={600} value={minSeconds} onChange={(e) => setMinSeconds(e.target.value)} disabled={!ready} />
            </div>
            <div className="space-y-2">
              <Label htmlFor="clip-max">Longest (s)</Label>
              <Input id="clip-max" type="number" min={5} max={900} value={maxSeconds} onChange={(e) => setMaxSeconds(e.target.value)} disabled={!ready} />
            </div>
          </div>
          <div className="space-y-2">
            <Label htmlFor="clip-model">Model</Label>
            <select id="clip-model" className="h-9 w-full rounded-lg border border-border bg-background px-3 text-sm" value={model} onChange={(e) => setModel(e.target.value)} disabled={!ready}>
              {MODELS.map((item) => (
                <option key={item.key} value={item.key}>{item.label}</option>
              ))}
            </select>
          </div>
          <div className="flex items-center justify-between gap-3">
            <p className="text-xs text-muted-foreground">
              {ready ? 'Charged before it starts, based on the transcript size. Refunded if no clip is found.' : 'Available once the transcript is ready.'}
            </p>
            <div className="flex items-center gap-3">
              <CostBadge quote={quote.data} loading={quote.isFetching} />
              <Button type="submit" disabled={!ready || request.trim().length < 3 || start.isPending} className="gap-2">
                <HugeiconsIcon icon={Film01Icon} size={16} strokeWidth={1.75} aria-hidden />
                {start.isPending ? 'Starting…' : 'Find clips'}
              </Button>
            </div>
          </div>
        </form>
      </CardContent>
    </Card>
  )
}

function RunResult({ runId, initial, compact = false }: { runId: string; initial: ClipRun; compact?: boolean }) {
  const { data } = useClipRun(runId)
  const run = data ?? initial

  if (run.status === 'queued' || run.status === 'running') {
    return (
      <Card className="glass border-border/60">
        <CardContent className="flex items-center gap-3 p-5 text-sm text-muted-foreground">
          <span className="size-2 animate-pulse rounded-full bg-brand" aria-hidden /> Finding the moments and cutting them. You can leave this page.
        </CardContent>
      </Card>
    )
  }

  if (run.status === 'failed') {
    return (
      <Card className="glass border-red-400/30">
        <CardContent className="p-5">
          <p className="text-sm text-red-300">{run.error ?? 'The request failed. Nothing was charged.'}</p>
        </CardContent>
      </Card>
    )
  }

  return (
    <section aria-label={`Clips for “${run.request}”`} className="space-y-3">
      <div className="flex flex-wrap items-baseline justify-between gap-2">
        <h3 className="font-heading text-base font-semibold">{run.clips.length} clip{run.clips.length === 1 ? '' : 's'} · “{run.request}”</h3>
        {run.price_usd !== null && <span className="text-xs text-muted-foreground">Cost ${run.price_usd.toFixed(4)}</span>}
      </div>
      <div className={cn('grid gap-4', compact ? 'sm:grid-cols-2' : 'sm:grid-cols-2 xl:grid-cols-3')}>
        {run.clips.map((clip) => (
          <ClipCard key={clip.id} clip={clip} />
        ))}
      </div>
    </section>
  )
}

function ClipCard({ clip }: { clip: Clip }) {
  const [captions, setCaptions] = useState<string | null>(null)
  const [start, setStart] = useState(String(clip.start_seconds))
  const [end, setEnd] = useState(String(clip.end_seconds))
  const [currentUrl, setCurrentUrl] = useState(clip.url)
  const [currentEnd, setCurrentEnd] = useState(clip.end_seconds)
  const [currentStart, setCurrentStart] = useState(clip.start_seconds)
  const queryClient = useQueryClient()
  const [trimming, setTrimming] = useState(false)

  // The captions are fetched with the auth header, then given to the player as a local file.
  useEffect(() => {
    let url: string | null = null
    captionObjectUrl(`/clips/${clip.id}/captions`).then((u) => {
      url = u
      setCaptions(u)
    }).catch(() => setCaptions(null))
    return () => {
      if (url) URL.revokeObjectURL(url)
    }
  }, [clip.id])

  async function saveTrim() {
    setTrimming(true)
    try {
      const result = await trimClip(clip.id, Number(start), Number(end))
      setCurrentUrl(result.url)
      setCurrentStart(result.start_seconds)
      setCurrentEnd(result.end_seconds)
      toast.success('Clip trimmed')
      queryClient.invalidateQueries({ queryKey: ['clips', 'run'] })
    } catch (error) {
      toast.error(apiErrorMessage(error))
    } finally {
      setTrimming(false)
    }
  }

  return (
    <Card className="glass overflow-hidden border-border/60">
      {currentUrl ? (
        <video src={currentUrl} controls preload="metadata" className="aspect-video w-full bg-black" aria-label={clip.title}>
          {captions && <track kind="captions" src={captions} srcLang="en" label="Captions" default />}
        </video>
      ) : (
        <div className="flex aspect-video items-center justify-center bg-card text-xs text-muted-foreground">Preview not ready</div>
      )}
      <CardContent className="space-y-2 p-4">
        <p className="font-medium">{clip.title}</p>
        {clip.reason && <p className="text-xs text-muted-foreground">{clip.reason}</p>}
        <div className="flex items-center justify-between gap-2 text-xs text-muted-foreground">
          <span>{formatClock(currentStart)} – {formatClock(currentEnd)} · {Math.round(currentEnd - currentStart)}s</span>
          {currentUrl && (
            <a href={currentUrl} download className="inline-flex items-center gap-1 text-foreground underline underline-offset-4">
              <HugeiconsIcon icon={Download01Icon} size={14} strokeWidth={1.75} aria-hidden /> Download
            </a>
          )}
        </div>
        <details className="text-xs">
          <summary className="cursor-pointer text-muted-foreground">Edit the cut</summary>
          <div className="mt-2 flex flex-wrap items-end gap-2">
            <div className="space-y-1">
              <Label htmlFor={`start-${clip.id}`}>Start (s)</Label>
              <Input id={`start-${clip.id}`} type="number" step="0.1" min={0} className="h-8 w-24" value={start} onChange={(e) => setStart(e.target.value)} />
            </div>
            <div className="space-y-1">
              <Label htmlFor={`end-${clip.id}`}>End (s)</Label>
              <Input id={`end-${clip.id}`} type="number" step="0.1" min={0} className="h-8 w-24" value={end} onChange={(e) => setEnd(e.target.value)} />
            </div>
            <Button size="sm" variant="outline" disabled={trimming} onClick={saveTrim}>{trimming ? 'Cutting…' : 'Save cut'}</Button>
          </div>
          <p className="mt-2 text-muted-foreground">Captions:</p>
          <div className="mt-1 flex gap-3">
            <button type="button" className="underline underline-offset-4" onClick={() => downloadCaptions(`/clips/${clip.id}/captions`, 'srt', `${clip.title}.srt`)}>Download .srt</button>
            <button type="button" className="underline underline-offset-4" onClick={() => downloadCaptions(`/clips/${clip.id}/captions`, 'vtt', `${clip.title}.vtt`)}>Download .vtt</button>
          </div>
        </details>
      </CardContent>
    </Card>
  )
}

/** Cut the whole video into consecutive parts of the chosen length. Local work, no charge. */
function SplitPanel({ sourceId, onStarted }: { sourceId: string; onStarted: (run: ClipRun) => void }) {
  const [seconds, setSeconds] = useState(60)
  const options = [
    { value: 30, label: '30 seconds' },
    { value: 60, label: '1 minute' },
    { value: 120, label: '2 minutes' },
    { value: 300, label: '5 minutes' },
    { value: 600, label: '10 minutes' },
  ]
  const split = useSplitSource(sourceId, (runId) => onStarted({ id: runId } as ClipRun))

  return (
    <Card className="glass border-border/60">
      <CardContent className="flex flex-wrap items-end gap-4 p-5">
        <div className="space-y-1">
          <h3 className="font-heading text-base font-semibold">Clip the whole video into parts</h3>
          <p className="text-xs text-muted-foreground">Any length. Each part ends at a sentence where it can. Free: no AI charge.</p>
        </div>
        <div className="ml-auto flex items-end gap-2">
          <select
            aria-label="Part length"
            className="h-9 rounded-lg border border-border bg-background px-3 text-sm"
            value={seconds}
            onChange={(e) => setSeconds(Number(e.target.value))}
          >
            {options.map((option) => (
              <option key={option.value} value={option.value}>{option.label}</option>
            ))}
          </select>
          <Button disabled={split.isPending} onClick={() => split.mutate(seconds)}>
            {split.isPending ? 'Starting…' : 'Clip into parts'}
          </Button>
        </div>
      </CardContent>
    </Card>
  )
}

/** Caption files for the whole video. */
function CaptionDownloads({ sourceId, name }: { sourceId: string; name: string }) {
  return (
    <div className="flex items-center justify-end gap-4 text-xs text-muted-foreground">
      <span>Captions for the whole video:</span>
      <button type="button" className="underline underline-offset-4" onClick={() => downloadCaptions(`/clip-sources/${sourceId}/captions`, 'srt', `${name}.srt`)}>.srt</button>
      <button type="button" className="underline underline-offset-4" onClick={() => downloadCaptions(`/clip-sources/${sourceId}/captions`, 'vtt', `${name}.vtt`)}>.vtt</button>
    </div>
  )
}

function EmptyState() {
  return (
    <div className="flex h-72 items-center justify-center rounded-xl border border-dashed border-border text-center text-sm text-muted-foreground">
      Choose a video, or add one to start.
    </div>
  )
}

function formatDuration(seconds: number): string {
  const total = Math.round(seconds)
  const h = Math.floor(total / 3600)
  const m = Math.floor((total % 3600) / 60)
  const s = total % 60
  return h > 0 ? `${h}h ${m}m` : `${m}m ${String(s).padStart(2, '0')}s`
}

/** Position in the video as m:ss or h:mm:ss. */
function formatClock(seconds: number): string {
  const total = Math.floor(seconds)
  const h = Math.floor(total / 3600)
  const m = Math.floor((total % 3600) / 60)
  const s = String(total % 60).padStart(2, '0')
  return h > 0 ? `${h}:${String(m).padStart(2, '0')}:${s}` : `${m}:${s}`
}

/** Writes an original story from the transcript, with its cost beside the button and its tokens after. */
function RemixPanel({ sourceId }: { sourceId: string }) {
  const [remixId, setRemixId] = useState<string | null>(null)
  const [instructions, setInstructions] = useState('')
  const start = useRequestRemix(sourceId, setRemixId)
  const remix = useRemix(remixId)
  const quote = useQuote({ kind: 'clips', source_id: sourceId, model_key: 'claude-haiku-5-5', request: instructions || 'story' })
  const data = remix.data

  return (
    <Card className="glass border-border/60">
      <CardContent className="space-y-4 p-5">
        <div>
          <h3 className="font-heading text-base font-semibold">Write an original story from this video</h3>
          <p className="text-xs text-muted-foreground">
            We read the transcript and write a new story with the same shape, in new words. The original transcript is never shown or copied.
          </p>
        </div>
        <div className="space-y-2">
          <Label htmlFor="remix-instructions">Direction (optional)</Label>
          <Input id="remix-instructions" value={instructions} onChange={(e) => setInstructions(e.target.value)} placeholder="e.g. a warm, comic tone set in a harbour town" />
        </div>
        <div className="flex items-center gap-3">
          <CostBadge quote={quote.data} loading={quote.isFetching} />
          <Button className="gap-2" disabled={start.isPending} onClick={() => start.mutate({ instructions: instructions || undefined, model_key: 'claude-haiku-5-5' })}>
            {start.isPending ? 'Starting…' : 'Write original story'}
          </Button>
        </div>

        {data && (data.status === 'queued' || data.status === 'running') && (
          <p className="text-sm text-muted-foreground">Writing the story…</p>
        )}
        {data && data.status === 'failed' && <p className="text-sm text-red-300">{data.error}</p>}
        {data && data.status === 'succeeded' && data.story && (
          <div className="space-y-3 rounded-xl border border-border/60 bg-background/40 p-4">
            <div className="flex flex-wrap items-center gap-2">
              <h4 className="font-heading text-base font-semibold">{data.title}</h4>
              {data.original && <Badge variant="outline">Original: no copied wording</Badge>}
            </div>
            {data.logline && <p className="text-sm text-muted-foreground">{data.logline}</p>}
            <div className="max-h-96 overflow-y-auto whitespace-pre-wrap text-sm leading-relaxed">{data.story}</div>
            <Button variant="outline" nativeButton={false} render={<Link to={`/tools/story?prompt=${encodeURIComponent(data.story)}`} />}>
              Make a video from this story
            </Button>
          </div>
        )}
        {data && <UsagePanel type="remix" id={data.id} />}
      </CardContent>
    </Card>
  )
}
