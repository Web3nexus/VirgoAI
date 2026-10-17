const usdFormat = (digits: number) =>
  new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  })

/** Dollars for display. Small unit prices need four decimals, so the digits are a parameter. */
export function usd(value: number | null | undefined, digits = 2): string {
  return usdFormat(digits).format(value ?? 0)
}

/** Dollars for a price per unit, which is often well under one cent. */
export function usdUnit(value: number): string {
  return usd(value, value < 0.01 ? 4 : 3)
}

export function percent(value: number): string {
  return `${Number.isInteger(value) ? value : value.toFixed(2)}%`
}
