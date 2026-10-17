import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'

export function NotFoundPage() {
  return (
    <div className="grid min-h-svh place-items-center bg-background px-4 text-center">
      <div className="space-y-4">
        <p className="font-heading text-6xl font-semibold text-brand">404</p>
        <p className="text-muted-foreground">This page does not exist.</p>
        <Button nativeButton={false} render={<Link to="/" />}>
          Back to dashboard
        </Button>
      </div>
    </div>
  )
}
