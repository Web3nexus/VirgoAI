// Puts the server-rendered landing page into dist/index.html, so the HTML a crawler receives already has the content.
import { readFile, writeFile } from 'node:fs/promises'
import { pathToFileURL } from 'node:url'

const { render } = await import(pathToFileURL(new URL('../dist-ssr/entry-server.js', import.meta.url).pathname).href)
const template = await readFile(new URL('../dist/index.html', import.meta.url), 'utf8')

if (!template.includes('<div id="root"></div>')) {
  throw new Error('dist/index.html has no #root placeholder to prerender into.')
}

await writeFile(new URL('../dist/index.html', import.meta.url), template.replace('<div id="root"></div>', `<div id="root">${render()}</div>`))
console.log('Prerendered the landing page into dist/index.html')
