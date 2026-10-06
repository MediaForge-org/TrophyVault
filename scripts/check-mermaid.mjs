// Parses every ```mermaid block in docs/ with the real Mermaid parser.
// Usage (from a directory where `mermaid` and `jsdom` are installed): node check-mermaid.mjs <repo-root>
import fs from 'node:fs'
import path from 'node:path'
import { JSDOM } from 'jsdom'

const root = path.resolve(process.argv[2] ?? '.')
const dom = new JSDOM('<!doctype html><body></body>')
globalThis.window = dom.window
globalThis.document = dom.window.document
const mermaid = (await import('mermaid')).default

function* markdownFiles(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name)
    if (entry.isDirectory()) yield* markdownFiles(full)
    else if (entry.name.endsWith('.md')) yield full
  }
}

let blocks = 0
let failed = 0
for (const file of markdownFiles(path.join(root, 'docs'))) {
  const text = fs.readFileSync(file, 'utf8')
  for (const [i, match] of [...text.matchAll(/```mermaid\n([\s\S]*?)```/g)].entries()) {
    blocks++
    try {
      await mermaid.parse(match[1])
    } catch (error) {
      failed++
      console.error(`${path.relative(root, file)} block ${i + 1}: ${String(error.message ?? error).split('\n').slice(0, 3).join(' | ')}`)
    }
  }
}
console.log(`mermaid: ${blocks - failed}/${blocks} blocks parse`)
process.exit(failed ? 1 : 0)
