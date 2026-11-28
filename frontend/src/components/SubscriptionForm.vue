<script setup lang="ts">
import type { SubscriptionStatus, SubscriptionType } from '../types'

const props = defineProps<{
  modelValue: { name: string; email: string; subscription_type_id: number; subscription_status_id: number; start_date: string }
  subscriptionTypes: SubscriptionType[]
  subscriptionStatuses: SubscriptionStatus[]
  canRemind: boolean
  busy: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: typeof props.modelValue]
  submit: []
  remind: []
}>()

function update(field: keyof typeof props.modelValue, event: Event): void {
  const target = event.target as HTMLInputElement | HTMLSelectElement
  const value = field === 'subscription_type_id' || field === 'subscription_status_id'
    ? Number(target.value)
    : target.value
  emit('update:modelValue', { ...props.modelValue, [field]: value })
}

function durationLabel(duration: string): string {
  const match = /^(\d+) (DAY|DAYS|WEEK|WEEKS|MONTH|MONTHS|YEAR|YEARS)$/i.exec(duration)
  if (!match) return duration
  const amount = Number(match[1])
  const unit = match[2].toUpperCase()
  if (unit.startsWith('DAY')) return `${amount} ${amount === 1 ? 'Tag' : 'Tage'}`
  if (unit.startsWith('WEEK')) return `${amount} ${amount === 1 ? 'Woche' : 'Wochen'}`
  if (unit.startsWith('MONTH')) return `${amount} ${amount === 1 ? 'Monat' : 'Monate'}`
  return `${amount} ${amount === 1 ? 'Jahr' : 'Jahre'}`
}
</script>

<template>
  <form class="space-y-5" @submit.prevent="emit('submit')">
    <div>
      <label for="name" class="mb-1 block text-sm font-semibold text-slate-800">Name</label>
      <input id="name" :value="modelValue.name" @input="update('name', $event)" required class="w-full rounded border border-slate-300 px-3 py-2" />
    </div>
    <div>
      <label for="email" class="mb-1 block text-sm font-semibold text-slate-800">E-Mail</label>
      <input id="email" type="email" :value="modelValue.email" @input="update('email', $event)" required class="w-full rounded border border-slate-300 px-3 py-2" />
    </div>
    <div>
      <label for="subscription_type_id" class="mb-1 block text-sm font-semibold text-slate-800">Abonnement</label>
      <select id="subscription_type_id" :value="modelValue.subscription_type_id" @change="update('subscription_type_id', $event)" required class="w-full rounded border border-slate-300 px-3 py-2">
        <option v-for="type in subscriptionTypes" :key="type.id" :value="type.id">
          {{ type.name }} ({{ durationLabel(type.duration) }})
        </option>
      </select>
    </div>
    <div>
      <label for="subscription_status_id" class="mb-1 block text-sm font-semibold text-slate-800">Status</label>
      <div class="flex gap-2">
        <select id="subscription_status_id" :value="modelValue.subscription_status_id" @change="update('subscription_status_id', $event)" required class="w-1/3 min-w-0 rounded border border-slate-300 px-3 py-2">
          <option v-for="status in subscriptionStatuses" :key="status.id" :value="status.id">
            {{ status.name }}
          </option>
        </select>
        <button
          type="button"
          :disabled="busy || !canRemind"
          class="flex shrink-0 items-center gap-2 rounded bg-ernesto-blue px-4 py-2 text-white disabled:opacity-50"
          @click="emit('remind')"
        >
          <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4 fill-none stroke-current" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.3 21h3.4" />
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />
            <path d="M2.7 5.3 5 7.6M21.3 5.3 19 7.6" />
          </svg>
          Erinnern
        </button>
      </div>
    </div>
    <div>
      <label for="start_date" class="mb-1 block text-sm font-semibold text-slate-800">Beginn</label>
      <input id="start_date" type="date" :value="modelValue.start_date" @input="update('start_date', $event)" required class="w-1/3 min-w-0 rounded border border-slate-300 px-3 py-2" />
    </div>
    <slot name="actions" :busy="busy" />
  </form>
</template>
