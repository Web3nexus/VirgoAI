import { useEffect, useState, type FormEvent } from 'react'
import { useStartProduction } from '@/features/productions/hooks'
import { toast } from 'sonner'
import { Link, useNavigate, useSearchParams } from '@/lib/router'
import { HugeiconsIcon } from '@hugeicons/react'
import { Add01Icon, AiSparklesIcon, ArrowRight01Icon, ArrowUp01Icon, Cancel01Icon, Image02Icon, Megaphone01Icon, Music01Icon, Wrench01Icon } from '@hugeicons/core-free-icons'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useProjects } from '@/features/projects/hooks'
import { PROJECT_FORMAT_INFO, type ProjectFormat } from '@/features/projects/types'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog'
import { AskFirstMenu } from '@/components/runs/AskFirstMenu'
import { useAskFirst } from '@/features/preferences/askFirst'
import { useCurrentUser } from '@/features/auth/hooks'
import { cn } from '@/lib/utils'
import { RecentProductions } from '@/components/projects/RecentProductions'
import { ModeMenu } from '@/components/skills/ModeMenu'
import { ReferenceControl, type Reference } from '@/components/skills/ReferenceControl'
import { ModelMenu } from '@/components/models/ModelMenu'
import { useModelOptions, type ModelCapability } from '@/features/models/hooks'
import { QualityMenu } from '@/components/projects/QualityMenu'
import { apiErrorMessage } from '@/lib/api'
import { skillCover, useSkills, type Skill } from '@/features/skills/hooks'
import { ToolsGallery } from '@/components/tools/ToolsGallery'


/** Suggestions under the prompt. Each one selects the skill it names and fills the prompt to start from. */
const CHIPS = [
  { label: 'Create product ads', icon: Megaphone01Icon, skill: 'ad_spot', prompt: 'Three product ad concepts for ' },
  { label: 'Design a film poster', icon: Image02Icon, skill: 'thumbnail', prompt: 'A film poster for ' },
  { label: 'Music for my film', icon: Music01Icon, skill: 'music_video', prompt: 'A music video concept for a song about ' },
]

