import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { ErrorScreen } from '@/components/errors/ErrorScreen'

export function SessionExpiredPage() {
  return (
    <ErrorScreen code="419" title="Your session has expired" message="For your safety we signed you out. Sign in again, and your work will still be there.">
      <Button nativeButton={false} render={<Link to="/login" />}>
        Sign in again
      </Button>
    </ErrorScreen>
  )
}
