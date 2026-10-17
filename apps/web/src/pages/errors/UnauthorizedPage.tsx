import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { ErrorScreen } from '@/components/errors/ErrorScreen'

export function UnauthorizedPage() {
  return (
    <ErrorScreen code="401" title="Sign in to continue" message="You need to be signed in to see this page. Your session may have ended.">
      <Button nativeButton={false} render={<Link to="/login" />}>
        Sign in
      </Button>
    </ErrorScreen>
  )
}
