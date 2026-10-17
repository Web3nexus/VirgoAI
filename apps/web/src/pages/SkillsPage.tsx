import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { HugeiconsIcon } from '@hugeicons/react'
import { Search01Icon } from '@hugeicons/core-free-icons'
import { Input } from '@/components/ui/input'
import { SKILL_MODE_LABEL, skillCover, useSkills } from '@/features/skills/hooks'
import { cn } from '@/lib/utils'

/**
 * The skills library: browse, search and filter by category. Choosing a skill opens the studio with it selected.
 */
export function SkillsPage() {
  const navigate = useNavigate()
  const { data: skills = [], isLoading } = useSkills()
  const [tab, setTab] = useState<'library' | 'mine'>('library')
  const [query, setQuery] = useState('')
  const [category, setCategory] = useState('All')

  const categories = useMemo(() => ['All', ...Array.from(new Set(skills.map((skill) => skill.category)))], [skills])

  const visible = useMemo(() => {
    const term = query.trim().toLowerCase()
    return skills.filter((skill) => {
      if (category !== 'All' && skill.category !== category) return false
      if (term === '') return true
      return `${skill.label} ${skill.description}`.toLowerCase().includes(term)
    })
  }, [skills, query, category])

  return (
    <div className="mx-auto w-full max-w-6xl">
      <header className="flex flex-wrap items-center justify-between gap-4">
        <div role="tablist" aria-label="Skills" className="flex items-center gap-2">
          {(['library', 'mine'] as const).map((value) => (
            <button
              key={value}
              type="button"
              role="tab"
              aria-selected={tab === value}
              onClick={() => setTab(value)}
              className={cn(
                'rounded-full px-5 py-2 text-sm capitalize transition-colors',
                tab === value ? 'bg-foreground text-background' : 'text-muted-foreground hover:text-foreground',
              )}
            >
              {value === 'library' ? 'Library' : 'Mine'}
            </button>
          ))}
        </div>
        <div className="relative w-full sm:w-80">
          <HugeiconsIcon icon={Search01Icon} size={16} strokeWidth={1.75} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" aria-hidden />
          <Input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Search Skill" aria-label="Search skills" className="pl-9" />
        </div>
      </header>

      {tab === 'library' && (
        <>
          <div role="radiogroup" aria-label="Category" className="mt-6 flex flex-wrap gap-2">
            {categories.map((name) => (
              <button
                key={name}
                type="button"
                role="radio"
                aria-checked={category === name}
                onClick={() => setCategory(name)}
                className={cn(
                  'rounded-full border px-4 py-1.5 text-sm transition-colors',
                  category === name ? 'border-foreground bg-foreground text-background' : 'border-border text-muted-foreground hover:text-foreground',
                )}
              >
                {name}
              </button>
            ))}
          </div>

          {isLoading ? (
            <p className="mt-10 text-sm text-muted-foreground">Loading skills…</p>
          ) : visible.length === 0 ? (
            <p className="mt-16 text-center text-sm text-muted-foreground">No Skills match your search.</p>
          ) : (
            <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
              {visible.map((skill) => (
                <button
                  key={skill.key}
                  type="button"
                  onClick={() => navigate(skill.inputs.length > 0 ? `/quick-start/${skill.key}` : `/?skill=${skill.key}`)}
                  className="group flex flex-col overflow-hidden rounded-2xl border border-border bg-card/60 text-left transition-colors hover:border-brand/50"
                >
                  <div className="relative">
                    <img src={skillCover(skill.key)} alt="" className="aspect-video w-full object-cover" loading="lazy" />
                    <span className="absolute right-3 top-3 rounded-full bg-black/60 px-2.5 py-1 text-xs text-white backdrop-blur">
                      {SKILL_MODE_LABEL[skill.mode]}
                    </span>
                  </div>
                  <div className="flex flex-1 flex-col gap-2 p-4">
                    <div className="flex items-center justify-between gap-3">
                      <h3 className="font-medium">{skill.label}</h3>
                      <span className="rounded-full bg-foreground px-3 py-1 text-xs font-medium text-background opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100">
                        Use Skill
                      </span>
                    </div>
                    <p className="line-clamp-2 text-sm leading-relaxed text-muted-foreground">{skill.description}</p>
                    <p className="mt-auto pt-2 text-xs text-foreground/70">{skill.produces}</p>
                  </div>
                </button>
              ))}
            </div>
          )}
        </>
      )}

      {tab === 'mine' && (
        <div className="mt-16 rounded-2xl border border-dashed border-border px-6 py-16 text-center">
          <p className="font-medium">No skills of your own yet</p>
          <p className="mt-1 text-sm text-muted-foreground">Custom skills will appear here. The library above is ready to use.</p>
        </div>
      )}
    </div>
  )
}
