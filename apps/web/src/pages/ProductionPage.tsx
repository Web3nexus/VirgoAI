import { Link, useNavigate, useParams } from 'react-router-dom'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { UsagePanel } from '@/components/usage/UsagePanel'
import { useProduction } from '@/features/productions/hooks'
import { deleteProduction } from '@/features/media/api'
import { DeleteMediaButton } from '@/components/media/DeleteMediaButton'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/api'
import { cn } from '@/lib/utils'

const PHASES = [
  { key: 'queued', label: 'Script' },
  { key: 'keyframing', label: 'Keyframes' },
  { key: 'animating', label: 'Motion' },
  { key: 'assembling', label: 'Join' },
  { key: 'succeeded', label: 'Ready' },
]

/** One full video: where it is, each scene as it is made, and the finished file. */
export function ProductionPage() {
  const { id } = useParams()
  const { data: production, isLoading, isError } = useProduction(id)
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  if (isLoading) return <Skeleton className="h-96 rounded-xl" />
  if (isError || !production) return <p className="text-sm text-red-300">That production could not be loaded.</p>

  const current = PHASES.findIndex((phase) => phase.key === production.status)
  const failed = production.status === 'failed'

  return (
    <div className="space-y-8">
      <Link to="/" className="text-sm text-muted-foreground hover:text-foreground">← Home</Link>
      <PageHeader
        title={production.script?.title ?? 'Full video'}
        description={production.script?.logline ?? production.prompt}
      />

      {production.output_url && (
        <div className="flex flex-wrap items-center justify-between gap-3">
          <p className="text-xs text-muted-foreground">Files are removed automatically after 30 days. Delete one sooner from here.</p>
          <DeleteMediaButton
            label="Delete video"
            onConfirm={async () => {
              try {
                await deleteProduction(production.id)
                toast('Video deleted')
                await queryClient.invalidateQueries({ queryKey: ['productions'] })
                navigate('/')
              } catch (error) {
                toast.error(apiErrorMessage(error))
              }
            }}
          />
        </div>
      )}

      <div className="flex flex-wrap items-center gap-2 text-xs">
        <Badge variant="outline">{production.format_label} · {production.width} × {production.height}</Badge>
        <Badge variant="outline">{production.quality}</Badge>
        {production.reserved_tokens !== null && production.status !== 'succeeded' && (
          <Badge variant="outline">Up to {production.reserved_tokens.toLocaleString('en-US', { maximumFractionDigits: 1 })} tokens reserved</Badge>
        )}
      </div>

      <ol aria-label="Progress" className="flex flex-wrap gap-2">
        {PHASES.map((phase, index) => (
          <li
            key={phase.key}
            className={cn(
              'rounded-full border px-3 py-1 text-xs',
              failed ? 'border-border text-muted-foreground' : index <= current ? 'border-brand/60 bg-brand/15 text-foreground' : 'border-border text-muted-foreground',
            )}
          >
            {phase.label}
          </li>
        ))}
      </ol>

      {failed && <p className="rounded-xl border border-red-400/30 bg-red-400/5 p-4 text-sm text-red-300">{production.error ?? 'The video could not be made.'} Your reserved tokens were returned.</p>}

      {production.status === 'succeeded' && production.output_url && (
        <Card className="glass overflow-hidden border-border/60">
          <CardContent className="p-0">
            <video src={production.output_url} controls className="mx-auto max-h-[80vh] w-full bg-black object-contain" style={{ aspectRatio: `${production.width} / ${production.height}` }} />
          </CardContent>
        </Card>
      )}

      {production.scenes && production.scenes.length > 0 && (
        <section aria-label="Scenes" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {production.scenes.map((scene) => (
            <Card key={scene.position} className="glass border-border/60">
              <CardContent className="space-y-3 p-4">
                <div className="aspect-video overflow-hidden rounded-lg bg-card">
                  {scene.clip_url ? (
                    <video src={scene.clip_url} controls className="size-full object-cover" />
                  ) : scene.keyframe_url ? (
                    <img src={scene.keyframe_url} alt={scene.title} className="size-full object-cover" />
                  ) : (
                    <div className="flex size-full items-center justify-center text-xs text-muted-foreground">Scene {scene.position}</div>
                  )}
                </div>
                <p className="line-clamp-2 text-sm">{scene.title}</p>
                <p className="text-xs capitalize text-muted-foreground">{scene.status}</p>
              </CardContent>
            </Card>
          ))}
        </section>
      )}

      {production.usage && <UsagePanel type="production" id={production.id} />}
    </div>
  )
}
