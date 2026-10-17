import { useMemo, useState } from 'react'
import { toast } from 'sonner'
import { useQueryClient } from '@tanstack/react-query'
import { DeleteMediaButton } from '@/components/media/DeleteMediaButton'
import { deleteGeneration } from '@/features/media/api'
import { apiErrorMessage } from '@/lib/api'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { ArrowLeft, Copy, Loader2, Play } from 'lucide-react'
import { GenerationOutput } from '@/components/tools/GenerationOutput'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { useCurrentUser } from '@/features/auth/hooks'
import { useProjects } from '@/features/projects/hooks'
import { PROJECT_FORMAT_INFO, type ProjectFormat } from '@/features/projects/types'
import { useGenerationPoll, useGenerations, useRunTool, useTools } from '@/features/tools/hooks'
import { useQuote } from '@/features/usage/hooks'
import { useStyles, type StyleChoice } from '@/features/styles/hooks'
import { trimGeneration } from '@/features/clips/api'
import { Input } from '@/components/ui/input'
import { CostBadge } from '@/components/usage/CostBadge'
import type { Generation, StudioTool } from '@/features/tools/types'
import { formatTime } from '@/features/workflows/timeline'
import { useSkills } from '@/features/skills/hooks'
import { usd } from '@/lib/money'
import { cn } from '@/lib/utils'

const NONE = '__none__'

