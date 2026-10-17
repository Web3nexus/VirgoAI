import { useState } from 'react'
import { HugeiconsIcon } from '@hugeicons/react'
import { ArrowDown01Icon } from '@hugeicons/core-free-icons'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { IMAGE_MODELS, TEXT_MODELS, VIDEO_MODELS, type CatalogModel } from '@/features/models/catalog'
import { ModelIcon } from './ModelIcon'
import { cn } from '@/lib/utils'

const DEFAULT_MODEL = VIDEO_MODELS[0]

/**
 * The model menu in the prompt box. The button shows the chosen model's own mark. Only connected models can be chosen.
 */
export function ModelMenu({ className }: { className?: string }) {
  const [chosen, setChosen] = useState<CatalogModel>(DEFAULT_MODEL)

  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        aria-label={`Model: ${chosen.name}`}
        title={chosen.name}
        className={cn('inline-flex h-9 items-center gap-2 rounded-full border border-border px-3 text-sm text-foreground transition-colors hover:bg-muted/40', className)}
      >
        <ModelIcon brand={chosen.brand} name={chosen.name} />
        <span className="max-w-[9rem] truncate">{chosen.name}</span>
        <HugeiconsIcon icon={ArrowDown01Icon} size={12} strokeWidth={2} aria-hidden />
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" side="top" collisionPadding={24} className="max-h-[min(var(--available-height),520px)] w-[min(680px,92vw)] overflow-y-auto p-4">
        <div className="grid gap-6 sm:grid-cols-3">
          <ModelColumn title="Video" models={VIDEO_MODELS} chosen={chosen} onChoose={setChosen} />
          <ModelColumn title="Image" models={IMAGE_MODELS} chosen={chosen} onChoose={setChosen} />
          <ModelColumn title="Text" models={TEXT_MODELS} chosen={chosen} onChoose={setChosen} />
        </div>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}

function ModelColumn({ title, models, chosen, onChoose }: { title: string; models: CatalogModel[]; chosen: CatalogModel; onChoose: (model: CatalogModel) => void }) {
  return (
    <div>
      <div className="px-2 pb-2 text-xs uppercase tracking-wider text-muted-foreground">{title} models</div>
      <ul className="flex flex-col gap-1">
        {models.map((model) => (
          <li key={model.name}>
            <DropdownMenuItem
              disabled={!model.connected}
              onClick={() => onChoose(model)}
              className={cn('justify-between gap-2 rounded-lg px-2 py-1.5 text-sm', chosen.name === model.name && 'bg-brand/15')}
            >
              <span className="flex min-w-0 items-center gap-2">
                <ModelIcon brand={model.brand} name={model.name} className={cn(!model.connected && 'opacity-50')} />
                <span className={cn('truncate', !model.connected && 'text-muted-foreground')}>{model.name}</span>
              </span>
              <span
                className={cn(
                  'shrink-0 rounded-full px-2 py-0.5 text-[11px]',
                  model.connected ? 'bg-brand/15 text-foreground' : 'border border-border text-muted-foreground',
                )}
              >
                {model.connected ? 'Connected' : 'Soon'}
              </span>
            </DropdownMenuItem>
          </li>
        ))}
      </ul>
    </div>
  )
}
