'use client'

import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from '@/lib/router'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { useFilms, useStartFilm } from '@/features/films/hooks'
import type { Film, FilmStatus } from '@/features/films/api'
import { PROJECT_FORMAT_INFO, PROJECT_FORMATS, type ProjectFormat } from '@/features/projects/types'
import { cn } from '@/lib/utils'

/** Lengths the user can pick. Anything else can be typed in seconds, within the limits. */
const LENGTHS = [
  { seconds: 30, label: '30 seconds' },
  { seconds: 60, label: '1 minute' },
  { seconds: 180, label: '3 minutes' },
  { seconds: 600, label: '10 minutes' },
  { seconds: 1800, label: '30 minutes' },
  { seconds: 3600, label: '1 hour' },
]

const MIN_SECONDS = 15
const MAX_SECONDS = 3600

const LOOKS = [
  { key: 'cinematic', label: 'Cinematic' },
  { key: 'documentary', label: 'Documentary' },
  { key: 'animated', label: 'Animated' },
  { key: 'noir', label: 'Noir' },
  { key: 'neon', label: 'Neon' },
  { key: 'pastel', label: 'Pastel' },
]

export const FILM_STATUS_LABEL: Record<FilmStatus, string> = {
  queued: 'Queued',
  planning: 'Writing the film',
  board: 'Building the board',
  rendering: 'Shooting',
  assembling: 'Joining chapters',
  succeeded: 'Ready',
  failed: 'Stopped',
}

export function lengthLabel(seconds: number): string {
  if (seconds < 60) return `${seconds} s`
  const minutes = Math.floor(seconds / 60)
  const rest = seconds % 60
  return rest === 0 ? `${minutes} min` : `${minutes} min ${rest} s`
}

/** The Director Lab: write a brief, choose a length, and let the director plan, board and shoot the film. */
export function DirectorLabPage() {
  const navigate = useNavigate()
  const { data: films, isLoading } = useFilms()
  const start = useStartFilm()

  const [brief, setBrief] = useState('')
  const [title, setTitle] = useState('')
  const [seconds, setSeconds] = useState(60)
  const [format, setFormat] = useState<ProjectFormat>('landscape_16_9')
  const [quality, setQuality] = useState<'720p' | '1080p'>('720p')
  const [look, setLook] = useState('cinematic')

  const valid = brief.trim().length >= 20 && seconds >= MIN_SECONDS && seconds <= MAX_SECONDS

  async function submit(event: FormEvent) {
    event.preventDefault()
    if (!valid) return

    const film = await start.mutateAsync({
      brief: brief.trim(),
      title: title.trim() || undefined,
      length_seconds: seconds,
      format,
      quality,
      styles: { look },
    })
    navigate(`/app/lab/${film.id}`)
  }

  return (
    <div className="space-y-10">
      <PageHeader
        title="Director Lab"
        description="Describe the film. The director writes the story, builds the cast and locations, then shoots every take and joins them. Up to an hour, in chapters of ten minutes."
      />

      <form onSubmit={submit} className="glass grid gap-6 rounded-xl border border-border/60 p-5 lg:grid-cols-[1fr_20rem]">
        <div className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="film-brief">What is the film about?</Label>
            <Textarea
              id="film-brief"
              rows={6}
              maxLength={4000}
              value={brief}
              onChange={(e) => setBrief(e.target.value)}
              placeholder="A woman finds a brass compass that points home, and follows it through a rainy city to a house she has never seen."
            />
            <p className="text-xs text-muted-foreground">At least 20 characters. The more the director knows about the people and places, the more consistent the film.</p>
          </div>

          <div className="space-y-2">
            <Label htmlFor="film-title">Title (optional)</Label>
            <Input id="film-title" maxLength={200} value={title} onChange={(e) => setTitle(e.target.value)} placeholder="The Compass" />
          </div>
        </div>

        <div className="space-y-5">
          <fieldset className="space-y-2">
            <legend className="text-sm font-medium">How long?</legend>
            <div className="flex flex-wrap gap-2">
              {LENGTHS.map((option) => (
                <button
                  key={option.seconds}
                  type="button"
                  onClick={() => setSeconds(option.seconds)}
                  aria-pressed={seconds === option.seconds}
                  className={cn('rounded-full border px-3 py-1 text-xs transition-colors', seconds === option.seconds ? 'border-brand/60 bg-brand/15 text-foreground' : 'border-border text-muted-foreground hover:text-foreground')}
                >
                  {option.label}
                </button>
              ))}
            </div>
            <div className="flex items-center gap-2 pt-1">
              <Label htmlFor="film-seconds" className="text-xs text-muted-foreground">Seconds</Label>
              <Input id="film-seconds" type="number" min={MIN_SECONDS} max={MAX_SECONDS} value={seconds} onChange={(e) => setSeconds(Number(e.target.value))} className="w-28" />
              <span className="text-xs text-muted-foreground">{lengthLabel(seconds)}</span>
            </div>
          </fieldset>

          <div className="space-y-2">
            <Label htmlFor="film-format">Format</Label>
            <select id="film-format" value={format} onChange={(e) => setFormat(e.target.value as ProjectFormat)} className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm">
              {PROJECT_FORMATS.map((key) => (
                <option key={key} value={key} className="bg-background">
                  {PROJECT_FORMAT_INFO[key].label}
                </option>
              ))}
            </select>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-2">
              <Label htmlFor="film-quality">Quality</Label>
              <select id="film-quality" value={quality} onChange={(e) => setQuality(e.target.value as '720p' | '1080p')} className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm">
                <option value="720p" className="bg-background">720p</option>
                <option value="1080p" className="bg-background">1080p</option>
              </select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="film-look">Look</Label>
              <select id="film-look" value={look} onChange={(e) => setLook(e.target.value)} className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm">
                {LOOKS.map((option) => (
                  <option key={option.key} value={option.key} className="bg-background">
                    {option.label}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <Button type="submit" className="w-full" disabled={!valid || start.isPending}>
            {start.isPending ? 'Starting…' : 'Direct this film'}
          </Button>
          <p className="text-xs text-muted-foreground">The full price is reserved first. Only the work that is sent to the video model is charged; the rest is returned.</p>
        </div>
      </form>

      <section aria-label="Your films" className="space-y-4">
        <h2 className="text-sm font-medium text-muted-foreground">Your films</h2>
        {isLoading && <Skeleton className="h-24 rounded-xl" />}
        {!isLoading && (films?.length ?? 0) === 0 && <p className="text-sm text-muted-foreground">No films yet.</p>}
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {films?.map((film) => <FilmCard key={film.id} film={film} />)}
        </div>
      </section>
    </div>
  )
}

function FilmCard({ film }: { film: Film }) {
  return (
    <Link to={`/app/lab/${film.id}`} className="block">
      <Card className="glass h-full border-border/60 transition-colors hover:border-brand/50">
        <CardContent className="space-y-3 p-4">
          <div className="flex items-start justify-between gap-2">
            <p className="line-clamp-2 text-sm font-medium">{film.title ?? film.brief}</p>
            <Badge variant="outline">{lengthLabel(film.length_seconds)}</Badge>
          </div>
          <div className="h-1.5 overflow-hidden rounded-full bg-muted">
            <div className="h-full bg-brand transition-all" style={{ width: `${Math.round(film.progress * 100)}%` }} />
          </div>
          <p className="text-xs text-muted-foreground">
            {FILM_STATUS_LABEL[film.status]} · {film.takes_done} of {film.takes_total} shots
          </p>
        </CardContent>
      </Card>
    </Link>
  )
}
