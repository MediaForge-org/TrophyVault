<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { fetchHealth, type HealthResponse } from '@/api'

type State = { kind: 'loading' } | { kind: 'error' } | { kind: 'loaded'; health: HealthResponse }

const state = ref<State>({ kind: 'loading' })

onMounted(async () => {
  try {
    state.value = { kind: 'loaded', health: await fetchHealth() }
  } catch {
    state.value = { kind: 'error' }
  }
})
</script>

<template>
  <section
    class="rounded-lg border border-slate-800 bg-slate-900 px-6 py-4"
    aria-label="Backend status"
  >
    <p
      v-if="state.kind === 'loading'"
      data-testid="status"
    >
      Checking backend…
    </p>
    <p
      v-else-if="state.kind === 'error'"
      class="text-red-400"
      data-testid="status"
    >
      Backend unreachable
    </p>
    <template v-else>
      <p
        data-testid="status"
        :class="state.health.status === 'ok' ? 'text-emerald-400' : 'text-amber-400'"
      >
        Backend: {{ state.health.status }}
      </p>
      <ul class="mt-2 text-sm text-slate-400">
        <li
          v-for="(value, name) in state.health.components"
          :key="name"
        >
          {{ name }}: {{ value }}
        </li>
      </ul>
    </template>
  </section>
</template>
