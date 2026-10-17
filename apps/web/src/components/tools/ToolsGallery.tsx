import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { HugeiconsIcon } from '@hugeicons/react'
import { Image02Icon, Mic02Icon, Note01Icon, VideoAiIcon } from '@hugeicons/core-free-icons'
import { useTools } from '@/features/tools/hooks'
import type { StudioTool } from '@/features/tools/types'
import { cn } from '@/lib/utils'

/** Tabs filter by what a tool makes. "Popular" shows everything. */
const TABS = [
  { key: 'popular', label: 'Popular', output: null },
  { key: 'video', label: 'Video', output: 'video' },
  { key: 'image', label: 'Image', output: 'image' },
  { key: 'audio', label: 'Audio', output: 'audio' },
  { key: 'text', label: 'Text', output: 'text' },
] as const

const OUTPUT_ICON = {
  video: VideoAiIcon,
  image: Image02Icon,
  audio: Mic02Icon,
  text: Note01Icon,
} as const

/** The Tools view: each tool with its cover, what it does, and what it makes. Opens the tool's own page. */
export function ToolsGallery() {
  const navigate = useNavigate()
  const { data: tools = [], isLoading } = useTools()
  const [tab, setTab] = useState<(typeof TABS)[number]['key']>('popular')

  const visible = useMemo(() => {
    const active = TABS.find((item) => item.key === tab)
    return tools.filter((tool) => !active?.output || tool.output === active.output)
  }, [tools, tab])

  return (
    <div className="mx-auto w-full max-w-5xl">
      <div role="tablist" aria-label="Tool categories" className="flex flex-wrap justify-center gap-2">
        {TABS.map((item) => (
          <button
            key={item.key}
            type="button"
            role="tab"
            aria-selected={tab === item.key}
            onClick={() => setTab(item.key)}
            className={cn(
              'rounded-full px-5 py-2 text-sm transition-colors',
              tab === item.key ? 'bg-foreground text-background' : 'text-muted-foreground hover:text-foreground',
            )}
          >
            {item.label}
          </button>
        ))}
      </div>

      {isLoading ? (
        <p className="mt-12 text-center text-sm text-muted-foreground">Loading tools…</p>
      ) : (
        <ul className="mt-10 grid gap-x-10 gap-y-6 sm:grid-cols-2">
          {visible.map((tool) => (
            <li key={tool.key}>
              <ToolRow tool={tool} onOpen={() => navigate(`/tools/${tool.key}`)} />
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

function ToolRow({ tool, onOpen }: { tool: StudioTool; onOpen: () => void }) {
  const icon = OUTPUT_ICON[tool.output as keyof typeof OUTPUT_ICON] ?? Note01Icon
  return (
    <button
      type="button"
      onClick={onOpen}
      className="group flex w-full items-center gap-4 rounded-2xl p-2 text-left transition-colors hover:bg-card/60"
    >
      <img src={`/tools/${tool.key}.svg`} alt="" className="h-20 w-28 shrink-0 rounded-xl object-cover" loading="lazy" />
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-2">
          <p className="font-medium group-hover:text-brand">{tool.label}</p>
          {!tool.ready && <span className="rounded-full border border-border px-2 py-0.5 text-[11px] text-muted-foreground">Not connected</span>}
        </div>
        <p className="mt-1 line-clamp-2 text-sm leading-relaxed text-muted-foreground">{tool.description}</p>
      </div>
      <span className="flex size-9 shrink-0 items-center justify-center rounded-full border border-border text-muted-foreground">
        <HugeiconsIcon icon={icon} size={16} strokeWidth={1.75} aria-hidden />
      </span>
    </button>
  )
}
