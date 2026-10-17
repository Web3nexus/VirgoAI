import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { ErrorScreen } from '@/components/errors/ErrorScreen'

export function NotFoundPage() {
  return (
    <ErrorScreen code="404" title="This page does not exist" message="The link may be old, or the page may have moved.">
      <Button nativeButton={false} render={<Link to="/" />}>
        Back to dashboard
      </Button>
    </ErrorScreen>
  )
}
