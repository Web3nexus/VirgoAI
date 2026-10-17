import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { ErrorScreen } from '@/components/errors/ErrorScreen'

export function TooManyRequestsPage() {
  return (
    <ErrorScreen code="429" title="Too many requests" message="You have sent a lot of requests in a short time. Wait a minute, then try again.">
      <Button nativeButton={false} render={<Link to="/" />}>
        Back to dashboard
      </Button>
    </ErrorScreen>
  )
}
