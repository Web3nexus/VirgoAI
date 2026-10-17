import { Link } from '@/lib/router'
import { StatusBadge } from '@/components/projects/StatusBadge'
import { ProductionPreview } from '@/components/projects/ProductionPreview'
import { PROJECT_TYPE_LABELS, type Project } from '@/features/projects/types'

type Orientation = 'landscape' | 'vertical' | 'square'

// One frame height per orientation, so every card in a section is the same size.
const GROUPS: { key: Orientation; title: string; frameHeight: number; matches: (p: Project) => boolean }[] = [
  {
    key: 'landscape',
    title: 'Landscape 16:9',
    frameHeight: 150,
    matches: (p) => p.dimensions.width > p.dimensions.height,
  },
  {
    key: 'vertical',
    title: 'Vertical 9:16',
    frameHeight: 260,
    matches: (p) => p.dimensions.height > p.dimensions.width * 1.2,
  },
  {
    key: 'square',
    title: 'Square and portrait',
    frameHeight: 190,
    matches: (p) => p.dimensions.height <= p.dimensions.width * 1.2 && p.dimensions.height >= p.dimensions.width * 0.8,
  },
]

const MIN_CARD_WIDTH = 220

function ProductionCard({ project, frameHeight }: { project: Project; frameHeight: number }) {
  const { width, height } = project.dimensions
  const frameWidth = Math.round((frameHeight * width) / height)
  // Cards never get narrower than the caption needs, so titles and dates stay on one line.
  const cardWidth = Math.max(frameWidth, MIN_CARD_WIDTH)

  return (
    <Link
      to={`/app/projects/${project.id}`}
      className="group flex flex-col gap-3 rounded-2xl border border-border bg-card/40 p-3 transition-colors hover:border-brand/40 hover:bg-ink-800"
      style={{ width: cardWidth }}
    >
      <div className="flex justify-center rounded-xl bg-ink-2/40 py-1">
        <ProductionPreview project={project} frameHeight={frameHeight} />
      </div>
      <div className="flex items-start justify-between gap-3 px-1">
        <div className="min-w-0">
          <p className="truncate text-sm font-medium group-hover:text-brand">{project.title}</p>
          <p className="mt-0.5 text-xs text-muted-foreground">
            {PROJECT_TYPE_LABELS[project.type]} · {new Date(project.created_at).toLocaleDateString()}
          </p>
        </div>
        <StatusBadge status={project.status} />
      </div>
    </Link>
  )
}

function Section({ group, items }: { group: (typeof GROUPS)[number]; items: Project[] }) {
  return (
    <section aria-label={group.title} className={group.key === 'landscape' ? 'w-full' : 'w-max'}>
      <div className="mb-4 flex items-baseline gap-2">
        <h3 className="text-sm font-medium">{group.title}</h3>
        <span className="text-xs text-muted-foreground">{items.length}</span>
      </div>
      <ul className="flex flex-wrap items-start gap-3">
        {items.map((project) => (
          <li key={project.id}>
            <ProductionCard project={project} frameHeight={group.frameHeight} />
          </li>
        ))}
      </ul>
    </section>
  )
}

/**
 * Recent productions grouped by orientation. Landscape spans the full width. Vertical and
 * square sit side by side below it, each only as wide as its cards, so no empty columns open up.
 */
export function RecentProductions({ projects }: { projects: Project[] }) {
  const sections = GROUPS.map((group) => ({ group, items: projects.filter(group.matches) })).filter(
    (section) => section.items.length > 0,
  )

  return (
    <div className="flex flex-wrap items-start gap-x-8 gap-y-6">
      {sections.map(({ group, items }) => (
        <Section key={group.key} group={group} items={items} />
      ))}
    </div>
  )
}
