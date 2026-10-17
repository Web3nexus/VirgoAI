import { Button } from '@/components/ui/button'
import { ErrorScreen } from '@/components/errors/ErrorScreen'

/** 503. The studio is down for maintenance or a provider it depends on is unreachable. */
export function ServiceUnavailablePage() {
  return (
    <ErrorScreen code="503" title="The studio is taking a short break" message="We are doing maintenance or a provider we depend on is not responding. Runs already started are kept. Try again shortly.">
      <Button onClick={() => window.location.reload()}>Reload</Button>
    </ErrorScreen>
  )
}
