/// <reference types="vite/client" />

// Lets plain-TypeScript tooling (ESLint's type-aware rules) resolve .vue imports.
// vue-tsc resolves the real component types and ignores this fallback.
declare module '*.vue' {
  import type { DefineComponent } from 'vue'
  const component: DefineComponent<object, object, unknown>
  export default component
}
