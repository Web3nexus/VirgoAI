import { useUsage } from '@/features/usage/hooks'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'

const STEP_LABEL: Record<string, string> = {
  transcribe: 'Transcription',
  rewrite: 'Original story',
  rewrite_retry: 'Original story (second attempt)',
  clip_analysis: 'Finding clips',
}

/** Every step of a production with its tokens, and the totals. Users see tokens and credits only. */
export function UsagePanel({ type, id }: { type: string; id: string }) {
  const { data, isLoading } = useUsage(type, id)

  if (isLoading) return <Skeleton className="h-32 rounded-xl" />
  if (!data || data.steps.length === 0) return null

  return (
    <Card className="glass border-border/60">
      <CardContent className="space-y-4 p-5">
        <div className="flex flex-wrap items-baseline justify-between gap-2">
          <h3 className="font-heading text-sm font-semibold">What this used</h3>
          <p className="text-sm">
            <span className="font-medium">{data.total_tokens.toLocaleString('en-US')}</span>
            <span className="text-muted-foreground"> model tokens · </span>
            <span className="font-medium">{data.total_platform_tokens.toLocaleString('en-US', { maximumFractionDigits: 1 })}</span>
            <span className="text-muted-foreground"> tokens</span>
          </p>
        </div>
        <table className="w-full text-left text-xs">
          <thead className="text-muted-foreground">
            <tr>
              <th className="py-1 font-medium">Step</th>
              <th className="py-1 font-medium">Model</th>
              <th className="py-1 text-right font-medium">Tokens</th>
              <th className="py-1 text-right font-medium">Tokens</th>
            </tr>
          </thead>
          <tbody>
            {data.steps.map((step, index) => (
              <tr key={`${step.step}-${index}`} className="border-t border-border/60">
                <td className="py-1.5">{STEP_LABEL[step.step] ?? step.step}{step.units ? ` · ${Math.round(step.units)} ${step.unit_label ?? ''}` : ''}</td>
                <td className="py-1.5 text-muted-foreground">{step.model ?? '—'}</td>
                <td className="py-1.5 text-right">{step.tokens ? step.tokens.toLocaleString('en-US') : '—'}</td>
                <td className="py-1.5 text-right">{step.platform_tokens.toLocaleString('en-US', { maximumFractionDigits: 1 })}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </CardContent>
    </Card>
  )
}
