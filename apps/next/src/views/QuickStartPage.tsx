import { useState, type FormEvent } from 'react'
import { Link, Navigate, useNavigate, useParams } from '@/lib/router'
import { AxiosError } from 'axios'
import { ArrowLeft01Icon, ArrowRight01Icon } from '@hugeicons/core-free-icons'
import { HugeiconsIcon } from '@hugeicons/react'
import { ReferenceControl, type Reference } from '@/components/skills/ReferenceControl'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { useStartProduction } from '@/features/productions/hooks'
import { skillCover, useSkills, type QuickStartField, type Skill } from '@/features/skills/hooks'
import { cn } from '@/lib/utils'

type Answers = Record<string, string | number>

/** The answers a form starts with: each choice or slider at its default, every text field empty. */
function initialAnswers(fields: QuickStartField[]): Answers {
  const answers: Answers = {}
  for (const field of fields) {
    if (field.type === 'reference') continue
    if (field.type === 'slider') answers[field.key] = Number(field.default ?? field.min ?? 0)
    else if (field.type === 'choice') answers[field.key] = field.default ?? ''
    else answers[field.key] = ''
  }
  return answers
}

/**
 * A Quick Start: a guided form for one kind of video. The answers are sent as they are, and the server writes the prompt
 * from them, so the form never builds a prompt on its own.
 */
export function QuickStartPage() {
  const { key = '' } = useParams()
  const { data: skills = [], isLoading } = useSkills()
  const skill = skills.find((item) => item.key === key)

  if (isLoading) return <p className="mt-16 text-center text-sm text-muted-foreground">Loading…</p>
  if (!skill) return <p className="mt-16 text-center text-sm text-muted-foreground">That skill does not exist.</p>
  // A skill with no form takes a plain prompt in the studio.
  if (skill.inputs.length === 0) return <Navigate to={`/app?skill=${skill.key}`} replace />

  return <QuickStartForm key={skill.key} skill={skill} />
}

