import { Link } from '@/lib/router'
import { Button } from '@/components/ui/button'
import { ErrorScreen } from '@/components/errors/ErrorScreen'

export function ForbiddenPage() {
  return (
    <ErrorScreen code="403" title="You do not have access to this page" message="Your account is not allowed to open it. If you think this is a mistake, contact your workspace owner.">
      <Button nativeButton={false} render={<Link to="/app" />}>
        Back to dashboard
      </Button>
    </ErrorScreen>
  )
}
