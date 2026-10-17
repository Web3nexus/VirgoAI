import { Link } from '@/lib/router'
import { Button } from '@/components/ui/button'
import { ErrorScreen } from '@/components/errors/ErrorScreen'

export function TooManyRequestsPage() {
  return (
    <ErrorScreen code="429" title="Too many requests" message="You have sent a lot of requests in a short time. Wait a minute, then try again.">
      <Button nativeButton={false} render={<Link to="/app" />}>
        Back to dashboard
      </Button>
    </ErrorScreen>
  )
}
