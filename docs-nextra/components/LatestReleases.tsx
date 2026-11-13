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

/** Ready-to-paste clone command for the latest release of a line. */
export function CloneCommand({ line }: { line: string }) {
  const l = lines[line]
  const ref = l ? l.tag : `${line}.x`
  const text = `git clone -b ${ref} --depth 1 ${REPO}.git\ncd csweb-community\ncat VERSION        # affiche ${l ? l.version : 'la version installée'}`
  return (
    <pre style={{ padding: '0.9rem 1rem', borderRadius: 6, overflowX: 'auto', background: 'rgba(127,127,127,0.08)', margin: '0.75rem 0' }}>
      <code>{text}</code>
    </pre>
  )
}
