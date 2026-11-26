export const SUBSCRIPTIONS_REFRESH_EVENT = 'subscriptions:refresh'

export type Customer = {
  id: number
  name: string
  email: string
  subscription_type_id: number
  duration_months: number
  start_date: string
  expires_at: string
  reminder_sent: string | null
}

export type SubscriptionType = {
  id: number
  name: string
  duration: string
  duration_months: number
  reminder: string
}
