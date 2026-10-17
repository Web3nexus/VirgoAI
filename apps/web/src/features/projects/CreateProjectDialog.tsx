import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { useCreateProject } from '@/features/projects/hooks'
import {
  PROJECT_FORMATS,
  PROJECT_FORMAT_INFO,
  PROJECT_TYPES,
  PROJECT_TYPE_LABELS,
  type ProjectFormat,
  type ProjectType,
} from '@/features/projects/types'
import { Plus } from 'lucide-react'

const schema = z.object({
  title: z.string().min(1, 'Title is required').max(200),
  description: z.string().max(5000).optional(),
  type: z.enum(PROJECT_TYPES),
  format: z.enum(PROJECT_FORMATS),
})

type FormValues = z.infer<typeof schema>

export function CreateProjectDialog({ label = 'New project', triggerClassName }: { label?: string; triggerClassName?: string }) {
  const [open, setOpen] = useState(false)
  const createProject = useCreateProject()

  const {
    register,
    handleSubmit,
    setValue,
    watch,
    reset,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { title: '', description: '', type: 'idea', format: 'landscape_16_9' },
  })

  const type = watch('type')
  const format = watch('format')

  const onSubmit = (values: FormValues) => {
    createProject.mutate(
      { title: values.title, description: values.description || null, type: values.type, format: values.format },
      {
        onSuccess: () => {
          reset()
          setOpen(false)
        },
      },
    )
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger render={<Button className={`gap-2 ${triggerClassName ?? ''}`} />}>
        <Plus className="size-4" />
        {label}
      </DialogTrigger>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Create a project</DialogTitle>
          <DialogDescription>
            Start from an idea, a YouTube URL, a transcript, a video, or a script.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="title">Title</Label>
            <Input id="title" placeholder="e.g. 5 AI tools that changed my workflow" {...register('title')} />
            {errors.title && <p className="text-xs text-destructive">{errors.title.message}</p>}
          </div>

          <div className="space-y-2">
            <Label htmlFor="type">Source</Label>
            <Select
              value={type}
              onValueChange={(value: string | null) =>
                setValue('type', value as ProjectType, { shouldValidate: true })
              }
            >
              <SelectTrigger id="type">
                <SelectValue placeholder="Select a source" />
              </SelectTrigger>
              <SelectContent>
                {PROJECT_TYPES.map((value) => (
                  <SelectItem key={value} value={value}>
                    {PROJECT_TYPE_LABELS[value]}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label htmlFor="format">Video format</Label>
            <Select
              value={format}
              onValueChange={(value: string | null) =>
                setValue('format', value as ProjectFormat, { shouldValidate: true })
              }
            >
              <SelectTrigger id="format">
                <SelectValue placeholder="Select a format" />
              </SelectTrigger>
              <SelectContent>
                {PROJECT_FORMATS.map((value) => (
                  <SelectItem key={value} value={value}>
                    {PROJECT_FORMAT_INFO[value].label} · {PROJECT_FORMAT_INFO[value].use}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <p className="text-xs text-muted-foreground">The format sets the render size of the final video.</p>
          </div>

          <div className="space-y-2">
            <Label htmlFor="description">Description</Label>
            <Textarea
              id="description"
              rows={3}
              placeholder="Optional context for this project"
              {...register('description')}
            />
            {errors.description && (
              <p className="text-xs text-destructive">{errors.description.message}</p>
            )}
          </div>

          <DialogFooter>
            <Button type="button" variant="ghost" onClick={() => setOpen(false)}>
              Cancel
            </Button>
            <Button type="submit" disabled={createProject.isPending}>
              {createProject.isPending ? 'Creating…' : 'Create project'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
