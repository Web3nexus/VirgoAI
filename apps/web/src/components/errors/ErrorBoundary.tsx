import { Component, type ErrorInfo, type ReactNode } from 'react'
import { ServerErrorPage } from '@/pages/errors/ServerErrorPage'

/** Catches a crash while drawing a page, so the user sees the error page instead of a blank screen. */
export class ErrorBoundary extends Component<{ children: ReactNode }, { failed: boolean }> {
  state = { failed: false }

  static getDerivedStateFromError() {
    return { failed: true }
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    console.error('The page failed to render.', error, info.componentStack)
  }

  render() {
    if (this.state.failed) {
      return <ServerErrorPage onRetry={() => this.setState({ failed: false })} />
    }
    return this.props.children
  }
}
