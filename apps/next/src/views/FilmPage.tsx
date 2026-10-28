'use client'

import { Link, useParams } from '@/lib/router'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useCancelFilm, useFilm } from '@/features/films/hooks'
import { cn } from '@/lib/utils'
import { FILM_STATUS_LABEL, lengthLabel } from './DirectorLabPage'

const PHASES = ['planning', 'board', 'rendering', 'assembling', 'succeeded'] as const

/** One film: the director's story, the board of references, each chapter and shot as it is made, and the finished file. */
export function FilmPage() {
  const { id } = useParams<{ id: string }>()
  const { data: film, isLoading, isError } = useFilm(id)
  const cancel = useCancelFilm(id ?? '')

  if (isLoading) return <Skeleton className="h-96 rounded-xl" />
  if (isError || !film) return <p className="text-sm text-red-300">That film could not be loaded.</p>

  const current = PHASES.indexOf(film.status as (typeof PHASES)[number])
  const stopped = film.status === 'failed'
  const running = !stopped && film.status !== 'succeeded'

  return (
    <div className="space-y-8">
      <Link to="/app/lab" className="text-sm text-muted-foreground hover:text-foreground">← Director Lab</Link>
      <PageHeader title={film.title ?? 'Untitled film'} description={film.logline ?? film.brief} />

      <div className="flex flex-wrap items-center gap-2 text-xs">
        <Badge variant="outline">{lengthLabel(film.length_seconds)}</Badge>
        <Badge variant="outline">{film.format_label}</Badge>
        <Badge variant="outline">{film.quality}</Badge>
        {film.pricing && film.status !== 'succeeded' && <Badge variant="outline">Reserved {film.pricing.reserved_tokens.toLocaleString('en-US', { maximumFractionDigits: 1 })} tokens</Badge>}
        {film.pricing && <Badge variant="outline">Used {film.pricing.spent_tokens.toLocaleString('en-US', { maximumFractionDigits: 1 })} tokens</Badge>}
      </div>

      <ol aria-label="Progress" className="flex flex-wrap gap-2">
        {PHASES.map((phase, index) => (
          <li
            key={phase}
            className={cn(
              'rounded-full border px-3 py-1 text-xs',
              !stopped && index <= current ? 'border-brand/60 bg-brand/15 text-foreground' : 'border-border text-muted-foreground',
            )}
          >
            {FILM_STATUS_LABEL[phase]}
          </li>
        ))}
      </ol>

      <div className="h-2 overflow-hidden rounded-full bg-muted" role="progressbar" aria-valuemin={0} aria-valuemax={100} aria-valuenow={Math.round(film.progress * 100)} aria-label="Shots made">
        <div className="h-full bg-brand transition-all" style={{ width: `${Math.round(film.progress * 100)}%` }} />
      </div>
      <p className="text-xs text-muted-foreground">
        {film.takes_done} of {film.takes_total} shots made
      </p>

      {stopped && <p className="rounded-xl border border-red-400/30 bg-red-400/5 p-4 text-sm text-red-300">{film.error ?? 'The film was stopped.'} Work that was never sent to the video model was returned.</p>}

      {running && (
        <Button variant="outline" size="sm" onClick={() => cancel.mutate()} disabled={cancel.isPending}>
          Stop this film
        </Button>
      )}

      {film.status === 'succeeded' && film.output_url && (
        <Card className="glass overflow-hidden border-border/60">
          <CardContent className="p-0">
            <video src={film.output_url} controls className="mx-auto max-h-[80vh] w-full bg-black object-contain" />
          </CardContent>
        </Card>
      )}

      {film.story && (
        <section aria-label="Story" className="glass space-y-3 rounded-xl border border-border/60 p-5">
          <h2 className="text-sm font-medium">The story</h2>
          {film.style && <p className="text-xs text-muted-foreground">Look: {film.style}</p>}
          <div className="space-y-3 whitespace-pre-line text-sm leading-relaxed">{film.story}</div>
        </section>
      )}

      {film.board && film.board.length > 0 && (
        <section aria-label="Board" className="space-y-3">
          <h2 className="text-sm font-medium">The board</h2>
          <p className="text-xs text-muted-foreground">Every shot is made from these references, so people and places stay the same.</p>
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {film.board.map((item) => (
              <Card key={`${item.kind}-${item.key}`} className="glass border-border/60">
                <CardContent className="space-y-2 p-3">
                  <div className="aspect-[3/4] overflow-hidden rounded-lg bg-card">
                    {item.image_url ? (
                      <img src={item.image_url} alt={item.name} className="size-full object-cover" />
                    ) : (
                      <div className="flex size-full items-center justify-center text-xs text-muted-foreground">{item.status === 'rendering' ? 'Drawing…' : 'Waiting'}</div>
                    )}
                  </div>
                  <p className="text-sm">{item.name}</p>
                  <p className="text-xs capitalize text-muted-foreground">{item.kind}</p>
                </CardContent>
              </Card>
            ))}
          </div>
        </section>
      )}

      {film.chapters && film.chapters.length > 0 && (
        <section aria-label="Chapters" className="space-y-3">
          <h2 className="text-sm font-medium">Chapters</h2>
          <div className="grid gap-4 md:grid-cols-2">
            {film.chapters.map((chapter) => (
              <Card key={chapter.index} className="glass border-border/60">
                <CardContent className="space-y-2 p-4">
                  <div className="flex items-center justify-between gap-2">
                    <p className="text-sm font-medium">Chapter {chapter.index}</p>
                    <Badge variant="outline">{lengthLabel(chapter.seconds)} · {chapter.takes} shots</Badge>
                  </div>
                  <p className="text-sm text-muted-foreground">{chapter.summary}</p>
                  {chapter.output_url && (
                    <video src={chapter.output_url} controls className="w-full rounded-lg bg-black" />
                  )}
                </CardContent>
              </Card>
            ))}
          </div>
        </section>
      )}

      {film.takes && film.takes.length > 0 && (
        <section aria-label="Shots" className="space-y-3">
          <h2 className="text-sm font-medium">Shots</h2>
          <ul className="grid grid-cols-2 gap-2 sm:grid-cols-4 xl:grid-cols-8">
            {film.takes.map((take) => (
              <li key={take.position} className={cn('rounded-lg border px-2 py-2 text-xs', take.status === 'failed' ? 'border-red-400/40 text-red-300' : take.status === 'done' ? 'border-brand/50' : 'border-border text-muted-foreground')} title={take.error ?? undefined}>
                <span className="block font-medium">#{take.position}</span>
                <span className="block capitalize">{take.status}</span>
                <span className="block">{take.seconds} s</span>
              </li>
            ))}
          </ul>
        </section>
      )}
    </div>
  )
}