function ToolWorkspace({ tool }: { tool: StudioTool }) {
  const [searchParams] = useSearchParams()
  const usableModels = tool.models.filter((model) => model.usable)
  const [prompt, setPrompt] = useState(() => searchParams.get('prompt') ?? '')
  const [modelKey, setModelKey] = useState<string>(usableModels[0]?.key ?? tool.models[0]?.key ?? '')
  const [size, setSize] = useState<string>(() => {
    const requested = searchParams.get('size')
    return requested && Object.keys(PROJECT_FORMAT_INFO).includes(requested) ? requested : tool.default_size
  })
  const [resolution, setResolution] = useState<'480p' | '720p' | '1080p'>('720p')
  const [projectId, setProjectId] = useState<string>(NONE)
  const choiceEntries = Object.entries(tool.choices ?? {})
  const [choices, setChoices] = useState<Record<string, string>>(() => {
    const initial: Record<string, string> = Object.fromEntries(choiceEntries.map(([key, choice]) => [key, choice.default]))
    // Chat Mode answers in plain prose, not the director's JSON script.
    if (searchParams.get('chat') === '1' && 'skill' in initial) initial.skill = 'plain'
    return initial
  })
  const [latest, setLatest] = useState<Generation | null>(null)
  const [keyframe, setKeyframe] = useState('')
  const [styles, setStyles] = useState<StyleChoice>({})
  const skillKey = searchParams.get('skill') ?? undefined
  // A reference image from the studio. For images it is the look the new image is edited from.
  const referenceUrl = searchParams.get('ref') ?? undefined
  const { data: skills = [] } = useSkills()
  const skillLabel = skills.find((skill) => skill.key === skillKey)?.label
  const [trimStart, setTrimStart] = useState('0')
  const [trimEnd, setTrimEnd] = useState('5')
  const [trimming, setTrimming] = useState(false)
  const styleCatalog = useStyles()

  const { data: me } = useCurrentUser()
  const { data: projectPage } = useProjects({ status: 'all' })
  const projects = projectPage?.data ?? []
  const history = useGenerations(tool.key, 12)
  const queryClient = useQueryClient()
  const run = useRunTool(tool.key)
  const polled = useGenerationPoll(latest)
  // The latest poll wins over the copy returned when the run was started.
  const current = latest && polled.data?.id === latest.id ? polled.data : latest

  const selected = useMemo(() => tool.models.find((model) => model.key === modelKey), [tool.models, modelKey])
  const price = useQuote(
    selected?.usable ? { kind: 'tool', tool: tool.key, model_key: modelKey, size: tool.sizes ? size : undefined, prompt: prompt || 'x' } : null,
  )
  const needsKeyframe = tool.key === 'video'
  const canRun = prompt.trim().length >= 3 && price.data?.affordable !== false
    && Boolean(selected?.usable)
    && !run.isPending
    && (!needsKeyframe || keyframe.trim().length > 0)

  const onRun = () => {
    run.mutate(
      {
        prompt: prompt.trim(),
        model_key: modelKey,
        size: tool.sizes ? size : undefined,
        project_id: projectId === NONE ? undefined : projectId,
        image_url: needsKeyframe ? keyframe.trim() : tool.key === 'image' ? referenceUrl : undefined,
        resolution: needsKeyframe ? resolution : undefined,
        styles: Object.keys(styles).length > 0 ? styles : undefined,
        skill_key: skillKey,
        ...choices,
      },
      { onSuccess: (generation) => setLatest(generation) },
    )
  }

  const copyKeyframeLink = async (url: string) => {
    try {
      await navigator.clipboard.writeText(url)
      toast.success('Link copied. Paste it into the video tool as the keyframe.')
    } catch {
      toast.error('Could not copy. Select the link and copy it manually.')
    }
  }

  return (
    <div className="grid gap-6 lg:grid-cols-[minmax(0,380px)_minmax(0,1fr)]">
      <Card className="glass border-border/60 lg:sticky lg:top-6 lg:self-start">
        <CardContent className="space-y-5 p-5">
          <div className="space-y-2">
            <Label htmlFor="tool-model">Model</Label>
            <Select value={modelKey} onValueChange={(value: string | null) => setModelKey(value ?? '')}>
              <SelectTrigger id="tool-model" className="w-full">
                <SelectValue>{selected ? selected.name : 'Choose a model'}</SelectValue>
              </SelectTrigger>
              <SelectContent>
                {tool.models.map((model) => (
                  <SelectItem key={model.key} value={model.key} disabled={!model.usable}>
                    {model.name} · {model.provider_key}
                    {model.price_label ? ` · ${model.price_label}` : ''}
                    {!model.usable ? ' (not connected)' : ''}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {selected && !selected.usable && (
              <p className="text-xs text-amber-300">{selected.reason}</p>
            )}
            {selected?.usable && selected.provider_key === 'echo' && (
              <p className="text-xs text-muted-foreground">
                Test model. It repeats your prompt so you can check the flow. It does not generate content.
              </p>
            )}
            {tool.models.length === 0 && (
              <p className="text-xs text-amber-300">No model is registered for this tool yet.</p>
            )}
          </div>

          {choiceEntries.map(([key, choice]) => (
            <div key={key} className="space-y-2">
              <Label>{choice.label}</Label>
              <div role="radiogroup" aria-label={choice.label} className="grid gap-2">
                {Object.entries(choice.values).map(([value, text]) => (
                  <button
                    key={value}
                    type="button"
                    role="radio"
                    aria-checked={choices[key] === value}
                    onClick={() => setChoices((current) => ({ ...current, [key]: value }))}
                    className={cn(
                      'rounded-lg border px-3 py-2 text-left text-sm transition-colors',
                      choices[key] === value
                        ? 'border-brand/60 bg-brand/10 text-foreground'
                        : 'border-border text-muted-foreground hover:text-foreground',
                    )}
                  >
                    {text}
                  </button>
                ))}
              </div>
            </div>
          ))}

          {choices.script_type === 'dialogue' && tool.voices.length === 0 && (
            <p className="rounded-lg border border-amber-400/30 bg-amber-400/5 p-3 text-xs text-amber-300">
              No voices are connected yet. A dialogue script cannot cast its characters until a voice is
              connected, so the run will fail. Choose Story (narration) to write without voices.
            </p>
          )}

          {tool.sizes && (
            <div className="space-y-2">
              <Label htmlFor="tool-size">Format</Label>
              <Select value={size} onValueChange={(value: string | null) => setSize(value ?? tool.default_size)}>
                <SelectTrigger id="tool-size" className="w-full">
                  <SelectValue>{PROJECT_FORMAT_INFO[size as ProjectFormat]?.label ?? size}</SelectValue>
                </SelectTrigger>
                <SelectContent>
                  {(Object.keys(PROJECT_FORMAT_INFO) as ProjectFormat[]).map((value) => (
                    <SelectItem key={value} value={value}>
                      {PROJECT_FORMAT_INFO[value].label} · {PROJECT_FORMAT_INFO[value].use}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}

          <div className="space-y-2">
            <Label htmlFor="tool-project">Save to project</Label>
            <Select value={projectId} onValueChange={(value: string | null) => setProjectId(value ?? NONE)}>
              <SelectTrigger id="tool-project" className="w-full">
                <SelectValue>
                  {projectId === NONE ? 'No project' : projects.find((project) => project.id === projectId)?.title}
                </SelectValue>
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={NONE}>No project</SelectItem>
                {projects.map((project) => (
                  <SelectItem key={project.id} value={project.id}>
                    {project.title}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {needsKeyframe && (
            <div className="space-y-2">
              <Label htmlFor="tool-keyframe">Keyframe image link</Label>
              <Input
                id="tool-keyframe"
                type="url"
                value={keyframe}
                onChange={(event) => setKeyframe(event.target.value)}
                placeholder="https://… (from an image run's Copy keyframe link)"
              />
              <p className="text-xs text-muted-foreground">
                Video animates one still image. Run an image first, then copy its link here.
              </p>
            </div>
          )}

          {needsKeyframe && (
            <div className="space-y-2">
              <Label htmlFor="tool-quality">Quality</Label>
              <select
                id="tool-quality"
                className="h-9 w-full rounded-lg border border-border bg-background px-3 text-sm"
                value={resolution}
                onChange={(e) => setResolution(e.target.value as '480p' | '720p' | '1080p')}
              >
                <option value="480p">480p · faster</option>
                <option value="720p">720p</option>
                <option value="1080p">1080p · sharpest</option>
              </select>
              <p className="text-xs text-muted-foreground">The video is fitted to the format above, at this quality.</p>
            </div>
          )}

          {styleCatalog.data && (
            <div className="space-y-3">
              <Label>Style</Label>
              {(['environment', 'character', 'look'] as const).map((group) => (
                <div key={group} className="space-y-1">
                  <label htmlFor={`style-${group}`} className="text-xs capitalize text-muted-foreground">
                    {group === 'environment' ? 'Where it is' : group === 'character' ? 'Who is on screen' : 'How it looks'}
                  </label>
                  <select
                    id={`style-${group}`}
                    className="h-9 w-full rounded-lg border border-border bg-background px-3 text-sm"
                    value={styles[group] ?? ''}
                    onChange={(e) => setStyles({ ...styles, [group]: e.target.value || undefined })}
                  >
                    <option value="">Let the model decide</option>
                    {styleCatalog.data.groups[group].map((option) => (
                      <option key={option.key} value={option.key}>{option.label}</option>
                    ))}
                  </select>
                </div>
              ))}
            </div>
          )}

          <div className="space-y-2">
            <div className="flex items-center justify-between gap-2">
              <Label htmlFor="tool-prompt">Prompt</Label>
              {skillLabel && <span className="text-xs text-muted-foreground">Skill: {skillLabel}</span>}
            </div>
            <Textarea
              id="tool-prompt"
              rows={6}
              value={prompt}
              onChange={(event) => setPrompt(event.target.value)}
              onKeyDown={(event) => {
                if (event.key === 'Enter' && (event.metaKey || event.ctrlKey) && canRun) onRun()
              }}
              placeholder={tool.key === 'character'
                ? 'Describe the character: age, hair, wardrobe, palette, proportions…'
                : 'Describe what you want to create…'}
            />
          </div>

          <div className="flex items-center gap-3">
            <Button className="flex-1 gap-2" disabled={!canRun} onClick={onRun}>
              {run.isPending ? <Loader2 className="size-4 animate-spin" /> : <Play className="size-4" />}
              {run.isPending ? 'Sending…' : `Run ${tool.label.toLowerCase()}`}
            </Button>
            <CostBadge quote={price.data} loading={price.isFetching} />
          </div>
          <p className="text-center text-xs text-muted-foreground">Balance {usd(me?.balance_usd)}. Each run is charged before it starts and refunded if it fails.</p>
        </CardContent>
      </Card>

      <div className="space-y-6">
        <Card className="glass border-border/60">
          <CardContent className="min-h-72 p-5">
            {run.isPending ? (
              <div className="flex h-64 flex-col items-center justify-center gap-3 text-sm text-muted-foreground">
                <Loader2 className="size-5 animate-spin" />
                Sending to the provider…
              </div>
            ) : current ? (
              <div className="space-y-3">
                <p className="text-xs text-muted-foreground">
                  {current.model_key ?? 'No model'} · {formatTime(current.created_at)}
                </p>
                <GenerationOutput generation={current} />
                {current.status === 'succeeded' && current.output_type === 'video' && current.output_url && (
                  <div className="flex flex-wrap items-end gap-2 rounded-xl border border-border/60 p-3">
                    <div className="space-y-1">
                      <Label htmlFor="trim-start">Start (s)</Label>
                      <Input id="trim-start" type="number" min={0} step="0.1" className="h-8 w-24" value={trimStart} onChange={(e) => setTrimStart(e.target.value)} />
                    </div>
                    <div className="space-y-1">
                      <Label htmlFor="trim-end">End (s)</Label>
                      <Input id="trim-end" type="number" min={0} step="0.1" className="h-8 w-24" value={trimEnd} onChange={(e) => setTrimEnd(e.target.value)} />
                    </div>
                    <Button
                      size="sm"
                      variant="outline"
                      disabled={trimming}
                      onClick={async () => {
                        setTrimming(true)
                        try {
                          const result = await trimGeneration(current.id, Number(trimStart), Number(trimEnd))
                          setLatest({ ...current, output_url: result.output_url })
                          toast.success('Video trimmed')
                        } catch (error) {
                          toast.error(apiErrorMessage(error))
                        } finally {
                          setTrimming(false)
                        }
                      }}
                    >
                      {trimming ? 'Trimming…' : 'Trim video'}
                    </Button>
                  </div>
                )}
                {current.status === 'succeeded' && current.output_type === 'image' && current.provider_url && (
                  <Button variant="outline" size="sm" className="gap-2" onClick={() => copyKeyframeLink(current.provider_url as string)}>
                    <Copy className="size-4" />
                    Copy keyframe link
                  </Button>
                )}
              </div>
            ) : (
              <div className="flex h-64 items-center justify-center text-center text-sm text-muted-foreground">
                Your result will appear here.
              </div>
            )}
          </CardContent>
        </Card>

        <section aria-label="Recent runs" className="space-y-3">
          <h2 className="text-xs font-medium uppercase tracking-[0.14em] text-muted-foreground">Recent runs</h2>
          {history.isLoading ? (
            <Skeleton className="h-24 rounded-xl" />
          ) : (history.data ?? []).length === 0 ? (
            <p className="text-sm text-muted-foreground">Nothing yet.</p>
          ) : (
            <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
              {(history.data ?? []).map((generation) => (
                <li key={generation.id} className="relative h-72">
                  {generation.output_url && generation.status === 'succeeded' && (
                    <div className="absolute right-2 top-2 z-10">
                      <DeleteMediaButton
                        iconOnly
                        label="Delete file"
                        onConfirm={async () => {
                          try {
                            await deleteGeneration(generation.id)
                            toast('File deleted')
                            await queryClient.invalidateQueries({ queryKey: ['generations'] })
                          } catch (error) {
                            toast.error(apiErrorMessage(error))
                          }
                        }}
                      />
                    </div>
                  )}
                  <button
                    type="button"
                    onClick={() => setLatest(generation)}
                    className="flex h-full w-full flex-col gap-3 overflow-hidden rounded-xl border border-border bg-card/40 p-3 text-left transition-colors hover:border-brand/40"
                  >
                    <div className="h-40 shrink-0 overflow-hidden rounded-lg bg-ink-2">
                      <GenerationOutput generation={generation} compact className="h-full" />
                    </div>
                    <p className="line-clamp-1 shrink-0 text-sm font-medium">{generation.prompt}</p>
                    <p className="mt-auto shrink-0 text-xs text-muted-foreground">
                      {generation.status === 'failed' ? 'Failed' : generation.status === 'running' ? 'Rendering' : 'Saved'} · {formatTime(generation.created_at)}
                    </p>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </section>
      </div>
    </div>
  )
}

export function ToolPage() {
  const { tool: toolKey = '' } = useParams()
  const { data: tools, isLoading } = useTools()
  const tool = tools?.find((item) => item.key === toolKey)

  return (
    <div>
      <Link to="/tools" className="mb-4 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
        <ArrowLeft className="size-4" /> Tools
      </Link>

      {isLoading ? (
        <Skeleton className="h-96 w-full rounded-xl" />
      ) : !tool ? (
        <p className="text-muted-foreground">That tool does not exist.</p>
      ) : (
        <>
          <PageHeader title={tool.label} description={tool.description} />
          <ToolWorkspace key={tool.key} tool={tool} />
        </>
      )}
    </div>
  )
}
