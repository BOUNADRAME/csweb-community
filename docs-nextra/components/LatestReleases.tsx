import { Pre, Code } from 'nextra/components'
import releases from '../latest-releases.json'

type Line = { tag: string; version: string; branch: string; status: string }
const lines = releases.lines as Record<string, Line>
const REPO = 'https://github.com/BOUNADRAME/csweb-community'

/** The newest release tag of one line, e.g. <LatestTag line="8.0" /> -> v8.0.5 */
export function LatestTag({ line }: { line: string }) {
  const l = lines[line]
  return <code>{l ? l.tag : `${line}.x`}</code>
}

/**
 * Table of the latest release of each line, generated at build time from the
 * repository tags (scripts/latest-releases.mjs). Never edited by hand.
 */
export function LatestReleases() {
  const order = Object.keys(lines).sort().reverse()
  return (
    <div style={{ overflowX: 'auto', margin: '1rem 0' }}>
      <table>
        <thead>
          <tr>
            <th>Ligne</th>
            <th>Dernière version</th>
            <th>CSPro sur le terrain</th>
            <th>Tag à cloner</th>
          </tr>
        </thead>
        <tbody>
          {order.map(key => {
            const l = lines[key]
            return (
              <tr key={key}>
                <td>
                  CSWeb {key} {l.status === 'current' ? '(Current)' : l.status === 'lts' ? '(LTS)' : ''}
                </td>
                <td>
                  <a href={`${REPO}/releases/tag/${l.tag}`} target="_blank" rel="noreferrer">
                    <strong>{l.version}</strong>
                  </a>
                </td>
                <td>{key === '8.0' ? 'CSPro 8.0 et antérieur' : `CSPro ${key}`}</td>
                <td>
                  <code>{l.tag}</code>
                </td>
              </tr>
            )
          })}
        </tbody>
      </table>
      <p style={{ fontSize: '0.8rem', opacity: 0.7, marginTop: '0.25rem' }}>
        Mis à jour automatiquement chaque jour ({releases.generatedAt}).
      </p>
    </div>
  )
}

/** Latest version number of a line as plain text, e.g. <LatestVersion line="8.1" /> -> 8.1.15 */
export function LatestVersion({ line }: { line: string }) {
  const l = lines[line]
  return <>{l ? l.version : line}</>
}

/**
 * Shell lines rendered like Nextra's own code blocks (same colours, copy
 * button, word-wrap toggle), for commands built at build time.
 */
function ShellBlock({ text }: { text: string }) {
  const token = (t: string, color: string, k: number) => (
    <span key={k} style={{ color: `var(--shiki-${color})` }}>{t}</span>
  )
  return (
    <Pre hasCopyCode data-language="bash" data-theme="default">
      <Code data-language="bash" data-theme="default">
        {text.split('\n').map((line, i) => {
          const hash = line.search(/(^|\s)#/)
          const code = hash >= 0 ? line.slice(0, hash) : line
          const comment = hash >= 0 ? line.slice(hash) : ''
          const parts = code.split(/(\s+)/)
          let first = true
          const spans = parts.map((p, k) => {
            if (!p || /^\s+$/.test(p)) return p
            if (first) { first = false; return token(p, 'token-function', k) }
            if (/^-/.test(p) || /^v?\d/.test(p)) return token(p, 'token-constant', k)
            return token(p, 'token-string', k)
          })
          return (
            <span key={i} data-line="">
              {spans}
              {comment && token(comment, 'token-comment', 999)}
              {'\n'}
            </span>
          )
        })}
      </Code>
    </Pre>
  )
}

/**
 * Ready-to-paste clone command for the latest release of a line.
 * `then`: extra command lines appended after `cd csweb-community`
 * (they replace the `cat VERSION` check).
 */
export function CloneCommand({ line, then }: { line: string; then?: string }) {
  const l = lines[line]
  const ref = l ? l.tag : `${line}.x`
  const tail = then ?? `cat VERSION        # affiche ${l ? l.version : 'la version installée'}`
  return <ShellBlock text={`git clone -b ${ref} --depth 1 ${REPO}.git\ncd csweb-community\n${tail}`} />
}
