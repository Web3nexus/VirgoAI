import { Link } from '@/lib/router'
import { Button } from '@/components/ui/button'
import { ErrorScreen } from '@/components/errors/ErrorScreen'

/** 500. Shown when something broke on our side. Retry is offered only when the caller can re-render. */
export function ServerErrorPage({ onRetry }: { onRetry?: () => void }) {
  return (
    <ErrorScreen code="500" title="Something went wrong on our side" message="We could not finish that. Your work is saved. Try again in a moment, and tell us if it keeps happening.">
      {onRetry && (
        <Button onClick={onRetry}>Try again</Button>
      )}
      <Button variant="outline" nativeButton={false} render={<Link to="/app" />}>
        Back to dashboard
      </Button>
    </ErrorScreen>
  )
}
