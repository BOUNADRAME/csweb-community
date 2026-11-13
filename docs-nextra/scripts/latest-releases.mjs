#!/usr/bin/env node
// Writes latest-releases.json: the newest release tag of each CSWeb line
// listed in versions.json (8.1 -> v8.1.*, 8.0 -> v8.0.*), read from the
// GitHub repository at build time. Runs before every `npm run build`
// (prebuild), so the docs always show the version a clone installs today.
//
// Offline or on any error, the committed file is kept as it is and the build
// goes on: a slightly old number is better than a failed deployment.
import { execFileSync } from 'node:child_process'
import { readFileSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const here = dirname(fileURLToPath(import.meta.url))
const out = join(here, '..', 'latest-releases.json')
const repo = process.env.CSWEB_REPO_URL || 'https://github.com/BOUNADRAME/csweb-community.git'
const versions = JSON.parse(readFileSync(join(here, '..', 'versions.json'), 'utf8'))

function semverKey(tag) {
  return tag.replace(/^v/, '').split('.').map(n => n.padStart(6, '0')).join('.')
}

try {
  const raw = execFileSync('git', ['ls-remote', '--tags', '--refs', repo], {
    encoding: 'utf8',
    timeout: 30000,
    stdio: ['ignore', 'pipe', 'ignore'],
  })
  const tags = raw
    .split('\n')
    .map(l => l.split('refs/tags/')[1])
    .filter(t => t && /^v\d+\.\d+\.\d+$/.test(t))

  const result = { generatedAt: new Date().toISOString().slice(0, 10), lines: {} }
  for (const v of versions.versions) {
    const line = tags
      .filter(t => t.startsWith('v' + v.version + '.'))
      .sort((a, b) => semverKey(a).localeCompare(semverKey(b)))
    const tag = line[line.length - 1]
    if (!tag) continue
    result.lines[v.version] = { tag, version: tag.slice(1), branch: v.branch, status: v.status }
  }
  if (Object.keys(result.lines).length === 0) throw new Error('no release tags found')

  writeFileSync(out, JSON.stringify(result, null, 2) + '\n')
  console.log('latest-releases.json:', Object.values(result.lines).map(l => l.tag).join(', '))
} catch (e) {
  console.warn('latest-releases: keeping the committed file (' + e.message + ')')
}
