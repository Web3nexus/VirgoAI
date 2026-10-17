import { useRef, useState } from 'react'
import { toast } from 'sonner'
import { HugeiconsIcon } from '@hugeicons/react'
import { Add01Icon, Cancel01Icon, Loader } from '@hugeicons/core-free-icons'
import { uploadReference } from '@/features/uploads/api'
import { apiErrorMessage } from '@/lib/api'
import { cn } from '@/lib/utils'

export type Reference = { url: string; preview: string }

/**
 * Attach a reference image: the look the run should follow. It is uploaded once and reused by the run.
 */
export function ReferenceControl({
  value,
  onChange,
  className,
}: {
  value: Reference | null
  onChange: (reference: Reference | null) => void
  className?: string
}) {
  const input = useRef<HTMLInputElement>(null)
  const [uploading, setUploading] = useState(false)

  async function attach(file: File | undefined) {
    if (!file) return
    setUploading(true)
    try {
      const url = await uploadReference(file)
      onChange({ url, preview: URL.createObjectURL(file) })
    } catch (error) {
      toast.error(apiErrorMessage(error))
    } finally {
      setUploading(false)
      if (input.current) input.current.value = ''
    }
  }

  function remove() {
    if (value) URL.revokeObjectURL(value.preview)
    onChange(null)
  }

  if (value) {
    return (
      <div className={cn('inline-flex h-9 items-center gap-1 rounded-full border border-border py-0.5 pl-0.5 pr-1', className)}>
        <img src={value.preview} alt="Reference image" className="size-8 rounded-full object-cover" />
        <button type="button" onClick={remove} aria-label="Remove reference image" className="rounded-full p-1 hover:text-foreground">
          <HugeiconsIcon icon={Cancel01Icon} size={12} strokeWidth={2} aria-hidden />
        </button>
      </div>
    )
  }

  return (
    <>
      <input
        ref={input}
        type="file"
        accept="image/jpeg,image/png,image/webp"
        className="sr-only"
        tabIndex={-1}
        aria-hidden
        onChange={(event) => attach(event.target.files?.[0])}
      />
      <button
        type="button"
        onClick={() => input.current?.click()}
        disabled={uploading}
        aria-label={uploading ? 'Attaching reference image' : 'Attach reference image'}
        title="Attach reference image"
        className={cn('inline-flex h-9 w-9 items-center justify-center rounded-full border border-border text-muted-foreground transition-colors hover:text-foreground disabled:opacity-60', className)}
      >
        <HugeiconsIcon icon={uploading ? Loader : Add01Icon} size={16} strokeWidth={1.75} className={cn(uploading && 'animate-spin')} aria-hidden />
      </button>
    </>
  )
}