function QuickStartForm({ skill }: { skill: Skill }) {
  const navigate = useNavigate()
  const start = useStartProduction()
  const [answers, setAnswers] = useState<Answers>(() => initialAnswers(skill.inputs))
  const [reference, setReference] = useState<Reference | null>(null)
  const [errors, setErrors] = useState<Record<string, string>>({})

  const cover = skillCover(skill.key)

  function setAnswer(key: string, value: string | number) {
    setAnswers((current) => ({ ...current, [key]: value }))
  }

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()

    // Only the answers the form has are sent; the server checks them against the same rules.
    const nextErrors: Record<string, string> = {}
    for (const field of skill.inputs) {
      if (!field.required) continue
      if (field.type === 'reference' && !reference) nextErrors.reference_url = `${field.label} is required.`
      if (field.type !== 'reference' && String(answers[field.key] ?? '').trim() === '') nextErrors[`inputs.${field.key}`] = `${field.label} is required.`
    }
    setErrors(nextErrors)
    if (Object.keys(nextErrors).length > 0) return

    const inputs: Answers = {}
    for (const field of skill.inputs) {
      if (field.type === 'reference') continue
      const value = answers[field.key]
      if (typeof value === 'number' || String(value).trim() !== '') inputs[field.key] = typeof value === 'string' ? value.trim() : value
    }

    try {
      const production = await start.mutateAsync({
        inputs,
        format: skill.defaults.format ?? 'landscape_16_9',
        quality: skill.defaults.quality ?? '720p',
        skill_key: skill.key,
        reference_url: reference?.url,
        // A video skill runs with its own default model; the form has no model picker.
        video_model: skill.capability === 'video' ? (skill.default_model ?? undefined) : undefined,
      })
      navigate(`/app/productions/${production.id}`)
    } catch (error) {
      // The server names the field that failed (inputs.story, reference_url, …). Show the message under it.
      if (error instanceof AxiosError) {
        const serverErrors = (error.response?.data as { errors?: Record<string, string[]> } | undefined)?.errors ?? {}
        setErrors(Object.fromEntries(Object.entries(serverErrors).map(([field, messages]) => [field, messages[0]])))
      }
    }
  }

  return (
    <div className="mx-auto w-full max-w-6xl">
      <Link to="/app/skills" className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
        <HugeiconsIcon icon={ArrowLeft01Icon} size={14} strokeWidth={1.75} aria-hidden />
        Skills
      </Link>

      <div className="mt-6 grid gap-10 lg:grid-cols-[1fr_1.1fr]">
        <section aria-label="Example" className="flex flex-col gap-4">
          <h1 className="font-heading text-3xl font-semibold tracking-tight">{skill.label}</h1>
          <p className="text-muted-foreground">{skill.description}</p>
          <div className="relative overflow-hidden rounded-2xl border border-border bg-card/60">
            <img src={cover} alt="" className="aspect-video w-full object-cover" />
            <span className="absolute left-4 top-4 rounded-full bg-black/60 px-3 py-1 text-xs text-white backdrop-blur">Example</span>
          </div>
          <p className="text-sm text-foreground/70">{skill.produces}</p>
        </section>

        <form onSubmit={submit} noValidate className="flex flex-col gap-6">
          {skill.inputs.map((field) => {
            const id = `quick-start-${field.key}`
            const error = errors[field.type === 'reference' ? 'reference_url' : `inputs.${field.key}`]

            return (
              <div key={field.key} className="flex flex-col gap-2">
                <div className="flex items-center gap-2">
                  <Label htmlFor={id} className="text-base font-medium">
                    {field.label}
                  </Label>
                  {!field.required && <span className="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">Optional</span>}
                </div>

                {field.type === 'text' && (
                  <Input id={id} value={String(answers[field.key] ?? '')} placeholder={field.placeholder} maxLength={field.max} onChange={(event) => setAnswer(field.key, event.target.value)} />
                )}

                {field.type === 'textarea' && (
                  <div className="relative">
                    <Textarea
                      id={id}
                      rows={4}
                      value={String(answers[field.key] ?? '')}
                      placeholder={field.placeholder}
                      maxLength={field.max}
                      onChange={(event) => setAnswer(field.key, event.target.value)}
                      className="pb-7"
                    />
                    <span className="pointer-events-none absolute bottom-3 right-3 text-xs tabular-nums text-muted-foreground">
                      {String(answers[field.key] ?? '').length}/{field.max}
                    </span>
                  </div>
                )}

                {field.type === 'choice' && (
                  <div role="radiogroup" aria-label={field.label} className="flex flex-wrap gap-2">
                    {Object.entries(field.options ?? {}).map(([value, text]) => {
                      const selected = answers[field.key] === value
                      return (
                        <button
                          key={value}
                          type="button"
                          role="radio"
                          aria-checked={selected}
                          onClick={() => setAnswer(field.key, selected && !field.required ? '' : value)}
                          className={cn(
                            'rounded-full border px-4 py-1.5 text-sm transition-colors',
                            selected ? 'border-brand bg-brand/15 text-foreground' : 'border-border text-muted-foreground hover:text-foreground',
                          )}
                        >
                          {text}
                        </button>
                      )
                    })}
                  </div>
                )}

                {field.type === 'slider' && (
                  <div className="flex items-center gap-4">
                    <input
                      id={id}
                      type="range"
                      min={field.min}
                      max={field.max}
                      step={field.step}
                      value={Number(answers[field.key])}
                      onChange={(event) => setAnswer(field.key, Number(event.target.value))}
                      className="flex-1 accent-brand"
                    />
                    <span className="w-24 text-right text-sm tabular-nums">
                      {String(answers[field.key])} {field.unit}
                    </span>
                  </div>
                )}

                {field.type === 'reference' && <ReferenceControl value={reference} onChange={setReference} />}

                {error && (
                  <p role="alert" className="text-sm text-destructive">
                    {error}
                  </p>
                )}
              </div>
            )
          })}

          <Button type="submit" size="lg" disabled={start.isPending} className="mt-2 w-full">
            {start.isPending ? 'Starting…' : `Direct my ${skill.label.toLowerCase()}`}
            <HugeiconsIcon icon={ArrowRight01Icon} size={16} strokeWidth={1.75} aria-hidden />
          </Button>
        </form>
      </div>
    </div>
  )
}
