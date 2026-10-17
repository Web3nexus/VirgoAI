import { useState } from 'react'
import { HugeiconsIcon } from '@hugeicons/react'
import { Delete02Icon } from '@hugeicons/core-free-icons'
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

/**
 * Deletes a video or image file after a confirmation. The record and its cost stay on the account.
 */
export function DeleteMediaButton({
  onConfirm,
  label = 'Delete',
  iconOnly = false,
  className,
}: {
  onConfirm: () => Promise<void>
  label?: string
  iconOnly?: boolean
  className?: string
}) {
  const [busy, setBusy] = useState(false)

  return (
    <AlertDialog>
      <AlertDialogTrigger
        render={
          iconOnly ? (
            <Button variant="secondary" size="icon-sm" aria-label={label} title={label} className={cn('bg-background/80 backdrop-blur', className)} />
          ) : (
            <Button variant="outline" size="sm" className={className} />
          )
        }
      >
        {iconOnly ? <HugeiconsIcon icon={Delete02Icon} size={14} strokeWidth={1.75} aria-hidden /> : (
          <>
            <HugeiconsIcon icon={Delete02Icon} size={14} strokeWidth={1.75} aria-hidden />
            {label}
          </>
        )}
      </AlertDialogTrigger>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Delete this file?</AlertDialogTitle>
          <AlertDialogDescription>
            The video or image is removed from storage and cannot be recovered. The record and what it cost stay on your account.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Keep it</AlertDialogCancel>
          <AlertDialogAction
            disabled={busy}
            onClick={async (event) => {
              event.preventDefault()
              setBusy(true)
              try {
                await onConfirm()
              } finally {
                setBusy(false)
              }
            }}
          >
            Delete
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
