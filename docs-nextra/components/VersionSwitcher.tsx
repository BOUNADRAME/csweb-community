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
  const ref = useRef<HTMLDivElement>(null)
  const router = useRouter()
  const basePath = router.basePath || ''

  const current = (versions.versions as VersionEntry[]).find(
    (v) => v.version === versions.current
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
        v{versions.current}
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

          {(versions.versions as VersionEntry[]).map((v) => {
            const sc = statusConfig[v.status]
            const isCurrent = v.version === versions.current
            return (
              <a
                key={v.version}
                href={isCurrent ? basePath + '/' : v.docsUrl}
                role="option"
                aria-selected={isCurrent}
                onClick={() => setOpen(false)}
                style={{
                  display: 'block',
                  padding: '10px 12px',
                  textDecoration: 'none',
                  color: 'currentColor',
                  borderBottom: '1px solid var(--nextra-border-color, #e5e7eb)',
                  background: isCurrent ? 'var(--nextra-primary-hue-bg, rgba(37,99,235,0.04))' : 'transparent',
                  cursor: 'pointer',
                }}
              >
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 4 }}>
                  <span style={{ fontWeight: 600, fontSize: 14 }}>{v.label}</span>
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
                <div style={{ marginTop: 4 }}>
                  <a
                    href={v.githubUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    onClick={(e) => e.stopPropagation()}
                    style={{ fontSize: 11, color: 'var(--nextra-primary-hue, #2563eb)', textDecoration: 'none' }}
                  >
                    {v.branch}
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
