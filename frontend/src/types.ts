export const SUBSCRIPTIONS_REFRESH_EVENT = 'subscriptions:refresh'

export type Customer = {
  id: number
  name: string
  email: string
  subscription_type_id: number
  duration: string
  duration_months: number | null
  start_date: string
  expires_at: string
  reminder_sent: string | null
  subscription_status_id: number
  subscription_status_name: string
  subscription_status_changed: string
  next_subscription_status_id: number | null
  next_subscription_status_name: string | null
  next_subscription_status_at: string | null
}

export type SubscriptionType = {
  id: number
  name: string
  duration: string
  duration_months: number | null
  reminder: string
}

export type SubscriptionStatus = {
  id: number
  name: string
  duration: string | null
  subject: string | null
  message: string | null
}
