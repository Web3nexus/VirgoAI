import type { DirectorScript } from '@/features/tools/director'
import { cn } from '@/lib/utils'

const PURPOSE_LABEL: Record<string, string> = {
  hook: 'Hook',
  setup: 'Setup',
  build: 'Build',
  climax: 'Climax',
  landing: 'Landing',
}

/** A director script in production terms: cast with voices, places, scenes, and the lines. */
export function DirectorScriptView({ script, className }: { script: DirectorScript; className?: string }) {
  const names = new Map(script.characters.map((c) => [c.character_id, c.name]))
  const places = new Map(script.environments.map((e) => [e.environment_id, e.name]))
  const nameOf = (id: string) => (id === 'narrator' ? 'Narrator' : names.get(id) ?? id)

  return (
    <div className={cn('space-y-6 text-sm', className)}>
      <div className="space-y-1">
        <p className="text-base font-medium">{script.title}</p>
        {script.logline && <p className="text-muted-foreground">{script.logline}</p>}
      </div>

      <section className="space-y-2" aria-label="Cast">
        <h3 className="text-[11px] font-medium uppercase tracking-[0.14em] text-muted-foreground">Cast</h3>
        <ul className="divide-y divide-border/50 rounded-lg border border-border/60">
          {script.characters.map((character) => (
            <li key={character.character_id} className="space-y-1 p-3">
              <div className="flex flex-wrap items-center gap-2">
                <span className="font-medium">{character.name}</span>
                <span className="text-xs text-muted-foreground">{character.character_id}</span>
                <span
                  className={cn(
                    'ml-auto rounded-full border px-2 py-px text-[11px]',
                    character.voice?.voice_id
                      ? 'border-emerald-400/30 text-emerald-300'
                      : 'border-amber-400/30 text-amber-300',
                  )}
                >
                  {character.voice?.voice_id
                    ? `Voice ${character.voice.voice_id} · speed ${character.voice.speed.toFixed(2)}`
                    : 'No voice'}
                </span>
              </div>
              {character.voice?.description && (
                <p className="text-xs text-muted-foreground">Voice: {character.voice.description}</p>
              )}
              <p className="text-xs text-muted-foreground">{character.identity_block}</p>
            </li>
          ))}
        </ul>
      </section>

      <section className="space-y-2" aria-label="Scenes">
        <h3 className="text-[11px] font-medium uppercase tracking-[0.14em] text-muted-foreground">Scenes</h3>
        <ol className="space-y-3">
          {script.sections.map((section, index) => (
            <li key={section.section_id} className="space-y-2 rounded-lg border border-border/60 p-3">
              <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                <span className="font-medium text-foreground">
                  {index + 1}. {PURPOSE_LABEL[section.purpose] ?? section.purpose}
                </span>
                <span>· {places.get(section.environment_id) ?? section.environment_id}</span>
                <span>
                  · {section.character_ids.map(nameOf).join(', ') || 'No characters'}
                </span>
                <span className="ml-auto">
                  {section.start_seconds}–{section.end_seconds}s
                </span>
              </div>
              {section.narration && <p className="italic text-muted-foreground">{section.narration}</p>}
              {script.lines
                .filter((line) => line.section_id === section.section_id)
                .map((line) => (
                  <p key={line.line_id} className="pl-3">
                    <span className="font-medium">{nameOf(line.speaker)}</span>
                    {line.emotion && <span className="text-xs text-muted-foreground"> ({line.emotion})</span>}
                    <span>: {line.text}</span>
                  </p>
                ))}
            </li>
          ))}
        </ol>
      </section>
    </div>
  )
}
