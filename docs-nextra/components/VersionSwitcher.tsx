import { useState, useRef, useEffect } from 'react'
import { useRouter } from 'next/router'
import versions from '../versions.json'

type VersionStatus = 'current' | 'lts' | 'eol' | 'beta'

interface VersionEntry {
  version: string
  label: string
  status: VersionStatus
  branch: string
  php: string
  symfony: string
  docsUrl: string
  githubUrl: string
}

const statusConfig: Record<VersionStatus, { color: string; bg: string; label: string }> = {
  current: { color: '#16a34a', bg: 'rgba(22,163,74,0.12)', label: 'Current' },
  lts:     { color: '#2563eb', bg: 'rgba(37,99,235,0.12)', label: 'LTS' },
  eol:     { color: '#6b7280', bg: 'rgba(107,114,128,0.12)', label: 'EOL' },
  beta:    { color: '#d97706', bg: 'rgba(217,119,6,0.12)', label: 'Beta' },
}

export function VersionSwitcher() {
  const [open, setOpen] = useState(false)
  // The line the reader picked. The site itself is not versioned — one set of
  // pages covers both — but leaving the badge pinned to versions.current made a
  // click look like it had done nothing. It now reflects the choice, which is
  // what a dropdown showing a checkmark is expected to do.
  const [selected, setSelected] = useState<string>(versions.current)
  const ref = useRef<HTMLDivElement>(null)
  const router = useRouter()
  const basePath = router.basePath || ''

  // Restore the choice across navigations: each page mounts the component
  // afresh, so without this the badge would snap back on every click-through.
  useEffect(() => {
    try {
      const saved = window.localStorage.getItem('csweb-doc-line')
      if (saved && (versions.versions as VersionEntry[]).some((v) => v.version === saved)) {
        setSelected(saved)
      }
    } catch {
      // localStorage unavailable (private mode): fall back to the default.
    }
  }, [])

  const current = (versions.versions as VersionEntry[]).find(
    (v) => v.version === selected
  )
  const currentStatus = current ? statusConfig[current.status] : statusConfig.current

  useEffect(() => {
    function handleClick(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) {
        setOpen(false)
      }
    }
    document.addEventListener('mousedown', handleClick)
    return () => document.removeEventListener('mousedown', handleClick)
  }, [])

  return (
    <div ref={ref} style={{ position: 'relative', marginLeft: 8 }}>
      <button
        onClick={() => setOpen(!open)}
        aria-expanded={open}
        aria-haspopup="listbox"
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: 6,
          padding: '4px 10px',
          borderRadius: 6,
          border: '1px solid var(--nextra-border-color, #e5e7eb)',
          background: 'transparent',
          color: 'currentColor',
          cursor: 'pointer',
          fontSize: 13,
          fontWeight: 500,
          lineHeight: 1.4,
          whiteSpace: 'nowrap',
        }}
      >
        <span
          style={{
            display: 'inline-block',
            width: 8,
            height: 8,
            borderRadius: '50%',
            backgroundColor: currentStatus.color,
            flexShrink: 0,
          }}
        />
        v{selected}
        <svg
          width="12"
          height="12"
          viewBox="0 0 12 12"
          fill="none"
          style={{
            transform: open ? 'rotate(180deg)' : 'rotate(0deg)',
            transition: 'transform 150ms',
          }}
        >
          <path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
      </button>

      {open && (
        <div
          role="listbox"
          style={{
            position: 'absolute',
            right: 0,
            top: 'calc(100% + 6px)',
            minWidth: 280,
            borderRadius: 8,
            border: '1px solid var(--nextra-border-color, #e5e7eb)',
            background: 'var(--nextra-bg, #fff)',
            boxShadow: '0 4px 12px rgba(0,0,0,0.1)',
            zIndex: 50,
            overflow: 'hidden',
          }}
        >
          <div
            style={{
              padding: '8px 12px',
              fontSize: 11,
              fontWeight: 600,
              textTransform: 'uppercase',
              letterSpacing: '0.05em',
              color: 'var(--nextra-gray-500, #6b7280)',
              borderBottom: '1px solid var(--nextra-border-color, #e5e7eb)',
            }}
          >
            Versions
          </div>
          {/* The site itself is not versioned: there is one set of pages,
              covering 8.0 with "8.1 only" callouts where the two differ. The
              switcher points at the right branch and the comparison page — it
              does not swap the documentation. Saying so avoids the reasonable
              assumption that picking 8.1 reloads the site as 8.1. */}
          <div
            style={{
              padding: '6px 12px',
              fontSize: 11,
              lineHeight: 1.4,
              color: 'var(--nextra-gray-500, #6b7280)',
              borderBottom: '1px solid var(--nextra-border-color, #e5e7eb)',
            }}
          >
            Cette documentation couvre les deux lignes. Choisissez la branche à
            installer :
          </div>

          {(versions.versions as VersionEntry[]).map((v) => {
            const sc = statusConfig[v.status]
            // Two different things: the project's current line (which carries
            // the Current badge) and the line this reader picked (highlighted
            // and reflected in the button).
            const isProjectCurrent = v.version === versions.current
            const isSelected = v.version === selected
            return (
              <a
                key={v.version}
                // basePath applies to every internal link, not just the current
                // version: without it a relative docsUrl resolves against the
                // domain root and 404s, since the site is served from
                // /csweb-community. Absolute URLs are left untouched.
                href={
                  isProjectCurrent
                    ? basePath + '/'
                    : v.docsUrl.startsWith('http')
                      ? v.docsUrl
                      : basePath + v.docsUrl
                }
                role="option"
                aria-selected={isSelected}
                onClick={() => {
                  setSelected(v.version)
                  try {
                    window.localStorage.setItem('csweb-doc-line', v.version)
                  } catch {
                    // localStorage unavailable: the choice simply lasts for
                    // this page only.
                  }
                  setOpen(false)
                }}
                style={{
                  display: 'block',
                  padding: '10px 12px',
                  textDecoration: 'none',
                  color: 'currentColor',
                  borderBottom: '1px solid var(--nextra-border-color, #e5e7eb)',
                  background: isSelected ? 'var(--nextra-primary-hue-bg, rgba(37,99,235,0.04))' : 'transparent',
                  cursor: 'pointer',
                }}
              >
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 4 }}>
                  <span style={{ fontWeight: 600, fontSize: 14 }}>
                    {isSelected && <span style={{ marginRight: 6 }}>✓</span>}
                    {v.label}
                  </span>
                  <span
                    style={{
                      fontSize: 11,
                      fontWeight: 600,
                      padding: '2px 8px',
                      borderRadius: 9999,
                      color: sc.color,
                      backgroundColor: sc.bg,
                    }}
                  >
                    {sc.label}
                  </span>
                </div>
                <div style={{ display: 'flex', gap: 12, fontSize: 12, color: 'var(--nextra-gray-500, #6b7280)' }}>
                  <span>PHP {v.php}</span>
                  <span>Symfony {v.symfony}</span>
                </div>
                {/* The clone command for this line, so the branch to use is
                    readable straight from the switcher instead of being looked
                    up on another page. */}
                <code
                  style={{
                    display: 'block',
                    marginTop: 6,
                    padding: '4px 6px',
                    fontSize: 11,
                    lineHeight: 1.4,
                    borderRadius: 4,
                    background: 'var(--nextra-gray-100, #f3f4f6)',
                    color: 'var(--nextra-gray-700, #374151)',
                    overflowWrap: 'anywhere',
                  }}
                >
                  git clone -b {v.branch} …
                </code>
                <div style={{ marginTop: 4 }}>
                  <a
                    href={v.githubUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    onClick={(e) => e.stopPropagation()}
                    style={{ fontSize: 11, color: 'var(--nextra-primary-hue, #2563eb)', textDecoration: 'none' }}
                  >
                    Code source ({v.branch}) →
                  </a>
                </div>
              </a>
            )
          })}
        </div>
      )}
    </div>
  )
}
