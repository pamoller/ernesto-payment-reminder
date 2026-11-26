<script setup lang="ts">
import type { SubscriptionType } from '../types'

const props = defineProps<{
  modelValue: { name: string; email: string; subscription_type_id: number; start_date: string }
  subscriptionTypes: SubscriptionType[]
  busy: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: typeof props.modelValue]
  submit: []
}>()

function update(field: keyof typeof props.modelValue, event: Event): void {
  const target = event.target as HTMLInputElement | HTMLSelectElement
  const value = field === 'subscription_type_id' ? Number(target.value) : target.value
  emit('update:modelValue', { ...props.modelValue, [field]: value })
}
</script>

<template>
  <form class="space-y-5" @submit.prevent="emit('submit')">
    <div>
      <label for="name" class="mb-1 block text-sm font-semibold text-white">Name</label>
      <input id="name" :value="modelValue.name" @input="update('name', $event)" required class="w-full rounded border border-slate-300 px-3 py-2" />
    </div>
    <div>
      <label for="email" class="mb-1 block text-sm font-semibold text-white">E-Mail</label>
      <input id="email" type="email" :value="modelValue.email" @input="update('email', $event)" required class="w-full rounded border border-slate-300 px-3 py-2" />
    </div>
    <div>
      <label for="subscription_type_id" class="mb-1 block text-sm font-semibold text-white">Abonnement</label>
      <select id="subscription_type_id" :value="modelValue.subscription_type_id" @change="update('subscription_type_id', $event)" required class="w-full rounded border border-slate-300 px-3 py-2">
        <option v-for="type in subscriptionTypes" :key="type.id" :value="type.id">
          {{ type.name }} ({{ type.duration_months }} Monate)
        </option>
      </select>
    </div>
    <div>
      <label for="start_date" class="mb-1 block text-sm font-semibold text-white">Beginn</label>
      <input id="start_date" type="date" :value="modelValue.start_date" @input="update('start_date', $event)" required class="w-full rounded border border-slate-300 px-3 py-2" />
    </div>
    <slot name="actions" :busy="busy" />
  </form>
</template>