export function DashboardPage() {
  const [view, setView] = useState<'agents' | 'tools'>('agents')
  const navigate = useNavigate()
  const { data: user } = useCurrentUser()
  const { data, isLoading } = useProjects()
  const [input, setInput] = useState('')
  const [quality, setQuality] = useState<'720p' | '1080p'>('720p')
  const [skill, setSkill] = useState<Skill | null>(null)
  // The skill decides the shape its output is made in. Without one, it is landscape.
  const format: ProjectFormat = skill?.defaults.format ?? 'landscape_16_9'
  const [chat, setChat] = useState(false)
  const [askFirst, setAskFirst] = useAskFirst()
  const [confirming, setConfirming] = useState(false)
  const [reference, setReference] = useState<Reference | null>(null)
  const start = useStartProduction()
  const [searchParams, setSearchParams] = useSearchParams()
  const { data: skillList = [] } = useSkills()
  const { data: modelOptions } = useModelOptions()
  // The models the user picked. Anything not picked uses the server's default for what it makes.
  const [picked, setPicked] = useState<Partial<Record<ModelCapability, string>>>({})
  const selection: Record<ModelCapability, string | null> = {
    video: picked.video ?? modelOptions?.video.default ?? null,
    image: picked.image ?? modelOptions?.image.default ?? null,
    text: picked.text ?? modelOptions?.text.default ?? null,
  }
  // A skill decides what is made, so its column is the active one. A thumbnail is an image; a short film is a video.
  const activeCapability: ModelCapability = skill?.capability ?? 'video'
  useEffect(() => {
    if (skill?.capability && skill.default_model) {
      setPicked((current) => ({ ...current, [skill.capability as ModelCapability]: skill.default_model ?? undefined }))
    }
  }, [skill])
  const mode: 'full_video' | 'image' | 'audio' = skill?.mode === 'image' || skill?.mode === 'audio' ? skill.mode : 'full_video'
  const projects = data?.data ?? []
  const firstName = user?.name.split(' ')[0]

  function submit(event?: FormEvent) {
    event?.preventDefault()
    if (input.trim() === '' || start.isPending) return
    // With "Ask first" on, the run waits for a confirmation; otherwise it starts straight away.
    if (askFirst) {
      setConfirming(true)
      return
    }
    void runPrompt()
  }

  async function runPrompt() {
    const text = input.trim()
    if (text === '' || start.isPending) return

    // Chat Mode answers in text through the story tool, so no video or image is made.
    if (chat) {
      navigate(`/app/tools/story?prompt=${encodeURIComponent(text)}&skill=agent&chat=1`)
      return
    }

    // A script skill writes the script through the story tool, so nothing is rendered and no media is charged.
    if (skill?.mode === 'text') {
      navigate(`/app/tools/story?prompt=${encodeURIComponent(text)}&skill=${skill.key}`)
      return
    }

    // Each mode opens the thing that makes it. The format is the same everywhere, so the size is never guessed.
    if (mode === 'image') {
      const refParam = reference ? `&ref=${encodeURIComponent(reference.url)}` : ''
      const modelParam = selection.image ? `&model=${encodeURIComponent(selection.image)}` : ''
      navigate(`/app/tools/image?prompt=${encodeURIComponent(text)}&size=${format}${skill ? `&skill=${skill.key}` : ''}${refParam}${modelParam}`)
      return
    }
    if (mode === 'audio') {
      navigate(`/app/tools/narration?prompt=${encodeURIComponent(text)}`)
      return
    }

    const production = await start
      .mutateAsync({ prompt: text, format, quality, skill_key: skill?.key, reference_url: reference?.url, video_model: selection.video ?? undefined })
      .catch((error) => {
        toast.error(apiErrorMessage(error))
        return null
      })
    if (production) {
      toast('Writing the script. Scenes are made one by one.')
      navigate(`/app/productions/${production.id}`)
    }
  }

  // A skill sets what is made and the defaults it was written for. With no skill, the answer is a full video.
  function chooseSkill(next: Skill | null) {
    setChat(false)
    setSkill(next)
    if (!next) return
    if (next.defaults.quality) setQuality(next.defaults.quality)
  }

  // The skills library opens the studio with ?skill=key. Apply it once, then clear it from the address.
  useEffect(() => {
    const key = searchParams.get('skill')
    if (!key) return
    const picked = skillList.find((item) => item.key === key)
    if (picked) chooseSkill(picked)
    if (picked || skillList.length > 0) setSearchParams({}, { replace: true })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [skillList, searchParams])

  // What the run will make, in the skill's own words. Tokens are reserved first and returned if the run fails.
  const runSummary = chat
    ? 'Chat Mode: a written answer from the story tool. No video or image is made.'
    : skill
      ? `${skill.label}: ${skill.produces}`
      : `Director Mode: a full video, ${PROJECT_FORMAT_INFO[format].label}, ${quality}. Tokens are reserved first and returned if the run fails.`

  function prefill(prompt: string) {
    setInput(prompt)
    document.getElementById('production-input')?.focus()
  }

  // A skill from Direct your video: Director Mode with that skill, and the prompt is ready to fill.
  function directWith(picked: Skill) {
    setChat(false)
    chooseSkill(picked)
    window.scrollTo({ top: 0, behavior: 'smooth' })
    document.getElementById('production-input')?.focus()
  }

  function startChip(chip: (typeof CHIPS)[number]) {
    chooseSkill(skillList.find((item) => item.key === chip.skill) ?? null)
    prefill(chip.prompt)
  }

  return (
    <div className="flex flex-col items-center">
      <div role="tablist" aria-label="Studio view" className="mt-8 inline-flex rounded-full border border-border bg-card/60 p-1">
        {([
          ['agents', 'Agents', AiSparklesIcon],
          ['tools', 'Tools', Wrench01Icon],
        ] as const).map(([value, label, icon]) => (
          <button
            key={value}
            type="button"
            role="tab"
            aria-selected={view === value}
            onClick={() => setView(value)}
            className={cn(
              'inline-flex items-center gap-2 rounded-full px-5 py-2 text-sm transition-colors',
              view === value ? 'bg-foreground text-background' : 'text-muted-foreground hover:text-foreground',
            )}
          >
            <HugeiconsIcon icon={icon} size={15} strokeWidth={1.75} aria-hidden />
            {label}
          </button>
        ))}
      </div>

      {view === 'tools' ? (
        <section className="mt-10 w-full">
          <ToolsGallery />
        </section>
      ) : (
        <>
      <section id="new-production" className="w-full pt-10 text-center sm:pt-14">
        <p className="text-sm text-muted-foreground">{firstName ? `Welcome back, ${firstName}` : 'Welcome back'}</p>
        <h1 className="mt-3 font-heading text-4xl font-bold tracking-tight sm:text-5xl">What are we making today?</h1>

        <form onSubmit={submit} className="mx-auto mt-8 w-full max-w-3xl">
          <div className="rounded-3xl border border-border bg-card/80 p-5 text-left shadow-[0_0_0_1px_var(--border-highlight)] backdrop-blur-[14px] transition-[border-color,box-shadow] focus-within:border-brand/50 focus-within:shadow-[0_0_0_4px_var(--brand-glow)]">
            <label htmlFor="production-input" className="sr-only">
              Describe a video, image or story, or paste a YouTube URL or transcript
            </label>
            <textarea
              id="production-input"
              value={input}
              onChange={(event) => setInput(event.target.value)}
              onKeyDown={(event) => {
                if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) submit()
              }}
              rows={4}
              placeholder="Describe a video, image or story. Or paste a YouTube URL or transcript…"
              className="w-full resize-none bg-transparent text-base leading-relaxed text-foreground placeholder:text-muted-foreground/70 focus:outline-none"
            />
            <div className="mt-4 flex items-center justify-between gap-3">
              <div className="flex min-w-0 flex-wrap items-center gap-2">
                <ReferenceControl value={reference} onChange={setReference} />
                <ModeMenu chat={chat} onChatChange={setChat} onBrowse={() => navigate('/app/skills')} />
                {skill && !chat && (
                  <button
                    type="button"
                    onClick={() => chooseSkill(null)}
                    className="inline-flex h-9 items-center gap-2 rounded-full border border-border px-3 text-sm text-muted-foreground transition-colors hover:text-foreground"
                  >
                    {skill.label}
                    <HugeiconsIcon icon={Cancel01Icon} size={12} strokeWidth={2} aria-hidden />
                  </button>
                )}
                <ModelMenu
                  active={activeCapability}
                  selection={selection}
                  onSelect={(capability, key) => setPicked((current) => ({ ...current, [capability]: key }))}
                />
                {mode === 'full_video' && !chat && <QualityMenu value={quality} onChange={setQuality} />}
              </div>
              <div className="flex shrink-0 items-center gap-2">
              <AskFirstMenu value={askFirst} onChange={setAskFirst} />
              <Button
                type="submit"
                size="icon"
                aria-label="Start production"
                disabled={input.trim() === '' || start.isPending}
                className="size-9 shrink-0 rounded-full"
              >
                <HugeiconsIcon icon={ArrowUp01Icon} size={18} strokeWidth={2} aria-hidden />
              </Button>
              </div>
            </div>
          </div>
        </form>

        <AlertDialog open={confirming} onOpenChange={setConfirming}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>Start this run?</AlertDialogTitle>
              <AlertDialogDescription>{runSummary}</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>Not yet</AlertDialogCancel>
              <AlertDialogAction onClick={() => void runPrompt()}>Start</AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>

        <div className="mx-auto mt-5 flex flex-wrap justify-center gap-2">
          {CHIPS.map((chip) => (
            <button
              key={chip.label}
              type="button"
              onClick={() => startChip(chip)}
              className="inline-flex items-center gap-2 rounded-full border border-border bg-card/60 px-4 py-2 text-sm text-muted-foreground transition-colors hover:border-brand/40 hover:text-foreground"
            >
              <HugeiconsIcon icon={chip.icon} size={15} strokeWidth={1.75} aria-hidden />
              {chip.label}
            </button>
          ))}
          <button
            type="button"
            onClick={() => navigate('/app/skills')}
            className="inline-flex items-center gap-2 rounded-full border border-border bg-card/60 px-4 py-2 text-sm text-muted-foreground transition-colors hover:border-brand/40 hover:text-foreground"
          >
            More
            <HugeiconsIcon icon={ArrowRight01Icon} size={14} strokeWidth={2} aria-hidden />
          </button>
        </div>
      </section>

      <section className="mt-20 w-full">
        <div className="mb-5 flex items-end justify-between">
          <h2 className="font-heading text-2xl font-semibold tracking-tight">Direct your video</h2>
          <Link to="/app/projects" className="inline-flex items-center gap-1 text-sm text-muted-foreground transition-colors hover:text-foreground">
            All projects
            <HugeiconsIcon icon={ArrowRight01Icon} size={14} strokeWidth={2} aria-hidden />
          </Link>
        </div>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {skillList.map((item) => (
            <button
              key={item.key}
              type="button"
              onClick={() => directWith(item)}
              className="group overflow-hidden rounded-2xl border border-border bg-card/60 text-left transition-[transform,border-color] duration-200 hover:scale-[1.02] hover:border-brand/40 motion-reduce:transform-none"
            >
              <img src={skillCover(item.key)} alt="" className="aspect-video w-full object-cover" loading="lazy" />
              <div className="px-4 py-3">
                <div className="flex items-center justify-between gap-2">
                  <span className="truncate text-sm font-medium">{item.label}</span>
                  <HugeiconsIcon icon={Add01Icon} size={14} strokeWidth={2} className="shrink-0 text-muted-foreground transition-colors group-hover:text-brand" aria-hidden />
                </div>
                <p className="mt-1 line-clamp-2 text-xs text-muted-foreground">{item.produces}</p>
              </div>
            </button>
          ))}
        </div>
      </section>

      <section className="mt-20 w-full">
        <div className="mb-5 flex items-end justify-between">
          <h2 className="font-heading text-2xl font-semibold tracking-tight">Recent productions</h2>
        </div>

        {isLoading ? (
          <div className="space-y-3">
            {Array.from({ length: 3 }).map((_, i) => (
              <Skeleton key={i} className="h-16 w-full rounded-xl" />
            ))}
          </div>
        ) : projects.length === 0 ? (
          <div className="rounded-2xl border border-dashed border-border bg-card/40 px-6 py-12 text-center">
            <p className="font-medium">No productions yet</p>
            <p className="mt-1 text-sm text-muted-foreground">Paste an input above to start your first one.</p>
          </div>
        ) : (
          <RecentProductions projects={projects.slice(0, 12)} />
        )}
      </section>
        </>
      )}
    </div>
  )
}
