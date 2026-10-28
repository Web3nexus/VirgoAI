import { HugeiconsIcon } from '@hugeicons/react'
import { ArrowDown01Icon } from '@hugeicons/core-free-icons'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { useModelOptions, type ModelCapability, type ModelOption } from '@/features/models/hooks'
import { ModelIcon } from './ModelIcon'
import { cn } from '@/lib/utils'

const COLUMNS: { capability: ModelCapability; title: string }[] = [
  { capability: 'video', title: 'Video models' },
  { capability: 'image', title: 'Image models' },
  { capability: 'text', title: 'Text models' },
]

/** The provider mark for a model, from its name. Models without a mark show their first letter. */
function brandOf(name: string): string | undefined {
  if (/seedance/i.test(name)) return 'bytedance'
  if (/hailuo|minimax/i.test(name)) return 'minimax'
  if (/nano banana|gemini|veo/i.test(name)) return 'google'
  if (/claude/i.test(name)) return 'anthropic'
  return undefined
}

/**
 * The model menu in the prompt box. The active column is the one the chosen skill makes: a thumbnail uses an image
 * model, a short film a video model. Models that cannot run yet are listed, disabled, with the reason.
 */
export function ModelMenu({
  active,
  selection,
  onSelect,
  className,
}: {
  active: ModelCapability
  selection: Record<ModelCapability, string | null>
  onSelect: (capability: ModelCapability, key: string) => void
  className?: string
}) {
  const { data } = useModelOptions()
  const models = data ? data[active].models : []
  const chosen = models.find((model) => model.key === selection[active]) ?? models.find((model) => model.usable) ?? null

  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        aria-label={`Model: ${chosen?.name ?? 'none'}`}
        title={chosen?.name}
        className={cn('inline-flex h-9 items-center gap-2 rounded-full border border-border px-3 text-sm text-foreground transition-colors hover:bg-muted/40', className)}
      >
        <ModelIcon brand={chosen ? brandOf(chosen.name) : undefined} name={chosen?.name ?? '?'} />
        <span className="max-w-[9rem] truncate">{chosen?.name ?? 'No model'}</span>
        <HugeiconsIcon icon={ArrowDown01Icon} size={12} strokeWidth={2} aria-hidden />
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" side="top" collisionPadding={24} className="max-h-[min(var(--available-height),520px)] w-[min(680px,92vw)] overflow-y-auto p-4">
        <div className="grid gap-6 sm:grid-cols-3">
          {COLUMNS.map((column) => (
            <ModelColumn
              key={column.capability}
              title={column.title}
              models={data?.[column.capability].models ?? []}
              chosen={selection[column.capability]}
              onChoose={(key) => onSelect(column.capability, key)}
            />
          ))}
        </div>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}

function ModelColumn({ title, models, chosen, onChoose }: { title: string; models: ModelOption[]; chosen: string | null; onChoose: (key: string) => void }) {
  return (
    <div>
      <div className="px-2 pb-2 text-xs uppercase tracking-wider text-muted-foreground">{title}</div>
      <ul className="flex flex-col gap-1">
        {models.map((model) => (
          <li key={model.key}>
            <DropdownMenuItem
              disabled={!model.usable}
              title={model.reason ?? undefined}
              onClick={() => onChoose(model.key)}
              className={cn('justify-between gap-2 rounded-lg px-2 py-1.5 text-sm', chosen === model.key && 'bg-brand/15')}
            >
              <span className="flex min-w-0 items-center gap-2">
                <ModelIcon brand={brandOf(model.name)} name={model.name} className={cn(!model.usable && 'opacity-50')} />
                <span className={cn('truncate', !model.usable && 'text-muted-foreground')}>{model.name}</span>
              </span>
              <span
                className={cn(
                  'shrink-0 rounded-full px-2 py-0.5 text-[11px]',
                  model.usable ? 'bg-brand/15 text-foreground' : 'border border-border text-muted-foreground',
                )}
              >
                {model.price_label ?? 'Ready'}
              </span>
            </DropdownMenuItem>
          </li>
        ))}
      </ul>
    </div>
  )
}
